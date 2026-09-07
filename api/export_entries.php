<?php
// api/export_entries.php — CSV export of entries, respecting active on-screen filters.
// A request with no filter params (e.g. the "Export All Entries" action) exports
// every row in bonanza_entries. Restricted strictly to the admin (super admin) role.
require_once __DIR__ . '/../includes/auth.php';

check_access(['admin']);

// All timestamps in this app are stored in Sri Lanka Standard Time (Asia/Colombo,
// UTC+5:30) — see config/db.php — so we only need to label them, not convert.
$SLST_LABEL = 'SLST';

$batchId  = (int) ($_GET['batch_id'] ?? 0);
$search   = trim($_GET['search'] ?? '');
$district = trim($_GET['district'] ?? '');
$town     = trim($_GET['town'] ?? '');
$dealer   = trim($_GET['dealer'] ?? '');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate   = trim($_GET['to_date'] ?? '');

// NIC/ID and Serial Number are not first-class columns on bonanza_entries. Pull
// them opportunistically so the export keeps working whether or not the optional
// migration that adds them has been run.
$entryColumns = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bonanza_entries'")
    ->fetchAll(PDO::FETCH_COLUMN);
$nicColumn    = null;
foreach (['nic', 'nic_number', 'national_id', 'id_number'] as $candidate) {
    if (in_array($candidate, $entryColumns, true)) { $nicColumn = $candidate; break; }
}
$serialColumn = null;
foreach (['serial_number', 'serial', 'invoice_number'] as $candidate) {
    if (in_array($candidate, $entryColumns, true)) { $serialColumn = $candidate; break; }
}

$nicSelect    = $nicColumn    ? "e.`{$nicColumn}`"    : "NULL";
$serialSelect = $serialColumn ? "e.`{$serialColumn}`" : "NULL";

$sql = "SELECT e.id, e.name, e.phone, {$nicSelect} AS nic, e.district, e.town, e.dealer,
               {$serialSelect} AS serial_number, e.created_at, e.is_winner, e.verification_status,
               b.batch_name
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
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM so Excel renders Sinhala/Tamil correctly

fputcsv($output, [
    'Entry ID',
    'Full Name',
    'Phone Number',
    'NIC/ID',
    'District',
    'City/Town',
    'Dealer',
    'Serial Number',
    'Submission Date & Time (SLST)',
    'Status',
]);

foreach ($rows as $row) {
    $submittedAt = $row['created_at']
        ? date('Y-m-d H:i:s', strtotime($row['created_at'])) . ' ' . $SLST_LABEL
        : '';

    if ($row['is_winner']) {
        $status = 'Winner';
    } else {
        $status = $row['verification_status'] !== null && $row['verification_status'] !== ''
            ? ucfirst($row['verification_status'])
            : '';
    }

    fputcsv($output, [
        $row['id'],
        $row['name'],
        $row['phone'],
        $row['nic'] ?? '',
        $row['district'],
        $row['town'],
        $row['dealer'],
        $row['serial_number'] ?? '',
        $submittedAt,
        $status,
    ]);
}

fclose($output);
