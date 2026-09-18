<?php
namespace App\Repository;

use PDO;
use PDOException;
use RuntimeException;

class GroupApproverRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Flat list of OGA rows for a group (multiple people per level allowed).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByGroupId(int $groupId): array
    {
        $sql = "SELECT oga.`approval_level`, oga.`approver_id`, oga.`updated_at`,
                       el.`surname`, el.`firstname`, el.`email`
                FROM `overtime_group_approvers` oga
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = oga.`approver_id`
                WHERE oga.`group_id` = :groupId
                ORDER BY oga.`approval_level` ASC, el.`surname` ASC, el.`firstname` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':groupId' => $groupId]);

        return $stmt->fetchAll() ?: [];
    }

    /** @return int[] */
    public function findAssignedApproverIds(int $groupId): array
    {
        $sql = "SELECT `approver_id` FROM `overtime_group_approvers` WHERE `group_id` = :groupId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':groupId' => $groupId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_map('intval', $ids);
    }

    public function isApproverInGroup(int $groupId, int $approverId): bool
    {
        if ($groupId <= 0 || $approverId <= 0) {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM `overtime_group_approvers`
                WHERE `group_id` = :groupId AND `approver_id` = :approverId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':groupId' => $groupId,
            ':approverId' => $approverId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findApproversByGroupId(int $groupId, int $excludeUserId): array
    {
        $sql = "SELECT el.`id`, el.`surname`, el.`firstname`, el.`email`,
                       el.`gender`, el.`marital_status`, oga.`approval_level`
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

    public function findApproverGroupDetails(int $approverId): array
    {
        $sql = "SELECT DISTINCT gl.`id`, gl.`abbreviation`, gl.`name`
                FROM `overtime_group_approvers` oga
                INNER JOIN kdtphdb_new.`group_list` gl ON gl.`id` = oga.`group_id`
                WHERE oga.`approver_id` = :approverId
                ORDER BY gl.`abbreviation` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':approverId' => $approverId]);

        return $stmt->fetchAll() ?: [];
    }

    public function isAssignedApprover(int $approverId): bool
    {
        $sql = "SELECT COUNT(*) FROM `overtime_group_approvers` WHERE `approver_id` = :approverId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':approverId' => $approverId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findHighestApprovalLevel(int $approverId): int
    {
        if ($approverId <= 0) {
            return 0;
        }

        $sql = "SELECT MAX(`approval_level`) FROM `overtime_group_approvers`
                WHERE `approver_id` = :approverId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':approverId' => $approverId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function hasConfiguredApprovers(int $groupId): bool
    {
        $sql = "SELECT COUNT(*) FROM `overtime_group_approvers` WHERE `group_id` = :groupId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':groupId' => $groupId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param int[] $groupIds
     * @return int[] group ids that have at least one OGA row
     */
    public function findConfiguredGroupIds(array $groupIds = []): array
    {
        $groupIds = array_values(array_unique(array_filter(
            array_map('intval', $groupIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($groupIds) {
            $placeholders = [];
            $params = [];
            foreach ($groupIds as $i => $id) {
                $key = ':g' . $i;
                $placeholders[] = $key;
                $params[$key] = $id;
            }
            $sql = "SELECT DISTINCT `group_id` FROM `overtime_group_approvers`
                    WHERE `group_id` IN (" . implode(',', $placeholders) . ")";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "SELECT DISTINCT `group_id` FROM `overtime_group_approvers`";
            $stmt = $this->pdo->query($sql);
        }

        $ids = $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];

        return array_map('intval', $ids);
    }

    /**
     * @throws RuntimeException when the person is already an approver for this group
     */
    public function addApprover(int $groupId, int $level, int $approverId, int $updatedBy): void
    {
        if ($level < 1 || $level > 4 || $approverId <= 0) {
            throw new RuntimeException('Invalid level or approver.');
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `overtime_group_approvers` (`group_id`, `approval_level`, `approver_id`, `updated_by`)
                 VALUES (:groupId, :level, :approverId, :updatedBy)"
            );
            $stmt->execute([
                ':groupId' => $groupId,
                ':level' => $level,
                ':approverId' => $approverId,
                ':updatedBy' => $updatedBy,
            ]);
        } catch (PDOException $e) {
            if ($this->isDuplicateKey($e)) {
                throw new RuntimeException('This employee is already an approver for this group.');
            }
            throw $e;
        }
    }

    public function removeApprover(int $groupId, int $approverId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM `overtime_group_approvers`
             WHERE `group_id` = :groupId AND `approver_id` = :approverId"
        );
        $stmt->execute([
            ':groupId' => $groupId,
            ':approverId' => $approverId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @throws RuntimeException when row missing or level invalid
     */
    public function changeApproverLevel(int $groupId, int $approverId, int $newLevel, int $updatedBy): void
    {
        if ($newLevel < 1 || $newLevel > 4 || $approverId <= 0) {
            throw new RuntimeException('Invalid level or approver.');
        }

        $stmt = $this->pdo->prepare(
            "UPDATE `overtime_group_approvers`
             SET `approval_level` = :level, `updated_by` = :updatedBy
             WHERE `group_id` = :groupId AND `approver_id` = :approverId"
        );
        $stmt->execute([
            ':level' => $newLevel,
            ':updatedBy' => $updatedBy,
            ':groupId' => $groupId,
            ':approverId' => $approverId,
        ]);

        if ($stmt->rowCount() === 0 && !$this->isApproverInGroup($groupId, $approverId)) {
            throw new RuntimeException('Approver is not assigned to this group.');
        }
    }

    private function isDuplicateKey(PDOException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? '');
        $msg = $e->getMessage();

        return $code === '1062' || stripos($msg, 'Duplicate') !== false;
    }
}
