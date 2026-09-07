<?php
// includes/draw_schedule.php — shared helpers for the Draw Scheduling & Auto-Rollover
// system (see database/06_create_draw_schedules.sql).
//
// The rollover is evaluated lazily: whichever page/endpoint touches the schedule
// queue first calls draw_schedule_rollover() and the DB state catches up to the
// current Asia/Colombo clock. config/db.php has already pinned PHP + the MySQL
// session to +05:30, so NOW() below is Sri Lanka time.

require_once __DIR__ . '/../config/db.php';

/**
 * Advance the schedule queue to match the current server time (Asia/Colombo):
 *   1. Any 'active' or 'scheduled' draw whose cutoff_time has elapsed -> 'completed'.
 *   2. If nothing is left 'active', promote the earliest 'scheduled' draw whose
 *      start_time <= NOW() (and whose cutoff is still in the future) -> 'active'.
 *
 * Safe to call on every request; it only writes when a boundary was crossed.
 */
function draw_schedule_rollover(PDO $pdo): void
{
    // 1. Close out every draw whose window has already ended. This covers a past
    //    'active' draw as well as any 'scheduled' draw that was never promoted
    //    because its whole window elapsed while another draw was running.
    $pdo->exec(
        "UPDATE draw_schedules
            SET status = 'completed'
          WHERE status IN ('active', 'scheduled')
            AND cutoff_time <= NOW()"
    );

    // 2. Promote the next due draw, but only if no draw is currently active.
    $hasActive = (int) $pdo->query(
        "SELECT COUNT(*) FROM draw_schedules WHERE status = 'active'"
    )->fetchColumn();

    if ($hasActive === 0) {
        $next = $pdo->query(
            "SELECT id
               FROM draw_schedules
              WHERE status = 'scheduled'
                AND start_time <= NOW()
                AND cutoff_time > NOW()
              ORDER BY start_time ASC, id ASC
              LIMIT 1"
        )->fetch();

        if ($next) {
            $stmt = $pdo->prepare("UPDATE draw_schedules SET status = 'active' WHERE id = :id");
            $stmt->execute([':id' => (int) $next['id']]);
        }
    }
}

/**
 * Decode a stored filter_rules JSON blob into the array shape the draw pool APIs
 * expect. Unknown / malformed input degrades to an empty ruleset.
 */
function draw_schedule_normalize_filters($raw): array
{
    $keys = ['inc_districts', 'inc_cities', 'inc_dealers', 'exc_districts', 'exc_cities', 'exc_dealers'];
    $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
    $out = [];
    if (is_array($decoded)) {
        foreach ($keys as $k) {
            $vals = $decoded[$k] ?? [];
            $out[$k] = is_array($vals)
                ? array_values(array_filter(array_map('strval', $vals), fn($s) => trim($s) !== ''))
                : [];
        }
    } else {
        foreach ($keys as $k) {
            $out[$k] = [];
        }
    }
    return $out;
}

/**
 * Shape a raw draw_schedules row for JSON output: adds explicit +05:30 ISO
 * strings and a human-readable cut-off label, and decodes filter_rules.
 */
function draw_schedule_present(array $row): array
{
    $startTs  = strtotime($row['start_time']);
    $cutoffTs = strtotime($row['cutoff_time']);

    return [
        'id'                    => (int) $row['id'],
        'title'                 => $row['title'],
        'status'                => $row['status'],
        'start_time'            => $row['start_time'],
        'start_time_iso'        => $startTs !== false ? date('c', $startTs) : null,
        'cutoff_time'           => $row['cutoff_time'],
        'cutoff_time_iso'       => $cutoffTs !== false ? date('c', $cutoffTs) : null,
        'cutoff_time_formatted' => $cutoffTs !== false ? date('F j, Y \a\t g:i A', $cutoffTs) : null,
        'filter_rules'          => draw_schedule_normalize_filters($row['filter_rules'] ?? null),
    ];
}
