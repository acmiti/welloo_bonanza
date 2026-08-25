<?php
// api/export_entries.php — CSV export of entries, respecting active filters.
// A request with no filter params (e.g. the "Export All Entries" action) exports
// every row in bonanza_entries. Restricted strictly to the admin (super admin) role.
require_once __DIR__ . '/../includes/auth.php';

check_access(['admin']);

$batchId  = (int) ($_GET['batch_id'] ?? 0);
$search   = trim($_GET['search'] ?? '');
$district = trim($_GET['district'] ?? '');
$town     = trim($_GET['town'] ?? '');
$dealer   = trim($_GET['dealer'] ?? '');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate   = trim($_GET['to_date'] ?? '');

$sql = "SELECT e.id, e.name, e.phone, e.district, e.town, e.dealer,
               e.language, b.batch_name, e.is_winner, e.verification_status, e.created_at, e.multiplier
        FROM bonanza_entries e
        LEFT JOIN draw_batches b ON b.id = e.batch_id";
$where = [];
$params = [];

if ($batchId > 0) {
    $where[] = "e.batch_id = :batch_id";
    $params[':batch_id'] = $batchId;
}
if ($search !== '') {
    $where[] = "(e.name LIKE :search OR e.phone LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if ($district !== '') {
    $where[] = "e.district = :district";
    $params[':district'] = $district;
}
if ($town !== '') {
    $where[] = "e.town = :town";
    $params[':town'] = $town;
}
if ($dealer !== '') {
    $where[] = "e.dealer = :dealer";
    $params[':dealer'] = $dealer;
}
if ($fromDate !== '') {
    $where[] = "DATE(e.created_at) >= :from_date";
    $params[':from_date'] = $fromDate;
}
if ($toDate !== '') {
    $where[] = "DATE(e.created_at) <= :to_date";
    $params[':to_date'] = $toDate;
}

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
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
    'Language', 'Winner Status', 'Batch Name', 'Verification Status', 'Submission Date', 'Winning Chances (Multiplier)',
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
        $row['multiplier'] !== null && $row['multiplier'] !== '' ? $row['multiplier'] : 1,
    ]);
}

fclose($output);
