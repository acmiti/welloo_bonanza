<?php
// api/winners.php — disqualify/re-spin and verification actions for recorded winners
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';

check_access(['admin', 'draw_manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $body['action'] ?? '';

match ($action) {
    'disqualify'          => handle_disqualify($pdo, $body),
    'toggle_verification' => handle_toggle_verification($pdo, $body),
    default                => respond(400, ['status' => 'error', 'message' => 'Unknown action']),
};

function respond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function handle_disqualify(PDO $pdo, array $body): void
{
    $entryId = (int) ($body['entry_id'] ?? 0);
    $reason  = trim($body['reason'] ?? '');

    if ($entryId <= 0 || $reason === '') {
        respond(400, ['status' => 'error', 'message' => 'A valid entry_id and a reason are required']);
    }

    $stmt = $pdo->prepare("SELECT batch_id FROM bonanza_entries WHERE id = :id AND is_winner = 1");
    $stmt->execute([':id' => $entryId]);
    $entry = $stmt->fetch();

    if (!$entry) {
        respond(404, ['status' => 'error', 'message' => 'Entry not found or is not currently a winner']);
    }

    $pdo->prepare("UPDATE bonanza_entries SET is_winner = 0, won_at = NULL WHERE id = :id")
        ->execute([':id' => $entryId]);

    $pdo->prepare(
        "INSERT INTO disqualifications (entry_id, batch_id, reason, disqualified_by)
         VALUES (:entry_id, :batch_id, :reason, :user_id)"
    )->execute([
        ':entry_id' => $entryId,
        ':batch_id' => $entry['batch_id'],
        ':reason'   => $reason,
        ':user_id'  => $_SESSION['user_id'] ?? null,
    ]);

    respond(200, ['status' => 'success', 'message' => 'Winner disqualified — the slot is now open for re-draw']);
}

function handle_toggle_verification(PDO $pdo, array $body): void
{
    $entryId = (int) ($body['entry_id'] ?? 0);

    if ($entryId <= 0) {
        respond(400, ['status' => 'error', 'message' => 'A valid entry_id is required']);
    }

    $stmt = $pdo->prepare("SELECT verification_status FROM bonanza_entries WHERE id = :id AND is_winner = 1");
    $stmt->execute([':id' => $entryId]);
    $entry = $stmt->fetch();

    if (!$entry) {
        respond(404, ['status' => 'error', 'message' => 'Winner not found']);
    }

    $newStatus = $entry['verification_status'] === 'verified' ? 'pending' : 'verified';
    $pdo->prepare("UPDATE bonanza_entries SET verification_status = :status WHERE id = :id")
        ->execute([':status' => $newStatus, ':id' => $entryId]);

    respond(200, ['status' => 'success', 'verification_status' => $newStatus]);
}
