<?php
// api/export_winners.php — CSV export of winners, optionally filtered by batch
require_once __DIR__ . '/../includes/auth.php';

check_access(['admin']);

$batchId = (int) ($_GET['batch_id'] ?? 0);

$sql = "SELECT e.id, e.name, e.phone, e.district, e.town, e.dealer, e.language,
               b.batch_name, e.verification_status, e.won_at, e.created_at
        FROM bonanza_entries e
        JOIN draw_batches b ON b.id = e.batch_id
        WHERE e.is_winner = 1";
$params = [];
if ($batchId > 0) {
    $sql .= " AND e.batch_id = :batch_id";
    $params[':batch_id'] = $batchId;
}
$sql .= " ORDER BY b.id DESC, e.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filenameSuffix = $batchId > 0 ? "_batch{$batchId}" : '_all';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=welloo_winners' . $filenameSuffix . '_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel support

fputcsv($output, ['ID', 'Name', 'Phone', 'District', 'Town', 'Dealer', 'Language', 'Batch', 'Verification Status', 'Won At', 'Registered At']);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['id'],
        $row['name'],
        $row['phone'],
        $row['district'],
        $row['town'],
        $row['dealer'],
        $row['language'],
        $row['batch_name'],
        $row['verification_status'],
        $row['won_at'],
        $row['created_at'],
    ]);
}

fclose($output);
