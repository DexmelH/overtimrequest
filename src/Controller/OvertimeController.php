<?php
namespace App\Controller;

use App\Repository\EmployeeRepository;
use App\Repository\HolidayRepository;
use App\Repository\LeaveRepository;
use App\Repository\UserRepository;
use App\Service\ApproverDirectoryService;
use App\Service\OvertimeApprovalService;
use App\Service\OvertimeSubmissionService;

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
        UserRepository $userRepo,
        HolidayRepository $holidayRepo,
        LeaveRepository $leaveRepo,
        EmployeeRepository $employeeRepo,
        ApproverDirectoryService $approverDirectory,
        OvertimeSubmissionService $submissionService,
        OvertimeApprovalService $approvalService
    ) {
        $this->userRepo = $userRepo;
        $this->holidayRepo = $holidayRepo;
        $this->leaveRepo = $leaveRepo;
        $this->employeeRepo = $employeeRepo;
        $this->approverDirectory = $approverDirectory;
        $this->submissionService = $submissionService;
        $this->approvalService = $approvalService;
    }

    public function getHolidays(): array
    {
        $user = $this->currentUser();
        $from = trim((string) ($_GET['from'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-d');
        }

        $employeeId = (int) $user['id'];

        return [
            'success' => true,
            'id' => $employeeId,
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

        $groups = $this->employeeRepo->findGroupsByEmployeeId($employeeId);

        return [
            'success' => true,
            'data' => $groups,
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

    public function followUpRequest(): array
    {
        $approver = $this->currentUser();
        $overtimeID = (int) ($_POST['overtimeID'] ?? 0);

        return $this->submissionService->resubmitAsFollowUp($approver, $overtimeID);
    }

    public function getUserHistory(): array
    {
        $user = $this->currentUser();
        return $this->submissionService->getUserHistory($user['id'], [
            'id' => $_GET['id'] ?? 0,
            'from' => $_GET['from'] ?? null,
            'to' => $_GET['to'] ?? null,
            'page' => $_GET['page'] ?? 1,
            'limit' => $_GET['limit'] ?? 25,
            'status' => $_GET['status'] ?? '',
            'q' => $_GET['q'] ?? '',
        ]);
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
        return $this->approvalService->getOvertimeToApprove((int) $user['id'], [
            'from' => $_GET['from'] ?? null,
            'to' => $_GET['to'] ?? null,
            'page' => $_GET['page'] ?? 1,
            'limit' => $_GET['limit'] ?? 25,
            'view' => $_GET['view'] ?? 'all',
        ]);
    }

    public function getApproverDashboard(): array
    {
        $user = $this->currentUser();
        $approverId = (int) $user['id'];

        if (!$this->approverDirectory->isApprover($approverId)) {
            return ['success' => false, 'message' => 'You are not authorized to view the approver dashboard.'];
        }

        return $this->approvalService->getApproverDashboard($approverId);
    }

    public function approveOvertime(): array
    {
        $user = $this->currentUser();
        $overtimeID = isset($_POST['overtimeID']) ? $_POST['overtimeID'] : 0;
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        $approved = isset($_POST['status']) ? $_POST['status'] : null;

        return $this->approvalService->approveOvertime($user, $overtimeID, $approved, $remarks);
    }

    public function approveOvertimeBulk(): array
    {
        $user = $this->currentUser();
        $ids = $_POST['overtimeIDs'] ?? [];
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        $approved = $_POST['status'] ?? null;

        return $this->approvalService->approveOvertimeBulk($user, $ids, $approved, $remarks);
    }

    private function currentUser(): array
    {
        $userHash = isset($_COOKIE['userID']) ? $_COOKIE['userID'] : '';
        return $this->userRepo->findIdByHash($userHash);
    }
}
