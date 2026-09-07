<?php
// api/get_active_draw.php — public endpoint powering the auto-rolling countdown.
// Intentionally no auth: stage + public views poll this to know which draw is
// live and when it closes.
//
// On every call it first advances the schedule queue against the current
// Asia/Colombo server clock (completing elapsed draws, promoting the next due
// one), then returns the active draw plus the next one waiting in line.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/draw_schedule.php';

try {
    draw_schedule_rollover($pdo);

    $active = $pdo->query(
        "SELECT id, title, status, start_time, cutoff_time, filter_rules
           FROM draw_schedules
          WHERE status = 'active'
          ORDER BY cutoff_time ASC, id ASC
          LIMIT 1"
    )->fetch();

    $next = $pdo->query(
        "SELECT id, title, status, start_time, cutoff_time, filter_rules
           FROM draw_schedules
          WHERE status = 'scheduled'
            AND cutoff_time > NOW()
          ORDER BY start_time ASC, id ASC
          LIMIT 1"
    )->fetch();

    echo json_encode([
        'status'      => 'success',
        'server_time' => date('c'),
        'active_draw' => $active ? draw_schedule_present($active) : null,
        'next_draw'   => $next ? draw_schedule_present($next) : null,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to resolve active draw: ' . $e->getMessage()]);
}
