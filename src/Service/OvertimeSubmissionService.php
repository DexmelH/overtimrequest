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
                (int) $groupID,
                $groupAbbrev,
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
                    isset($app['role']) ? (int) $app['role'] : 1
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

        // Handled-group check is against the employee's main group only.
        $mainGroupId = (int) ($employee['group_id'] ?? 0);
        $mainGroupAbbrev = trim((string) ($employee['group_abbr'] ?? ''));
        if ($mainGroupId <= 0) {
            return ['success' => false, 'message' => 'The selected employee has no main group assigned.'];
        }
        if (!in_array($mainGroupId, $approverGroupIds, true)) {
            return ['success' => false, 'message' => 'You can only submit for employees whose main group you handle.'];
        }

        if ($groupID <= 0) {
            return ['success' => false, 'message' => 'Please select a group.'];
        }

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

        $payload = [
            'user_id' => $employeeId,
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
            // OGA / Form PIC chain follows the employee's main group, not the selected OT group.
            $approvers = $this->approverDirectory->resolveApprovers(
                $mainGroupId,
                $mainGroupAbbrev,
                $employeeId
            );

            foreach ($approvers as $app) {
                $this->overtimeRepo->addAcceptance(
                    $id,
                    (int) $app['id'],
                    isset($app['approval_level']) ? (int) $app['approval_level'] : 1
                );
                $this->overtimeRepo->approveRequest(
                    $id,
                    (int) $app['id'],
                    'Automatically approved upon submission',
                    1
                );
            }

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
    public function getUserHistory($userId): array
    {
        return $this->overtimeRepo->findHistoryByUserId($userId);
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

        $today = new \DateTime('today');
        if ($dt < $today) {
            return 'Past dates are not allowed.';
        }

        if ($relaxed) {
            return null;
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
