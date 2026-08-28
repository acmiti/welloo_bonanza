<?php
// config/db.php — shared PDO database connection

// The business runs on Sri Lanka Standard Time (UTC+5:30). Pin PHP's clock here
// so every date() / strtotime() call across the app resolves in Asia/Colombo
// regardless of the server's own timezone.
date_default_timezone_set('Asia/Colombo');

$db_host = 'localhost';
$db_name = 'welloocs_bonanza';
$db_user = 'welloocs_bonanza';
$db_pass = 'Tyyps7sJrXghJ3gUVwhy';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // Align the MySQL session clock with Asia/Colombo so NOW(), CURDATE(),
    // DATE() and CURRENT_DATE evaluate against Sri Lanka midnight, not UTC.
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $e) {
    http_response_code(500);
    // DEBUG: surfaces the real PDO error string during API calls for diagnosis.
    // Remove this detail (or gate it behind an env check) once the connection is confirmed working.
    $message = 'Database connection failed: ' . $e->getMessage();
    if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $message]);
    } else {
        echo htmlspecialchars($message);
    }
    exit;
}
