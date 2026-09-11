<?php

/**
 * Long-running email queue worker.
 *
 * IMPORTANT (production):
 * - Run exactly ONE instance (Task Scheduler: "Do not start a new instance").
 * - This process holds MySQL GET_LOCK('overtime_email_worker') so duplicates exit.
 * - Stuck status=sending usually means SMTP hung after the row was claimed;
 *   that is a mail/network problem, but a thrashing worker can also keep mysqld busy.
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
$baseSleep = 2;
/** Idle poll interval when the queue is empty. */
$idleSleep = 5;
/** Pause after each processed job so mysqld is not hammered in a tight loop. */
$jobPause = 1;
/** How often to reclaim abandoned "sending" rows (not every loop). */
$reclaimEverySeconds = 60;
/** Seconds after which a row stuck in "sending" is treated as abandoned. */
$staleSendingSeconds = 120;

error_log(sprintf(
    'Email worker started [env=%s, mail=%s, db=%s, pid=%d]',
    $config['app']['env'] ?? 'unknown',
    ($config['mail']['enabled'] ?? true) ? 'enabled' : 'disabled',
    $config['connections']['webjmr']['dsn'] ?? 'n/a',
    getmypid() ?: 0
));

// Prevent multiple workers from polling the same queue (common Task Scheduler mistake).
$lockStmt = $mailRepo->query("SELECT GET_LOCK('overtime_email_worker', 0)");
$gotLock = $lockStmt && (int) $lockStmt->fetchColumn() === 1;
if (!$gotLock) {
    error_log('Email worker exiting: another instance already holds overtime_email_worker lock');
    exit(0);
}

register_shutdown_function(static function () use ($mailRepo): void {
    try {
        $mailRepo->query("SELECT RELEASE_LOCK('overtime_email_worker')");
    } catch (\Throwable $e) {
        // ignore
    }
});

/**
 * Mark a claimed job as retryable or permanently failed, and always store last_error.
 */
function finalizeFailedJob(
    PDO $mailRepo,
    array $row,
    string $error,
    int $maxAttempts,
    int $baseSleep
): void {
    $attempts = ((int) ($row['attempts'] ?? 0)) + 1;
    $error = mb_substr($error, 0, 500);

    if ($attempts >= $maxAttempts) {
        $mailRepo->prepare(
            "UPDATE email_queue SET status='failed', attempts = ?, last_error = ? WHERE id = ?"
        )->execute([$attempts, $error, $row['id']]);
        error_log("Email job {$row['id']} failed permanently after {$attempts} attempts: {$error}");
        return;
    }

    $mailRepo->prepare(
        "UPDATE email_queue SET status='pending', attempts = ?, last_error = ? WHERE id = ?"
    )->execute([$attempts, $error, $row['id']]);
    $sleep = $baseSleep * $attempts;
    error_log("Email job {$row['id']} failed, will retry after {$sleep}s (attempt {$attempts}): {$error}");
    sleep($sleep);
}

function reclaimStaleSending(PDO $mailRepo, int $staleSendingSeconds): int
{
    $stmt = $mailRepo->prepare(
        "UPDATE email_queue
         SET status = 'pending',
             last_error = COALESCE(NULLIF(last_error, ''), 'reclaimed stale sending job')
         WHERE status = 'sending'
           AND (
                last_attempt_at IS NULL
                OR last_attempt_at < (NOW() - INTERVAL :stale SECOND)
           )"
    );
    $stmt->bindValue(':stale', $staleSendingSeconds, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount();
}

$lastReclaimAt = 0;

while (true) {
    $claimed = null;

    try {
        $now = time();
        if ($now - $lastReclaimAt >= $reclaimEverySeconds) {
            $reclaimed = reclaimStaleSending($mailRepo, $staleSendingSeconds);
            $lastReclaimAt = $now;
            if ($reclaimed > 0) {
                error_log("Reclaimed {$reclaimed} stale sending email job(s)");
            }
        }

        $mailRepo->beginTransaction();
        // SKIP LOCKED avoids pile-ups if a second worker somehow starts.
        $stmt = $mailRepo->query(
            "SELECT * FROM email_queue
             WHERE status = 'pending'
             ORDER BY created_at
             LIMIT 1
             FOR UPDATE SKIP LOCKED"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $mailRepo->commit();
            sleep($idleSleep);
            continue;
        }

        $mailRepo->prepare(
            "UPDATE email_queue SET status='sending', last_attempt_at = NOW() WHERE id = ?"
        )->execute([$row['id']]);
        $mailRepo->commit();
        $claimed = $row;

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
            $claimed = null;
            sleep($jobPause);
        } else {
            finalizeFailedJob($mailRepo, $row, 'send returned false', $maxAttempts, $baseSleep);
            $claimed = null;
        }
    } catch (\Throwable $e) {
        error_log('Worker exception: ' . $e->getMessage());
        if ($mailRepo->inTransaction()) {
            $mailRepo->rollBack();
        }
        // Status was already committed as "sending" — recover so the job is not stuck forever.
        if ($claimed !== null) {
            try {
                finalizeFailedJob(
                    $mailRepo,
                    $claimed,
                    'worker exception: ' . $e->getMessage(),
                    $maxAttempts,
                    $baseSleep
                );
            } catch (\Throwable $inner) {
                error_log('Failed to finalize email job after exception: ' . $inner->getMessage());
                sleep(5);
            }
            $claimed = null;
        } else {
            sleep(5);
        }
    }
}
