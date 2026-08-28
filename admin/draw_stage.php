<?php
// admin/draw_stage.php — stream-only stage view: wheel + spin button + winner modal, nothing else.
// Meant to be popped out from admin/draw.php and shared on-screen for a live draw broadcast.
require_once __DIR__ . '/../includes/auth.php';

check_access(['admin', 'draw_manager']);

$batchIds = array_values(array_filter(array_map('intval', explode(',', (string) ($_GET['batch_ids'] ?? ''))), fn($id) => $id > 0));
$targetDistrict = trim((string) ($_GET['target_district'] ?? ''));
$targetCity     = trim((string) ($_GET['target_city'] ?? ''));
$targetDealer   = trim((string) ($_GET['target_dealer'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Draw | Welloo Bonanza</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, sans-serif; }
        html, body { height: 100%; overflow-x: hidden; }
        body { background: #0F0F0F; color: #FFF; display: flex; align-items: center; justify-content: center; padding: 24px; }

        .stage { width: 100%; max-width: 520px; text-align: center; }
        .stage-brand { color: #FF6600; font-weight: 900; font-size: 15px; letter-spacing: 0.4px; margin-bottom: 18px; text-transform: uppercase; }

        .pool-box { background: #1A1A1A; border: 1px solid #333; border-radius: 14px; padding: 28px; }
        #pool-count-label { color: #DDD; font-size: 13px; font-weight: 700; margin-bottom: 16px; }

        .wheel-stage { position: relative; width: 420px; max-width: 100%; aspect-ratio: 1 / 1; height: auto; margin: 0 auto 24px; }
        #wheel-canvas { display: block; width: 100%; height: 100%; border-radius: 50%; box-shadow: 0 0 0 6px #1A1A1A, 0 0 0 8px #FF6600, 0 10px 40px rgba(0,0,0,0.6); }

        .spin-actions { text-align: center; margin-top: 4px; }
        .btn-wheel { background: linear-gradient(180deg, #FF6600 0%, #D64F00 100%); color: #000; border: none; font-weight: bold; font-size: 18px; padding: 18px 34px; border-radius: 8px; text-transform: uppercase; cursor: pointer; box-shadow: 0 4px 20px rgba(255,102,0,0.4); }
        .btn-wheel:disabled { opacity: 0.4; cursor: not-allowed; }

        @media (max-width: 520px) {
            body { padding: 14px; }
            .pool-box { padding: 18px; }
            .wheel-stage { width: 100%; margin-bottom: 18px; }
            .spin-actions { display: flex; flex-direction: column; align-items: stretch; gap: 10px; }
            .btn-wheel { width: 100%; font-size: 16px; padding: 16px 20px; }
        }

        /* Winner modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); display: none; justify-content: center; align-items: center; z-index: 400; padding: 20px; }
        .modal-overlay.open { display: flex; }
        .winner-modal-box { background: #1A1A1A; border: 2px solid #FF6600; border-radius: 14px; max-width: 460px; width: 100%; padding: 34px; text-align: center; box-shadow: 0 0 60px rgba(255,102,0,0.45); }
        .winner-modal-box .emoji { font-size: 48px; margin-bottom: 8px; }
        .winner-modal-box h2 { color: #FF9900; font-size: 26px; margin-bottom: 8px; }
        .winner-modal-box .winner-meta { color: #DDD; font-size: 16px; margin-bottom: 26px; line-height: 1.7; }
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

        #fullscreen-btn { position: fixed; top: 16px; right: 16px; background: #1A1A1A; border: 1px solid #333; color: #AAA; padding: 8px 12px; border-radius: 8px; font-size: 12px; cursor: pointer; z-index: 200; opacity: 0.6; transition: opacity 0.2s ease; }
        #fullscreen-btn:hover { opacity: 1; border-color: #FF6600; color: #FF9900; }
    </style>
</head>
<body>
    <button type="button" id="fullscreen-btn" onclick="toggleFullscreen()">⛶ Fullscreen</button>
    <div class="stage">
        <div class="stage-brand">Welloo Bonanza — Live Draw</div>
        <div class="pool-box">
            <div id="pool-count-label">Loading pool…</div>
            <div class="wheel-stage">
                <canvas id="wheel-canvas" width="420" height="420"></canvas>
            </div>
            <div class="spin-actions">
                <button class="btn-wheel" id="spin-btn" onclick="DrawWheel.spinWheel()" disabled>🎡 SPIN NOW</button>
            </div>
        </div>
    </div>

    <!-- No admin controls, filters, batch details, nav, or phone numbers on this view — stream-safe by design. -->

    <!-- Winner modal -->
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

    <!-- Target criteria carried over from the Draw Manager launch — hidden on the stage view. -->
    <input type="hidden" id="target-district" value="<?= htmlspecialchars($targetDistrict) ?>">
    <input type="hidden" id="target-city" value="<?= htmlspecialchars($targetCity) ?>">
    <input type="hidden" id="target-dealer" value="<?= htmlspecialchars($targetDealer) ?>">

    <div id="confetti-layer"></div>
    <div id="toast"></div>

    <script src="/admin/assets/draw-wheel.js"></script>
    <script>
        /* ---------- Fullscreen presentation mode ---------- */

        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen();
            }
        }

        document.addEventListener('fullscreenchange', function () {
            const btn = document.getElementById('fullscreen-btn');
            btn.innerText = document.fullscreenElement ? '⛶ Exit Fullscreen' : '⛶ Fullscreen';
        });

        document.querySelector('.wheel-stage').addEventListener('dblclick', toggleFullscreen);

        const STAGE_BATCH_IDS = <?= json_encode($batchIds) ?>;

        DrawWheel.onPoolLoaded = function (pool) {
            document.getElementById('pool-count-label').innerText = `${pool.length} eligible entries loaded`;
            document.getElementById('spin-btn').disabled = pool.length === 0;
        };

        DrawWheel.onError = function (message) {
            document.getElementById('pool-count-label').innerText = message || 'Failed to load draw pool';
        };

        DrawWheel.onSpinStart = function () {
            document.getElementById('spin-btn').disabled = true;
        };

        DrawWheel.onSpinSettled = function () {
            document.getElementById('spin-btn').disabled = DrawWheel.getPool().length === 0;
            document.getElementById('pool-count-label').innerText = `${DrawWheel.getPool().length} eligible entries loaded`;
        };

        if (STAGE_BATCH_IDS.length === 0) {
            document.getElementById('pool-count-label').innerText = 'No batches selected — relaunch from Draw Manager';
        } else {
            DrawWheel.loadPool(STAGE_BATCH_IDS);
        }
    </script>
</body>
</html>
