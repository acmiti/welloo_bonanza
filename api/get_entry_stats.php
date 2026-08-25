<?php
// api/get_entry_stats.php — daily submission counts for the entries analytics chart
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';

check_access(['admin', 'draw_manager', 'data_entry']);

$batchId = (int) ($_GET['batch_id'] ?? 0);

try {
    if ($batchId <= 0) {
        $activeStmt = $pdo->query(
            "SELECT id FROM draw_batches WHERE status = 'active' ORDER BY id DESC LIMIT 1"
        );
        $active = $activeStmt->fetch();
        $batchId = $active ? (int) $active['id'] : 0;
    }

    $params = [];
    $batchClause = '';
    if ($batchId > 0) {
        $batchClause = ' WHERE batch_id = :batch_id';
        $params[':batch_id'] = $batchId;
    }

    $trendStmt = $pdo->prepare(
        "SELECT DATE(created_at) AS entry_date, COUNT(*) AS total
         FROM bonanza_entries" . $batchClause . "
         GROUP BY DATE(created_at)
         ORDER BY entry_date ASC"
    );
    $trendStmt->execute($params);
    $trend = $trendStmt->fetchAll();

    $todayParams = $params;
    $todaySql = "SELECT COUNT(*) AS total FROM bonanza_entries WHERE DATE(created_at) = CURDATE()";
    if ($batchId > 0) {
        $todaySql .= " AND batch_id = :batch_id";
    }
    $todayStmt = $pdo->prepare($todaySql);
    $todayStmt->execute($todayParams);
    $today = (int) $todayStmt->fetch()['total'];

    echo json_encode([
        'status'       => 'success',
        'batch_id'     => $batchId,
        'today_count'  => $today,
        'trend'        => array_map(function ($row) {
            return ['date' => $row['entry_date'], 'count' => (int) $row['total']];
        }, $trend),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not load entry stats: ' . $e->getMessage()]);
}
