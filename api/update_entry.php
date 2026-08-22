<?php
// api/update_entry.php — admin-only edit of an entry's contact/profile fields
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';

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
$email    = trim($body['email'] ?? '');
$invoice  = trim($body['invoice_number'] ?? '');
$district = trim($body['district'] ?? '');
$town     = trim($body['town'] ?? '');
$dealer   = trim($body['dealer'] ?? '');

if ($id <= 0 || $name === '' || $phone === '' || $district === '' || $town === '' || $dealer === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Name, phone, district, town, and dealer are required']);
    exit;
}

if (!preg_match('/^(07[0-2,4-8]\d{7}|7[0-2,4-8]\d{7})$/', $phone)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid phone number']);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid email address']);
    exit;
}

$existsStmt = $pdo->prepare("SELECT id FROM bonanza_entries WHERE id = :id");
$existsStmt->execute([':id' => $id]);
if (!$existsStmt->fetch()) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Entry not found']);
    exit;
}

$stmt = $pdo->prepare(
    "UPDATE bonanza_entries
     SET name = :name, phone = :phone, email = :email, invoice_number = :invoice_number,
         district = :district, town = :town, dealer = :dealer
     WHERE id = :id"
);
$stmt->execute([
    ':name'           => $name,
    ':phone'          => $phone,
    ':email'          => $email !== '' ? $email : null,
    ':invoice_number' => $invoice !== '' ? $invoice : null,
    ':district'       => $district,
    ':town'           => $town,
    ':dealer'         => $dealer,
    ':id'             => $id,
]);

echo json_encode(['status' => 'success', 'message' => 'Entry updated']);
