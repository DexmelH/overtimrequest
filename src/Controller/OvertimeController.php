<?php
namespace App\Controller;

use App\Repository\OvertimeRepository;
use App\Repository\UserRepository;
use App\Repository\GroupRepository;
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
        $user = $this->userRepo->findIdByHash($userHash);
        $userID = $user['id'];
        $history = $this->overtimeRepo->findHistoryByUserId($userID);

        return $history;
    }

    public function addOvertime(): int
    {
        $userHash = isset($_COOKIE['userID']) ? $_COOKIE['userID'] : '';
        $user = $this->userRepo->findIdByHash($userHash);
        $userID = $user['id'];
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

        $pdo = $this->overtimeRepo->getPdo();

        try {
            $pdo->beginTransaction();

            $id = $this->overtimeRepo->addOvertime($payload);
            $approver = $this->userRepo->findApprover($user["abbreviation"], $userID);
            foreach ($approver as $app) {
                $payload = [
                    'email_to' => $app['email'],
                    'approver_name' => $app['surname'],
                    'overtime_id' => $id,
                ];
                $this->overtimeRepo->insertEmailQueue($payload);
                $this->overtimeRepo->addAcceptance($id, $app['id']);
            }

            $pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Add overtime failed: ' . $e->getMessage());
            throw $e;
        }
        
    }

    public function getOvertimeToApprove(): array
    {
        $userHash = isset($_COOKIE['userID']) ? $_COOKIE['userID'] : '';
        $user = $this->userRepo->findIdByHash($userHash);
        $approverID = $user['id'];
        $overtimeToApprove = $this->overtimeRepo->findOvertimeToApprove($approverID);

        foreach ($overtimeToApprove as &$request) {
            $request['approver_details'] = $this->overtimeRepo->findApproverDetails($request['id']);
        }

        return $overtimeToApprove;
    }

    public function approveOvertime(): bool
    {
        $overtimeID = isset($_POST['overtimeID']) ? $_POST['overtimeID'] : 0;
        $remarks = isset($_POST['remarks']) ? $_POST['remarks'] : '';
        $approved = isset($_POST['status']) ? $_POST['status'] : NULL;

        $userHash = isset($_COOKIE['userID']) ? $_COOKIE['userID'] : '';
        $user = $this->userRepo->findIdByHash($userHash);
        $approverID = $user['id'];
        return $this->overtimeRepo->approveRequest($overtimeID, $approverID, $remarks, $approved);
    }
}