<?php
namespace App\Repository;

use PDO;

class OvertimeRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function findRequestEmailDetails(int $requestID): array
    {
        $sql = "SELECT orq.`id`, orq.`remarks`, orq.`duration`, orq.`request_date`, orq.`date_created`, orq.`status`,
                    el.`surname`, el.`surname` AS requestor_name, el.`email` AS requestor_email,
                    gl.`abbreviation`, gl.`abbreviation` AS group_name,
                    l.`fldLocation` AS location_name
                FROM `overtime_request` orq
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = orq.`user_id`
                LEFT JOIN kdtphdb_new.`group_list` gl ON gl.`id` = orq.`group_id`
                LEFT JOIN `dispatch_locations` l ON l.`fldID` = orq.`location_id`
                WHERE orq.`id` = :requestID";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":requestID" => $requestID]);
        $data = $stmt->fetch();

        if (!$data) {
            return [];
        }

        $data['projects'] = $this->findProjectsByRequestIds([$requestID])[$requestID] ?? [];
        $data['project_name'] = $this->formatProjectSummary($data['projects']);

        return $data;
    }

    public function findRequestorByOvertimeId(int $overtimeID): array
    {
        $sql = "SELECT el.`id`, el.`surname`, el.`email`
                FROM `overtime_request` orq
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = orq.`user_id`
                WHERE orq.`id` = :overtimeID";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":overtimeID" => $overtimeID]);
        $data = $stmt->fetch();

        return $data ? $data : [];
    }

    public function findLatestDecisionRemarks(int $overtimeID): string
    {
        $sql = "SELECT `remarks` FROM `overtime_accept`
                WHERE `overtime_id` = :overtimeID AND `status` IS NOT NULL
                ORDER BY COALESCE(`approval_level`, 0) DESC, `date_accepted` DESC
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":overtimeID" => $overtimeID]);
        $remarks = $stmt->fetchColumn();

        return $remarks ? (string) $remarks : '';
    }

    /**
     * Remarks for the requestor's status email. An auto-rejected request has no
     * approver decision to quote, so the system reason is used instead.
     */
    public function findStatusNotificationRemarks(int $overtimeID, int $decision, string $cutoffTime): string
    {
        $remarks = $this->findLatestDecisionRemarks($overtimeID);
        if ($remarks !== '' || $decision === 1) {
            return $remarks;
        }

        return sprintf(
            'No approver action was recorded before the %s cutoff, so this request was automatically rejected.',
            $cutoffTime
        );
    }

    /**
     * Employee request history, bounded by request_date and paginated.
     *
     * @param array{from: string, to: string, page: int, limit: int, offset: int, status?: string, q?: string} $filters
     * @return array{data: array<int, array<string, mixed>>, pagination: array{page: int, limit: int, total: int, pages: int}}
     */
    public function findHistoryByUserId(string $userID, array $filters = []): array
    {
        $from = (string) ($filters['from'] ?? date('Y-m-d', strtotime('-7 days')));
        $to = (string) ($filters['to'] ?? date('Y-m-d'));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, (int) ($filters['limit'] ?? 10));
        $offset = max(0, (int) ($filters['offset'] ?? (($page - 1) * $limit)));
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $q = trim((string) ($filters['q'] ?? ''));

        $where = [
            'orq.`user_id` = :userID',
            'orq.`request_date` >= :fromDate',
            'orq.`request_date` <= :toDate',
        ];
        $params = [
            ':userID' => $userID,
            ':fromDate' => $from,
            ':toDate' => $to,
        ];

        if ($status === 'pending') {
            $where[] = '(orq.`status` IS NULL OR orq.`status` = \'\')';
        } elseif ($status === 'approved') {
            $where[] = 'orq.`status` = 1';
        } elseif ($status === 'denied') {
            $where[] = 'orq.`status` = 0';
        } elseif ($status === 'cancelled') {
            $where[] = 'orq.`status` = 2';
        }

        if ($q !== '') {
            $where[] = '(gl.`abbreviation` LIKE :q
                        OR l.`fldLocation` LIKE :q
                        OR orq.`remarks` LIKE :q
                        OR orq.`request_date` LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
                     FROM `overtime_request` orq
                     LEFT JOIN kdtphdb_new.`group_list` gl ON orq.`group_id` = gl.`id`
                     LEFT JOIN `dispatch_locations` l ON orq.`location_id` = l.`fldID`
                     WHERE {$whereSql}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT orq.`id`, orq.`duration`, orq.`remarks`, orq.`request_date`, orq.`status`,
                       orq.`date_created`, orq.`submitted_by`, orq.`origin_request_id`,
                       gl.`abbreviation` AS `group_name`,
                       l.`fldLocation` AS `location_name`,
                       TRIM(CONCAT(COALESCE(sub.`surname`, ''), ' ', COALESCE(sub.`firstname`, ''))) AS `submitted_by_name`,
                       origin.`request_date` AS `origin_request_date`,
                       origin.`status` AS `origin_request_status`
                FROM `overtime_request` orq
                LEFT JOIN kdtphdb_new.`group_list` gl ON orq.`group_id` = gl.`id`
                LEFT JOIN `dispatch_locations` l ON orq.`location_id` = l.`fldID`
                LEFT JOIN kdtphdb_new.`employee_list` sub ON sub.`id` = orq.`submitted_by`
                LEFT JOIN `overtime_request` origin ON origin.`id` = orq.`origin_request_id`
                WHERE {$whereSql}
                ORDER BY orq.`request_date` DESC, orq.`id` DESC
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll() ?: [];

        $enriched = $data ? $this->attachRequestDetails($data) : [];
        $enriched = $this->attachHistoryMeta($enriched);

        return [
            'data' => $enriched,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
            ],
        ];
    }

    /**
     * Single history row for the owner (ignores date window). Used when opening
     * an origin/follow-up link that may sit outside the current list page.
     *
     * @return array<string, mixed>|null
     */
    public function findOwnedHistoryRequest(string $userID, int $requestId): ?array
    {
        if ($requestId <= 0) {
            return null;
        }

        $sql = "SELECT orq.`id`, orq.`duration`, orq.`remarks`, orq.`request_date`, orq.`status`,
                       orq.`date_created`, orq.`submitted_by`, orq.`origin_request_id`,
                       gl.`abbreviation` AS `group_name`,
                       l.`fldLocation` AS `location_name`,
                       TRIM(CONCAT(COALESCE(sub.`surname`, ''), ' ', COALESCE(sub.`firstname`, ''))) AS `submitted_by_name`,
                       origin.`request_date` AS `origin_request_date`,
                       origin.`status` AS `origin_request_status`
                FROM `overtime_request` orq
                LEFT JOIN kdtphdb_new.`group_list` gl ON orq.`group_id` = gl.`id`
                LEFT JOIN `dispatch_locations` l ON orq.`location_id` = l.`fldID`
                LEFT JOIN kdtphdb_new.`employee_list` sub ON sub.`id` = orq.`submitted_by`
                LEFT JOIN `overtime_request` origin ON origin.`id` = orq.`origin_request_id`
                WHERE orq.`user_id` = :userID
                  AND orq.`id` = :requestId
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':userID' => $userID,
            ':requestId' => $requestId,
        ]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $enriched = $this->attachRequestDetails([$row]);
        $enriched = $this->attachHistoryMeta($enriched);

        return $enriched[0] ?? null;
    }

    /**
     * Flags + follow-up child link for employee-facing history rows.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function attachHistoryMeta(array $rows): array
    {
        if (!$rows) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));

        $followUps = [];
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT `id`, `origin_request_id`, `request_date`, `status`
                    FROM `overtime_request`
                    WHERE `origin_request_id` IN ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() ?: [] as $child) {
                $originId = (int) ($child['origin_request_id'] ?? 0);
                if ($originId > 0 && !isset($followUps[$originId])) {
                    $followUps[$originId] = $child;
                }
            }
        }

        foreach ($rows as &$row) {
            $requestId = (int) ($row['id'] ?? 0);
            $submittedBy = $row['submitted_by'] ?? null;
            $originId = $row['origin_request_id'] ?? null;
            $submitterName = trim((string) ($row['submitted_by_name'] ?? ''));

            $row['submitted_by'] = $submittedBy !== null && $submittedBy !== ''
                ? (int) $submittedBy
                : null;
            $row['origin_request_id'] = $originId !== null && $originId !== ''
                ? (int) $originId
                : null;
            $row['submitted_by_name'] = $submitterName !== '' ? $submitterName : null;
            $row['is_on_behalf'] = $row['submitted_by'] !== null;
            $row['is_follow_up'] = $row['origin_request_id'] !== null;

            $child = $followUps[$requestId] ?? null;
            if ($child) {
                $row['follow_up_id'] = (int) $child['id'];
                $row['follow_up_request_date'] = $child['request_date'] ?? null;
                $row['follow_up_status'] = $child['status'] ?? null;
                $row['has_follow_up'] = true;
            } else {
                $row['follow_up_id'] = null;
                $row['follow_up_request_date'] = null;
                $row['follow_up_status'] = null;
                $row['has_follow_up'] = false;
            }
        }
        unset($row);

        return $rows;
    }

    public function addOvertime(array $payload): int
    {
        $sql = "INSERT INTO `overtime_request`
                    (`user_id`, `submitted_by`, `origin_request_id`, `location_id`, `group_id`,
                     `duration`, `remarks`, `request_date`)
                VALUES
                    (:userID, :submittedBy, :originRequestID, :locationID, :groupID,
                     :duration, :remarks, :requestDate)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ":userID" => $payload["user_id"],
            ":submittedBy" => $payload["submitted_by"] ?? null,
            ":originRequestID" => $payload["origin_request_id"] ?? null,
            ":locationID" => $payload["location_id"],
            ":groupID" => $payload["group_id"],
            ":duration" => $payload["duration"],
            ":remarks" => $payload["remarks"],
            ":requestDate" => $payload["request_date"]
        ]);
        $lastId = $this->pdo->lastInsertId();

        return (int) $lastId;
    }

    /** @param array<int, array{project_id: int, hours: int}> $projects */
    public function addProjectAllocations(int $requestId, array $projects): void
    {
        $sql = "INSERT INTO `overtime_request_projects`
                    (`overtime_request_id`, `project_id`, `hours`, `sort_order`)
                VALUES (:requestId, :projectId, :hours, :sortOrder)";
        $stmt = $this->pdo->prepare($sql);

        foreach ($projects as $index => $project) {
            $stmt->execute([
                ':requestId' => $requestId,
                ':projectId' => $project['project_id'],
                ':hours' => $project['hours'],
                ':sortOrder' => $index,
            ]);
        }
    }

    /**
     * Validate that projects are either:
     * - owned by selected group (active + not deleted), or
     * - explicitly shared to the requesting user via project_share.
     *
     * @param int[] $projectIds
     */
    public function projectsBelongToGroup(array $projectIds, string $groupAbbreviation, int $userId = 0): bool
    {
        $projectIds = array_values(array_unique(array_filter(array_map('intval', $projectIds))));
        if (!$projectIds || trim($groupAbbreviation) === '') {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $sql = "SELECT COUNT(DISTINCT pt.`fldID`)
                FROM `projectstable` pt
                LEFT JOIN `project_share` ps
                  ON ps.`fldProject` = pt.`fldID` AND ps.`fldEmployeeNum` = ?
                WHERE pt.`fldID` IN ({$placeholders})
                  AND pt.`fldActive` = 1
                  AND pt.`fldDelete` = 0
                  AND (
                      pt.`fldGroup` = ?
                      OR pt.`fldGroup` IS NULL
                      OR ps.`fldEmployeeNum` IS NOT NULL
                  )";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, ...$projectIds, $groupAbbreviation]);

        return (int) $stmt->fetchColumn() === count($projectIds);
    }

    /** @return array<int, array<int, array{project_id: int, project_name: string, hours: int}>> */
    public function findProjectsByRequestIds(array $requestIds): array
    {
        $requestIds = $this->normalizeRequestIds($requestIds);
        if (!$requestIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
        $sql = "SELECT orp.`overtime_request_id`, orp.`project_id`,
                       COALESCE(pt.`fldProject`, CONCAT('Project #', orp.`project_id`)) AS `project_name`,
                       orp.`hours`
                FROM `overtime_request_projects` orp
                LEFT JOIN `projectstable` pt ON pt.`fldID` = orp.`project_id`
                WHERE orp.`overtime_request_id` IN ({$placeholders})
                ORDER BY orp.`overtime_request_id`, orp.`sort_order`, orp.`id`";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($requestIds);

        $projectsByRequest = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $requestId = (int) $row['overtime_request_id'];
            $projectsByRequest[$requestId][] = [
                'project_id' => (int) $row['project_id'],
                'project_name' => (string) $row['project_name'],
                'hours' => (int) $row['hours'],
            ];
        }

        return $projectsByRequest;
    }

    /**
     * Batch-load projects and approver details for list rows (fixed 2 queries, not N+1).
     *
     * @param int[]|string[] $requestIds
     * @return array{projects: array<int, array<int, array{project_id: int, project_name: string, hours: int}>>, approvers: array<int, array<int, array<string, mixed>>>}
     */
    private function fetchRelatedByRequestIds(array $requestIds): array
    {
        $requestIds = $this->normalizeRequestIds($requestIds);
        if (!$requestIds) {
            return ['projects' => [], 'approvers' => []];
        }

        return [
            'projects' => $this->findProjectsByRequestIds($requestIds),
            'approvers' => $this->findApproverDetailsByRequestIds($requestIds),
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function attachRequestDetails(array $rows): array
    {
        if (!$rows) {
            return [];
        }

        $related = $this->fetchRelatedByRequestIds(array_column($rows, 'id'));

        foreach ($rows as &$row) {
            $requestId = (int) $row['id'];
            $projects = $related['projects'][$requestId] ?? [];
            $row['projects'] = $projects;
            $row['project_name'] = $this->formatProjectSummary($projects);
            $row['approver_details'] = $related['approvers'][$requestId] ?? [];
        }
        unset($row);

        return $rows;
    }

    /** @param int[]|string[] $ids */
    private function normalizeRequestIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));
    }

    /** @param array<int, array{project_name: string, hours: int}> $projects */
    private function formatProjectSummary(array $projects): string
    {
        if (!$projects) {
            return '';
        }

        return implode(', ', array_map(
            static fn (array $project): string => $project['project_name'] . ' (' . $project['hours'] . ' hrs)',
            $projects
        ));
    }

    public function insertEmailQueue(array $payload): bool
    {
        $sql = "INSERT INTO `email_queue`
                    (`email_to`, `approver_name`, `overtime_id`, `email_type`, `decision`, `actor_name`, `attempts`)
                VALUES (:emailTo, :approverName, :overtimeID, :emailType, :decision, :actorName, 0)";
        $stmt = $this->pdo->prepare($sql);
        return (bool) $stmt->execute([
            ":emailTo" => $payload["email_to"],
            ":approverName" => $payload["approver_name"],
            ":overtimeID" => $payload["overtime_id"],
            ":emailType" => $payload["email_type"] ?? "new_request",
            ":decision" => $payload["decision"] ?? null,
            ":actorName" => $payload["actor_name"] ?? null,
        ]);
    }

    public function queueRequestorStatusEmail(int $overtimeID, int $decision, string $actorName): void
    {
        $requestor = $this->findRequestorByOvertimeId($overtimeID);
        $email = trim((string) ($requestor['email'] ?? ''));

        if ($email === '') {
            error_log("Overtime {$overtimeID}: no requestor email; status notification skipped.");
            return;
        }

        $this->insertEmailQueue([
            'email_to' => $email,
            'approver_name' => $requestor['surname'] ?? 'Employee',
            'overtime_id' => $overtimeID,
            'email_type' => 'status_update',
            'decision' => $decision,
            'actor_name' => $actorName,
        ]);
    }

    public function addAcceptance(int $overtime, int $approverID, ?int $approvalLevel = 1): bool
    {
        $level = $approvalLevel !== null && $approvalLevel >= 1 && $approvalLevel <= 4
            ? $approvalLevel
            : 1;
        $sql = "INSERT INTO `overtime_accept` (`overtime_id`, `approver_id`, `approval_level`)
                VALUES (:overtimeID, :approverID, :approvalLevel)";
        $stmt = $this->pdo->prepare($sql);
        return (bool)$stmt->execute([
            ":overtimeID" => $overtime,
            ":approverID" => $approverID,
            ":approvalLevel" => $level,
        ]);
    }

    /**
     * The approver's assignment row, or null when they are not assigned at all.
     * Unlike findAcceptanceLevel(), a row with a NULL level is still returned.
     *
     * @return array{approval_level: ?int, status: ?int, remarks: ?string}|null
     */
    public function findAcceptance(int $overtimeID, int $approverID): ?array
    {
        $sql = "SELECT `approval_level`, `status`, `remarks` FROM `overtime_accept`
                WHERE `overtime_id` = :overtimeID AND `approver_id` = :approverID
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':overtimeID' => $overtimeID,
            ':approverID' => $approverID,
        ]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return [
            'approval_level' => $row['approval_level'] !== null ? (int) $row['approval_level'] : null,
            'status' => ($row['status'] === null || $row['status'] === '') ? null : (int) $row['status'],
            'remarks' => $row['remarks'] !== null ? (string) $row['remarks'] : null,
        ];
    }

    public function findAcceptanceLevel(int $overtimeID, int $approverID): ?int
    {
        $sql = "SELECT `approval_level` FROM `overtime_accept`
                WHERE `overtime_id` = :overtimeID AND `approver_id` = :approverID
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':overtimeID' => $overtimeID,
            ':approverID' => $approverID,
        ]);
        $level = $stmt->fetchColumn();
        if ($level === false || $level === null) {
            return null;
        }

        return (int) $level;
    }

    /** @return array<int, array<string, mixed>> */
    public function findPendingRequestsForDate(string $date): array
    {
        $sql = "SELECT orq.`id`, orq.`user_id`, orq.`group_id`, orq.`request_date`, orq.`status`
                FROM `overtime_request` orq
                WHERE orq.`request_date` = :requestDate AND orq.`status` IS NULL
                ORDER BY orq.`id` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':requestDate' => $date]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Decisions already cast by approvers, with snapshot levels.
     *
     * @return array<int, array{approver_id: int, surname: string, status: int, remarks: string, approval_level: int, date_accepted: ?string}>
     */
    public function findDecisionsWithLevels(int $overtimeID): array
    {
        $sql = "SELECT oa.`approver_id`, el.`surname`, oa.`status`, oa.`remarks`,
                       oa.`approval_level`, oa.`date_accepted`
                FROM `overtime_accept` oa
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = oa.`approver_id`
                WHERE oa.`overtime_id` = :overtimeID AND oa.`status` IS NOT NULL
                ORDER BY oa.`approval_level` DESC, oa.`date_accepted` DESC, oa.`approver_id` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':overtimeID' => $overtimeID]);
        $rows = $stmt->fetchAll() ?: [];

        return array_map(static function (array $row): array {
            return [
                'approver_id' => (int) $row['approver_id'],
                'surname' => (string) ($row['surname'] ?? 'Approver'),
                'status' => (int) $row['status'],
                'remarks' => (string) ($row['remarks'] ?? ''),
                'approval_level' => (int) ($row['approval_level'] ?? 1),
                'date_accepted' => $row['date_accepted'] ?? null,
            ];
        }, $rows);
    }

    /**
     * Approver queue: date-bounded on request_date, but open items always included.
     *
     * @param array{from: string, to: string, page: int, limit: int, offset: int, view?: string} $filters
     * @return array{
     *   data: array<int, array<string, mixed>>,
     *   pagination: array{page: int, limit: int, total: int, pages: int},
     *   counts: array{total: int, pending: int, acted: int}
     * }
     */
    public function findOvertimeToApprove(int $approverID, array $filters = []): array
    {
        $from = (string) ($filters['from'] ?? date('Y-m-d', strtotime('-7 days')));
        $to = (string) ($filters['to'] ?? date('Y-m-d'));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, (int) ($filters['limit'] ?? 10));
        $offset = max(0, (int) ($filters['offset'] ?? (($page - 1) * $limit)));
        $view = strtolower(trim((string) ($filters['view'] ?? 'all')));

        $baseWhere = [
            'oa.`approver_id` = :approverID',
            '(orq.`status` != 2 OR orq.`status` IS NULL)',
            '((orq.`request_date` >= :fromDate AND orq.`request_date` <= :toDate)
              OR (oa.`status` IS NULL AND (orq.`status` IS NULL OR orq.`status` = \'\')))',
        ];
        $params = [
            ':approverID' => $approverID,
            ':fromDate' => $from,
            ':toDate' => $to,
        ];

        $openSql = '(oa.`status` IS NULL AND (orq.`status` IS NULL OR orq.`status` = \'\'))';
        $actedSql = '(oa.`status` IS NOT NULL OR (orq.`status` IS NOT NULL AND orq.`status` != \'\'))';

        $viewWhere = [];
        if ($view === 'action') {
            $viewWhere[] = $openSql;
        } elseif ($view === 'done') {
            $viewWhere[] = $actedSql;
        } elseif ($view === 'auto_approved') {
            $viewWhere[] = 'orq.`status` = 1 AND orq.`submitted_by` IS NOT NULL';
        } elseif ($view === 'auto_rejected') {
            $viewWhere[] = 'orq.`status` = 0
                AND NOT EXISTS (
                    SELECT 1 FROM `overtime_accept` oax
                    WHERE oax.`overtime_id` = orq.`id`
                      AND oax.`status` IS NOT NULL
                )';
        }

        $whereAll = array_merge($baseWhere, $viewWhere);
        $whereSql = implode(' AND ', $whereAll);
        $baseWhereSql = implode(' AND ', $baseWhere);

        $fromSql = "FROM `overtime_accept` oa
                INNER JOIN `overtime_request` orq ON oa.`overtime_id` = orq.`id`
                LEFT JOIN kdtphdb_new.`group_list` gl ON orq.`group_id` = gl.`id`
                LEFT JOIN `dispatch_locations` l ON orq.`location_id` = l.`fldID`
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = orq.`user_id`";

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) {$fromSql} WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Stats ignore the status chip so the three counters stay meaningful.
        $statsSql = "SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN {$openSql} THEN 1 ELSE 0 END) AS pending
                     {$fromSql}
                     WHERE {$baseWhereSql}";
        $statsStmt = $this->pdo->prepare($statsSql);
        $statsStmt->execute([
            ':approverID' => $approverID,
            ':fromDate' => $from,
            ':toDate' => $to,
        ]);
        $stats = $statsStmt->fetch() ?: ['total' => 0, 'pending' => 0];
        $statsTotal = (int) ($stats['total'] ?? 0);
        $statsPending = (int) ($stats['pending'] ?? 0);

        $sql = "SELECT orq.`id`, orq.`duration`, orq.`remarks`, orq.`request_date`, orq.`status`,
                       orq.`date_created`, orq.`submitted_by`, orq.`origin_request_id`,
                       el.`id` AS `employee_id`,
                       el.`surname` AS `employee_name`,
                       gl.`abbreviation` AS `group_name`,
                       l.`fldLocation` AS `location_name`
                {$fromSql}
                WHERE {$whereSql}
                ORDER BY
                    CASE
                        WHEN orq.`status` IS NOT NULL OR oa.`status` IS NOT NULL THEN 1
                        ELSE 0
                    END ASC,
                    orq.`date_created` DESC
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll() ?: [];

        return [
            'data' => $data ? $this->attachRequestDetails($data) : [],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
            ],
            'counts' => [
                'total' => $statsTotal,
                'pending' => $statsPending,
                'acted' => max(0, $statsTotal - $statsPending),
            ],
        ];
    }

    public function findApproverDetails(int $overtimeID): array
    {
        return $this->findApproverDetailsByRequestIds([$overtimeID])[$overtimeID] ?? [];
    }

    /**
     * @param int[] $requestIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function findApproverDetailsByRequestIds(array $requestIds): array
    {
        $requestIds = $this->normalizeRequestIds($requestIds);
        if (!$requestIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
        $sql = "SELECT oa.`overtime_id`, oa.`approver_id`, el.`surname`,
                    COALESCE(
                        CONCAT('Level ', COALESCE(oa.`approval_level`, oga.`approval_level`)),
                        dl.`name`
                    ) AS `role`,
                    oa.`status`, oa.`remarks`, oa.`date_accepted`,
                    COALESCE(oa.`approval_level`, oga.`approval_level`) AS `approval_level`
                FROM `overtime_accept` oa
                LEFT JOIN `overtime_request` orq ON orq.`id` = oa.`overtime_id`
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = oa.`approver_id`
                LEFT JOIN `overtime_group_approvers` oga
                    ON oga.`approver_id` = oa.`approver_id` AND oga.`group_id` = orq.`group_id`
                LEFT JOIN kdtphdb_new.`designation_list` dl ON dl.`id` = el.`designation`
                WHERE oa.`overtime_id` IN ({$placeholders})
                ORDER BY oa.`overtime_id` ASC,
                         COALESCE(oa.`approval_level`, oga.`approval_level`) ASC,
                         oa.`approver_id` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($requestIds);

        $grouped = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $requestId = (int) $row['overtime_id'];
            unset($row['overtime_id']);
            $grouped[$requestId][] = $row;
        }

        return $grouped;
    }

    public function approveRequest(int $overtimeID, int $approverID, string $remarks, int $approved): bool
    {
        $sql = "UPDATE `overtime_accept`
                SET `status` = :approved, `remarks` = :remarks, `date_accepted` = NOW()
                WHERE `overtime_id` = :overtimeID AND `approver_id` = :approverID";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':remarks' => $remarks,
            ':overtimeID' => $overtimeID,
            ':approverID' => $approverID,
            ':approved' => $approved,
        ]);

        // rowCount() is also 0 when the approver re-submits an identical decision,
        // so callers must verify assignment with findAcceptance() instead.
        return $stmt->rowCount() > 0;
    }

    public function requestExists(int $overtimeID): bool
    {
        $sql = "SELECT 1 FROM `overtime_request` WHERE `id` = :overtimeID LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':overtimeID' => $overtimeID]);

        return (bool) $stmt->fetchColumn();
    }

    public function checkIfFullyApproved(int $overtimeID): bool
    {
        $sql = "SELECT `status` FROM `overtime_request` WHERE `id` = :overtimeID";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ":overtimeID" => $overtimeID
        ]);
        $req = $stmt->fetchColumn();

        if ($req === false) {
            return false;
        }

        return $req !== null && $req !== '';
    }

    public function updateOvertimeStatus(int $overtimeID, string $ostatus): bool
    {
        $sql = "UPDATE `overtime_request` SET `status` = :ostatus WHERE `id` = :overtimeID";
        $stmt = $this->pdo->prepare($sql);
        return (bool)$stmt->execute([
            ":ostatus" => $ostatus,
            ":overtimeID" => $overtimeID
        ]);
    }

    public function addAcceptedRequestToDailyReport(int $overtimeID): void
    {
        $sql = "SELECT orq.`id`, orq.`user_id`, orq.`group_id`, orq.`location_id`,
                       orq.`request_date`, orq.`remarks`, gl.`abbreviation`
                FROM `overtime_request` orq
                INNER JOIN kdtphdb_new.`group_list` gl ON gl.`id` = orq.`group_id`
                WHERE orq.`id` = :overtimeID
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':overtimeID' => $overtimeID]);
        $request = $stmt->fetch();
        if (!$request) {
            throw new \RuntimeException('Accepted overtime request was not found.');
        }

        $projects = $this->findProjectsByRequestIds([$overtimeID])[$overtimeID] ?? [];
        if (!$projects) {
            throw new \RuntimeException('Accepted overtime request has no project allocations.');
        }

        $insertStmt = $this->pdo->prepare(
            "INSERT INTO `dailyreport`
                (`fldEmployeeNum`, `fldGroup`, `fldGroupID`, `fldDate`, `fldLocation`,
                 `fldProject`, `fldItem`, `fldRevision`, `fldDuration`, `fldMHType`,
                 `fldRemarks`, `fldChangeLog`)
             VALUES
                (:employeeId, :groupAbbr, :groupId, :reportDate, :locationId,
                 :projectId, 0, 0, :durationMinutes, 1, :remarks, :changeLog)"
        );
        $changeLog = date('YmdHis') . '_' . (int) $request['user_id'];

        foreach ($projects as $project) {
            $insertStmt->execute([
                ':employeeId' => (int) $request['user_id'],
                ':groupAbbr' => (string) $request['abbreviation'],
                ':groupId' => (int) $request['group_id'],
                ':reportDate' => (string) $request['request_date'],
                ':locationId' => (int) $request['location_id'],
                ':projectId' => (int) $project['project_id'],
                ':durationMinutes' => (int) $project['hours'] * 60,
                ':remarks' => $request['remarks'] !== '' ? $request['remarks'] : null,
                ':changeLog' => $changeLog,
            ]);
        }
    }

    /**
     * A request plus how many approvers actually acted on it, so callers can tell an
     * auto-rejection (nobody acted by the cutoff) from a real approver rejection.
     *
     * @return array<string, mixed>|null
     */
    public function findRequestWithDecisionCount(int $overtimeID): ?array
    {
        $sql = "SELECT orq.`id`, orq.`user_id`, orq.`group_id`, orq.`location_id`,
                       orq.`remarks`, orq.`request_date`, orq.`status`,
                       (SELECT COUNT(*) FROM `overtime_accept` oa
                         WHERE oa.`overtime_id` = orq.`id` AND oa.`status` IS NOT NULL) AS `acted_count`
                FROM `overtime_request` orq
                WHERE orq.`id` = :overtimeID";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':overtimeID' => $overtimeID]);
        $data = $stmt->fetch();

        return $data ? $data : null;
    }

    public function hasFollowUp(int $originRequestID): bool
    {
        $sql = "SELECT 1 FROM `overtime_request`
                WHERE `origin_request_id` = :originRequestID LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':originRequestID' => $originRequestID]);

        return (bool) $stmt->fetchColumn();
    }

    public function findOwnedPendingRequest(int $overtimeID, int $userID): array
    {
        $sql = "SELECT orq.`id`, orq.`user_id`, orq.`status`, orq.`duration`, orq.`remarks`, orq.`request_date`,
                    gl.`abbreviation`
                FROM `overtime_request` orq
                LEFT JOIN kdtphdb_new.`group_list` gl ON gl.`id` = orq.`group_id`
                WHERE orq.`id` = :overtimeID AND orq.`user_id` = :userID";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':overtimeID' => $overtimeID,
            ':userID' => $userID,
        ]);
        $data = $stmt->fetch();

        return $data ? $data : [];
    }

    public function cancelRequest(int $overtimeID, int $userID): bool
    {
        $sql = "UPDATE `overtime_request` SET `status` = 2
                WHERE `id` = :overtimeID AND `user_id` = :userID AND `status` IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':overtimeID' => $overtimeID,
            ':userID' => $userID,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function findPicsForOvertime(int $overtimeID): array
    {
        $sql = "SELECT el.`id`, el.`surname`, el.`email`
                FROM `overtime_accept` oa
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = oa.`approver_id`
                WHERE oa.`overtime_id` = :overtimeID";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':overtimeID' => $overtimeID]);
        $data = $stmt->fetchAll();

        return $data ? $data : [];
    }
}