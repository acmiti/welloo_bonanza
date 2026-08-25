<?php
// api/get_filter_options.php — cascading district/town/dealer options for the entries filter bar
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';

check_access(['admin', 'draw_manager', 'data_entry']);

$district = trim($_GET['district'] ?? '');
$town     = trim($_GET['town'] ?? '');

try {
    $districts = $pdo->query(
        "SELECT DISTINCT district FROM bonanza_entries WHERE district IS NOT NULL AND district <> '' ORDER BY district ASC"
    )->fetchAll(PDO::FETCH_COLUMN);

    $townSql = "SELECT DISTINCT town FROM bonanza_entries WHERE town IS NOT NULL AND town <> ''";
    $townParams = [];
    if ($district !== '') {
        $townSql .= " AND district = :district";
        $townParams[':district'] = $district;
    }
    $townSql .= " ORDER BY town ASC";
    $townStmt = $pdo->prepare($townSql);
    $townStmt->execute($townParams);
    $towns = $townStmt->fetchAll(PDO::FETCH_COLUMN);

    $dealerSql = "SELECT DISTINCT dealer FROM bonanza_entries WHERE dealer IS NOT NULL AND dealer <> ''";
    $dealerParams = [];
    if ($district !== '') {
        $dealerSql .= " AND district = :district";
        $dealerParams[':district'] = $district;
    }
    if ($town !== '') {
        $dealerSql .= " AND town = :town";
        $dealerParams[':town'] = $town;
    }
    $dealerSql .= " ORDER BY dealer ASC";
    $dealerStmt = $pdo->prepare($dealerSql);
    $dealerStmt->execute($dealerParams);
    $dealers = $dealerStmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'status'    => 'success',
        'districts' => $districts,
        'towns'     => $towns,
        'dealers'   => $dealers,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not load filter options: ' . $e->getMessage()]);
}
