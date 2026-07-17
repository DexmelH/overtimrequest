<?php

include "globalFunctions.php";

$userHash = isset($_COOKIE['userID']) ? $_COOKIE['userID'] : '';

function getHistory($userID) {
    global $connjmr;

    $getHistory = "SELECT orq.id, orq.duration, orq.remarks, orq.request_date, orq.status,
                   gl.abbreviation AS group_name,
                   l.fldLocation AS location_name
                   FROM `overtime_request` orq
                   LEFT JOIN kdtphdb_new.`group_list` gl ON orq.group_id = gl.id
                   LEFT JOIN `dispatch_locations` l ON orq.location_id = l.fldID
                   WHERE orq.user_id = :userID ORDER BY orq.date_created DESC";
    $stmt = $connjmr->prepare($getHistory);
    $stmt->execute(['userID' => $userID]);
    $rows = $stmt->fetchAll();

    $projectStmt = $connjmr->prepare(
        "SELECT orp.project_id, pt.fldProject AS project_name, orp.hours
         FROM overtime_request_projects orp
         LEFT JOIN projectstable pt ON pt.fldID = orp.project_id
         WHERE orp.overtime_request_id = :requestID
         ORDER BY orp.sort_order, orp.id"
    );
    foreach ($rows as &$row) {
        $projectStmt->execute(['requestID' => $row['id']]);
        $row['projects'] = $projectStmt->fetchAll();
    }
    unset($row);

    return $rows;
}

$data = getHistory(getUserID($userHash));
echo json_encode($data);