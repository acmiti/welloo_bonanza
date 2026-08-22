<?php
require_once __DIR__ . '/../api/db.php';

// Fetch Summary Metrics
$totalLeads = $pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
$todayLeads = $pdo->query("SELECT COUNT(*) FROM registrations WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// Fetch Language Distribution
$langStmt = $pdo->query("SELECT language_preference, COUNT(*) as count FROM registrations GROUP BY language_preference");
$langCounts = $langStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch Top Districts
$districtStmt = $pdo->query("SELECT district, COUNT(*) as count FROM registrations GROUP BY district ORDER BY count DESC LIMIT 3");
$topDistricts = $districtStmt->fetchAll();

// Fetch Registrations List
$search = trim($_GET['q'] ?? '');
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE full_name LIKE :q1 OR phone_number LIKE :q2 OR district LIKE :q3 ORDER BY id DESC LIMIT 100");
    $stmt->execute([':q1' => "%$search%", ':q2' => "%$search%", ':q3' => "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM registrations ORDER BY id DESC LIMIT 100");
}
$registrations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welloo Bonanza | Admin Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, sans-serif; }
        body { background: #0F0F0F; color: #FFF; padding: 20px; }
        .wrapper { max-width: 1100px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #222; }
        h1 { color: #FF6600; font-size: 24px; }
        .btn-export { background: #FF6600; color: #000; text-decoration: none; padding: 10px 18px; font-weight: bold; border-radius: 6px; font-size: 14px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .card { background: #1A1A1A; border: 1px solid #333; padding: 18px; border-radius: 10px; }
        .card h3 { color: #888; font-size: 12px; text-transform: uppercase; margin-bottom: 8px; }
        .card .num { font-size: 28px; font-weight: bold; color: #FF9900; }
        .search-box { margin-bottom: 16px; display: flex; gap: 10px; }
        .search-box input { flex: 1; padding: 10px 14px; background: #141414; border: 1px solid #333; border-radius: 6px; color: #FFF; outline: none; }
        .search-box button { background: #333; color: #FFF; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: #1A1A1A; border-radius: 10px; overflow: hidden; border: 1px solid #333; }
        th, td { padding: 12px 14px; text-align: left; font-size: 13px; border-bottom: 1px solid #2A2A2A; }
        th { background: #222; color: #AAA; text-transform: uppercase; font-size: 11px; }
        tr:hover { background: #222; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; background: #333; color: #FF9900; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Welloo Bonanza Leads Dashboard</h1>
        <a href="export.php" class="btn-export">📥 Export CSV</a>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="card">
            <h3>Total Entries</h3>
            <div class="num"><?= number_format($totalLeads) ?></div>
        </div>
        <div class="card">
            <h3>Registered Today</h3>
            <div class="num"><?= number_format($todayLeads) ?></div>
        </div>
        <div class="card">
            <h3>Languages</h3>
            <div style="font-size: 13px; color: #DDD; margin-top: 4px;">
                SI: <strong><?= $langCounts['SI'] ?? 0 ?></strong> | 
                TA: <strong><?= $langCounts['TA'] ?? 0 ?></strong> | 
                EN: <strong><?= $langCounts['EN'] ?? 0 ?></strong>
            </div>
        </div>
        <div class="card">
            <h3>Top District</h3>
            <div style="font-size: 16px; font-weight: bold; color: #FFF; margin-top: 4px;">
                <?= !empty($topDistricts) ? $topDistricts[0]['district'] . ' (' . $topDistricts[0]['count'] . ')' : 'N/A' ?>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <form class="search-box" method="GET">
        <input type="text" name="q" placeholder="Search by name, phone number, or district..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Search</button>
        <?php if(!empty($search)): ?>
            <a href="index.php" style="color: #AAA; align-self: center; font-size: 12px; text-decoration: none;">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Registrations Table -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>District</th>
                <th>City / Town</th>
                <th>Hardware Store</th>
                <th>Lang</th>
                <th>Source</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($registrations)): ?>
                <tr><td colspan="9" style="text-align: center; color: #777;">No registrations found.</td></tr>
            <?php else: ?>
                <?php foreach ($registrations as $row): ?>
                    <tr>
                        <td>#<?= $row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['full_name']) ?></strong></td>
                        <td><?= htmlspecialchars($row['phone_number']) ?></td>
                        <td><?= htmlspecialchars($row['district']) ?></td>
                        <td><?= htmlspecialchars($row['city_town']) ?></td>
                        <td><?= htmlspecialchars($row['hardware_dealer']) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($row['language_preference']) ?></span></td>
                        <td><?= htmlspecialchars($row['utm_source'] ?? 'Organic') ?></td>
                        <td><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>