<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

class OtReportRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Aggregate OT hours for payroll / Daily Report use.
     *
     * @param array{
     *   from: string,
     *   to: string,
     *   group_id?: int,
     *   status?: string,
     *   group_by?: string
     * } $filters
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   summary: array<string, int|float>
     * }
     */
    public function findOtHours(array $filters): array
    {
        $from = (string) ($filters['from'] ?? date('Y-m-01'));
        $to = (string) ($filters['to'] ?? date('Y-m-d'));
        $groupId = (int) ($filters['group_id'] ?? 0);
        $status = strtolower(trim((string) ($filters['status'] ?? 'approved')));
        $groupBy = strtolower(trim((string) ($filters['group_by'] ?? 'project')));

        $where = [
            'orq.`request_date` >= :fromDate',
            'orq.`request_date` <= :toDate',
        ];
        $params = [
            ':fromDate' => $from,
            ':toDate' => $to,
        ];

        if ($groupId > 0) {
            $where[] = 'orq.`group_id` = :groupId';
            $params[':groupId'] = $groupId;
        }

        if ($status === 'approved') {
            $where[] = 'orq.`status` = 1';
        } elseif ($status === 'denied') {
            $where[] = 'orq.`status` = 0';
        } elseif ($status === 'cancelled') {
            $where[] = 'orq.`status` = 2';
        } elseif ($status === 'pending') {
            $where[] = '(orq.`status` IS NULL OR orq.`status` = \'\')';
        }
        // status=all → no status predicate

        $whereSql = implode(' AND ', $where);

        if ($groupBy === 'employee') {
            $select = "orq.`user_id` AS `employee_id`,
                       TRIM(CONCAT(COALESCE(el.`surname`, ''), ' ', COALESCE(el.`firstname`, ''))) AS `employee_name`,
                       orq.`group_id`,
                       gl.`abbreviation` AS `group_name`,
                       NULL AS `project_id`,
                       NULL AS `project_name`,
                       SUM(orp.`hours`) AS `hours`,
                       COUNT(DISTINCT orq.`id`) AS `request_count`,
                       COUNT(DISTINCT orp.`project_id`) AS `project_count`";
            $groupSql = 'orq.`user_id`, orq.`group_id`, el.`surname`, el.`firstname`, gl.`abbreviation`';
            $orderSql = 'gl.`abbreviation` ASC, el.`surname` ASC, el.`firstname` ASC';
        } elseif ($groupBy === 'group') {
            $select = "NULL AS `employee_id`,
                       NULL AS `employee_name`,
                       orq.`group_id`,
                       gl.`abbreviation` AS `group_name`,
                       NULL AS `project_id`,
                       NULL AS `project_name`,
                       SUM(orp.`hours`) AS `hours`,
                       COUNT(DISTINCT orq.`id`) AS `request_count`,
                       COUNT(DISTINCT orq.`user_id`) AS `employee_count`,
                       COUNT(DISTINCT orp.`project_id`) AS `project_count`";
            $groupSql = 'orq.`group_id`, gl.`abbreviation`';
            $orderSql = 'gl.`abbreviation` ASC';
        } else {
            // project (default): employee × group × project
            $select = "orq.`user_id` AS `employee_id`,
                       TRIM(CONCAT(COALESCE(el.`surname`, ''), ' ', COALESCE(el.`firstname`, ''))) AS `employee_name`,
                       orq.`group_id`,
                       gl.`abbreviation` AS `group_name`,
                       orp.`project_id`,
                       COALESCE(pt.`fldProject`, CONCAT('Project #', orp.`project_id`)) AS `project_name`,
                       SUM(orp.`hours`) AS `hours`,
                       COUNT(DISTINCT orq.`id`) AS `request_count`,
                       1 AS `project_count`";
            $groupSql = 'orq.`user_id`, orq.`group_id`, orp.`project_id`, el.`surname`, el.`firstname`,
                         gl.`abbreviation`, pt.`fldProject`';
            $orderSql = 'gl.`abbreviation` ASC, el.`surname` ASC, el.`firstname` ASC, pt.`fldProject` ASC';
        }

        $sql = "SELECT {$select}
                FROM `overtime_request` orq
                INNER JOIN `overtime_request_projects` orp ON orp.`overtime_request_id` = orq.`id`
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = orq.`user_id`
                LEFT JOIN kdtphdb_new.`group_list` gl ON gl.`id` = orq.`group_id`
                LEFT JOIN `projectstable` pt ON pt.`fldID` = orp.`project_id`
                WHERE {$whereSql}
                GROUP BY {$groupSql}
                ORDER BY {$orderSql}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $raw = $stmt->fetchAll() ?: [];

        $rows = [];
        foreach ($raw as $row) {
            $hours = (float) ($row['hours'] ?? 0);
            $requestCount = (int) ($row['request_count'] ?? 0);
            $employeeId = isset($row['employee_id']) && $row['employee_id'] !== null && $row['employee_id'] !== ''
                ? (int) $row['employee_id']
                : null;
            $projectId = isset($row['project_id']) && $row['project_id'] !== null && $row['project_id'] !== ''
                ? (int) $row['project_id']
                : null;
            $gid = isset($row['group_id']) && $row['group_id'] !== null && $row['group_id'] !== ''
                ? (int) $row['group_id']
                : null;

            $normalized = [
                'employee_id' => $employeeId,
                'employee_name' => trim((string) ($row['employee_name'] ?? '')) ?: null,
                'group_id' => $gid,
                'group_name' => trim((string) ($row['group_name'] ?? '')) ?: null,
                'project_id' => $projectId,
                'project_name' => trim((string) ($row['project_name'] ?? '')) ?: null,
                'hours' => round($hours, 2),
                'request_count' => $requestCount,
            ];
            if (isset($row['employee_count'])) {
                $normalized['employee_count'] = (int) $row['employee_count'];
            }
            if (isset($row['project_count'])) {
                $normalized['project_count'] = (int) $row['project_count'];
            }

            $rows[] = $normalized;
        }

        $summarySql = "SELECT
                          COALESCE(SUM(orp.`hours`), 0) AS `total_hours`,
                          COUNT(DISTINCT orq.`id`) AS `request_count`,
                          COUNT(DISTINCT orq.`user_id`) AS `employee_count`,
                          COUNT(DISTINCT orp.`project_id`) AS `project_count`,
                          COUNT(DISTINCT orq.`group_id`) AS `group_count`
                       FROM `overtime_request` orq
                       INNER JOIN `overtime_request_projects` orp ON orp.`overtime_request_id` = orq.`id`
                       WHERE {$whereSql}";
        $summaryStmt = $this->pdo->prepare($summarySql);
        $summaryStmt->execute($params);
        $summaryRow = $summaryStmt->fetch() ?: [];

        return [
            'rows' => $rows,
            'summary' => [
                'total_hours' => round((float) ($summaryRow['total_hours'] ?? 0), 2),
                'row_count' => count($rows),
                'request_count' => (int) ($summaryRow['request_count'] ?? 0),
                'employee_count' => (int) ($summaryRow['employee_count'] ?? 0),
                'project_count' => (int) ($summaryRow['project_count'] ?? 0),
                'group_count' => (int) ($summaryRow['group_count'] ?? 0),
            ],
        ];
    }
}
