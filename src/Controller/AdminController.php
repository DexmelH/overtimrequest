<?php
namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Repository\AdminMemberRepository;
use App\Repository\EmployeeRepository;
use App\Repository\GroupApproverRepository;
use App\Repository\ProjectNotifyRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use App\Service\AdminAccessService;

class AdminController
{
    private ActivityLogRepository $logRepo;
    private UserRepository $userRepo;
    private EmployeeRepository $employeeRepo;
    private GroupApproverRepository $approverRepo;
    private ProjectNotifyRepository $projectNotifyRepo;
    private ProjectRepository $projectRepo;
    private AdminMemberRepository $adminMemberRepo;
    private AdminAccessService $adminAccess;
    private ActivityLogger $logger;

    public function __construct(
        ActivityLogRepository $logRepo,
        UserRepository $userRepo,
        EmployeeRepository $employeeRepo,
        GroupApproverRepository $approverRepo,
        ProjectNotifyRepository $projectNotifyRepo,
        ProjectRepository $projectRepo,
        AdminMemberRepository $adminMemberRepo,
        AdminAccessService $adminAccess,
        ActivityLogger $logger
    ) {
        $this->logRepo = $logRepo;
        $this->userRepo = $userRepo;
        $this->employeeRepo = $employeeRepo;
        $this->approverRepo = $approverRepo;
        $this->projectNotifyRepo = $projectNotifyRepo;
        $this->projectRepo = $projectRepo;
        $this->adminMemberRepo = $adminMemberRepo;
        $this->adminAccess = $adminAccess;
        $this->logger = $logger;
    }

    public function getSession(): array
    {
        $user = $this->currentUser();
        return [
            'success' => true,
            'is_admin' => $this->adminAccess->isAdmin((int) $user['id']),
            'user' => [
                'id' => $user['id'],
                'name' => trim(
                    trim((string) ($user['firstname'] ?? '')) . ' ' . trim((string) ($user['surname'] ?? ''))
                ) ?: ($user['surname'] ?? ''),
            ],
        ];
    }

    public function getAdminMembers(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        return [
            'success' => true,
            'default_groups' => $this->adminAccess->defaultGroupAbbreviations(),
            'data' => $this->adminAccess->listAdmins(),
        ];
    }

    public function addAdminMember(): array
    {
        $user = $this->currentUser();
        $actorId = (int) $user['id'];
        $this->requireAdmin($actorId);

        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($employeeId <= 0) {
            return ['success' => false, 'message' => 'Select an employee to add as admin.'];
        }

        $employee = $this->employeeRepo->findById($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found.'];
        }

        if ($this->adminMemberRepo->exists($employeeId)) {
            return ['success' => false, 'message' => 'This employee is already an assigned admin.'];
        }

        $this->adminMemberRepo->add($employeeId, $notes !== '' ? $notes : null, $actorId);

        $name = trim(($employee['firstname'] ?? '') . ' ' . ($employee['surname'] ?? ''));
        $this->logger->log(
            'admin.members.add',
            $actorId,
            $user['surname'] ?? null,
            'admin_member',
            $employeeId,
            [
                'employee_id' => $employeeId,
                'employee_name' => $name !== '' ? $name : null,
                'notes' => $notes !== '' ? $notes : null,
            ]
        );

        return [
            'success' => true,
            'message' => 'Admin member added.',
            'data' => $this->adminAccess->listAdmins(),
        ];
    }

