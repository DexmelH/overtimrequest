<?php

require_once 'dbconnect.php';

$userDetails = $result = array();
$userHash = isset($_COOKIE['userHash']) ? $_COOKIE['userHash'] : '';

if (!isset($userHash) || empty($userHash)) {
    $result = [
        'success' => false,
        'message' => 'User not logged in'
    ];
} else {
    $stmt = $connjmr->prepare("SELECT * FROM `users` WHERE `fldHash` = :userHash");
    $stmt->execute(['userHash' => $userHash]);
    $userDetails = $stmt->fetch();

    if ($userDetails) {
        $result = [
            'status' => 'success',
            'data' => $userDetails
        ];
    } else {
        $result = [
            'status' => 'error',
            'message' => 'Invalid user hash'
        ];
    }
}