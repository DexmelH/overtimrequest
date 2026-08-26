<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/config.php';

$container = new \App\Container();
(require __DIR__ . '/services.php')($container, $config);

return $container;
