<?php

require 'globalFunctions.php';

$userHash = isset($_COOKIE['userID']) ? $_COOKIE['userID'] : '';

function getGroups($userID) {
    global $connkdtnew;

    $getGroups = "SELECT gl.`id`, gl.`abbreviation`, gl.`name` FROM `employee_group` eg
                  JOIN `group_list` gl ON eg.`group_id` = gl.`id`
                  WHERE eg.`employee_number` = :userID";
    $stmt = $connkdtnew->prepare($getGroups);
    $stmt->execute(['userID' => $userID]);
    return $stmt->fetchAll();
}

$groups = getGroups(getUserID($userHash));

echo json_encode($groups);
