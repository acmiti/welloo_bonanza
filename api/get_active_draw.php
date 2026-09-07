<?php
// api/get_active_draw.php — public endpoint powering the auto-rolling countdown.
// Intentionally no auth: stage + public views poll this to know which batch is
// live and when entries close.
//
// On every call it first advances the batch queue against the current
// Asia/Colombo server clock (locking batches whose deadline elapsed, promoting
// the next batch whose entry window is open), then returns the active batch
// plus the next one waiting in line. Scheduling now lives entirely in
// Draw Batches — there is no separate draw_schedules table.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/draw_batch_rollover.php';

const ACTIVE_DRAW_COLUMNS =
    'id, batch_name, status, entry_start_time, entry_deadline, draw_datetime';

try {
    draw_batch_rollover($pdo);

    $active = $pdo->query(
        "SELECT " . ACTIVE_DRAW_COLUMNS . "
           FROM draw_batches
          WHERE status = 'active'
          ORDER BY id DESC
          LIMIT 1"
    )->fetch();

    $next = $pdo->query(
        "SELECT " . ACTIVE_DRAW_COLUMNS . "
           FROM draw_batches
          WHERE status = 'draft'
            AND entry_deadline > NOW()
          ORDER BY entry_start_time ASC, id ASC
          LIMIT 1"
    )->fetch();

    echo json_encode([
        'status'      => 'success',
        'server_time' => date('c'),
        'active_draw' => $active ? draw_batch_present($active) : null,
        'next_draw'   => $next ? draw_batch_present($next) : null,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to resolve active draw: ' . $e->getMessage()]);
}
