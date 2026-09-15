<?php
// Quick local check for worker claim SQL compatibility.
require dirname(__DIR__) . '/vendor/autoload.php';
$config = require dirname(__DIR__) . '/src/config.php';
$db = new App\Database($config['connections'] ?? $config);
$pdo = $db->getConnection('webjmr');

echo 'version=' . $pdo->query('SELECT VERSION()')->fetchColumn() . PHP_EOL;

foreach ([
    "SELECT id FROM email_queue WHERE status='pending' ORDER BY created_at LIMIT 1 FOR UPDATE",
] as $sql) {
    try {
        $pdo->beginTransaction();
        $pdo->query($sql);
        $pdo->commit();
        echo "OK: {$sql}\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "FAIL: {$e->getMessage()}\nSQL: {$sql}\n";
    }
}

echo "lock_used=" . $pdo->query("SELECT IS_USED_LOCK('overtime_email_worker')")->fetchColumn() . PHP_EOL;
foreach ($pdo->query("SELECT status, COUNT(*) AS c, SUM(last_attempt_at IS NULL) AS null_attempts FROM email_queue GROUP BY status") as $row) {
    echo "status={$row['status']} count={$row['c']} null_attempts={$row['null_attempts']}\n";
}
