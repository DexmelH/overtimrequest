<?php
namespace App\Service;

use App\Repository\AdminMemberRepository;
use App\Repository\EmployeeRepository;

class AdminAccessService
{
    private AdminMemberRepository $adminRepo;
    private EmployeeRepository $employeeRepo;
    /** @var string[] */
    private array $defaultGroupAbbrs;
    /** @var int[]|null */
    private ?array $defaultGroupIds = null;

    /**
     * @param string[] $defaultGroupAbbrs Group abbreviations from env (e.g. MNG, IT, SYS)
     */
    public function __construct(
        AdminMemberRepository $adminRepo,
        EmployeeRepository $employeeRepo,
        array $defaultGroupAbbrs = []
    ) {
        $this->adminRepo = $adminRepo;
        $this->employeeRepo = $employeeRepo;
        $this->defaultGroupAbbrs = array_values(array_unique(array_filter(array_map(
            static fn ($abbr) => strtoupper(trim((string) $abbr)),
            $defaultGroupAbbrs
        ))));
    }

    public function isAdmin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if ($this->adminRepo->exists($userId)) {
            return true;
        }

        $groupIds = $this->defaultGroupIds();
        if (!$groupIds) {
            return false;
        }

        return $this->employeeRepo->isEmployeeInGroups($userId, $groupIds);
    }

    public function isDefaultGroupAdmin(int $userId): bool
    {
        $groupIds = $this->defaultGroupIds();
        return $userId > 0
            && $groupIds
            && $this->employeeRepo->isEmployeeInGroups($userId, $groupIds);
    }

    public function isAssignedAdmin(int $userId): bool
    {
        return $this->adminRepo->exists($userId);
    }

    /** @return string[] */
    public function defaultGroupAbbreviations(): array
    {
        return $this->defaultGroupAbbrs;
    }

    /** @return int[] */
    public function defaultGroupIds(): array
    {
        if ($this->defaultGroupIds === null) {
            $this->defaultGroupIds = $this->defaultGroupAbbrs
                ? $this->employeeRepo->findGroupIdsByAbbreviations($this->defaultGroupAbbrs)
                : [];
        }

        return $this->defaultGroupIds;
    }

    /**
     * @return array<int, array{
     *   employee_id: int,
     *   surname: string,
     *   firstname: string,
     *   email: string|null,
     *   group_abbr: string|null,
     *   notes: string|null,
     *   sources: string[],
     *   can_remove: bool,
     *   can_update: bool
     * }>
     */
    public function listAdmins(): array
    {
        $byId = [];

        foreach ($this->employeeRepo->findEmployeesInGroups($this->defaultGroupIds()) as $row) {
            $id = (int) $row['id'];
            $byId[$id] = $this->emptyMember($row);
            $byId[$id]['sources'][] = 'default';
            $byId[$id]['group_abbr'] = $row['group_abbr'] ?? $byId[$id]['group_abbr'];
        }

        foreach ($this->adminRepo->findAll() as $row) {
            $id = (int) $row['employee_id'];
            if (!isset($byId[$id])) {
                $byId[$id] = $this->emptyMember([
                    'id' => $id,
                    'surname' => $row['surname'] ?? '',
                    'firstname' => $row['firstname'] ?? '',
                    'email' => $row['email'] ?? null,
                    'group_abbr' => $row['group_abbr'] ?? null,
                ]);
            }
            $byId[$id]['sources'][] = 'assigned';
            $byId[$id]['notes'] = $row['notes'] ?? null;
            $byId[$id]['updated_at'] = $row['updated_at'] ?? null;
        }

        foreach ($byId as &$member) {
            $member['sources'] = array_values(array_unique($member['sources']));
            $member['can_remove'] = in_array('assigned', $member['sources'], true)
                && !in_array('default', $member['sources'], true);
            $member['can_update'] = in_array('assigned', $member['sources'], true);
        }
        unset($member);

        uasort($byId, static function (array $a, array $b): int {
            $sa = strcasecmp((string) $a['surname'], (string) $b['surname']);
            if ($sa !== 0) {
                return $sa;
            }
            return strcasecmp((string) $a['firstname'], (string) $b['firstname']);
        });

        return array_values($byId);
    }

    /** @param array<string, mixed> $row */
    private function emptyMember(array $row): array
    {
        return [
            'employee_id' => (int) ($row['id'] ?? $row['employee_id'] ?? 0),
            'surname' => (string) ($row['surname'] ?? ''),
            'firstname' => (string) ($row['firstname'] ?? ''),
            'email' => $row['email'] ?? null,
            'group_abbr' => $row['group_abbr'] ?? null,
            'notes' => null,
            'updated_at' => null,
            'sources' => [],
            'can_remove' => false,
            'can_update' => false,
        ];
    }
}
