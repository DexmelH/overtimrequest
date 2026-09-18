<?php
namespace App\Controller;

use App\Repository\EmployeeRepository;
use App\Repository\UserRepository;
use App\Repository\WorkLookupRepository;

class WorkController
{
    private WorkLookupRepository $workLookup;
    private EmployeeRepository $employeeRepo;
    private UserRepository $userRepo;

    public function __construct(
        WorkLookupRepository $workLookup,
        EmployeeRepository $employeeRepo,
        UserRepository $userRepo
    ) {
        $this->workLookup = $workLookup;
        $this->employeeRepo = $employeeRepo;
        $this->userRepo = $userRepo;
    }

    public function getItems(): array
    {
        $this->requireSessionUser();

        $projectId = (int) ($_GET['project_id'] ?? 0);
        $groupId = (int) ($_GET['group_id'] ?? 0);
        $groupAbbr = $this->resolveGroupAbbreviation($groupId);

        return [
            'success' => true,
            'data' => $this->workLookup->findItems($projectId, $groupAbbr),
        ];
    }

    public function getJobs(): array
    {
        $this->requireSessionUser();

        $projectId = (int) ($_GET['project_id'] ?? 0);
        $itemId = (int) ($_GET['item_id'] ?? 0);
        $groupId = (int) ($_GET['group_id'] ?? 0);
        $groupAbbr = $this->resolveGroupAbbreviation($groupId);

        return [
            'success' => true,
            'data' => $this->workLookup->findJobs($projectId, $itemId, $groupAbbr),
        ];
    }

    public function getTypesOfWork(): array
    {
        $this->requireSessionUser();

        $projectId = (int) ($_GET['project_id'] ?? 0);

        return [
            'success' => true,
            'data' => $this->workLookup->findTypesOfWork($projectId),
        ];
    }

    private function requireSessionUser(): void
    {
        $userHash = $_COOKIE['userID'] ?? '';
        $user = $this->userRepo->findIdByHash($userHash);
        if (!$user || (int) ($user['id'] ?? 0) <= 0) {
            http_response_code(401);
            echo json_encode(['success' => false, 'errors' => ['Not authenticated.']]);
            exit;
        }
    }

    private function resolveGroupAbbreviation(int $groupId): string
    {
        if ($groupId <= 0) {
            return '';
        }

        $group = $this->employeeRepo->findGroupById($groupId);

        return trim((string) ($group['abbreviation'] ?? ''));
    }
}
