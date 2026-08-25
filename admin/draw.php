<?php
require_once __DIR__ . '/../includes/auth.php';

check_access(['admin', 'draw_manager']);

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

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
        .badge-removed { background: rgba(37, 211, 102, 0.15); color: #25D366; }
        .badge-kept { background: rgba(77, 166, 255, 0.15); color: #4DA6FF; }

        .selection-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 16px; background: #1A1A1A; border: 1px solid #333; border-radius: 10px; padding: 14px 18px; }
        #selection-summary { color: #DDD; font-size: 13px; font-weight: 600; }
        .btn { background: #FF6600; color: #000; border: none; padding: 10px 18px; font-weight: bold; border-radius: 6px; font-size: 14px; cursor: pointer; }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .btn-secondary { background: #333; color: #FFF; }
        .btn-stream { background: #262626; color: #4DA6FF; border: 1px solid #4DA6FF; }
        .btn-wheel { background: linear-gradient(180deg, #FF6600 0%, #D64F00 100%); font-size: 17px; padding: 16px 30px; text-transform: uppercase; box-shadow: 0 4px 20px rgba(255,102,0,0.4); }

        #pool-section { display: none; }
        .pool-box { background: #1A1A1A; border: 1px solid #333; border-radius: 10px; padding: 22px; margin-top: 14px; }
        #pool-count-label { color: #DDD; font-size: 13px; font-weight: 700; margin-bottom: 14px; text-align: center; }

        .wheel-stage { position: relative; width: 340px; height: 340px; margin: 0 auto 20px; }
        #wheel-canvas { display: block; width: 100%; height: 100%; border-radius: 50%; box-shadow: 0 0 0 6px #1A1A1A, 0 0 0 8px #FF6600, 0 10px 40px rgba(0,0,0,0.6); }

        .spin-actions { text-align: center; margin-top: 4px; display: flex; gap: 10px; justify-content: center; }

        /* Target criteria panel (admin-only pre-selection) */
        .criteria-box { background: #1A1A1A; border: 1px dashed #4DA6FF; border-radius: 10px; padding: 18px 22px; margin-top: 14px; }
        .criteria-box .criteria-title { color: #4DA6FF; font-size: 12.5px; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }
        .criteria-box .criteria-hint { color: #777; font-size: 11.5px; margin-bottom: 14px; }
        .criteria-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
        .criteria-fields label { display: block; color: #AAA; font-size: 11px; text-transform: uppercase; margin-bottom: 5px; }
        .criteria-fields input { width: 100%; background: #0F0F0F; border: 1px solid #333; color: #FFF; padding: 9px 10px; border-radius: 6px; font-size: 13px; }
        .criteria-fields input:focus { outline: none; border-color: #4DA6FF; }

        #winners-section { display: none; }
        .winner-row { background: #141414; border: 1px solid #2A2A2A; border-radius: 6px; padding: 10px 14px; font-size: 12.5px; color: #DDD; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }

        /* Post-winner choice modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); display: none; justify-content: center; align-items: center; z-index: 400; padding: 20px; }
        .modal-overlay.open { display: flex; }
        .winner-modal-box { background: #1A1A1A; border: 2px solid #FF6600; border-radius: 14px; max-width: 420px; width: 100%; padding: 28px; text-align: center; box-shadow: 0 0 40px rgba(255,102,0,0.35); }
        .winner-modal-box .emoji { font-size: 40px; margin-bottom: 6px; }
        .winner-modal-box h2 { color: #FF9900; font-size: 20px; margin-bottom: 4px; }
        .winner-modal-box .winner-meta { color: #AAA; font-size: 13px; margin-bottom: 22px; line-height: 1.6; }
        .winner-modal-box .winner-meta div { display: block; }
        .winner-choice-actions { display: flex; flex-direction: column; gap: 10px; }
        .winner-choice-actions button { padding: 14px; border-radius: 8px; font-size: 13.5px; font-weight: 700; cursor: pointer; border: none; }
        .btn-remove { background: linear-gradient(180deg, #25D366 0%, #199C4E 100%); color: #06210F; }
        .btn-keep { background: #262626; color: #DDD; border: 1px solid #444 !important; }
        .btn-keep:hover { border-color: #4DA6FF !important; color: #4DA6FF; }

        /* Confetti */
        #confetti-layer { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; overflow: hidden; z-index: 500; }
        .confetti-piece { position: absolute; top: -20px; width: 8px; height: 14px; opacity: 0.95; animation: confetti-fall linear forwards; }
        @keyframes confetti-fall {
            to { transform: translateY(110vh) rotate(720deg); opacity: 0.2; }
        }

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
            <div style="display:flex; gap:10px;">
                <button class="btn btn-stream" id="stream-view-btn" onclick="launchStreamView()" disabled>📺 Launch Stream View</button>
                <button class="btn" id="load-pool-btn" onclick="loadPool()" disabled>Load Draw Pool</button>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <div class="criteria-box">
            <div class="criteria-title">🎯 Target Criteria (admin only)</div>
            <div class="criteria-hint">Optional pre-selection — narrows the eligible pool before a spin. Leave blank for a fully random draw. Falls back to the full pool automatically if nothing matches.</div>
            <div class="criteria-fields">
                <div>
                    <label for="target-district">Target District</label>
                    <input type="text" id="target-district" placeholder="e.g. Kandy">
                </div>
                <div>
                    <label for="target-city">Target City / Town</label>
                    <input type="text" id="target-city" placeholder="e.g. Gampola">
                </div>
                <div>
                    <label for="target-dealer">Target Dealer</label>
                    <input type="text" id="target-dealer" placeholder="e.g. G.M.lanka">
                </div>
            </div>
        </div>
        <?php else: ?>
        <input type="hidden" id="target-district" value="">
        <input type="hidden" id="target-city" value="">
        <input type="hidden" id="target-dealer" value="">
        <?php endif; ?>
    <?php endif; ?>

    <div id="pool-section">
        <h2 class="section-title">2. Spin the Wheel</h2>
        <div class="pool-box">
            <div id="pool-count-label">0 eligible entries loaded</div>
            <div class="wheel-stage">
                <canvas id="wheel-canvas" width="340" height="340"></canvas>
            </div>
            <div class="spin-actions">
                <button class="btn btn-wheel" id="spin-btn" onclick="DrawWheel.spinWheel()" disabled>🎡 SPIN THE WHEEL</button>
            </div>
        </div>
    </div>

    <div id="winners-section">
        <h2 class="section-title">Session Winners</h2>
        <div id="winners-log"></div>
    </div>
</div>

<!-- Post-winner choice modal -->
<div class="modal-overlay" id="winnerModal">
    <div class="winner-modal-box">
        <div class="emoji">🎉</div>
        <h2 id="winner-modal-name">Winner Name</h2>
        <div class="winner-meta">
            <div id="winner-modal-line1">City from District</div>
            <div id="winner-modal-line2">Dealer: —</div>
        </div>
        <div class="winner-choice-actions">
            <button class="btn-remove" id="btn-remove-winner">Remove from List &amp; Record Winner</button>
            <button class="btn-keep" id="btn-keep-winner">Allow Re-entry &amp; Re-spin</button>
        </div>
    </div>
</div>

<div id="confetti-layer"></div>
<div id="toast"></div>

<script src="/admin/assets/draw-wheel.js"></script>
<script>
    /* ---------- Batch selection ---------- */

    function updateSelectionSummary() {
        const checked = [...document.querySelectorAll('.batch-check:checked')];
        const totalEligible = checked.reduce((sum, c) => sum + parseInt(c.dataset.eligible, 10), 0);
        const summary = document.getElementById('selection-summary');
        if (summary) {
            summary.innerText = `${checked.length} batch(es) selected · ${totalEligible} eligible entries (estimated)`;
        }
        const loadBtn = document.getElementById('load-pool-btn');
        if (loadBtn) loadBtn.disabled = checked.length === 0;
        const streamBtn = document.getElementById('stream-view-btn');
        if (streamBtn) streamBtn.disabled = checked.length === 0;
    }
    updateSelectionSummary();

    function selectedBatchIds() {
        return [...document.querySelectorAll('.batch-check:checked')].map(c => c.dataset.id);
    }

    async function loadPool() {
        const batchIds = selectedBatchIds();
        if (batchIds.length === 0) return;
        document.getElementById('pool-section').style.display = 'block';
        await DrawWheel.loadPool(batchIds);
    }

    function launchStreamView() {
        const batchIds = selectedBatchIds();
        if (batchIds.length === 0) return;

        const params = new URLSearchParams();
        params.set('batch_ids', batchIds.join(','));
        const district = document.getElementById('target-district')?.value.trim();
        const city = document.getElementById('target-city')?.value.trim();
        const dealer = document.getElementById('target-dealer')?.value.trim();
        if (district) params.set('target_district', district);
        if (city) params.set('target_city', city);
        if (dealer) params.set('target_dealer', dealer);

        window.open('/admin/draw_stage.php?' + params.toString(), '_blank', 'width=820,height=900,noopener');
    }

    /* ---------- Wire up shared wheel engine hooks ---------- */

    DrawWheel.onPoolLoaded = function (pool) {
        document.getElementById('pool-count-label').innerText = `${pool.length} eligible entries loaded`;
        document.getElementById('spin-btn').disabled = pool.length === 0;
    };

    DrawWheel.onError = function (message) {
        DrawWheel.showToast(message || 'Something went wrong');
    };

    DrawWheel.onSpinStart = function () {
        document.getElementById('spin-btn').disabled = true;
    };

    DrawWheel.onSpinSettled = function () {
        document.getElementById('spin-btn').disabled = DrawWheel.getPool().length === 0;
    };

    DrawWheel.onWinnerResolved = function (winner, action, spinCount) {
        document.getElementById('pool-count-label').innerText = `${DrawWheel.getPool().length} eligible entries loaded`;

        const log = document.getElementById('winners-log');
        const row = document.createElement('div');
        row.className = 'winner-row';
        const actionBadge = action === 'remove'
            ? '<span class="badge badge-removed">Recorded</span>'
            : '<span class="badge badge-kept">Kept Eligible</span>';
        row.innerHTML = `
            <span><strong>${DrawWheel.escapeHtml(winner.name)}</strong> — ${DrawWheel.escapeHtml(winner.town)} City, ${DrawWheel.escapeHtml(winner.district)} District <span style="color:#FF9900;font-size:10.5px;font-weight:700;">${DrawWheel.escapeHtml(winner.batch_name)}</span></span>
            <span>Spin #${spinCount} ${actionBadge}</span>
        `;
        log.prepend(row);
        document.getElementById('winners-section').style.display = 'block';
    };
</script>
</body>
</html>
