<?php
namespace App\Repository;

use PDO;

/**
 * Cascading work lookups against webjmr tables (same sources as Daily Report).
 */
class WorkLookupRepository
{
    private PDO $pdo;
    private ?int $leaveProjectId = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getLeaveProjectId(): int
    {
        if ($this->leaveProjectId !== null) {
            return $this->leaveProjectId;
        }

        $stmt = $this->pdo->prepare(
            "SELECT `fldID` FROM `projectstable` WHERE `fldProject` = 'Leave' LIMIT 1"
        );
        $stmt->execute();
        $this->leaveProjectId = (int) ($stmt->fetchColumn() ?: 0);

        return $this->leaveProjectId;
    }

    public function findProjectDirect(int $projectId): ?int
    {
        if ($projectId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT `fldDirect` FROM `projectstable`
             WHERE `fldID` = :id AND `fldActive` = 1 AND `fldDelete` = 0
             LIMIT 1"
        );
        $stmt->execute([':id' => $projectId]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function findItems(int $projectId, string $groupAbbreviation): array
    {
        if ($projectId <= 0) {
            return [];
        }

        $sql = "SELECT `fldID` AS `id`, `fldItem` AS `name`
                FROM `itemofworkstable`
                WHERE `fldProject` = :projectId
                  AND `fldActive` = 1
                  AND `fldDelete` = 0
                ORDER BY `fldPriority`, `fldItem`";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':projectId' => $projectId,
        ]);

        return $this->mapIdNameRows($stmt->fetchAll() ?: []);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function findJobs(int $projectId, int $itemId, string $groupAbbreviation): array
    {
        if ($projectId <= 0 || $itemId <= 0) {
            return [];
        }

        $sql = "SELECT `fldID` AS `id`, `fldJob` AS `name`
                FROM `drawingreference`
                WHERE `fldProject` = :projectId
                  AND `fldItem` = :itemId
                  AND `fldActive` = 1
                  AND `fldDelete` = 0
                ORDER BY `fldPriority`, `fldJob`";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':projectId' => $projectId,
            ':itemId' => $itemId,
        ]);

        return $this->mapIdNameRows($stmt->fetchAll() ?: []);
    }

    /**
     * @return array<int, array{id: int, name: string, description: string}>
     */
    public function findTypesOfWork(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        $leaveId = $this->getLeaveProjectId();
        $type = ($leaveId > 0 && $projectId === $leaveId) ? 0 : 1;

        $sql = "SELECT `fldID` AS `id`, `fldTOW` AS `name`,
                       COALESCE(`fldTOWDesc`, '') AS `description`
                FROM `typesofworktable`
                WHERE `fldTOWType` = :type
                  AND `fldActive` = 1
                ORDER BY `fldPrio`, `fldID`";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':type' => $type]);

        $out = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'name' => $name,
                'description' => trim((string) ($row['description'] ?? '')),
            ];
        }

        return $out;
    }

    public function itemBelongsToProject(int $itemId, int $projectId, string $groupAbbreviation): bool
    {
        if ($itemId <= 0 || $projectId <= 0) {
            return false;
        }

        $sql = "SELECT 1 FROM `itemofworkstable`
                WHERE `fldID` = :itemId
                  AND `fldProject` = :projectId
                  AND `fldActive` = 1
                  AND `fldDelete` = 0
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':itemId' => $itemId,
            ':projectId' => $projectId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function jobBelongsToProjectItem(int $jobId, int $projectId, int $itemId, string $groupAbbreviation): bool
    {
        if ($jobId <= 0 || $projectId <= 0 || $itemId <= 0) {
            return false;
        }

        $sql = "SELECT 1 FROM `drawingreference`
                WHERE `fldID` = :jobId
                  AND `fldProject` = :projectId
                  AND `fldItem` = :itemId
                  AND `fldActive` = 1
                  AND `fldDelete` = 0
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':jobId' => $jobId,
            ':projectId' => $projectId,
            ':itemId' => $itemId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function towIsValidForProject(int $towId, int $projectId): bool
    {
        if ($towId <= 0 || $projectId <= 0) {
            return false;
        }

        $leaveId = $this->getLeaveProjectId();
        $type = ($leaveId > 0 && $projectId === $leaveId) ? 0 : 1;

        $sql = "SELECT 1 FROM `typesofworktable`
                WHERE `fldID` = :towId
                  AND `fldTOWType` = :type
                  AND `fldActive` = 1
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':towId' => $towId,
            ':type' => $type,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{id: int, name: string}>
     */
    private function mapIdNameRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $out[] = ['id' => $id, 'name' => $name];
        }

        return $out;
    }
}
