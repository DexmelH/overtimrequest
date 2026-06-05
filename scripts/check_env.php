<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$config = require dirname(__DIR__) . '/src/config.php';

echo 'env=' . $config['app']['env'] . PHP_EOL;
echo 'db=' . $config['connections']['webjmr']['dsn'] . PHP_EOL;
echo 'mail_enabled=' . ($config['mail']['enabled'] ? '1' : '0') . PHP_EOL;
