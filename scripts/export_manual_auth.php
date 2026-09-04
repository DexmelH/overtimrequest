<?php
declare(strict_types=1);

/**
 * Export demo personas (hashes) to a temp JSON for screenshot capture only.
 * Does not write into the git tree.
 */

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

function pick(PDO $webjmr, PDO $kdtph, string $sql): ?array
{
    foreach ($webjmr->query($sql) as $r) {
        $h = hashFor($kdtph, (int) $r['id']);
        if ($h === '') {
            continue;
        }
        return [
            'id' => (int) $r['id'],
            'name' => trim(($r['surname'] ?? '') . ' ' . ($r['firstname'] ?? '')),
            'hash' => $h,
            'group' => (string) ($r['grp'] ?? ''),
        ];
    }
    return null;
}

$out = [
    'baseUrl' => rtrim((string) ($config['app']['url'] ?? 'http://localhost/overtime'), '/'),
    'employee' => pick(
        $webjmr,
        $kdtph,
        "SELECT el.id, el.surname, el.firstname, gl.abbreviation AS grp, COUNT(orq.id) c
         FROM kdtphdb_new.employee_list el
         LEFT JOIN kdtphdb_new.group_list gl ON gl.id = el.group_id
         INNER JOIN overtime_request orq ON orq.user_id = el.id
         WHERE el.emp_status = 1
         GROUP BY el.id, el.surname, el.firstname, gl.abbreviation
         ORDER BY c DESC LIMIT 30"
    ),
    'approver' => pick(
        $webjmr,
        $kdtph,
        "SELECT el.id, el.surname, el.firstname, gl.abbreviation AS grp
         FROM overtime_group_approvers oga
         INNER JOIN kdtphdb_new.employee_list el ON el.id = oga.approver_id
         LEFT JOIN kdtphdb_new.group_list gl ON gl.id = el.group_id
         WHERE el.emp_status = 1
         GROUP BY el.id, el.surname, el.firstname, gl.abbreviation
         ORDER BY el.surname LIMIT 10"
    ),
    'admin' => pick(
        $webjmr,
        $kdtph,
        "SELECT el.id, el.surname, el.firstname, gl.abbreviation AS grp
         FROM overtime_app_admins oa
         INNER JOIN kdtphdb_new.employee_list el ON el.id = oa.employee_id
         LEFT JOIN kdtphdb_new.group_list gl ON gl.id = el.group_id
         LIMIT 10"
    ),
];

// Verify sessions via internal HTTP
$verified = [];
foreach (['employee', 'approver', 'admin'] as $role) {
    if (empty($out[$role]['hash'])) {
        $verified[$role] = false;
        continue;
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Cookie: userID=" . $out[$role]['hash'] . "\r\n",
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($out['baseUrl'] . '/api/session', false, $ctx);
    $json = $body ? json_decode($body, true) : null;
    $verified[$role] = !empty($json['user']['id']);
    if ($verified[$role]) {
        $out[$role]['session_name'] = trim(($json['user']['name'] ?? '') ?: $out[$role]['name']);
        $out[$role]['is_approver'] = !empty($json['is_approver']);
        $out[$role]['is_admin'] = !empty($json['is_admin']);
    }
}
$out['verified'] = $verified;

$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ot-manual';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
$path = $dir . DIRECTORY_SEPARATOR . 'users.json';
file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo $path . "\n";
echo json_encode(['verified' => $verified, 'names' => [
    'employee' => $out['employee']['name'] ?? null,
    'approver' => $out['approver']['name'] ?? null,
    'admin' => $out['admin']['name'] ?? null,
]], JSON_PRETTY_PRINT) . "\n";
