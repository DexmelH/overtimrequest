<?php
declare(strict_types=1);

/**
 * Automated QA checks for recent overtime app changes.
 * Run: php scripts/qa_check.php
 */

$root = dirname(__DIR__);
$results = [];

function qa(string $area, string $name, bool $pass, string $detail = ''): void
{
    global $results;
    $results[] = [
        'area' => $area,
        'name' => $name,
        'pass' => $pass,
        'detail' => $detail,
    ];
    $mark = $pass ? 'PASS' : 'FAIL';
    $line = sprintf("[%s] %s — %s", $mark, $area, $name);
    if ($detail !== '') {
        $line .= ' | ' . $detail;
    }
    echo $line . PHP_EOL;
}

echo "=== Overtime App QA ===" . PHP_EOL;
echo 'Run at: ' . date('c') . PHP_EOL . PHP_EOL;

// ---------------------------------------------------------------------------
// 1. Environment & config
// ---------------------------------------------------------------------------
$config = require $root . '/src/config.php';
qa('Env', 'Config loads', is_array($config) && isset($config['app'], $config['connections']));
qa(
    'Env',
    'APP_URL configured',
    ($config['app']['url'] ?? '') !== '',
    (string) ($config['app']['url'] ?? '')
);
qa(
    'Env',
    'Production hides exception text',
    ($config['app']['env'] ?? 'local') !== 'production' || ($config['app']['debug'] ?? true) === false,
    'env=' . ($config['app']['env'] ?? '?') . ', debug=' . (($config['app']['debug'] ?? false) ? 'true' : 'false')
);

