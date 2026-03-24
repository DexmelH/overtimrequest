<?php

require "globalFunctions.php";

$userHash = isset($_COOKIE['userID']) ? $_COOKIE['userID'] : '';
$userID = getUserID($userHash);

$groupID = isset($_POST['group']) ? $_POST['group'] : 0;
$locationID = isset($_POST['location']) ? $_POST['location'] : 0;
$projectID = isset($_POST['project']) ? $_POST['project'] : 0;
$itemOfWorkID = isset($_POST['item']) ? $_POST['item'] : 0;
$jobDescriptionID = isset($_POST['jobdesc']) ? $_POST['jobdesc'] : 0;
$workID = isset($_POST['work']) ? $_POST['work'] : 0;
$remarks = isset($_POST['remarks']) ? $_POST['remarks'] : '';
$duration = isset($_POST['hours']) ? $_POST['hours'] : 0;
$requestDate = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');

function insertRequest($userID, $groupID, $locationID, $projectID, $itemOfWorkID, $jobDescriptionID, $workID, $remarks, $duration, $requestDate) {
    global $connjmr;

    $insertRequest = "INSERT INTO `overtime_request` (`user_id`, `location_id`, `group_id`, `project_id`, `item_id`, `job_id`, `tow_id`, 
                        `duration`, `remarks`, `request_date`) 
                        VALUES (:userID, :locationID, :groupID, :projectID, :itemOfWorkID, :jobDescriptionID, :workID, :duration, :remarks, :requestDate)";
    $stmt = $connjmr->prepare($insertRequest);
    $stmt->execute([
        'userID' => $userID,
        'locationID' => $locationID,
        'groupID' => $groupID,
        'projectID' => $projectID,
        'itemOfWorkID' => $itemOfWorkID,
        'jobDescriptionID' => $jobDescriptionID,
        'workID' => $workID,
        'duration' => $duration,
        'remarks' => $remarks,
        'requestDate' => $requestDate
    ]);
    return $connjmr->lastInsertId();
}

$insert = insertRequest($userID, $groupID, $locationID, $projectID, $itemOfWorkID, $jobDescriptionID, $workID, $remarks, $duration, $requestDate);

echo json_encode(['success' => $insert > 0, 'requestID' => $insert]);