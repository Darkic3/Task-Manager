@extends('layouts.app')

@section('title', 'Live Workout Session | ' . $workoutSession->day->title)

@push('styles')
<style>
    .ws-shell { padding: 18px 20px 120px; background: #f8fafc; min-height: 100vh; }
    .ws-wrap { max-width: 820px; margin: auto; }
    
    /* Header & Stats */
    .ws-header-card {
        background: white; border: 1px solid #e2e8f0; border-radius: var(--radius-lg);
        padding: 1.25rem; margin-bottom: 1rem; box-shadow: var(--shadow-sm);
    }
    .ws-title { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem; }
    .ws-sub { font-size: 0.8125rem; color: #64748b; }

    .ws-summary-pill {
        display: inline-flex; align-items: center; gap: 0.5rem;
        background: #f1f5f9; padding: 0.35rem 0.85rem; border-radius: 20px;
        font-size: 0.8125rem; font-weight: 600; color: #334155;
    }

    /* Exercise Card */
    .ws-card-ex {
        background: white; border: 1px solid #e2e8f0; border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm); margin-bottom: 1.25rem; overflow: hidden;
        transition: all 0.2s ease;
    }
    .ws-card-ex.is-completed { border-color: #86efac; }
    .ws-card-ex-head {
        padding: 1rem 1.25rem; background: #fafbfc; border-bottom: 1px solid #f1f5f9;
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;
    }
    .ws-ex-name { font-size: 1.15rem; font-weight: 700; color: #1e293b; }
    
    /* 3-Column Clean Big-Tap Set Rows */
    .set-row-clean {
        display: grid;
        grid-template-columns: 36px minmax(70px, 1.2fr) minmax(70px, 1fr) 42px 75px;
        gap: 8px; align-items: center;
        padding: 8px 12px; border-bottom: 1px solid #f8fafc;
        transition: background 0.15s ease;
    }
    .set-row-clean:hover { background: #fafbfc; }
    .set-row-clean.is-saved { background: #f0fdf4; }
    
    .set-num-badge {
        width: 28px; height: 28px; border-radius: 50%;
        background: #f1f5f9; color: #475569;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.75rem; font-weight: 700;
    }
    .set-row-clean.is-saved .set-num-badge { background: #dcfce7; color: #15803d; }

    .ws-input-big {
        width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px;
        padding: 8px 10px; font-size: 1rem; font-weight: 600;
        text-align: center; color: #0f172a; outline: none; background: white;
    }
    .ws-input-big:focus { border-color: var(--primary-600); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
    .ws-input-big::placeholder { color: #94a3b8; font-weight: 400; font-size: 0.875rem; }

    .btn-save-set {
        padding: 8px 10px; border-radius: 8px; font-size: 0.8125rem; font-weight: 700;
        display: flex; align-items: center; justify-content: center; gap: 4px;
        border: none; background: #e0e7ff; color: #4338ca; cursor: pointer; transition: all 0.15s;
    }
    .btn-save-set:hover { background: #c7d2fe; }
    .btn-save-set.saved { background: #16a34a; color: white; }

    .btn-more-toggle {
        width: 36px; height: 36px; border-radius: 8px; border: 1px solid #e2e8f0;
        background: white; color: #64748b; display: flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: 0.875rem;
    }

    .set-more-fields {
        background: #f8fafc; padding: 10px 14px; border-radius: 8px; margin: 4px 8px 8px;
        display: none; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 8px;
    }
    .set-more-fields.show { display: grid; }

    /* PR Golden Badge */
    .pr-badge {
        display: inline-flex; align-items: center; gap: 3px;
        background: linear-gradient(135deg, #fbbf24, #d97706);
        color: white; font-size: 10px; font-weight: 800;
        padding: 2px 7px; border-radius: 12px;
        box-shadow: 0 2px 6px rgba(217, 119, 6, 0.3);
        animation: pop-in 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    @keyframes pop-in { from { transform: scale(0.6); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    /* Floating Auto-Rest Timer Bar */
    .floating-rest-bar {
        position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
        background: #0f172a; color: white; border-radius: 40px;
        padding: 8px 16px 8px 22px; display: none; align-items: center; gap: 14px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 1050;
        border: 1px solid #334155;
    }
    .floating-rest-bar.show { display: flex; animation: slide-up 0.25s ease; }
    @keyframes slide-up { from { transform: translate(-50%, 40px); opacity: 0; } to { transform: translate(-50%, 0); opacity: 1; } }
    .rest-clock { font-size: 1.25rem; font-weight: 800; font-family: monospace; color: #86efac; }

    .ghost-hint { font-size: 0.72rem; color: #94a3b8; font-weight: 500; }
</style>
@endpush

@php
    $exerciseLogs = $logsByExercise;
    $exerciseCount = $workoutSession->day->exercises->count();
    $completedCount = $workoutSession->exerciseLogs->where('completed', true)->count();
@endphp

@section('content')
<div class="ws-shell">
    <div class="ws-wrap">

        {{-- Top Header --}}
        <div class="ws-header-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <a href="{{ route('workouts.plans.index') }}" class="small text-muted text-decoration-none">
                        <i class="bi bi-arrow-left"></i> All Workout Plans
                    </a>
                    <h1 class="ws-title mt-1">{{ $workoutSession->day->title }}</h1>
                    <div class="ws-sub">
                        {{ ucfirst($workoutSession->day->weekday) }} • {{ $workoutSession->workout_date->format('l, M j, Y') }} • {{ $workoutSession->day->plan->title }}
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="ws-summary-pill">
                        <i class="bi bi-stopwatch text-primary"></i> <span id="sessionDuration">{{ $workoutSession->durationMinutes() ?? 0 }}m</span>
                    </span>
                    <span class="ws-summary-pill">
                        <i class="bi bi-check2-circle text-success"></i> <strong id="doneCount">{{ $completedCount }}</strong>/{{ $exerciseCount }} Movements
                    </span>
                </div>
            </div>

            <div class="progress" style="height: 6px;">
                <div class="progress-bar bg-primary" id="progressFill" style="width: {{ $exerciseCount ? round($completedCount / $exerciseCount * 100) : 0 }}%"></div>
            </div>
        </div>

        @if($workoutSession->day->notes)
            <div class="alert alert-light border p-3 small mb-3 text-secondary">
                <i class="bi bi-info-circle text-primary me-1"></i> <strong>Day notes:</strong> {{ $workoutSession->day->notes }}
            </div>
        @endif

        {{-- Exercise Cards --}}
        @forelse($workoutSession->day->exercises as $workoutExercise)
            @php
                $log = $exerciseLogs->get($workoutExercise->id);
                $previous = $previousByExercise->get($workoutExercise->exercise_id);
                $targetSets = $workoutExercise->requiredSetCount();
                $setRows = max($targetSets, $log?->setLogs?->count() ?? 0);
            @endphp
            <article class="ws-card-ex {{ $log?->completed ? 'is-completed' : '' }}" data-exercise-card data-exercise-id="{{ $workoutExercise->id }}" data-rest="{{ $workoutExercise->rest_seconds ?: 90 }}">
                
                {{-- Exercise Head --}}
                <div class="ws-card-ex-head">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="ws-ex-name">{{ $workoutExercise->exercise->name }}</span>
                            <span class="badge {{ $log?->completed ? 'bg-success text-white' : 'bg-light text-muted border' }}" data-exercise-status style="font-size:10px;">
                                {{ $log?->completed ? 'Completed' : 'Open' }}
                            </span>
                        </div>
                        <div class="small text-muted mt-1 d-flex flex-wrap gap-2">
                            <span><i class="bi bi-bullseye"></i> {{ $workoutExercise->targetLabel() }}</span>
                            @if($workoutExercise->target_rir !== null)
                                <span>• RIR {{ $workoutExercise->target_rir }}</span>
                            @endif
                            @if($workoutExercise->rest_seconds)
                                <span>• Rest {{ $workoutExercise->rest_seconds }}s</span>
                            @endif
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="startRestCountdown({{ $workoutExercise->rest_seconds ?: 90 }})">
                            <i class="bi bi-stopwatch"></i> Rest
                        </button>
                        @if($previous)
                            <button type="button" class="btn btn-sm btn-light border text-primary rounded-pill use-previous-btn" data-previous="{{ json_encode($previous['sets']) }}" title="Copy last session values">
                                <i class="bi bi-copy"></i> Autofill Last
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Previous Session Mini Info --}}
                @if($previous)
                <div class="bg-light px-3 py-2 border-bottom d-flex align-items-center justify-content-between text-muted small" style="font-size:11px;">
                    <div>
                        <i class="bi bi-clock-history me-1"></i> Last session ({{ $previous['date'] }}): 
                        @foreach($previous['sets'] as $ps)
                            <span class="badge bg-white text-dark border ms-1">{{ $ps['weight'] ?? 0 }}kg × {{ $ps['reps'] ?? 0 }}</span>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Set Rows List --}}
                <div class="p-2 set-list">
                    {{-- Headers --}}
                    <div class="set-row-clean text-muted small fw-bold px-3 py-1" style="font-size:10px; text-transform:uppercase;">
                        <span>Set</span>
                        <span>Weight (kg)</span>
                        <span>Reps</span>
                        <span></span>
                        <span class="text-center">Action</span>
                    </div>

                    @for($setNumber = 1; $setNumber <= $setRows; $setNumber++)
                        @php
                            $set = $log?->setLogs?->firstWhere('set_number', $setNumber);
                            $prevSet = $previous ? collect($previous['sets'])->firstWhere('set_number', $setNumber) : null;
                            $ghostWeight = $prevSet['weight'] ?? '';
                            $ghostReps = $prevSet['reps'] ?? $workoutExercise->rep_min ?? '';
                        @endphp
                        <div class="set-row-clean {{ $set?->completed ? 'is-saved' : '' }}" data-set-row data-set-number="{{ $setNumber }}">
                            <div class="set-num-badge">{{ $setNumber }}</div>

                            <div>
                                <input type="number" step="0.5" min="0" class="ws-input-big" data-field="weight" 
                                       value="{{ $set?->weight }}" 
                                       placeholder="{{ $ghostWeight ? $ghostWeight . ' kg' : 'kg' }}"
                                       onclick="if(!this.value && '{{ $ghostWeight }}') this.value='{{ $ghostWeight }}';">
                            </div>

                            <div>
                                <input type="number" step="1" min="0" class="ws-input-big" data-field="reps" 
                                       value="{{ $set?->reps }}" 
                                       placeholder="{{ $ghostReps ? $ghostReps . ' reps' : 'reps' }}"
                                       onclick="if(!this.value && '{{ $ghostReps }}') this.value='{{ $ghostReps }}';">
                            </div>

                            <button type="button" class="btn-more-toggle" onclick="toggleSetMore(this)" title="Advanced set options (RIR, form, notes)">
                                <i class="bi bi-sliders"></i>
                            </button>

                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn-save-set w-100 {{ $set?->completed ? 'saved' : '' }}" onclick="saveSetRow(this)">
                                    <i class="bi {{ $set?->completed ? 'bi-check-lg' : 'bi-check2' }}"></i>
                                    <span>{{ $set?->completed ? 'Done' : 'Save' }}</span>
                                </button>
                            </div>

                            {{-- Collapsible Advanced More Options --}}
                            <div class="set-more-fields col-12" style="grid-column: 1 / -1;">
                                <div>
                                    <label class="small text-muted">RIR (in reserve)</label>
                                    <input type="number" min="0" max="10" step="0.5" class="form-control form-control-sm" data-field="rir" value="{{ $set?->rir }}" placeholder="Target: {{ $workoutExercise->target_rir ?? '—' }}">
                                </div>
                                <div>
                                    <label class="small text-muted">Form Rating</label>
                                    <select class="form-select form-select-sm" data-field="form_rating">
                                        <option value="">—</option>
                                        @for($r=1; $r<=5; $r++)
                                            <option value="{{ $r }}" @selected((int) $set?->form_rating === $r)>{{ $r }}/5 ⭐</option>
                                        @endfor
                                    </select>
                                </div>
                                <div>
                                    <label class="small text-muted">Pain Level (0-10)</label>
                                    <input type="number" min="0" max="10" class="form-control form-control-sm" data-field="pain_level" value="{{ $set?->pain_level }}" placeholder="0">
                                </div>
                                <div>
                                    <label class="small text-muted">Set Note</label>
                                    <input type="text" class="form-control form-control-sm" data-field="note" value="{{ $set?->note }}" placeholder="Optional notes">
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>

                {{-- Add Set & Movement Notes --}}
                <div class="px-3 pb-3 pt-1 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 rounded-pill" onclick="addNewSetRow(this)">
                        <i class="bi bi-plus-lg"></i> Add Set
                    </button>
                    <span class="small text-muted exercise-1rm-display" id="ex-1rm-{{ $workoutExercise->id }}"></span>
                </div>
            </article>
        @empty
            <div class="text-center py-5 bg-white rounded-3 border">
                <i class="bi bi-cup-hot text-warning fs-1 mb-2 d-block"></i>
                <h5>No movements planned for today</h5>
                <p class="text-muted small">Enjoy your recovery day!</p>
            </div>
        @endforelse

        {{-- Finish Session Card --}}
        <form class="ws-header-card mt-4" method="POST" action="{{ route('workouts.sessions.finish', $workoutSession) }}">
            @csrf
            @method('PATCH')
            <h5 class="fw-bold mb-3"><i class="bi bi-flag-fill text-primary me-1"></i> Finish Workout Session</h5>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted">Session Summary / How did it feel?</label>
                    <textarea class="form-control" name="session_note" rows="2" placeholder="Great energy, increased bench press weight...">{{ $workoutSession->session_note }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted">Pain / Limitations (if any)</label>
                    <textarea class="form-control" name="pain_note" rows="2" placeholder="Slight shoulder tightness on second set...">{{ $workoutSession->pain_note }}</textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button type="submit" name="status" value="skipped" class="btn btn-light border px-3">Mark Skipped</button>
                <button type="submit" name="status" value="completed" class="btn btn-success fw-bold px-4 shadow-sm">
                    <i class="bi bi-check-circle-fill me-1"></i> Finish Workout
                </button>
            </div>
        </form>

    </div>
</div>

{{-- Floating Auto-Rest Timer Bar --}}
<div class="floating-rest-bar" id="floatingRestBar">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-stopwatch-fill text-warning"></i>
        <span class="small text-white-50">Rest Timer:</span>
        <span class="rest-clock" id="floatingRestClock">01:30</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-dark border-secondary text-white rounded-pill px-2 py-0" onclick="adjustRestTimer(30)" style="font-size:11px;">+30s</button>
        <button type="button" class="btn btn-sm btn-dark border-secondary text-white rounded-pill px-2 py-0" onclick="adjustRestTimer(-30)" style="font-size:11px;">-30s</button>
        <button type="button" class="btn btn-sm btn-danger rounded-pill px-3 py-1 ms-1" onclick="stopRestTimer()" style="font-size:11px;">Skip</button>
    </div>
</div>

<div class="ws-toast" id="toast"></div>
@endsection

@push('scripts')
<script>
    const WS_TOKEN = '{{ csrf_token() }}';
    const SET_URL = '{{ route("workouts.sessions.sets.store", $workoutSession) }}';

    // Toast helper
    function toast(msg) {
        const el = document.getElementById('toast');
        if (el) {
            el.textContent = msg;
            el.classList.add('show');
            setTimeout(() => el.classList.remove('show'), 2000);
        }
    }

    // Toggle advanced options
    function toggleSetMore(btn) {
        const row = btn.closest('[data-set-row]');
        const fields = row.querySelector('.set-more-fields');
        if (fields) fields.classList.toggle('show');
    }

    // Web Audio API beep for rest timer completion
    function playChime() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.setValueAtTime(880, ctx.currentTime + 0.15); // A5
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
            osc.start();
            osc.stop(ctx.currentTime + 0.5);
        } catch (e) {
            console.log(e);
        }
    }

    // Rest Timer Logic
    let restInterval = null;
    let restSecondsLeft = 0;

    function startRestCountdown(seconds) {
        clearInterval(restInterval);
        restSecondsLeft = seconds;
        const bar = document.getElementById('floatingRestBar');
        bar.classList.add('show');
        updateRestClockDisplay();

        restInterval = setInterval(() => {
            restSecondsLeft--;
            updateRestClockDisplay();
            if (restSecondsLeft <= 0) {
                clearInterval(restInterval);
                playChime();
                toast('🔔 Rest Time Complete! Next Set Ready.');
                setTimeout(() => {
                    bar.classList.remove('show');
                }, 3000);
            }
        }, 1000);
    }

    function updateRestClockDisplay() {
        const mins = String(Math.floor(Math.max(0, restSecondsLeft) / 60)).padStart(2, '0');
        const secs = String(Math.max(0, restSecondsLeft) % 60).padStart(2, '0');
        const clock = document.getElementById('floatingRestClock');
        if (clock) clock.textContent = `${mins}:${secs}`;
    }

    function adjustRestTimer(delta) {
        restSecondsLeft = Math.max(0, restSecondsLeft + delta);
        updateRestClockDisplay();
    }

    function stopRestTimer() {
        clearInterval(restInterval);
        document.getElementById('floatingRestBar').classList.remove('show');
    }

    // Save Set Row & PR Detection
    async function saveSetRow(btn) {
        const row = btn.closest('[data-set-row]');
        const card = btn.closest('[data-exercise-card]');
        btn.disabled = true;

        const payload = {
            workout_exercise_id: card.dataset.exerciseId,
            set_number: row.dataset.setNumber,
            completed: true
        };

        row.querySelectorAll('[data-field]').forEach(input => {
            if (input.value !== '') payload[input.dataset.field] = input.value;
        });

        try {
            const res = await fetch(SET_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': WS_TOKEN
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Error saving set');

            row.classList.add('is-saved');
            btn.classList.add('saved');
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Done';

            // PR Hunter Celebration!
            if (data.is_pr) {
                playChime();
                let prEl = row.querySelector('.pr-badge');
                if (!prEl) {
                    prEl = document.createElement('span');
                    prEl.className = 'pr-badge';
                    prEl.innerHTML = '🏆 NEW PR!';
                    row.querySelector('.set-num-badge').after(prEl);
                }
                toast('🏆 INCREDIBLE! New Personal Record (PR) achieved!');
            }

            // Update 1RM display
            if (data.estimated_1rm > 0) {
                const ex1rmEl = document.getElementById('ex-1rm-' + card.dataset.exerciseId);
                if (ex1rmEl) ex1rmEl.innerHTML = `Est. 1RM: <strong>${data.estimated_1rm} kg</strong>`;
            }

            // Update completed badge
            const status = card.querySelector('[data-exercise-status]');
            if (status) {
                status.textContent = data.exercise_completed ? 'Completed' : 'In progress';
                status.className = `badge ${data.exercise_completed ? 'bg-success text-white' : 'bg-light text-muted border'}`;
                card.classList.toggle('is-completed', data.exercise_completed);
            }

            updateSessionProgress();

            // Trigger Automatic Rest Timer!
            const restSeconds = Number(card.dataset.rest) || 90;
            startRestCountdown(restSeconds);

        } catch (e) {
            toast(e.message);
        } finally {
            btn.disabled = false;
        }
    }

    function updateSessionProgress() {
        const cards = [...document.querySelectorAll('[data-exercise-card]')];
        const completed = cards.filter(c => c.classList.contains('is-completed')).length;
        document.getElementById('doneCount').textContent = completed;
        document.getElementById('progressFill').style.width = (cards.length ? (completed / cards.length * 100) : 0) + '%';
    }

    // Add new set row dynamically
    function addNewSetRow(addBtn) {
        const card = addBtn.closest('[data-exercise-card]');
        const list = card.querySelector('.set-list');
        const count = list.querySelectorAll('[data-set-row]').length + 1;

        const row = document.createElement('div');
        row.className = 'set-row-clean';
        row.dataset.setRow = '1';
        row.dataset.setNumber = count;
        row.innerHTML = `
            <div class="set-num-badge">${count}</div>
            <div><input type="number" step="0.5" min="0" class="ws-input-big" data-field="weight" placeholder="kg"></div>
            <div><input type="number" step="1" min="0" class="ws-input-big" data-field="reps" placeholder="reps"></div>
            <button type="button" class="btn-more-toggle" onclick="toggleSetMore(this)"><i class="bi bi-sliders"></i></button>
            <div class="d-flex align-items-center gap-1">
                <button type="button" class="btn-save-set w-100" onclick="saveSetRow(this)">
                    <i class="bi bi-check2"></i> Save
                </button>
            </div>
            <div class="set-more-fields col-12" style="grid-column: 1 / -1;">
                <div><label class="small text-muted">RIR</label><input type="number" min="0" max="10" step="0.5" class="form-control form-control-sm" data-field="rir" placeholder="RIR"></div>
                <div><label class="small text-muted">Form</label><select class="form-select form-select-sm" data-field="form_rating"><option value="">—</option><option value="5">5/5 ⭐</option><option value="4">4/5</option><option value="3">3/5</option></select></div>
                <div><label class="small text-muted">Pain</label><input type="number" min="0" max="10" class="form-control form-control-sm" data-field="pain_level" placeholder="0"></div>
                <div><label class="small text-muted">Note</label><input type="text" class="form-control form-control-sm" data-field="note" placeholder="Optional"></div>
            </div>
        `;
        list.appendChild(row);
        row.querySelector('[data-field="weight"]').focus();
    }

    // Autofill Previous Session Values
    document.querySelectorAll('.use-previous-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const card = btn.closest('[data-exercise-card]');
            const prevSets = JSON.parse(btn.dataset.previous || '[]');
            prevSets.forEach(ps => {
                const row = card.querySelector(`[data-set-number="${ps.set_number}"]`);
                if (row) {
                    if (ps.weight !== null) row.querySelector('[data-field="weight"]').value = ps.weight;
                    if (ps.reps !== null) row.querySelector('[data-field="reps"]').value = ps.reps;
                }
            });
            toast('✓ Last session values filled!');
        });
    });
</script>
@endpush
