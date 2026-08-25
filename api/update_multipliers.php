<?php
// api/update_multipliers.php — bulk-set the winning-chance multiplier for entries
// matched by phone number. Super admin (admin role) only.
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/MetaCapi.php';

check_access(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$multiplier = (int) ($body['multiplier'] ?? 0);
if ($multiplier < 1 || $multiplier > 100) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Multiplier must be between 1 and 100']);
    exit;
}

$rawNumbers = (string) ($body['phones'] ?? '');
$lines = preg_split('/[\r\n,]+/', $rawNumbers) ?: [];

$phones = [];
foreach ($lines as $line) {
    $cleaned = preg_replace('/[^0-9+]/', '', trim($line));
    if ($cleaned !== '') {
        $phones[] = $cleaned;
    }
}
$phones = array_values(array_unique($phones));

if (empty($phones)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'At least one mobile number is required']);
    exit;
}

$placeholders = [];
$params = [':multiplier' => $multiplier];
foreach ($phones as $i => $phone) {
    $key = ":p{$i}";
    $placeholders[] = $key;
    $params[$key] = $phone;
}

$sql = "UPDATE bonanza_entries SET multiplier = :multiplier WHERE phone IN (" . implode(', ', $placeholders) . ")";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$updated = $stmt->rowCount();

if ($updated > 0) {
    foreach ($phones as $phone) {
        MetaCapi::sendEvent('MultiplierGranted', [
            'ph'          => $phone,
            'external_id' => $phone,
        ], [
            'multiplier' => $multiplier,
        ]);
    }
}

echo json_encode([
    'status'  => 'success',
    'updated' => $updated,
    'message' => "Successfully updated {$updated} " . ($updated === 1 ? 'entry' : 'entries') . " to {$multiplier}x multiplier",
]);
