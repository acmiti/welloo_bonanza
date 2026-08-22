<?php
// includes/auth.php — session-based authentication helpers
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verify credentials and start an authenticated session.
 * $identifier may be a username or an email address.
 */
function login(string $identifier, string $password): bool
{
    global $pdo;

    $stmt = $pdo->prepare(
        "SELECT id, username, password_hash, role, status
         FROM users
         WHERE (username = :identifier OR email = :identifier)
         LIMIT 1"
    );
    $stmt->execute([':identifier' => $identifier]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];

    return true;
}

/**
 * Clear and destroy the current session.
 */
function logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

/**
 * Middleware: require an active session, optionally restricted to specific roles.
 * API routes (path contains "/api/" or an Accept: application/json header) get a
 * 403 JSON response; everything else is redirected to login.php.
 */
function check_access(array $allowed_roles = []): void
{
    $is_logged_in = !empty($_SESSION['user_id']);
    $has_role     = $is_logged_in && (empty($allowed_roles) || in_array($_SESSION['role'], $allowed_roles, true));

    if ($is_logged_in && $has_role) {
        return;
    }

    $is_api_request = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

    if ($is_api_request) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'error',
            'message' => $is_logged_in ? 'Forbidden: insufficient role' : 'Unauthorized',
        ]);
        exit;
    }

    header('Location: /login.php');
    exit;
}
