<?php
date_default_timezone_set('Africa/Accra'); // Set Timezone to Ghana/UTC

$host = '127.0.0.1';
$db   = 'student_exam_system';
$user = 'root';
$pass = ''; // Default XAMPP password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Attempt to create database if it doesn't exist
    try {
        $pdo = new PDO("mysql:host=$host;charset=$charset", $user, $pass, $options);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db`");
        $pdo->exec("USE `$db`");
    } catch (\PDOException $e2) {
        throw new \PDOException($e2->getMessage(), (int)$e2->getCode());
    }
}
?>
