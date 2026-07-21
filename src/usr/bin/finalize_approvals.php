<?php

/**
 * Finalize pending overtime requests for today after the approval cutoff.
 *
 * Usage:
 *   php src/usr/bin/finalize_approvals.php
 *   php src/usr/bin/finalize_approvals.php --force
 *
 * Schedule via Windows Task Scheduler / cron at or after APPROVAL_CUTOFF_TIME (default 15:00).
 */

use App\Repository\ActivityLogRepository;
use App\Repository\OvertimeRepository;
use App\Service\ActivityLogger;
use App\Service\ApprovalFinalizer;

require __DIR__ . '/../../../vendor/autoload.php';

$config = require __DIR__ . '/../../config.php';

$force = in_array('--force', $argv ?? [], true);

$dbManager = new \App\Database($config['connections'] ?? $config);
$pdo = $dbManager->getConnection('webjmr');

$overtimeRepo = new OvertimeRepository($pdo);
$logger = new ActivityLogger(new ActivityLogRepository($pdo));
$cutoffTime = (string) ($config['app']['approval_cutoff_time'] ?? '15:00');
$finalizer = new ApprovalFinalizer($overtimeRepo, $logger, $cutoffTime);

$now = new DateTimeImmutable('now');
$today = $now->format('Y-m-d');

error_log(sprintf(
    'Approval finalizer started [env=%s, date=%s, cutoff=%s, force=%s, tz=%s]',
    $config['app']['env'] ?? 'unknown',
    $today,
    $finalizer->getCutoffTime(),
    $force ? 'yes' : 'no',
    $config['app']['timezone'] ?? date_default_timezone_get()
));

if (!$force && !$finalizer->isPastCutoff($now)) {
    $message = sprintf(
        "Skipped: current time %s is before cutoff %s. Use --force to run anyway.\n",
        $now->format('H:i:s'),
        $finalizer->getCutoffTime()
    );
    fwrite(STDOUT, $message);
    exit(0);
}

$summary = $finalizer->finalizePendingForDate($today);

fwrite(STDOUT, sprintf(
    "Finalized %d request(s) for %s (approved=%d, rejected=%d, auto_rejected=%d).\n",
    $summary['finalized'],
    $today,
    $summary['approved'],
    $summary['rejected'],
    $summary['auto_rejected']
));

if ($summary['errors']) {
    foreach ($summary['errors'] as $error) {
        fwrite(STDERR, $error . "\n");
    }
    exit(1);
}

exit(0);
