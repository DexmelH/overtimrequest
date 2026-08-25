<?php
namespace App\Service;

use App\Repository\EmployeeRepository;
use App\Repository\GroupApproverRepository;
use App\Repository\UserRepository;

class ApproverDirectoryService
{
    private GroupApproverRepository $groupApproverRepo;
    private UserRepository $userRepo;
    private EmployeeRepository $employeeRepo;

    public function __construct(
        GroupApproverRepository $groupApproverRepo,
        UserRepository $userRepo,
        EmployeeRepository $employeeRepo
    ) {
        $this->groupApproverRepo = $groupApproverRepo;
        $this->userRepo = $userRepo;
        $this->employeeRepo = $employeeRepo;
    }

    public function isApprover(int $approverId): bool
    {
        if ($approverId <= 0) {
            return false;
        }

        return $this->groupApproverRepo->isAssignedApprover($approverId)
            || $this->userRepo->isFormPicApprover($approverId);
    }

    /**
     * Groups the user may handle for on-behalf / search:
     * - groups where they are configured in overtime_group_approvers
     * - Form PIC groups that do not yet have OGA configuration
     *
     * @return array<int, array{id: int, abbreviation: string, name: string}>
     */
    public function findApproverGroupsForUser(int $approverId): array
    {
        $groups = [];
        foreach ($this->groupApproverRepo->findApproverGroupDetails($approverId) as $row) {
            $groups[(int) $row['id']] = $row;
        }

        $picAbbrs = $this->userRepo->findFormPicGroupAbbreviationsByEmployeeId($approverId);
        $picGroups = $this->employeeRepo->findGroupsByAbbreviations($picAbbrs);
        $picGroupIds = array_map(static fn(array $row): int => (int) $row['id'], $picGroups);
        $configured = array_flip($this->groupApproverRepo->findGroupsWithConfiguredApprovers($picGroupIds));
        foreach ($picGroups as $row) {
            $groupId = (int) $row['id'];
            if (!isset($configured[$groupId])) {
                $groups[$groupId] = $row;
            }
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
