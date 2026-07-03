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
        $sql = "SELECT el.`id`, el.`surname`, gl.`id` AS `group_id`, gl.`abbreviation` 
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

    public function findApprover(string $group, string $userID): array
    {
        $rows = $this->findFormPicApproversByGroupAbbrev($group);
        if ($userID === '') {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => (string) $row['id'] !== (string) $userID
        ));
    }

    /**
     * @return array<int, array{id: int, role: int, surname: string, firstname: string, email: string}>
     */
    public function findFormPicApproversByGroupAbbrev(string $abbreviation): array
    {
        $abbreviation = trim($abbreviation);
        if ($abbreviation === '') {
            return [];
        }

        $sql = "SELECT fp.`fldEmployeeNum` AS id, fp.`fldRole` AS role,
                       el.`surname`, el.`firstname`, el.`email`, fp.`fldGroups` AS groups_raw
                FROM `formspic` fp
                INNER JOIN kdtphdb_new.`employee_list` el ON el.`id` = fp.`fldEmployeeNum`
                WHERE el.`emp_status` = 1
                ORDER BY fp.`fldRole` ASC, el.`surname` ASC";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? ($stmt->fetchAll() ?: []) : [];

        $matches = [];
        foreach ($rows as $row) {
            if (!$this->formPicGroupsContains((string) ($row['groups_raw'] ?? ''), $abbreviation)) {
                continue;
            }
            unset($row['groups_raw']);
            $matches[] = [
                'id' => (int) $row['id'],
                'role' => (int) $row['role'],
                'surname' => (string) ($row['surname'] ?? ''),
                'firstname' => (string) ($row['firstname'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
            ];
        }

        return $matches;
    }

    private function formPicGroupsContains(string $serialized, string $abbreviation): bool
    {
        $groups = @unserialize($serialized);
        if (is_array($groups)) {
            return in_array($abbreviation, $groups, true);
        }

        return stripos($serialized, '"' . $abbreviation . '"') !== false;
    }
}