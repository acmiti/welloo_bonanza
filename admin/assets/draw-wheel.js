// admin/assets/draw-wheel.js — shared spinning-wheel engine for the Draw Manager
// and its stream-only stage view. Pool loading, winner selection, the physics-based
// spin animation, and the winner modal all live here so both pages stay in sync.
(function () {
    const WHEEL_COLORS = ['#B8860B', '#D64F00', '#2A2A2A', '#FF6600'];

    let currentPool = [];
    let batchIds = [];
    let wheelRotation = 0;
    let spinning = false;
    let pendingWinner = null;
    const spinCounts = {};
    let audioCtx = null;

    // Central hub live tracker state
    let hubEntry = null;      // entry currently under the top pointer
    let hubIndex = -1;        // pool index of the entry in the reel's centre row
    let hubLocked = false;    // true once the wheel stops on the winner
    let hubGlow = 0;          // 0..1 pulse intensity for the winner-lock highlight

    /* ---------- utils ---------- */

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.innerText = str ?? '';
        return div.innerHTML;
    }

    function showToast(message) {
        const toast = document.getElementById('toast');
        if (!toast) return;
        toast.innerText = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2500);
    }

    function getCriteria() {
        const district = (document.getElementById('target-district')?.value || '').trim();
        const city = (document.getElementById('target-city')?.value || '').trim();
        const dealer = (document.getElementById('target-dealer')?.value || '').trim();
        const criteria = {};
        if (district) criteria.district = district;
        if (city) criteria.city = city;
        if (dealer) criteria.dealer = dealer;
        return criteria;
    }

    /* ---------- canvas wheel ---------- */

    function sliceAngleFor(poolSize) { return (Math.PI * 2) / poolSize; }
    function normalizeAngle(a) {
        const twoPi = Math.PI * 2;
        return ((a % twoPi) + twoPi) % twoPi;
    }

    function drawWheel(pool, rotation) {
        const canvas = document.getElementById('wheel-canvas');
        if (!canvas) return;
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

        // Central hub live tracker — keep it synced to whatever slice is under the pointer
        if (!hubLocked) {
            hubIndex = sliceIndexAtPointer(pool.length, rotation);
            hubEntry = pool[hubIndex] || null;
        }
        drawHub(ctx, cx, cy, radius, pool, hubIndex);

        // Fixed pointer at top, pointing down — scales with the canvas
        const k = size / 340;
        ctx.beginPath();
        ctx.moveTo(cx - 14 * k, 2 * k);
        ctx.lineTo(cx + 14 * k, 2 * k);
        ctx.lineTo(cx, 30 * k);
        ctx.closePath();
        ctx.fillStyle = '#FF9900';
        ctx.fill();
        ctx.strokeStyle = '#0F0F0F';
        ctx.lineWidth = 1.5 * k;
        ctx.stroke();
    }

    /* ---------- keep the canvas backing store matched to its displayed size ---------- */

    function resizeCanvas() {
        const canvas = document.getElementById('wheel-canvas');
        if (!canvas) return;
        const rect = canvas.getBoundingClientRect();
        if (!rect.width) return;
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const target = Math.max(240, Math.round(rect.width * dpr));
        if (canvas.width !== target) {
            canvas.width = target;
            canvas.height = target;
        }
        drawWheel(currentPool, wheelRotation);
    }

    window.addEventListener('resize', () => {
        clearTimeout(resizeCanvas._t);
        resizeCanvas._t = setTimeout(resizeCanvas, 120);
    });

    /* ---------- central hub: enlarged live digital display ---------- */

    function roundRectPath(ctx, x, y, w, h, r) {
        if (typeof ctx.roundRect === 'function') {
            ctx.beginPath();
            ctx.roundRect(x, y, w, h, r);
            return;
        }
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function fitText(ctx, text, maxWidth) {
        if (ctx.measureText(text).width <= maxWidth) return text;
        let t = text;
        while (t.length > 1 && ctx.measureText(t + '…').width > maxWidth) {
            t = t.slice(0, -1);
        }
        return t + '…';
    }

    // One row of the slot reel. The centre row renders full-height with a gold
    // border and bold text; the prev/next rows render muted and get visually
    // clipped by the caller's reel window so only ~half of each one shows.
    function drawHubRow(ctx, cx, y, w, rowH, entry, s, opts) {
        const isCenter = !!opts.center;
        ctx.save();
        ctx.globalAlpha = isCenter ? 1 : 0.4;

        if (isCenter) {
            roundRectPath(ctx, cx - w / 2 + 4 * s, y - rowH / 2, w - 8 * s, rowH, 8 * s);
            ctx.fillStyle = 'rgba(22,15,2,0.92)';
            ctx.fill();
            ctx.lineWidth = 2.5 * s;
            ctx.strokeStyle = opts.locked
                ? 'rgba(255,153,0,' + (0.6 + 0.4 * hubGlow).toFixed(3) + ')'
                : '#FFB300';
            ctx.stroke();
        }

        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        const maxW = w - 26 * s;

        if (!entry) {
            ctx.fillStyle = '#777';
            ctx.font = (13 * s).toFixed(1) + 'px Segoe UI, sans-serif';
            ctx.fillText('—', cx, y);
            ctx.restore();
            return;
        }

        const idxLabel = (entry.entry_no || entry.id) ? '#' + (entry.entry_no || entry.id) + '  ' : '';

        // Line 1: entry number + name
        ctx.fillStyle = isCenter ? (opts.locked ? '#FFFFFF' : '#FF9900') : '#C8C8C8';
        ctx.font = (isCenter ? 'bold ' : '') + ((isCenter ? 16 : 12.5) * s).toFixed(1) + 'px Segoe UI, sans-serif';
        ctx.fillText(fitText(ctx, idxLabel + (entry.name || ''), maxW), cx, y - (isCenter ? 7 : 5) * s);

        // Line 2: town — district
        ctx.fillStyle = isCenter ? (opts.locked ? '#FF9900' : '#FFFFFF') : '#888';
        ctx.font = ((isCenter ? 11.5 : 10) * s).toFixed(1) + 'px Segoe UI, sans-serif';
        const loc = [entry.town, entry.district].filter(Boolean).join(' — ');
        ctx.fillText(fitText(ctx, loc, maxW), cx, y + (isCenter ? 9 : 7) * s);

        ctx.restore();
    }

    function drawHub(ctx, cx, cy, radius, pool, centerIdx) {
        const s = radius / 164; // scale factor relative to the 340px canvas
        const rowH = radius * 0.34;
        const boxW = radius * 1.34;
        const boxH = rowH * 2; // centre row fully shown; prev/next clipped to ~half
        const x = cx - boxW / 2;
        const y = cy - boxH / 2;

        ctx.save();

        // Winner-lock glow
        if (hubLocked && hubGlow > 0) {
            ctx.shadowColor = 'rgba(255,153,0,' + (0.85 * hubGlow).toFixed(3) + ')';
            ctx.shadowBlur = 34 * s * hubGlow;
        }

        roundRectPath(ctx, x, y, boxW, boxH, 12 * s);
        ctx.fillStyle = 'rgba(8,8,8,0.9)';
        ctx.fill();
        ctx.shadowBlur = 0;
        ctx.lineWidth = (hubLocked ? 3 : 2) * s;
        ctx.strokeStyle = hubLocked
            ? 'rgba(255,153,0,' + (0.55 + 0.45 * hubGlow).toFixed(3) + ')'
            : '#FF6600';
        ctx.stroke();

        // Clip everything else to the reel window so prev/next rows are cut off.
        roundRectPath(ctx, x, y, boxW, boxH, 12 * s);
        ctx.clip();

        const n = pool ? pool.length : 0;
        let prev = null, curr = null, next = null;
        if (n > 0 && centerIdx != null && centerIdx >= 0) {
            curr = pool[centerIdx % n] || null;
            prev = pool[(centerIdx - 1 + n) % n] || null;
            next = pool[(centerIdx + 1) % n] || null;
        }

        drawHubRow(ctx, cx, cy - rowH, boxW, rowH, prev, s, { center: false });
        drawHubRow(ctx, cx, cy + rowH, boxW, rowH, next, s, { center: false });
        drawHubRow(ctx, cx, cy, boxW, rowH, curr, s, { center: true, locked: hubLocked });

        if (hubLocked && curr) {
            ctx.globalAlpha = 1;
            ctx.fillStyle = 'rgba(255,153,0,' + (0.5 + 0.5 * hubGlow).toFixed(3) + ')';
            ctx.font = 'bold ' + (8.5 * s).toFixed(1) + 'px Segoe UI, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('★ WINNER ★', cx, cy + rowH * 0.62);
        }

        ctx.restore();
    }

    /* ---------- spin animation: 0-5s multi-revolution spin-up, 5-10s cubic ease-out ---------- */

    function easeOutCubic(x) { return 1 - Math.pow(1 - x, 3); }

    function spinProgress(t) {
        if (t <= 0.5) {
            return 1.4 * t; // constant angular velocity for the first 5s (multiple full revolutions)
        }
        return 0.7 + 0.3 * easeOutCubic((t - 0.5) / 0.5); // organic cubic deceleration over the final 5s
    }

    /* ---------- Web Audio: synthesized tick + fanfare ---------- */

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

    /* ---------- Confetti ---------- */

    function fireConfetti() {
        const layer = document.getElementById('confetti-layer');
        if (!layer) return;
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

    /* ---------- pool loading ---------- */

    async function loadPool(ids) {
        batchIds = ids;
        const res = await fetch('/api/get_draw_pool.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ batch_ids: batchIds })
        });
        const data = await res.json();

        if (data.status !== 'success') {
            showToast(data.message || 'Failed to load draw pool');
            window.DrawWheel.onError(data.message || 'Failed to load draw pool');
            return;
        }

        currentPool = data.pool;
        wheelRotation = 0;
        hubLocked = false;
        hubGlow = 0;
        // Seed the stationary reel with real entries instead of a single blank row.
        hubIndex = currentPool.length ? sliceIndexAtPointer(currentPool.length, 0) : -1;
        hubEntry = currentPool[hubIndex] || null;
        resizeCanvas();
        drawWheel(currentPool, wheelRotation);
        window.DrawWheel.onPoolLoaded(currentPool);
    }

    /* ---------- spin: server picks the (possibly criteria-weighted) target, client animates to it ---------- */

    async function spinWheel() {
        if (spinning || currentPool.length === 0) return;
        spinning = true;
        hubLocked = false;
        hubGlow = 0;
        window.DrawWheel.onSpinStart();
        getAudioCtx(); // unlock audio on this user gesture

        let winner = null;
        let winningIndex = -1;
        try {
            const res = await fetch('/api/get_draw_winner.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ batch_ids: batchIds, criteria: getCriteria() })
            });
            const data = await res.json();
            if (data.status === 'success' && data.winner) {
                winningIndex = currentPool.findIndex(e => e.id === data.winner.id);
                if (winningIndex !== -1) winner = currentPool[winningIndex];
            }
        } catch (e) { /* fall through to local fallback below */ }

        if (!winner) {
            // Fallback keeps the show going even if the target-selection call fails.
            winningIndex = Math.floor(Math.random() * currentPool.length);
            winner = currentPool[winningIndex];
        }

        const sliceAngle = sliceAngleFor(currentPool.length);
        const center = winningIndex * sliceAngle + sliceAngle / 2;
        const desiredMod = normalizeAngle(-Math.PI / 2 - center);
        const currentMod = normalizeAngle(wheelRotation);
        let delta = desiredMod - currentMod;
        if (delta < 0) delta += Math.PI * 2;
        const extraSpins = 8 + Math.floor(Math.random() * 3); // 8-10 full turns during the spin-up
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
                playFanfare();
                lockHubOnWinner(winner);
            }
        }
        requestAnimationFrame(frame);
    }

    // Lock the central hub onto the winner and play a pulse/glow before the modal opens.
    function lockHubOnWinner(winner) {
        spinning = false;
        hubLocked = true;
        hubEntry = winner;
        hubIndex = currentPool.findIndex(e => e.id === winner.id);
        const holdMs = 1100;
        const t0 = performance.now();
        function pulse(now) {
            const elapsed = now - t0;
            // two eased pulses over the hold window, settling near full glow
            const cycle = Math.sin((elapsed / holdMs) * Math.PI * 2);
            hubGlow = 0.55 + 0.45 * Math.abs(cycle);
            drawWheel(currentPool, wheelRotation);
            if (elapsed < holdMs) {
                requestAnimationFrame(pulse);
            } else {
                hubGlow = 1;
                drawWheel(currentPool, wheelRotation);
                onSpinComplete(winner);
            }
        }
        requestAnimationFrame(pulse);
    }

    function onSpinComplete(winner) {
        pendingWinner = winner;
        spinCounts[winner.id] = (spinCounts[winner.id] || 0) + 1;

        fireConfetti();

        const nameEl = document.getElementById('winner-modal-name');
        const line1El = document.getElementById('winner-modal-line1');
        const line2El = document.getElementById('winner-modal-line2');
        if (nameEl) nameEl.innerText = winner.name;
        if (line1El) line1El.innerText = `${winner.town} City from ${winner.district} District`;
        if (line2El) line2El.innerText = `Dealer: ${winner.dealer}`;

        document.getElementById('winnerModal')?.classList.add('open');
        window.DrawWheel.onWinnerAnnounced(winner);
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
            document.getElementById('winnerModal')?.classList.remove('open');
            window.DrawWheel.onSpinSettled();
            return;
        }

        hubLocked = false;
        hubGlow = 0;

        if (action === 'remove') {
            currentPool = currentPool.filter(e => e.id !== winner.id);
        }
        // Re-seed the stationary reel from the (possibly trimmed) pool.
        hubIndex = currentPool.length ? sliceIndexAtPointer(currentPool.length, wheelRotation) : -1;
        hubEntry = currentPool[hubIndex] || null;
        drawWheel(currentPool, wheelRotation);

        window.DrawWheel.onWinnerResolved(winner, action, spinCounts[winner.id]);

        document.getElementById('winnerModal')?.classList.remove('open');
        pendingWinner = null;
        window.DrawWheel.onSpinSettled();
    }

    document.getElementById('btn-remove-winner')?.addEventListener('click', () => resolveWinner('remove'));
    document.getElementById('btn-keep-winner')?.addEventListener('click', () => resolveWinner('keep_eligible'));

    window.DrawWheel = {
        loadPool,
        spinWheel,
        resolveWinner,
        escapeHtml,
        showToast,
        getPool: () => currentPool,
        // Page-specific hooks — pages override these after this script loads.
        onPoolLoaded() {},
        onSpinStart() {},
        onWinnerAnnounced() {},
        onWinnerResolved() {},
        onSpinSettled() {},
        onError() {},
    };

    resizeCanvas();
    drawWheel([], 0);
})();
