<?php
// api/delete_entries.php — admin-only single/bulk delete of entries
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';

check_access(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$ids = [];
if (isset($body['entry_ids']) && is_array($body['entry_ids'])) {
    $ids = $body['entry_ids'];
} elseif (isset($body['id'])) {
    $ids = [$body['id']];
}

$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'At least one entry id is required']);
    exit;
}

$placeholders = [];
$params = [];
foreach ($ids as $i => $id) {
    $key = ":id{$i}";
    $placeholders[] = $key;
    $params[$key] = $id;
}
$inClause = implode(', ', $placeholders);

try {
    $pdo->beginTransaction();

    // Entries are the parent side of the disqualifications FK, so any log
    // rows for these entries have to go first or the DELETE below would fail.
    $pdo->prepare("DELETE FROM disqualifications WHERE entry_id IN ($inClause)")->execute($params);

    $stmt = $pdo->prepare("DELETE FROM bonanza_entries WHERE id IN ($inClause)");
    $stmt->execute($params);
    $deleted = $stmt->rowCount();

    $pdo->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => "Deleted {$deleted} " . ($deleted === 1 ? 'entry' : 'entries'),
        'deleted' => $deleted,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
