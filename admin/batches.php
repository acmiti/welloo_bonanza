<?php
require_once __DIR__ . '/../includes/auth.php';

check_access(['admin']);

$stmt = $pdo->query(
    "SELECT b.id, b.batch_name, b.entry_start_time, b.entry_deadline, b.draw_datetime,
            b.status, b.created_at,
            COUNT(e.id) AS entry_count
     FROM draw_batches b
     LEFT JOIN bonanza_entries e ON e.batch_id = b.id
     GROUP BY b.id
     ORDER BY b.id DESC"
);
$batches = $stmt->fetchAll();

$statuses = ['draft', 'active', 'locked', 'completed'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Draw Batches | Welloo Bonanza Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, sans-serif; }
        body { background: #0F0F0F; color: #FFF; padding: 20px; }
        .wrapper { max-width: 1150px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #222; flex-wrap: wrap; gap: 12px; }
        h1 { color: #FF6600; font-size: 24px; }
        .nav-links { display: flex; gap: 14px; align-items: center; }
        .nav-links a { color: #AAA; text-decoration: none; font-size: 13px; font-weight: 600; }
        .nav-links a:hover { color: #FF9900; }
        .btn { background: #FF6600; color: #000; border: none; padding: 10px 18px; font-weight: bold; border-radius: 6px; font-size: 14px; cursor: pointer; }
        .btn-secondary { background: #333; color: #FFF; }

        table { width: 100%; border-collapse: collapse; background: #1A1A1A; border-radius: 10px; overflow: hidden; border: 1px solid #333; }
        th, td { padding: 12px 14px; text-align: left; font-size: 13px; border-bottom: 1px solid #2A2A2A; white-space: nowrap; }
        th { background: #222; color: #AAA; text-transform: uppercase; font-size: 11px; }
        tr:hover { background: #202020; }

        select.status-select { background: #141414; border: 1px solid #333; color: #FFF; padding: 6px 8px; border-radius: 6px; font-size: 12px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-draft { background: rgba(170, 170, 170, 0.15); color: #AAA; }
        .badge-active { background: rgba(37, 211, 102, 0.15); color: #25D366; }
        .badge-locked { background: rgba(255, 153, 0, 0.15); color: #FF9900; }
        .badge-completed { background: rgba(255, 102, 0, 0.15); color: #FF6600; }

        .row-actions button { background: #262626; color: #DDD; border: 1px solid #3A3A3A; padding: 6px 10px; border-radius: 6px; font-size: 11px; cursor: pointer; }
        .row-actions button:hover { border-color: #FF6600; color: #FF9900; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: none; justify-content: center; align-items: center; z-index: 200; padding: 20px; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: #1A1A1A; border: 1px solid #444; border-radius: 12px; max-width: 420px; width: 100%; padding: 24px; }
        .modal-box h2 { color: #FF9900; font-size: 17px; margin-bottom: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
        .form-group label { font-size: 12.5px; color: #DDD; font-weight: 600; }
        .form-group input, .form-group select { padding: 10px 12px; background: #141414; border: 1px solid #333; border-radius: 6px; color: #FFF; font-size: 14px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: #FF6600; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
        .modal-error { display: none; background: rgba(255, 51, 51, 0.1); border: 1px solid #FF3333; color: #FF6B6B; font-size: 12px; font-weight: 600; padding: 8px 10px; border-radius: 6px; margin-bottom: 12px; }

        #toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(80px); background: #1A1A1A; border: 1px solid #FF6600; border-radius: 8px; padding: 10px 18px; font-size: 13px; opacity: 0; transition: 0.3s ease; z-index: 300; }
        #toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Draw Batches</h1>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="/api/auth/logout.php">Log Out</a>
            <button class="btn" onclick="openCreateModal()">+ New Batch</button>
        </div>
    </div>

    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Batch Name</th>
                <th>Entries</th>
                <th>Entry Start</th>
                <th>Entry Deadline</th>
                <th>Draw Date/Time</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="batches-tbody">
            <?php if (empty($batches)): ?>
                <tr><td colspan="8" style="text-align:center; color:#777;">No draw batches found.</td></tr>
            <?php else: ?>
                <?php foreach ($batches as $b): ?>
                    <tr data-id="<?= (int) $b['id'] ?>">
                        <td>#<?= (int) $b['id'] ?></td>
                        <td><strong><?= htmlspecialchars($b['batch_name']) ?></strong></td>
                        <td><?= (int) $b['entry_count'] ?></td>
                        <td><?= date('M d, Y H:i', strtotime($b['entry_start_time'])) ?></td>
                        <td><?= date('M d, Y H:i', strtotime($b['entry_deadline'])) ?></td>
                        <td><?= date('M d, Y H:i', strtotime($b['draw_datetime'])) ?></td>
                        <td>
                            <select class="status-select" onchange="updateStatus(<?= (int) $b['id'] ?>, this.value)">
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $b['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div style="margin-top:4px;">
                                <span class="badge badge-<?= $b['status'] ?>"><?= htmlspecialchars($b['status']) ?></span>
                            </div>
                        </td>
                        <td class="row-actions">
                            <button onclick='openEditModal(<?= json_encode([
                                "id" => (int) $b["id"],
                                "batch_name" => $b["batch_name"],
                                "entry_start_time" => date("Y-m-d\TH:i", strtotime($b["entry_start_time"])),
                                "entry_deadline" => date("Y-m-d\TH:i", strtotime($b["entry_deadline"])),
                                "draw_datetime" => date("Y-m-d\TH:i", strtotime($b["draw_datetime"])),
                            ]) ?>)'>Edit Schedule</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Create Batch Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal-box">
        <h2>Create New Batch</h2>
        <div class="modal-error" id="create-error"></div>
        <form id="createForm">
            <div class="form-group">
                <label>Batch Name</label>
                <input type="text" id="new-name" placeholder="Week 5 Draw" required>
            </div>
            <div class="form-group">
                <label>Entry Start</label>
                <input type="datetime-local" id="new-start" required>
            </div>
            <div class="form-group">
                <label>Entry Deadline</label>
                <input type="datetime-local" id="new-deadline" required>
            </div>
            <div class="form-group">
                <label>Draw Date/Time</label>
                <input type="datetime-local" id="new-draw" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="new-status">
                    <option value="draft">draft</option>
                    <option value="active">active</option>
                    <option value="locked">locked</option>
                    <option value="completed">completed</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn">Create Batch</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h2>Edit Batch Schedule</h2>
        <div class="modal-error" id="edit-error"></div>
        <form id="editForm">
            <div class="form-group">
                <label>Batch Name</label>
                <input type="text" id="edit-name" required>
            </div>
            <div class="form-group">
                <label>Entry Start</label>
                <input type="datetime-local" id="edit-start" required>
            </div>
            <div class="form-group">
                <label>Entry Deadline</label>
                <input type="datetime-local" id="edit-deadline" required>
            </div>
            <div class="form-group">
                <label>Draw Date/Time</label>
                <input type="datetime-local" id="edit-draw" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="toast"></div>

<script>
    let editBatchId = null;

    function openCreateModal() {
        document.getElementById('create-error').style.display = 'none';
        document.getElementById('createForm').reset();
        document.getElementById('createModal').classList.add('open');
    }

    function openEditModal(batch) {
        editBatchId = batch.id;
        document.getElementById('edit-error').style.display = 'none';
        document.getElementById('edit-name').value = batch.batch_name;
        document.getElementById('edit-start').value = batch.entry_start_time;
        document.getElementById('edit-deadline').value = batch.entry_deadline;
        document.getElementById('edit-draw').value = batch.draw_datetime;
        document.getElementById('editModal').classList.add('open');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    function showToast(message) {
        const toast = document.getElementById('toast');
        toast.innerText = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2500);
    }

    async function callApi(payload) {
        const res = await fetch('/api/batches.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });
        return { ok: res.ok, data: await res.json() };
    }

    document.getElementById('createForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const errorBox = document.getElementById('create-error');
        errorBox.style.display = 'none';

        const { ok, data } = await callApi({
            action: 'create',
            batch_name: document.getElementById('new-name').value.trim(),
            entry_start_time: document.getElementById('new-start').value,
            entry_deadline: document.getElementById('new-deadline').value,
            draw_datetime: document.getElementById('new-draw').value,
            status: document.getElementById('new-status').value
        });

        if (!ok) {
            errorBox.innerText = data.message || 'Failed to create batch';
            errorBox.style.display = 'block';
            return;
        }

        closeModal('createModal');
        showToast('Batch created');
        setTimeout(() => window.location.reload(), 600);
    });

    document.getElementById('editForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const errorBox = document.getElementById('edit-error');
        errorBox.style.display = 'none';

        const { ok, data } = await callApi({
            action: 'update_schedule',
            id: editBatchId,
            batch_name: document.getElementById('edit-name').value.trim(),
            entry_start_time: document.getElementById('edit-start').value,
            entry_deadline: document.getElementById('edit-deadline').value,
            draw_datetime: document.getElementById('edit-draw').value
        });

        if (!ok) {
            errorBox.innerText = data.message || 'Failed to update schedule';
            errorBox.style.display = 'block';
            return;
        }

        closeModal('editModal');
        showToast('Schedule updated');
        setTimeout(() => window.location.reload(), 600);
    });

    async function updateStatus(id, status) {
        const { ok, data } = await callApi({ action: 'update_status', id, status });
        if (!ok) {
            showToast(data.message || 'Failed to update status');
            return;
        }
        showToast('Status updated');
        setTimeout(() => window.location.reload(), 500);
    }
</script>
</body>
</html>
