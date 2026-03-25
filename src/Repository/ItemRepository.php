<?php
namespace App\Repository;

use PDO;

class ItemRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findItemByProjectID(string $projectID): array
    {
        $sql = "SELECT `fldID`, `fldItem` FROM `itemofworkstable` 
        WHERE `fldProject` = :projectID AND `fldActive` = 1 AND `fldDelete` = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":projectID" => $projectID]);
        $data = $stmt->fetchAll();

        return $data ? $data : [];
    }
}