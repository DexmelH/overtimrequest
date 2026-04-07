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

    public function findIdByHash(string $userHash): array
    {
        $sql = "SELECT el.`id`, gl.`abbreviation` 
                FROM `kdtlogin` kl 
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = kl.`fldEmployeeNum` 
                LEFT JOIN kdtphdb_new.`group_list` gl ON gl.`id` = el.`group_id` 
                WHERE kl.`fldUserHash` = :userHash";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":userHash" => $userHash]);
        $data = $stmt->fetch();

        if (!$data) {
            http_response_code(401);
            echo json_encode(['success' => false, 'errors' => ['Unauthorized']]);
            exit;
        }

        return $data ? $data : [];
    }

    public function findApprover(string $group): array
    {
        $sql = "SELECT el.`surname`, el.`email` 
                FROM kdtphdb_new.`employee_list` el
                LEFT JOIN `formspic` fp ON fp.`fldEmployeeNum` = el.`id` 
                LEFT JOIN kdtphdb_new.`group_list` gl ON gl.`id` = el.`group_id` 
                WHERE fp.`fldGroups` LIKE '%$group%'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll();

        return $data ? $data : [];
    }
}