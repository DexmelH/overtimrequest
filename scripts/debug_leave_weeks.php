<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
$config = require dirname(__DIR__) . '/src/config.php';

$db = new App\Database($config['connections']);
$forms = $db->getConnection('forms');
$repo = new App\Repository\LeaveRepository($forms);

$from = $argv[1] ?? date('Y-m-d');
$id1 = isset($argv[2]) ? (int) $argv[2] : 0;
$id2 = isset($argv[3]) ? (int) $argv[3] : 0;

[$workWeekStart] = App\Repository\LeaveRepository::workWeekBoundsForDate($from);
echo "from={$from}\nworkWeekStart={$workWeekStart}\n\n";

$dumpEmployee = static function (PDO $forms, App\Repository\LeaveRepository $repo, int $employeeId, string $from, string $workWeekStart): void {
    echo "=== employee_id={$employeeId} ===\n";

    $raw = $forms->prepare(
        'SELECT li.l_id, li.l_sdate, li.l_edate, li.l_duration, li.l_status
         FROM leave_info li
         WHERE li.l_eid = ? AND li.l_edate >= ?
         ORDER BY li.l_sdate ASC'
    );
    $raw->execute([$employeeId, $workWeekStart]);
    $rawRows = $raw->fetchAll() ?: [];
    echo 'raw leave_info rows: ' . count($rawRows) . "\n";
    foreach ($rawRows as $row) {
        echo '  ' . json_encode($row) . "\n";
    }

    $accepted = $forms->prepare(
        'SELECT li.l_id, li.l_sdate, li.l_edate, li.l_duration, li.l_status,
                (SELECT COUNT(*) FROM leave_accept la WHERE la.fldLeaveID = li.l_id AND la.fldAccept = 1) AS accept_count
         FROM leave_info li
         WHERE li.l_eid = ? AND li.l_edate >= ?
         ORDER BY li.l_sdate ASC'
    );
    $accepted->execute([$employeeId, $workWeekStart]);
    $accRows = $accepted->fetchAll() ?: [];
    echo "leave with accept counts:\n";
    foreach ($accRows as $row) {
        $ok = ((int) $row['accept_count'] >= 2) ? 'INCLUDED' : 'EXCLUDED';
        echo "  [{$ok}] " . json_encode($row) . "\n";
    }

    $weeks = $repo->findAcceptedLeaveWeekRanges($employeeId, $from);
    echo 'API leave_weeks: ' . json_encode($weeks) . "\n\n";
};

if ($id1 > 0 && $id2 > 0) {
    $dumpEmployee($forms, $repo, $id1, $from, $workWeekStart);
    $dumpEmployee($forms, $repo, $id2, $from, $workWeekStart);
    exit(0);
}

echo "No employee ids passed. Showing employees with raw leave vs fully accepted leave.\n\n";

$stmt = $forms->prepare(
    'SELECT li.l_eid, COUNT(*) AS raw_cnt
     FROM leave_info li
     WHERE li.l_edate >= ?
     GROUP BY li.l_eid
     ORDER BY raw_cnt DESC
     LIMIT 15'
);
$stmt->execute([$workWeekStart]);
$rawEmps = $stmt->fetchAll() ?: [];

$stmt2 = $forms->prepare(
    'SELECT li.l_eid, COUNT(*) AS accepted_cnt
     FROM leave_info li
     INNER JOIN (
         SELECT fldLeaveID
         FROM leave_accept
         WHERE fldAccept = 1
         GROUP BY fldLeaveID
         HAVING COUNT(*) = 2
     ) a ON a.fldLeaveID = li.l_id
     WHERE li.l_edate >= ?
     GROUP BY li.l_eid
     ORDER BY accepted_cnt DESC
     LIMIT 15'
);
$stmt2->execute([$workWeekStart]);
$accEmps = $stmt2->fetchAll() ?: [];
$accMap = [];
foreach ($accEmps as $row) {
    $accMap[(int) $row['l_eid']] = (int) $row['accepted_cnt'];
}

echo "employee_id | raw_leave_rows | accepted_leave_rows (API uses this)\n";
foreach ($rawEmps as $row) {
    $eid = (int) $row['l_eid'];
    $acceptedCnt = $accMap[$eid] ?? 0;
    echo sprintf("%11d | %14d | %d\n", $eid, (int) $row['raw_cnt'], $acceptedCnt);
}

echo "\nUsage:\n  php scripts/debug_leave_weeks.php 2026-08-25 <id1> <id2>\n";
