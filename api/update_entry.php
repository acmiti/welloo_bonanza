<?php
// api/update_entry.php — admin-only edit of an entry's contact/profile fields
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

$id       = (int) ($body['id'] ?? 0);
$name     = trim($body['name'] ?? '');
$phone    = trim($body['phone'] ?? '');
$district = trim($body['district'] ?? '');
$town     = trim($body['town'] ?? '');
$dealer   = trim($body['dealer'] ?? '');
$language = strtoupper(trim($body['language'] ?? ''));
$isWinner   = (int) ($body['is_winner'] ?? 0) === 1 ? 1 : 0;
$multiplier = (int) ($body['multiplier'] ?? 1);

if ($id <= 0 || $name === '' || $phone === '' || $district === '' || $town === '' || $dealer === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Name, phone, district, town, and dealer are required']);
    exit;
}

if ($multiplier < 1 || $multiplier > 100) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Multiplier must be an integer between 1 and 100']);
    exit;
}

if (!preg_match('/^(07[0-2,4-8]\d{7}|7[0-2,4-8]\d{7})$/', $phone)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid phone number']);
    exit;
}

if (!in_array($language, ['EN', 'SI', 'TA'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid language']);
    exit;
}

$existsStmt = $pdo->prepare("SELECT id, multiplier FROM bonanza_entries WHERE id = :id");
$existsStmt->execute([':id' => $id]);
$existingEntry = $existsStmt->fetch();
if (!$existingEntry) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Entry not found']);
    exit;
}

$stmt = $pdo->prepare(
    "UPDATE bonanza_entries
     SET name = :name, phone = :phone, district = :district, town = :town, dealer = :dealer,
         language = :language, is_winner = :is_winner, multiplier = :multiplier
     WHERE id = :id"
);
$stmt->execute([
    ':name'       => $name,
    ':phone'      => $phone,
    ':district'   => $district,
    ':town'       => $town,
    ':dealer'     => $dealer,
    ':language'   => $language,
    ':is_winner'  => $isWinner,
    ':multiplier' => $multiplier,
    ':id'         => $id,
]);

if ((int) $existingEntry['multiplier'] !== $multiplier) {
    MetaCapi::sendEvent('MultiplierGranted', [
        'ph'          => $phone,
        'external_id' => $phone,
    ], [
        'multiplier' => $multiplier,
    ]);
}

echo json_encode(['status' => 'success', 'message' => 'Entry updated']);
