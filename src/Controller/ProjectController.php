<?php
namespace App\Controller;

use App\Repository\EmployeeRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\ApproverDirectoryService;

class ProjectController
{
    private ProjectRepository $projectRepo;
    private UserRepository $userRepo;
    private EmployeeRepository $employeeRepo;
    private ApproverDirectoryService $approverDirectory;

    public function __construct(
        ProjectRepository $projectRepo,
        UserRepository $userRepo,
        EmployeeRepository $employeeRepo,
        ApproverDirectoryService $approverDirectory
    ) {
        $this->projectRepo = $projectRepo;
        $this->userRepo = $userRepo;
        $this->employeeRepo = $employeeRepo;
        $this->approverDirectory = $approverDirectory;
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
            // On-behalf: approver must handle the target employee's main group (employee_list.group_id).
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
