<?php
namespace App\Repository;

use PDO;

class HolidayRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array<int, array{date: string, name: string}> */
    public function findFromDate(string $fromDate): array
    {
        $sql = "SELECT `fldDate` AS `date`, MIN(`fldHoliday`) AS `name`
                FROM `kdtholiday`
                WHERE `fldDate` >= :fromDate
                GROUP BY `fldDate`
                ORDER BY `fldDate` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fromDate' => $fromDate]);

        return $stmt->fetchAll() ?: [];
    }

    public function isBlockedDate(string $date): bool
    {
        $sql = "SELECT COUNT(*) FROM `kdtholiday` WHERE `fldDate` = :date";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':date' => $date]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findHolidayName(string $date): ?string
    {
        $sql = "SELECT `fldHoliday` FROM `kdtholiday` WHERE `fldDate` = :date LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':date' => $date]);
        $name = $stmt->fetchColumn();

        return $name !== false ? (string) $name : null;
    }
}
