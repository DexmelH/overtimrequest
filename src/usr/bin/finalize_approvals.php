<?php

/**
 * Finalize pending overtime requests after the approval cutoff.
 *
 * On Friday, also finalizes Saturday- and Sunday-dated requests for that week
 * so weekend OT is settled at Friday's cutoff instead of waiting for the weekend.
 * Other days finalize that calendar date only (Sat/Sun runs remain a catch-up).
 *
 * Usage:
 *   php src/usr/bin/finalize_approvals.php
 *   php src/usr/bin/finalize_approvals.php --force
 *
 * Schedule via Windows Task Scheduler / cron at or after APPROVAL_CUTOFF_TIME (default 15:00).
 */

use App\Service\ApprovalFinalizer;

/** @var \App\Container $container */
$container = require __DIR__ . '/../../bootstrap.php';

$force = in_array('--force', $argv ?? [], true);

$config = $container->get('config');
$finalizer = $container->get(ApprovalFinalizer::class);

$now = new DateTimeImmutable('now');
$today = $now->format('Y-m-d');
$dates = $finalizer->datesToFinalizeOn($now);

error_log(sprintf(
    'Approval finalizer started [env=%s, run_date=%s, request_dates=%s, cutoff=%s, force=%s, tz=%s]',
    $config['app']['env'] ?? 'unknown',
    $today,
    implode(',', $dates),
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

$summary = $finalizer->finalizePendingForRunDate($now);

fwrite(STDOUT, sprintf(
    "Finalized %d request(s) for request_date(s) %s (approved=%d, rejected=%d, auto_rejected=%d).\n",
    $summary['finalized'],
    implode(', ', $summary['dates']),
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
