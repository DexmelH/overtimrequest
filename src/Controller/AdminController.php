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

        return [
            'success' => true,
            'summary' => $this->logRepo->getActionSummary(),
            ...$result,
        ];
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

        $levels = $this->approverRepo->findByGroupId($groupId);
        $formatted = [];
        for ($i = 1; $i <= 4; $i++) {
            $formatted["L{$i}"] = $levels[$i] ?? null;
        }

        return [
            'success' => true,
            'group_id' => $groupId,
            'levels' => $formatted,
        ];
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
