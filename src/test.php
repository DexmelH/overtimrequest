<?php

require  '../vendor/autoload.php';
$config = require __DIR__ . '/config.php';
$htmlPath = __DIR__ . '/usr/template/request_email.html';
$htmlTemplate = file_get_contents($htmlPath);

use App\Service\Mailer;

$mailer = new Mailer($config["mail"]);

$toEmail = "coquia-kdt@global.kawasaki.com";
$toName = "Dexmel";
$subject = "Test";
$bodyHtml = $htmlTemplate;

$mailer->send($toEmail, $toName, $subject, $bodyHtml);