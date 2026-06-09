<?php
namespace App\Service;

use App\Repository\ActivityLogRepository;

class ActivityLogger
{
    private ActivityLogRepository $repo;

    public function __construct(ActivityLogRepository $repo)
    {
        $this->repo = $repo;
    }

    public function log(
        string $action,
        ?int $userId = null,
        ?string $userName = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null
    ): void {
        try {
            $this->repo->insert([
                'user_id' => $userId,
                'user_name' => $userName,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => $this->clientIp(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT'])
                    ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 512)
                    : null,
            ]);
        } catch (\Throwable $e) {
            error_log('ActivityLogger failed: ' . $e->getMessage());
        }
    }

    private function clientIp(): ?string
    {
        $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', (string) $_SERVER[$key])[0];
                return trim($ip);
            }
        }
        return null;
    }
}
