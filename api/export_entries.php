<?php
// api/export_entries.php — CSV export of entries, optionally filtered by batch
require_once __DIR__ . '/../includes/auth.php';

check_access(['admin']);

$batchId = (int) ($_GET['batch_id'] ?? 0);

$sql = "SELECT e.id, e.name, e.phone, e.district, e.town, e.dealer,
               e.language, b.batch_name, e.is_winner, e.verification_status, e.created_at
        FROM bonanza_entries e
        LEFT JOIN draw_batches b ON b.id = e.batch_id";
$params = [];
if ($batchId > 0) {
    $sql .= " WHERE e.batch_id = :batch_id";
    $params[':batch_id'] = $batchId;
}
$sql .= " ORDER BY e.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filenameSuffix = $batchId > 0 ? "_batch{$batchId}" : '_all';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=welloo_entries' . $filenameSuffix . '_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel support

fputcsv($output, [
    'ID', 'Name', 'WhatsApp Number', 'District', 'City/Town', 'Hardware Store/Dealer',
    'Language', 'Winner Status', 'Batch Name', 'Verification Status', 'Submission Date',
]);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['id'],
        $row['name'],
        $row['phone'],
        $row['district'],
        $row['town'],
        $row['dealer'],
        $row['language'],
        $row['is_winner'] ? 'Winner' : 'Not Winner',
        $row['batch_name'] ?? '',
        $row['verification_status'],
        $row['created_at'],
    ]);
}

fclose($output);