    public function updateAdminMember(): array
    {
        $user = $this->currentUser();
        $actorId = (int) $user['id'];
        $this->requireAdmin($actorId);

        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($employeeId <= 0) {
            return ['success' => false, 'message' => 'Invalid employee.'];
        }

        if (!$this->adminMemberRepo->exists($employeeId)) {
            return [
                'success' => false,
                'message' => 'Only assigned admins can be updated. Default group admins are managed via APP_ADMIN_GROUP_ABBRS.',
            ];
        }

        $this->adminMemberRepo->updateNotes($employeeId, $notes !== '' ? $notes : null, $actorId);

        $employee = $this->employeeRepo->findById($employeeId);
        $name = trim(($employee['firstname'] ?? '') . ' ' . ($employee['surname'] ?? ''));
        $this->logger->log(
            'admin.members.update',
            $actorId,
            $user['surname'] ?? null,
            'admin_member',
            $employeeId,
            [
                'employee_id' => $employeeId,
                'employee_name' => $name !== '' ? $name : null,
                'notes' => $notes !== '' ? $notes : null,
            ]
        );

        return [
            'success' => true,
            'message' => 'Admin member updated.',
            'data' => $this->adminAccess->listAdmins(),
        ];
    }

    public function removeAdminMember(): array
    {
        $user = $this->currentUser();
        $actorId = (int) $user['id'];
        $this->requireAdmin($actorId);

        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return ['success' => false, 'message' => 'Invalid employee.'];
        }

        if ($employeeId === $actorId) {
            return ['success' => false, 'message' => 'You cannot remove your own admin access.'];
        }

        if (!$this->adminMemberRepo->exists($employeeId)) {
            return [
                'success' => false,
                'message' => 'Only assigned admins can be removed. Default group admins cannot be removed here.',
            ];
        }

        if ($this->adminAccess->isDefaultGroupAdmin($employeeId)) {
            $this->adminMemberRepo->remove($employeeId);
            return [
                'success' => true,
                'message' => 'Assigned record removed. This person remains an admin via a default admin group.',
                'data' => $this->adminAccess->listAdmins(),
            ];
        }

        $employee = $this->employeeRepo->findById($employeeId);
        $this->adminMemberRepo->remove($employeeId);

        $name = trim(($employee['firstname'] ?? '') . ' ' . ($employee['surname'] ?? ''));
        $this->logger->log(
            'admin.members.remove',
            $actorId,
            $user['surname'] ?? null,
            'admin_member',
            $employeeId,
            [
                'employee_id' => $employeeId,
                'employee_name' => $name !== '' ? $name : null,
            ]
        );

