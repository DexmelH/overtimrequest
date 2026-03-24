<?php

require "globalFunctions.php";

function getJobDescriptions() {
    global $connjmr;

    $getJobDescriptions = "SELECT `fldID`, `fldTOW` FROM `typesofworktable` WHERE `fldActive` = 1 AND `fldTOWType` = 1";
    $stmt = $connjmr->prepare($getJobDescriptions);
    $stmt->execute();
    return $stmt->fetchAll();
}

$works = getJobDescriptions();

echo json_encode($works);