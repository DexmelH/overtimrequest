<?php

require 'globalFunctions.php';

$project = isset($_GET['project']) ? $_GET['project'] : 0;

function getItemOfWorks($projectID) {
    global $connjmr;

    $getItemOfWorks = "SELECT `fldID`, `fldItem` FROM `itemofworkstable` WHERE `fldProject` = :projectID AND `fldActive` = 1 AND `fldDelete` = 0";
    $stmt = $connjmr->prepare($getItemOfWorks);
    $stmt->execute(['projectID' => $projectID]);
    return $stmt->fetchAll();
}

$items = getItemOfWorks($project);

echo json_encode($items);