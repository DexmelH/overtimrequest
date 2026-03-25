<?php
namespace App\Repository;

use PDO;

class ProjectRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findProjectByGroupID(string $groupID): array
    {
        $sql = "SELECT `fldID`, `fldProject` FROM `projectstable` 
        WHERE `fldGroup` = :groupID AND `fldActive` = 1 AND `fldDelete` = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":groupID" => $groupID]);
        $data = $stmt->fetchAll();

        return $data ? $data : [];
    }
}