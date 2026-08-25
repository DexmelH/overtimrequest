<?php
namespace App\Controller;

use App\Repository\EmployeeRepository;
use App\Repository\GroupApproverRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\ApproverDirectoryService;
use PDO;

class ProjectController
{
    private ProjectRepository $projectRepo;
    private UserRepository $userRepo;
    private EmployeeRepository $employeeRepo;
    private ApproverDirectoryService $approverDirectory;

    public function __construct(PDO $projectPdo, PDO $userPdo, PDO $employeePdo)
    {
        $this->projectRepo = new ProjectRepository($projectPdo);
        $this->userRepo = new UserRepository($userPdo);
        $this->employeeRepo = new EmployeeRepository($employeePdo);
        $this->approverDirectory = new ApproverDirectoryService(
            new GroupApproverRepository($projectPdo),
            $this->userRepo,
            $this->employeeRepo
        );
    }

    public function getProjects(): array
    {
        $group = trim((string) ($_GET['group'] ?? ''));
        $userHash = $_COOKIE['userID'] ?? '';
        $user = $this->userRepo->findIdByHash($userHash);
        $actorId = (int) ($user['id'] ?? 0);

        $requestedEmployeeId = (int) ($_GET['employee_id'] ?? 0);
        $shareUserId = $actorId;

        if ($requestedEmployeeId > 0 && $requestedEmployeeId !== $actorId) {
            if (!$this->canLoadProjectsForEmployee($actorId, $requestedEmployeeId)) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'errors' => ['You are not authorized to view projects for this employee.'],
                ]);
                exit;
            }
            $shareUserId = $requestedEmployeeId;
        }

        $groupProjects = $group !== '' ? $this->projectRepo->findProjectByGroupID($group) : [];
        $sharedProjects = $this->projectRepo->findProjectByUserID((string) $shareUserId);

        return $this->mergeProjectsById($sharedProjects, $groupProjects);
    }

    private function canLoadProjectsForEmployee(int $actorId, int $employeeId): bool
    {
        if (!$this->approverDirectory->isApprover($actorId)) {
            return false;
        }

        $approverGroupIds = $this->approverDirectory->getApproverGroupIds($actorId);
        if (!$approverGroupIds) {
            return false;
        }

        $employee = $this->employeeRepo->findById($employeeId);
        if (!$employee) {
            return false;
        }

        $mainGroupId = (int) ($employee['group_id'] ?? 0);
        return $mainGroupId > 0 && in_array($mainGroupId, $approverGroupIds, true);
    }

    /**
     * @param array<int, array<string, mixed>> ...$lists
     * @return array<int, array<string, mixed>>
     */
    private function mergeProjectsById(array ...$lists): array
    {
        $merged = [];
        foreach ($lists as $list) {
            foreach ($list as $row) {
                $id = (int) ($row['fldID'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $merged[$id] = $row;
            }
        }

        return array_values($merged);
    }
}
