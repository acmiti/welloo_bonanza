<?php
require_once __DIR__ . '/../includes/auth.php';

check_access(['admin', 'draw_manager', 'data_entry']);

$isAdmin = $_SESSION['role'] === 'admin';

$batchFilter    = (int) ($_GET['batch_id'] ?? 0);
$searchFilter   = trim($_GET['search'] ?? '');
$districtFilter = trim($_GET['district'] ?? '');
$townFilter     = trim($_GET['town'] ?? '');
$dealerFilter   = trim($_GET['dealer'] ?? '');

$loadError = null;
$batchList = [];
$entries = [];
$districtOptions = [];
$townOptions = [];
$dealerOptions = [];

try {
    $batchListStmt = $pdo->query("SELECT id, batch_name FROM draw_batches ORDER BY id DESC");
    $batchList = $batchListStmt->fetchAll();

    $districtOptions = $pdo->query("SELECT DISTINCT district FROM bonanza_entries WHERE district IS NOT NULL AND district <> '' ORDER BY district ASC")->fetchAll(PDO::FETCH_COLUMN);
    $townOptions     = $pdo->query("SELECT DISTINCT town FROM bonanza_entries WHERE town IS NOT NULL AND town <> '' ORDER BY town ASC")->fetchAll(PDO::FETCH_COLUMN);
    $dealerOptions   = $pdo->query("SELECT DISTINCT dealer FROM bonanza_entries WHERE dealer IS NOT NULL AND dealer <> '' ORDER BY dealer ASC")->fetchAll(PDO::FETCH_COLUMN);

    $sql = "SELECT e.id, e.name, e.phone, e.district, e.town, e.dealer,
                   e.language, e.is_winner, e.batch_id, e.created_at, b.batch_name
            FROM bonanza_entries e
            LEFT JOIN draw_batches b ON b.id = e.batch_id";
    $where = [];
    $params = [];
    if ($batchFilter > 0) {
        $where[] = "e.batch_id = :batch_id";
        $params[':batch_id'] = $batchFilter;
    }
    if ($searchFilter !== '') {
        $where[] = "(e.name LIKE :search OR e.phone LIKE :search)";
        $params[':search'] = '%' . $searchFilter . '%';
    }
    if ($districtFilter !== '') {
        $where[] = "e.district = :district";
        $params[':district'] = $districtFilter;
    }
    if ($townFilter !== '') {
        $where[] = "e.town = :town";
        $params[':town'] = $townFilter;
    }
    if ($dealerFilter !== '') {
        $where[] = "e.dealer = :dealer";
        $params[':dealer'] = $dealerFilter;
    }
    if ($where) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= " ORDER BY e.id DESC LIMIT 200";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll();
} catch (PDOException $e) {
    $loadError = 'Could not load entries: ' . $e->getMessage();
}

