<?php

require "globalFunctions.php";

$userHash = isset($_COOKIE['userID']) ? $_COOKIE['userID'] : '';
$userID = getUserID($userHash);

$groupID = isset($_POST['group']) ? $_POST['group'] : 0;
$locationID = isset($_POST['location']) ? $_POST['location'] : 0;
$remarks = isset($_POST['remarks']) ? $_POST['remarks'] : '';
$requestDate = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
$projects = json_decode((string) ($_POST['projects'] ?? ''), true);

function insertRequest($userID, $groupID, $locationID, $remarks, $requestDate, array $projects) {
    global $connjmr;

    $normalized = [];
    $seen = [];
    foreach ($projects as $project) {
        $projectId = (int) ($project['project_id'] ?? 0);
        $hours = filter_var($project['hours'] ?? null, FILTER_VALIDATE_INT);
        if ($projectId <= 0 || $hours === false || $hours <= 0 || isset($seen[$projectId])) {
            return 0;
        }
        $seen[$projectId] = true;
        $normalized[] = ['project_id' => $projectId, 'hours' => $hours];
    }
    if (!$normalized) {
        return 0;
    }

    $connjmr->beginTransaction();
    try {
        $insertRequest = "INSERT INTO `overtime_request`
                            (`user_id`, `location_id`, `group_id`, `duration`, `remarks`, `request_date`)
                          VALUES
                            (:userID, :locationID, :groupID, :duration, :remarks, :requestDate)";
        $stmt = $connjmr->prepare($insertRequest);
        $stmt->execute([
            'userID' => $userID,
            'locationID' => $locationID,
            'groupID' => $groupID,
            'duration' => array_sum(array_column($normalized, 'hours')),
            'remarks' => $remarks,
            'requestDate' => $requestDate,
        ]);
        $requestId = (int) $connjmr->lastInsertId();

        $allocation = $connjmr->prepare(
            "INSERT INTO `overtime_request_projects`
                (`overtime_request_id`, `project_id`, `hours`, `sort_order`)
             VALUES (:requestId, :projectId, :hours, :sortOrder)"
        );
        foreach ($normalized as $index => $project) {
            $allocation->execute([
                'requestId' => $requestId,
                'projectId' => $project['project_id'],
                'hours' => $project['hours'],
                'sortOrder' => $index,
            ]);
        }

        $connjmr->commit();
        return $requestId;
    } catch (Throwable $e) {
        if ($connjmr->inTransaction()) {
            $connjmr->rollBack();
        }
        return 0;
    }
}

$insert = is_array($projects)
    ? insertRequest($userID, $groupID, $locationID, $remarks, $requestDate, $projects)
    : 0;

echo json_encode(['success' => $insert > 0, 'requestID' => $insert]);