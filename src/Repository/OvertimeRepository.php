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
        $sql = "SELECT el.`surname`, gl.`abbreviation`, pt.`fldProject`, orq.`remarks`, 
                    orq.`duration`, orq.`request_date`, orq.`date_created` 
                FROM `overtime_request` orq 
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = orq.`user_id` 
                LEFT JOIN kdtphdb_new.`group_list` gl ON gl.`id` = orq.`group_id` 
                LEFT JOIN `projectstable` pt ON pt.`fldID` = orq.`project_id` 
                WHERE orq.`id` = :requestID";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":requestID" => $requestID]);
        $data = $stmt->fetch();

        return $data ? $data : [];
    }

    public function findHistoryByUserId(string $userID): array
    {
        $sql = "SELECT orq.id, orq.duration, orq.remarks, orq.request_date, orq.status,
                   gl.abbreviation AS group_name,
                   l.fldLocation AS location_name,
                   p.fldProject AS project_name,
                   i.fldItem AS item_name,
                   j.fldJob AS job_desc, 
                   w.fldTOW AS work
                FROM `overtime_request` orq
                LEFT JOIN kdtphdb_new.`group_list` gl ON orq.group_id = gl.id
                LEFT JOIN `dispatch_locations` l ON orq.location_id = l.fldID
                LEFT JOIN `projectstable` p ON orq.project_id = p.fldID
                LEFT JOIN `itemofworkstable` i ON orq.item_id = i.fldID 
                LEFT JOIN `drawingreference` j ON orq.job_id = j.fldID 
                LEFT JOIN `typesofworktable` w ON orq.tow_id = w.fldID
                WHERE orq.user_id = :userID ORDER BY orq.date_created DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([":userID" => $userID]);
        $data = $stmt->fetchAll();

        return $data ? $data : [];
    }

    public function addOvertime(array $payload): int
    {
        $sql = "INSERT INTO `overtime_request` (`user_id`, `location_id`, `group_id`, 
                        `project_id`, `item_id`, `job_id`, `tow_id`, 
                        `duration`, `remarks`, `request_date`) 
                VALUES (:userID, :locationID, :groupID, :projectID, :itemOfWorkID, 
                        :jobDescriptionID, :workID, :duration, :remarks, :requestDate)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ":userID" => $payload["user_id"],
            ":locationID" => $payload["location_id"],
            ":groupID" => $payload["group_id"],
            ":projectID" => $payload["project_id"],
            ":itemOfWorkID" => $payload["item_id"],
            ":jobDescriptionID" => $payload["job_id"],
            ":workID" => $payload["work_id"],
            ":duration" => $payload["duration"],
            ":remarks" => $payload["remarks"],
            ":requestDate" => $payload["request_date"]
        ]);
        $lastId = $this->pdo->lastInsertId();

        return $lastId;
    }

    public function insertEmailQueue(array $payload): bool
    {
        $sql = "INSERT INTO `email_queue` (`email_to`, `approver_name`, `overtime_id`)
                VALUES (:emailTo, :approverName, :overtimeID)";
        $stmt = $this->pdo->prepare($sql);
        return (bool)$stmt->execute([
            ":emailTo" => $payload["email_to"],
            ":approverName" => $payload["approver_name"],
            ":overtimeID" => $payload["overtime_id"]
        ]);
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
                   l.fldLocation AS location_name,
                   p.fldProject AS project_name,
                   i.fldItem AS item_name,
                   j.fldJob AS job_desc, 
                   w.fldTOW AS work
                FROM `overtime_accept` oa
                LEFT JOIN `overtime_request` orq ON oa.overtime_id = orq.id
                LEFT JOIN kdtphdb_new.`group_list` gl ON orq.group_id = gl.id
                LEFT JOIN `dispatch_locations` l ON orq.location_id = l.fldID
                LEFT JOIN `projectstable` p ON orq.project_id = p.fldID
                LEFT JOIN `itemofworkstable` i ON orq.item_id = i.fldID 
                LEFT JOIN `drawingreference` j ON orq.job_id = j.fldID 
                LEFT JOIN `typesofworktable` w ON orq.tow_id = w.fldID
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.id = orq.user_id
                WHERE oa.approver_id = :approverID ORDER BY orq.date_created DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ":approverID" => $approverID
        ]);
        $data = $stmt->fetchAll();

        return $data ? $data : [];
    }

    public function findApproverDetails(int $overtimeID): array
    {
        $sql = "SELECT oa.`approver_id`, el.`surname`, dl.`name` as `role`, 
                    oa.`status`, oa.`remarks`, oa.`date_accepted` 
                FROM `overtime_accept` oa
                LEFT JOIN kdtphdb_new.`employee_list` el ON el.`id` = oa.`approver_id` 
                LEFT JOIN kdtphdb_new.`designation_list` dl ON dl.`id` = el.`designation` 
                WHERE oa.`overtime_id` = :overtimeID";
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
}