$exportParams = array_filter([
    'batch_id' => $batchFilter > 0 ? $batchFilter : null,
    'search'   => $searchFilter !== '' ? $searchFilter : null,
    'district' => $districtFilter !== '' ? $districtFilter : null,
    'town'     => $townFilter !== '' ? $townFilter : null,
    'dealer'   => $dealerFilter !== '' ? $dealerFilter : null,
], fn($v) => $v !== null);
$exportUrl = '/api/export_entries.php' . ($exportParams ? '?' . http_build_query($exportParams) : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entries | Welloo Bonanza Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, sans-serif; }
        body { background: #0F0F0F; color: #FFF; padding: 20px; }
        .wrapper { max-width: 1250px; margin: 0 auto; padding-bottom: 70px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #222; flex-wrap: wrap; gap: 12px; }
        h1 { color: #FF6600; font-size: 24px; }
        .nav-links { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .btn { background: #FF6600; color: #000; border: none; padding: 10px 18px; font-weight: bold; border-radius: 6px; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-secondary { background: #333; color: #FFF; }
        .btn-danger { background: #FF3333; color: #FFF; }
        .btn-small { padding: 6px 10px; font-size: 11px; }
        select.batch-filter { background: #141414; border: 1px solid #333; color: #FFF; padding: 9px 12px; border-radius: 6px; font-size: 13px; }

        .toolbar { background: #1A1A1A; border: 1px solid #333; border-radius: 10px; padding: 16px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .toolbar input, .toolbar select { background: #141414; border: 1px solid #333; color: #FFF; padding: 9px 12px; border-radius: 6px; font-size: 13px; outline: none; }
        .toolbar input:focus, .toolbar select:focus { border-color: #FF6600; }
        .toolbar input#search-input { min-width: 220px; flex: 1 1 220px; }
        .toolbar select { min-width: 150px; }

        .stats-row { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; }
        .stat-card { background: #1A1A1A; border: 1px solid #333; border-radius: 10px; padding: 16px 20px; min-width: 160px; }
        .stat-card .stat-label { color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .stat-card .stat-value { color: #FF9900; font-size: 26px; font-weight: 700; }
        .chart-card { background: #1A1A1A; border: 1px solid #333; border-radius: 10px; padding: 16px 20px; flex: 1 1 320px; }
        .chart-card .stat-label { color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; }
        .chart-card .chart-wrap { position: relative; height: 220px; }

        table { width: 100%; border-collapse: collapse; background: #1A1A1A; border-radius: 10px; overflow: hidden; border: 1px solid #333; }
        th, td { padding: 11px 12px; text-align: left; font-size: 12.5px; border-bottom: 1px solid #2A2A2A; white-space: nowrap; }
        th { background: #222; color: #AAA; text-transform: uppercase; font-size: 10.5px; }
        tr:hover { background: #202020; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-lang { background: #333; color: #FF9900; }
        .badge-winner { background: rgba(37, 211, 102, 0.15); color: #25D366; }
        .muted { color: #666; }
        .load-error { background: rgba(255, 51, 51, 0.1); border: 1px solid #FF3333; color: #FF6B6B; padding: 16px 18px; border-radius: 10px; font-size: 13px; line-height: 1.6; margin-bottom: 20px; }

        .row-actions { display: flex; gap: 6px; }
        .row-actions button { background: #262626; color: #DDD; border: 1px solid #3A3A3A; padding: 5px 9px; border-radius: 6px; font-size: 11px; cursor: pointer; }
        .row-actions button:hover { border-color: #FF6600; color: #FF9900; }
        .row-actions button.del-btn:hover { border-color: #FF3333; color: #FF6B6B; }

        #bulk-bar { position: fixed; bottom: 0; left: 0; width: 100%; background: #1A1A1A; border-top: 2px solid #FF6600; padding: 14px 20px; display: none; justify-content: center; align-items: center; gap: 18px; z-index: 250; box-shadow: 0 -4px 20px rgba(0,0,0,0.5); }
        #bulk-bar.show { display: flex; }
        #bulk-count { font-size: 13px; font-weight: 700; color: #DDD; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: none; justify-content: center; align-items: center; z-index: 300; padding: 20px; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: #1A1A1A; border: 1px solid #444; border-radius: 12px; max-width: 460px; width: 100%; padding: 24px; }
        .modal-box h2 { color: #FF9900; font-size: 17px; margin-bottom: 18px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
        .form-group label { font-size: 12px; color: #DDD; font-weight: 600; }
        .form-group input, .form-group select { padding: 10px 12px; background: #141414; border: 1px solid #333; border-radius: 6px; color: #FFF; font-size: 13.5px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: #FF6600; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
        .modal-error { display: none; background: rgba(255, 51, 51, 0.1); border: 1px solid #FF3333; color: #FF6B6B; font-size: 12px; font-weight: 600; padding: 8px 10px; border-radius: 6px; margin-bottom: 12px; }
        .modal-box p.confirm-text { font-size: 13.5px; color: #DDD; line-height: 1.5; margin-bottom: 6px; }

        #toast { position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%) translateY(80px); background: #1A1A1A; border: 1px solid #FF6600; border-radius: 8px; padding: 10px 18px; font-size: 13px; opacity: 0; transition: 0.3s ease; z-index: 300; }
        #toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>
<div class="wrapper">
    <div class="header">
        <h1>Entries</h1>
        <div class="nav-links">
            <a class="btn" id="export-btn" href="<?= htmlspecialchars($exportUrl) ?>">Export to CSV</a>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Today's Entries</div>
            <div class="stat-value" id="today-count">—</div>
        </div>
        <div class="chart-card">
            <div class="stat-label">New Entries Trend</div>
            <div class="chart-wrap"><canvas id="trend-chart"></canvas></div>
        </div>
    </div>

    <form id="filter-form" method="GET" class="toolbar">
        <input type="text" id="search-input" name="search" placeholder="Search by name or phone…" value="<?= htmlspecialchars($searchFilter) ?>">

        <select class="batch-filter" name="batch_id" onchange="document.getElementById('filter-form').submit()">
            <option value="0">All Batches</option>
            <?php foreach ($batchList as $b): ?>
                <option value="<?= (int) $b['id'] ?>" <?= $batchFilter === (int) $b['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b['batch_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="district" onchange="document.getElementById('filter-form').submit()">
            <option value="">All Districts</option>
            <?php foreach ($districtOptions as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= $districtFilter === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="town" onchange="document.getElementById('filter-form').submit()">
            <option value="">All Cities/Towns</option>
            <?php foreach ($townOptions as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= $townFilter === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="dealer" onchange="document.getElementById('filter-form').submit()">
            <option value="">All Dealers</option>
            <?php foreach ($dealerOptions as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= $dealerFilter === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="button" class="btn btn-secondary btn-small" onclick="window.location.href='entries.php'">Clear Filters</button>
    </form>

    <?php if ($loadError): ?>
        <div class="load-error"><?= htmlspecialchars($loadError) ?></div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <?php if ($isAdmin): ?><th><input type="checkbox" id="select-all" onchange="toggleSelectAll(this)"></th><?php endif; ?>
                <th>ID</th>
                <th>Name</th>
                <th>WhatsApp Number</th>
                <th>District</th>
                <th>City/Town</th>
                <th>Dealer</th>
                <th>Language</th>
                <th>Winner Status</th>
                <th>Batch</th>
                <th>Submission Date</th>
                <?php if ($isAdmin): ?><th>Actions</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($entries)): ?>
                <tr><td colspan="<?= $isAdmin ? 12 : 10 ?>" style="text-align:center; color:#777;">No entries found.</td></tr>
            <?php else: ?>
                <?php foreach ($entries as $e): ?>
                    <tr data-id="<?= (int) $e['id'] ?>">
                        <?php if ($isAdmin): ?>
                            <td><input type="checkbox" class="row-check" value="<?= (int) $e['id'] ?>" onchange="updateBulkBar()"></td>
                        <?php endif; ?>
                        <td>#<?= (int) $e['id'] ?></td>
                        <td><strong><?= htmlspecialchars($e['name']) ?></strong></td>
                        <td><?= htmlspecialchars($e['phone']) ?></td>
                        <td><?= htmlspecialchars($e['district']) ?></td>
                        <td><?= htmlspecialchars($e['town']) ?></td>
                        <td><?= htmlspecialchars($e['dealer']) ?></td>
                        <td><span class="badge badge-lang"><?= htmlspecialchars($e['language']) ?></span></td>
                        <td><?= $e['is_winner'] ? '<span class="badge badge-winner">Winner</span>' : '<span class="muted">—</span>' ?></td>
                        <td><?= htmlspecialchars($e['batch_name'] ?? '—') ?></td>
                        <td><?= date('M d, Y H:i', strtotime($e['created_at'])) ?></td>
                        <?php if ($isAdmin): ?>
                            <td class="row-actions">
                                <button onclick='openEditModal(<?= json_encode([
                                    "id" => (int) $e["id"],
                                    "name" => $e["name"],
                                    "phone" => $e["phone"],
                                    "district" => $e["district"],
                                    "town" => $e["town"],
                                    "dealer" => $e["dealer"],
                                    "language" => $e["language"],
                                    "is_winner" => (int) $e["is_winner"],
                                ]) ?>)'>Edit</button>
                                <button class="del-btn" onclick="openDeleteModal([<?= (int) $e['id'] ?>])">Delete</button>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php if ($isAdmin && !$loadError): ?>
<div id="bulk-bar">
    <span id="bulk-count">0 items selected</span>
    <button class="btn btn-danger" onclick="openDeleteModal(getSelectedIds())">Delete Selected</button>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h2>Edit Entry</h2>
        <div class="modal-error" id="edit-error"></div>
        <form id="editForm">
            <div class="form-group">
                <label>Name</label>
                <input type="text" id="edit-name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>WhatsApp Number</label>
                    <input type="text" id="edit-phone" required>
                </div>
                <div class="form-group">
                    <label>District</label>
                    <input type="text" id="edit-district" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>City / Town</label>
                    <input type="text" id="edit-town" required>
                </div>
                <div class="form-group">
                    <label>Hardware Store / Dealer</label>
                    <input type="text" id="edit-dealer" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Preferred Language</label>
                    <select id="edit-language">
                        <option value="EN">English</option>
                        <option value="SI">Sinhala</option>
                        <option value="TA">Tamil</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Winner Status</label>
                    <select id="edit-is-winner">
                        <option value="0">Not Winner</option>
                        <option value="1">Winner</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <h2>Confirm Delete</h2>
        <p class="confirm-text" id="delete-confirm-text">Are you sure you want to permanently delete these entries?</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete Permanently</button>
        </div>
    </div>
</div>

<div id="toast"></div>

<script>
    let pendingDeleteIds = [];

    function showToast(message) {
        const toast = document.getElementById('toast');
        toast.innerText = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2500);
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    async function callApi(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });
        return { ok: res.ok, data: await res.json() };
    }

    /* ---------- Selection / bulk bar ---------- */

    function toggleSelectAll(checkbox) {
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = checkbox.checked);
        updateBulkBar();
    }

    function getSelectedIds() {
        return [...document.querySelectorAll('.row-check:checked')].map(cb => parseInt(cb.value, 10));
    }

    function updateBulkBar() {
        const ids = getSelectedIds();
        const bar = document.getElementById('bulk-bar');
        document.getElementById('bulk-count').innerText = `${ids.length} item(s) selected`;
        bar.classList.toggle('show', ids.length > 0);

        const selectAll = document.getElementById('select-all');
        const allChecks = document.querySelectorAll('.row-check');
        selectAll.checked = allChecks.length > 0 && ids.length === allChecks.length;
    }

    /* ---------- Delete ---------- */

    function openDeleteModal(ids) {
        if (!ids || ids.length === 0) return;
        pendingDeleteIds = ids;
        document.getElementById('delete-confirm-text').innerText =
            `Are you sure you want to permanently delete these ${ids.length} entr${ids.length === 1 ? 'y' : 'ies'}?`;
        document.getElementById('deleteModal').classList.add('open');
    }

    async function confirmDelete() {
        const { ok, data } = await callApi('/api/delete_entries.php', { entry_ids: pendingDeleteIds });
        closeModal('deleteModal');

        if (!ok) {
            showToast(data.message || 'Failed to delete entries');
            return;
        }

        showToast(data.message || 'Entries deleted');
        setTimeout(() => window.location.reload(), 600);
    }

    /* ---------- Edit ---------- */

    let editEntryId = null;

    function openEditModal(entry) {
        editEntryId = entry.id;
        document.getElementById('edit-error').style.display = 'none';
        document.getElementById('edit-name').value = entry.name || '';
        document.getElementById('edit-phone').value = entry.phone || '';
        document.getElementById('edit-district').value = entry.district || '';
        document.getElementById('edit-town').value = entry.town || '';
        document.getElementById('edit-dealer').value = entry.dealer || '';
        document.getElementById('edit-language').value = entry.language || 'EN';
        document.getElementById('edit-is-winner').value = entry.is_winner ? '1' : '0';
        document.getElementById('editModal').classList.add('open');
    }

    document.getElementById('editForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const errorBox = document.getElementById('edit-error');
        errorBox.style.display = 'none';

        const { ok, data } = await callApi('/api/update_entry.php', {
            id: editEntryId,
            name: document.getElementById('edit-name').value.trim(),
            phone: document.getElementById('edit-phone').value.trim(),
            district: document.getElementById('edit-district').value.trim(),
            town: document.getElementById('edit-town').value.trim(),
            dealer: document.getElementById('edit-dealer').value.trim(),
            language: document.getElementById('edit-language').value,
            is_winner: document.getElementById('edit-is-winner').value
        });

        if (!ok) {
            errorBox.innerText = data.message || 'Failed to update entry';
            errorBox.style.display = 'block';
            return;
        }

        closeModal('editModal');
        showToast('Entry updated');
        setTimeout(() => window.location.reload(), 600);
    });
</script>
<?php endif; ?>

<script>
    /* ---------- Live search (debounced) ---------- */
    const searchInput = document.getElementById('search-input');
    let searchDebounce = null;
    searchInput.addEventListener('input', function () {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => document.getElementById('filter-form').submit(), 500);
    });

    /* ---------- Analytics: today's count + trend chart ---------- */
    (async function loadEntryStats() {
        const batchId = <?= $batchFilter > 0 ? $batchFilter : 0 ?>;
        const url = '/api/get_entry_stats.php' + (batchId > 0 ? `?batch_id=${batchId}` : '');

        try {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (data.status !== 'success') return;

            document.getElementById('today-count').innerText = data.today_count;

            const labels = data.trend.map(row => row.date);
            const counts = data.trend.map(row => row.count);

            new Chart(document.getElementById('trend-chart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Entries',
                        data: counts,
                        borderColor: '#FF6600',
                        backgroundColor: 'rgba(255, 102, 0, 0.15)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 3,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: '#888' }, grid: { color: '#2A2A2A' } },
                        y: { beginAtZero: true, ticks: { color: '#888', precision: 0 }, grid: { color: '#2A2A2A' } },
                    },
                },
            });
        } catch (err) {
            document.getElementById('today-count').innerText = '—';
        }
    })();
</script>
</body>
</html>
