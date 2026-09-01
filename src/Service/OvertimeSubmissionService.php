<?php
namespace App\Service;

use App\Repository\EmployeeRepository;
use App\Repository\HolidayRepository;
use App\Repository\LeaveRepository;
use App\Repository\OvertimeRepository;

class OvertimeSubmissionService
{
    private OvertimeRepository $overtimeRepo;
    private EmployeeRepository $employeeRepo;
    private HolidayRepository $holidayRepo;
    private LeaveRepository $leaveRepo;
    private ApproverDirectoryService $approverDirectory;
    private ActivityLogger $logger;
    private ApprovalCutoff $cutoff;

    public function __construct(
        OvertimeRepository $overtimeRepo,
        EmployeeRepository $employeeRepo,
        HolidayRepository $holidayRepo,
        LeaveRepository $leaveRepo,
        ApproverDirectoryService $approverDirectory,
        ActivityLogger $logger,
        string $approvalCutoffTime = '15:00'
    ) {
        $this->overtimeRepo = $overtimeRepo;
        $this->employeeRepo = $employeeRepo;
        $this->holidayRepo = $holidayRepo;
        $this->leaveRepo = $leaveRepo;
        $this->approverDirectory = $approverDirectory;
        $this->logger = $logger;
        $this->cutoff = new ApprovalCutoff($approvalCutoffTime);
    }

    /**
     * @param array{group?: mixed, location?: mixed, remarks?: mixed, date?: mixed, projectsJson?: mixed} $input
     */
    public function addOvertime(array $user, array $input): array
    {
        if ($this->cutoff->isPastCutoff()) {
            return ['success' => false, 'message' => $this->cutoff->employeeLockMessage()];
        }

        $userID = $user['id'];
        
        $groupID = (int) ($input['group'] ?? 0);
        $locationID = (int) ($input['location'] ?? 0);
        $remarks = trim((string) ($input['remarks'] ?? ''));
        $requestDate = (string) ($input['date'] ?? date('Y-m-d'));

        $dateError = $this->validateRequestDate($requestDate, (int) $userID);
        if ($dateError !== null) {
            return ['success' => false, 'message' => $dateError];
        }

        if ($groupID <= 0 || $locationID <= 0) {
            return ['success' => false, 'message' => 'Please complete all required fields.'];
        }

        // Approvers come from the requestor's main group; the selected OT group is
        // only stored on the request record and used for project allocation.
        $mainGroupId = (int) ($user['group_id'] ?? 0);
        $mainGroupAbbrev = trim((string) ($user['abbreviation'] ?? ''));
        if ($mainGroupId <= 0) {
            return ['success' => false, 'message' => 'Your employee record has no main group assigned.'];
        }

        $group = $this->employeeRepo->findGroupById($groupID);
        $groupAbbrev = (string) ($group['abbreviation'] ?? '');
        [$projects, $projectError] = $this->parseProjectAllocations(
            (string) ($input['projectsJson'] ?? ''),
            $groupAbbrev,
            (int) $userID
        );
        if ($projectError !== null) {
            return ['success' => false, 'message' => $projectError];
        }
        $duration = array_sum(array_column($projects, 'hours'));

        $payload = [
            "user_id" => $userID,
            "group_id" => $groupID,
            "location_id" => $locationID,
            "remarks" => $remarks,
            "duration" => $duration,
            "request_date" => $requestDate
        ];

        $pdo = $this->overtimeRepo->getPdo();

        try {
            $pdo->beginTransaction();

            $id = $this->overtimeRepo->addOvertime($payload);
            $this->overtimeRepo->addProjectAllocations((int) $id, $projects);
            $approver = $this->approverDirectory->resolveApprovers(
                $mainGroupId,
                $mainGroupAbbrev,
                (int) $userID
            );
            foreach ($approver as $app) {
                $emailPayload = [
                    'email_to' => $app['email'],
                    'approver_name' => $app['surname'],
                    'overtime_id' => $id,
                    'email_type' => 'new_request',
                ];
                $this->overtimeRepo->insertEmailQueue($emailPayload);
                $this->overtimeRepo->addAcceptance(
                    $id,
                    (int) $app['id'],
                    $this->resolveApprovalLevel($app)
                );
            }

            $pdo->commit();

            $this->logger->log(
                'request.submit',
                (int) $userID,
                $user['surname'] ?? null,
                'overtime_request',
                (int) $id,
                [
                    'group_id' => $groupID,
                    'hours' => $duration,
                    'projects' => $projects,
                    'request_date' => $requestDate,
                ]
            );

            return ["success" => true, "id" => $id];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Add overtime failed: ' . $e->getMessage());
            return ["success" => false, "message" => "Failed to add overtime request. Please try again."];
        }
    }

