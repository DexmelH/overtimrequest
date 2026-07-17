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

    public function findRequestById(string $requestID): array
    {
        return $this->findRequestEmailDetails((int) $requestID);
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
                ORDER BY `date_accepted` DESC
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":overtimeID" => $overtimeID]);
        $remarks = $stmt->fetchColumn();

        return $remarks ? (string) $remarks : '';
    }

    public function findHistoryByUserId(string $userID): array
    {
        $sql = "SELECT orq.id, orq.duration, orq.remarks, orq.request_date, orq.status,
                   gl.abbreviation AS group_name,
                   l.fldLocation AS location_name
                FROM `overtime_request` orq
                LEFT JOIN kdtphdb_new.`group_list` gl ON orq.group_id = gl.id
                LEFT JOIN `dispatch_locations` l ON orq.location_id = l.fldID
                WHERE orq.user_id = :userID ORDER BY orq.date_created DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":userID" => $userID]);
        $data = $stmt->fetchAll();

        return $data ? $this->attachProjects($data) : [];
    }

    public function addOvertime(array $payload): int
    {
        $sql = "INSERT INTO `overtime_request`
                    (`user_id`, `location_id`, `group_id`, `duration`, `remarks`, `request_date`)
                VALUES
                    (:userID, :locationID, :groupID, :duration, :remarks, :requestDate)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ":userID" => $payload["user_id"],
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

    /** @param int[] $projectIds */
    public function projectsBelongToGroup(array $projectIds, string $groupAbbreviation): bool
    {
        $projectIds = array_values(array_unique(array_filter(array_map('intval', $projectIds))));
        if (!$projectIds || trim($groupAbbreviation) === '') {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $sql = "SELECT COUNT(DISTINCT `fldID`)
                FROM `projectstable`
                WHERE `fldID` IN ({$placeholders})
                  AND `fldGroup` = ?
                  AND `fldActive` = 1
                  AND `fldDelete` = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([...$projectIds, $groupAbbreviation]);

        return (int) $stmt->fetchColumn() === count($projectIds);
    }

    /** @return array<int, array<int, array{project_id: int, project_name: string, hours: int}>> */
    public function findProjectsByRequestIds(array $requestIds): array
    {
        $requestIds = array_values(array_unique(array_filter(array_map('intval', $requestIds))));
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

    /** @param array<int, array<string, mixed>> $rows */
    private function attachProjects(array $rows): array
    {
        $projectsByRequest = $this->findProjectsByRequestIds(array_column($rows, 'id'));

        foreach ($rows as &$row) {
            $requestId = (int) $row['id'];
            $projects = $projectsByRequest[$requestId] ?? [];
            $row['projects'] = $projects;
            $row['project_name'] = $this->formatProjectSummary($projects);
        }
        unset($row);

        return $rows;
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

    /** @return int[] */
    public function findAssignedGroupIds(int $approverId): array
    {
        $sql = "SELECT DISTINCT orq.`group_id`
                FROM `overtime_accept` oa
                INNER JOIN `overtime_request` orq ON orq.`id` = oa.`overtime_id`
                WHERE oa.`approver_id` = :approverId AND orq.`group_id` IS NOT NULL
                ORDER BY orq.`group_id` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':approverId' => $approverId]);
        $ids = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $ids[] = (int) $row['group_id'];
        }

        return $ids;
    }

    public function findApproverGroupDetails(int $approverId): array
    {
        $sql = "SELECT DISTINCT gl.`id`, gl.`abbreviation`, gl.`name`
                FROM `overtime_accept` oa
                INNER JOIN `overtime_request` orq ON orq.`id` = oa.`overtime_id`
                INNER JOIN kdtphdb_new.`group_list` gl ON gl.`id` = orq.`group_id`
                WHERE oa.`approver_id` = :approverId AND orq.`group_id` IS NOT NULL
                ORDER BY gl.`abbreviation` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':approverId' => $approverId]);

        return $stmt->fetchAll() ?: [];
    }

    public function addAcceptance(int $overtime, int $approverID): bool
    {
        $sql = "INSERT INTO `overtime_accept` (`overtime_id`, `approver_id`) VALUES (:overtimeID, :approverID)";
        $stmt = $this->pdo->prepare($sql);
        return (bool)$stmt->execute([
            ":overtimeID" => $overtime,
            ":approverID" => $approverID
        ]);
    }

    public function findOvertimeToApprove(int $approverID): array
    {
        $sql = "SELECT orq.id, orq.duration, orq.remarks, orq.request_date, orq.status,
                   el.id AS employee_id,
                   el.surname AS employee_name,
                   gl.abbreviation AS group_name,
                   l.fldLocation AS location_name
                FROM `overtime_accept` oa
                LEFT JOIN `overtime_request` orq ON oa.overtime_id = orq.id
                LEFT JOIN kdtphdb_new.`group_list` gl ON orq.group_id = gl.id
                LEFT JOIN `dispatch_locations` l ON orq.location_id = l.fldID
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.id = orq.user_id
                WHERE oa.approver_id = :approverID AND (orq.status != 2 OR orq.status IS NULL)
                ORDER BY orq.date_created DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ":approverID" => $approverID
        ]);
        $data = $stmt->fetchAll();

        return $data ? $this->attachProjects($data) : [];
    }

    public function findApproverDetails(int $overtimeID): array
    {
        $sql = "SELECT oa.`approver_id`, el.`surname`,
                    COALESCE(CONCAT('Level ', oga.`approval_level`), dl.`name`) AS `role`,
                    oa.`status`, oa.`remarks`, oa.`date_accepted`, oga.`approval_level`
                FROM `overtime_accept` oa
                LEFT JOIN `overtime_request` orq ON orq.`id` = oa.`overtime_id`
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = oa.`approver_id`
                LEFT JOIN `overtime_group_approvers` oga
                    ON oga.`approver_id` = oa.`approver_id` AND oga.`group_id` = orq.`group_id`
                LEFT JOIN kdtphdb_new.`designation_list` dl ON dl.`id` = el.`designation`
                WHERE oa.`overtime_id` = :overtimeID
                ORDER BY oga.`approval_level` ASC, oa.`approver_id` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ":overtimeID" => $overtimeID
        ]);
        $data = $stmt->fetchAll();

        return $data ? $data : [];
    }

    public function approveRequest(int $overtimeID, int $approverID, string $remarks, int $approved): bool
    {
        $sql = "UPDATE `overtime_accept` SET `status` = :approved, `remarks` = :remarks, `date_accepted` = NOW() 
                WHERE `overtime_id` = :overtimeID AND `approver_id` = :approverID";
        $stmt = $this->pdo->prepare($sql);
        return (bool)$stmt->execute([
            ":remarks" => $remarks,
            ":overtimeID" => $overtimeID,
            ":approverID" => $approverID,
            ":approved" => $approved
        ]);
    }

    public function checkIfAlreadyApproved(int $overtimeID, int $approverID): bool
    {
        $sql = "SELECT COUNT(*) FROM `overtime_accept` WHERE `overtime_id` = :overtimeID AND `approver_id` = :approverID
                AND `status` IS NOT NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ":overtimeID" => $overtimeID,
            ":approverID" => $approverID
        ]);
        $count = $stmt->fetchColumn();

        $sql2 = "SELECT COUNT(*) FROM `overtime_request` WHERE `id` = :overtimeID AND `status` IS NOT NULL";
        $stmt2 = $this->pdo->prepare($sql2);
        $stmt2->execute([":overtimeID" => $overtimeID]);
        $count2 = $stmt2->fetchColumn();

        return $count > 0 || $count2 > 0;
    }

    public function checkIfFullyApproved(int $overtimeID): bool
    {
        $sql = "SELECT `status` FROM `overtime_request` WHERE `id` = :overtimeID";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ":overtimeID" => $overtimeID
        ]);
        $req = $stmt->fetchColumn();

        return $req !== NULL;
    }

    public function checkIfForApproval(int $overtimeID, string $ostatus): bool
    {
        $sql = "SELECT COUNT(*) FROM `overtime_accept` WHERE `overtime_id` = :overtimeID AND `status` = :ostatus";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ":overtimeID" => $overtimeID,
            ":ostatus" => $ostatus
        ]);
        $count = $stmt->fetchColumn();

        return $count == 1;
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