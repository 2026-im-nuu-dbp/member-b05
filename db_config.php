<?php
// Database configuration

$host = 'localhost';
$db = 'hw_7';
$user = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('資料庫連線失敗: ' . $e->getMessage());
}

// Note: utility functions like `escape()` are defined in `auth.php` to avoid
// duplicate declarations when files include both db_config.php and auth.php.
