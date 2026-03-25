<?php
namespace App\Repository;

use PDO;

class WorkRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findWork(): array
    {
        $sql = "SELECT `fldID`, `fldTOW` FROM `typesofworktable` 
        WHERE `fldActive` = 1 AND `fldTOWType` = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll();

        return $data ? $data : [];
    }
}