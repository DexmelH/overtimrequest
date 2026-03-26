<?php

use App\Service\Mailer;

require __DIR__ . '/../../../vendor/autoload.php';

$config = require __DIR__ . '/../../config.php';
$htmlPath = __DIR__  . '/../template/request_email.html';
$htmlTemplate = file_get_contents($htmlPath);

$dbManager = new \App\Database($config['connections'] ?? $config);
$mailRepo = $dbManager->getConnection("webjmr");

$mailer = new Mailer($config["mail"]);



$mailService = new \App\Service\MailService($mailer, $htmlTemplate);
$overtimeRepo = new \App\Repository\OvertimeRepository($mailRepo);

$maxAttempts = 5;
$baseSleep = 2;

while (true) {
    try {
        $mailRepo->beginTransaction();
        // select one pending job and lock it
        $stmt = $mailRepo->query("SELECT * FROM email_queue WHERE status='pending' ORDER BY created_at LIMIT 1 FOR UPDATE");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (! $row) {
            $mailRepo->commit();
            sleep(3);
            continue;
        }

        // mark sending
        $mailRepo->prepare("UPDATE email_queue SET status='sending', last_attempt_at = NOW() WHERE id = ?")->execute([$row['id']]);
        $mailRepo->commit();

        $payload = $overtimeRepo->findRequestById($row['overtime_id'] ?? null);
        $payload['email_to'] = $row['email_to'];
        $payload['approver_name'] = $row['approver_name'];
        $ok = $mailService->sendOvertimeEmail($payload);

        if ($ok) {
            $mailRepo->prepare("UPDATE email_queue SET status='sent', attempts = attempts + 1 WHERE id = ?")->execute([$row['id']]);
            error_log("Email job {$row['id']} sent successfully");
        } else {
            $attempts = $row['attempts'] + 1;
            $error = 'send failed at ' . date('c');
            if ($attempts >= $maxAttempts) {
                $mailRepo->prepare("UPDATE email_queue SET status='failed', attempts = ?, last_error = ? WHERE id = ?")
                    ->execute([$attempts, $error, $row['id']]);
                error_log("Email job {$row['id']} failed permanently after {$attempts} attempts");
            } else {
                $mailRepo->prepare("UPDATE email_queue SET status='pending', attempts = ?, last_error = ? WHERE id = ?")
                    ->execute([$attempts, $error, $row['id']]);
                $sleep = $baseSleep * $attempts;
                error_log("Email job {$row['id']} failed, will retry after {$sleep}s (attempt {$attempts})");
                sleep($sleep);
            }
        }
    } catch (\Throwable $e) {
        // log and sleep to avoid tight loop on fatal errors
        error_log('Worker exception: ' . $e->getMessage());
        if ($mailRepo->inTransaction()) { $mailRepo->rollBack(); }
        sleep(5);
    }
}