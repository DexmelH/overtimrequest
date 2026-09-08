<?php
namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Send an email via SMTP using PHPMailer.
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $subject
     * @param string $bodyHtml
     * @param string|null $bodyText
     * @return bool true on success, false on failure
     */
    public function send(string $toEmail, string $toName, string $subject, string $bodyHtml, ?string $bodyText = null): bool
    {
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->SMTPAuth   = false;
            $mail->SMTPSecure = false;
            // $mail->Username   = $this->config['username'];
            // $mail->Password   = $this->config['password'];
            $mail->Port       = (int) ($this->config['port'] ?? 25);
            // Without these, an unreachable SMTP host hangs forever and the
            // queue row stays stuck on status=sending with no last_error.
            $mail->Timeout    = (int) ($this->config['timeout'] ?? 20);
            $mail->SMTPKeepAlive = false;

            // From
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);

            // Recipient
            $mail->addAddress($toEmail, $toName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;
            if ($bodyText !== null) {
                $mail->AltBody = $bodyText;
            }

            // Optional: reply-to, cc, bcc
            if (!empty($this->config['reply_to'])) {
                $mail->addReplyTo($this->config['reply_to']);
            }

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            $detail = trim($mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage());
            error_log('Mailer error: ' . $detail);
            throw new \RuntimeException('SMTP send failed: ' . $detail, 0, $e);
        }
    }
}
