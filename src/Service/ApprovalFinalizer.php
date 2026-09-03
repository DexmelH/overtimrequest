<?php
namespace App\Service;

use App\Repository\OvertimeRepository;
use App\Service\ActivityLogger;
use App\Service\ApprovalCutoff;

class ApprovalFinalizer
{
    private OvertimeRepository $overtimeRepo;
    private ActivityLogger $logger;
    private ApprovalCutoff $cutoff;

    public function __construct(
        OvertimeRepository $overtimeRepo,
        ActivityLogger $logger,
        string $cutoffTime = '15:00'
    ) {
        $this->overtimeRepo = $overtimeRepo;
        $this->logger = $logger;
        $this->cutoff = new ApprovalCutoff($cutoffTime);
    }

    public function getCutoffTime(): string
    {
        return $this->cutoff->getCutoffTime();
    }

    public function isPastCutoff(?\DateTimeInterface $now = null): bool
    {
        return $this->cutoff->isPastCutoff($now);
    }

    /**
     * Finalize all pending requests for a calendar date (request_date).
     *
     * @return array{finalized: int, approved: int, rejected: int, auto_rejected: int, errors: string[]}
     */
    public function finalizePendingForDate(string $date): array
    {
        $summary = [
            'finalized' => 0,
            'approved' => 0,
            'rejected' => 0,
            'auto_rejected' => 0,
            'errors' => [],
        ];

        $pending = $this->overtimeRepo->findPendingRequestsForDate($date);
        foreach ($pending as $request) {
            $overtimeId = (int) $request['id'];
            try {
                $result = $this->finalizePendingRequest($overtimeId);
                if (!$result['finalized']) {
                    continue;
                }
                $summary['finalized']++;
                if ($result['decision'] === 1) {
                    $summary['approved']++;
                } else {
                    $summary['rejected']++;
                    if ($result['auto_rejected']) {
                        $summary['auto_rejected']++;
                    }
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = "Request {$overtimeId}: " . $e->getMessage();
                error_log('Approval finalizer failed for ' . $overtimeId . ': ' . $e->getMessage());
            }
        }

        return $summary;
    }

    /**
     * Apply the winning L1–L3 decision (or auto-reject) for one pending request.
     *
     * @return array{finalized: bool, decision: ?int, auto_rejected: bool}
     */
    public function finalizePendingRequest(int $overtimeId): array
    {
        if ($this->overtimeRepo->checkIfFullyApproved($overtimeId)) {
            return ['finalized' => false, 'decision' => null, 'auto_rejected' => false];
        }

        $decisions = $this->overtimeRepo->findDecisionsWithLevels($overtimeId);
        if ($decisions) {
            $winner = $this->pickHighestLevelDecision($decisions);
            $this->applyFinalDecision(
                $overtimeId,
                (int) $winner['status'],
                (string) $winner['remarks'],
                (string) ($winner['surname'] ?? 'Approver'),
                (int) $winner['approver_id'],
                false,
                (int) $winner['approval_level']
            );

            return [
                'finalized' => true,
                'decision' => (int) $winner['status'],
                'auto_rejected' => false,
            ];
        }

        $remarks = sprintf(
            'No approver action by cutoff (%s).',
            $this->cutoff->getCutoffTime()
        );
        $this->applyFinalDecision(
            $overtimeId,
            0,
            $remarks,
            'System',
            null,
            true,
            null
        );

        return ['finalized' => true, 'decision' => 0, 'auto_rejected' => true];
    }

    /**
     * Immediate finalize used when an L4 approver acts.
     */
    public function finalizeImmediate(
        int $overtimeId,
        int $decision,
        string $remarks,
        string $actorName,
        ?int $actorUserId = null,
        int $approvalLevel = 4
    ): void {
        $this->applyFinalDecision(
            $overtimeId,
            $decision,
            $remarks,
            $actorName,
            $actorUserId,
            false,
            $approvalLevel
        );
    }

    /**
     * @param array<int, array{approver_id: int, surname: string, status: int, remarks: string, approval_level: int, date_accepted: ?string}> $decisions
     * @return array{approver_id: int, surname: string, status: int, remarks: string, approval_level: int, date_accepted: ?string}
     */
    public function pickHighestLevelDecision(array $decisions): array
    {
        usort($decisions, static function (array $a, array $b): int {
            $levelCmp = $b['approval_level'] <=> $a['approval_level'];
            if ($levelCmp !== 0) {
                return $levelCmp;
            }

            return strcmp((string) ($b['date_accepted'] ?? ''), (string) ($a['date_accepted'] ?? ''));
        });

        return $decisions[0];
    }

    private function applyFinalDecision(
        int $overtimeId,
        int $decision,
        string $remarks,
        string $actorName,
        ?int $actorUserId,
        bool $autoRejected,
        ?int $approvalLevel
    ): void {
        $pdo = $this->overtimeRepo->getPdo();
        $ownTransaction = !$pdo->inTransaction();

        try {
            if ($ownTransaction) {
                $pdo->beginTransaction();
            }

            if ($this->overtimeRepo->checkIfFullyApproved($overtimeId)) {
                if ($ownTransaction) {
                    $pdo->rollBack();
                }
                return;
            }

            $this->overtimeRepo->updateOvertimeStatus($overtimeId, (string) $decision);
            if ($decision === 1) {
                $this->overtimeRepo->addAcceptedRequestToDailyReport($overtimeId);
            }
            $this->overtimeRepo->queueRequestorStatusEmail($overtimeId, $decision, $actorName);

            if ($ownTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $action = $decision === 1 ? 'request.approve.finalize' : 'request.reject.finalize';
        $this->logger->log(
            $action,
            $actorUserId,
            $actorName,
            'overtime_request',
            $overtimeId,
            [
                'decision' => $decision,
                'remarks' => $remarks,
                'auto_rejected' => $autoRejected,
                'approval_level' => $approvalLevel,
                'cutoff_time' => $this->cutoff->getCutoffTime(),
            ]
        );
    }
}
