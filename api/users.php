<?php
// api/users.php — CRUD endpoint for Super Admin user management
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';

check_access(['admin']);

$method = $_SERVER['REQUEST_METHOD'];
$raw_input = file_get_contents('php://input');
$body = json_decode($raw_input, true) ?: [];

const VALID_ROLES   = ['admin', 'data_entry', 'draw_manager'];
const VALID_STATUSES = ['active', 'inactive'];

switch ($method) {
    case 'GET':
        handle_list($pdo);
        break;

    case 'POST':
        $action = $body['action'] ?? 'create';
        match ($action) {
            'create'         => handle_create($pdo, $body),
            'update_role'    => handle_update_role($pdo, $body),
            'toggle_status'  => handle_toggle_status($pdo, $body),
            'reset_password' => handle_reset_password($pdo, $body),
            default          => respond(400, ['status' => 'error', 'message' => 'Unknown action']),
        };
        break;

    default:
        respond(405, ['status' => 'error', 'message' => 'Method Not Allowed']);
}

function respond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function handle_list(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT id, username, email, role, status, created_at FROM users ORDER BY id DESC");
    respond(200, ['status' => 'success', 'users' => $stmt->fetchAll()]);
}

function handle_create(PDO $pdo, array $body): void
{
    $username = trim($body['username'] ?? '');
    $email    = trim($body['email'] ?? '');
    $password = (string) ($body['password'] ?? '');
    $role     = $body['role'] ?? 'data_entry';

    if ($username === '' || $email === '' || $password === '') {
        respond(400, ['status' => 'error', 'message' => 'Username, email, and temporary password are required']);
    }
    if (strlen($password) < 8) {
        respond(400, ['status' => 'error', 'message' => 'Temporary password must be at least 8 characters']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(400, ['status' => 'error', 'message' => 'Invalid email address']);
    }
    if (!in_array($role, VALID_ROLES, true)) {
        respond(400, ['status' => 'error', 'message' => 'Invalid role']);
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO users (username, email, password_hash, role, status)
             VALUES (:username, :email, :password_hash, :role, 'active')"
        );
        $stmt->execute([
            ':username'      => $username,
            ':email'         => $email,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':role'          => $role,
        ]);
        respond(201, ['status' => 'success', 'message' => 'User created', 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            respond(409, ['status' => 'error', 'message' => 'Username or email already exists']);
        }
        respond(500, ['status' => 'error', 'message' => 'Database error']);
    }
}

function handle_update_role(PDO $pdo, array $body): void
{
    $id   = (int) ($body['id'] ?? 0);
    $role = $body['role'] ?? '';

    if ($id <= 0 || !in_array($role, VALID_ROLES, true)) {
        respond(400, ['status' => 'error', 'message' => 'Valid user id and role are required']);
    }

    $stmt = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
    $stmt->execute([':role' => $role, ':id' => $id]);

    respond(200, ['status' => 'success', 'message' => 'Role updated']);
}

function handle_toggle_status(PDO $pdo, array $body): void
{
    $id     = (int) ($body['id'] ?? 0);
    $status = $body['status'] ?? '';

    if ($id <= 0 || !in_array($status, VALID_STATUSES, true)) {
        respond(400, ['status' => 'error', 'message' => 'Valid user id and status are required']);
    }
    if ($id === (int) ($_SESSION['user_id'] ?? 0) && $status === 'inactive') {
        respond(400, ['status' => 'error', 'message' => 'You cannot deactivate your own account']);
    }

    $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $status, ':id' => $id]);

    respond(200, ['status' => 'success', 'message' => 'Status updated']);
}

function handle_reset_password(PDO $pdo, array $body): void
{
    $id       = (int) ($body['id'] ?? 0);
    $password = (string) ($body['password'] ?? '');

    if ($id <= 0 || strlen($password) < 8) {
        respond(400, ['status' => 'error', 'message' => 'Valid user id and a password of at least 8 characters are required']);
    }

    $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
    $stmt->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id]);

    respond(200, ['status' => 'success', 'message' => 'Password reset']);
}
