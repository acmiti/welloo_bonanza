<?php
require_once __DIR__ . '/../includes/auth.php';

check_access(['admin', 'draw_manager']);

$stmt = $pdo->query(
    "SELECT e.id, e.name, e.phone, e.district, e.town, e.dealer, e.language,
            e.batch_id, e.verification_status, e.won_at,
            b.batch_name, b.draw_datetime
     FROM bonanza_entries e
     JOIN draw_batches b ON b.id = e.batch_id
     WHERE e.is_winner = 1
     ORDER BY b.id DESC, e.won_at ASC"
);
$winners = $stmt->fetchAll();

$batches = [];
foreach ($winners as $w) {
    $bid = (int) $w['batch_id'];
    if (!isset($batches[$bid])) {
        $batches[$bid] = [
            'batch_name'    => $w['batch_name'],
            'draw_datetime' => $w['draw_datetime'],
            'winners'       => [],
        ];
    }
    $batches[$bid]['winners'][] = $w;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Winner Logs | Welloo Bonanza Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, sans-serif; }
        body { background: #0F0F0F; color: #FFF; padding: 20px; }
        .wrapper { max-width: 1150px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #222; flex-wrap: wrap; gap: 12px; }
        h1 { color: #FF6600; font-size: 24px; }
        .nav-links { display: flex; gap: 14px; align-items: center; }
        .btn { background: #FF6600; color: #000; border: none; padding: 10px 18px; font-weight: bold; border-radius: 6px; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-secondary { background: #333; color: #FFF; }
        .btn-small { padding: 6px 10px; font-size: 11px; }

        .placeholder { background: #1A1A1A; border: 1px solid #333; border-radius: 10px; padding: 40px; text-align: center; color: #888; }

        .batch-group { background: #161616; border: 1px solid #2A2A2A; border-radius: 10px; margin-bottom: 20px; overflow: hidden; }
        .batch-group-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding: 14px 18px; background: #1E1E1E; border-bottom: 1px solid #2A2A2A; }
        .batch-group-header h2 { color: #FF9900; font-size: 15px; }
        .batch-group-header .meta { color: #888; font-size: 12px; margin-top: 2px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 14px; text-align: left; font-size: 13px; border-bottom: 1px solid #2A2A2A; }
        th { background: #202020; color: #AAA; text-transform: uppercase; font-size: 11px; }
        tr:hover { background: #1C1C1C; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-pending { background: rgba(255, 153, 0, 0.15); color: #FF9900; }
        .badge-verified { background: rgba(37, 211, 102, 0.15); color: #25D366; }

        .row-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .row-actions button { background: #262626; color: #DDD; border: 1px solid #3A3A3A; padding: 6px 10px; border-radius: 6px; font-size: 11px; cursor: pointer; }
        .row-actions button:hover { border-color: #FF6600; color: #FF9900; }
        .row-actions button.btn-disqualify:hover { border-color: #FF3333; color: #FF6B6B; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: none; justify-content: center; align-items: center; z-index: 200; padding: 20px; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: #1A1A1A; border: 1px solid #444; border-radius: 12px; max-width: 420px; width: 100%; padding: 24px; }
        .modal-box h2 { color: #FF9900; font-size: 17px; margin-bottom: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
        .form-group label { font-size: 12.5px; color: #DDD; font-weight: 600; }
        .form-group textarea { padding: 10px 12px; background: #141414; border: 1px solid #333; border-radius: 6px; color: #FFF; font-size: 14px; outline: none; resize: vertical; min-height: 80px; font-family: inherit; }
        .form-group textarea:focus { border-color: #FF6600; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
        .modal-error { display: none; background: rgba(255, 51, 51, 0.1); border: 1px solid #FF3333; color: #FF6B6B; font-size: 12px; font-weight: 600; padding: 8px 10px; border-radius: 6px; margin-bottom: 12px; }

        #toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(80px); background: #1A1A1A; border: 1px solid #FF6600; border-radius: 8px; padding: 10px 18px; font-size: 13px; opacity: 0; transition: 0.3s ease; z-index: 300; }
        #toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>
<div class="wrapper">
    <div class="header">
        <h1>Winner Logs</h1>
        <div class="nav-links">
            <a class="btn" href="/api/export_winners.php">Export All to CSV</a>
        </div>
    </div>

    <?php if (empty($batches)): ?>
        <div class="placeholder">No winners recorded yet. Run a draw from the Draw Manager to get started.</div>
    <?php else: ?>
        <?php foreach ($batches as $batchId => $group): ?>
            <div class="batch-group">
                <div class="batch-group-header">
                    <div>
                        <h2><?= htmlspecialchars($group['batch_name']) ?></h2>
                        <div class="meta">Draw: <?= date('M d, Y H:i', strtotime($group['draw_datetime'])) ?> &middot; <?= count($group['winners']) ?> winner(s)</div>
                    </div>
                    <a class="btn btn-secondary btn-small" href="/api/export_winners.php?batch_id=<?= (int) $batchId ?>">Export This Batch</a>
                </div>
                <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>District</th>
                            <th>Dealer</th>
                            <th>Verification</th>
                            <th>Won At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group['winners'] as $w): ?>
                            <tr data-id="<?= (int) $w['id'] ?>">
                                <td><strong><?= htmlspecialchars($w['name']) ?></strong></td>
                                <td><?= htmlspecialchars($w['phone']) ?></td>
                                <td><?= htmlspecialchars($w['district']) ?></td>
                                <td><?= htmlspecialchars($w['dealer']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $w['verification_status'] ?>"><?= htmlspecialchars($w['verification_status']) ?></span>
                                </td>
                                <td><?= $w['won_at'] ? date('M d, Y H:i', strtotime($w['won_at'])) : '—' ?></td>
                                <td class="row-actions">
                                    <button onclick="toggleVerification(<?= (int) $w['id'] ?>)">
                                        <?= $w['verification_status'] === 'verified' ? 'Mark Pending' : 'Mark Verified' ?>
                                    </button>
                                    <button class="btn-disqualify" onclick="openDisqualifyModal(<?= (int) $w['id'] ?>, '<?= htmlspecialchars($w['name'], ENT_QUOTES) ?>')">Disqualify / Re-Spin</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Disqualify Modal -->
<div class="modal-overlay" id="disqualifyModal">
    <div class="modal-box">
        <h2>Disqualify Winner</h2>
        <div class="modal-error" id="disqualify-error"></div>
        <form id="disqualifyForm">
            <p style="font-size:13px; color:#AAA; margin-bottom:14px;">Disqualifying <strong id="disqualify-name"></strong>. This reopens their slot for re-draw.</p>
            <div class="form-group">
                <label>Reason</label>
                <textarea id="disqualify-reason" required placeholder="e.g. Could not verify NIC on prize collection"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('disqualifyModal')">Cancel</button>
                <button type="submit" class="btn">Disqualify</button>
            </div>
        </form>
    </div>
</div>

<div id="toast"></div>

<script>
    let disqualifyEntryId = null;

    function openDisqualifyModal(id, name) {
        disqualifyEntryId = id;
        document.getElementById('disqualify-error').style.display = 'none';
        document.getElementById('disqualifyForm').reset();
        document.getElementById('disqualify-name').innerText = name;
        document.getElementById('disqualifyModal').classList.add('open');
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
        const res = await fetch('/api/winners.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });
        return { ok: res.ok, data: await res.json() };
    }

    document.getElementById('disqualifyForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const errorBox = document.getElementById('disqualify-error');
        errorBox.style.display = 'none';

        const { ok, data } = await callApi({
            action: 'disqualify',
            entry_id: disqualifyEntryId,
            reason: document.getElementById('disqualify-reason').value.trim()
        });

        if (!ok) {
            errorBox.innerText = data.message || 'Failed to disqualify winner';
            errorBox.style.display = 'block';
            return;
        }

        closeModal('disqualifyModal');
        showToast('Winner disqualified — slot reopened for re-draw');
        setTimeout(() => window.location.reload(), 700);
    });

    async function toggleVerification(id) {
        const { ok, data } = await callApi({ action: 'toggle_verification', entry_id: id });
        if (!ok) {
            showToast(data.message || 'Failed to update verification status');
            return;
        }
        showToast('Verification status updated');
        setTimeout(() => window.location.reload(), 500);
    }
</script>
</body>
</html>
