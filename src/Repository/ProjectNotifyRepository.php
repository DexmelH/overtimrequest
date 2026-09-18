<?php
namespace App\Repository;

use PDO;
use PDOException;
use RuntimeException;

class ProjectNotifyRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Notify rows, optionally filtered by project group abbreviation and/or project id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(?string $groupAbbrev = null, ?int $projectId = null): array
    {
        $sql = "SELECT opn.`project_id`, opn.`employee_id`, opn.`updated_at`,
                       pt.`fldProject` AS `project_name`, pt.`fldGroup` AS `project_group`,
                       el.`surname`, el.`firstname`, el.`email`
                FROM `overtime_project_notify` opn
                INNER JOIN `projectstable` pt ON pt.`fldID` = opn.`project_id`
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = opn.`employee_id`
                WHERE 1=1";
        $params = [];

        $groupAbbrev = $groupAbbrev !== null ? trim($groupAbbrev) : '';
        if ($groupAbbrev !== '') {
            $sql .= " AND pt.`fldGroup` = :abbr";
            $params[':abbr'] = $groupAbbrev;
        }

        if ($projectId !== null && $projectId > 0) {
            $sql .= " AND opn.`project_id` = :projectId";
            $params[':projectId'] = $projectId;
        }

        $sql .= " ORDER BY pt.`fldGroup` ASC, pt.`fldProject` ASC, el.`surname` ASC, el.`firstname` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Distinct projects that already have notify recipients (for list filters).
     *
     * @return array<int, array{id: int, name: string, group: string}>
     */
    public function findFilterProjects(?string $groupAbbrev = null): array
    {
        $sql = "SELECT DISTINCT pt.`fldID` AS `id`, pt.`fldProject` AS `name`, pt.`fldGroup` AS `group`
                FROM `overtime_project_notify` opn
                INNER JOIN `projectstable` pt ON pt.`fldID` = opn.`project_id`
                WHERE 1=1";
        $params = [];

        $groupAbbrev = $groupAbbrev !== null ? trim($groupAbbrev) : '';
        if ($groupAbbrev !== '') {
            $sql .= " AND pt.`fldGroup` = :abbr";
            $params[':abbr'] = $groupAbbrev;
        }

        $sql .= " ORDER BY pt.`fldGroup` ASC, pt.`fldProject` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'group' => (string) ($row['group'] ?? ''),
            ];
        }, $rows);
    }

    /**
     * Notify rows for projects belonging to a group abbreviation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByGroupAbbreviation(string $groupAbbrev): array
    {
        return $this->findAll($groupAbbrev, null);
    }

    /**
     * Recipients for new_request emails, keyed by matching project ids.
     * Deduped by employee_id (one row per person even if watching multiple projects).
     *
     * @param int[] $projectIds
     * @return array<int, array{employee_id: int, surname: string, email: string, project_ids: int[]}>
     */
    public function findRecipientsByProjectIds(array $projectIds): array
    {
        $projectIds = array_values(array_unique(array_filter(
            array_map('intval', $projectIds),
            static fn (int $id): bool => $id > 0
        )));
        if (!$projectIds) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($projectIds as $i => $id) {
            $key = ':p' . $i;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $sql = "SELECT opn.`employee_id`, opn.`project_id`,
                       el.`surname`, el.`email`
                FROM `overtime_project_notify` opn
                INNER JOIN kdtphdb_new.`employee_list` el ON el.`id` = opn.`employee_id`
                WHERE opn.`project_id` IN (" . implode(',', $placeholders) . ")
                  AND el.`email` IS NOT NULL
                  AND TRIM(el.`email`) <> ''";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        $byEmployee = [];
        foreach ($rows as $row) {
            $employeeId = (int) ($row['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                continue;
            }
            if (!isset($byEmployee[$employeeId])) {
                $byEmployee[$employeeId] = [
                    'employee_id' => $employeeId,
                    'surname' => (string) ($row['surname'] ?? 'Notify'),
                    'email' => (string) ($row['email'] ?? ''),
                    'project_ids' => [],
                ];
            }
            $byEmployee[$employeeId]['project_ids'][] = (int) ($row['project_id'] ?? 0);
        }

        return array_values($byEmployee);
    }

    /** @return int[] */
    public function findEmployeeIdsByProjectId(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }
        $sql = "SELECT `employee_id` FROM `overtime_project_notify` WHERE `project_id` = :projectId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':projectId' => $projectId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_map('intval', $ids);
    }

    public function exists(int $projectId, int $employeeId): bool
    {
        if ($projectId <= 0 || $employeeId <= 0) {
            return false;
        }
        $sql = "SELECT COUNT(*) FROM `overtime_project_notify`
                WHERE `project_id` = :projectId AND `employee_id` = :employeeId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':projectId' => $projectId,
            ':employeeId' => $employeeId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function projectExists(int $projectId): bool
    {
        if ($projectId <= 0) {
            return false;
        }
        $sql = "SELECT COUNT(*) FROM `projectstable`
                WHERE `fldID` = :projectId AND `fldActive` = 1 AND `fldDelete` = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':projectId' => $projectId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findProjectName(int $projectId): string
    {
        if ($projectId <= 0) {
            return '';
        }
        $sql = "SELECT `fldProject` FROM `projectstable` WHERE `fldID` = :projectId LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':projectId' => $projectId]);
        $name = $stmt->fetchColumn();

        return is_string($name) ? $name : '';
    }

    /**
     * @throws RuntimeException when the pair already exists
     */
    public function add(int $projectId, int $employeeId, int $updatedBy): void
    {
        if ($projectId <= 0 || $employeeId <= 0) {
            throw new RuntimeException('Invalid project or employee.');
        }
        if (!$this->projectExists($projectId)) {
            throw new RuntimeException('Project not found or inactive.');
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `overtime_project_notify` (`project_id`, `employee_id`, `updated_by`)
                 VALUES (:projectId, :employeeId, :updatedBy)"
            );
            $stmt->execute([
                ':projectId' => $projectId,
                ':employeeId' => $employeeId,
                ':updatedBy' => $updatedBy > 0 ? $updatedBy : null,
            ]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new RuntimeException('This employee is already notify-only for this project.');
            }
            throw $e;
        }
    }

    public function remove(int $projectId, int $employeeId): bool
    {
        if ($projectId <= 0 || $employeeId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "DELETE FROM `overtime_project_notify`
             WHERE `project_id` = :projectId AND `employee_id` = :employeeId"
        );
        $stmt->execute([
            ':projectId' => $projectId,
            ':employeeId' => $employeeId,
        ]);

        return $stmt->rowCount() > 0;
    }
}
