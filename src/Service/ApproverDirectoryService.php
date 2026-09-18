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
            || $this->findFormPicFallbackGroupsForUser($approverId) !== [];
    }

    /**
     * Groups the user approves for, used for on-behalf / search:
     * - groups where they are configured in overtime_group_approvers
     * - groups where they are a Form PIC and that group has no OGA rows
     *   (saved approvers replace Forms PIC for that group)
     *
     * @return array<int, array{id: int, abbreviation: string, name: string}>
     */
    public function findApproverGroupsForUser(int $approverId): array
    {
        $groups = [];
        foreach ($this->groupApproverRepo->findApproverGroupDetails($approverId) as $row) {
            $groups[(int) $row['id']] = $row;
        }

        foreach ($this->findFormPicFallbackGroupsForUser($approverId) as $row) {
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
     * Highest OGA level, or Form PIC role only for groups that still use Forms PIC fallback.
     */
    public function findHighestApprovalLevel(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $ogaLevel = $this->groupApproverRepo->findHighestApprovalLevel($userId);
        $picLevel = $this->findFormPicFallbackGroupsForUser($userId) !== []
            ? $this->userRepo->findHighestFormPicRole($userId)
            : 0;

        return max($ogaLevel, $picLevel);
    }

    public function isSeniorApprover(int $userId, int $minLevel = 3): bool
    {
        return $this->findHighestApprovalLevel($userId) >= $minLevel;
    }

    /**
     * Resolve approvers for a group (OGA first, then Form PIC fallback).
     * Self-filed and on-behalf submit both pass the employee's main group, not
     * the selected OT group.
     *
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

    /**
     * Form PIC groups that still apply because they have no saved OGA approvers.
     *
     * @return array<int, array{id: int, abbreviation: string, name: string}>
     */
    private function findFormPicFallbackGroupsForUser(int $approverId): array
    {
        if ($approverId <= 0) {
            return [];
        }

        $picAbbrs = $this->userRepo->findFormPicGroupAbbreviationsByEmployeeId($approverId);
        if (!$picAbbrs) {
            return [];
        }

        $picGroups = $this->employeeRepo->findGroupsByAbbreviations($picAbbrs);
        if (!$picGroups) {
            return [];
        }

        $picGroupIds = array_map(static fn(array $row): int => (int) $row['id'], $picGroups);
        $configuredIds = array_fill_keys(
            $this->groupApproverRepo->findConfiguredGroupIds($picGroupIds),
            true
        );

        $fallback = [];
        foreach ($picGroups as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && !isset($configuredIds[$id])) {
                $fallback[] = $row;
            }
        }

        return $fallback;
    }
}
