<?php
namespace App\Controller;

use App\Repository\EmployeeRepository;
use App\Repository\GroupApproverRepository;
use App\Repository\HolidayRepository;
use App\Repository\LeaveRepository;
use App\Repository\OvertimeRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use PDO;

class OvertimeController
{
    private OvertimeRepository $overtimeRepo;
    private UserRepository $userRepo;
    private GroupApproverRepository $groupApproverRepo;
    private HolidayRepository $holidayRepo;
    private LeaveRepository $leaveRepo;
    private EmployeeRepository $employeeRepo;
    private ActivityLogger $logger;

    public function __construct(PDO $overtimePDO, PDO $userPDO, PDO $formsPDO, PDO $kdtphNewPdo, ActivityLogger $logger)
    {
        $this->overtimeRepo = new OvertimeRepository($overtimePDO);
        $this->userRepo = new UserRepository($userPDO);
        $this->groupApproverRepo = new GroupApproverRepository($overtimePDO);
        $this->holidayRepo = new HolidayRepository($userPDO);
        $this->leaveRepo = new LeaveRepository($formsPDO);
        $this->employeeRepo = new EmployeeRepository($kdtphNewPdo);
        $this->logger = $logger;
    }

    public function getHolidays(): array
    {
        $user = $this->currentUser();
        $from = trim((string) ($_GET['from'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-d');
        }

        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            $employeeId = (int) $user['id'];
        }

        return [
            'success' => true,
            'data' => $this->holidayRepo->findFromDate($from),
            'leave_weeks' => $this->leaveRepo->findAcceptedLeaveWeekRanges($employeeId, $from),
        ];
    }

    public function getApproverGroups(): array
    {
        $user = $this->currentUser();
        $approverId = (int) $user['id'];

        return [
            'success' => true,
            'is_approver' => $this->isApprover($approverId),
            'data' => $this->findApproverGroupsForUser($approverId),
        ];
    }

    public function getEmployeeGroups(): array
    {
        $user = $this->currentUser();
        $approverId = (int) $user['id'];

        if (!$this->isApprover($approverId)) {
            return ['success' => false, 'message' => 'You are not authorized to view employee group assignments.'];
        }

        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return ['success' => false, 'message' => 'Please select an employee.'];
        }

        $employee = $this->employeeRepo->findById($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found.'];
        }

        return [
            'success' => true,
            'data' => $this->employeeRepo->findGroupsByEmployeeId($employeeId),
        ];
    }

    /** @deprecated Use getApproverGroups() */
    public function getManagedGroups(): array
    {
        return $this->getApproverGroups();
    }

