<?php
namespace App\Repository;

use PDO;

class JobRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findJobByItemId(string $itemID): array
    {
        $sql = "SELECT `fldID`, `fldJob` FROM `drawingreference` 
        WHERE `fldItem` = :itemID AND `fldActive` = 1 AND `fldDelete` = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":itemID" => $itemID]);
        $data = $stmt->fetchAll();

        return $data ? $data : [];
    }
}