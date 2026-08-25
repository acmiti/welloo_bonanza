<?php
// api/get_draw_winner.php — selects one winning entry from the eligible pool for a spin.
// If criteria (district/city/dealer) are supplied, the pool is first filtered to entries
// matching those criteria and the winner is drawn from that subset. If nothing matches the
// criteria, selection falls back to the full eligible pool so a spin never fails outright.
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';

check_access(['admin', 'draw_manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$batchIds = array_values(array_unique(array_filter(array_map('intval', (array) ($body['batch_ids'] ?? [])), fn($id) => $id > 0)));

if (empty($batchIds)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'At least one batch_id is required']);
    exit;
}

$criteria = is_array($body['criteria'] ?? null) ? $body['criteria'] : [];
$targetDistrict = trim((string) ($criteria['district'] ?? ''));
$targetCity     = trim((string) ($criteria['city'] ?? ''));
$targetDealer   = trim((string) ($criteria['dealer'] ?? ''));

$placeholders = [];
$params = [];
foreach ($batchIds as $i => $id) {
    $key = ":bid{$i}";
    $placeholders[] = $key;
    $params[$key] = $id;
}

$sql = "SELECT e.id, e.name, e.phone, e.district, e.town, e.dealer, e.language, e.batch_id, b.batch_name
        FROM bonanza_entries e
        JOIN draw_batches b ON b.id = e.batch_id
        WHERE e.batch_id IN (" . implode(', ', $placeholders) . ")
          AND e.is_winner = 0
        ORDER BY e.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pool = $stmt->fetchAll();

if (empty($pool)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'No eligible entries remain in this pool']);
    exit;
}

$hasCriteria = $targetDistrict !== '' || $targetCity !== '' || $targetDealer !== '';
$matchedCriteria = false;
$candidates = $pool;

if ($hasCriteria) {
    $filtered = array_values(array_filter($pool, function (array $e) use ($targetDistrict, $targetCity, $targetDealer): bool {
        if ($targetDistrict !== '' && strcasecmp((string) $e['district'], $targetDistrict) !== 0) {
            return false;
        }
        if ($targetCity !== '' && strcasecmp((string) $e['town'], $targetCity) !== 0) {
            return false;
        }
        if ($targetDealer !== '' && strcasecmp((string) $e['dealer'], $targetDealer) !== 0) {
            return false;
        }
        return true;
    }));

    if (!empty($filtered)) {
        $candidates = $filtered;
        $matchedCriteria = true;
    }
}

$winnerRow = $candidates[array_rand($candidates)];

$winner = [
    'id'         => (int) $winnerRow['id'],
    'label'      => $winnerRow['name'],
    'name'       => $winnerRow['name'],
    'phone'      => $winnerRow['phone'],
    'district'   => $winnerRow['district'],
    'town'       => $winnerRow['town'],
    'dealer'     => $winnerRow['dealer'],
    'batch_id'   => (int) $winnerRow['batch_id'],
    'batch_name' => $winnerRow['batch_name'],
];

echo json_encode([
    'status'           => 'success',
    'winner'           => $winner,
    'used_criteria'    => $hasCriteria,
    'matched_criteria' => $matchedCriteria,
    'candidate_count'  => count($candidates),
]);