        return [
            'success' => true,
            'message' => 'Admin member removed.',
            'data' => $this->adminAccess->listAdmins(),
        ];
    }

    public function getActivityLogs(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        $filters = [
            'page' => $_GET['page'] ?? 1,
            'limit' => $_GET['limit'] ?? 50,
            'action' => $_GET['action'] ?? '',
            'user_id' => $_GET['user_id'] ?? '',
            'search' => $_GET['search'] ?? '',
            'from' => $_GET['from'] ?? '',
            'to' => $_GET['to'] ?? '',
        ];

        $result = $this->logRepo->findLogs($filters);
        $this->enrichLogRows($result['data']);

        return array_merge(
            [
                'success' => true,
                'summary' => $this->logRepo->getActionSummary(),
            ],
            $result
        );
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function enrichLogRows(array &$rows): void
    {
        $idsToResolve = [];
        foreach ($rows as $row) {
            $details = is_array($row['details'] ?? null) ? $row['details'] : [];

            if (($row['entity_type'] ?? '') === 'group' && !empty($row['entity_id']) && empty($details['group_abbr'])) {
                $idsToResolve[(int) $row['entity_id']] = true;
            }
            if (!empty($details['group_id']) && empty($details['group_abbr']) && empty($details['group'])) {
                $idsToResolve[(int) $details['group_id']] = true;
            }
        }

        $abbrMap = $idsToResolve
            ? $this->employeeRepo->findAbbreviationsByIds(array_keys($idsToResolve))
            : [];

        foreach ($rows as &$row) {
            $details = is_array($row['details'] ?? null) ? $row['details'] : [];

            if (!empty($details['group_id']) && empty($details['group_abbr']) && empty($details['group'])) {
                $id = (int) $details['group_id'];
                if (isset($abbrMap[$id])) {
                    $details['group_abbr'] = $abbrMap[$id];
                    $row['details'] = $details;
                }
            }

            if (($row['entity_type'] ?? '') !== 'group') {
                continue;
            }
            if (!empty($details['group_abbr'])) {
                $row['entity_label'] = $details['group_abbr'];
            } elseif (!empty($row['entity_id'])) {
                $id = (int) $row['entity_id'];
                $row['entity_label'] = $abbrMap[$id] ?? null;
            }
        }
    }

    public function getAdminGroups(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        return [
            'success' => true,
            'data' => $this->employeeRepo->findAllGroups(),
        ];
    }

    public function searchEmployees(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        $query = $_GET['q'] ?? '';
        $groupId = (int) ($_GET['group_id'] ?? 0);
        $excludeIds = $groupId > 0
            ? $this->approverRepo->findAssignedApproverIds($groupId)
            : [];
        $employees = $this->employeeRepo->searchEmployees($query, 25, $excludeIds);

        return [
            'success' => true,
            'data' => $employees,
        ];
    }

    public function getProjectNotify(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        $groupId = (int) ($_GET['group_id'] ?? 0);
        $projectId = (int) ($_GET['project_id'] ?? 0);
        $groupAbbrev = null;

        if ($groupId > 0) {
            $group = $this->employeeRepo->findGroupById($groupId);
            if (!$group) {
                return ['success' => false, 'message' => 'Group not found.'];
            }
            $groupAbbrev = (string) ($group['abbreviation'] ?? '');
        }

        return [
            'success' => true,
            'recipients' => $this->projectNotifyRepo->findAll(
                $groupAbbrev,
                $projectId > 0 ? $projectId : null
            ),
            'filter_projects' => $this->projectNotifyRepo->findFilterProjects($groupAbbrev),
        ];
    }

    public function getProjectNotifyEmployees(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        $groupId = (int) ($_GET['group_id'] ?? 0);
        if ($groupId <= 0) {
            return ['success' => false, 'message' => 'Invalid group ID.'];
        }

        $group = $this->employeeRepo->findGroupById($groupId);
        if (!$group) {
            return ['success' => false, 'message' => 'Group not found.'];
        }

        return [
            'success' => true,
            'data' => $this->employeeRepo->findByMainGroupId($groupId),
        ];
    }

    public function getProjectNotifyProjects(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        $groupId = (int) ($_GET['group_id'] ?? 0);
        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        if ($groupId <= 0) {
            return ['success' => false, 'message' => 'Invalid group ID.'];
        }
        if ($employeeId <= 0) {
            return ['success' => false, 'message' => 'Select an employee first.'];
        }

        $group = $this->employeeRepo->findGroupById($groupId);
        if (!$group) {
            return ['success' => false, 'message' => 'Group not found.'];
        }

        $employee = $this->employeeRepo->findById($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Invalid employee.'];
        }

        if ((int) ($employee['group_id'] ?? 0) !== $groupId) {
            return ['success' => false, 'message' => 'Employee main group does not match the selected group.'];
        }

        $abbr = (string) ($group['abbreviation'] ?? '');
        $projects = $this->projectRepo->findProjectsForGroupAndUser($abbr, (string) $employeeId);

        return [
            'success' => true,
            'data' => array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['fldID'] ?? 0),
                    'name' => (string) ($row['fldProject'] ?? ''),
                ];
            }, $projects),
        ];
    }

    public function addProjectNotify(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        $groupId = (int) ($_POST['group_id'] ?? 0);
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $projectId = (int) ($_POST['project_id'] ?? 0);

        if ($groupId <= 0) {
            return ['success' => false, 'message' => 'Invalid group ID.'];
        }
        if ($employeeId <= 0) {
            return ['success' => false, 'message' => 'Select an employee to add.'];
        }
        if ($projectId <= 0) {
            return ['success' => false, 'message' => 'Select a project.'];
        }

        $group = $this->employeeRepo->findGroupById($groupId);
        if (!$group) {
            return ['success' => false, 'message' => 'Group not found.'];
        }

        $employee = $this->employeeRepo->findById($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Invalid employee.'];
        }

        if ((int) ($employee['group_id'] ?? 0) !== $groupId) {
            return ['success' => false, 'message' => 'Employee main group does not match the selected group.'];
        }

        $email = trim((string) ($employee['email'] ?? ''));
        if ($email === '') {
            return ['success' => false, 'message' => 'This employee has no email address.'];
        }

        $abbr = (string) ($group['abbreviation'] ?? '');
        $allowed = $this->projectRepo->findProjectsForGroupAndUser($abbr, (string) $employeeId);
        $allowedIds = array_map(static fn (array $row): int => (int) ($row['fldID'] ?? 0), $allowed);
        if (!in_array($projectId, $allowedIds, true)) {
            return ['success' => false, 'message' => 'Selected project is not available for this group and user.'];
        }

        $employeeName = trim((string) ($_POST['employee_name'] ?? ''));
        if ($employeeName === '') {
            $employeeName = trim(($employee['surname'] ?? '') . ' ' . ($employee['firstname'] ?? ''));
        }

        try {
            $this->projectNotifyRepo->add($projectId, $employeeId, (int) $user['id']);
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $projectName = $this->projectNotifyRepo->findProjectName($projectId);

        $this->logger->log(
            'admin.project_notify.add',
            (int) $user['id'],
            $user['surname'] ?? null,
            'project',
            $projectId,
            [
                'employee_id' => $employeeId,
                'employee_name' => $employeeName !== '' ? $employeeName : null,
                'project_id' => $projectId,
                'project_name' => $projectName !== '' ? $projectName : null,
                'group_id' => $groupId,
                'group_abbr' => $abbr !== '' ? $abbr : null,
            ]
        );

        return [
            'success' => true,
            'message' => 'Notify recipient added.',
        ];
    }

    public function removeProjectNotify(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $groupId = (int) ($_POST['group_id'] ?? 0);

        if ($employeeId <= 0 || $projectId <= 0) {
            return ['success' => false, 'message' => 'Invalid notify assignment.'];
        }

        $employeeName = trim((string) ($_POST['employee_name'] ?? ''));
        if ($employeeName === '') {
            $employee = $this->employeeRepo->findById($employeeId);
            if ($employee) {
                $employeeName = trim(($employee['surname'] ?? '') . ' ' . ($employee['firstname'] ?? ''));
            }
        }

        $projectName = trim((string) ($_POST['project_name'] ?? ''));
        if ($projectName === '') {
            $projectName = $this->projectNotifyRepo->findProjectName($projectId);
        }

        $groupAbbr = trim((string) ($_POST['group_abbr'] ?? ''));
        if ($groupAbbr === '' && $groupId > 0) {
            $group = $this->employeeRepo->findGroupById($groupId);
            $groupAbbr = (string) ($group['abbreviation'] ?? '');
        }

        $removed = $this->projectNotifyRepo->remove($projectId, $employeeId);
        if (!$removed) {
            return ['success' => false, 'message' => 'Notify assignment was not found.'];
        }

        $this->logger->log(
            'admin.project_notify.remove',
            (int) $user['id'],
            $user['surname'] ?? null,
            'project',
            $projectId,
            [
                'employee_id' => $employeeId,
                'employee_name' => $employeeName !== '' ? $employeeName : null,
                'project_id' => $projectId,
                'project_name' => $projectName !== '' ? $projectName : null,
                'group_id' => $groupId > 0 ? $groupId : null,
                'group_abbr' => $groupAbbr !== '' ? $groupAbbr : null,
            ]
        );

        return [
            'success' => true,
            'message' => 'Notify recipient removed.',
        ];
    }

    public function getGroupApprovers(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        $groupId = (int) ($_GET['group_id'] ?? 0);
        if ($groupId <= 0) {
            return ['success' => false, 'message' => 'Invalid group ID.'];
        }

        $group = $this->employeeRepo->findGroupById($groupId);
        if (!$group) {
            return ['success' => false, 'message' => 'Group not found.'];
        }

        $approvers = $this->userRepo->findFormPicApproversByGroupAbbrev((string) $group['abbreviation']);
        $savedApprovers = $this->approverRepo->findByGroupId($groupId);

        return [
            'success' => true,
            'group_id' => $groupId,
            'group' => $group,
            'source' => 'formspic',
            'saved_approvers' => $savedApprovers,
            'approvers' => $approvers,
        ];
    }

    public function addGroupApprover(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        $groupId = (int) ($_POST['group_id'] ?? 0);
        if ($groupId <= 0) {
            return ['success' => false, 'message' => 'Invalid group ID.'];
        }

        $group = $this->employeeRepo->findGroupById($groupId);
        if (!$group) {
            return ['success' => false, 'message' => 'Group not found.'];
        }

        $level = $this->parseApprovalLevel($_POST['level'] ?? '');
        if ($level === null) {
            return ['success' => false, 'message' => 'Invalid approval level.'];
        }

        $approverId = (int) ($_POST['approver_id'] ?? 0);
        if ($approverId <= 0) {
            return ['success' => false, 'message' => 'Select an employee to add.'];
        }

        $employee = $this->employeeRepo->findById($approverId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Invalid employee.'];
        }

        $approverName = trim((string) ($_POST['approver_name'] ?? ''));
        if ($approverName === '') {
            $approverName = trim(($employee['surname'] ?? '') . ' ' . ($employee['firstname'] ?? ''));
        }

        try {
            $this->approverRepo->addApprover($groupId, $level, $approverId, (int) $user['id']);
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $this->logger->log(
            'admin.approvers.add',
            (int) $user['id'],
            $user['surname'] ?? null,
            'group',
            $groupId,
            [
                'level' => 'L' . $level,
                'approver_id' => $approverId,
                'approver_name' => $approverName !== '' ? $approverName : null,
                'group_abbr' => $group['abbreviation'] ?? null,
            ]
        );

        return [
            'success' => true,
            'message' => 'Approver added.',
            'saved_approvers' => $this->approverRepo->findByGroupId($groupId),
        ];
    }

    public function removeGroupApprover(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        $groupId = (int) ($_POST['group_id'] ?? 0);
        if ($groupId <= 0) {
            return ['success' => false, 'message' => 'Invalid group ID.'];
        }

        $group = $this->employeeRepo->findGroupById($groupId);
        if (!$group) {
            return ['success' => false, 'message' => 'Group not found.'];
        }

        $approverId = (int) ($_POST['approver_id'] ?? 0);
        if ($approverId <= 0) {
            return ['success' => false, 'message' => 'Invalid approver.'];
        }

        $approverName = trim((string) ($_POST['approver_name'] ?? ''));
        if ($approverName === '') {
            $employee = $this->employeeRepo->findById($approverId);
            if ($employee) {
                $approverName = trim(($employee['surname'] ?? '') . ' ' . ($employee['firstname'] ?? ''));
            }
        }

        $removed = $this->approverRepo->removeApprover($groupId, $approverId);
        if (!$removed) {
            return ['success' => false, 'message' => 'Approver was not assigned to this group.'];
        }

        $this->logger->log(
            'admin.approvers.remove',
            (int) $user['id'],
            $user['surname'] ?? null,
            'group',
            $groupId,
            [
                'approver_id' => $approverId,
                'approver_name' => $approverName !== '' ? $approverName : null,
                'group_abbr' => $group['abbreviation'] ?? null,
            ]
        );

        return [
            'success' => true,
            'message' => 'Approver removed.',
            'saved_approvers' => $this->approverRepo->findByGroupId($groupId),
        ];
    }

    public function changeGroupApproverLevel(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        $groupId = (int) ($_POST['group_id'] ?? 0);
        if ($groupId <= 0) {
            return ['success' => false, 'message' => 'Invalid group ID.'];
        }

        $group = $this->employeeRepo->findGroupById($groupId);
        if (!$group) {
            return ['success' => false, 'message' => 'Group not found.'];
        }

        $level = $this->parseApprovalLevel($_POST['level'] ?? '');
        if ($level === null) {
            return ['success' => false, 'message' => 'Invalid approval level.'];
        }

        $approverId = (int) ($_POST['approver_id'] ?? 0);
        if ($approverId <= 0) {
            return ['success' => false, 'message' => 'Invalid approver.'];
        }

        $approverName = trim((string) ($_POST['approver_name'] ?? ''));
        if ($approverName === '') {
            $employee = $this->employeeRepo->findById($approverId);
            if ($employee) {
                $approverName = trim(($employee['surname'] ?? '') . ' ' . ($employee['firstname'] ?? ''));
            }
        }

        try {
            $this->approverRepo->changeApproverLevel($groupId, $approverId, $level, (int) $user['id']);
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $this->logger->log(
            'admin.approvers.change_level',
            (int) $user['id'],
            $user['surname'] ?? null,
            'group',
            $groupId,
            [
                'level' => 'L' . $level,
                'approver_id' => $approverId,
                'approver_name' => $approverName !== '' ? $approverName : null,
                'group_abbr' => $group['abbreviation'] ?? null,
            ]
        );

        return [
            'success' => true,
            'message' => 'Approver level updated.',
            'saved_approvers' => $this->approverRepo->findByGroupId($groupId),
        ];
    }

    /**
     * @deprecated Bulk one-per-level save; use add/remove/change-level endpoints.
     */
    public function saveGroupApprovers(): array
    {
        return [
            'success' => false,
            'message' => 'Bulk level save is no longer supported. Add or remove approvers individually.',
        ];
    }

    public function logApproverAction(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        $action = (string) ($_POST['action'] ?? '');
        $allowed = [
            'admin.approvers.preview.add',
            'admin.approvers.preview.clear',
        ];
        if (!in_array($action, $allowed, true)) {
            return ['success' => false, 'message' => 'Invalid action.'];
        }

        $groupId = (int) ($_POST['group_id'] ?? 0);
        if ($groupId <= 0) {
            return ['success' => false, 'message' => 'Invalid group ID.'];
        }

        $level = trim((string) ($_POST['level'] ?? ''));
        $approverId = (int) ($_POST['approver_id'] ?? 0);
        $approverName = trim((string) ($_POST['approver_name'] ?? ''));
        $groupAbbr = trim((string) ($_POST['group_abbr'] ?? ''));

        if ($groupAbbr === '') {
            $group = $this->employeeRepo->findGroupById($groupId);
            $groupAbbr = $group['abbreviation'] ?? '';
        }

        $this->logger->log(
            $action,
            (int) $user['id'],
            $user['surname'] ?? null,
            'group',
            $groupId,
            [
                'level' => $level,
                'approver_id' => $approverId > 0 ? $approverId : null,
                'approver_name' => $approverName !== '' ? $approverName : null,
                'group_abbr' => $groupAbbr !== '' ? $groupAbbr : null,
            ]
        );

        return ['success' => true];
    }

    private function parseApprovalLevel($raw): ?int
    {
        $levelRaw = trim((string) $raw);
        if (preg_match('/^L?(\d)$/i', $levelRaw, $matches)) {
            $level = (int) $matches[1];
        } else {
            $level = (int) $levelRaw;
        }

        if ($level < 1 || $level > 4) {
            return null;
        }

        return $level;
    }

    private function currentUser(): array
    {
        $userHash = $_COOKIE['userID'] ?? '';
        return $this->userRepo->findIdByHash($userHash);
    }

    private function requireAdmin(int $userId): void
    {
        if (!$this->adminAccess->isAdmin($userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'errors' => ['Forbidden']]);
            exit;
        }
    }
}
