<?php
namespace App\Repository;

use PDO;

class AdminMemberRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function exists(int $employeeId): bool
    {
        if ($employeeId <= 0) {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM `overtime_app_admins` WHERE `employee_id` = :employeeId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':employeeId' => $employeeId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return array<int, array<string, mixed>> */
    public function findAll(): array
    {
        $sql = "SELECT oa.`employee_id`, oa.`notes`, oa.`updated_at`, oa.`updated_by`, oa.`created_at`,
                       el.`surname`, el.`firstname`, el.`email`, gl.`abbreviation` AS `group_abbr`
                FROM `overtime_app_admins` oa
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = oa.`employee_id`
                LEFT JOIN kdtphdb_new.`group_list` gl ON gl.`id` = el.`group_id`
                ORDER BY el.`surname` ASC, el.`firstname` ASC";
        $stmt = $this->pdo->query($sql);

        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    public function findByEmployeeId(int $employeeId): ?array
    {
        $sql = "SELECT oa.`employee_id`, oa.`notes`, oa.`updated_at`, oa.`updated_by`,
                       el.`surname`, el.`firstname`, el.`email`
                FROM `overtime_app_admins` oa
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = oa.`employee_id`
                WHERE oa.`employee_id` = :employeeId
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':employeeId' => $employeeId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function add(int $employeeId, ?string $notes, int $actorId): void
    {
        $sql = "INSERT INTO `overtime_app_admins`
                    (`employee_id`, `notes`, `created_by`, `updated_by`)
                VALUES
                    (:employeeId, :notes, :createdBy, :updatedBy)
                ON DUPLICATE KEY UPDATE
                    `notes` = VALUES(`notes`),
                    `updated_by` = VALUES(`updated_by`)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':employeeId' => $employeeId,
            ':notes' => $notes !== null && $notes !== '' ? $notes : null,
            ':createdBy' => $actorId,
            ':updatedBy' => $actorId,
        ]);
    }

    public function updateNotes(int $employeeId, ?string $notes, int $actorId): bool
    {
        $sql = "UPDATE `overtime_app_admins`
                SET `notes` = :notes, `updated_by` = :updatedBy
                WHERE `employee_id` = :employeeId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':notes' => $notes !== null && $notes !== '' ? $notes : null,
            ':updatedBy' => $actorId,
            ':employeeId' => $employeeId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function remove(int $employeeId): bool
    {
        $sql = "DELETE FROM `overtime_app_admins` WHERE `employee_id` = :employeeId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':employeeId' => $employeeId]);

        return $stmt->rowCount() > 0;
    }
}
