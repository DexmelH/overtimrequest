<?php
namespace App\Repository;

use PDO;

class GroupRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUserId(string $userId): array
    {
        $sql = "SELECT gl.id, gl.abbreviation, gl.name
                FROM employee_group eg
                JOIN group_list gl ON eg.group_id = gl.id
                WHERE eg.employee_number = :userId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['userId' => $userId]);
        $data = $stmt->fetchAll();
        
        return $data ? $data : [];
    }
}
