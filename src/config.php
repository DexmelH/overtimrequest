<?php

use App\Env;

require_once __DIR__ . '/Env.php';

Env::load(dirname(__DIR__));

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$dbHost = Env::get('DB_HOST', '127.0.0.1');
$dbPort = Env::get('DB_PORT', '3306');
$dbUser = Env::get('DB_USER', 'root');
$dbPass = Env::get('DB_PASS', '');
$dbCharset = Env::get('DB_CHARSET', 'utf8mb4');

$makeDsn = static function (string $database) use ($dbHost, $dbPort, $dbCharset): string {
    return "mysql:host={$dbHost};port={$dbPort};dbname={$database};charset={$dbCharset}";
};

$makeConnection = static function (string $database) use ($makeDsn, $dbUser, $dbPass, $pdoOptions): array {
    return [
        'dsn' => $makeDsn($database),
        'user' => $dbUser,
        'pass' => $dbPass,
        'options' => $pdoOptions,
    ];
};

$environment = Env::environment();
$timezone = Env::get('APP_TIMEZONE', 'Asia/Manila');
if ($timezone !== '') {
    date_default_timezone_set($timezone);
}

return [
    'app' => [
        'env' => $environment,
        'is_local' => Env::isLocal(),
        'is_testing' => Env::isTesting(),
        'is_production' => Env::isProduction(),
        'debug' => Env::bool('APP_DEBUG', false),
        'name' => Env::get('APP_NAME', 'Overtime Request App'),
        'base_path' => Env::get('APP_BASE_PATH', '/overtime'),
        'url' => Env::get('APP_URL', 'http://localhost/overtime'),
        'timezone' => $timezone,
        'approval_cutoff_time' => Env::get('APPROVAL_CUTOFF_TIME', '15:00'),
        'admin_group_abbrs' => array_values(array_filter(array_map(
            static fn ($abbr) => strtoupper(trim($abbr)),
            explode(',', Env::get('APP_ADMIN_GROUP_ABBRS', 'MNG,IT,SYS'))
        ))),
    ],
    'connections' => [
        '__default' => Env::get('DB_DEFAULT', 'webjmr'),
        'webjmr' => $makeConnection(Env::get('DB_WEBJMR_NAME', 'webjmrdb')),
        'kdtph' => $makeConnection(Env::get('DB_KDTPH_NAME', 'kdtphdb')),
        'kdtphnew' => $makeConnection(Env::get('DB_KDTPHNEW_NAME', 'kdtphdb_new')),
        'forms' => $makeConnection(Env::get('DB_FORMS_NAME', 'forms_db')),
    ],
    'mail' => [
        'host' => Env::get('MAIL_HOST', 'mail01.khi.co.jp'),
        'port' => Env::int('MAIL_PORT', 25),
        'from_email' => Env::get('MAIL_FROM_EMAIL', 'kdt-ph_overtime@global.kawasaki.com'),
        'from_name' => Env::get('MAIL_FROM_NAME', 'Overtime Request App'),
        'reply_to' => Env::get('MAIL_REPLY_TO', ''),
        'enabled' => Env::bool('MAIL_ENABLED', true),
        'test_recipient' => Env::get('MAIL_TEST_RECIPIENT', ''),
    ],
];
