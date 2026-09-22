@auth
@php
    $ttProjects = auth()->user()->projects()->orderBy('name')->get(['id', 'name']);
@endphp
<style>
    #tt-root { position: fixed; right: 16px; bottom: 16px; z-index: 1040; font-size: 13px; }
    #tt-fab {
        width: 52px; height: 52px; border-radius: 50%; border: none; cursor: pointer;
        background: linear-gradient(135deg, #7c3aed, #5b21b6); color: white;
        box-shadow: 0 4px 14px rgba(124,58,237,.45); display: flex;
        align-items: center; justify-content: center; font-size: 20px;
        position: relative; margin-left: auto;
    }
    #tt-fab.tt-running { background: linear-gradient(135deg, #16a34a, #15803d); }
    #tt-fab.tt-paused { background: linear-gradient(135deg, #d97706, #b45309); }
    #tt-fab .tt-dot {
        position: absolute; top: 2px; right: 2px; width: 12px; height: 12px;
        border-radius: 50%; background: #22c55e; border: 2px solid white; display: none;
    }
    #tt-fab.tt-running .tt-dot, #tt-fab.tt-paused .tt-dot { display: block; }
    #tt-fab.tt-paused .tt-dot { background: #f59e0b; }
    #tt-panel {
        display: none; width: 290px; background: white; border: 1px solid #e3e4e8;
        border-radius: 12px; box-shadow: 0 12px 32px rgba(0,0,0,.16);
        overflow: hidden; margin-bottom: 10px;
    }
    #tt-root.tt-open #tt-panel { display: block; }
    .tt-head {
        display: flex; align-items: center; gap: 8px; padding: 10px 12px;
        background: #fafbfc; border-bottom: 1px solid #e3e4e8;
        font-weight: 700; font-size: 12px; color: #1a1d23;
    }
    .tt-head a { margin-left: auto; font-size: 11px; color: #7c3aed; text-decoration: none; font-weight: 600; }
    .tt-body { padding: 12px; }
    .tt-elapsed { font-size: 26px; font-weight: 800; color: #1a1d23; text-align: center; font-variant-numeric: tabular-nums; }
    .tt-sub { font-size: 11px; color: #8a8f98; text-align: center; margin-bottom: 10px; word-break: break-word; }
    .tt-row { display: flex; gap: 6px; }
    .tt-btn {
        flex: 1; border: 1px solid #e3e4e8; background: white; color: #3d4149;
        border-radius: 7px; padding: 7px 0; font-size: 12px; font-weight: 600;
        cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px;
    }
    .tt-btn:hover { border-color: #7c3aed; color: #7c3aed; }
    .tt-btn.primary { background: #7c3aed; border-color: #7c3aed; color: white; }
    .tt-btn.primary:hover { background: #6d28d9; }
    .tt-btn.danger { color: #dc2626; }
    .tt-btn.danger:hover { border-color: #dc2626; background: #fef2f2; }
    .tt-field { margin-bottom: 8px; }
    .tt-field label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #8a8f98; margin-bottom: 3px; }
    .tt-input { width: 100%; border: 1px solid #e3e4e8; border-radius: 7px; padding: 6px 8px; font-size: 12px; color: #1a1d23; background: white; outline: none; }
    .tt-input:focus { border-color: #7c3aed; }
    @media (max-width: 480px) { #tt-panel { width: calc(100vw - 32px); } }
</style>
<div id="tt-root">
    <div id="tt-panel">
        <div class="tt-head">
            <i class="bi bi-stopwatch"></i> Time Tracker
            <a href="{{ route('time.reports') }}">Reports</a>
        </div>
        <div class="tt-body">
            <div id="tt-active" style="display:none;">
                <div class="tt-elapsed" id="tt-elapsed">00:00</div>
                <div class="tt-sub" id="tt-sub"></div>
                <div class="tt-row">
                    <button type="button" class="tt-btn" id="tt-pause"><i class="bi bi-pause-fill"></i><span>Pause</span></button>
                    <button type="button" class="tt-btn danger" id="tt-stop"><i class="bi bi-stop-fill"></i>Stop</button>
                </div>
            </div>
            <div id="tt-form">
                <div class="tt-field">
                    <label>Project</label>
                    <select class="tt-input" id="tt-project">
                        <option value="">No project</option>
                        @foreach($ttProjects as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="tt-field">
                    <label>Task</label>
                    <select class="tt-input" id="tt-task" disabled>
                        <option value="">Select a project first</option>
                    </select>
                </div>
                <div class="tt-field">
                    <label>What are you working on?</label>
                    <input type="text" class="tt-input" id="tt-desc" placeholder="e.g. Chapter 2 exercises" maxlength="500">
                </div>
                <div class="tt-field">
                    <label>Category</label>
                    <select class="tt-input" id="tt-category">
                        <option value="">—</option>
                        <option value="study">Study</option>
                        <option value="coding">Coding</option>
                        <option value="review">Review</option>
                        <option value="meeting">Meeting</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <button type="button" class="tt-btn primary" id="tt-start" style="width:100%;">
                    <i class="bi bi-play-fill"></i> Start Timer
                </button>
            </div>
        </div>
    </div>
    <button type="button" id="tt-fab" title="Time tracker">
        <i class="bi bi-stopwatch" id="tt-fab-icon"></i>
        <span class="tt-dot"></span>
    </button>
</div>
<script>
(function () {
    const root = document.getElementById('tt-root');
    if (!root) return;
    const CSRF = '{{ csrf_token() }}';
    const fab = document.getElementById('tt-fab');
    const fabIcon = document.getElementById('tt-fab-icon');
    const activeBox = document.getElementById('tt-active');
    const formBox = document.getElementById('tt-form');
    const elapsedEl = document.getElementById('tt-elapsed');
    const subEl = document.getElementById('tt-sub');
    const pauseBtn = document.getElementById('tt-pause');
    const projectSel = document.getElementById('tt-project');
    const taskSel = document.getElementById('tt-task');

    let active = null, baseElapsed = 0, baseAt = 0;

    function fmt(s) {
        s = Math.max(0, Math.floor(s));
        const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
        const mm = String(m).padStart(2, '0'), ss = String(sec).padStart(2, '0');
        return h > 0 ? h + ':' + mm + ':' + ss : mm + ':' + ss;
    }

    function render() {
        const has = !!active;
        activeBox.style.display = has ? '' : 'none';
        formBox.style.display = has ? 'none' : '';
        fab.classList.toggle('tt-running', has && active.status === 'running');
        fab.classList.toggle('tt-paused', has && active.status === 'paused');
        fabIcon.className = has
            ? (active.status === 'running' ? 'bi bi-pause-fill' : 'bi bi-play-fill')
            : 'bi bi-stopwatch';
        if (has) {
            const bits = [];
            if (active.task) bits.push(active.task.title);
            else if (active.project) bits.push(active.project.name);
            if (active.description) bits.push(active.description);
            subEl.textContent = (active.status === 'paused' ? 'Paused · ' : '') + (bits.join(' — ') || 'Working…');
            pauseBtn.querySelector('span').textContent = active.status === 'running' ? 'Pause' : 'Resume';
        }
        tick();
    }

    function tick() {
        if (!active) return;
        const s = active.status === 'running' ? baseElapsed + (Date.now() - baseAt) / 1000 : baseElapsed;
        elapsedEl.textContent = fmt(s);
    }
    setInterval(tick, 1000);

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: body ? JSON.stringify(body) : '{}',
        }).then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
    }

    function refresh() {
        return fetch('{{ route('time.active') }}', { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(j => {
                active = j.active;
                if (active) { baseElapsed = active.elapsed; baseAt = Date.now(); }
                render();
            })
            .catch(() => {});
    }

    fab.addEventListener('click', () => {
        if (active) {
            root.classList.toggle('tt-open');
        } else {
            root.classList.toggle('tt-open');
            if (root.classList.contains('tt-open')) refresh();
        }
    });

    document.getElementById('tt-start').addEventListener('click', () => {
        post('{{ route('time.start') }}', {
            project_id: projectSel.value || null,
            task_id: taskSel.disabled ? null : (taskSel.value || null),
            description: document.getElementById('tt-desc').value || null,
            category: document.getElementById('tt-category').value || null,
        }).then(j => { active = j.active; baseElapsed = active.elapsed; baseAt = Date.now(); render(); })
          .catch(() => alert('Could not start the timer.'));
    });

    pauseBtn.addEventListener('click', () => {
        if (!active) return;
        const url = active.status === 'running'
            ? '{{ url('/time/entries') }}/' + active.id + '/pause'
            : '{{ url('/time/entries') }}/' + active.id + '/resume';
        post(url).then(j => { active = j.active; baseElapsed = active.elapsed; baseAt = Date.now(); render(); });
    });

    document.getElementById('tt-stop').addEventListener('click', () => {
        if (!active) return;
        post('{{ url('/time/entries') }}/' + active.id + '/stop')
            .then(() => { active = null; render(); });
    });

    projectSel.addEventListener('change', () => {
        taskSel.innerHTML = '<option value="">Loading…</option>';
        taskSel.disabled = true;
        if (!projectSel.value) {
            taskSel.innerHTML = '<option value="">Select a project first</option>';
            return;
        }
        fetch('{{ route('time.tasks') }}?project_id=' + encodeURIComponent(projectSel.value), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(j => {
                taskSel.innerHTML = '<option value="">No specific task</option>' +
                    (j.tasks || []).map(t => '<option value="' + t.id + '">' + t.title.replace(/</g, '&lt;') + '</option>').join('');
                taskSel.disabled = false;
            })
            .catch(() => { taskSel.innerHTML = '<option value="">Could not load tasks</option>'; });
    });

    refresh();
    setInterval(refresh, 60000);
})();
</script>
@endauth
