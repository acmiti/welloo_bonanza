<?php
// api/get_draw_winner.php — selects one winning entry from the eligible pool for a spin.
// If criteria (district/city/dealer) are supplied, the pool is first filtered to entries
// matching those criteria and the winner is drawn from that subset. If nothing matches the
// criteria, selection falls back to the full eligible pool so a spin never fails outright.
// Each entry contributes `multiplier` tokens to the pool, so array_rand() picking uniformly
// over tokens gives higher-multiplier entries a proportionally higher chance of winning.
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

// Advanced pool filters — keep the winner pool identical to the displayed wheel pool.
$filterValues = static function ($raw): array {
    if (!is_array($raw)) {
        $raw = $raw === null || $raw === '' ? [] : explode(',', (string) $raw);
    }
    return array_values(array_unique(array_filter(array_map('trim', array_map('strval', $raw)), fn($s) => $s !== '')));
};
// Two-tier pipeline: inclusion clauses build the base pool, exclusion clauses
// are then applied on top. Each row: [accepted request keys, column, isExclude].
$filterDefs = [
    [['inc_districts', 'include_districts'], 'district', false],
    [['inc_cities',    'include_cities'],    'town',     false],
    [['inc_dealers',   'include_dealers'],   'dealer',   false],
    [['exc_districts', 'exclude_districts'], 'district', true],
    [['exc_cities',    'exclude_cities'],    'town',     true],
    [['exc_dealers',   'exclude_dealers'],   'dealer',   true],
];

$placeholders = [];
$params = [];
foreach ($batchIds as $i => $id) {
    $key = ":bid{$i}";
    $placeholders[] = $key;
    $params[$key] = $id;
}

$filterSql = '';
foreach ($filterDefs as [$reqKeys, $column, $isExclude]) {
    $values = [];
    foreach ((array) $reqKeys as $rk) {
        if (isset($body[$rk])) {
            $values = array_merge($values, $filterValues($body[$rk]));
        }
    }
    $values = array_values(array_unique($values));
    if (empty($values)) {
        continue;
    }
    $tag = is_array($reqKeys) ? $reqKeys[0] : $reqKeys;
    $keys = [];
    foreach ($values as $j => $val) {
        $key = ":f_{$tag}_{$j}";
        $keys[] = $key;
        $params[$key] = $val;
    }
    $filterSql .= " AND e.{$column} " . ($isExclude ? 'NOT IN' : 'IN') . " (" . implode(', ', $keys) . ")";
}

$sql = "SELECT e.id, e.name, e.phone, e.district, e.town, e.dealer, e.language, e.batch_id, e.multiplier, b.batch_name
        FROM bonanza_entries e
        JOIN draw_batches b ON b.id = e.batch_id
        WHERE e.batch_id IN (" . implode(', ', $placeholders) . ")
          AND e.is_winner = 0"
        . $filterSql . "
        ORDER BY e.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if (empty($rows)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'No eligible entries remain in this pool']);
    exit;
}

// Expand each entry into `multiplier` tokens so a uniform random pick over the
// pool weights higher-multiplier entries proportionally more likely to win.
$pool = [];
foreach ($rows as $row) {
    $copies = max(1, (int) $row['multiplier']);
    for ($i = 0; $i < $copies; $i++) {
        $pool[] = $row;
    }
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
    'multiplier' => (int) $winnerRow['multiplier'],
];

echo json_encode([
    'status'           => 'success',
    'winner'           => $winner,
    'used_criteria'    => $hasCriteria,
    'matched_criteria' => $matchedCriteria,
    'candidate_count'  => count($candidates),
]);
