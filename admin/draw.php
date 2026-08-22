<?php
require_once __DIR__ . '/../includes/auth.php';

check_access(['admin', 'draw_manager']);

$stmt = $pdo->query(
    "SELECT b.id, b.batch_name, b.status, b.entry_deadline, b.draw_datetime,
            COUNT(e.id) AS total_entries,
            COALESCE(SUM(CASE WHEN e.is_winner = 0 THEN 1 ELSE 0 END), 0) AS eligible_entries
     FROM draw_batches b
     LEFT JOIN bonanza_entries e ON e.batch_id = b.id
     WHERE b.status IN ('locked', 'completed')
        OR EXISTS (SELECT 1 FROM bonanza_entries e2 WHERE e2.batch_id = b.id AND e2.is_winner = 0)
     GROUP BY b.id
     ORDER BY b.id DESC"
);
$batches = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Draw Manager | Welloo Bonanza Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, sans-serif; }
        body { background: #0F0F0F; color: #FFF; padding: 20px; }
        .wrapper { max-width: 1150px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #222; }
        h1 { color: #FF6600; font-size: 24px; }
        h2.section-title { color: #FF9900; font-size: 15px; margin: 28px 0 12px; }

        .placeholder { background: #1A1A1A; border: 1px solid #333; border-radius: 10px; padding: 40px; text-align: center; color: #888; }

        table { width: 100%; border-collapse: collapse; background: #1A1A1A; border-radius: 10px; overflow: hidden; border: 1px solid #333; }
        th, td { padding: 12px 14px; text-align: left; font-size: 13px; border-bottom: 1px solid #2A2A2A; }
        th { background: #222; color: #AAA; text-transform: uppercase; font-size: 11px; }
        tr:hover { background: #202020; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-draft { background: rgba(170, 170, 170, 0.15); color: #AAA; }
        .badge-active { background: rgba(37, 211, 102, 0.15); color: #25D366; }
        .badge-locked { background: rgba(255, 153, 0, 0.15); color: #FF9900; }
        .badge-completed { background: rgba(255, 102, 0, 0.15); color: #FF6600; }

        .selection-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 16px; background: #1A1A1A; border: 1px solid #333; border-radius: 10px; padding: 14px 18px; }
        #selection-summary { color: #DDD; font-size: 13px; font-weight: 600; }
        .btn { background: #FF6600; color: #000; border: none; padding: 10px 18px; font-weight: bold; border-radius: 6px; font-size: 14px; cursor: pointer; }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .btn-wheel { background: linear-gradient(180deg, #FF6600 0%, #D64F00 100%); font-size: 16px; padding: 16px 26px; text-transform: uppercase; }

        #pool-section, #winners-section { display: none; }
        .pool-box { background: #1A1A1A; border: 1px solid #333; border-radius: 10px; padding: 18px; margin-top: 14px; }
        #pool-count-label { color: #DDD; font-size: 13px; font-weight: 700; margin-bottom: 10px; }
        .pool-list { max-height: 220px; overflow-y: auto; display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
        .pool-item { background: #141414; border: 1px solid #2A2A2A; border-radius: 6px; padding: 8px 12px; font-size: 12.5px; color: #DDD; display: flex; justify-content: space-between; gap: 10px; }
        .pool-empty { color: #777; font-size: 12.5px; text-align: center; padding: 12px; }
        .pool-batch { color: #FF9900; font-size: 10.5px; font-weight: 700; }

        .wheel-reveal { display: none; margin: 20px auto; max-width: 360px; text-align: center; background: #141414; border: 2px solid #333; border-radius: 12px; padding: 26px; font-size: 20px; font-weight: 900; color: #FFF; }
        .wheel-reveal.spinning { border-color: #FF6600; animation: pulse 0.3s ease-in-out infinite alternate; }
        .wheel-reveal.winner { border-color: #25D366; font-size: 18px; color: #25D366; }
        @keyframes pulse { from { box-shadow: 0 0 6px rgba(255,102,0,0.3); } to { box-shadow: 0 0 20px rgba(255,102,0,0.7); } }

        .spin-actions { text-align: center; margin-top: 8px; }

        .winner-row { background: #141414; border: 1px solid #2A2A2A; border-radius: 6px; padding: 10px 14px; font-size: 12.5px; color: #DDD; margin-bottom: 8px; }

        #toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(80px); background: #1A1A1A; border: 1px solid #FF6600; border-radius: 8px; padding: 10px 18px; font-size: 13px; opacity: 0; transition: 0.3s ease; z-index: 300; }
        #toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>
<div class="wrapper">
    <div class="header">
        <h1>Draw Manager</h1>
    </div>

    <h2 class="section-title">1. Select Batches for This Draw</h2>
    <?php if (empty($batches)): ?>
        <div class="placeholder">No batches are ready for a draw yet. A batch becomes eligible once it's locked/completed, or has entries waiting to be drawn.</div>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th></th>
                    <th>Batch</th>
                    <th>Status</th>
                    <th>Draw Date/Time</th>
                    <th>Total Entries</th>
                    <th>Eligible (not yet won)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batches as $b): ?>
                    <tr>
                        <td><input type="checkbox" class="batch-check" data-id="<?= (int) $b['id'] ?>" data-eligible="<?= (int) $b['eligible_entries'] ?>" onchange="updateSelectionSummary()"></td>
                        <td><strong><?= htmlspecialchars($b['batch_name']) ?></strong></td>
                        <td><span class="badge badge-<?= $b['status'] ?>"><?= htmlspecialchars($b['status']) ?></span></td>
                        <td><?= date('M d, Y H:i', strtotime($b['draw_datetime'])) ?></td>
                        <td><?= (int) $b['total_entries'] ?></td>
                        <td><?= (int) $b['eligible_entries'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div class="selection-bar">
            <span id="selection-summary">0 batches selected · 0 eligible entries (estimated)</span>
            <button class="btn" id="load-pool-btn" onclick="loadPool()" disabled>Load Draw Pool</button>
        </div>
    <?php endif; ?>

    <div id="pool-section">
        <h2 class="section-title">2. Draw Pool</h2>
        <div class="pool-box">
            <div id="pool-count-label">0 eligible entries loaded</div>
            <div class="pool-list" id="pool-list"></div>
            <div class="spin-actions">
                <button class="btn btn-wheel" id="spin-btn" onclick="spinWheel()" disabled>🎡 Spin the Wheel</button>
            </div>
            <div class="wheel-reveal" id="wheel-reveal"></div>
        </div>
    </div>

    <div id="winners-section">
        <h2 class="section-title">Winners This Session</h2>
        <div id="winners-log"></div>
    </div>
</div>

<div id="toast"></div>

<script>
    function showToast(message) {
        const toast = document.getElementById('toast');
        toast.innerText = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2500);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.innerText = str ?? '';
        return div.innerHTML;
    }

    function updateSelectionSummary() {
        const checked = [...document.querySelectorAll('.batch-check:checked')];
        const totalEligible = checked.reduce((sum, c) => sum + parseInt(c.dataset.eligible, 10), 0);
        const summary = document.getElementById('selection-summary');
        if (summary) {
            summary.innerText = `${checked.length} batch(es) selected · ${totalEligible} eligible entries (estimated)`;
        }
        const loadBtn = document.getElementById('load-pool-btn');
        if (loadBtn) loadBtn.disabled = checked.length === 0;
    }
    updateSelectionSummary();

    let currentPool = [];

    async function loadPool() {
        const batchIds = [...document.querySelectorAll('.batch-check:checked')].map(c => c.dataset.id);
        if (batchIds.length === 0) return;

        const res = await fetch('/api/get_draw_pool.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ batch_ids: batchIds })
        });
        const data = await res.json();

        if (data.status !== 'success') {
            showToast(data.message || 'Failed to load draw pool');
            return;
        }

        currentPool = data.pool;
        renderPool();
        document.getElementById('pool-section').style.display = 'block';
        document.getElementById('wheel-reveal').style.display = 'none';
    }

    function renderPool() {
        document.getElementById('pool-count-label').innerText = `${currentPool.length} eligible entries loaded`;
        const list = document.getElementById('pool-list');
        list.innerHTML = currentPool.length
            ? currentPool.map(e => `<div class="pool-item"><span>${escapeHtml(e.name)} — ${escapeHtml(e.district)}</span><span class="pool-batch">${escapeHtml(e.batch_name)}</span></div>`).join('')
            : '<div class="pool-empty">No eligible entries remaining in this pool.</div>';
        document.getElementById('spin-btn').disabled = currentPool.length === 0;
    }

    let spinning = false;

    function spinWheel() {
        if (spinning || currentPool.length === 0) return;
        spinning = true;
        document.getElementById('spin-btn').disabled = true;

        const reveal = document.getElementById('wheel-reveal');
        reveal.style.display = 'block';
        reveal.className = 'wheel-reveal spinning';

        let ticks = 0;
        const maxTicks = 20;
        const interval = setInterval(() => {
            const r = currentPool[Math.floor(Math.random() * currentPool.length)];
            reveal.innerText = r.name;
            ticks++;
            if (ticks >= maxTicks) {
                clearInterval(interval);
                finishSpin();
            }
        }, 90);
    }

    async function finishSpin() {
        const reveal = document.getElementById('wheel-reveal');
        const candidate = currentPool[Math.floor(Math.random() * currentPool.length)];

        const res = await fetch('/api/record_winner.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ entry_id: candidate.id })
        });
        const data = await res.json();

        if (data.status !== 'success') {
            // Most likely raced with another draw session on the same entry — drop it and let the admin retry.
            currentPool = currentPool.filter(e => e.id !== candidate.id);
            renderPool();
            reveal.className = 'wheel-reveal';
            reveal.style.display = 'none';
            spinning = false;
            document.getElementById('spin-btn').disabled = currentPool.length === 0;
            showToast(data.message || 'That entry was just claimed — spin again');
            return;
        }

        const winner = data.winner;
        reveal.className = 'wheel-reveal winner';
        reveal.innerHTML = `🎉 <strong>${escapeHtml(winner.name)}</strong><br><span style="font-size:12px;color:#AAA;">${escapeHtml(winner.district)} &middot; ${escapeHtml(winner.batch_name)}</span>`;

        currentPool = currentPool.filter(e => e.id !== winner.id);
        renderPool();

        const log = document.getElementById('winners-log');
        const row = document.createElement('div');
        row.className = 'winner-row';
        row.innerHTML = `<strong>${escapeHtml(winner.name)}</strong> — ${escapeHtml(winner.phone)} — ${escapeHtml(winner.district)} <span class="pool-batch">${escapeHtml(winner.batch_name)}</span>`;
        log.prepend(row);
        document.getElementById('winners-section').style.display = 'block';

        spinning = false;
        document.getElementById('spin-btn').disabled = currentPool.length === 0;
    }
</script>
</body>
</html>
