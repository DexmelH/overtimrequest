<?php
namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Repository\EmployeeRepository;
use App\Repository\GroupApproverRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;

class AdminController
{
    private ActivityLogRepository $logRepo;
    private UserRepository $userRepo;
    private EmployeeRepository $employeeRepo;
    private GroupApproverRepository $approverRepo;
    private ActivityLogger $logger;
    /** @var int[] */
    private array $adminUserIds;

    public function __construct(
        ActivityLogRepository $logRepo,
        UserRepository $userRepo,
        EmployeeRepository $employeeRepo,
        GroupApproverRepository $approverRepo,
        ActivityLogger $logger,
        array $adminUserIds = []
    ) {
        $this->logRepo = $logRepo;
        $this->userRepo = $userRepo;
        $this->employeeRepo = $employeeRepo;
        $this->approverRepo = $approverRepo;
        $this->logger = $logger;
        $this->adminUserIds = array_map('intval', $adminUserIds);
    }

    public function getSession(): array
    {
        $user = $this->currentUser();
        return [
            'success' => true,
            'is_admin' => $this->isAdmin((int) $user['id']),
            'user' => [
                'id' => $user['id'],
                'name' => $user['surname'] ?? '',
            ],
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

        return [
            'success' => true,
            'summary' => $this->logRepo->getActionSummary(),
            ...$result,
        ];
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
        $employees = $this->employeeRepo->searchEmployees($query);

        return [
            'success' => true,
            'data' => $employees,
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
        $savedLevels = $this->approverRepo->findByGroupId($groupId);

        return [
            'success' => true,
            'group_id' => $groupId,
            'group' => $group,
            'source' => 'formspic',
            'saved_levels' => $savedLevels,
            'approvers' => $approvers,
        ];
    }

    public function saveGroupApproverLevel(): array
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

        $levelRaw = trim((string) ($_POST['level'] ?? ''));
        if (preg_match('/^L?(\d)$/i', $levelRaw, $matches)) {
            $level = (int) $matches[1];
        } else {
            $level = (int) $levelRaw;
        }
        if ($level < 1 || $level > 4) {
            return ['success' => false, 'message' => 'Invalid approval level.'];
        }

        $approverId = (int) ($_POST['approver_id'] ?? 0);
        $approverName = trim((string) ($_POST['approver_name'] ?? ''));

        if ($approverId > 0) {
            $employee = $this->employeeRepo->findById($approverId);
            if (!$employee) {
                return ['success' => false, 'message' => 'Invalid employee for this level.'];
            }
            if ($approverName === '') {
                $approverName = trim(($employee['surname'] ?? '') . ' ' . ($employee['firstname'] ?? ''));
            }
            $this->approverRepo->saveLevel($groupId, $level, $approverId, (int) $user['id']);
        } else {
            $this->approverRepo->deleteLevel($groupId, $level);
        }

        $this->logger->log(
            'admin.approvers.save',
            (int) $user['id'],
            $user['surname'] ?? null,
            'group',
            $groupId,
            [
                'level' => 'L' . $level,
                'approver_id' => $approverId > 0 ? $approverId : null,
                'approver_name' => $approverName !== '' ? $approverName : null,
                'group_abbr' => $group['abbreviation'] ?? null,
                'cleared' => $approverId <= 0,
            ]
        );

        return [
            'success' => true,
            'message' => $approverId > 0 ? 'Approver saved.' : 'Approver cleared.',
            'saved_levels' => $this->approverRepo->findByGroupId($groupId),
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

    public function saveGroupApprovers(): array
    {
        $user = $this->currentUser();
        $this->requireAdmin((int) $user['id']);

        $groupId = (int) ($_POST['group_id'] ?? 0);
        if ($groupId <= 0) {
            return ['success' => false, 'message' => 'Invalid group ID.'];
        }

        $levels = [];
        for ($i = 1; $i <= 4; $i++) {
            $key = 'l' . $i;
            $val = $_POST[$key] ?? '';
            if ($val !== '' && $val !== null) {
                $levels[$i] = (int) $val;
            }
        }

        foreach ($levels as $level => $approverId) {
            $employee = $this->employeeRepo->findById($approverId);
            if (!$employee) {
                return ['success' => false, 'message' => "Invalid employee for L{$level}."];
            }
        }

        $this->approverRepo->saveForGroup($groupId, $levels, (int) $user['id']);

        $this->logger->log(
            'admin.approvers.save',
            (int) $user['id'],
            $user['surname'] ?? null,
            'group',
            $groupId,
            ['levels' => $levels]
        );

        return [
            'success' => true,
            'message' => 'Group approvers saved successfully.',
            'levels' => $this->approverRepo->findByGroupId($groupId),
        ];
    }

    private function currentUser(): array
    {
        $userHash = $_COOKIE['userID'] ?? '';
        return $this->userRepo->findIdByHash($userHash);
    }

    private function isAdmin(int $userId): bool
    {
        return in_array($userId, $this->adminUserIds, true);
    }

    private function requireAdmin(int $userId): void
    {
        if (!$this->isAdmin($userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'errors' => ['Forbidden']]);
            exit;
        }
    }
}
