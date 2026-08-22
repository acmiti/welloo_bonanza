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

echo json_encode([
    'status' => 'success',
    'batch'  => $batch ?: null,
]);
