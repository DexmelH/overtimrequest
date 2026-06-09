<?php
namespace App\Repository;

use PDO;

class ActivityLogRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function insert(array $entry): bool
    {
        $sql = "INSERT INTO `activity_logs`
                    (`user_id`, `user_name`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `user_agent`)
                VALUES (:userId, :userName, :action, :entityType, :entityId, :details, :ipAddress, :userAgent)";
        $stmt = $this->pdo->prepare($sql);
        return (bool) $stmt->execute([
            ':userId' => $entry['user_id'] ?? null,
            ':userName' => $entry['user_name'] ?? null,
            ':action' => $entry['action'],
            ':entityType' => $entry['entity_type'] ?? null,
            ':entityId' => $entry['entity_id'] ?? null,
            ':details' => $entry['details'] ?? null,
            ':ipAddress' => $entry['ip_address'] ?? null,
            ':userAgent' => $entry['user_agent'] ?? null,
        ]);
    }

    public function findLogs(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(200, max(1, (int) ($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'al.`action` = :action';
            $params[':action'] = $filters['action'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'al.`user_id` = :userId';
            $params[':userId'] = (int) $filters['user_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(al.`user_name` LIKE :search OR al.`action` LIKE :search OR al.`details` LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['from'])) {
            $where[] = 'al.`created_at` >= :fromDate';
            $params[':fromDate'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'al.`created_at` <= :toDate';
            $params[':toDate'] = $filters['to'] . ' 23:59:59';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM `activity_logs` al {$whereSql}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT al.`id`, al.`user_id`, al.`user_name`, al.`action`, al.`entity_type`,
                       al.`entity_id`, al.`details`, al.`ip_address`, al.`created_at`
                FROM `activity_logs` al
                {$whereSql}
                ORDER BY al.`created_at` DESC
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        foreach ($rows as &$row) {
            if (!empty($row['details'])) {
                $decoded = json_decode($row['details'], true);
                $row['details'] = is_array($decoded) ? $decoded : $row['details'];
            }
        }

        return [
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $limit > 0 ? (int) ceil($total / $limit) : 0,
            ],
        ];
    }

    public function getActionSummary(): array
    {
        $sql = "SELECT `action`, COUNT(*) AS total FROM `activity_logs` GROUP BY `action` ORDER BY total DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }
}
