<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/db.php';

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true) ?: $_POST;

$full_name = trim($data['name'] ?? '');
$phone_number = trim($data['phone'] ?? '');
$district = trim($data['district'] ?? '');
$city_town = trim($data['town'] ?? '');
$hardware_dealer = trim($data['dealer'] ?? '');
$language = strtoupper(trim($data['lang'] ?? 'SI'));

if (empty($full_name) || empty($phone_number) || empty($district) || empty($city_town) || empty($hardware_dealer)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

if (!preg_match('/^(07[0-2,4-8]\d{7}|7[0-2,4-8]\d{7})$/', $phone_number)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Phone Number']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO bonanza_entries 
        (name, phone, district, town, dealer, language) 
        VALUES (:name, :phone, :district, :town, :dealer, :lang)");
    
    $stmt->execute([
        ':name'     => $full_name,
        ':phone'    => $phone_number,
        ':district' => $district,
        ':town'     => $city_town,
        ':dealer'   => $hardware_dealer,
        ':lang'     => $language
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Registration saved successfully']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}