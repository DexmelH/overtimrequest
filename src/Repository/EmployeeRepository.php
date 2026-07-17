<?php
namespace App\Repository;

use PDO;

class EmployeeRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findAllGroups(): array
    {
        $sql = "SELECT `id`, `abbreviation`, `name` FROM `group_list` ORDER BY `abbreviation` ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    public function findGroupById(int $id): ?array
    {
        $sql = "SELECT `id`, `abbreviation`, `name` FROM `group_list` WHERE `id` = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<int, string> id => abbreviation */
    public function findAbbreviationsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT `id`, `abbreviation` FROM `group_list` WHERE `id` IN ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);

        $map = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $map[(int) $row['id']] = (string) $row['abbreviation'];
        }

        return $map;
    }

    public function searchEmployees(string $query, int $limit = 25): array
    {
        $query = trim($query);
        $sql = "SELECT el.`id`, el.`surname`, el.`firstname`, el.`email`,
                       gl.`abbreviation` AS group_abbr
                FROM `employee_list` el
                LEFT JOIN `group_list` gl ON gl.`id` = el.`group_id`
                WHERE el.`emp_status` = 1";
        $params = [];

        if ($query !== '') {
            if (ctype_digit($query)) {
                $sql .= " AND (el.`id` = :id OR el.`surname` LIKE :q1 OR el.`firstname` LIKE :q2)";
                $params[':id'] = (int) $query;
                $like = '%' . $query . '%';
                $params[':q1'] = $like;
                $params[':q2'] = $like;
            } else {
                $sql .= " AND (el.`surname` LIKE :q1 OR el.`firstname` LIKE :q2 OR el.`username` LIKE :q3)";
                $like = '%' . $query . '%';
                $params[':q1'] = $like;
                $params[':q2'] = $like;
                $params[':q3'] = $like;
            }
        }

        $sql .= " ORDER BY el.`surname` ASC LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): array
    {
        $sql = "SELECT el.`id`, el.`surname`, el.`firstname`, el.`email`, el.`group_id`,
                       gl.`abbreviation` AS `group_abbr`
                FROM `employee_list` el
                LEFT JOIN `group_list` gl ON gl.`id` = el.`group_id`
                WHERE el.`id` = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function findEmployeesInGroups(array $groupIds): array
    {
        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
        if (!$groupIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $sql = "SELECT DISTINCT el.`id`, el.`surname`, el.`firstname`, el.`email`,
                       gl.`abbreviation` AS `group_abbr`
                FROM `employee_list` el
                LEFT JOIN `group_list` gl ON gl.`id` = el.`group_id`
                WHERE el.`emp_status` = 1
                  AND el.`group_id` IN ({$placeholders})
                ORDER BY el.`surname` ASC, el.`firstname` ASC";
        $params = array_merge($groupIds);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /** @return int[] */
    public function findGroupIdsByAbbreviations(array $abbreviations): array
    {
        $abbreviations = array_values(array_unique(array_filter(array_map('trim', $abbreviations))));
        if (!$abbreviations) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($abbreviations), '?'));
        $sql = "SELECT `id` FROM `group_list` WHERE `abbreviation` IN ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($abbreviations);

        return array_map('intval', array_column($stmt->fetchAll() ?: [], 'id'));
    }

    public function findGroupsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT `id`, `abbreviation`, `name` FROM `group_list` WHERE `id` IN ({$placeholders}) ORDER BY `abbreviation` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);

        return $stmt->fetchAll() ?: [];
    }

    public function findGroupsByAbbreviations(array $abbreviations): array
    {
        $abbreviations = array_values(array_unique(array_filter(array_map('trim', $abbreviations))));
        if (!$abbreviations) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($abbreviations), '?'));
        $sql = "SELECT `id`, `abbreviation`, `name` FROM `group_list` WHERE `abbreviation` IN ({$placeholders}) ORDER BY `abbreviation` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($abbreviations);

        return $stmt->fetchAll() ?: [];
    }

    /** @return array<int, array{id: int, abbreviation: string, name: string}> */
    public function findGroupsByEmployeeId(int $employeeId): array
    {
        if ($employeeId <= 0) {
            return [];
        }

        $sql = "SELECT DISTINCT gl.`id`, gl.`abbreviation`, gl.`name`
                FROM `employee_group` eg
                INNER JOIN `group_list` gl ON gl.`id` = eg.`group_id`
                WHERE eg.`employee_number` = :employeeId
                ORDER BY gl.`abbreviation` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':employeeId' => $employeeId]);

        return $stmt->fetchAll() ?: [];
    }

    public function isEmployeeInEmployeeGroup(int $employeeId, int $groupId): bool
    {
        if ($employeeId <= 0 || $groupId <= 0) {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM `employee_group`
                WHERE `employee_number` = :employeeId AND `group_id` = :groupId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':employeeId' => $employeeId,
            ':groupId' => $groupId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function isEmployeeInGroups(int $employeeId, array $groupIds): bool
    {
        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
        if ($employeeId <= 0 || !$groupIds) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $sql = "SELECT COUNT(*)
                FROM `employee_list` el
                LEFT JOIN `employee_group` eg ON eg.`employee_number` = el.`id`
                WHERE el.`id` = ?
                  AND el.`emp_status` = 1
                  AND (el.`group_id` IN ({$placeholders}) OR eg.`group_id` IN ({$placeholders}))";
        $params = array_merge([$employeeId], $groupIds, $groupIds);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function searchEmployeesInGroups(array $groupIds, string $query, int $limit = 25): array
    {
        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
        if (!$groupIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $query = trim($query);
        $groupMatchSql = "CASE
                WHEN el.`group_id` IN ({$placeholders}) THEN el.`group_id`
                ELSE (
                    SELECT eg2.`group_id`
                    FROM `employee_group` eg2
                    WHERE eg2.`employee_number` = el.`id` AND eg2.`group_id` IN ({$placeholders})
                    LIMIT 1
                )
            END";
        $sql = "SELECT DISTINCT el.`id`, el.`surname`, el.`firstname`, el.`email`,
                       gl.`abbreviation` AS group_abbr, el.`group_id`,
                       {$groupMatchSql} AS approver_group_id
                FROM `employee_list` el
                LEFT JOIN `group_list` gl ON gl.`id` = el.`group_id`
                LEFT JOIN `employee_group` eg ON eg.`employee_number` = el.`id`
                WHERE el.`emp_status` = 1
                  AND (el.`group_id` IN ({$placeholders}) OR eg.`group_id` IN ({$placeholders}))";
        $params = array_merge($groupIds, $groupIds, $groupIds, $groupIds);

        if ($query !== '') {
            if (ctype_digit($query)) {
                $sql .= " AND (el.`id` = ? OR el.`surname` LIKE ? OR el.`firstname` LIKE ?)";
                $like = '%' . $query . '%';
                $params[] = (int) $query;
                $params[] = $like;
                $params[] = $like;
            } else {
                $sql .= " AND (el.`surname` LIKE ? OR el.`firstname` LIKE ? OR el.`username` LIKE ?)";
                $like = '%' . $query . '%';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
        }

        $sql .= " ORDER BY el.`surname` ASC LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }
}
