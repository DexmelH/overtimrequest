<?php
namespace App\Repository;

use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findIdByHash(string $userHash): int
    {
        $sql = "SELECT `fldEmployeeNum` FROM `kdtlogin` WHERE `fldUserHash` = :userHash";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":userHash" => $userHash]);
        $data = $stmt->fetch();

        return $data ? $data["fldEmployeeNum"] : null;
    }
}