<?php
namespace App\Service;

class MailService
{
    private $mailer;
    private EmailTemplate $templates;

    public function __construct($mailer, ?EmailTemplate $templates = null)
    {
        $this->mailer = $mailer;
        $this->templates = $templates ?? new EmailTemplate();
    }

    /**
     * Dispatch a queued email row using the correct template.
     */
    public function sendQueuedEmail(array $queueRow, array $requestData): bool
    {
        $emailType = $queueRow['email_type'] ?? 'new_request';

        if ($emailType === 'status_update') {
            return $this->sendStatusUpdateEmail($queueRow, $requestData);
        }

        return $this->sendNewRequestEmail($queueRow, $requestData);
    }

    /**
     * Notify approver of a new overtime request.
     */
    public function sendNewRequestEmail(array $queueRow, array $requestData): bool
    {
        $recipientEmail = trim((string) ($queueRow['email_to'] ?? ''));
        $recipientName = trim((string) ($queueRow['approver_name'] ?? 'Approver'));

        if ($recipientEmail === '') {
            error_log('MailService: missing approver email for overtime ' . ($queueRow['overtime_id'] ?? ''));
            return false;
        }

        $html = $this->templates->load('request_email.html');
        $map = $this->buildNewRequestVars($queueRow, $requestData);
        $body = $this->templates->render($html, $map);
        $subject = sprintf(
            'New overtime request from %s',
            $map['{{requestor_name}}'] ?? 'employee'
        );

        return $this->deliver($recipientEmail, $recipientName, $subject, $body);
    }

    /**
     * Notify requestor that their overtime was approved or rejected.
     */
    public function sendStatusUpdateEmail(array $queueRow, array $requestData): bool
    {
        $recipientEmail = trim((string) ($queueRow['email_to'] ?? ''));
        $recipientName = trim((string) ($queueRow['approver_name'] ?? 'Employee'));

        if ($recipientEmail === '') {
            error_log('MailService: missing requestor email for overtime ' . ($queueRow['overtime_id'] ?? ''));
            return false;
        }

        $decision = (int) ($queueRow['decision'] ?? $requestData['decision'] ?? -1);
        $isApproved = $decision === 1;

        $html = $this->templates->load('status_email.html');
        $map = $this->buildStatusVars($queueRow, $requestData, $isApproved);
        $body = $this->templates->render($html, $map);
        $subject = $isApproved
            ? 'Your overtime request was approved'
            : 'Your overtime request was rejected';

        return $this->deliver($recipientEmail, $recipientName, $subject, $body);
    }

    private function buildNewRequestVars(array $queueRow, array $data): array
    {
        $requestor = EmailTemplate::escape($data['surname'] ?? $data['requestor_name'] ?? '—');
        $remarks = EmailTemplate::escape($data['remarks'] ?? '—');

        return [
            '{{recipient_name}}' => EmailTemplate::escape($queueRow['approver_name'] ?? 'Approver'),
            '{{approver_name}}' => EmailTemplate::escape($queueRow['approver_name'] ?? 'Approver'),
            '{{requestor_name}}' => $requestor,
            '{{submitted_at}}' => EmailTemplate::normalizeDate($data['date_created'] ?? null),
            '{{group_name}}' => EmailTemplate::escape($data['abbreviation'] ?? $data['group_name'] ?? '—'),
            '{{project_name}}' => EmailTemplate::escape($data['fldProject'] ?? $data['project_name'] ?? '—'),
            '{{location_name}}' => EmailTemplate::escape($data['location_name'] ?? '—'),
            '{{date}}' => EmailTemplate::normalizeDate($data['request_date'] ?? null),
            '{{hours}}' => EmailTemplate::escape((string) ($data['duration'] ?? '—')),
            '{{remarks}}' => $remarks !== '' ? $remarks : '—',
            '{{request_id}}' => EmailTemplate::escape((string) ($queueRow['overtime_id'] ?? $data['id'] ?? '—')),
        ];
    }

    private function buildStatusVars(array $queueRow, array $data, bool $isApproved): array
    {
        $statusLabel = $isApproved ? 'Approved' : 'Rejected';
        $actor = EmailTemplate::escape($queueRow['actor_name'] ?? '—');
        $approverRemarks = EmailTemplate::escape($data['approver_remarks'] ?? '—');

        return [
            '{{recipient_name}}' => EmailTemplate::escape($queueRow['approver_name'] ?? 'Employee'),
            '{{requestor_name}}' => EmailTemplate::escape($queueRow['approver_name'] ?? 'Employee'),
            '{{status_label}}' => $statusLabel,
            '{{status_label_lower}}' => strtolower($statusLabel),
            '{{status_bg}}' => $isApproved ? '#16a34a' : '#dc2626',
            '{{status_soft_bg}}' => $isApproved ? '#ecfdf5' : '#fef2f2',
            '{{status_soft_border}}' => $isApproved ? '#bbf7d0' : '#fecaca',
            '{{status_text}}' => $isApproved ? '#166534' : '#991b1b',
            '{{header_bg}}' => $isApproved
                ? 'linear-gradient(135deg,#2563eb 0%,#1d4ed8 100%)'
                : 'linear-gradient(135deg,#dc2626 0%,#b91c1c 100%)',
            '{{actor_name}}' => $actor,
            '{{approver_remarks}}' => $approverRemarks !== '' ? $approverRemarks : '—',
            '{{group_name}}' => EmailTemplate::escape($data['abbreviation'] ?? $data['group_name'] ?? '—'),
            '{{project_name}}' => EmailTemplate::escape($data['fldProject'] ?? $data['project_name'] ?? '—'),
            '{{location_name}}' => EmailTemplate::escape($data['location_name'] ?? '—'),
            '{{date}}' => EmailTemplate::normalizeDate($data['request_date'] ?? null),
            '{{hours}}' => EmailTemplate::escape((string) ($data['duration'] ?? '—')),
            '{{remarks}}' => EmailTemplate::escape($data['remarks'] ?? '—'),
            '{{request_id}}' => EmailTemplate::escape((string) ($queueRow['overtime_id'] ?? $data['id'] ?? '—')),
            '{{submitted_at}}' => EmailTemplate::normalizeDate($data['date_created'] ?? null),
        ];
    }

    private function deliver(string $toEmail, string $toName, string $subject, string $html): bool
    {
        try {
            if (method_exists($this->mailer, 'send')) {
                return (bool) $this->mailer->send($toEmail, $toName, $subject, $html);
            }
            error_log('MailService: mailer has no send method');
            return false;
        } catch (\Throwable $e) {
            error_log('MailService send error: ' . $e->getMessage());
            return false;
        }
    }
}
