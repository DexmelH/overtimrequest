<?php
/* ----------------- CONFIG: set your DB details here ----------------- */
$dbHost = '157.116.25.158';
$dbName = 'pc_login';
$dbUser = 'kdt';
$dbPass = 'none';
$dsn = "mysql:host=$dbHost;port=3000;dbname=$dbName;charset=utf8mb4";
/* -------------------------------------------------------------------- */

/* ----------------- ENTRY: change these values as needed ------------- */
$entry = [
    'employee_name'  => 439,
    'date_requested' => '2026-08-18 17:39:05',
];
/* -------------------------------------------------------------------- */

try {
    // Connect
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Insert
    $sql = "INSERT INTO `timelog`(`fldEmployeeNum`, `fldDT`, `fldStatus`, `pcNum`, `dateSync`, `method`) 
            VALUES (:employeeNum, :dateAdded, :fldStatus, :pcNum, :dateSync, :method)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':employeeNum' => $entry['employee_name'],
        ':dateAdded' => $entry['date_requested'],
        ':fldStatus' => 0,
        ':pcNum' => 'KDTW703',
        ':dateSync' => $entry['date_requested'],
        ':method' => 3,
    ]);

    $id = (int)$pdo->lastInsertId();
    echo "Inserted timeout with id: {$id}\n";
} catch (PDOException $e) {
    // In production, log the error instead of echoing full message
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}