    /**
     * @param array{employee_id?: mixed, group?: mixed, location?: mixed, remarks?: mixed, date?: mixed, projectsJson?: mixed} $input
     */
    public function addOvertimeOnBehalf(array $approver, array $input): array
    {
        $approverId = (int) $approver['id'];
        $employeeId = (int) ($input['employee_id'] ?? 0);
        $groupID = (int) ($input['group'] ?? 0);
        $locationID = (int) ($input['location'] ?? 0);
        $remarks = trim((string) ($input['remarks'] ?? ''));
        $requestDate = trim((string) ($input['date'] ?? date('Y-m-d')));

        if (!$this->approverDirectory->isApprover($approverId)) {
            return ['success' => false, 'message' => 'You are not authorized to submit member overtime requests.'];
        }

        $approverGroupIds = $this->approverDirectory->getApproverGroupIds($approverId);
        if (!$approverGroupIds) {
            return ['success' => false, 'message' => 'You are not authorized to submit member overtime requests.'];
        }

        if ($employeeId <= 0) {
            return ['success' => false, 'message' => 'Please select an employee.'];
        }

        $employee = $this->employeeRepo->findById($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found.'];
        }

        $dateError = $this->validateRequestDate($requestDate, $employeeId, true);
        if ($dateError !== null) {
            return ['success' => false, 'message' => $dateError];
        }
        // relaxed=true: format-only check; past dates and holiday/weekend rules are allowed on-behalf.

        // Approver-group check is against the employee's main group only.
        $mainGroupId = (int) ($employee['group_id'] ?? 0);
        $mainGroupAbbrev = trim((string) ($employee['group_abbr'] ?? ''));
        if ($mainGroupId <= 0) {
            return ['success' => false, 'message' => 'The selected employee has no main group assigned.'];
        }
        if (!in_array($mainGroupId, $approverGroupIds, true)) {
            return ['success' => false, 'message' => 'You can only submit for employees whose main group you approve for.'];
        }

        if ($groupID <= 0) {
            return ['success' => false, 'message' => 'Please select a group.'];
        }

        // Selected OT group must exist in employee_group for this employee (may differ from main group).
        if (!$this->employeeRepo->isEmployeeInEmployeeGroup($employeeId, $groupID)) {
            return ['success' => false, 'message' => 'The selected group is not assigned to this employee.'];
        }

        if ($locationID <= 0) {
            return ['success' => false, 'message' => 'Please complete all required fields.'];
        }

        $group = $this->employeeRepo->findGroupById($groupID);
        $groupAbbrev = (string) ($group['abbreviation'] ?? '');
        [$projects, $projectError] = $this->parseProjectAllocations(
            (string) ($input['projectsJson'] ?? ''),
            $groupAbbrev,
            $employeeId
        );
        if ($projectError !== null) {
            return ['success' => false, 'message' => $projectError];
        }
        $duration = array_sum(array_column($projects, 'hours'));

        $originRequestId = (int) ($input['origin_request_id'] ?? 0);

        $payload = [
            'user_id' => $employeeId,
            'submitted_by' => $approverId,
            'origin_request_id' => $originRequestId > 0 ? $originRequestId : null,
            'group_id' => $groupID,
            'location_id' => $locationID,
            'remarks' => $remarks,
            'duration' => $duration,
            'request_date' => $requestDate,
        ];

        $pdo = $this->overtimeRepo->getPdo();

        try {
            $pdo->beginTransaction();

            $id = (int) $this->overtimeRepo->addOvertime($payload);
            $this->overtimeRepo->addProjectAllocations($id, $projects);

            // Only the filing approver is recorded. The rest of the main group's
            // chain is resolved purely to snapshot this approver's own level.
            $approvers = $this->approverDirectory->resolveApprovers(
                $mainGroupId,
                $mainGroupAbbrev,
                $employeeId
            );
            $approverLevel = 1;
            foreach ($approvers as $app) {
                if ((int) $app['id'] === $approverId) {
                    $approverLevel = $this->resolveApprovalLevel($app);
                    break;
                }
            }

            $this->overtimeRepo->addAcceptance($id, $approverId, $approverLevel);
            $this->overtimeRepo->approveRequest(
                $id,
                $approverId,
                'Automatically approved upon submission',
                1
            );

            $this->overtimeRepo->updateOvertimeStatus($id, 1);
            $this->overtimeRepo->addAcceptedRequestToDailyReport($id);
            $this->overtimeRepo->queueRequestorStatusEmail($id, 1, (string) ($approver['surname'] ?? 'Approver'));

            $pdo->commit();

            $this->logger->log(
                'request.submit.on_behalf',
                $approverId,
                $approver['surname'] ?? null,
                'overtime_request',
                $id,
                [
                    'employee_id' => $employeeId,
                    'employee_name' => trim(($employee['surname'] ?? '') . ' ' . ($employee['firstname'] ?? '')),
                    'group_id' => $groupID,
                    'group_abbr' => $groupAbbrev !== '' ? $groupAbbrev : null,
                    'main_group_id' => $mainGroupId > 0 ? $mainGroupId : null,
                    'main_group_abbr' => $mainGroupAbbrev !== '' ? $mainGroupAbbrev : null,
                    'hours' => $duration,
                    'projects' => $projects,
                    'request_date' => $requestDate,
                    'auto_approved' => true,
                    'approval_level' => $approverLevel,
                    'origin_request_id' => $originRequestId > 0 ? $originRequestId : null,
                ]
            );

            return [
                'success' => true,
                'id' => $id,
                'message' => 'The overtime request has been submitted and approved.',
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Add overtime on behalf failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to submit the overtime request. Please try again.'];
        }
    }

    /**
     * Re-file an auto-rejected request as a fresh on-behalf submission.
     *
     * The original request is never modified; the copy goes through the normal
     * on-behalf path so all authorization and validation rules still apply.
     */
    public function resubmitAsFollowUp(array $approver, int $overtimeId): array
    {
        if ($overtimeId <= 0) {
            return ['success' => false, 'message' => 'Invalid request ID.'];
        }

        $original = $this->overtimeRepo->findRequestWithDecisionCount($overtimeId);
        if ($original === null) {
            return ['success' => false, 'message' => 'Overtime request not found.'];
        }

        if ((string) ($original['status'] ?? '') !== '0') {
            return ['success' => false, 'message' => 'Only auto-rejected requests can be re-submitted.'];
        }

        if ((int) ($original['acted_count'] ?? 0) > 0) {
            return [
                'success' => false,
                'message' => 'This request was rejected by an approver, so it cannot be re-submitted this way.',
            ];
        }

        if ($this->overtimeRepo->hasFollowUp($overtimeId)) {
            return ['success' => false, 'message' => 'This request has already been re-submitted.'];
        }

        $projects = $this->overtimeRepo->findProjectsByRequestIds([$overtimeId])[$overtimeId] ?? [];
        if (!$projects) {
            return ['success' => false, 'message' => 'The original request has no projects to copy.'];
        }

        $projectsJson = json_encode(array_map(static function (array $project): array {
            return [
                'project_id' => (int) $project['project_id'],
                'hours' => (int) $project['hours'],
            ];
        }, $projects));

        return $this->addOvertimeOnBehalf($approver, [
            'employee_id' => (int) $original['user_id'],
            'group' => (int) $original['group_id'],
            'location' => (int) $original['location_id'],
            'remarks' => (string) ($original['remarks'] ?? ''),
            'date' => (string) $original['request_date'],
            'projectsJson' => (string) $projectsJson,
            'origin_request_id' => $overtimeId,
        ]);
    }

    public function cancelOvertime(array $user, int $overtimeID): array
    {
        $userID = (int) $user['id'];

        if ($overtimeID <= 0) {
            return ['success' => false, 'message' => 'Invalid request ID.'];
        }

        $request = $this->overtimeRepo->findOwnedPendingRequest($overtimeID, $userID);
        if (!$request) {
            return ['success' => false, 'message' => 'Request not found.'];
        }
        if ($request['status'] !== null) {
            return ['success' => false, 'message' => 'Only pending requests can be cancelled.'];
        }

        $pdo = $this->overtimeRepo->getPdo();
        try {
            $pdo->beginTransaction();

            if (!$this->overtimeRepo->cancelRequest($overtimeID, $userID)) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Unable to cancel this request.'];
            }

            $pics = $this->overtimeRepo->findPicsForOvertime($overtimeID);
            foreach ($pics as $pic) {
                $email = trim((string) ($pic['email'] ?? ''));
                if ($email === '') {
                    continue;
                }
                $this->overtimeRepo->insertEmailQueue([
                    'email_to' => $email,
                    'approver_name' => $pic['surname'] ?? 'PIC',
                    'overtime_id' => $overtimeID,
                    'email_type' => 'request_cancelled',
                    'actor_name' => $user['surname'] ?? 'Employee',
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Cancel overtime failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to cancel request. Please try again.'];
        }

        $this->logger->log(
            'request.cancel',
            $userID,
            $user['surname'] ?? null,
            'overtime_request',
            $overtimeID,
            ['group' => $request['abbreviation'] ?? null]
        );

        return ['success' => true, 'message' => 'Request cancelled successfully.'];
    }

    /** @param string|int $userId */
    public function getUserHistory($userId, array $filters = []): array
    {
        $query = \App\Support\ListQuery::normalize($filters);
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $allowedStatus = ['pending', 'approved', 'denied', 'cancelled'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = '';
        }

        $result = $this->overtimeRepo->findHistoryByUserId((string) $userId, [
            'from' => $query['from'],
            'to' => $query['to'],
            'page' => $query['page'],
            'limit' => $query['limit'],
            'offset' => $query['offset'],
            'status' => $status,
            'q' => trim((string) ($filters['q'] ?? '')),
        ]);

        return [
            'success' => true,
            'data' => $result['data'],
            'from' => $query['from'],
            'to' => $query['to'],
            'pagination' => $result['pagination'],
        ];
    }

    /**
     * OGA rows expose `approval_level`; the legacy Form PIC fallback exposes `role`.
     *
     * @param array<string, mixed> $approver
     */
    private function resolveApprovalLevel(array $approver): int
    {
        foreach (['approval_level', 'role'] as $key) {
            if (isset($approver[$key]) && (int) $approver[$key] > 0) {
                return (int) $approver[$key];
            }
        }

        return 1;
    }

    /**
     * @return array{0: array<int, array{project_id: int, hours: int}>, 1: ?string}
     */
    private function parseProjectAllocations(string $json, string $groupAbbreviation, int $actorUserId = 0): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !$decoded) {
            return [[], 'Add at least one project with its hours.'];
        }

        $projects = [];
        $seen = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                return [[], 'The project allocation list is invalid.'];
            }

            $projectId = (int) ($row['project_id'] ?? 0);
            $hours = filter_var($row['hours'] ?? null, FILTER_VALIDATE_INT);
            if ($projectId <= 0 || $hours === false || $hours <= 0) {
                return [[], 'Each project must have a positive whole number of hours.'];
            }
            if (isset($seen[$projectId])) {
                return [[], 'Each project can only be selected once.'];
            }

            $seen[$projectId] = true;
            $projects[] = ['project_id' => $projectId, 'hours' => $hours];
        }

