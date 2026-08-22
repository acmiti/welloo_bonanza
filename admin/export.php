<?php
require_once __DIR__ . '/../api/db.php';

try {
    $stmt = $pdo->query("SELECT id, name, phone, district, town, dealer, language, created_at FROM bonanza_entries ORDER BY id DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=welloo_bonanza_leads_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel support

    fputcsv($output, ['ID', 'Full Name', 'Phone Number', 'District', 'City/Town', 'Hardware Dealer', 'Language', 'Registration Date']);

    foreach ($rows as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}