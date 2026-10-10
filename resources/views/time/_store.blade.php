@php
    /**
     * Global timer store (window.TM) — single source of truth for the active
     * time entry. Every timer UI (tt-panel widget, planner floating bar,
     * dashboard/drawer start buttons) subscribes here instead of keeping its
     * own copy of the state.
     *
     * - Elapsed time is derived from the wall clock (never counted ++), so
     *   no drift and pause/resume are exact.
     * - Actions are optimistic: the UI flips in the same frame the button is
     *   pressed, the server response only reconciles it.
     * - One shared 250ms ticker drives all subscribed views at once.
     * - Cross-tab sync via localStorage "storage" events.
     */
@endphp
@if(auth()->check())
<script>
(function () {
    if (window.TM) return;

    const ACTIVE_URL = @json(route('time.active'));
    const START_URL = @json(route('time.start'));
    const ENTRY_URL = @json(url('/time/entries'));
    const LS_KEY = 'tm.timer.v1';

    let state = { entry: null, baseElapsed: 0, baseAt: 0 };
    let busy = false;
    let rev = 0;
    let lastShownSec = -1;
    const listeners = [];

    /* ── Elapsed time: always derived from wall clock ── */
    function nowSec() {
        if (!state.entry) return 0;
        if (state.entry.status !== 'running') return state.baseElapsed;
        return state.baseElapsed + (Date.now() - state.baseAt) / 1000;
    }

    function snapshot() {
        return { entry: state.entry, elapsed: Math.floor(nowSec()), busy: busy, rev: rev };
    }

    function notify() {
        const snap = snapshot();
        listeners.forEach(fn => { try { fn(snap); } catch (e) { console.error('[TM] listener error', e); } });
        document.dispatchEvent(new CustomEvent('tm:timer', { detail: snap }));
    }

    function persist() {
        try { localStorage.setItem(LS_KEY, JSON.stringify(state)); } catch (e) { /* private mode */ }
    }

    function setEntry(entry, serverElapsed) {
        if (!entry) {
            state = { entry: null, baseElapsed: 0, baseAt: 0 };
        } else {
            state.entry = entry;
            state.baseElapsed = Math.max(0, Math.floor(
                serverElapsed !== undefined && serverElapsed !== null ? serverElapsed : (entry.elapsed || 0)
            ));
            state.baseAt = Date.now();
        }
        rev++;
        lastShownSec = -1;
        persist();
        notify();
    }

    /* ── HTTP with live CSRF + one-shot 419 refresh/retry ── */
    function csrfToken() {
        const m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    async function refreshCsrf() {
        try {
            const page = await fetch(window.location.href, { headers: { 'Accept': 'text/html' }, credentials: 'same-origin', cache: 'no-store' });
            const m = (await page.text()).match(/<meta\s+name=["']csrf-token["']\s+content=["']([^"']+)["']/i);
            if (m) {
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.content = m[1];
                return m[1];
            }
        } catch (e) { /* network */ }
        return null;
    }

    async function req(url, method, body) {
        const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() };
        const init = { method, headers, credentials: 'same-origin' };
        if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(body);
        }
        let res = await fetch(url, init);
        if (res.status === 419) {
            const fresh = await refreshCsrf();
            if (fresh) { headers['X-CSRF-TOKEN'] = fresh; res = await fetch(url, init); }
        }
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    /* ── Reconcile from the server ── */
    function sync() {
        if (busy) return Promise.resolve();
        return req(ACTIVE_URL, 'GET')
            .then(j => {
                if (busy) return;
                const incoming = j.active || null;
                if (!incoming) { if (state.entry) setEntry(null); return; }
                const cur = state.entry;
                if (cur && cur.id === incoming.id && cur.status === incoming.status) {
                    if (Math.abs((incoming.elapsed || 0) - nowSec()) > 2) {
                        state.baseElapsed = Math.max(0, Math.floor(incoming.elapsed || 0));
                        state.baseAt = Date.now();
                        rev++;
                        persist();
                        notify();
                    }
                    return;
                }
                setEntry(incoming);
            })
            .catch(() => {});
    }

    /* ── Actions (optimistic + single in-flight guard) ── */
    function start(payload) {
        if (busy) return Promise.resolve(state.entry);
        busy = true; notify();
        return req(START_URL, 'POST', payload || {})
            .then(j => { busy = false; setEntry(j.active); return j.active; })
            .catch(err => { busy = false; sync(); throw err; });
    }

    function togglePause() {
        if (!state.entry || busy) return Promise.resolve();
        const id = state.entry.id;
        const toPaused = state.entry.status === 'running';
        if (toPaused) {
            state.baseElapsed = Math.floor(nowSec());
            state.entry = Object.assign({}, state.entry, { status: 'paused' });
        } else {
            state.entry = Object.assign({}, state.entry, { status: 'running' });
            state.baseAt = Date.now();
        }
        busy = true; rev++;
        persist(); notify();
        return req(ENTRY_URL + '/' + id + '/' + (toPaused ? 'pause' : 'resume'), 'POST', {})
            .then(j => { busy = false; setEntry(j.active); })
            .catch(err => {
                busy = false;
                state.entry = Object.assign({}, state.entry, { status: toPaused ? 'running' : 'paused' });
                rev++; persist();
                sync();
                throw err;
            });
    }

    function stop() {
        if (!state.entry || busy) return Promise.resolve(null);
        const id = state.entry.id;
        busy = true;
        setEntry(null);
        return req(ENTRY_URL + '/' + id + '/stop', 'POST', {})
            .then(j => { busy = false; rev++; notify(); return j.duration; })
            .catch(err => { busy = false; sync(); throw err; });
    }

    /* ── Single shared ticker ── */
    setInterval(() => {
        if (!state.entry || state.entry.status !== 'running' || busy) return;
        const s = Math.floor(nowSec());
        if (s !== lastShownSec) { lastShownSec = s; notify(); }
    }, 250);

    /* ── Cross-tab sync ── */
    window.addEventListener('storage', (e) => {
        if (e.key !== LS_KEY || busy) return;
        try {
            if (!e.newValue) return;
            const s = JSON.parse(e.newValue);
            const same = (!s.entry && !state.entry) ||
                (s.entry && state.entry && s.entry.id === state.entry.id && s.entry.status === state.entry.status);
            if (same && s.baseAt === state.baseAt) return;
            state = { entry: s.entry || null, baseElapsed: s.baseElapsed || 0, baseAt: s.baseAt || Date.now() };
            rev++; lastShownSec = -1;
            notify();
        } catch (err) { /* ignore */ }
    });

    /* ── Boot + heartbeat + tab-refocus reconcile ── */
    sync();
    setInterval(sync, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });
    window.addEventListener('focus', () => sync());

    function fmt(s) {
        s = Math.max(0, Math.floor(s));
        const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
        const mm = String(m).padStart(2, '0'), ss = String(sec).padStart(2, '0');
        return h > 0 ? h + ':' + mm + ':' + ss : mm + ':' + ss;
    }

    window.TM = {
        subscribe(fn) { listeners.push(fn); fn(snapshot()); return () => {
            const i = listeners.indexOf(fn); if (i > -1) listeners.splice(i, 1);
        }; },
        snapshot, fmt, sync, start, stop, togglePause,
        get entry() { return state.entry; },
        get elapsed() { return Math.floor(nowSec()); },
        get busy() { return busy; },
    };
})();
</script>
@endif
