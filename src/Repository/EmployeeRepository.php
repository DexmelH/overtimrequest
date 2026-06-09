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
                $sql .= " AND (el.`id` = :id OR el.`surname` LIKE :q OR el.`firstname` LIKE :q)";
                $params[':id'] = (int) $query;
                $params[':q'] = '%' . $query . '%';
            } else {
                $sql .= " AND (el.`surname` LIKE :q OR el.`firstname` LIKE :q OR el.`username` LIKE :q)";
                $params[':q'] = '%' . $query . '%';
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
