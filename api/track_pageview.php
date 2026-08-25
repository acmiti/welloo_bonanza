<?php
// api/track_pageview.php — server-side Meta CAPI PageView event, called from index.html on load
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../includes/MetaCapi.php';

$data = json_decode(file_get_contents('php://input'), true) ?: [];

MetaCapi::sendEvent('PageView', [
    'fbp' => $data['fbp'] ?? null,
    'fbc' => $data['fbc'] ?? null,
], [], $data['url'] ?? null);

echo json_encode(['status' => 'ok']);
