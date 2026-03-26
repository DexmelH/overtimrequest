<?php
return [
  'connections' => [
    '__default' => 'webjmr',
    'webjmr' => [
      'dsn' => 'mysql:host=127.0.0.1;dbname=webjmrdb;charset=utf8mb4',
      'user' => 'root',
      'pass' => '',
      'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ],
    ],
    'kdtph' => [
      'dsn' => 'mysql:host=127.0.0.1;dbname=kdtphdb;charset=utf8mb4',
      'user' => 'root',
      'pass' => '',
      'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ],
    ],
    'kdtphnew' => [
      'dsn' => 'mysql:host=127.0.0.1;dbname=kdtphdb_new;charset=utf8mb4',
      'user' => 'root',
      'pass' => '',
      'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ],
    ],
  ],
  'mail' => [
    'host'       => 'mail01.khi.co.jp',
    'port'       => 25,
    'from_email' => 'kdt-ph_overtime@global.kawasaki.com',
    'from_name'  => 'Overtime Request App',
],
];
