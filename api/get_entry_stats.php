<?php
// api/get_entry_stats.php — daily submission counts + top district/dealer for the entries analytics cards
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';

check_access(['admin', 'draw_manager', 'data_entry']);

$batchId  = (int) ($_GET['batch_id'] ?? 0);
$search   = trim($_GET['search'] ?? '');
$district = trim($_GET['district'] ?? '');
$town     = trim($_GET['town'] ?? '');
$dealer   = trim($_GET['dealer'] ?? '');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate   = trim($_GET['to_date'] ?? '');

try {
    if ($batchId <= 0) {
        $activeStmt = $pdo->query(
            "SELECT id FROM draw_batches WHERE status = 'active' ORDER BY id DESC LIMIT 1"
        );
        $active = $activeStmt->fetch();
        $batchId = $active ? (int) $active['id'] : 0;
    }

    $where = [];
    $params = [];
    if ($batchId > 0) {
        $where[] = "batch_id = :batch_id";
        $params[':batch_id'] = $batchId;
    }
    if ($search !== '') {
        $where[] = "(name LIKE :search OR phone LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    if ($district !== '') {
        $where[] = "district = :district";
        $params[':district'] = $district;
    }
    if ($town !== '') {
        $where[] = "town = :town";
        $params[':town'] = $town;
    }
    if ($dealer !== '') {
        $where[] = "dealer = :dealer";
        $params[':dealer'] = $dealer;
    }
    if ($fromDate !== '') {
        $where[] = "DATE(created_at) >= :from_date";
        $params[':from_date'] = $fromDate;
    }
    if ($toDate !== '') {
        $where[] = "DATE(created_at) <= :to_date";
        $params[':to_date'] = $toDate;
    }
    $whereClause = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $trendStmt = $pdo->prepare(
        "SELECT DATE(created_at) AS entry_date, COUNT(*) AS total
         FROM bonanza_entries" . $whereClause . "
         GROUP BY DATE(created_at)
         ORDER BY entry_date ASC"
    );
    $trendStmt->execute($params);
    $trend = $trendStmt->fetchAll();

    $todayWhere = $where;
    $todayParams = $params;
    $todayWhere[] = "DATE(created_at) = CURDATE()";
    $todaySql = "SELECT COUNT(*) AS total FROM bonanza_entries WHERE " . implode(' AND ', $todayWhere);
    $todayStmt = $pdo->prepare($todaySql);
    $todayStmt->execute($todayParams);
    $today = (int) $todayStmt->fetch()['total'];

    $topDistrictStmt = $pdo->prepare(
        "SELECT district, COUNT(*) AS total
         FROM bonanza_entries" . $whereClause . "
         " . ($where ? "AND" : "WHERE") . " district IS NOT NULL AND district <> ''
         GROUP BY district
         ORDER BY total DESC
         LIMIT 1"
    );
    $topDistrictStmt->execute($params);
    $topDistrictRow = $topDistrictStmt->fetch();
    $topDistrict = $topDistrictRow
        ? ['name' => $topDistrictRow['district'], 'count' => (int) $topDistrictRow['total']]
        : null;

    $topDealerStmt = $pdo->prepare(
        "SELECT dealer, COUNT(*) AS total
         FROM bonanza_entries" . $whereClause . "
         " . ($where ? "AND" : "WHERE") . " dealer IS NOT NULL AND dealer <> ''
         GROUP BY dealer
         ORDER BY total DESC
         LIMIT 1"
    );
    $topDealerStmt->execute($params);
    $topDealerRow = $topDealerStmt->fetch();
    $topDealer = $topDealerRow
        ? ['name' => $topDealerRow['dealer'], 'count' => (int) $topDealerRow['total']]
        : null;

    echo json_encode([
        'status'       => 'success',
        'batch_id'     => $batchId,
        'today_count'  => $today,
        'top_district' => $topDistrict,
        'top_dealer'   => $topDealer,
        'trend'        => array_map(function ($row) {
            return ['date' => $row['entry_date'], 'count' => (int) $row['total']];
        }, $trend),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not load entry stats: ' . $e->getMessage()]);
}
