<?php

require 'globalFunctions.php';

$group = isset($_GET['group']) ? $_GET['group'] : '';

function getProjects($groupID) {
    global $connjmr;

    $getProject = "SELECT `fldID`, `fldProject` FROM `projectstable` WHERE `fldGroup` = :groupID AND `fldActive` = 1 AND `fldDelete` = 0";
    $stmt = $connjmr->prepare($getProject);
    $stmt->execute(['groupID' => $groupID]);
    return $stmt->fetchAll();
}

$projects = getProjects($group);

echo json_encode($projects);