<?php
// api/get_current_batch.php — public endpoint powering the landing-page countdown/lockout.
// Intentionally no auth: the public entry page needs this to render for anonymous visitors.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

$stmt = $pdo->query(
    "SELECT batch_name, entry_deadline, draw_datetime
     FROM draw_batches
     WHERE status = 'active'
     ORDER BY id DESC
     LIMIT 1"
);
$batch = $stmt->fetch();

if ($batch) {
    // Stored datetimes are Asia/Colombo wall-clock. Emit them as explicit
    // +05:30 ISO 8601 strings so the client parses an unambiguous instant
    // regardless of the visitor's device timezone.
    $deadlineTs = strtotime($batch['entry_deadline']);
    $drawTs     = strtotime($batch['draw_datetime']);
    $batch['entry_deadline_formatted'] = date('F j, Y \a\t g:i A', $deadlineTs);
    $batch['entry_deadline_iso']       = date('c', $deadlineTs);
    $batch['draw_datetime_iso']        = $drawTs !== false ? date('c', $drawTs) : null;
}

echo json_encode([
    'status' => 'success',
    'batch'  => $batch ?: null,
]);
