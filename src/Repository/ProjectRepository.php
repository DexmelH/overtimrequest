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
        WHERE (`fldGroup` = :groupID OR `fldGroup` IS NULL) AND `fldActive` = 1 AND `fldDelete` = 0
        AND `fldID` NOT IN (5, 6)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":groupID" => $groupID]);
        $data = $stmt->fetchAll();

        return $data ? $data : [];
    }

    public function findProjectByUserID(string $userID): array
    {
        $sql = "SELECT pt.`fldID`, CONCAT(pt.`fldProject`, ' (', pt.`fldGroup`, ')') as `fldProject` FROM `projectstable` as `pt`
        LEFT JOIN `project_share` as `ps` ON pt.`fldID` = ps.`fldProject`
        WHERE ps.`fldEmployeeNum` = :userID";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":userID" => $userID]);
        $data = $stmt->fetchAll();

        return $data ? $data : [];
    }
}