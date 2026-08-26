<?php
namespace App\Service;

use App\Repository\OvertimeRepository;

class OvertimeApprovalService
{
    private OvertimeRepository $overtimeRepo;
    private ApprovalFinalizer $approvalFinalizer;
    private ActivityLogger $logger;

    public function __construct(
        OvertimeRepository $overtimeRepo,
        ApprovalFinalizer $approvalFinalizer,
        ActivityLogger $logger
    ) {
        $this->overtimeRepo = $overtimeRepo;
        $this->approvalFinalizer = $approvalFinalizer;
        $this->logger = $logger;
    }

    public function getOvertimeToApprove(int $approverId): array
    {
        $overtimeToApprove = $this->overtimeRepo->findOvertimeToApprove($approverId);

        foreach ($overtimeToApprove as &$request) {
            $alreadyFinalized = $request['status'] !== null && $request['status'] !== '';
            $myDecision = null;
            $anyoneActed = false;
            foreach ($request['approver_details'] ?? [] as $detail) {
                $decision = $detail['status'] ?? null;
                if ($decision !== null && $decision !== '') {
                    $anyoneActed = true;
                }
                if ((int) ($detail['approver_id'] ?? 0) === $approverId) {
                    $myDecision = $decision;
                }
            }
            $iActed = $myDecision !== null && $myDecision !== '';

            $request['is_approved'] = $alreadyFinalized || $iActed;
            $request['is_finalized'] = $alreadyFinalized;
            $request['my_decision'] = $iActed ? (int) $myDecision : null;
            $request['can_change'] = !$alreadyFinalized;
            $request['is_on_behalf'] = ($request['submitted_by'] ?? null) !== null;
            $request['is_follow_up'] = ($request['origin_request_id'] ?? null) !== null;

            [$statusCode, $statusLabel] = $this->deriveRequestStatus($request, $anyoneActed);
            $request['status_code'] = $statusCode;
            $request['status_label'] = $statusLabel;

            [$actionCode, $actionLabel] = $this->deriveApproverAction($request);
            $request['action_code'] = $actionCode;
            $request['action_label'] = $actionLabel;
        }
        unset($request);

        usort($overtimeToApprove, static function (array $a, array $b): int {
            $aPending = !empty($a['is_approved']) ? 1 : 0;
            $bPending = !empty($b['is_approved']) ? 1 : 0;
            if ($aPending !== $bPending) {
                return $aPending <=> $bPending;
            }

            $aDate = (string) ($a['date_created'] ?? $a['request_date'] ?? '');
            $bDate = (string) ($b['date_created'] ?? $b['request_date'] ?? '');
            return strcmp($bDate, $aDate);
        });

        return ["success" => true, "data" => $overtimeToApprove];
    }

    /**
     * Where the request itself stands, independent of the current approver.
     *
     * @param array<string, mixed> $request
     * @return array{0: string, 1: string}
     */
    private function deriveRequestStatus(array $request, bool $anyoneActed): array
    {
        $status = $request['status'];

        if ((string) $status === '2') {
            return ['cancelled', 'Cancelled'];
        }

        if (!$request['is_finalized']) {
            return ['pending', 'Pending'];
        }

        if ((string) $status === '1') {
            return $request['is_on_behalf']
                ? ['auto_approved', 'Auto-approved']
                : ['approved', 'Approved'];
        }

        return $anyoneActed
            ? ['rejected', 'Rejected']
            : ['auto_rejected', 'Auto-rejected'];
    }

    /**
     * What the current approver has done about the request. Shown as its own
     * badge beside the request status while the request is still open. Once
     * finalized, only the request status is shown.
     *
     * @param array<string, mixed> $request
     * @return array{0: ?string, 1: ?string}
     */
    private function deriveApproverAction(array $request): array
    {
        if ($request['is_finalized'] || (string) $request['status'] === '2') {
            return [null, null];
        }

        if ($request['my_decision'] === 1) {
            return ['you_approved', 'You approved'];
        }

        if ($request['my_decision'] === 0) {
            return ['you_rejected', 'You rejected'];
        }

        return ['action_needed', 'Action needed'];
    }

