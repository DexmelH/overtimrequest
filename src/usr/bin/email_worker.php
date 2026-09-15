<?php

/**
 * Email queue batch drain (not a daemon).
 *
 * Default: reclaim stale "sending" once, process up to 10 pending jobs, exit.
 *
 * Usage:
 *   php src/usr/bin/email_worker.php
 *   php src/usr/bin/email_worker.php --limit=20
 *
 * Production (Windows Task Scheduler):
 *   - Trigger: every 1 minute
 *   - Action: scripts\start_email_worker.bat
 *   - Settings: "Do not start a new instance if the previous is still running"
 *   - Stop any old long-running email_worker php.exe processes before switching
 *
 * Server DB (once): apply databases/migrations/013_email_queue_worker_indexes.sql
 */

use App\Repository\OvertimeRepository;
use App\Service\MailService;

/** @var \App\Container $container */
$container = require __DIR__ . '/../../bootstrap.php';

$config = $container->get('config');
$cutoffTime = $container->get('config.approval_cutoff_time');
$mailRepo = $container->get('db.webjmr');
$mailService = $container->get(MailService::class);
$overtimeRepo = $container->get(OvertimeRepository::class);

$maxAttempts = 5;
$staleSendingSeconds = 120;
$defaultLimit = 10;

$limit = $defaultLimit;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--limit=(\d+)$/', (string) $arg, $m)) {
        $limit = max(1, min(100, (int) $m[1]));
    }
}

$projectRoot = dirname(__DIR__, 2);
$lockDir = $projectRoot . DIRECTORY_SEPARATOR . 'storage';
if (!is_dir($lockDir) && !mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
    fwrite(STDERR, "Cannot create storage directory for email worker lock.\n");
    exit(1);
}

$lockPath = $lockDir . DIRECTORY_SEPARATOR . 'email_worker.lock';
$lockFh = fopen($lockPath, 'c+');
if ($lockFh === false) {
    fwrite(STDERR, "Cannot open email worker lock file.\n");
    exit(1);
}

if (!flock($lockFh, LOCK_EX | LOCK_NB)) {
    $msg = 'Email worker exiting: another batch is already running (file lock).';
    error_log($msg);
    fwrite(STDOUT, $msg . "\n");
    fclose($lockFh);
    exit(0);
}

fwrite($lockFh, (string) (getmypid() ?: 0) . ' ' . date('c') . "\n");
fflush($lockFh);

error_log(sprintf(
    'Email batch started [env=%s, mail=%s, db=%s, pid=%d, limit=%d]',
    $config['app']['env'] ?? 'unknown',
    ($config['mail']['enabled'] ?? true) ? 'enabled' : 'disabled',
    $config['connections']['webjmr']['dsn'] ?? 'n/a',
    getmypid() ?: 0,
    $limit
));

/**
 * Detect FOR UPDATE SKIP LOCKED (MySQL 8+ / MariaDB 10.6+).
 */
function supportsSkipLocked(PDO $pdo): bool
{
    try {
        $pdo->beginTransaction();
        $pdo->query(
            "SELECT id FROM email_queue WHERE status = 'pending' ORDER BY created_at LIMIT 1 FOR UPDATE SKIP LOCKED"
        );
        $pdo->commit();
        return true;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('FOR UPDATE SKIP LOCKED not available, falling back: ' . $e->getMessage());
        return false;
    }
}

function finalizeFailedJob(PDO $mailRepo, array $row, string $error, int $maxAttempts): string
{
    $attempts = ((int) ($row['attempts'] ?? 0)) + 1;
    $error = mb_substr($error, 0, 500);

    if ($attempts >= $maxAttempts) {
        $mailRepo->prepare(
            "UPDATE email_queue SET status='failed', attempts = ?, last_error = ? WHERE id = ?"
        )->execute([$attempts, $error, $row['id']]);
        error_log("Email job {$row['id']} failed permanently after {$attempts} attempts: {$error}");
        return 'failed';
    }

    $mailRepo->prepare(
        "UPDATE email_queue SET status='pending', attempts = ?, last_error = ? WHERE id = ?"
    )->execute([$attempts, $error, $row['id']]);
    error_log("Email job {$row['id']} failed, requeued for next batch (attempt {$attempts}): {$error}");
    return 'requeued';
}