foreach (['webjmr', 'kdtph', 'kdtphnew', 'forms'] as $conn) {
    try {
        $pdo = new PDO(
            $config['connections'][$conn]['dsn'],
            $config['connections'][$conn]['user'],
            $config['connections'][$conn]['pass'],
            $config['connections'][$conn]['options'] ?? []
        );
        $pdo->query('SELECT 1');
        qa('DB', "Connection: {$conn}", true);
    } catch (Throwable $e) {
        qa('DB', "Connection: {$conn}", false, $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// 2. Schema (migration 012)
// ---------------------------------------------------------------------------
try {
    $pdo = new PDO(
        $config['connections']['webjmr']['dsn'],
        $config['connections']['webjmr']['user'],
        $config['connections']['webjmr']['pass'],
        $config['connections']['webjmr']['options'] ?? []
    );
    $cols = $pdo->query("SHOW COLUMNS FROM overtime_request")->fetchAll(PDO::FETCH_COLUMN);
    qa('Schema', 'submitted_by column', in_array('submitted_by', $cols, true));
    qa('Schema', 'origin_request_id column', in_array('origin_request_id', $cols, true));
} catch (Throwable $e) {
    qa('Schema', 'overtime_request columns', false, $e->getMessage());
}

$dbUp = false;
foreach ($results as $r) {
    if ($r['area'] === 'DB' && $r['name'] === 'Connection: webjmr' && $r['pass']) {
        $dbUp = true;
        break;
    }
}

// ---------------------------------------------------------------------------
// 3. Services bootstrap (only when DB is up)
// ---------------------------------------------------------------------------
$container = null;
if ($dbUp) {
    try {
        $container = require $root . '/src/bootstrap.php';
        qa('Bootstrap', 'Container resolves', $container instanceof \App\Container);
    } catch (Throwable $e) {
        qa('Bootstrap', 'Container resolves', false, $e->getMessage());
        $dbUp = false;
    }
}

if (!$dbUp) {
    echo PHP_EOL . 'Database unavailable — running static checks only.' . PHP_EOL . PHP_EOL;

    $templateDir = $root . '/src/usr/template';
    foreach (['request_email.html', 'status_email.html', 'cancel_email.html'] as $file) {
        $html = (string) file_get_contents($templateDir . '/' . $file);
        qa('Email (static)', "{$file}: no rgba() text", preg_match_all('/color:\s*rgba\(/i', $html) === 0);
        qa('Email (static)', "{$file}: header bgcolor fallback", (bool) preg_match('/bgcolor="/i', $html));
        qa('Email (static)', "{$file}: no faint #94a3b8", !preg_match('/#94a3b8/i', $html));
        qa('Email (static)', "{$file}: has action_url placeholder", str_contains($html, '{{action_url}}'));
    }

    $phpFiles = [
        'src/Service/OvertimeSubmissionService.php',
        'src/Service/OvertimeApprovalService.php',
        'src/Service/ApproverDirectoryService.php',
        'src/Service/MailService.php',
        'src/Application.php',
        'public/api.php',
        'src/usr/bin/email_worker.php',
        'src/usr/bin/finalize_approvals.php',
    ];
    foreach ($phpFiles as $file) {
        $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $file);
        exec('"' . PHP_BINARY . '" -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        qa('Syntax', $file, $code === 0, trim(implode(' ', $out)));
    }

    goto summary;
}

try {
/** @var \App\Service\ApproverDirectoryService $approverDir */
$approverDir = $container->get(\App\Service\ApproverDirectoryService::class);
/** @var \App\Service\OvertimeApprovalService $approvalSvc */
$approvalSvc = $container->get(\App\Service\OvertimeApprovalService::class);
/** @var \App\Service\OvertimeSubmissionService $submitSvc */
$submitSvc = $container->get(\App\Service\OvertimeSubmissionService::class);
/** @var \App\Service\MailService $mailSvc */
$mailSvc = $container->get(\App\Service\MailService::class);
/** @var \App\Service\EmailTemplate $templates */
$templates = $container->get(\App\Service\EmailTemplate::class);

// ---------------------------------------------------------------------------
// 4. Approver directory — Form PIC keeps OGA-configured groups
// ---------------------------------------------------------------------------
$groups134 = $approverDir->findApproverGroupsForUser(134);
$abbr134 = array_column($groups134, 'abbreviation');
qa(
    'On-behalf auth',
    'Form PIC includes OGA-configured SYS/IT (user 134)',
    in_array('SYS', $abbr134, true) && in_array('IT', $abbr134, true),
    implode(',', $abbr134)
);

// ---------------------------------------------------------------------------
// 5. Self-filed vs selected group — main group approvers
// ---------------------------------------------------------------------------
$mainApprovers = $approverDir->resolveApprovers(14, 'MNG', 10);
$selectedApprovers = $approverDir->resolveApprovers(3, 'ANA', 10);
$mainIds = array_map(static fn(array $a): int => (int) $a['id'], $mainApprovers);
$selectedIds = array_map(static fn(array $a): int => (int) $a['id'], $selectedApprovers);
qa(
    'Self-filed approvers',
    'Main group (MNG) differs from selected OT group (ANA) for employee 10',
    $mainIds !== $selectedIds,
    'main=' . implode(',', $mainIds) . ' selected=' . implode(',', $selectedIds)
);

// ---------------------------------------------------------------------------
// 6. Approver level not always 1
// ---------------------------------------------------------------------------
$levels = array_map(
    static fn(array $a): int => (int) ($a['approval_level'] ?? $a['role'] ?? 0),
    $mainApprovers
);
qa(
    'Approver level',
    'OGA returns approval_level > 1 for MNG',
    max($levels ?: [0]) > 1,
    'levels=' . implode(',', $levels)
);

// ---------------------------------------------------------------------------
// 7. On-behalf main-group gate
// ---------------------------------------------------------------------------
$gateOk = $submitSvc->addOvertimeOnBehalf(
    ['id' => 134],
    ['employee_id' => 517, 'group' => 0, 'location' => 0, 'remarks' => 'qa', 'date' => date('Y-m-d'), 'projectsJson' => '[]']
);
qa(
    'On-behalf submit',
    'Form PIC of SYS passes main-group gate for SYS employee',
    ($gateOk['message'] ?? '') === 'Please select a group.',
    (string) ($gateOk['message'] ?? '')
);

$gateFail = $submitSvc->addOvertimeOnBehalf(
    ['id' => 545],
    ['employee_id' => 517, 'group' => 0, 'location' => 0, 'remarks' => 'qa', 'date' => date('Y-m-d'), 'projectsJson' => '[]']
);
qa(
    'On-behalf submit',
    'Non-SYS approver blocked for SYS employee',
    str_contains((string) ($gateFail['message'] ?? ''), 'main group'),
    (string) ($gateFail['message'] ?? '')
);

// ---------------------------------------------------------------------------
// 8. Status badges — split + finalized hides action
// ---------------------------------------------------------------------------
$ref = new ReflectionClass($approvalSvc);
$deriveRequest = $ref->getMethod('deriveRequestStatus');
$deriveRequest->setAccessible(true);
$deriveAction = $ref->getMethod('deriveApproverAction');
$deriveAction->setAccessible(true);

$pendingReq = ['status' => null, 'is_finalized' => false, 'my_decision' => null, 'is_on_behalf' => false];
[$sCode, $sLabel] = $deriveRequest->invoke($approvalSvc, $pendingReq, false);
[$aCode, $aLabel] = $deriveAction->invoke($approvalSvc, $pendingReq);
qa('Status UI', 'Open request: Pending + Action needed', $sCode === 'pending' && $aCode === 'action_needed', "{$sLabel} / {$aLabel}");

$pendingActed = ['status' => null, 'is_finalized' => false, 'my_decision' => 1, 'is_on_behalf' => false];
[$sCode2, $sLabel2] = $deriveRequest->invoke($approvalSvc, $pendingActed, true);
[$aCode2, $aLabel2] = $deriveAction->invoke($approvalSvc, $pendingActed);
qa('Status UI', 'Open request: Pending + You approved', $sCode2 === 'pending' && $aCode2 === 'you_approved', "{$sLabel2} / {$aLabel2}");

$finalized = ['status' => '1', 'is_finalized' => true, 'my_decision' => 1, 'is_on_behalf' => false];
[$sCode3, $sLabel3] = $deriveRequest->invoke($approvalSvc, $finalized, true);
[$aCode3] = $deriveAction->invoke($approvalSvc, $finalized);
qa('Status UI', 'Finalized: Approved only (no action badge)', $sCode3 === 'approved' && $aCode3 === null, $sLabel3);

$list510 = $approvalSvc->getOvertimeToApprove(510)['data'] ?? [];
$finalRows = array_filter($list510, static fn(array $r): bool => !empty($r['is_finalized']));
$finalWithAction = array_filter($finalRows, static fn(array $r): bool => ($r['action_code'] ?? null) !== null);
qa(
    'Status UI',
    'Live data: finalized rows have no action_code (approver 510)',
    count($finalWithAction) === 0,
    count($finalRows) . ' finalized, ' . count($finalWithAction) . ' with action badge'
);

// ---------------------------------------------------------------------------
// 9. Email templates — contrast / links
// ---------------------------------------------------------------------------
$mailRef = new ReflectionClass($mailSvc);
$buildStatus = $mailRef->getMethod('buildStatusVars');
$buildStatus->setAccessible(true);
$buildNew = $mailRef->getMethod('buildNewRequestVars');
$buildNew->setAccessible(true);

$queueRow = ['approver_name' => 'QA', 'actor_name' => 'QA', 'overtime_id' => 1];
$data = [
    'surname' => 'Test',
    'abbreviation' => 'SYS',
    'location_name' => 'Office',
    'request_date' => date('Y-m-d'),
    'duration' => 2,
    'remarks' => 'QA',
    'approver_remarks' => 'OK',
    'projects' => [['project_name' => 'WEBJMR', 'hours' => 2]],
];

foreach ([true, false] as $approved) {
    $vars = $buildStatus->invoke($mailSvc, $queueRow, $data, $approved);
    $html = $templates->render($templates->load('status_email.html'), $vars);
    $hasBgcolorHeader = (bool) preg_match('/bgcolor="\{\{header_bg_solid\}\}"/', $templates->load('status_email.html'))
        || (bool) preg_match('/bgcolor="#[0-9a-f]{6}"/i', $html);
    qa(
        'Email',
        'Status email (' . ($approved ? 'approved' : 'rejected') . ') header has bgcolor fallback',
        (bool) preg_match('/bgcolor="/i', $html),
        $approved ? 'approved' : 'rejected'
    );
    qa(
        'Email',
        'Status email no rgba() text colors',
        preg_match_all('/color:\s*rgba\(/i', $html) === 0
    );
    qa(
        'Email',
        'Status email has action_url link',
        str_contains($html, 'href="' . ($vars['{{action_url}}'] ?? '')) && str_contains($html, ($vars['{{action_label}}'] ?? ''))
    );
}

$newVars = $buildNew->invoke($mailSvc, $queueRow, $data);
$newHtml = $templates->render($templates->load('request_email.html'), $newVars);
qa('Email', 'New request email has Review link', str_contains($newHtml, '/approve/'));
qa('Email', 'New request email no #94a3b8 faint grey', !preg_match('/#94a3b8/i', $newHtml));

// ---------------------------------------------------------------------------
// 10. PHP syntax on touched files
// ---------------------------------------------------------------------------
$phpFiles = [
    'src/Service/OvertimeSubmissionService.php',
    'src/Service/OvertimeApprovalService.php',
    'src/Service/ApproverDirectoryService.php',
    'src/Service/MailService.php',
    'src/Application.php',
    'public/api.php',
];
foreach ($phpFiles as $file) {
    $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $file);
    exec('"' . PHP_BINARY . '" -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    qa('Syntax', $file, $code === 0, trim(implode(' ', $out)));
}

} catch (Throwable $e) {
    qa('Services', 'Service-level QA', false, $e->getMessage());
}

summary:
// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
$passed = count(array_filter($results, static fn(array $r): bool => $r['pass']));
$failed = count($results) - $passed;
echo PHP_EOL . "=== Summary: {$passed} passed, {$failed} failed ===" . PHP_EOL;

if ($failed > 0) {
    echo PHP_EOL . 'Failures:' . PHP_EOL;
    foreach ($results as $r) {
        if (!$r['pass']) {
            echo "  - [{$r['area']}] {$r['name']}: {$r['detail']}" . PHP_EOL;
        }
    }
    exit(1);
}

exit(0);
