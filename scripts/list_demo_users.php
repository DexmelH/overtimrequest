<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
$config = require dirname(__DIR__) . '/src/config.php';
$db = new App\Database($config['connections'] ?? $config);
$webjmr = $db->getConnection('webjmr');
$kdtph = $db->getConnection('kdtph');

function hashFor(PDO $kdtph, int $id): string
{
    $stmt = $kdtph->prepare('SELECT fldUserHash FROM kdtlogin WHERE fldEmployeeNum = ? LIMIT 1');
    $stmt->execute([$id]);
    return (string) ($stmt->fetchColumn() ?: '');
}

echo "=== Approvers (OGA) ===\n";
$sql = "SELECT el.id, el.surname, el.firstname, el.email, gl.abbreviation AS grp
        FROM overtime_group_approvers oga
        INNER JOIN kdtphdb_new.employee_list el ON el.id = oga.approver_id
        LEFT JOIN kdtphdb_new.group_list gl ON gl.id = el.group_id
        WHERE el.emp_status = 1
        GROUP BY el.id, el.surname, el.firstname, el.email, gl.abbreviation
        ORDER BY el.surname
        LIMIT 10";
foreach ($webjmr->query($sql) as $r) {
    $h = hashFor($kdtph, (int) $r['id']);
    echo sprintf("%d\t%s %s\t%s\thash=%s\n", $r['id'], $r['surname'], $r['firstname'], $r['grp'], $h !== '' ? $h : 'NONE');
}

echo "\n=== App admins ===\n";
$sql = "SELECT el.id, el.surname, el.firstname, gl.abbreviation AS grp
        FROM overtime_app_admins oa
        INNER JOIN kdtphdb_new.employee_list el ON el.id = oa.employee_id
        LEFT JOIN kdtphdb_new.group_list gl ON gl.id = el.group_id
        LIMIT 10";
foreach ($webjmr->query($sql) as $r) {
    $h = hashFor($kdtph, (int) $r['id']);
    echo sprintf("%d\t%s %s\t%s\thash=%s\n", $r['id'], $r['surname'], $r['firstname'], $r['grp'], $h !== '' ? $h : 'NONE');
}

echo "\n=== Employees with recent OT or login ===\n";
$sql = "SELECT el.id, el.surname, el.firstname, gl.abbreviation AS grp, COUNT(orq.id) AS ot_count
        FROM kdtphdb_new.employee_list el
        LEFT JOIN kdtphdb_new.group_list gl ON gl.id = el.group_id
        LEFT JOIN overtime_request orq ON orq.user_id = el.id
        WHERE el.emp_status = 1
        GROUP BY el.id, el.surname, el.firstname, gl.abbreviation
        HAVING ot_count > 0
        ORDER BY ot_count DESC
        LIMIT 12";
foreach ($webjmr->query($sql) as $r) {
    $h = hashFor($kdtph, (int) $r['id']);
    if ($h === '') {
        continue;
    }
    echo sprintf("%d\t%s %s\t%s\tot=%d\thash=%s\n", $r['id'], $r['surname'], $r['firstname'], $r['grp'], $r['ot_count'], $h);
}

echo "\n=== JSON for capture ===\n";
$out = ['employee' => null, 'approver' => null, 'admin' => null];

$approverRow = $webjmr->query(
    "SELECT el.id, el.surname, el.firstname FROM overtime_group_approvers oga
     INNER JOIN kdtphdb_new.employee_list el ON el.id = oga.approver_id
     WHERE el.emp_status = 1 GROUP BY el.id ORDER BY el.surname LIMIT 1"
)->fetch();
if ($approverRow) {
    $h = hashFor($kdtph, (int) $approverRow['id']);
    if ($h !== '') {
        $out['approver'] = [
            'id' => (int) $approverRow['id'],
            'name' => trim($approverRow['surname'] . ' ' . $approverRow['firstname']),
            'hash' => $h,
        ];
    }
}

$adminRow = $webjmr->query(
    "SELECT el.id, el.surname, el.firstname FROM overtime_app_admins oa
     INNER JOIN kdtphdb_new.employee_list el ON el.id = oa.employee_id LIMIT 1"
)->fetch();
if ($adminRow) {
    $h = hashFor($kdtph, (int) $adminRow['id']);
    if ($h !== '') {
        $out['admin'] = [
            'id' => (int) $adminRow['id'],
            'name' => trim($adminRow['surname'] . ' ' . $adminRow['firstname']),
            'hash' => $h,
        ];
    }
}

$empRow = $webjmr->query(
    "SELECT el.id, el.surname, el.firstname, COUNT(orq.id) c
     FROM kdtphdb_new.employee_list el
     INNER JOIN overtime_request orq ON orq.user_id = el.id
     WHERE el.emp_status = 1
     GROUP BY el.id ORDER BY c DESC LIMIT 20"
)->fetchAll();
foreach ($empRow as $r) {
    $h = hashFor($kdtph, (int) $r['id']);
    if ($h === '') {
        continue;
    }
    // Prefer someone who is not only an approver for variety
    $out['employee'] = [
        'id' => (int) $r['id'],
        'name' => trim($r['surname'] . ' ' . $r['firstname']),
        'hash' => $h,
    ];
    break;
}

file_put_contents(dirname(__DIR__) . '/docs/manual_users.json', json_encode($out, JSON_PRETTY_PRINT));
echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
