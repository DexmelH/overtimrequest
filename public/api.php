<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/** @var \App\Container $container */
$container = require __DIR__ . '/../src/bootstrap.php';
$container->get(\App\Application::class)->run();