function reclaimStaleSending(PDO $mailRepo, int $staleSendingSeconds): int
{
    $seconds = max(1, $staleSendingSeconds);
    $stmt = $mailRepo->prepare(
        "UPDATE email_queue
         SET status = 'pending',
             last_error = COALESCE(NULLIF(last_error, ''), 'reclaimed stale sending job')
         WHERE status = 'sending'
           AND (
                last_attempt_at IS NULL
                OR last_attempt_at < (NOW() - INTERVAL {$seconds} SECOND)
           )"
    );
    $stmt->execute();
    return $stmt->rowCount();
}

/**
 * @return array{id: mixed}|null
 */
function claimNextPending(PDO $mailRepo, string $claimSql): ?array
{
    $mailRepo->beginTransaction();
    try {
        $stmt = $mailRepo->query($claimSql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $mailRepo->commit();
            return null;
        }

        $mailRepo->prepare(
            "UPDATE email_queue SET status='sending', last_attempt_at = NOW() WHERE id = ?"
        )->execute([$row['id']]);
        $mailRepo->commit();
        return $row;
    } catch (\Throwable $e) {
        if ($mailRepo->inTransaction()) {
            $mailRepo->rollBack();
        }
        throw $e;
    }
}

$useSkipLocked = supportsSkipLocked($mailRepo);
$claimSql = $useSkipLocked
    ? "SELECT * FROM email_queue WHERE status = 'pending' ORDER BY created_at LIMIT 1 FOR UPDATE SKIP LOCKED"
    : "SELECT * FROM email_queue WHERE status = 'pending' ORDER BY created_at LIMIT 1 FOR UPDATE";

error_log('Email batch claim mode: ' . ($useSkipLocked ? 'SKIP LOCKED' : 'FOR UPDATE'));

$stats = [
    'processed' => 0,
    'sent' => 0,
    'failed' => 0,
    'requeued' => 0,
    'reclaimed' => 0,
];

try {
    $stats['reclaimed'] = reclaimStaleSending($mailRepo, $staleSendingSeconds);
    if ($stats['reclaimed'] > 0) {
        error_log("Reclaimed {$stats['reclaimed']} stale sending email job(s)");
    }

    for ($i = 0; $i < $limit; $i++) {
        $claimed = null;
        try {
            $row = claimNextPending($mailRepo, $claimSql);
            if ($row === null) {
                break;
            }
            $claimed = $row;
            $stats['processed']++;

            $requestId = (int) ($row['overtime_id'] ?? 0);
            $payload = $overtimeRepo->findRequestEmailDetails($requestId);

            if (($row['email_type'] ?? 'new_request') === 'status_update') {
                $decision = (int) ($row['decision'] ?? 0);
                $payload['decision'] = $row['decision'] ?? null;
                $payload['approver_remarks'] = $overtimeRepo->findStatusNotificationRemarks(
                    $requestId,
                    $decision,
                    $cutoffTime
                );
            }

            $ok = $mailService->sendQueuedEmail($row, $payload);

            if ($ok) {
                $mailRepo->prepare(
                    "UPDATE email_queue SET status='sent', attempts = attempts + 1, last_error = NULL WHERE id = ?"
                )->execute([$row['id']]);
                error_log("Email job {$row['id']} sent successfully");
                $stats['sent']++;
                $claimed = null;
            } else {
                $outcome = finalizeFailedJob($mailRepo, $row, 'send returned false', $maxAttempts);
                $stats[$outcome === 'failed' ? 'failed' : 'requeued']++;
                $claimed = null;
            }
        } catch (\Throwable $e) {
            error_log('Batch worker exception: ' . $e->getMessage());
            if ($mailRepo->inTransaction()) {
                $mailRepo->rollBack();
            }
            if ($claimed !== null) {
                try {
                    $outcome = finalizeFailedJob(
                        $mailRepo,
                        $claimed,
                        'worker exception: ' . $e->getMessage(),
                        $maxAttempts
                    );
                    $stats[$outcome === 'failed' ? 'failed' : 'requeued']++;
                } catch (\Throwable $inner) {
                    error_log('Failed to finalize email job after exception: ' . $inner->getMessage());
                    $stats['failed']++;
                }
            }
        }
    }
} finally {
    flock($lockFh, LOCK_UN);
    fclose($lockFh);
}

$summary = sprintf(
    'Email batch finished processed=%d sent=%d failed=%d requeued=%d reclaimed=%d',
    $stats['processed'],
    $stats['sent'],
    $stats['failed'],
    $stats['requeued'],
    $stats['reclaimed']
);
error_log($summary);
fwrite(STDOUT, $summary . "\n");
exit(0);