    /**
     * @param mixed $overtimeID
     * @param mixed $approved
     */
    public function approveOvertime(array $user, $overtimeID, $approved, string $remarks): array
    {
        if ((int) $approved === 0 && $remarks === '') {
            return ['success' => false, 'message' => 'Remarks are required when rejecting a request.'];
        }

        $overtimeID = (int) $overtimeID;
        $approverID = (int) $user['id'];
        if ($overtimeID <= 0 || $approverID <= 0) {
            return ['success' => false, 'message' => 'Invalid overtime request.'];
        }

        if (!$this->overtimeRepo->requestExists($overtimeID)) {
            return ['success' => false, 'message' => 'Overtime request not found.'];
        }

        $ifApproved = $this->overtimeRepo->checkIfFullyApproved($overtimeID);
        if ($ifApproved) {
            return ['success' => false, 'message' => "This request has already been finalized."];
        }

        // Authorization comes from the assignment row, not from the UPDATE's row count,
        // so an approver may also re-submit or reverse their own decision.
        $acceptance = $this->overtimeRepo->findAcceptance($overtimeID, $approverID);
        if ($acceptance === null) {
            return ['success' => false, 'message' => 'You are not assigned to approve this request.'];
        }

        $previousDecision = $acceptance['status'];
        $isChange = $previousDecision !== null && $previousDecision !== (int) $approved;

        $pdo = $this->overtimeRepo->getPdo();
        $finalized = false;
        $level = $acceptance['approval_level'];
        try {
            $pdo->beginTransaction();

            $this->overtimeRepo->approveRequest($overtimeID, $approverID, $remarks, (int) $approved);

            if ($level === 4) {
                $this->approvalFinalizer->finalizeImmediate(
                    (int) $overtimeID,
                    (int) $approved,
                    $remarks,
                    (string) ($user['surname'] ?? 'Approver'),
                    (int) $approverID,
                    4
                );
                $finalized = true;
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Approve overtime failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update the overtime request. Please try again.'];
        }

        if ($isChange) {
            $action = 'request.decision.change';
        } else {
            $action = ((int) $approved === 1) ? 'request.approve' : 'request.reject';
        }
        $this->logger->log(
            $action,
            (int) $approverID,
            $user['surname'] ?? null,
            'overtime_request',
            (int) $overtimeID,
            [
                'remarks' => $remarks,
                'finalized' => $finalized,
                'approval_level' => $level,
                'decision' => (int) $approved,
                'previous_decision' => $previousDecision,
            ]
        );

        if ($finalized) {
            return [
                'success' => true,
                'finalized' => true,
                'message' => 'Overtime request finalized successfully.',
            ];
        }

        return [
            'success' => true,
            'finalized' => false,
            'message' => sprintf(
                '%s Final status will be set at %s (or sooner if Level 4 acts).',
                $isChange ? 'Decision changed.' : 'Decision recorded.',
                $this->approvalFinalizer->getCutoffTime()
            ),
        ];
    }

    /**
     * @param mixed $ids
     * @param mixed $approved
     */
    public function approveOvertimeBulk(array $user, $ids, $approved, string $remarks): array
    {
        $requestIds = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
        if (!$requestIds) {
            return ['success' => false, 'message' => 'No overtime requests selected.', 'ok' => 0, 'failed' => 0];
        }

        $ok = 0;
        $failed = 0;
        $errors = [];

        foreach ($requestIds as $overtimeID) {
            $result = $this->approveOvertime($user, $overtimeID, $approved, $remarks);
            if (!empty($result['success'])) {
                $ok++;
            } else {
                $failed++;
                $errors[] = [
                    'id' => $overtimeID,
                    'message' => (string) ($result['message'] ?? 'Unable to update the overtime request.'),
                ];
            }
        }

        return [
            'success' => $failed === 0,
            'ok' => $ok,
            'failed' => $failed,
            'errors' => $errors,
            'message' => $failed === 0
                ? sprintf('Updated %d request(s).', $ok)
                : sprintf('Updated %d request(s), %d failed.', $ok, $failed),
        ];
    }
}
