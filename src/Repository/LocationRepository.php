<?php
namespace App\Repository;

use PDO;

class LocationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findLocations(): array
    {
        $sql = "SELECT * FROM `dispatch_locations` WHERE `fldActive` = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll();

        return $data ? $data : [];
    }
}