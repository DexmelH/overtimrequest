<?php
namespace App\Service;

use App\Repository\EmployeeRepository;
use App\Repository\GroupApproverRepository;
use App\Repository\OvertimeRepository;
use App\Repository\UserRepository;

class ApproverDirectoryService
{
    private GroupApproverRepository $groupApproverRepo;
    private UserRepository $userRepo;
    private EmployeeRepository $employeeRepo;
    private OvertimeRepository $overtimeRepo;

    public function __construct(
        GroupApproverRepository $groupApproverRepo,
        UserRepository $userRepo,
        EmployeeRepository $employeeRepo,
        OvertimeRepository $overtimeRepo
    ) {
        $this->groupApproverRepo = $groupApproverRepo;
        $this->userRepo = $userRepo;
        $this->employeeRepo = $employeeRepo;
        $this->overtimeRepo = $overtimeRepo;
    }

    public function isApprover(int $approverId): bool
    {
        if ($approverId <= 0) {
            return false;
        }

        return $this->groupApproverRepo->isAssignedApprover($approverId)
            || $this->userRepo->isFormPicApprover($approverId);
    }

    /** @return array<int, array{id: int, abbreviation: string, name: string}> */
    public function findApproverGroupsForUser(int $approverId): array
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
    public function getApproverGroupIds(int $approverId): array
    {
        return array_map(
            static fn(array $group): int => (int) $group['id'],
            $this->findApproverGroupsForUser($approverId)
        );
    }

    /**
     * @return array<int, array{id: int, surname: string, email: string, approval_level?: int}>
     */
    public function resolveApprovers(int $groupId, string $groupAbbrev, int $userId): array
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
}
