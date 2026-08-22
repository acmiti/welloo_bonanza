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

try {
    $sql = "INSERT INTO bonanza_entries (name, phone, district, town, dealer, language) 
            VALUES (:name, :phone, :district, :town, :dealer, :language)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name'     => $name,
        ':phone'    => $phone,
        ':district' => $district,
        ':town'     => $town,
        ':dealer'   => $dealer,
        ':language' => $language
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Entry recorded successfully']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}