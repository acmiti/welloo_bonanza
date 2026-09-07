// admin/assets/draw-countdown.js — self-rolling countdown widget driven by
// api/get_active_draw.php. Polls the endpoint (which advances the draw_batches
// queue server-side against Asia/Colombo time), shows the active batch's name +
// entry-deadline countdown, and when the active batch locks it re-polls and
// seamlessly swaps to the next batch with no page reload.
//
// Usage:
//   <div id="draw-countdown"
//        data-title-el="#draw-title"      (optional selector to render the title into)
//        data-empty-text="No draw scheduled"></div>
//   <script src="/admin/assets/draw-countdown.js"></script>
//   DrawCountdown.mount('#draw-countdown');
(function () {
    const POLL_MS = 30000;

    function parseColombo(iso, fallback) {
        if (iso) {
            const d = new Date(iso);
            if (!isNaN(d.getTime())) return d;
        }
        return fallback ? new Date(String(fallback).replace(' ', 'T')) : null;
    }

    function fmtDiff(ms) {
        if (ms <= 0) return '00:00:00';
        const total = Math.floor(ms / 1000);
        const d = Math.floor(total / 86400);
        const h = Math.floor(total / 3600) % 24;
        const m = Math.floor(total / 60) % 60;
        const s = total % 60;
        const hh = String(h).padStart(2, '0');
        const mm = String(m).padStart(2, '0');
        const ss = String(s).padStart(2, '0');
        return (d > 0 ? d + 'd ' : '') + hh + ':' + mm + ':' + ss;
    }

    function mount(selector, opts) {
        const el = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!el) return null;
        opts = opts || {};

        const titleEl = el.dataset.titleEl ? document.querySelector(el.dataset.titleEl) : null;
        const emptyText = el.dataset.emptyText || opts.emptyText || 'No active draw';

        let current = null;         // presented active_draw object
        let cutoffMs = null;
        let tickTimer = null;
        let pollTimer = null;
        let onChange = opts.onChange || function () {};

        function render() {
            if (!current) {
                el.textContent = emptyText;
                if (titleEl) titleEl.textContent = '';
                return;
            }
            if (titleEl) titleEl.textContent = current.title;
            el.textContent = fmtDiff(cutoffMs - Date.now());
        }

        function tick() {
            if (!current) return;
            const remaining = cutoffMs - Date.now();
            if (remaining <= 0) {
                el.textContent = '00:00:00';
                // Active window closed — ask the server to roll over, then adopt
                // whatever draw is active next (or clear if none).
                poll();
                return;
            }
            render();
        }

        function apply(activeDraw) {
            const changed = (current && current.id) !== (activeDraw && activeDraw.id);
            current = activeDraw || null;
            cutoffMs = current ? (parseColombo(current.cutoff_time_iso, current.cutoff_time) || { getTime: () => 0 }).getTime() : null;
            render();
            if (changed) onChange(current);
        }

        async function poll() {
            try {
                const res = await fetch('/api/get_active_draw.php', { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                const data = await res.json();
                if (data && data.status === 'success') {
                    apply(data.active_draw);
                    el._nextDraw = data.next_draw || null;
                }
            } catch (e) {
                /* keep showing the last known state until the next poll */
            }
        }

        poll();
        tickTimer = setInterval(tick, 1000);
        pollTimer = setInterval(poll, POLL_MS);

        const api = {
            el,
            refresh: poll,
            getActive: () => current,
            getNext: () => el._nextDraw || null,
            destroy() { clearInterval(tickTimer); clearInterval(pollTimer); },
        };
        return api;
    }

    window.DrawCountdown = { mount, fmtDiff, parseColombo };
})();
