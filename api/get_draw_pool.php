<?php
// api/get_draw_pool.php — returns non-winning entries across one or more draw batches
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';

check_access(['admin', 'draw_manager']);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $raw = $_GET['batch_ids'] ?? '';
    $batchIds = is_array($raw) ? $raw : array_filter(explode(',', (string) $raw), fn($v) => $v !== '');
} elseif ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $batchIds = $body['batch_ids'] ?? [];
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$batchIds = array_values(array_unique(array_filter(array_map('intval', (array) $batchIds), fn($id) => $id > 0)));

if (empty($batchIds)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'At least one batch_id is required']);
    exit;
}

// Accept SQL parameter arrays for the advanced pool filters. Each may arrive as a
// real JSON array or as a comma-separated string (GET). Blank values are dropped.
$bodyForFilters = isset($body) && is_array($body) ? $body : $_GET;
$filterValues = static function ($raw): array {
    if (!is_array($raw)) {
        $raw = $raw === null || $raw === '' ? [] : explode(',', (string) $raw);
    }
    return array_values(array_unique(array_filter(array_map('trim', array_map('strval', $raw)), fn($s) => $s !== '')));
};

$filterDefs = [
    ['include_districts', 'district', false],
    ['exclude_districts', 'district', true],
    ['include_cities',    'town',     false],
    ['exclude_cities',    'town',     true],
    ['include_dealers',   'dealer',   false],
    ['exclude_dealers',   'dealer',   true],
];

$placeholders = [];
$params = [];
foreach ($batchIds as $i => $id) {
    $key = ":bid{$i}";
    $placeholders[] = $key;
    $params[$key] = $id;
}

$filterSql = '';
foreach ($filterDefs as [$field, $column, $isExclude]) {
    $values = $filterValues($bodyForFilters[$field] ?? []);
    if (empty($values)) {
        continue;
    }
    $keys = [];
    foreach ($values as $j => $val) {
        $key = ":f_{$field}_{$j}";
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
$entries = $stmt->fetchAll();

// An entry with multiplier = N gets N slices/tokens in the pool, multiplying its odds.
$pool = [];
foreach ($entries as $e) {
    $token = [
        'id'         => (int) $e['id'],
        'label'      => $e['name'],
        'name'       => $e['name'],
        'phone'      => $e['phone'],
        'district'   => $e['district'],
        'town'       => $e['town'],
        'dealer'     => $e['dealer'],
        'batch_id'   => (int) $e['batch_id'],
        'batch_name' => $e['batch_name'],
        'multiplier' => (int) $e['multiplier'],
    ];
    $copies = max(1, (int) $e['multiplier']);
    for ($i = 0; $i < $copies; $i++) {
        $pool[] = $token;
    }
}

echo json_encode(['status' => 'success', 'count' => count($pool), 'pool' => $pool]);
