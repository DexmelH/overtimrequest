<?php
namespace App\Repository;

use DateTime;
use PDO;

class LeaveRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function hasAcceptedLeaveInWeek(int $employeeId, string $weekStart, string $weekEnd): bool
    {
        $sql = "SELECT COALESCE(SUM(li.`l_duration`), 0)
                FROM `leave_info` li
                INNER JOIN (
                    SELECT `fldLeaveID`
                    FROM `leave_accept`
                    WHERE `fldAccept` = 1
                    GROUP BY `fldLeaveID`
                    HAVING COUNT(*) = 2
                ) accepted ON accepted.`fldLeaveID` = li.`l_id`
                WHERE li.`l_eid` = :employeeId
                  AND li.`l_sdate` <= :weekEnd
                  AND li.`l_edate` >= :weekStart";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':employeeId' => $employeeId,
            ':weekStart' => $weekStart,
            ':weekEnd' => $weekEnd,
        ]);

        return (float) $stmt->fetchColumn() >= 1;
    }

    /** @return array<int, array{start: string, end: string}> */
    public function findAcceptedLeaveWeekRanges(int $employeeId, string $fromDate): array
    {
        [$workWeekStart] = self::workWeekBoundsForDate($fromDate);

        $sql = "SELECT li.`l_sdate` AS start_date, li.`l_edate` AS end_date, li.`l_duration` AS duration
                FROM `leave_info` li
                INNER JOIN (
                    SELECT `fldLeaveID`
                    FROM `leave_accept`
                    WHERE `fldAccept` = 1
                    GROUP BY `fldLeaveID`
                    HAVING COUNT(*) = 2
                ) accepted ON accepted.`fldLeaveID` = li.`l_id`
                WHERE li.`l_eid` = :employeeId
                  AND li.`l_edate` >= :workWeekStart
                ORDER BY li.`l_sdate` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':employeeId' => $employeeId,
            ':workWeekStart' => $workWeekStart,
        ]);

        $rows = $stmt->fetchAll() ?: [];
        $ranges = [];
        $seen = [];
        $totalDuration = (float) 0;

        foreach ($rows as $row) {
            $leaveStart = new DateTime((string) $row['start_date']);
            $leaveEnd = new DateTime((string) $row['end_date']);
            $duration = (float) $row['duration'];
            $week = clone $leaveStart;
            $week->setISODate((int) $week->format('o'), (int) $week->format('W'), 1);

            while ($week <= $leaveEnd) {
                $totalDuration = $totalDuration + $duration;
                $friday = clone $week;
                $friday->modify('+4 days');
                $weekStartKey = $week->format('Y-m-d');
                $weekEndKey = $friday->format('Y-m-d');

                if ($weekEndKey >= $workWeekStart) {
                    if ($totalDuration >= 1 && !isset($seen[$weekStartKey])) {
                            $ranges[] = [
                                'start' => $weekStartKey,
                                'end' => $weekEndKey,
                            ];
                            $seen[$weekStartKey] = true;
                            $totalDuration = (float) 0;
                    }
                }

                $week->modify('+7 days');
            }
        }

        return $ranges;
    }

    /** Work week = Monday through Friday for the week containing the date. @return array{0: string, 1: string} */
    public static function workWeekBoundsForDate(string $date): array
    {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt) {
            return [$date, $date];
        }

        $start = clone $dt;
        $start->setISODate((int) $dt->format('o'), (int) $dt->format('W'), 1);
        $end = clone $start;
        $end->modify('+4 days');

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    /** @deprecated Use workWeekBoundsForDate() */
    public static function weekBoundsForDate(string $date): array
    {
        return self::workWeekBoundsForDate($date);
    }
}
