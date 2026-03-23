<?php
$configjmr = [
  'host' => 'localhost',
  'dbname' => 'webjmrdb',
  'charset' => 'utf8mb4',
];
$configkdt = [
    'host' => 'localhost',
    'dbname' => 'kdtphdb',
    'charset' => 'utf8mb4',
];
$configkdtnew = [
  'host' => 'localhost',
  'dbname' => 'kdtphdb_new',
  'charset' => 'utf8mb4',
];
$username = 'root';
$password = '';
$dsnjmr = 'mysql:' . http_build_query($configjmr, '', ';');
$dsnkdt = 'mysql:' . http_build_query($configkdt, '', ';');
$dsnkdtnew = 'mysql:' . http_build_query($configkdtnew, '', ';');

try {
  $connjmr = new PDO($dsnjmr, $username, $password, [
    PDO::ATTR_EMULATE_PREPARES, false,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
} catch (PDOException $e) {
  echo "Connection failed JMR";
}
try {
  $connkdt = new PDO($dsnkdt, $username, $password, [
    PDO::ATTR_EMULATE_PREPARES, false,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
} catch (PDOException $e) {
  echo "Connection failed KDTNew";
}
try {
  $connkdtnew = new PDO($dsnkdtnew, $username, $password, [
    PDO::ATTR_EMULATE_PREPARES, false,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
} catch (PDOException $e) {
  echo "Connection failed KDTNew";
}