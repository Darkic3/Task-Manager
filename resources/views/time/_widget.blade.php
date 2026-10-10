@auth
@php
    $ttProjects = auth()->user()->projects()->orderBy('name')->get(['id', 'name']);
@endphp
<style>
    #tt-root { position: fixed; right: 16px; bottom: 16px; z-index: 1040; font-size: 13px; }
    html[dir="rtl"] #tt-root { right: auto; left: 16px; }
    #tt-fab {
        width: 52px; height: 52px; border-radius: 50%; border: none; cursor: pointer;
        background: linear-gradient(135deg, #7c3aed, #5b21b6); color: white;
        box-shadow: 0 4px 14px rgba(124,58,237,.45); display: flex;
        align-items: center; justify-content: center; font-size: 20px;
        position: relative; margin-left: auto;
        transition: background .25s ease, transform .12s ease;
    }
    html[dir="rtl"] #tt-fab { margin-left: 0; margin-right: auto; }
    #tt-fab:active { transform: scale(.93); }
    #tt-fab.tt-running { background: linear-gradient(135deg, #16a34a, #15803d); }
    #tt-fab.tt-paused { background: linear-gradient(135deg, #d97706, #b45309); }
    #tt-fab .tt-dot {
        position: absolute; top: 2px; right: 2px; width: 12px; height: 12px;
        border-radius: 50%; background: #22c55e; border: 2px solid white; display: none;
    }
    html[dir="rtl"] #tt-fab .tt-dot { right: auto; left: 2px; }
    #tt-fab.tt-running .tt-dot, #tt-fab.tt-paused .tt-dot { display: block; }
    #tt-fab.tt-paused .tt-dot { background: #f59e0b; }
    #tt-panel {
        display: none; width: 290px; background: white; border: 1px solid #e3e4e8;
        border-radius: 12px; box-shadow: 0 12px 32px rgba(0,0,0,.16);
        overflow: hidden; margin-bottom: 10px;
    }
    #tt-root.tt-open #tt-panel { display: block; animation: ttPop .18s cubic-bezier(.16,1,.3,1); }
    @keyframes ttPop { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    .tt-head {
        display: flex; align-items: center; gap: 8px; padding: 10px 12px;
        background: #fafbfc; border-bottom: 1px solid #e3e4e8;
        font-weight: 700; font-size: 12px; color: #1a1d23;
    }
    .tt-head a { margin-left: auto; font-size: 11px; color: #7c3aed; text-decoration: none; font-weight: 600; }
    html[dir="rtl"] .tt-head a { margin-left: 0; margin-right: auto; }
    .tt-body { padding: 12px; }
    .tt-elapsed { font-size: 26px; font-weight: 800; color: #1a1d23; text-align: center; font-variant-numeric: tabular-nums; }
    #tt-active[data-status="paused"] .tt-elapsed { color: #d97706; }
    .tt-sub { font-size: 11px; color: #8a8f98; text-align: center; margin-bottom: 10px; word-break: break-word; }
    .tt-row { display: flex; gap: 6px; }
    .tt-btn {
        flex: 1; border: 1px solid #e3e4e8; background: white; color: #3d4149;
        border-radius: 7px; padding: 7px 0; font-size: 12px; font-weight: 600;
        cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px;
        transition: opacity .12s ease, border-color .12s ease;
    }
    .tt-btn:hover { border-color: #7c3aed; color: #7c3aed; }
    .tt-btn.primary { background: #7c3aed; border-color: #7c3aed; color: white; }
    .tt-btn.primary:hover { background: #6d28d9; }
    .tt-btn.danger { color: #dc2626; }
    .tt-btn.danger:hover { border-color: #dc2626; background: #fef2f2; }
    .tt-btn:disabled { opacity: .55; cursor: default; pointer-events: none; }
    .tt-field { margin-bottom: 8px; }
    .tt-field label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #8a8f98; margin-bottom: 3px; }
    .tt-input { width: 100%; border: 1px solid #e3e4e8; border-radius: 7px; padding: 6px 8px; font-size: 12px; color: #1a1d23; background: white; outline: none; }
    .tt-input:focus { border-color: #7c3aed; }
    @media (max-width: 480px) { #tt-panel { width: calc(100vw - 32px); } }
</style>
<div id="tt-root">
    <div id="tt-panel">
        <div class="tt-head">
            <i class="bi bi-stopwatch"></i> {{ __('Time Tracker') }}
            <a href="{{ route('time.reports') }}">{{ __('Reports') }}</a>
        </div>
        <div class="tt-body">
            <div id="tt-active" style="display:none;">
                <div class="tt-elapsed" id="tt-elapsed">00:00</div>
                <div class="tt-sub" id="tt-sub"></div>
                <div class="tt-row">
                    <button type="button" class="tt-btn" id="tt-pause"><i class="bi bi-pause-fill"></i><span>{{ __('Pause') }}</span></button>
                    <button type="button" class="tt-btn danger" id="tt-stop"><i class="bi bi-stop-fill"></i>{{ __('Stop') }}</button>
                </div>
            </div>
            <div id="tt-form">
                <div class="tt-field">
                    <label>{{ __('Project') }}</label>
                    <select class="tt-input" id="tt-project">
                        <option value="">{{ __('No Project') }}</option>
                        @foreach($ttProjects as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="tt-field">
                    <label>{{ __('Task') }}</label>
                    <select class="tt-input" id="tt-task" disabled>
                        <option value="">{{ __('Select a project first') }}</option>
                    </select>
                </div>
                <div class="tt-field">
                    <label>{{ __('What are you working on?') }}</label>
                    <input type="text" class="tt-input" id="tt-desc" placeholder="{{ __('e.g. Chapter 2 exercises') }}" maxlength="500">
                </div>
                <div class="tt-field">
                    <label>{{ __('Category') }}</label>
                    <select class="tt-input" id="tt-category">
                        <option value="">—</option>
                        <option value="study">{{ __('Study') }}</option>
                        <option value="coding">{{ __('Coding') }}</option>
                        <option value="review">{{ __('Review') }}</option>
                        <option value="meeting">{{ __('Meeting') }}</option>
                        <option value="other">{{ __('Other') }}</option>
                    </select>
                </div>
                <button type="button" class="tt-btn primary" id="tt-start" style="width:100%;">
                    <i class="bi bi-play-fill"></i> {{ __('Start Timer') }}
                </button>
            </div>
        </div>
    </div>
    <button type="button" id="tt-fab" title="{{ __('Time Tracker') }}">
        <i class="bi bi-stopwatch" id="tt-fab-icon"></i>
        <span class="tt-dot"></span>
    </button>
</div>
<script>
(function () {
    const root = document.getElementById('tt-root');
    if (!root || !window.TM) return;
    const fab = document.getElementById('tt-fab');
    const fabIcon = document.getElementById('tt-fab-icon');
    const activeBox = document.getElementById('tt-active');
    const formBox = document.getElementById('tt-form');
    const elapsedEl = document.getElementById('tt-elapsed');
    const subEl = document.getElementById('tt-sub');
    const pauseBtn = document.getElementById('tt-pause');
    const stopBtn = document.getElementById('tt-stop');
    const startBtn = document.getElementById('tt-start');
    const projectSel = document.getElementById('tt-project');
    const taskSel = document.getElementById('tt-task');

    function render(snap) {
        const entry = snap.entry;
        const has = !!entry;
        activeBox.style.display = has ? '' : 'none';
        formBox.style.display = has ? 'none' : '';
        activeBox.dataset.status = has ? entry.status : '';
        fab.classList.toggle('tt-running', has && entry.status === 'running');
        fab.classList.toggle('tt-paused', has && entry.status === 'paused');
        fabIcon.className = has
            ? (entry.status === 'running' ? 'bi bi-pause-fill' : 'bi bi-play-fill')
            : 'bi bi-stopwatch';
        if (has) {
            const bits = [];
            if (entry.task) bits.push(entry.task.title);
            else if (entry.project) bits.push(entry.project.name);
            if (entry.description) bits.push(entry.description);
            subEl.textContent = (entry.status === 'paused' ? @json(__('Paused')) + ' · ' : '') + (bits.join(' — ') || @json(__('Working…')));
            pauseBtn.querySelector('span').textContent = entry.status === 'running' ? @json(__('Pause')) : @json(__('Resume'));
            pauseBtn.querySelector('i').className = entry.status === 'running' ? 'bi bi-pause-fill' : 'bi bi-play-fill';
        }
        elapsedEl.textContent = TM.fmt(snap.elapsed);
        pauseBtn.disabled = !has || snap.busy;
        stopBtn.disabled = !has || snap.busy;
        startBtn.disabled = snap.busy;
    }

    TM.subscribe(render);

    fab.addEventListener('click', () => {
        root.classList.toggle('tt-open');
    });

    startBtn.addEventListener('click', () => {
        startBtn.disabled = true;
        TM.start({
            project_id: projectSel.value || null,
            task_id: taskSel.disabled ? null : (taskSel.value || null),
            description: document.getElementById('tt-desc').value || null,
            category: document.getElementById('tt-category').value || null,
        }).catch(() => alertSwal(@json(__('Could not start the timer.')), null, 'error'));
    });

    pauseBtn.addEventListener('click', () => {
        TM.togglePause().catch(() => {});
    });

    stopBtn.addEventListener('click', () => {
        TM.stop().catch(() => alertSwal(@json(__('Could not stop the timer.')), null, 'error'));
    });

    projectSel.addEventListener('change', () => {
        loadTasks();
    });

    function loadTasks() {
        taskSel.innerHTML = '<option value="">' + @json(__('Loading…')) + '</option>';
        const url = projectSel.value
            ? '{{ route('time.tasks') }}?project_id=' + encodeURIComponent(projectSel.value)
            : '{{ route('time.tasks') }}';
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(j => {
                taskSel.innerHTML = '<option value="">' + @json(__('No specific task')) + '</option>' +
                    (j.tasks || []).map(t => '<option value="' + t.id + '">' + t.title.replace(/</g, '&lt;') + '</option>').join('');
                taskSel.disabled = false;
            })
            .catch(() => { taskSel.innerHTML = '<option value="">' + @json(__('Could not load tasks')) + '</option>'; });
    }

    loadTasks();
})();
</script>
@endauth
