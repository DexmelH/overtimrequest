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
}