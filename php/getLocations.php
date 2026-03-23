<?php

require_once 'dbconnect.php';

$getLocations = $connjmr->query("SELECT * FROM `dispatch_locations` WHERE `fldActive` = 1")->fetchAll();
echo json_encode($getLocations);