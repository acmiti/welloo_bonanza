<?php
// api/auth/login.php — handles login form submissions
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../includes/auth.php';

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true) ?: $_POST;

$identifier = trim($data['username'] ?? '');
$password   = (string) ($data['password'] ?? '');

if ($identifier === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username/email and password are required']);
    exit;
}

try {
    $ok = login($identifier, $password);
} catch (PDOException $e) {
    http_response_code(500);
    // DEBUG: surfaces the real PDO error string for diagnosis — remove once login is confirmed working.
    echo json_encode(['status' => 'error', 'message' => 'Login query failed: ' . $e->getMessage()]);
    exit;
}

if (!$ok) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
    exit;
}

$landing_pages = [
    'admin'        => '/admin/dashboard.php',
    'data_entry'   => '/admin/entries.php',
    'draw_manager' => '/admin/draw.php',
];

echo json_encode([
    'status'   => 'success',
    'redirect' => $landing_pages[$_SESSION['role']] ?? '/login.php',
]);
