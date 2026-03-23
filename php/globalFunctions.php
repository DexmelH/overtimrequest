<?php

require_once 'dbconnect.php';

function getUserID($userHash) {
    global $connkdt;

    $getID = "SELECT `fldEmployeeNum` FROM `kdtlogin` WHERE `fldUserHash` = :userHash";
    $stmt = $connkdt->prepare($getID);
    $stmt->execute(['userHash' => $userHash]);
    $result = $stmt->fetch();
    return $result ? $result['fldEmployeeNum'] : null;
}