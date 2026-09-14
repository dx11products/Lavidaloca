<?php
session_start();

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'lavidaloca');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Database verbinding mislukt.");
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}