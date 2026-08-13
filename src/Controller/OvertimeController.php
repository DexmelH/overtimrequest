<?php
namespace App\Controller;

use App\Repository\EmployeeRepository;
use App\Repository\GroupApproverRepository;
use App\Repository\HolidayRepository;
use App\Repository\LeaveRepository;
use App\Repository\OvertimeRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use App\Service\ApprovalFinalizer;
use App\Service\ApproverDirectoryService;
use App\Service\OvertimeApprovalService;
use App\Service\OvertimeSubmissionService;
use PDO;

class OvertimeController
{
    private UserRepository $userRepo;
    private HolidayRepository $holidayRepo;
    private LeaveRepository $leaveRepo;
    private EmployeeRepository $employeeRepo;
    private ApproverDirectoryService $approverDirectory;
    private OvertimeSubmissionService $submissionService;
    private OvertimeApprovalService $approvalService;

    public function __construct(
        PDO $overtimePDO,
        PDO $userPDO,
        PDO $formsPDO,
        PDO $kdtphNewPdo,
        ActivityLogger $logger,
        string $approvalCutoffTime = '15:00'
    ) {
        $overtimeRepo = new OvertimeRepository($overtimePDO);
        $this->userRepo = new UserRepository($userPDO);
        $groupApproverRepo = new GroupApproverRepository($overtimePDO);
        $this->holidayRepo = new HolidayRepository($userPDO);
        $this->leaveRepo = new LeaveRepository($formsPDO);
        $this->employeeRepo = new EmployeeRepository($kdtphNewPdo);

        $this->approverDirectory = new ApproverDirectoryService(
            $groupApproverRepo,
            $this->userRepo,
            $this->employeeRepo,
            $overtimeRepo
        );

        $this->submissionService = new OvertimeSubmissionService(
            $overtimeRepo,
            $this->employeeRepo,
            $this->holidayRepo,
            $this->leaveRepo,
            $this->approverDirectory,
            $logger,
            $approvalCutoffTime
        );

        $approvalFinalizer = new ApprovalFinalizer(
            $overtimeRepo,
            $logger,
            $approvalCutoffTime
        );

        $this->approvalService = new OvertimeApprovalService(
            $overtimeRepo,
            $approvalFinalizer,
            $logger
        );
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
            'is_approver' => $this->approverDirectory->isApprover($approverId),
            'data' => $this->approverDirectory->findApproverGroupsForUser($approverId),
        ];
    }

    public function getEmployeeGroups(): array
    {
        $user = $this->currentUser();
        $approverId = (int) $user['id'];

        if (!$this->approverDirectory->isApprover($approverId)) {
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

        $approverGroupIds = $this->approverDirectory->getApproverGroupIds($approverId);
        if (!$approverGroupIds) {
            return ['success' => true, 'data' => []];
        }

        if (!$this->employeeRepo->isEmployeeInGroupsViaEmployeeGroup($employeeId, $approverGroupIds)) {
            return ['success' => false, 'message' => 'This employee is not under your handled groups.'];
        }

        $employeeGroups = $this->employeeRepo->findGroupsByEmployeeId($employeeId);
        $allowed = array_values(array_filter(
            $employeeGroups,
            static fn(array $group): bool => in_array((int) $group['id'], $approverGroupIds, true)
        ));

        return [
            'success' => true,
            'data' => $allowed,
        ];
    }

    public function searchApproverEmployees(): array
    {
        $user = $this->currentUser();
        $approverId = (int) $user['id'];

        if (!$this->approverDirectory->isApprover($approverId)) {
            return ['success' => false, 'message' => 'You are not authorized to search for employees.'];
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $groupIds = $this->approverDirectory->getApproverGroupIds($approverId);
        $employees = $this->employeeRepo->searchEmployeesInEmployeeGroups($groupIds, $query);

        return [
            'success' => true,
            'data' => $employees,
        ];
    }

    public function addOvertimeOnBehalf(): array
    {
        $approver = $this->currentUser();

        return $this->submissionService->addOvertimeOnBehalf($approver, [
            'employee_id' => $_POST['employee_id'] ?? 0,
            'group' => $_POST['group'] ?? 0,
            'location' => $_POST['location'] ?? 0,
            'remarks' => $_POST['remarks'] ?? '',
            'date' => $_POST['date'] ?? date('Y-m-d'),
            'projectsJson' => $_POST['projects'] ?? '',
        ]);
    }

    public function getUserHistory(): array
    {
        $user = $this->currentUser();
        return $this->submissionService->getUserHistory($user['id']);
    }

    public function addOvertime(): array
    {
        $user = $this->currentUser();

        return $this->submissionService->addOvertime($user, [
            'group' => $_POST['group'] ?? 0,
            'location' => $_POST['location'] ?? 0,
            'remarks' => $_POST['remarks'] ?? '',
            'date' => $_POST['date'] ?? date('Y-m-d'),
            'projectsJson' => $_POST['projects'] ?? '',
        ]);
    }

    public function cancelOvertime(): array
    {
        $user = $this->currentUser();
        $overtimeID = (int) ($_POST['overtimeID'] ?? 0);

        return $this->submissionService->cancelOvertime($user, $overtimeID);
    }

    public function getOvertimeToApprove(): array
    {
        $user = $this->currentUser();
        return $this->approvalService->getOvertimeToApprove((int) $user['id']);
    }

    public function approveOvertime(): array
    {
        $user = $this->currentUser();
        $overtimeID = isset($_POST['overtimeID']) ? $_POST['overtimeID'] : 0;
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        $approved = isset($_POST['status']) ? $_POST['status'] : null;

        return $this->approvalService->approveOvertime($user, $overtimeID, $approved, $remarks);
    }

    private function currentUser(): array
    {
        $userHash = isset($_COOKIE['userID']) ? $_COOKIE['userID'] : '';
        return $this->userRepo->findIdByHash($userHash);
    }
}
