@extends('layouts.app')

@section('title', 'My Day')

@push('styles')
<style>
    .main-content { padding:14px 16px; background:#f7f8fa; min-height:100vh; }

    /* Header */
    .pl-header {
        background:linear-gradient(135deg,#7c3aed 0%,#5b21b6 100%);
        border-radius:10px; padding:12px 18px; color:white; margin-bottom:14px;
        position:relative; overflow:hidden; border:1px solid #6d28d9;
        box-shadow:0 2px 8px rgba(124,58,237,.3);
    }
    .pl-header::before {
        content:''; position:absolute; top:0; right:0; width:90px; height:90px;
        background:rgba(255,255,255,.08); border-radius:50%; transform:translate(24px,-24px);
    }
    .pl-header-title{font-weight:700;font-size:18px;margin:0;position:relative;z-index:1;}
    .pl-header-sub  {font-size:12.5px;opacity:.85;margin:2px 0 0;position:relative;z-index:1;}

    /* Toolbar */
    .pl-toolbar {
        display:flex; align-items:center; justify-content:space-between; gap:10px;
        background:white; border:1px solid #e3e4e8; border-radius:9px;
        padding:8px 12px; margin-bottom:14px; flex-wrap:wrap;
    }
    .pl-toggle{display:flex;background:#f0f1f3;border-radius:7px;padding:3px;gap:2px;}
    .pl-toggle-btn{
        padding:5px 14px;border-radius:5px;font-size:12px;font-weight:600;
        color:#8a8f98;text-decoration:none;display:flex;align-items:center;gap:5px;transition:all .15s;
    }
    .pl-toggle-btn.active{background:white;color:#1a1d23;box-shadow:0 1px 3px rgba(0,0,0,.1);}
    .pl-nav{display:flex;align-items:center;gap:6px;}
    .pl-nav-btn{
        width:30px;height:30px;display:flex;align-items:center;justify-content:center;
        border:1px solid #e3e4e8;border-radius:7px;background:white;color:#6b7385;
        text-decoration:none;transition:all .15s;
    }
    .pl-nav-btn:hover{border-color:#c4b5fd;color:#7c3aed;background:#faf5ff;}
    .pl-today-btn{
        padding:5px 14px;border:1px solid #e3e4e8;border-radius:7px;background:white;
        color:#3d4149;font-size:12px;font-weight:600;text-decoration:none;transition:all .15s;
    }
    .pl-today-btn:hover{border-color:#c4b5fd;color:#7c3aed;background:#faf5ff;}

    /* Stat chips */
    .pl-stats{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
    .pl-stat{
        display:flex;align-items:center;gap:8px;background:white;border:1px solid #e3e4e8;
        border-radius:9px;padding:8px 14px;font-size:12.5px;color:#6b7385;font-weight:600;
    }
    .pl-stat strong{font-size:15px;color:#1a1d23;}
    .pl-stat.overdue strong{color:#dc2626;}

    /* Section */
    .pl-section{background:white;border:1px solid #e3e4e8;border-radius:10px;overflow:hidden;margin-bottom:14px;}
    .pl-section-head{
        display:flex;align-items:center;gap:8px;padding:10px 16px;
        background:#fafbfc;border-bottom:1px solid #e3e4e8;
    }
    .pl-section-head i{font-size:14px;}
    .pl-section-title{font-size:13px;font-weight:700;color:#1a1d23;}
    .pl-section-count{
        margin-left:auto;background:#f0f1f3;border-radius:20px;padding:1px 9px;
        font-size:11px;font-weight:700;color:#8a8f98;
    }
    .pl-section-body{padding:8px;display:flex;flex-direction:column;gap:6px;}
    .pl-empty{padding:22px;text-align:center;color:#adb0b8;font-size:12.5px;}
    .pl-empty i{display:block;font-size:24px;margin-bottom:6px;color:#c4c9d4;}

    /* Task row */
    .pl-task{
        display:flex;align-items:flex-start;gap:10px;background:white;border:1px solid #eceef1;
        border-radius:8px;padding:9px 11px;transition:all .15s;
    }
    .pl-task:hover{box-shadow:0 3px 10px rgba(0,0,0,.07);border-color:#d8dae0;}
    .pl-check{position:relative;flex-shrink:0;margin-top:1px;cursor:pointer;}
    .pl-check input{position:absolute;opacity:0;width:0;height:0;}
    .pl-check-box{
        width:19px;height:19px;border:2px solid #c4c9d4;border-radius:6px;
        display:flex;align-items:center;justify-content:center;color:transparent;
        font-size:11px;transition:all .15s;
    }
    .pl-check:hover .pl-check-box{border-color:#7c3aed;}
    .pl-check input:checked + .pl-check-box{background:#16a34a;border-color:#16a34a;color:white;}
    .pl-task-body{flex:1;min-width:0;}
    .pl-task-title{font-size:13px;font-weight:600;color:#1a1d23;line-height:1.35;word-break:break-word;}
    .pl-task.is-done .pl-task-title{text-decoration:line-through;color:#adb0b8;}
    .pl-task-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:4px;}
    .pl-priority{font-size:10.5px;font-weight:700;padding:1px 8px;border-radius:20px;text-transform:uppercase;letter-spacing:.3px;}
    .pl-proj,.pl-due{font-size:11px;color:#8a8f98;display:inline-flex;align-items:center;gap:4px;}
    .pl-due.overdue{color:#dc2626;font-weight:600;}
    .pl-task-open{
        flex-shrink:0;width:26px;height:26px;display:flex;align-items:center;justify-content:center;
        color:#c4c9d4;text-decoration:none;border-radius:6px;transition:all .15s;
    }
    .pl-task-open:hover{color:#7c3aed;background:#faf5ff;}

    /* Routine row accent */
    .pl-routine .pl-check input:checked + .pl-check-box{background:#7c3aed;border-color:#7c3aed;}
    .pl-routine-static{
        flex-shrink:0;margin-top:1px;width:19px;height:19px;display:flex;
        align-items:center;justify-content:center;color:#c4c9d4;font-size:12px;
    }

    /* ── Habit Ring (package A) ── */
    .pl-habit{position:relative;flex-shrink:0;margin-top:1px;cursor:pointer;display:inline-flex;}
    .pl-habit input{position:absolute;opacity:0;width:0;height:0;}
    .routine-check-box{
        width:19px;height:19px;border:2px solid #c4c9d4;border-radius:50%;
        display:flex;align-items:center;justify-content:center;color:transparent;
        font-size:10px;transition:all .15s;background:white;position:absolute;
        top:50%;left:50%;transform:translate(-50%,-50%);
    }
    .pl-habit:hover .routine-check-box{border-color:#7c3aed;}
    .pl-habit input:checked + .habit-ring .routine-check-box,
    .pl-habit input:checked + .routine-check-box{background:#16a34a;border-color:#16a34a;color:white;}
    .habit-ring{position:relative;width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;}
    .habit-ring svg{width:30px;height:30px;transform:rotate(-90deg);}
    .habit-ring .ring-bg{fill:none;stroke:#eef0f2;stroke-width:3.5;}
    .habit-ring .ring-fg{fill:none;stroke:#b9a5f5;stroke-width:3.5;stroke-linecap:round;transition:stroke-dashoffset .4s;}
    .pl-task.is-done .habit-ring .ring-fg{stroke:#16a34a;}
    .pl-habit input:checked + .habit-ring .ring-fg{stroke:#16a34a;}
    .flame{
        font-size:11px;font-weight:700;color:#d97706;background:#fdf4de;
        border-radius:20px;padding:0 7px;margin-left:6px;white-space:nowrap;
        vertical-align:1px;
    }
    .last7{display:inline-flex;gap:3px;align-items:center;}
    .last7 .sq{width:7px;height:7px;border-radius:2.5px;display:inline-block;}
    .sq-done{background:#30a46c;}
    .sq-missed{background:#e3e5e9;}
    .sq-na{background:#f2f3f5;}
    .sq-future,.sq-today{background:transparent;box-shadow:inset 0 0 0 1px #e8eaef;}

    /* ── Package B: confetti + toast ── */
    #plConfetti{position:fixed;inset:0;pointer-events:none;z-index:1080;overflow:hidden;}
    #plConfetti i{position:absolute;top:-12px;width:8px;height:14px;border-radius:2px;opacity:0;animation:plFall 1.4s ease-in forwards;}
    @keyframes plFall{
        0%{opacity:1;transform:translateY(0) rotate(0);}
        100%{opacity:0;transform:translateY(70vh) rotate(540deg);}
    }
    #plToast{
        position:fixed;bottom:22px;left:50%;transform:translateX(-50%);z-index:1070;
        background:#1f2328;color:white;font-size:13px;padding:9px 14px 9px 18px;border-radius:8px;
        display:none;align-items:center;gap:12px;white-space:nowrap;
        box-shadow:0 6px 20px rgba(0,0,0,.22);
    }
    #plToast.show{display:flex;}
    #plToast button{
        background:none;border:none;color:#a78bfa;font-size:12.5px;font-weight:700;
        cursor:pointer;padding:2px 4px;
    }
    #plToast button:hover{color:white;}

    /* Week grid */
    .pl-week{display:grid;grid-template-columns:repeat(7,minmax(150px,1fr));gap:10px;overflow-x:auto;padding-bottom:4px;}
    @media(max-width:1100px){ .pl-week{grid-template-columns:repeat(7,minmax(160px,1fr));} }
    .pl-day{background:white;border:1px solid #e3e4e8;border-radius:10px;overflow:hidden;min-height:140px;}
    .pl-day.is-today{border-color:#c4b5fd;box-shadow:0 0 0 2px rgba(124,58,237,.12);}
    .pl-day-head{
        display:flex;align-items:center;justify-content:space-between;
        padding:9px 12px;background:#fafbfc;border-bottom:1px solid #e3e4e8;
    }
    .pl-day.is-today .pl-day-head{background:#faf5ff;}
    .pl-day-name{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#8a8f98;}
    .pl-day-date{font-size:14px;font-weight:700;color:#1a1d23;}
    .pl-day.is-today .pl-day-date{color:#7c3aed;}
    .pl-day-count{font-size:11px;font-weight:700;color:#adb0b8;background:#f0f1f3;border-radius:20px;padding:1px 8px;}
    .pl-day-body{padding:7px;display:flex;flex-direction:column;gap:6px;}
    .pl-day .pl-task{padding:7px 9px;}
    .pl-day .pl-task-title{font-size:12px;}
    .pl-day-empty{padding:14px 8px;text-align:center;color:#c4c9d4;font-size:11px;}
</style>
@endpush

@section('content')
<div class="main-content">

    @php
        $rangeLabel = $view === 'week'
            ? $start->format('M j') . ' – ' . $end->format('M j, Y')
            : $date->format('l, F j, Y');
        $prevDate = $view === 'week' ? $date->copy()->subWeek() : $date->copy()->subDay();
        $nextDate = $view === 'week' ? $date->copy()->addWeek() : $date->copy()->addDay();
    @endphp

    {{-- Header --}}
    <div class="pl-header">
        <h1 class="pl-header-title">{{ $view === 'week' ? 'My Week' : 'My Day' }}</h1>
        <p class="pl-header-sub">{{ $rangeLabel }}{{ $isToday ? ' · Today' : '' }}</p>
    </div>

    {{-- Toolbar --}}
    <div class="pl-toolbar">
        <div class="pl-toggle">
            <a href="{{ route('planner.index', ['view' => 'day', 'date' => $date->toDateString()]) }}"
               class="pl-toggle-btn {{ $view === 'day' ? 'active' : '' }}">
                <i class="bi bi-sun"></i> Day
            </a>
            <a href="{{ route('planner.index', ['view' => 'week', 'date' => $date->toDateString()]) }}"
               class="pl-toggle-btn {{ $view === 'week' ? 'active' : '' }}">
                <i class="bi bi-calendar-week"></i> Week
            </a>
        </div>
        <div class="pl-nav">
            <a href="{{ route('planner.index', ['view' => $view, 'date' => $prevDate->toDateString()]) }}"
               class="pl-nav-btn" title="Previous"><i class="bi bi-chevron-left"></i></a>
            <a href="{{ route('planner.index', ['view' => $view]) }}" class="pl-today-btn">Today</a>
            <a href="{{ route('planner.index', ['view' => $view, 'date' => $nextDate->toDateString()]) }}"
               class="pl-nav-btn" title="Next"><i class="bi bi-chevron-right"></i></a>
        </div>
    </div>

    @if($view === 'day')
        {{-- Stats --}}
        <div class="pl-stats">
            <div class="pl-stat"><i class="bi bi-arrow-repeat"></i> Routines <strong><span id="plRoutineDone">{{ $routineDone }}</span><span style="color:#adb0b8;font-weight:600;">/{{ $routineTotal }}</span></strong></div>
            <div class="pl-stat"><i class="bi bi-list-check"></i> Pending <strong id="plPendingCount">{{ $pending->count() }}</strong></div>
            <div class="pl-stat"><i class="bi bi-check-circle"></i> Done <strong id="plDoneCount">{{ $done->count() }}</strong></div>
            @if($overdue->count())
                <div class="pl-stat overdue"><i class="bi bi-exclamation-triangle"></i> Overdue <strong>{{ $overdue->count() }}</strong></div>
            @endif
        </div>

        {{-- Buckets: this week / this month --}}
        @if($bucketWeek->count() || $bucketMonth->count())
            <div class="pl-section">
                <div class="pl-section-head">
                    <i class="bi bi-calendar3" style="color:#2563eb;"></i>
                    <span class="pl-section-title">Upcoming</span>
                </div>
                <div class="pl-section-body">
                    @if($bucketWeek->count())
                        <div style="font-size:11px;font-weight:700;color:#8a8f98;text-transform:uppercase;letter-spacing:.5px;margin-top:4px;">This week</div>
                        @foreach($bucketWeek as $routine)
                            @include('planner._routine-row', ['routine' => $routine, 'routineDate' => $date, 'count' => false, 'toggleable' => false])
                        @endforeach
                    @endif
                    @if($bucketMonth->count())
                        <div style="font-size:11px;font-weight:700;color:#8a8f98;text-transform:uppercase;letter-spacing:.5px;margin-top:4px;">This month</div>
                        @foreach($bucketMonth as $routine)
                            @include('planner._routine-row', ['routine' => $routine, 'routineDate' => $date, 'count' => false, 'toggleable' => false])
                        @endforeach
                    @endif
                </div>
            </div>
        @endif

        {{-- Routines for the selected day --}}
        <div class="pl-section">
            <div class="pl-section-head">
                <i class="bi bi-arrow-repeat" style="color:#7c3aed;"></i>
                <span class="pl-section-title">{{ $isToday ? "Today's Routines" : 'Routines' }}</span>
                <span class="pl-section-count" id="plRoutineSectionCount">{{ $routineTotal }}</span>
            </div>
            <div class="pl-section-body" id="plRoutinesBody">
                @forelse($routines as $routine)
                    @include('planner._routine-row', ['routine' => $routine, 'routineDate' => $date, 'count' => true])
                @empty
                    <div class="pl-empty"><i class="bi bi-arrow-repeat"></i>No routines scheduled for this day.</div>
                @endforelse
            </div>
        </div>

        {{-- Overdue --}}
        @if($overdue->count())
            <div class="pl-section">
                <div class="pl-section-head">
                    <i class="bi bi-exclamation-triangle-fill" style="color:#dc2626;"></i>
                    <span class="pl-section-title">Overdue</span>
                    <span class="pl-section-count">{{ $overdue->count() }}</span>
                </div>
                <div class="pl-section-body">
                    @foreach($overdue as $task)
                        @include('planner._task-row', ['task' => $task, 'count' => false])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Today's tasks --}}
        <div class="pl-section">
            <div class="pl-section-head">
                <i class="bi bi-check2-square" style="color:#7c3aed;"></i>
                <span class="pl-section-title">{{ $isToday ? "Today's Tasks" : 'Tasks' }}</span>
                <span class="pl-section-count" id="plTodaySectionCount">{{ $pending->count() }}</span>
            </div>
            <div class="pl-section-body" id="plPendingBody">
                @forelse($pending as $task)
                    @include('planner._task-row', ['task' => $task, 'count' => true])
                @empty
                    <div class="pl-empty"><i class="bi bi-cup-hot"></i>Nothing scheduled for this day. Enjoy!</div>
                @endforelse
            </div>
        </div>

        {{-- Done --}}
        @if($done->count())
            <div class="pl-section">
                <div class="pl-section-head">
                    <i class="bi bi-check-circle-fill" style="color:#16a34a;"></i>
                    <span class="pl-section-title">Completed</span>
                    <span class="pl-section-count">{{ $done->count() }}</span>
                </div>
                <div class="pl-section-body">
                    @foreach($done as $task)
                        @include('planner._task-row', ['task' => $task, 'count' => true])
                    @endforeach
                </div>
            </div>
        @endif
    @else
        {{-- Week view --}}
        <div class="pl-week">
            @foreach($days as $day)
                @php $dayDate = $day['date']; $isDayToday = $dayDate->isToday(); @endphp
                <div class="pl-day {{ $isDayToday ? 'is-today' : '' }}">
                    <div class="pl-day-head">
                        <div>
                            <div class="pl-day-name">{{ $dayDate->format('D') }}</div>
                            <div class="pl-day-date">{{ $dayDate->format('M j') }}</div>
                        </div>
                        <span class="pl-day-count" title="Routines done">{{ $day['routineDone'] }}/{{ $day['routineTotal'] }}</span>
                    </div>
                    <div class="pl-day-body">
                        @if(count($day['routines']))
                            @foreach($day['routines'] as $routine)
                                @include('planner._routine-row', ['routine' => $routine, 'routineDate' => $dayDate, 'count' => false])
                            @endforeach
                        @endif
                        @forelse($day['tasks'] as $task)
                            @include('planner._task-row', ['task' => $task, 'hideDue' => true, 'count' => false])
                        @empty
                            @if(!count($day['routines']))
                                <div class="pl-day-empty">—</div>
                            @endif
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    </div>

    <div id="plConfetti" aria-hidden="true"></div>
    <div id="plToast" role="status"></div>
@endsection

@push('scripts')
<script>
    const PL_CSRF = '{{ csrf_token() }}';

    async function toggleTask(cb) {
        const url = cb.dataset.url;
        const id  = cb.dataset.id;
        cb.disabled = true;
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            document.querySelectorAll('[data-task-item][data-id="' + id + '"]').forEach(row => {
                row.classList.toggle('is-done', !!json.completed);
                row.dataset.completed = json.completed ? '1' : '0';
            });
            refreshCounters();
        } catch (e) {
            cb.checked = !cb.checked;
            console.error('[Planner] toggle failed', e);
        } finally {
            cb.disabled = false;
        }
    }

    function refreshCounters() {
        let pending = 0, done = 0;
        document.querySelectorAll('[data-task-item][data-count="1"]').forEach(el => {
            if (el.dataset.completed === '1') done++; else pending++;
        });
        const p = document.getElementById('plPendingCount');
        const d = document.getElementById('plDoneCount');
        const t = document.getElementById('plTodaySectionCount');
        if (p) p.textContent = pending;
        if (d) d.textContent = done;
        if (t) t.textContent = pending;
    }

    async function toggleRoutine(cb) {
        const url = cb.dataset.url;
        const id  = cb.dataset.id;
        const date = cb.dataset.date;
        cb.disabled = true;
        try {
            const json = await routineToggleRequest(url, date);
            applyRoutineToggle(id, date, !!json.completed);
            /* Package B: accurate flame from server + minimal undo toast */
            if (json.completed) {
                setStreak(id, json.streak ?? null);
                showRoutineToast(id, date);
                maybeCelebrate();
            } else {
                setStreak(id, json.streak ?? 0, true);
                hideRoutineToast();
            }
            refreshRoutineCounters();
        } catch (e) {
            cb.checked = !cb.checked;
            console.error('[Planner] routine toggle failed', e);
        } finally {
            cb.disabled = false;
        }
    }

    function routineToggleRequest(url, date) {
        return fetch(url + (url.includes('?') ? '&' : '?') + 'date=' + encodeURIComponent(date), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json' },
        }).then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        });
    }

    function applyRoutineToggle(id, date, completed) {
        document.querySelectorAll('[data-routine-item][data-id="' + id + '"][data-date="' + date + '"]').forEach(row => {
            row.classList.toggle('is-done', completed);
            row.dataset.completed = completed ? '1' : '0';
            const box = row.querySelector('input[type="checkbox"]');
            if (box) box.checked = completed;
        });
    }

    /* Flame reflects the server-computed streak (never inflated client-side) */
    function setStreak(id, streak, hideIfZero) {
        document.querySelectorAll('[data-routine-item][data-id="' + id + '"] .flame').forEach(fl => {
            if (!streak) {
                if (hideIfZero) fl.remove();
                return;
            }
            fl.textContent = '🔥' + streak;
        });
    }

    /* Minimal toast with undo (package B) */
    let plToastTimer = null;
    function showRoutineToast(id, date) {
        const toast = document.getElementById('plToast');
        if (!toast) return;
        const row = document.querySelector('[data-routine-item][data-id="' + id + '"][data-date="' + date + '"]');
        const title = row ? (row.querySelector('.pl-task-title')?.textContent || '').trim() : 'Routine';
        toast.replaceChildren();
        const span = document.createElement('span');
        span.textContent = title + ' done ✓';
        toast.appendChild(span);
        const undo = document.createElement('button');
        undo.type = 'button';
        undo.textContent = 'Undo';
        undo.onclick = () => {
            hideRoutineToast();
            try {
                routineToggleRequest(document.querySelector('[data-routine-item][data-id="' + id + '"][data-date="' + date + '"] input[type="checkbox"]')?.dataset.url || '', date)
                    .then(() => { applyRoutineToggle(id, date, false); refreshRoutineCounters(); });
            } catch (e) { /* keep UI state */ }
        };
        toast.appendChild(undo);
        toast.classList.add('show');
        clearTimeout(plToastTimer);
        plToastTimer = setTimeout(hideRoutineToast, 4500);
    }
    function hideRoutineToast() {
        const t = document.getElementById('plToast');
        if (t) { t.classList.remove('show'); t.replaceChildren(); }
        clearTimeout(plToastTimer);
    }

    /* One small confetti burst when ALL today's (counted) routines are done */
    function maybeCelebrate() {
        const items = document.querySelectorAll('[data-routine-item][data-count="1"]');
        if (!items.length) return;
        let done = 0;
        items.forEach(el => { if (el.dataset.completed === '1') done++; });
        if (done !== items.length) return;
        const host = document.getElementById('plConfetti');
        if (!host || host.childElementCount) return;
        const colors = ['#7c3aed','#a78bfa','#30a46c','#f59e0b','#e5484d','#0b6bcb'];
        for (let i = 0; i < 16; i++) {
            const p = document.createElement('i');
            p.style.left = (5 + Math.random() * 90) + '%';
            p.style.background = colors[i % colors.length];
            p.style.animationDelay = (Math.random() * .25) + 's';
            p.style.animationDuration = (1.1 + Math.random() * .7) + 's';
            host.appendChild(p);
        }
        setTimeout(() => host.replaceChildren(), 2400);
    }

    function refreshRoutineCounters() {
        let done = 0, total = 0;
        document.querySelectorAll('[data-routine-item][data-count="1"]').forEach(el => {
            total++;
            if (el.dataset.completed === '1') done++;
        });
        const rd = document.getElementById('plRoutineDone');
        const rs = document.getElementById('plRoutineSectionCount');
        if (rd) rd.textContent = done;
        if (rs) rs.textContent = total;

        // per-day counters in week view
        document.querySelectorAll('.pl-day').forEach(dayEl => {
            const routines = dayEl.querySelectorAll('[data-routine-item]');
            if (!routines.length) return;
            let d = 0;
            routines.forEach(r => { if (r.dataset.completed === '1') d++; });
            const countEl = dayEl.querySelector('.pl-day-count');
            if (countEl) countEl.textContent = d + '/' + routines.length;
        });
    }
</script>
@endpush
