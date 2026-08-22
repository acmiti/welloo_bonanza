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
        .badge-removed { background: rgba(37, 211, 102, 0.15); color: #25D366; }
        .badge-kept { background: rgba(77, 166, 255, 0.15); color: #4DA6FF; }

        .selection-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 16px; background: #1A1A1A; border: 1px solid #333; border-radius: 10px; padding: 14px 18px; }
        #selection-summary { color: #DDD; font-size: 13px; font-weight: 600; }
        .btn { background: #FF6600; color: #000; border: none; padding: 10px 18px; font-weight: bold; border-radius: 6px; font-size: 14px; cursor: pointer; }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .btn-secondary { background: #333; color: #FFF; }
        .btn-wheel { background: linear-gradient(180deg, #FF6600 0%, #D64F00 100%); font-size: 17px; padding: 16px 30px; text-transform: uppercase; box-shadow: 0 4px 20px rgba(255,102,0,0.4); }

        #pool-section { display: none; }
        .pool-box { background: #1A1A1A; border: 1px solid #333; border-radius: 10px; padding: 22px; margin-top: 14px; }
        #pool-count-label { color: #DDD; font-size: 13px; font-weight: 700; margin-bottom: 14px; text-align: center; }

        .wheel-stage { position: relative; width: 340px; height: 340px; margin: 0 auto 20px; }
        #wheel-canvas { display: block; width: 100%; height: 100%; border-radius: 50%; box-shadow: 0 0 0 6px #1A1A1A, 0 0 0 8px #FF6600, 0 10px 40px rgba(0,0,0,0.6); }

        .spin-actions { text-align: center; margin-top: 4px; }

        #winners-section { display: none; }
        .winner-row { background: #141414; border: 1px solid #2A2A2A; border-radius: 6px; padding: 10px 14px; font-size: 12.5px; color: #DDD; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }

        /* Post-winner choice modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); display: none; justify-content: center; align-items: center; z-index: 400; padding: 20px; }
        .modal-overlay.open { display: flex; }
        .winner-modal-box { background: #1A1A1A; border: 2px solid #FF6600; border-radius: 14px; max-width: 420px; width: 100%; padding: 28px; text-align: center; box-shadow: 0 0 40px rgba(255,102,0,0.35); }
        .winner-modal-box .emoji { font-size: 40px; margin-bottom: 6px; }
        .winner-modal-box h2 { color: #FF9900; font-size: 20px; margin-bottom: 4px; }
        .winner-modal-box .winner-meta { color: #AAA; font-size: 13px; margin-bottom: 22px; }
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
            <button class="btn" id="load-pool-btn" onclick="loadPool()" disabled>Load Draw Pool</button>
        </div>
    <?php endif; ?>

    <div id="pool-section">
        <h2 class="section-title">2. Spin the Wheel</h2>
        <div class="pool-box">
            <div id="pool-count-label">0 eligible entries loaded</div>
            <div class="wheel-stage">
                <canvas id="wheel-canvas" width="340" height="340"></canvas>
            </div>
            <div class="spin-actions">
                <button class="btn btn-wheel" id="spin-btn" onclick="spinWheel()" disabled>🎡 SPIN THE WHEEL</button>
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
        <div class="winner-meta" id="winner-modal-meta">Phone · District · Batch</div>
        <div class="winner-choice-actions">
            <button class="btn-remove" id="btn-remove-winner">Remove from List &amp; Record Winner</button>
            <button class="btn-keep" id="btn-keep-winner">Allow Re-entry &amp; Re-spin</button>
        </div>
    </div>
</div>

<div id="confetti-layer"></div>
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
    }
    updateSelectionSummary();

    let currentPool = [];
    const spinCounts = {}; // entry_id -> number of times drawn this session

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
        wheelRotation = 0;
        document.getElementById('pool-count-label').innerText = `${currentPool.length} eligible entries loaded`;
        document.getElementById('spin-btn').disabled = currentPool.length === 0;
        document.getElementById('pool-section').style.display = 'block';
        drawWheel(currentPool, wheelRotation);
    }

    /* ---------- Canvas wheel ---------- */

    const WHEEL_COLORS = ['#B8860B', '#D64F00', '#2A2A2A', '#FF6600'];
    let wheelRotation = 0;

    function sliceAngleFor(poolSize) { return (Math.PI * 2) / poolSize; }
    function sliceCenterAngle(index, poolSize) {
        const sa = sliceAngleFor(poolSize);
        return index * sa + sa / 2;
    }
    function normalizeAngle(a) {
        const twoPi = Math.PI * 2;
        return ((a % twoPi) + twoPi) % twoPi;
    }

    function drawWheel(pool, rotation) {
        const canvas = document.getElementById('wheel-canvas');
        const ctx = canvas.getContext('2d');
        const size = canvas.width;
        const cx = size / 2, cy = size / 2, radius = size / 2 - 6;

        ctx.clearRect(0, 0, size, size);

        if (!pool || pool.length === 0) {
            ctx.beginPath();
            ctx.arc(cx, cy, radius, 0, Math.PI * 2);
            ctx.fillStyle = '#1A1A1A';
            ctx.fill();
            ctx.fillStyle = '#777';
            ctx.font = '14px Segoe UI';
            ctx.textAlign = 'center';
            ctx.fillText('No entries loaded', cx, cy + 5);
            return;
        }

        const sliceAngle = sliceAngleFor(pool.length);

        ctx.save();
        ctx.translate(cx, cy);
        ctx.rotate(rotation);

        pool.forEach((entry, i) => {
            const start = i * sliceAngle;
            const end = start + sliceAngle;

            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.arc(0, 0, radius, start, end);
            ctx.closePath();
            ctx.fillStyle = WHEEL_COLORS[i % WHEEL_COLORS.length];
            ctx.fill();
            ctx.strokeStyle = '#0F0F0F';
            ctx.lineWidth = 1;
            ctx.stroke();

            if (pool.length <= 40) {
                ctx.save();
                ctx.rotate(start + sliceAngle / 2);
                ctx.textAlign = 'right';
                ctx.fillStyle = '#FFFFFF';
                ctx.font = 'bold 11px Segoe UI';
                const label = entry.name.length > 14 ? entry.name.slice(0, 13) + '…' : entry.name;
                ctx.fillText(label, radius - 10, 4);
                ctx.restore();
            }
        });

        ctx.restore();

        // Hub
        ctx.beginPath();
        ctx.arc(cx, cy, 20, 0, Math.PI * 2);
        ctx.fillStyle = '#0F0F0F';
        ctx.fill();
        ctx.strokeStyle = '#FF6600';
        ctx.lineWidth = 3;
        ctx.stroke();

        // Fixed pointer at top, pointing down
        ctx.beginPath();
        ctx.moveTo(cx - 14, 2);
        ctx.lineTo(cx + 14, 2);
        ctx.lineTo(cx, 30);
        ctx.closePath();
        ctx.fillStyle = '#FF9900';
        ctx.fill();
        ctx.strokeStyle = '#0F0F0F';
        ctx.lineWidth = 1.5;
        ctx.stroke();
    }

    /* ---------- Spin animation (10s: 0-5s constant speed, 5-10s cubic ease-out) ---------- */

    function easeOutCubic(x) { return 1 - Math.pow(1 - x, 3); }

    function spinProgress(t) {
        if (t <= 0.5) {
            return 1.4 * t; // constant angular velocity for the first 5s
        }
        return 0.7 + 0.3 * easeOutCubic((t - 0.5) / 0.5); // decelerate over the final 5s
    }

    /* ---------- Web Audio: synthesized tick + fanfare ---------- */

    let audioCtx = null;

    function getAudioCtx() {
        const AudioCtor = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtor) return null;
        if (!audioCtx) audioCtx = new AudioCtor();
        if (audioCtx.state === 'suspended') audioCtx.resume();
        return audioCtx;
    }

    function playTick() {
        const ctx = getAudioCtx();
        if (!ctx) return;
        try {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'square';
            osc.frequency.value = 950;
            gain.gain.setValueAtTime(0.16, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.05);
            osc.connect(gain).connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.06);
        } catch (e) { /* audio unavailable — spin still works silently */ }
    }

    function playFanfare() {
        const ctx = getAudioCtx();
        if (!ctx) return;
        try {
            const notes = [523.25, 659.25, 783.99, 1046.5]; // C5 E5 G5 C6
            notes.forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.value = freq;
                const startTime = ctx.currentTime + i * 0.12;
                gain.gain.setValueAtTime(0.0001, startTime);
                gain.gain.exponentialRampToValueAtTime(0.25, startTime + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, startTime + 0.5);
                osc.connect(gain).connect(ctx.destination);
                osc.start(startTime);
                osc.stop(startTime + 0.55);
            });
        } catch (e) { /* audio unavailable — spin still works silently */ }
    }

    function sliceIndexAtPointer(poolSize, rotation) {
        const sliceAngle = sliceAngleFor(poolSize);
        const localAngle = normalizeAngle(-Math.PI / 2 - rotation);
        return Math.floor(localAngle / sliceAngle) % poolSize;
    }

    let spinning = false;

    function spinWheel() {
        if (spinning || currentPool.length === 0) return;
        spinning = true;
        document.getElementById('spin-btn').disabled = true;
        getAudioCtx(); // unlock audio on this user gesture

        const winningIndex = Math.floor(Math.random() * currentPool.length);
        const winner = currentPool[winningIndex];

        const sliceAngle = sliceAngleFor(currentPool.length);
        const center = winningIndex * sliceAngle + sliceAngle / 2;
        const desiredMod = normalizeAngle(-Math.PI / 2 - center);
        const currentMod = normalizeAngle(wheelRotation);
        let delta = desiredMod - currentMod;
        if (delta < 0) delta += Math.PI * 2;
        const extraSpins = 8 + Math.floor(Math.random() * 3); // 8-10 full turns
        const targetRotation = wheelRotation + delta + extraSpins * Math.PI * 2;

        const startRotation = wheelRotation;
        const duration = 10000;
        const t0 = performance.now();
        let lastTickIndex = sliceIndexAtPointer(currentPool.length, wheelRotation);

        function frame(now) {
            const t = Math.min((now - t0) / duration, 1);
            const p = spinProgress(t);
            wheelRotation = startRotation + (targetRotation - startRotation) * p;
            drawWheel(currentPool, wheelRotation);

            const tickIndex = sliceIndexAtPointer(currentPool.length, wheelRotation);
            if (tickIndex !== lastTickIndex) {
                lastTickIndex = tickIndex;
                playTick();
            }

            if (t < 1) {
                requestAnimationFrame(frame);
            } else {
                wheelRotation = targetRotation;
                drawWheel(currentPool, wheelRotation);
                playFanfare();
                onSpinComplete(winner);
            }
        }
        requestAnimationFrame(frame);
    }

    /* ---------- Confetti ---------- */

    function fireConfetti() {
        const layer = document.getElementById('confetti-layer');
        const colors = ['#FF6600', '#FF9900', '#25D366', '#4DA6FF', '#FFFFFF'];
        for (let i = 0; i < 60; i++) {
            const piece = document.createElement('div');
            piece.className = 'confetti-piece';
            piece.style.left = Math.random() * 100 + 'vw';
            piece.style.background = colors[Math.floor(Math.random() * colors.length)];
            piece.style.animationDuration = (2 + Math.random() * 1.5) + 's';
            piece.style.transform = `rotate(${Math.random() * 360}deg)`;
            layer.appendChild(piece);
            setTimeout(() => piece.remove(), 4000);
        }
    }

    /* ---------- Post-spin: choice modal ---------- */

    let pendingWinner = null;

    function onSpinComplete(winner) {
        spinning = false;
        pendingWinner = winner;
        spinCounts[winner.id] = (spinCounts[winner.id] || 0) + 1;

        fireConfetti();

        document.getElementById('winner-modal-name').innerText = winner.name;
        document.getElementById('winner-modal-meta').innerText = `${winner.phone} · ${winner.district} · ${winner.batch_name}`;
        document.getElementById('winnerModal').classList.add('open');
    }

    async function resolveWinner(action) {
        if (!pendingWinner) return;
        const winner = pendingWinner;

        const res = await fetch('/api/record_winner.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ entry_id: winner.id, action })
        });
        const data = await res.json();

        if (data.status !== 'success') {
            showToast(data.message || 'Failed to record decision — try again');
            document.getElementById('winnerModal').classList.remove('open');
            document.getElementById('spin-btn').disabled = currentPool.length === 0;
            return;
        }

        if (action === 'remove') {
            currentPool = currentPool.filter(e => e.id !== winner.id);
            document.getElementById('pool-count-label').innerText = `${currentPool.length} eligible entries loaded`;
            drawWheel(currentPool, wheelRotation);
        }

        logSessionWinner(winner, action);

        document.getElementById('winnerModal').classList.remove('open');
        pendingWinner = null;
        document.getElementById('spin-btn').disabled = currentPool.length === 0;
    }

    document.getElementById('btn-remove-winner').addEventListener('click', () => resolveWinner('remove'));
    document.getElementById('btn-keep-winner').addEventListener('click', () => resolveWinner('keep_eligible'));

    function logSessionWinner(winner, action) {
        const log = document.getElementById('winners-log');
        const row = document.createElement('div');
        row.className = 'winner-row';
        const actionBadge = action === 'remove'
            ? '<span class="badge badge-removed">Recorded</span>'
            : '<span class="badge badge-kept">Kept Eligible</span>';
        row.innerHTML = `
            <span><strong>${escapeHtml(winner.name)}</strong> — ${escapeHtml(winner.phone)} — ${escapeHtml(winner.district)} <span style="color:#FF9900;font-size:10.5px;font-weight:700;">${escapeHtml(winner.batch_name)}</span></span>
            <span>Spin #${spinCounts[winner.id]} ${actionBadge}</span>
        `;
        log.prepend(row);
        document.getElementById('winners-section').style.display = 'block';
    }

    drawWheel([], 0);
</script>
</body>
</html>
