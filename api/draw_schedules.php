<?php
// api/draw_schedules.php — CRUD endpoint for the admin Draw Schedule queue.
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/draw_schedule.php';

check_access(['admin']);

const VALID_SCHEDULE_STATUSES = ['scheduled', 'active', 'completed'];
const FILTER_RULE_KEYS = ['inc_districts', 'inc_cities', 'inc_dealers', 'exc_districts', 'exc_cities', 'exc_dealers'];

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?: [];

function respond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

/** Coerce a submitted filter_rules payload into a clean {key: string[]} map. */
function clean_filter_rules($raw): array
{
    $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
    $out = [];
    foreach (FILTER_RULE_KEYS as $k) {
        $vals = is_array($decoded) ? ($decoded[$k] ?? []) : [];
        if (!is_array($vals)) {
            $vals = [];
        }
        $vals = array_values(array_unique(array_filter(
            array_map(fn($v) => trim((string) $v), $vals),
            fn($v) => $v !== ''
        )));
        if ($vals) {
            $out[$k] = $vals;
        }
    }
    return $out;
}

function valid_window(string $start, string $cutoff): bool
{
    $s = strtotime($start);
    $c = strtotime($cutoff);
    return $s !== false && $c !== false && $c > $s;
}

switch ($method) {
    case 'GET':
        draw_schedule_rollover($pdo);
        $rows = $pdo->query(
            "SELECT id, title, status, start_time, cutoff_time, filter_rules, created_at
               FROM draw_schedules
              ORDER BY start_time ASC, id ASC"
        )->fetchAll();
        respond(200, [
            'status'    => 'success',
            'schedules' => array_map(fn($r) => draw_schedule_present($r) + ['created_at' => $r['created_at']], $rows),
        ]);
        break;

    case 'POST':
        $action = $body['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $title  = trim($body['title'] ?? '');
            $start  = trim($body['start_time'] ?? '');
            $cutoff = trim($body['cutoff_time'] ?? '');
            $status = $body['status'] ?? 'scheduled';
            $rules  = clean_filter_rules($body['filter_rules'] ?? []);

            if ($title === '' || $start === '' || $cutoff === '') {
                respond(400, ['status' => 'error', 'message' => 'Title, start time and cut-off time are required']);
            }
            if (!in_array($status, VALID_SCHEDULE_STATUSES, true)) {
                respond(400, ['status' => 'error', 'message' => 'Invalid status']);
            }
            if (!valid_window($start, $cutoff)) {
                respond(400, ['status' => 'error', 'message' => 'Cut-off time must be after the start time']);
            }

            $rulesJson = $rules ? json_encode($rules) : null;

            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    "INSERT INTO draw_schedules (title, start_time, cutoff_time, status, filter_rules)
                     VALUES (:title, :start, :cutoff, :status, :rules)"
                );
                $stmt->execute([
                    ':title' => $title, ':start' => $start, ':cutoff' => $cutoff,
                    ':status' => $status, ':rules' => $rulesJson,
                ]);
                respond(201, ['status' => 'success', 'message' => 'Schedule created', 'id' => $pdo->lastInsertId()]);
            }

            $id = (int) ($body['id'] ?? 0);
            if ($id <= 0) {
                respond(400, ['status' => 'error', 'message' => 'Valid schedule id is required']);
            }
            $stmt = $pdo->prepare(
                "UPDATE draw_schedules
                    SET title = :title, start_time = :start, cutoff_time = :cutoff,
                        status = :status, filter_rules = :rules
                  WHERE id = :id"
            );
            $stmt->execute([
                ':title' => $title, ':start' => $start, ':cutoff' => $cutoff,
                ':status' => $status, ':rules' => $rulesJson, ':id' => $id,
            ]);
            respond(200, ['status' => 'success', 'message' => 'Schedule updated']);
        }

        if ($action === 'update_status') {
            $id     = (int) ($body['id'] ?? 0);
            $status = $body['status'] ?? '';
            if ($id <= 0 || !in_array($status, VALID_SCHEDULE_STATUSES, true)) {
                respond(400, ['status' => 'error', 'message' => 'Valid schedule id and status are required']);
            }
            $stmt = $pdo->prepare("UPDATE draw_schedules SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $id]);
            respond(200, ['status' => 'success', 'message' => 'Status updated']);
        }

        if ($action === 'delete') {
            $id = (int) ($body['id'] ?? 0);
            if ($id <= 0) {
                respond(400, ['status' => 'error', 'message' => 'Valid schedule id is required']);
            }
            $stmt = $pdo->prepare("DELETE FROM draw_schedules WHERE id = :id");
            $stmt->execute([':id' => $id]);
            respond(200, ['status' => 'success', 'message' => 'Schedule deleted']);
        }

        respond(400, ['status' => 'error', 'message' => 'Unknown action']);
        break;

    default:
        respond(405, ['status' => 'error', 'message' => 'Method Not Allowed']);
}
