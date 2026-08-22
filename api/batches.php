<?php
// api/batches.php — CRUD endpoint for admin draw-batch management
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';

check_access(['admin']);

const VALID_BATCH_STATUSES = ['draft', 'active', 'locked', 'completed'];

$method = $_SERVER['REQUEST_METHOD'];
$raw_input = file_get_contents('php://input');
$body = json_decode($raw_input, true) ?: [];

switch ($method) {
    case 'GET':
        handle_list($pdo);
        break;

    case 'POST':
        $action = $body['action'] ?? '';
        match ($action) {
            'create'          => handle_create($pdo, $body),
            'update_status'   => handle_update_status($pdo, $body),
            'update_schedule' => handle_update_schedule($pdo, $body),
            default           => respond(400, ['status' => 'error', 'message' => 'Unknown action']),
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
    $stmt = $pdo->query(
        "SELECT b.id, b.batch_name, b.entry_start_time, b.entry_deadline, b.draw_datetime,
                b.status, b.created_at,
                COUNT(e.id) AS entry_count
         FROM draw_batches b
         LEFT JOIN bonanza_entries e ON e.batch_id = b.id
         GROUP BY b.id
         ORDER BY b.id DESC"
    );
    respond(200, ['status' => 'success', 'batches' => $stmt->fetchAll()]);
}

function handle_create(PDO $pdo, array $body): void
{
    $name     = trim($body['batch_name'] ?? '');
    $start    = trim($body['entry_start_time'] ?? '');
    $deadline = trim($body['entry_deadline'] ?? '');
    $draw     = trim($body['draw_datetime'] ?? '');
    $status   = $body['status'] ?? 'draft';

    if ($name === '' || $start === '' || $deadline === '' || $draw === '') {
        respond(400, ['status' => 'error', 'message' => 'Batch name, start time, deadline, and draw time are required']);
    }
    if (!in_array($status, VALID_BATCH_STATUSES, true)) {
        respond(400, ['status' => 'error', 'message' => 'Invalid status']);
    }
    foreach (['start' => $start, 'deadline' => $deadline, 'draw' => $draw] as $label => $value) {
        if (strtotime($value) === false) {
            respond(400, ['status' => 'error', 'message' => "Invalid date/time for $label"]);
        }
    }

    if ($status === 'active') {
        demote_other_active_batches($pdo);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO draw_batches (batch_name, entry_start_time, entry_deadline, draw_datetime, status)
         VALUES (:name, :start, :deadline, :draw, :status)"
    );
    $stmt->execute([
        ':name'     => $name,
        ':start'    => $start,
        ':deadline' => $deadline,
        ':draw'     => $draw,
        ':status'   => $status,
    ]);

    respond(201, ['status' => 'success', 'message' => 'Batch created', 'id' => $pdo->lastInsertId()]);
}

function handle_update_status(PDO $pdo, array $body): void
{
    $id     = (int) ($body['id'] ?? 0);
    $status = $body['status'] ?? '';

    if ($id <= 0 || !in_array($status, VALID_BATCH_STATUSES, true)) {
        respond(400, ['status' => 'error', 'message' => 'Valid batch id and status are required']);
    }

    if ($status === 'active') {
        demote_other_active_batches($pdo, $id);
    }

    $stmt = $pdo->prepare("UPDATE draw_batches SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $status, ':id' => $id]);

    respond(200, ['status' => 'success', 'message' => 'Status updated']);
}

function handle_update_schedule(PDO $pdo, array $body): void
{
    $id       = (int) ($body['id'] ?? 0);
    $name     = trim($body['batch_name'] ?? '');
    $start    = trim($body['entry_start_time'] ?? '');
    $deadline = trim($body['entry_deadline'] ?? '');
    $draw     = trim($body['draw_datetime'] ?? '');

    if ($id <= 0 || $name === '' || $start === '' || $deadline === '' || $draw === '') {
        respond(400, ['status' => 'error', 'message' => 'Batch id, name, start time, deadline, and draw time are required']);
    }
    foreach (['start' => $start, 'deadline' => $deadline, 'draw' => $draw] as $label => $value) {
        if (strtotime($value) === false) {
            respond(400, ['status' => 'error', 'message' => "Invalid date/time for $label"]);
        }
    }

    $stmt = $pdo->prepare(
        "UPDATE draw_batches
         SET batch_name = :name, entry_start_time = :start, entry_deadline = :deadline, draw_datetime = :draw
         WHERE id = :id"
    );
    $stmt->execute([
        ':name'     => $name,
        ':start'    => $start,
        ':deadline' => $deadline,
        ':draw'     => $draw,
        ':id'       => $id,
    ]);

    respond(200, ['status' => 'success', 'message' => 'Schedule updated']);
}

/**
 * Keeps the "one active batch" invariant that submit.php relies on:
 * whenever a batch is set to active, any other currently-active batch
 * is locked.
 */
function demote_other_active_batches(PDO $pdo, int $exceptId = 0): void
{
    $stmt = $pdo->prepare("UPDATE draw_batches SET status = 'locked' WHERE status = 'active' AND id != :id");
    $stmt->execute([':id' => $exceptId]);
}
