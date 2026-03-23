<?php

include "globalFunctions.php";

$userHash = isset($_COOKIE['userID']) ? $_COOKIE['userID'] : '';

function getHistory($userID) {
    global $connjmr;

    $getHistory = "SELECT orq.id, orq.duration, orq.remarks, orq.request_date, orq.status,
                   gl.abbreviation AS group_name,
                   l.fldLocation AS location_name,
                   p.fldProject AS project_name,
                   i.fldItem AS item_name,
                   j.fldTOW AS job_description
                   FROM `overtime_request` orq
                   LEFT JOIN kdtphdb_new.`group_list` gl ON orq.group_id = gl.id
                   LEFT JOIN `dispatch_locations` l ON orq.location_id = l.fldID
                   LEFT JOIN `projectstable` p ON orq.project_id = p.fldID
                   LEFT JOIN `itemofworkstable` i ON orq.item_id = i.fldID
                   LEFT JOIN `typesofworktable` j ON orq.job_id = j.fldID
                   WHERE orq.user_id = :userID ORDER BY orq.date_created DESC";
    $stmt = $connjmr->prepare($getHistory);
    $stmt->execute(['userID' => $userID]);
    return $stmt->fetchAll();
}

$data = getHistory(getUserID($userHash));
echo json_encode($data);