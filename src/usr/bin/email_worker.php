<?php

use App\Repository\OvertimeRepository;
use App\Service\MailService;

/** @var \App\Container $container */
$container = require __DIR__ . '/../../bootstrap.php';

$config = $container->get('config');
$cutoffTime = $container->get('config.approval_cutoff_time');
$mailRepo = $container->get('db.webjmr');
$mailService = $container->get(MailService::class);
$overtimeRepo = $container->get(OvertimeRepository::class);

error_log(sprintf(
    'Email worker started [env=%s, mail=%s, db=%s]',
    $config['app']['env'] ?? 'unknown',
    ($config['mail']['enabled'] ?? true) ? 'enabled' : 'disabled',
    $config['connections']['webjmr']['dsn'] ?? 'n/a'
));

$maxAttempts = 5;
$baseSleep = 2;
/** Seconds after which a row stuck in "sending" is treated as abandoned. */
$staleSendingSeconds = 120;

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

while (true) {
    $claimed = null;

    try {
        // Reclaim abandoned "sending" rows from a crashed / hung previous attempt.
        $mailRepo->prepare(
            "UPDATE email_queue
             SET status = 'pending',
                 last_error = COALESCE(NULLIF(last_error, ''), 'reclaimed stale sending job')
             WHERE status = 'sending'
               AND (
                    last_attempt_at IS NULL
                    OR last_attempt_at < (NOW() - INTERVAL {$staleSendingSeconds} SECOND)
               )"
        )->execute();

        $mailRepo->beginTransaction();
        $stmt = $mailRepo->query(
            "SELECT * FROM email_queue WHERE status='pending' ORDER BY created_at LIMIT 1 FOR UPDATE"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $mailRepo->commit();
            sleep(3);
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
