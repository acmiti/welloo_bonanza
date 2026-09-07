<?php
// includes/draw_batch_rollover.php — shared helpers that keep the draw_batches
// queue in sync with the current Asia/Colombo clock.
//
// The standalone "Draw Schedule" queue (old draw_schedules table) has been
// merged into Draw Batches: draw_batches is now the single source of truth for
// both scheduling and batch management. The rollover is evaluated lazily —
// whichever page/endpoint touches the queue first calls draw_batch_rollover()
// and the DB catches up to the wall clock. config/db.php has already pinned PHP
// and the MySQL session to +05:30, so NOW() below is Sri Lanka time.

require_once __DIR__ . '/../config/db.php';

/**
 * Advance the batch queue to match the current server time (Asia/Colombo):
 *   1. Any 'active' batch whose entry_deadline has elapsed -> 'locked'
 *      (entries closed; the draw itself is still taken from Draw Manager).
 *   2. If nothing is left 'active', promote the newest 'draft' batch whose
 *      entry window currently contains NOW() (entry_start_time <= NOW() <
 *      entry_deadline) -> 'active', demoting any stale active batch first so the
 *      "one active batch" invariant that submit.php relies on is preserved.
 *
 * Safe to call on every request; it only writes when a boundary was crossed.
 * If the draw_batches table is missing or a query fails, this degrades to a
 * no-op rather than raising an unhandled fatal error.
 */
function draw_batch_rollover(PDO $pdo): void
{
    try {
        // 1. Close entries on any active batch whose deadline has passed.
        $pdo->exec(
            "UPDATE draw_batches
                SET status = 'locked'
              WHERE status = 'active'
                AND entry_deadline <= NOW()"
        );

        // 2. Promote the batch whose entry window is open right now, but only if
        //    no batch is currently active.
        $hasActive = (int) $pdo->query(
            "SELECT COUNT(*) FROM draw_batches WHERE status = 'active'"
        )->fetchColumn();

        if ($hasActive === 0) {
            $next = $pdo->query(
                "SELECT id
                   FROM draw_batches
                  WHERE status = 'draft'
                    AND entry_start_time <= NOW()
                    AND entry_deadline > NOW()
                  ORDER BY entry_start_time DESC, id DESC
                  LIMIT 1"
            )->fetch();

            if ($next) {
                $id = (int) $next['id'];
                $pdo->prepare(
                    "UPDATE draw_batches SET status = 'locked' WHERE status = 'active' AND id != :id"
                )->execute([':id' => $id]);
                $pdo->prepare(
                    "UPDATE draw_batches SET status = 'active' WHERE id = :id"
                )->execute([':id' => $id]);
            }
        }
    } catch (PDOException $e) {
        // Table missing or query failed — leave the queue untouched. Callers
        // fall back to a "no active batch" state.
        error_log('draw_batch_rollover: ' . $e->getMessage());
    }
}

/**
 * Decode an optional filter_rules blob into the array shape the draw pool APIs
 * expect. draw_batches does not currently persist filter presets, so this is
 * normally the empty ruleset — but it keeps every key present so callers can
 * rely on them, and transparently supports a future filter_rules column.
 */
function draw_batch_filter_presets($raw): array
{
    $keys = ['inc_districts', 'inc_cities', 'inc_dealers', 'exc_districts', 'exc_cities', 'exc_dealers'];
    $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
    $out = [];
    foreach ($keys as $k) {
        $vals = is_array($decoded) ? ($decoded[$k] ?? []) : [];
        $out[$k] = is_array($vals)
            ? array_values(array_filter(array_map('strval', $vals), fn($s) => trim($s) !== ''))
            : [];
    }
    return $out;
}

/**
 * Shape a raw draw_batches row for JSON output: adds explicit +05:30 ISO
 * strings and human-readable labels. Includes `title` / `cutoff_time*` aliases
 * so the existing auto-rolling countdown widgets keep working unchanged.
 */
function draw_batch_present(array $row): array
{
    try {
        $startTs    = strtotime($row['entry_start_time'] ?? '');
        $deadlineTs = strtotime($row['entry_deadline'] ?? '');
        $drawTs     = strtotime($row['draw_datetime'] ?? '');
        $deadlineIso = $deadlineTs !== false ? date('c', $deadlineTs) : null;
        $deadlineFmt = $deadlineTs !== false ? date('F j, Y \a\t g:i A', $deadlineTs) : null;

        return [
            'id'                       => (int) ($row['id'] ?? 0),
            'batch_name'               => $row['batch_name'] ?? '',
            'title'                    => $row['batch_name'] ?? '',
            'status'                   => $row['status'] ?? '',
            'entry_start_time'         => $row['entry_start_time'] ?? null,
            'entry_start_time_iso'     => $startTs !== false ? date('c', $startTs) : null,
            'entry_deadline'           => $row['entry_deadline'] ?? null,
            'entry_deadline_iso'       => $deadlineIso,
            'entry_deadline_formatted' => $deadlineFmt,
            'draw_datetime'            => $row['draw_datetime'] ?? null,
            'draw_datetime_iso'        => $drawTs !== false ? date('c', $drawTs) : null,
            // Aliases kept for the countdown JS which still speaks "cutoff_time".
            'cutoff_time'              => $row['entry_deadline'] ?? null,
            'cutoff_time_iso'          => $deadlineIso,
            'cutoff_time_formatted'    => $deadlineFmt,
            'filter_rules'             => draw_batch_filter_presets($row['filter_rules'] ?? null),
        ];
    } catch (PDOException $e) {
        error_log('draw_batch_present: ' . $e->getMessage());
        return [
            'id'                       => (int) ($row['id'] ?? 0),
            'batch_name'               => $row['batch_name'] ?? '',
            'title'                    => $row['batch_name'] ?? '',
            'status'                   => $row['status'] ?? '',
            'entry_start_time'         => null,
            'entry_start_time_iso'     => null,
            'entry_deadline'           => null,
            'entry_deadline_iso'       => null,
            'entry_deadline_formatted' => null,
            'draw_datetime'            => null,
            'draw_datetime_iso'        => null,
            'cutoff_time'              => null,
            'cutoff_time_iso'          => null,
            'cutoff_time_formatted'    => null,
            'filter_rules'             => draw_batch_filter_presets(null),
        ];
    }
}
