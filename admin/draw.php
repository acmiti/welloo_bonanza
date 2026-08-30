<?php
require_once __DIR__ . '/../includes/auth.php';

check_access(['admin', 'draw_manager']);

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

$stmt = $pdo->query(
    "SELECT b.id, b.batch_name, b.status, b.entry_deadline, b.draw_datetime,
            COUNT(e.id) AS total_entries,
            COALESCE(SUM(CASE WHEN e.is_winner = 0 THEN e.multiplier ELSE 0 END), 0) AS eligible_entries
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
        html, body { overflow-x: hidden; }
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

        .wheel-stage { position: relative; width: 340px; max-width: 100%; aspect-ratio: 1 / 1; height: auto; margin: 0 auto 20px; }
        #wheel-canvas { display: block; width: 100%; height: 100%; border-radius: 50%; box-shadow: 0 0 0 6px #1A1A1A, 0 0 0 8px #FF6600, 0 10px 40px rgba(0,0,0,0.6); }

        .spin-actions { text-align: center; margin-top: 4px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }

        @media (max-width: 520px) {
            body { padding: 12px; }
            .wheel-stage { width: 100%; }
            .pool-box { padding: 16px; }
            .spin-actions { flex-direction: column; align-items: stretch; }
            .spin-actions .btn { width: 100%; }
        }

        /* Target criteria panel (admin-only pre-selection) */
        .criteria-box { background: #1A1A1A; border: 1px dashed #4DA6FF; border-radius: 10px; padding: 18px 22px; margin-top: 14px; }
        .criteria-box .criteria-title { color: #4DA6FF; font-size: 12.5px; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }
        .criteria-box .criteria-hint { color: #777; font-size: 11.5px; margin-bottom: 14px; }
        .criteria-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
        .criteria-fields label { display: block; color: #AAA; font-size: 11px; text-transform: uppercase; margin-bottom: 5px; }
        .criteria-fields select { width: 100%; background: #0F0F0F; border: 1px solid #333; color: #FFF; padding: 9px 10px; border-radius: 6px; font-size: 13px; }
        .criteria-fields select:focus { outline: none; border-color: #4DA6FF; }

        /* Advanced draw-pool filters (collapsible) */
        .pool-filters { background: #1A1A1A; border: 1px solid #333; border-radius: 10px; margin-top: 14px; overflow: hidden; }
        .pool-filters > .pf-toggle { width: 100%; background: #202020; border: none; color: #FF9900; font-size: 12.5px; font-weight: 700; text-transform: uppercase; text-align: left; padding: 14px 18px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .pool-filters > .pf-toggle .pf-caret { transition: transform 0.2s ease; }
        .pool-filters.open > .pf-toggle .pf-caret { transform: rotate(90deg); }
        .pool-filters .pf-body { display: none; padding: 18px; }
        .pool-filters.open .pf-body { display: block; }
        .pf-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; }
        .pf-field { background: #0F0F0F; border: 1px solid #333; border-radius: 8px; padding: 12px; }
        .pf-field .pf-name { color: #DDD; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 8px; }
        .pf-mode { display: inline-flex; border: 1px solid #333; border-radius: 6px; overflow: hidden; margin-bottom: 10px; }
        .pf-mode button { background: #1A1A1A; color: #888; border: none; padding: 6px 10px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; cursor: pointer; }
        .pf-mode button.active[data-mode="include"] { background: rgba(37,211,102,0.18); color: #25D366; }
        .pf-mode button.active[data-mode="exclude"] { background: rgba(255,102,0,0.18); color: #FF6600; }
        .pf-search { width: 100%; background: #1A1A1A; border: 1px solid #333; color: #FFF; padding: 8px 10px; border-radius: 6px; font-size: 12.5px; }
        .pf-search:focus { outline: none; border-color: #4DA6FF; }
        .pf-menu { max-height: 150px; overflow-y: auto; border: 1px solid #2A2A2A; border-radius: 6px; margin-top: 6px; display: none; }
        .pf-field.searching .pf-menu { display: block; }
        .pf-opt { padding: 6px 10px; font-size: 12px; color: #CCC; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .pf-opt:hover { background: #202020; }
        .pf-opt.selected { color: #4DA6FF; }
        .pf-opt.empty { color: #666; cursor: default; }
        .pf-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .pf-chip { background: #262626; border: 1px solid #444; color: #DDD; border-radius: 12px; padding: 3px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 6px; }
        .pf-chip button { background: none; border: none; color: #FF6600; cursor: pointer; font-size: 13px; line-height: 1; padding: 0; }
        .pf-actions { margin-top: 16px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .pf-actions .pf-summary { color: #888; font-size: 11.5px; }

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
                        <td><input type="checkbox" class="batch-check" data-id="<?= (int) $b['id'] ?>" data-eligible="<?= (int) $b['eligible_entries'] ?>" data-deadline="<?= htmlspecialchars($b['entry_deadline']) ?>" data-deadline-fmt="<?= htmlspecialchars(date('M j, Y, g:i A', strtotime($b['entry_deadline']))) ?>" onchange="updateSelectionSummary()"></td>
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
            <div class="criteria-hint">Optional pre-selection — narrows the eligible pool before a spin. Leave blank for a fully random draw. Falls back to the full pool automatically if nothing matches. Entries with a bulk multiplier get proportionally more slices/odds in the pool.</div>
            <div class="criteria-fields">
                <div>
                    <label for="target-district">Target District</label>
                    <select id="target-district"><option value="">All (Fully Random)</option></select>
                </div>
                <div>
                    <label for="target-city">Target City / Town</label>
                    <select id="target-city"><option value="">All (Fully Random)</option></select>
                </div>
                <div>
                    <label for="target-dealer">Target Dealer</label>
                    <select id="target-dealer"><option value="">All (Fully Random)</option></select>
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

        <div class="pool-filters" id="pool-filters">
            <button type="button" class="pf-toggle" onclick="document.getElementById('pool-filters').classList.toggle('open')">
                <span>⚙ Advanced Draw Pool Filters</span>
                <span class="pf-caret">▸</span>
            </button>
            <div class="pf-body">
                <div class="pf-grid">
                    <div class="pf-field" data-field="district" data-column="districts">
                        <div class="pf-name">District</div>
                        <div class="pf-mode">
                            <button type="button" class="active" data-mode="include">Include (Only)</button>
                            <button type="button" data-mode="exclude">Exclude</button>
                        </div>
                        <input type="text" class="pf-search" placeholder="Search districts…">
                        <div class="pf-menu"></div>
                        <div class="pf-chips"></div>
                    </div>
                    <div class="pf-field" data-field="city" data-column="cities">
                        <div class="pf-name">City / Town</div>
                        <div class="pf-mode">
                            <button type="button" class="active" data-mode="include">Include (Only)</button>
                            <button type="button" data-mode="exclude">Exclude</button>
                        </div>
                        <input type="text" class="pf-search" placeholder="Search cities / towns…">
                        <div class="pf-menu"></div>
                        <div class="pf-chips"></div>
                    </div>
                    <div class="pf-field" data-field="dealer" data-column="dealers">
                        <div class="pf-name">Dealer</div>
                        <div class="pf-mode">
                            <button type="button" class="active" data-mode="include">Include (Only)</button>
                            <button type="button" data-mode="exclude">Exclude</button>
                        </div>
                        <input type="text" class="pf-search" placeholder="Search dealers…">
                        <div class="pf-menu"></div>
                        <div class="pf-chips"></div>
                    </div>
                </div>
                <div class="pf-actions">
                    <button class="btn" type="button" onclick="loadPool()">Apply Filters &amp; Reload Pool</button>
                    <button class="btn btn-secondary" type="button" onclick="clearPoolFilters()">Clear</button>
                    <span class="pf-summary" id="pf-summary">No filters applied — full eligible pool.</span>
                </div>
            </div>
        </div>

        <div class="pool-box">
            <div id="pool-count-label">No entries loaded yet</div>
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
        // Reveal section 2 as soon as a batch is picked so the filter panel is reachable before loading.
        if (checked.length > 0) {
            const ps = document.getElementById('pool-section');
            if (ps) ps.style.display = 'block';
        }
    }
    updateSelectionSummary();

    function selectedBatchIds() {
        return [...document.querySelectorAll('.batch-check:checked')].map(c => c.dataset.id);
    }

    // Cut-off = the latest entry deadline among the selected batches, taken
    // straight from the batch config and pre-formatted server-side.
    let currentCutoffLabel = null;

    function selectedCutoffLabel() {
        let bestRaw = null, bestFmt = null;
        [...document.querySelectorAll('.batch-check:checked')].forEach(c => {
            const raw = c.dataset.deadline;
            if (raw && (bestRaw === null || raw > bestRaw)) {
                bestRaw = raw;
                bestFmt = c.dataset.deadlineFmt;
            }
        });
        return bestFmt;
    }

    function renderPoolCountLabel() {
        const el = document.getElementById('pool-count-label');
        if (!el) return;
        el.innerText = currentCutoffLabel
            ? `All entries submitted until ${currentCutoffLabel} loaded`
            : 'All submitted entries loaded';
    }

    async function loadPool() {
        const batchIds = selectedBatchIds();
        if (batchIds.length === 0) return;
        currentCutoffLabel = selectedCutoffLabel();
        document.getElementById('pool-section').style.display = 'block';
        await DrawWheel.loadPool(batchIds, collectPoolFilters());
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

        window.open('/admin/draw_stage.php?' + params.toString(), 'StreamView', 'width=1280,height=720,menubar=no,toolbar=no,location=no,status=no,resizable=yes,noopener');
    }

    /* ---------- Target criteria: bi-directional cascading dropdowns ---------- */

    let criteriaTriples = [];

    async function initCriteriaDropdowns() {
        const districtSelect = document.getElementById('target-district');
        if (!districtSelect || districtSelect.tagName !== 'SELECT') return; // non-admin: hidden inputs, nothing to wire up

        try {
            const res = await fetch('/api/get_filter_options.php?mode=triples', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (data.status === 'success') criteriaTriples = data.triples;
        } catch (e) { /* leave dropdowns empty-but-usable if this fails */ }

        applyCascade(null);

        districtSelect.addEventListener('change', () => applyCascade('district'));
        document.getElementById('target-city')?.addEventListener('change', () => applyCascade('city'));
        document.getElementById('target-dealer')?.addEventListener('change', () => applyCascade('dealer'));
    }

    function uniqueSorted(values) {
        return [...new Set(values.filter(v => v !== '' && v != null))].sort();
    }

    function populateCriteriaSelect(select, values, selectedValue) {
        const current = select.value;
        select.innerHTML = '';
        const allOpt = document.createElement('option');
        allOpt.value = '';
        allOpt.innerText = 'All (Fully Random)';
        select.appendChild(allOpt);
        values.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v;
            opt.innerText = v;
            select.appendChild(opt);
        });
        const target = selectedValue !== undefined ? selectedValue : current;
        select.value = values.includes(target) ? target : '';
        return select.value;
    }

    function applyCascade(source) {
        const districtSelect = document.getElementById('target-district');
        const citySelect = document.getElementById('target-city');
        const dealerSelect = document.getElementById('target-dealer');
        if (!districtSelect || !citySelect || !dealerSelect) return;

        let sel = {
            district: districtSelect.value,
            city: citySelect.value,
            dealer: dealerSelect.value,
        };

        // Auto-select the parent(s) implied by a more specific pick.
        if (source === 'city' && sel.city) {
            const matches = criteriaTriples.filter(t => t.town === sel.city);
            const districts = uniqueSorted(matches.map(t => t.district));
            if (districts.length === 1) sel.district = districts[0];
        }
        if (source === 'dealer' && sel.dealer) {
            const matches = criteriaTriples.filter(t => t.dealer === sel.dealer);
            const districts = uniqueSorted(matches.map(t => t.district));
            const towns = uniqueSorted(matches.map(t => t.town));
            if (districts.length === 1) sel.district = districts[0];
            if (towns.length === 1) sel.city = towns[0];
        }

        const districtOptions = uniqueSorted(criteriaTriples
            .filter(t => (!sel.city || t.town === sel.city) && (!sel.dealer || t.dealer === sel.dealer))
            .map(t => t.district));
        const townOptions = uniqueSorted(criteriaTriples
            .filter(t => (!sel.district || t.district === sel.district) && (!sel.dealer || t.dealer === sel.dealer))
            .map(t => t.town));
        const dealerOptions = uniqueSorted(criteriaTriples
            .filter(t => (!sel.district || t.district === sel.district) && (!sel.city || t.town === sel.city))
            .map(t => t.dealer));

        sel.district = populateCriteriaSelect(districtSelect, districtOptions, sel.district);
        sel.city = populateCriteriaSelect(citySelect, townOptions, sel.city);
        sel.dealer = populateCriteriaSelect(dealerSelect, dealerOptions, sel.dealer);
    }

    initCriteriaDropdowns();

    /* ---------- Advanced draw-pool filters: searchable multi-select + include/exclude ---------- */

    const poolFilterState = {
        district: { mode: 'include', selected: new Set(), options: [] },
        city:     { mode: 'include', selected: new Set(), options: [] },
        dealer:   { mode: 'include', selected: new Set(), options: [] },
    };

    async function initPoolFilters() {
        let triples = [];
        try {
            const res = await fetch('/api/get_filter_options.php?mode=triples', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (data.status === 'success') triples = data.triples || [];
        } catch (e) { /* leave options empty — filters just won't offer suggestions */ }

        poolFilterState.district.options = uniqueSorted(triples.map(t => t.district));
        poolFilterState.city.options     = uniqueSorted(triples.map(t => t.town));
        poolFilterState.dealer.options   = uniqueSorted(triples.map(t => t.dealer));

        document.querySelectorAll('#pool-filters .pf-field').forEach(field => {
            const key = field.dataset.field;
            const search = field.querySelector('.pf-search');
            const menu = field.querySelector('.pf-menu');

            field.querySelectorAll('.pf-mode button').forEach(btn => {
                btn.addEventListener('click', () => {
                    field.querySelectorAll('.pf-mode button').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    poolFilterState[key].mode = btn.dataset.mode;
                    renderPoolFilterSummary();
                });
            });

            search.addEventListener('input', () => renderPoolFilterMenu(field));
            search.addEventListener('focus', () => { field.classList.add('searching'); renderPoolFilterMenu(field); });
            search.addEventListener('blur', () => setTimeout(() => field.classList.remove('searching'), 150));

            menu.addEventListener('mousedown', e => {
                const opt = e.target.closest('.pf-opt');
                if (!opt || opt.classList.contains('empty')) return;
                e.preventDefault();
                const val = opt.dataset.value;
                const set = poolFilterState[key].selected;
                set.has(val) ? set.delete(val) : set.add(val);
                search.value = '';
                renderPoolFilterMenu(field);
                renderPoolFilterChips(field);
                renderPoolFilterSummary();
            });

            renderPoolFilterChips(field);
        });
        renderPoolFilterSummary();
    }

    function renderPoolFilterMenu(field) {
        const key = field.dataset.field;
        const st = poolFilterState[key];
        const q = field.querySelector('.pf-search').value.trim().toLowerCase();
        const matches = st.options.filter(o => o.toLowerCase().includes(q)).slice(0, 50);
        const menu = field.querySelector('.pf-menu');
        if (matches.length === 0) {
            menu.innerHTML = '<div class="pf-opt empty">No matches</div>';
            return;
        }
        menu.innerHTML = matches.map(o => {
            const sel = st.selected.has(o) ? ' selected' : '';
            return `<div class="pf-opt${sel}" data-value="${DrawWheel.escapeHtml(o)}">${st.selected.has(o) ? '☑' : '☐'} ${DrawWheel.escapeHtml(o)}</div>`;
        }).join('');
    }

    function renderPoolFilterChips(field) {
        const key = field.dataset.field;
        const chips = field.querySelector('.pf-chips');
        chips.innerHTML = [...poolFilterState[key].selected].map(v =>
            `<span class="pf-chip">${DrawWheel.escapeHtml(v)}<button type="button" data-value="${DrawWheel.escapeHtml(v)}">×</button></span>`
        ).join('');
        chips.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                poolFilterState[key].selected.delete(btn.dataset.value);
                renderPoolFilterChips(field);
                renderPoolFilterMenu(field);
                renderPoolFilterSummary();
            });
        });
    }

    function renderPoolFilterSummary() {
        const el = document.getElementById('pf-summary');
        if (!el) return;
        const parts = [];
        [['district', 'District'], ['city', 'City'], ['dealer', 'Dealer']].forEach(([key, label]) => {
            const st = poolFilterState[key];
            if (st.selected.size > 0) {
                parts.push(`${st.mode === 'exclude' ? 'Exclude' : 'Only'} ${st.selected.size} ${label}${st.selected.size > 1 ? 's' : ''}`);
            }
        });
        el.innerText = parts.length ? parts.join(' · ') : 'No filters applied — full eligible pool.';
    }

    function collectPoolFilters() {
        const map = { district: 'districts', city: 'cities', dealer: 'dealers' };
        const out = {};
        Object.entries(map).forEach(([key, col]) => {
            const st = poolFilterState[key];
            const vals = [...st.selected];
            out['include_' + col] = st.mode === 'include' ? vals : [];
            out['exclude_' + col] = st.mode === 'exclude' ? vals : [];
        });
        return out;
    }

    function clearPoolFilters() {
        Object.values(poolFilterState).forEach(st => { st.selected.clear(); st.mode = 'include'; });
        document.querySelectorAll('#pool-filters .pf-field').forEach(field => {
            field.querySelectorAll('.pf-mode button').forEach(b => b.classList.toggle('active', b.dataset.mode === 'include'));
            field.querySelector('.pf-search').value = '';
            renderPoolFilterChips(field);
        });
        renderPoolFilterSummary();
    }

    initPoolFilters();

    /* ---------- Wire up shared wheel engine hooks ---------- */

    DrawWheel.onPoolLoaded = function (pool) {
        renderPoolCountLabel(pool.length);
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
        renderPoolCountLabel(DrawWheel.getPool().length);

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
