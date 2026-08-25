<?php
declare(strict_types=1);

/**
 * Reset overtime transactional data (local / testing).
 *
 * Usage:
 *   php scripts/reset_overtime_data.php --confirm
 *   php scripts/reset_overtime_data.php --confirm --with-logs
 *   php scripts/reset_overtime_data.php --confirm --with-dailyreport
 *   php scripts/reset_overtime_data.php --confirm --with-approvers --with-admins
 *   php scripts/reset_overtime_data.php --dry-run
 *
 * Default clears:
 *   - overtime_request_projects
 *   - overtime_accept
 *   - overtime_request
 *   - email_queue rows tied to overtime_id
 *
 * Does NOT clear by default:
 *   - overtime_group_approvers
 *   - overtime_app_admins
 *   - activity_logs
 *   - dailyreport (use --with-dailyreport; best-effort match for OT-inserted rows)
 */

require dirname(__DIR__) . '/vendor/autoload.php';
$config = require dirname(__DIR__) . '/src/config.php';

$args = array_slice($argv, 1);
$flags = [
    'confirm' => in_array('--confirm', $args, true),
    'dry_run' => in_array('--dry-run', $args, true),
    'force' => in_array('--force', $args, true),
    'with_logs' => in_array('--with-logs', $args, true),
    'with_dailyreport' => in_array('--with-dailyreport', $args, true),
    'with_approvers' => in_array('--with-approvers', $args, true),
    'with_admins' => in_array('--with-admins', $args, true),
];

if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    echo <<<HELP
Reset overtime data (webjmrdb).

  php scripts/reset_overtime_data.php --confirm
  php scripts/reset_overtime_data.php --dry-run
  php scripts/reset_overtime_data.php --confirm --with-logs
  php scripts/reset_overtime_data.php --confirm --with-dailyreport
  php scripts/reset_overtime_data.php --confirm --with-approvers --with-admins

Flags:
  --confirm           Required to execute (unless --dry-run)
  --dry-run           Show counts only; do not delete
  --force             Allow run when APP_ENV is production
  --with-logs         Also truncate activity_logs
  --with-dailyreport  Also delete OT-like dailyreport rows (fldMHType=1, fldItem=0, fldRevision=0)
  --with-approvers    Also truncate overtime_group_approvers
  --with-admins       Also truncate overtime_app_admins

HELP;
    exit(0);
}

$env = (string) ($config['app']['env'] ?? 'local');
$isSafeEnv = !empty($config['app']['is_local']) || !empty($config['app']['is_testing']);
if (!$isSafeEnv && !$flags['force']) {
    fwrite(STDERR, "Refusing to run in APP_ENV={$env}. Use --force if you really mean it.\n");
    exit(1);
}

if (!$flags['confirm'] && !$flags['dry_run']) {
    fwrite(STDERR, "Add --confirm to execute, or --dry-run to preview. Use --help for options.\n");
    exit(1);
}

$db = new App\Database($config['connections'] ?? $config);
$pdo = $db->getConnection('webjmr');

$count = static function (PDO $pdo, string $sql, array $params = []): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
};

$plan = [
    [
        'label' => 'overtime_request_projects',
        'count_sql' => 'SELECT COUNT(*) FROM `overtime_request_projects`',
        'delete_sql' => 'DELETE FROM `overtime_request_projects`',
        'enabled' => true,
    ],
    [
        'label' => 'overtime_accept',
        'count_sql' => 'SELECT COUNT(*) FROM `overtime_accept`',
        'delete_sql' => 'DELETE FROM `overtime_accept`',
        'enabled' => true,
    ],
    [
        'label' => 'overtime_request',
        'count_sql' => 'SELECT COUNT(*) FROM `overtime_request`',
        'delete_sql' => 'DELETE FROM `overtime_request`',
        'enabled' => true,
    ],
    [
        'label' => 'email_queue (overtime-linked)',
        'count_sql' => 'SELECT COUNT(*) FROM `email_queue` WHERE `overtime_id` IS NOT NULL AND `overtime_id` > 0',
        'delete_sql' => 'DELETE FROM `email_queue` WHERE `overtime_id` IS NOT NULL AND `overtime_id` > 0',
        'enabled' => true,
    ],
    [
        'label' => 'dailyreport (OT-like rows)',
        'count_sql' => 'SELECT COUNT(*) FROM `dailyreport` WHERE `fldMHType` = 1 AND `fldItem` = 0 AND `fldRevision` = 0',
        'delete_sql' => 'DELETE FROM `dailyreport` WHERE `fldMHType` = 1 AND `fldItem` = 0 AND `fldRevision` = 0',
        'enabled' => $flags['with_dailyreport'],
    ],
    [
        'label' => 'activity_logs',
        'count_sql' => 'SELECT COUNT(*) FROM `activity_logs`',
        'delete_sql' => 'TRUNCATE TABLE `activity_logs`',
        'enabled' => $flags['with_logs'],
    ],
    [
        'label' => 'overtime_group_approvers',
        'count_sql' => 'SELECT COUNT(*) FROM `overtime_group_approvers`',
        'delete_sql' => 'DELETE FROM `overtime_group_approvers`',
        'enabled' => $flags['with_approvers'],
    ],
    [
        'label' => 'overtime_app_admins',
        'count_sql' => 'SELECT COUNT(*) FROM `overtime_app_admins`',
        'delete_sql' => 'DELETE FROM `overtime_app_admins`',
        'enabled' => $flags['with_admins'],
    ],
];

echo "APP_ENV={$env}\n";
echo ($flags['dry_run'] ? "Mode: DRY RUN\n" : "Mode: EXECUTE\n");
echo str_repeat('-', 48) . "\n";

$steps = array_values(array_filter($plan, static fn(array $step): bool => $step['enabled']));
if (!$steps) {
    echo "Nothing selected to reset.\n";
    exit(0);
}

foreach ($steps as $step) {
    $n = $count($pdo, $step['count_sql']);
    echo sprintf("%-36s %d row(s)\n", $step['label'] . ':', $n);
}

if ($flags['dry_run']) {
    echo str_repeat('-', 48) . "\nDry run complete. Re-run with --confirm to delete.\n";
    exit(0);
}

echo str_repeat('-', 48) . "\nDeleting...\n";

try {
    $pdo->beginTransaction();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    foreach ($steps as $step) {
        if (stripos($step['delete_sql'], 'TRUNCATE') === 0) {
            $pdo->exec($step['delete_sql']);
            echo "Cleared {$step['label']}\n";
            continue;
        }
        $deleted = $pdo->exec($step['delete_sql']);
        echo "Deleted {$deleted} from {$step['label']}\n";
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->commit();
    echo "Done.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $ignored) {
    }
    fwrite(STDERR, 'Reset failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
