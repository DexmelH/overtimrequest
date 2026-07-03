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
        $sql = "SELECT `id`, `surname`, `firstname`, `email` FROM `employee_list` WHERE `id` = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: [];
    }
}
