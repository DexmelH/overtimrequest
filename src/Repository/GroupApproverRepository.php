<?php
namespace App\Repository;

use PDO;

class GroupApproverRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByGroupId(int $groupId): array
    {
        $sql = "SELECT oga.`approval_level`, oga.`approver_id`, oga.`updated_at`,
                       el.`surname`, el.`firstname`, el.`email`
                FROM `overtime_group_approvers` oga
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = oga.`approver_id`
                WHERE oga.`group_id` = :groupId
                ORDER BY oga.`approval_level` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':groupId' => $groupId]);
        $rows = $stmt->fetchAll() ?: [];

        $levels = [];
        foreach ($rows as $row) {
            $levels[(int) $row['approval_level']] = $row;
        }
        return $levels;
    }

    public function findApproversByGroupId(int $groupId, int $excludeUserId): array
    {
        $sql = "SELECT el.`id`, el.`surname`, el.`email`, oga.`approval_level`
                FROM `overtime_group_approvers` oga
                INNER JOIN kdtphdb_new.`employee_list` el ON el.`id` = oga.`approver_id`
                WHERE oga.`group_id` = :groupId AND el.`id` != :userId
                ORDER BY oga.`approval_level` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':groupId' => $groupId,
            ':userId' => $excludeUserId,
        ]);
        $data = $stmt->fetchAll();

        return $data ?: [];
    }

    public function findApproversByGroupAbbreviation(string $abbreviation, int $excludeUserId): array
    {
        $sql = "SELECT el.`id`, el.`surname`, el.`email`, oga.`approval_level`
                FROM `overtime_group_approvers` oga
                INNER JOIN kdtphdb_new.`group_list` gl ON gl.`id` = oga.`group_id`
                INNER JOIN kdtphdb_new.`employee_list` el ON el.`id` = oga.`approver_id`
                WHERE gl.`abbreviation` = :abbreviation AND el.`id` != :userId
                ORDER BY oga.`approval_level` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':abbreviation' => $abbreviation,
            ':userId' => $excludeUserId,
        ]);
        $data = $stmt->fetchAll();

        return $data ?: [];
    }

    public function hasConfiguredApprovers(int $groupId): bool
    {
        $sql = "SELECT COUNT(*) FROM `overtime_group_approvers` WHERE `group_id` = :groupId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':groupId' => $groupId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function saveForGroup(int $groupId, array $levels, int $updatedBy): void
    {
        $delete = $this->pdo->prepare(
            "DELETE FROM `overtime_group_approvers` WHERE `group_id` = :groupId AND `approval_level` = :level"
        );
        $upsert = $this->pdo->prepare(
            "INSERT INTO `overtime_group_approvers` (`group_id`, `approval_level`, `approver_id`, `updated_by`)
             VALUES (:groupId, :level, :approverId, :updatedBy)
             ON DUPLICATE KEY UPDATE `approver_id` = VALUES(`approver_id`), `updated_by` = VALUES(`updated_by`)"
        );

        for ($level = 1; $level <= 4; $level++) {
            $approverId = isset($levels[$level]) ? (int) $levels[$level] : 0;
            if ($approverId <= 0) {
                $delete->execute([':groupId' => $groupId, ':level' => $level]);
                continue;
            }
            $upsert->execute([
                ':groupId' => $groupId,
                ':level' => $level,
                ':approverId' => $approverId,
                ':updatedBy' => $updatedBy,
            ]);
        }
    }
}
