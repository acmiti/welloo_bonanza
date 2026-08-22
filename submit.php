<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

// Connect via central database configuration file
require_once __DIR__ . '/api/db.php';

$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true) ?: $_POST;

$name     = trim($data['name'] ?? '');
$phone    = trim($data['phone'] ?? '');
$district = trim($data['district'] ?? '');
$town     = trim($data['town'] ?? '');
$dealer   = trim($data['dealer'] ?? '');
$language = strtoupper(trim($data['lang'] ?? 'SI'));

if (empty($name) || empty($phone) || empty($district) || empty($town) || empty($dealer)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing data']);
    exit;
}

/**
 * Returns the draw batch this entry should be filed under.
 * Rolls over to the next draft batch — or creates a fresh weekly batch —
 * whenever the currently active batch's deadline has passed.
 * Must be called inside an open transaction; the row locks it takes
 * (FOR UPDATE) are what keep concurrent submissions from racing each
 * other into creating duplicate batches.
 */
function resolve_active_batch(PDO $pdo): array
{
    $active = $pdo->query(
        "SELECT * FROM draw_batches WHERE status = 'active' ORDER BY id DESC LIMIT 1 FOR UPDATE"
    )->fetch();

    if ($active && strtotime($active['entry_deadline']) >= time()) {
        return $active;
    }

    // Deadline passed (or no active batch exists) — lock the stale batch.
    if ($active) {
        $pdo->prepare("UPDATE draw_batches SET status = 'locked' WHERE id = :id")
            ->execute([':id' => $active['id']]);
    }

    // Promote the next upcoming draft batch, if one exists and hasn't itself expired.
    $next = $pdo->prepare(
        "SELECT * FROM draw_batches
         WHERE status = 'draft' AND entry_deadline >= NOW()
         ORDER BY entry_deadline ASC, id ASC
         LIMIT 1 FOR UPDATE"
    );
    $next->execute();
    $next = $next->fetch();

    if ($next) {
        $pdo->prepare("UPDATE draw_batches SET status = 'active' WHERE id = :id")
            ->execute([':id' => $next['id']]);
        $next['status'] = 'active';
        return $next;
    }

    // No usable batch left — dynamically create the next one.
    $weekNumber = ((int) $pdo->query("SELECT COUNT(*) FROM draw_batches")->fetchColumn()) + 1;
    $batchName  = "Week {$weekNumber} Draw";

    $insert = $pdo->prepare(
        "INSERT INTO draw_batches (batch_name, entry_start_time, entry_deadline, draw_datetime, status)
         VALUES (:name, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 7 DAY), 'active')"
    );
    $insert->execute([':name' => $batchName]);
    $newId = (int) $pdo->lastInsertId();

    $fetch = $pdo->prepare("SELECT * FROM draw_batches WHERE id = :id");
    $fetch->execute([':id' => $newId]);
    return $fetch->fetch();
}

try {
    $pdo->beginTransaction();

    $batch = resolve_active_batch($pdo);

    $stmt = $pdo->prepare(
        "INSERT INTO bonanza_entries (name, phone, district, town, dealer, language, batch_id)
         VALUES (:name, :phone, :district, :town, :dealer, :language, :batch_id)"
    );
    $stmt->execute([
        ':name'     => $name,
        ':phone'    => $phone,
        ':district' => $district,
        ':town'     => $town,
        ':dealer'   => $dealer,
        ':language' => $language,
        ':batch_id' => $batch['id'],
    ]);

    $pdo->commit();

    echo json_encode([
        'status'        => 'success',
        'message'       => 'Entry recorded successfully',
        'batch_id'      => (int) $batch['id'],
        'batch_name'    => $batch['batch_name'],
        'draw_datetime' => $batch['draw_datetime'],
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
