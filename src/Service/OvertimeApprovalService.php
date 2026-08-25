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
            foreach ($request['approver_details'] ?? [] as $detail) {
                if ((int) ($detail['approver_id'] ?? 0) === $approverId) {
                    $myDecision = $detail['status'] ?? null;
                    break;
                }
            }
            $request['is_approved'] = $alreadyFinalized || ($myDecision !== null && $myDecision !== '');
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

        $pdo = $this->overtimeRepo->getPdo();
        $finalized = false;
        $level = null;
        try {
            $pdo->beginTransaction();

            $updated = $this->overtimeRepo->approveRequest($overtimeID, $approverID, $remarks, (int) $approved);
            if (!$updated) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'You are not assigned to approve this request.'];
            }

            $level = $this->overtimeRepo->findAcceptanceLevel($overtimeID, $approverID);
            if ($level === null) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'You are not assigned to approve this request.'];
            }

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

        $action = ((int) $approved === 1) ? 'request.approve' : 'request.reject';
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
                'Decision recorded. Final status will be set at %s (or sooner if Level 4 acts).',
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
