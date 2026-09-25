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

    public function getOvertimeToApprove(int $approverId, array $filters = []): array
    {
        $query = \App\Support\ListQuery::normalize($filters);
        $view = strtolower(trim((string) ($filters['view'] ?? 'all')));
        $allowedViews = ['all', 'action', 'done', 'auto_rejected', 'auto_approved', 'resubmitted'];
        if (!in_array($view, $allowedViews, true)) {
            $view = 'all';
        }

        $result = $this->overtimeRepo->findOvertimeToApprove($approverId, [
            'from' => $query['from'],
            'to' => $query['to'],
            'page' => $query['page'],
            'limit' => $query['limit'],
            'offset' => $query['offset'],
            'view' => $view,
        ]);

        $overtimeToApprove = $result['data'];

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
            $request['has_follow_up'] = ((int) ($request['has_follow_up'] ?? 0)) === 1;

            [$statusCode, $statusLabel] = $this->deriveRequestStatus($request, $anyoneActed);
            $request['status_code'] = $statusCode;
            $request['status_label'] = $statusLabel;

            [$actionCode, $actionLabel] = $this->deriveApproverAction($request);
            $request['action_code'] = $actionCode;
            $request['action_label'] = $actionLabel;
        }
        unset($request);

        return [
            'success' => true,
            'data' => $overtimeToApprove,
            'from' => $query['from'],
            'to' => $query['to'],
            'pagination' => $result['pagination'],
            'counts' => $result['counts'],
        ];
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
            $emptyChain = empty($request['approver_details']);
            return ($request['is_on_behalf'] || $emptyChain)
                ? ['auto_approved', 'Auto-approved']
                : ['approved', 'Approved'];
        }

        if ($anyoneActed) {
            return ['rejected', 'Rejected'];
        }

        // Auto-rejected originals keep that outcome; once a follow-up exists
        // the status becomes Re-submitted so the list shows the later action.
        if (!empty($request['has_follow_up'])) {
            return ['resubmitted', 'Re-submitted'];
        }

        return ['auto_rejected', 'Auto-rejected'];
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

    /**
     * Approved OT hours and request counts for groups this approver handles.
     *
     * @param array<int, array{id?: mixed, abbreviation?: mixed, name?: mixed}> $groups
     * @return array{success: bool, month: string, month_label: string, from: string, to: string, groups: array<int, array{id: int, abbreviation: string, name: string, minutes: int, hours: int, duration_minutes: int, duration_label: string}>, total_minutes: int, total_label: string, counts: array{total: int, approved: int, rejected: int}}
     */
    public function getGroupOtForMonth(int $approverId, array $groups, ?string $month = null): array
    {
        $currentMonth = date('Y-m');
        $month = $month && preg_match('/^\d{4}-\d{2}$/', $month) ? $month : $currentMonth;
        if ($month > $currentMonth) {
            $month = $currentMonth;
        }
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from) ?: time());
        $monthLabel = date('M Y', strtotime($from) ?: time());

        $normalized = [];
        foreach ($groups as $group) {
            $id = (int) ($group['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $normalized[$id] = [
                'id' => $id,
                'abbreviation' => (string) ($group['abbreviation'] ?? ''),
                'name' => (string) ($group['name'] ?? ''),
            ];
        }

        $minutesByGroup = $this->overtimeRepo->sumApprovedMinutesByGroupIds(
            array_keys($normalized),
            $from,
            $to
        );

        $rows = [];
        $totalMinutes = 0;
        foreach ($normalized as $id => $group) {
            $minutes = (int) ($minutesByGroup[$id] ?? 0);
            $totalMinutes += $minutes;
            $hours = intdiv($minutes, 60);
            $mins = $minutes % 60;
            $rows[] = [
                'id' => $id,
                'abbreviation' => $group['abbreviation'],
                'name' => $group['name'],
                'minutes' => $minutes,
                'hours' => $hours,
                'duration_minutes' => $mins,
                'duration_label' => OvertimeRepository::formatDurationLabel($hours, $mins),
            ];
        }

        $totalHours = intdiv($totalMinutes, 60);
        $totalMins = $totalMinutes % 60;

        return [
            'success' => true,
            'month' => $month,
            'month_label' => $monthLabel,
            'from' => $from,
            'to' => $to,
            'groups' => $rows,
            'total_minutes' => $totalMinutes,
            'total_label' => OvertimeRepository::formatDurationLabel($totalHours, $totalMins),
            'counts' => $this->overtimeRepo->countApproverRequestsInRange($approverId, $from, $to),
        ];
    }
}