    public function searchApproverEmployees(): array
    {
        $user = $this->currentUser();
        $approverId = (int) $user['id'];

        if (!$this->isApprover($approverId)) {
            return ['success' => false, 'message' => 'You are not authorized to search for employees.'];
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $employees = $this->employeeRepo->searchEmployees($query);

        return [
            'success' => true,
            'data' => $employees,
        ];
    }

    /** @deprecated Use searchApproverEmployees() */
    public function searchManagedEmployees(): array
    {
        return $this->searchApproverEmployees();
    }

    public function addOvertimeOnBehalf(): array
    {
        $approver = $this->currentUser();
        $approverId = (int) $approver['id'];
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $groupID = (int) ($_POST['group'] ?? 0);
        $locationID = (int) ($_POST['location'] ?? 0);
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        $requestDate = trim((string) ($_POST['date'] ?? date('Y-m-d')));

        if (!$this->isApprover($approverId)) {
            return ['success' => false, 'message' => 'You are not authorized to submit member overtime requests.'];
        }

        if ($employeeId <= 0) {
            return ['success' => false, 'message' => 'Please select an employee.'];
        }

        $employee = $this->employeeRepo->findById($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found.'];
        }

        if ($groupID <= 0 || !$this->employeeRepo->isEmployeeInEmployeeGroup($employeeId, $groupID)) {
            return ['success' => false, 'message' => 'The selected group is not assigned to this employee.'];
        }

        if ($locationID <= 0) {
            return ['success' => false, 'message' => 'Please complete all required fields.'];
        }

        $group = $this->employeeRepo->findGroupById($groupID);
        $groupAbbrev = (string) ($group['abbreviation'] ?? '');
        [$projects, $projectError] = $this->parseProjectAllocations(
            (string) ($_POST['projects'] ?? ''),
            $groupAbbrev
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
            $approvers = $this->resolveApprovers($groupID, $groupAbbrev, $employeeId);

            foreach ($approvers as $app) {
                $this->overtimeRepo->addAcceptance($id, (int) $app['id']);
                $this->overtimeRepo->approveRequest(
                    $id,
                    (int) $app['id'],
                    'Automatically approved upon submission',
                    1
                );
            }

            $this->overtimeRepo->updateOvertimeStatus($id, 1);
            $this->overtimeRepo->addAcceptedRequestToDailyReport($id);
            $this->queueRequestorStatusEmail($id, 1, (string) ($approver['surname'] ?? 'Approver'));

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

    public function getUserHistory(): array
    {
        $user = $this->currentUser();
        return $this->overtimeRepo->findHistoryByUserId($user['id']);
    }

    public function addOvertime(): array
    {
        $user = $this->currentUser();
        $userID = $user['id'];
        $groupID = (int) ($_POST['group'] ?? 0);
        $locationID = (int) ($_POST['location'] ?? 0);
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        $requestDate = (string) ($_POST['date'] ?? date('Y-m-d'));

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
            (string) ($_POST['projects'] ?? ''),
            $groupAbbrev
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
            $approver = $this->resolveApprovers(
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
                $this->overtimeRepo->addAcceptance($id, $app['id']);
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

    public function cancelOvertime(): array
    {
        $overtimeID = (int) ($_POST['overtimeID'] ?? 0);
        $user = $this->currentUser();
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

    public function getOvertimeToApprove(): array
    {
        $user = $this->currentUser();
        $approverID = $user['id'];
        $overtimeToApprove = $this->overtimeRepo->findOvertimeToApprove($approverID);

        foreach ($overtimeToApprove as &$request) {
            $request['is_approved'] = $this->overtimeRepo->checkIfAlreadyApproved($request['id'], $approverID);
            $request['approver_details'] = $this->overtimeRepo->findApproverDetails($request['id']);
        }

        return ["success" => true, "data" => $overtimeToApprove];
    }

    public function approveOvertime(): array
    {
        $overtimeID = isset($_POST['overtimeID']) ? $_POST['overtimeID'] : 0;
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        $approved = isset($_POST['status']) ? $_POST['status'] : NULL;

        if ((int) $approved === 0 && $remarks === '') {
            return ['success' => false, 'message' => 'Remarks are required when rejecting a request.'];
        }

        $user = $this->currentUser();
        $approverID = $user['id'];
        $ifApproved = $this->overtimeRepo->checkIfFullyApproved($overtimeID);
        if ($ifApproved) {
            return ['success' => false, 'message' => "This request has already been finalized."];
        }

        $pdo = $this->overtimeRepo->getPdo();
        try {
            $pdo->beginTransaction();

            $this->overtimeRepo->approveRequest($overtimeID, $approverID, $remarks, $approved);
            $confirmApproval = $this->overtimeRepo->checkIfForApproval($overtimeID, $approved);
            if ($confirmApproval) {
                $this->overtimeRepo->updateOvertimeStatus($overtimeID, $approved);
                if ((int) $approved === 1) {
                    $this->overtimeRepo->addAcceptedRequestToDailyReport((int) $overtimeID);
                }
                $this->queueRequestorStatusEmail(
                    (int) $overtimeID,
                    (int) $approved,
                    (string) ($user['surname'] ?? 'Approver')
                );
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
            ['remarks' => $remarks, 'finalized' => $confirmApproval]
        );

        return ['success' => true, 'message' => "Overtime request updated successfully."];
    }

    private function queueRequestorStatusEmail(int $overtimeID, int $decision, string $actorName): void
    {
        $requestor = $this->overtimeRepo->findRequestorByOvertimeId($overtimeID);
        $email = trim((string) ($requestor['email'] ?? ''));

        if ($email === '') {
            error_log("Overtime {$overtimeID}: no requestor email; status notification skipped.");
            return;
        }

        $this->overtimeRepo->insertEmailQueue([
            'email_to' => $email,
            'approver_name' => $requestor['surname'] ?? 'Employee',
            'overtime_id' => $overtimeID,
            'email_type' => 'status_update',
            'decision' => $decision,
            'actor_name' => $actorName,
        ]);
    }

    /**
     * @return array<int, array{id: int, surname: string, email: string}>
     */
    private function resolveApprovers(int $groupId, string $groupAbbrev, int $userId): array
    {
        if ($groupId > 0) {
            $configured = $this->groupApproverRepo->findApproversByGroupId($groupId, $userId);
            if (!empty($configured)) {
                return $configured;
            }
        }

        if ($groupAbbrev !== '') {
            return $this->userRepo->findApprover($groupAbbrev, (string) $userId);
        }

        return [];
    }

    /** @return array<int, array{id: int, abbreviation: string, name: string}> */
    private function findApproverGroupsForUser(int $approverId): array
    {
        $groups = [];
        foreach ($this->groupApproverRepo->findApproverGroupDetails($approverId) as $row) {
            $groups[(int) $row['id']] = $row;
        }

        $picAbbrs = $this->userRepo->findFormPicGroupAbbreviationsByEmployeeId($approverId);
        foreach ($this->employeeRepo->findGroupsByAbbreviations($picAbbrs) as $row) {
            $groupId = (int) $row['id'];
            if (!$this->groupApproverRepo->hasConfiguredApprovers($groupId)) {
                $groups[$groupId] = $row;
            }
        }

        foreach ($this->overtimeRepo->findApproverGroupDetails($approverId) as $row) {
            $groups[(int) $row['id']] = $row;
        }

        $list = array_values($groups);
        usort($list, static fn(array $a, array $b): int => strcmp((string) $a['abbreviation'], (string) $b['abbreviation']));

        return $list;
    }

    /** @return int[] */
    private function getApproverGroupIds(int $approverId): array
    {
        return array_map(
            static fn(array $group): int => (int) $group['id'],
            $this->findApproverGroupsForUser($approverId)
        );
    }

    private function isApprover(int $approverId): bool
    {
        if ($approverId <= 0) {
            return false;
        }

        return $this->groupApproverRepo->isAssignedApprover($approverId)
            || $this->userRepo->isFormPicApprover($approverId);
    }

    /** @deprecated Use getApproverGroupIds() */
    private function getManagedGroupIds(int $approverId): array
    {
        return $this->getApproverGroupIds($approverId);
    }

    private function currentUser(): array
    {
        $userHash = isset($_COOKIE['userID']) ? $_COOKIE['userID'] : '';
        return $this->userRepo->findIdByHash($userHash);
    }

    /**
     * @return array{0: array<int, array{project_id: int, hours: int}>, 1: ?string}
     */
    private function parseProjectAllocations(string $json, string $groupAbbreviation): array
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

        if (!$this->overtimeRepo->projectsBelongToGroup(array_keys($seen), $groupAbbreviation)) {
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
