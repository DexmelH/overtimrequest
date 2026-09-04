<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
$config = require dirname(__DIR__) . '/src/config.php';
$db = new App\Database($config['connections'] ?? $config);
$webjmr = $db->getConnection('webjmr');
$kdtph = $db->getConnection('kdtph');

echo "kdtlogin columns:\n";
foreach ($kdtph->query('SHOW COLUMNS FROM kdtlogin') as $r) {
    echo $r['Field'] . "\t" . $r['Type'] . "\n";
}
echo "\nSample row keys for emp 464:\n";
$stmt = $kdtph->prepare('SELECT * FROM kdtlogin WHERE fldEmployeeNum = ? LIMIT 1');
$stmt->execute([464]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    foreach ($row as $k => $v) {
        $show = is_string($v) ? (strlen($v) > 24 ? substr($v, 0, 24) . '…' : $v) : json_encode($v);
        echo "$k=$show\n";
    }
}
