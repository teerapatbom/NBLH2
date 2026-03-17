<?php
declare(strict_types=1);

$host = "127.0.0.1";
$db   = "nblh";
$user = "root";
$pass = "1q2w3e4r5t";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;port=3306;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("เชื่อมต่อข้อมูลไม่ได้" . $e->getMessage());
}

