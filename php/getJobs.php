<?php

require "globalFunctions.php";

$item = isset($_GET['item']) ? $_GET['item'] : 0;

function getJobDescriptions($itemID) {
    global $connjmr;

    $getJobDescriptions = "SELECT `fldID`, `fldJob` FROM `drawingreference` WHERE `fldItem` = :itemID AND `fldActive` = 1 AND `fldDelete` = 0";
    $stmt = $connjmr->prepare($getJobDescriptions);
    $stmt->execute(['itemID' => $itemID]);
    return $stmt->fetchAll();
}

$jobs = getJobDescriptions($item);

echo json_encode($jobs);