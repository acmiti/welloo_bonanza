<?php
// api/record_winner.php — marks an entry as a winner, excluding it from future spins
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';

check_access(['admin', 'draw_manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$entryId = (int) ($body['entry_id'] ?? 0);

if ($entryId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'A valid entry_id is required']);
    exit;
}

// The "AND is_winner = 0" guard makes this atomic: if two draws race on the
// same entry, only the first UPDATE affects a row.
$update = $pdo->prepare("UPDATE bonanza_entries SET is_winner = 1 WHERE id = :id AND is_winner = 0");
$update->execute([':id' => $entryId]);

if ($update->rowCount() === 0) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => 'Entry not found or already recorded as a winner']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT e.id, e.name, e.phone, e.district, e.town, e.dealer, e.batch_id, b.batch_name
     FROM bonanza_entries e
     JOIN draw_batches b ON b.id = e.batch_id
     WHERE e.id = :id"
);
$stmt->execute([':id' => $entryId]);
$winner = $stmt->fetch();

echo json_encode(['status' => 'success', 'message' => 'Winner recorded', 'winner' => $winner]);