        if (!$this->overtimeRepo->projectsBelongToGroup(array_keys($seen), $groupAbbreviation, $actorUserId)) {
            return [[], 'One or more selected projects do not belong to the selected group.'];
        }

        return [$projects, null];
    }

    private function validateRequestDate(string $date, int $employeeId, bool $relaxed = false): ?string
    {
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return 'Invalid request date.';
        }

        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            return 'Invalid request date.';
        }

        if ($relaxed) {
            return null;
        }

        $today = new \DateTime('today');
        if ($dt < $today) {
            return 'Past dates are not allowed.';
        }

        $dayOfWeek = (int) $dt->format('N');
        $isWeekend = $dayOfWeek >= 6;
        $isHoliday = $this->holidayRepo->isBlockedDate($date);

        if ($isWeekend || $isHoliday) {
            [$todayWeekStart] = LeaveRepository::workWeekBoundsForDate($today->format('Y-m-d'));
            [$dateWeekStart, $weekEnd] = LeaveRepository::workWeekBoundsForDate($date);

            if ($dateWeekStart !== $todayWeekStart) {
                return $isHoliday
                    ? 'Only holidays in the current week can be selected.'
                    : 'Only weekends in the current week can be selected.';
            }

            if ($this->leaveRepo->hasAcceptedLeaveInWeek($employeeId, $dateWeekStart, $weekEnd)) {
                if ($isHoliday) {
                    $name = $this->holidayRepo->findHolidayName($date);
                    return $name
                        ? "You have approved leave this week, so {$name} cannot be selected."
                        : 'You have approved leave this week, so this holiday cannot be selected.';
                }

                return 'You have approved leave this week, so weekend overtime cannot be requested.';
            }

            return null;
        }

        return null;
    }
}
