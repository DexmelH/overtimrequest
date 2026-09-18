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
        $sql = "SELECT `fldID`, `fldProject`, `fldDirect` FROM `projectstable` 
        WHERE (`fldGroup` = :groupID OR `fldGroup` IS NULL) AND `fldActive` = 1 AND `fldDelete` = 0
        AND `fldID` NOT IN (5, 6)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":groupID" => $groupID]);
        $data = $stmt->fetchAll();

        return $data ? $data : [];
    }

    public function findProjectByUserID(string $userID): array
    {
        $sql = "SELECT pt.`fldID`, CONCAT(pt.`fldProject`, ' (', pt.`fldGroup`, ')') as `fldProject`,
                       pt.`fldDirect`
                FROM `projectstable` as `pt`
        LEFT JOIN `project_share` as `ps` ON pt.`fldID` = ps.`fldProject`
        WHERE ps.`fldEmployeeNum` = :userID AND pt.`fldActive` = 1 AND pt.`fldDelete` = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":userID" => $userID]);
        $data = $stmt->fetchAll();

        return $data ? $data : [];
    }

    /**
     * Same merge as the request page: group catalog + projects shared to the user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findProjectsForGroupAndUser(string $groupAbbrev, string $userID): array
    {
        $groupAbbrev = trim($groupAbbrev);
        $groupProjects = $groupAbbrev !== '' ? $this->findProjectByGroupID($groupAbbrev) : [];
        $sharedProjects = $userID !== '' ? $this->findProjectByUserID($userID) : [];

        return $this->mergeProjectsById($sharedProjects, $groupProjects);
    }

    /**
     * @param array<int, array<string, mixed>> ...$lists
     * @return array<int, array<string, mixed>>
     */
    public function mergeProjectsById(array ...$lists): array
    {
        $merged = [];
        foreach ($lists as $list) {
            foreach ($list as $row) {
                $id = (int) ($row['fldID'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $merged[$id] = $row;
            }
        }

        return array_values($merged);
    }
}