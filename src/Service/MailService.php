<?php
namespace App\Service;

class MailService
{
    private $mailer;
    private $htmlTemplate;

    public function __construct($mailer, string $htmlTemplate)
    {
        $this->mailer = $mailer;
        $this->htmlTemplate = $htmlTemplate;
    }

    public function sendOvertimeEmail(array $payload): bool
    {
        $recipientEmail = "hernandez-kdt@global.kawasaki.com";
        $recipientName = "Dexmel";

        if (!$recipientEmail) {
            error_log("MailService: missing recipient email for request ");
            return false;
        }

        $map = [
            "{{approver_name}}" => $payload["approver_name"]
        ];
        $html = strtr($this->htmlTemplate, $map);

        $subject = "Overtime request";
        
        try {
            if (method_exists($this->mailer, 'send')) {
                return (bool)$this->mailer->send($recipientEmail, $recipientName, $subject, $html);
            }
            error_log("MailService: mailer has no send method");
            return false;
        } catch (\Throwable $e) {
            error_log("MailService send error: " . $e->getMessage());
            return false;
        }
    }
}