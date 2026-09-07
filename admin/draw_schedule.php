<?php
// admin/draw_schedule.php — manage the Draw Scheduling & Auto-Rollover queue.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/draw_schedule.php';

check_access(['admin']);

// Bring the queue up to date with the current Asia/Colombo clock before rendering.
draw_schedule_rollover($pdo);

$rows = $pdo->query(
    "SELECT id, title, status, start_time, cutoff_time, filter_rules, created_at
       FROM draw_schedules
      ORDER BY start_time ASC, id ASC"
)->fetchAll();

$schedules = ['scheduled' => [], 'active' => [], 'completed' => []];
foreach ($rows as $r) {
    $schedules[$r['status']][] = draw_schedule_present($r);
}

$FILTER_FIELDS = [
    'inc_districts' => 'Include Districts',
    'inc_cities'    => 'Include Cities / Towns',
    'inc_dealers'   => 'Include Dealers',
    'exc_districts' => 'Exclude Districts',
    'exc_cities'    => 'Exclude Cities / Towns',
    'exc_dealers'   => 'Exclude Dealers',
];

function fmt_dt(?string $iso): string
{
    $ts = $iso ? strtotime($iso) : false;
    return $ts !== false ? date('M j, Y · g:i A', $ts) : '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require __DIR__ . '/../includes/gtag.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Draw Schedule | Welloo Bonanza Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, sans-serif; }
        body { background: #0F0F0F; color: #FFF; padding: 20px; }
        .wrapper { max-width: 1150px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding-bottom: 16px; border-bottom: 1px solid #222; flex-wrap: wrap; gap: 12px; }
        h1 { color: #FF6600; font-size: 24px; }
        h2.section-title { color: #FF9900; font-size: 14px; margin: 26px 0 12px; text-transform: uppercase; letter-spacing: 0.4px; }
        .tz-note { color: #888; font-size: 12px; margin: 10px 0 0; }
        .tz-note strong { color: #4DA6FF; }

        .btn { background: #FF6600; color: #000; border: none; padding: 10px 18px; font-weight: bold; border-radius: 6px; font-size: 14px; cursor: pointer; }
        .btn-secondary { background: #333; color: #FFF; }
        .btn-sm { padding: 6px 10px; font-size: 11px; }

        .card { background: #1A1A1A; border: 1px solid #333; border-radius: 10px; padding: 20px; margin-top: 14px; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 12px; color: #DDD; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .form-group input, .form-group select { padding: 10px 12px; background: #141414; border: 1px solid #333; border-radius: 6px; color: #FFF; font-size: 14px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: #FF6600; }
        .form-group .hint { font-size: 11px; color: #777; font-weight: 400; text-transform: none; }

        .filters-fieldset { border: 1px dashed #3A3A3A; border-radius: 8px; padding: 14px; margin-top: 16px; }
        .filters-fieldset legend { color: #4DA6FF; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 0 6px; }

        .form-actions { display: flex; gap: 10px; margin-top: 18px; align-items: center; flex-wrap: wrap; }
        .form-error { display: none; background: rgba(255, 51, 51, 0.1); border: 1px solid #FF3333; color: #FF6B6B; font-size: 12px; font-weight: 600; padding: 8px 10px; border-radius: 6px; margin-bottom: 12px; }

        table { width: 100%; border-collapse: collapse; background: #1A1A1A; border-radius: 10px; overflow: hidden; border: 1px solid #333; }
        th, td { padding: 12px 14px; text-align: left; font-size: 13px; border-bottom: 1px solid #2A2A2A; }
        th { background: #222; color: #AAA; text-transform: uppercase; font-size: 11px; }
        tr:hover { background: #202020; }
        td.rules { font-size: 11px; color: #999; max-width: 260px; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-scheduled { background: rgba(77, 166, 255, 0.15); color: #4DA6FF; }
        .badge-active { background: rgba(37, 211, 102, 0.15); color: #25D366; }
        .badge-completed { background: rgba(170, 170, 170, 0.15); color: #AAA; }

        .placeholder { background: #151515; border: 1px dashed #333; border-radius: 8px; padding: 22px; text-align: center; color: #777; font-size: 13px; }
        .active-strip { background: linear-gradient(180deg, rgba(37,211,102,0.12), rgba(37,211,102,0.03)); border: 1px solid rgba(37,211,102,0.4); border-radius: 10px; padding: 16px 20px; margin-top: 14px; }
        .active-strip .live-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #25D366; margin-right: 8px; animation: pulse 1.4s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }
        .active-strip .cd { font-variant-numeric: tabular-nums; color: #25D366; font-weight: 800; font-size: 18px; }
        .row-actions { white-space: nowrap; }
        .row-actions button { background: #262626; color: #DDD; border: 1px solid #3A3A3A; padding: 6px 10px; border-radius: 6px; font-size: 11px; cursor: pointer; margin-right: 4px; }
        .row-actions button:hover { border-color: #FF6600; color: #FF9900; }

        #toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(80px); background: #1A1A1A; border: 1px solid #FF6600; border-radius: 8px; padding: 10px 18px; font-size: 13px; opacity: 0; transition: 0.3s ease; z-index: 300; }
        #toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>
<div class="wrapper">
    <div class="header">
        <h1>Draw Schedule</h1>
    </div>
    <p class="tz-note">All times are entered and shown in <strong>Sri Lanka Time (Asia/Colombo, UTC+5:30)</strong>. The active draw and rollover are evaluated against the server clock, which is pinned to this zone.</p>

    <!-- Active draw + live countdown -->
    <h2 class="section-title">Active Draw</h2>
    <div id="active-wrap">
        <?php if (empty($schedules['active'])): ?>
            <div class="placeholder">No draw is active right now. The next scheduled draw is promoted automatically once its start time passes.</div>
        <?php else: $a = $schedules['active'][0]; ?>
            <div class="active-strip">
                <div><span class="live-dot"></span><strong><?= htmlspecialchars($a['title']) ?></strong> · closes <?= htmlspecialchars(fmt_dt($a['cutoff_time_iso'])) ?></div>
                <div style="margin-top:6px;">Cut-off in <span class="cd" id="active-countdown" data-cutoff="<?= htmlspecialchars($a['cutoff_time_iso']) ?>">—</span></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create / edit form -->
    <h2 class="section-title" id="form-heading">Schedule a New Draw</h2>
    <div class="card">
        <div class="form-error" id="form-error"></div>
        <form id="schedule-form">
            <input type="hidden" id="f-id" value="">
            <div class="form-grid">
                <div class="form-group">
                    <label for="f-title">Title</label>
                    <input type="text" id="f-title" placeholder="Draw 1 - Week 1" required>
                </div>
                <div class="form-group">
                    <label for="f-start">Start Time <span class="hint">(SL time — draw goes active)</span></label>
                    <input type="datetime-local" id="f-start" required>
                </div>
                <div class="form-group">
                    <label for="f-cutoff">Cut-off Time <span class="hint">(SL time — entries close / draw taken)</span></label>
                    <input type="datetime-local" id="f-cutoff" required>
                </div>
                <div class="form-group">
                    <label for="f-status">Status</label>
                    <select id="f-status">
                        <option value="scheduled">scheduled</option>
                        <option value="active">active</option>
                        <option value="completed">completed</option>
                    </select>
                </div>
            </div>

            <fieldset class="filters-fieldset">
                <legend>Inclusion / Exclusion Presets (optional)</legend>
                <div class="form-grid">
                    <?php foreach ($FILTER_FIELDS as $key => $label): ?>
                        <div class="form-group">
                            <label for="f-<?= $key ?>"><?= $label ?></label>
                            <input type="text" id="f-<?= $key ?>" data-filter-key="<?= $key ?>" placeholder="Comma-separated, blank = no rule">
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn" id="form-submit">Create Schedule</button>
                <button type="button" class="btn btn-secondary" id="form-reset" style="display:none;">Cancel Edit</button>
            </div>
        </form>
    </div>

    <!-- Queue -->
    <h2 class="section-title">Upcoming (Scheduled)</h2>
    <?php if (empty($schedules['scheduled'])): ?>
        <div class="placeholder">No upcoming draws queued.</div>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table>
            <thead><tr><th>Title</th><th>Start</th><th>Cut-off</th><th>Filter Rules</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($schedules['scheduled'] as $s): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['title']) ?></strong></td>
                        <td><?= htmlspecialchars(fmt_dt($s['start_time_iso'])) ?></td>
                        <td><?= htmlspecialchars(fmt_dt($s['cutoff_time_iso'])) ?></td>
                        <td class="rules"><?= htmlspecialchars(rules_summary($s['filter_rules'])) ?></td>
                        <td class="row-actions">
                            <button onclick='editSchedule(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                            <button onclick="deleteSchedule(<?= $s['id'] ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <h2 class="section-title">Completed</h2>
    <?php if (empty($schedules['completed'])): ?>
        <div class="placeholder">No completed draws yet.</div>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table>
            <thead><tr><th>Title</th><th>Start</th><th>Cut-off</th><th>Filter Rules</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($schedules['completed'] as $s): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['title']) ?></strong> <span class="badge badge-completed">done</span></td>
                        <td><?= htmlspecialchars(fmt_dt($s['start_time_iso'])) ?></td>
                        <td><?= htmlspecialchars(fmt_dt($s['cutoff_time_iso'])) ?></td>
                        <td class="rules"><?= htmlspecialchars(rules_summary($s['filter_rules'])) ?></td>
                        <td class="row-actions">
                            <button onclick='editSchedule(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                            <button onclick="deleteSchedule(<?= $s['id'] ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div id="toast"></div>

<?php
function rules_summary(array $rules): string
{
    $parts = [];
    foreach ($rules as $k => $vals) {
        if ($vals) {
            $parts[] = $k . ': ' . implode(', ', $vals);
        }
    }
    return $parts ? implode(' | ', $parts) : 'None';
}
?>

<script>
    const FILTER_KEYS = ['inc_districts', 'inc_cities', 'inc_dealers', 'exc_districts', 'exc_cities', 'exc_dealers'];

    function showToast(msg) {
        const t = document.getElementById('toast');
        t.innerText = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 2500);
    }

    async function callApi(payload) {
        const res = await fetch('/api/draw_schedules.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        return { ok: res.ok, data: await res.json() };
    }

    function collectFilterRules() {
        const rules = {};
        FILTER_KEYS.forEach(k => {
            const raw = document.getElementById('f-' + k).value.trim();
            const vals = raw ? raw.split(',').map(s => s.trim()).filter(Boolean) : [];
            if (vals.length) rules[k] = vals;
        });
        return rules;
    }

    function resetForm() {
        document.getElementById('schedule-form').reset();
        document.getElementById('f-id').value = '';
        document.getElementById('form-error').style.display = 'none';
        document.getElementById('form-heading').innerText = 'Schedule a New Draw';
        document.getElementById('form-submit').innerText = 'Create Schedule';
        document.getElementById('form-reset').style.display = 'none';
    }
    document.getElementById('form-reset').addEventListener('click', resetForm);

    function editSchedule(s) {
        document.getElementById('f-id').value = s.id;
        document.getElementById('f-title').value = s.title;
        document.getElementById('f-start').value = toLocalInput(s.start_time);
        document.getElementById('f-cutoff').value = toLocalInput(s.cutoff_time);
        document.getElementById('f-status').value = s.status;
        FILTER_KEYS.forEach(k => {
            document.getElementById('f-' + k).value = (s.filter_rules && s.filter_rules[k] || []).join(', ');
        });
        document.getElementById('form-heading').innerText = 'Edit Schedule #' + s.id;
        document.getElementById('form-submit').innerText = 'Save Changes';
        document.getElementById('form-reset').style.display = 'inline-block';
        document.getElementById('form-error').style.display = 'none';
        window.scrollTo({ top: document.getElementById('form-heading').offsetTop - 20, behavior: 'smooth' });
    }

    // Stored value is Asia/Colombo wall-clock ("YYYY-MM-DD HH:MM:SS"); feed the
    // first 16 chars straight into datetime-local so no timezone shift happens.
    function toLocalInput(dt) {
        return String(dt).replace(' ', 'T').slice(0, 16);
    }

    document.getElementById('schedule-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        const errBox = document.getElementById('form-error');
        errBox.style.display = 'none';

        const id = document.getElementById('f-id').value;
        const payload = {
            action: id ? 'update' : 'create',
            title: document.getElementById('f-title').value.trim(),
            start_time: document.getElementById('f-start').value.replace('T', ' ') + ':00',
            cutoff_time: document.getElementById('f-cutoff').value.replace('T', ' ') + ':00',
            status: document.getElementById('f-status').value,
            filter_rules: collectFilterRules(),
        };
        if (id) payload.id = Number(id);

        const { ok, data } = await callApi(payload);
        if (!ok) {
            errBox.innerText = data.message || 'Failed to save schedule';
            errBox.style.display = 'block';
            return;
        }
        showToast(id ? 'Schedule updated' : 'Schedule created');
        setTimeout(() => window.location.reload(), 600);
    });

    async function deleteSchedule(id) {
        if (!confirm('Delete this schedule? This cannot be undone.')) return;
        const { ok, data } = await callApi({ action: 'delete', id });
        if (!ok) { showToast(data.message || 'Delete failed'); return; }
        showToast('Schedule deleted');
        setTimeout(() => window.location.reload(), 500);
    }

    /* ---------- live countdown for the active draw ---------- */
    (function initActiveCountdown() {
        const el = document.getElementById('active-countdown');
        if (!el) return;
        const target = new Date(el.dataset.cutoff).getTime();
        function tick() {
            const diff = target - Date.now();
            if (diff <= 0) {
                el.innerText = 'elapsed — rolling over…';
                // Rollover happens server-side on the next get_active_draw poll; refresh to pick it up.
                setTimeout(() => window.location.reload(), 4000);
                clearInterval(h);
                return;
            }
            const d = Math.floor(diff / 86400000);
            const hh = Math.floor(diff / 3600000) % 24;
            const mm = Math.floor(diff / 60000) % 60;
            const ss = Math.floor(diff / 1000) % 60;
            el.innerText = `${d}d ${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}:${String(ss).padStart(2, '0')}`;
        }
        tick();
        const h = setInterval(tick, 1000);
    })();
</script>
</body>
</html>
