<?php
namespace App\Controller;

use App\Repository\OvertimeRepository;
use App\Repository\UserRepository;
use PDO;

class OvertimeController
{
    private OvertimeRepository $overtimeRepo;
    private UserRepository $userRepo;
    
    public function __construct(PDO $overtimePDO, PDO $userPDO)
    {
        $this->overtimeRepo = new OvertimeRepository($overtimePDO);
        $this->userRepo = new UserRepository($userPDO);
    }

    public function getUserHistory(): array
    {
        $userHash = isset($_COOKIE['userID']) ? $_COOKIE['userID'] : '';
        $userID = $this->userRepo->findIdByHash($userHash);
        $history = $this->overtimeRepo->findHistoryByUserId($userID);

        return $history;
    }

    public function addOvertime(): int
    {
        $userHash = isset($_COOKIE['userID']) ? $_COOKIE['userID'] : '';
        $userID = $this->userRepo->findIdByHash($userHash);
        $groupID = isset($_POST['group']) ? $_POST['group'] : 0;
        $locationID = isset($_POST['location']) ? $_POST['location'] : 0;
        $projectID = isset($_POST['project']) ? $_POST['project'] : 0;
        $itemOfWorkID = isset($_POST['item']) ? $_POST['item'] : 0;
        $jobDescriptionID = isset($_POST['jobdesc']) ? $_POST['jobdesc'] : 0;
        $workID = isset($_POST['work']) ? $_POST['work'] : 0;
        $remarks = isset($_POST['remarks']) ? $_POST['remarks'] : '';
        $duration = isset($_POST['hours']) ? $_POST['hours'] : 0;
        $requestDate = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');

        $payload = [
            "user_id" => $userID,
            "group_id" => $groupID,
            "location_id" => $locationID,
            "project_id" => $projectID,
            "item_id" => $itemOfWorkID,
            "job_id" => $jobDescriptionID,
            "work_id" => $workID,
            "remarks" => $remarks,
            "duration" => $duration,
            "request_date" => $requestDate
        ];

        $id = $this->overtimeRepo->addOvertime($payload);

        return $id;
    }
}