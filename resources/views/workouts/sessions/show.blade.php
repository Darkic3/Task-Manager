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
    .ws-card-ex.is-skipped { opacity: 0.75; border-color: #fca5a5; background: #fffbfb; }
    .ws-card-ex-head {
        padding: 1rem 1.25rem; background: #fafbfc; border-bottom: 1px solid #f1f5f9;
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;
    }
    .ws-ex-name { font-size: 1.15rem; font-weight: 700; color: #1e293b; }
    
    /* Clean Big-Tap Set Rows */
    .set-row-clean {
        display: grid;
        grid-template-columns: 36px minmax(70px, 1.2fr) minmax(70px, 1fr) 40px 75px;
        gap: 8px; align-items: center;
        padding: 8px 12px; border-bottom: 1px solid #f8fafc;
        transition: background 0.15s ease;
    }
    .set-row-clean.time-mode {
        grid-template-columns: 36px minmax(120px, 2fr) minmax(70px, 1fr) 40px 75px;
    }
    .set-row-clean:hover { background: #fafbfc; }
    .set-row-clean.is-saved { background: #f0fdf4; }
    .set-row-clean.is-skipped { background: #fef2f2; text-decoration: line-through; }
    
    .set-num-badge {
        width: 28px; height: 28px; border-radius: 50%;
        background: #f1f5f9; color: #475569;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.75rem; font-weight: 700;
    }
    .set-row-clean.is-saved .set-num-badge { background: #dcfce7; color: #15803d; }
    .set-row-clean.is-skipped .set-num-badge { background: #fee2e2; color: #b91c1c; }

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
                
                // Check if this movement is time/duration based (e.g. mobility, stretching, planks)
                $isTimedMovement = !empty($workoutExercise->duration_seconds) 
                    || (empty($workoutExercise->rep_min) && empty($workoutExercise->rep_max) && empty($workoutExercise->target_weight));
                $isSkipped = !empty($log?->skip_reason);
            @endphp
            <article class="ws-card-ex {{ $log?->completed ? 'is-completed' : '' }} {{ $isSkipped ? 'is-skipped' : '' }}" 
                     data-exercise-card 
                     data-exercise-id="{{ $workoutExercise->id }}" 
                     data-rest="{{ $workoutExercise->rest_seconds ?: 90 }}"
                     data-is-timed="{{ $isTimedMovement ? '1' : '0' }}">
                
                {{-- Exercise Head --}}
                <div class="ws-card-ex-head">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="ws-ex-name">{{ $workoutExercise->exercise->name }}</span>
                            @if($isSkipped)
                                <span class="badge bg-danger text-white" data-exercise-status style="font-size:10px;">
                                    Skipped: {{ $log->skip_reason }}
                                </span>
                            @else
                                <span class="badge {{ $log?->completed ? 'bg-success text-white' : 'bg-light text-muted border' }}" data-exercise-status style="font-size:10px;">
                                    {{ $log?->completed ? 'Completed' : 'Open' }}
                                </span>
                            @endif
                            @if($isTimedMovement)
                                <span class="badge bg-info-subtle text-info border" style="font-size:10px;"><i class="bi bi-clock"></i> Timed / Mobility</span>
                            @endif
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
                                <i class="bi bi-copy"></i> Autofill
                            </button>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="openSkipModal({{ $workoutExercise->id }}, '{{ addslashes($workoutExercise->exercise->name) }}')" title="Skip this entire exercise">
                            <i class="bi bi-skip-forward"></i> Skip
                        </button>
                    </div>
                </div>

                {{-- Previous Session Mini Info --}}
                @if($previous)
                <div class="bg-light px-3 py-2 border-bottom d-flex align-items-center justify-content-between text-muted small" style="font-size:11px;">
                    <div>
                        <i class="bi bi-clock-history me-1"></i> Last session ({{ $previous['date'] }}): 
                        @foreach($previous['sets'] as $ps)
                            @if($isTimedMovement)
                                <span class="badge bg-white text-dark border ms-1">{{ $ps['duration_seconds'] ?? 45 }}s</span>
                            @else
                                <span class="badge bg-white text-dark border ms-1">{{ $ps['weight'] ?? 0 }}kg × {{ $ps['reps'] ?? 0 }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Set Rows List --}}
                <div class="p-2 set-list">
                    {{-- Headers --}}
                    <div class="set-row-clean {{ $isTimedMovement ? 'time-mode' : '' }} text-muted small fw-bold px-3 py-1" style="font-size:10px; text-transform:uppercase;">
                        <span>Set</span>
                        @if($isTimedMovement)
                            <span>Duration (Seconds / Mins)</span>
                            <span>RIR / Effort</span>
                        @else
                            <span>Weight (kg)</span>
                            <span>Reps</span>
                        @endif
                        <span></span>
                        <span class="text-center">Action</span>
                    </div>

                    @for($setNumber = 1; $setNumber <= $setRows; $setNumber++)
                        @php
                            $set = $log?->setLogs?->firstWhere('set_number', $setNumber);
                            $prevSet = $previous ? collect($previous['sets'])->firstWhere('set_number', $setNumber) : null;
                            $ghostWeight = $prevSet['weight'] ?? '';
                            $ghostReps = $prevSet['reps'] ?? $workoutExercise->rep_min ?? '';
                            $ghostDuration = $prevSet['duration_seconds'] ?? $workoutExercise->duration_seconds ?? '45';
                        @endphp
                        <div class="set-row-clean {{ $isTimedMovement ? 'time-mode' : '' }} {{ $set?->completed ? 'is-saved' : '' }}" 
                             data-set-row 
                             data-set-number="{{ $setNumber }}">
                            
                            <div class="set-num-badge">{{ $setNumber }}</div>

                            @if($isTimedMovement)
                                {{-- Timed / Mobility Mode Input --}}
                                <div>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="1" min="0" class="ws-input-big form-control" data-field="duration_seconds" 
                                               value="{{ $set?->duration_seconds }}" 
                                               placeholder="{{ $ghostDuration ? $ghostDuration . 's' : 'seconds' }}">
                                        <span class="input-group-text bg-light text-muted small">sec</span>
                                    </div>
                                </div>
                                <div>
                                    <input type="number" step="0.5" min="0" max="10" class="ws-input-big" data-field="rir" 
                                           value="{{ $set?->rir }}" 
                                           placeholder="RIR (0-10)">
                                </div>
                            @else
                                {{-- Weight & Reps Lifting Mode Input --}}
                                <div>
                                    <input type="number" step="0.5" min="0" class="ws-input-big" data-field="weight" 
                                           value="{{ $set?->weight }}" 
                                           placeholder="{{ $ghostWeight ? $ghostWeight . ' kg' : 'kg' }}">
                                </div>

                                <div>
                                    <input type="number" step="1" min="0" class="ws-input-big" data-field="reps" 
                                           value="{{ $set?->reps }}" 
                                           placeholder="{{ $ghostReps ? $ghostReps . ' reps' : 'reps' }}">
                                </div>
                            @endif

                            <button type="button" class="btn-more-toggle" onclick="toggleSetMore(this)" title="Advanced set options (RIR, form, notes, skip set)">
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
                                @if(!$isTimedMovement)
                                <div>
                                    <label class="small text-muted">RIR (in reserve)</label>
                                    <input type="number" min="0" max="10" step="0.5" class="form-control form-control-sm" data-field="rir" value="{{ $set?->rir }}" placeholder="Target: {{ $workoutExercise->target_rir ?? '—' }}">
                                </div>
                                @endif
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
                                    <label class="small text-muted">Set Note / Reason</label>
                                    <input type="text" class="form-control form-control-sm" data-field="note" value="{{ $set?->note }}" placeholder="Optional notes">
                                </div>
                                <div class="d-flex align-items-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="openSkipSetModal({{ $workoutExercise->id }}, {{ $setNumber }}, '{{ addslashes($workoutExercise->exercise->name) }}')">
                                        <i class="bi bi-slash-circle me-1"></i> Skip Set {{ $setNumber }}
                                    </button>
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

{{-- Unified Skip Modal (Exercise or Set) with Reason Input --}}
<div class="modal fade" id="skipMovementModal" tabindex="-1" aria-labelledby="skipMovementTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 bg-light p-3">
                <h6 class="modal-title fw-bold" id="skipMovementTitle">Skip Movement</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-3" id="skipMovementPrompt">Why are you skipping this?</p>
                <input type="hidden" id="skipExerciseId">
                <input type="hidden" id="skipSetNumber">
                <input type="hidden" id="skipMode" value="exercise">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Reason (Optional)</label>
                    <input type="text" class="form-control" id="skipReasonInput" placeholder="e.g. Equipment busy, muscle fatigue, joint discomfort" onkeydown="if(event.key==='Enter'){event.preventDefault();submitSkipAction();}">
                </div>
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <button type="button" class="btn btn-sm btn-light border" onclick="setQuickSkipReason('Equipment Busy')">Equipment Busy</button>
                    <button type="button" class="btn btn-sm btn-light border" onclick="setQuickSkipReason('Joint Discomfort / Pain')">Joint Discomfort</button>
                    <button type="button" class="btn btn-sm btn-light border" onclick="setQuickSkipReason('Fatigue / Ran out of time')">Out of Time</button>
                    <button type="button" class="btn btn-sm btn-light border" onclick="setQuickSkipReason('Form breakdown / Overload')">Form Breakdown</button>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4" id="skipSubmitBtn" onclick="submitSkipAction()">
                    <i class="bi bi-skip-forward me-1"></i> Confirm Skip
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Floating Auto-Rest Timer Bar --}}
<div class="floating-rest-bar" id="floatingRestBar">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-stopwatch-fill text-warning"></i>
        <span class="small text-white-50">Rest:</span>
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

    // Enter Key Listener across all inputs to immediately save the set!
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            const input = event.target;
            if (input.matches('[data-field]')) {
                event.preventDefault();
                const row = input.closest('[data-set-row]');
                const btn = row?.querySelector('.btn-save-set');
                if (btn) saveSetRow(btn);
            }
        }
    });

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
            osc.frequency.setValueAtTime(587.33, ctx.currentTime);
            osc.frequency.setValueAtTime(880, ctx.currentTime + 0.15);
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

            // Auto-advance focus to next set row's input if present
            const nextRow = row.nextElementSibling;
            if (nextRow && nextRow.matches('[data-set-row]')) {
                const nextInput = nextRow.querySelector('[data-field]');
                if (nextInput) setTimeout(() => nextInput.focus(), 150);
            }

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
        const isTimed = card.dataset.isTimed === '1';

        const row = document.createElement('div');
        row.className = `set-row-clean ${isTimed ? 'time-mode' : ''}`;
        row.dataset.setRow = '1';
        row.dataset.setNumber = count;

        if (isTimed) {
            row.innerHTML = `
                <div class="set-num-badge">${count}</div>
                <div>
                    <div class="input-group input-group-sm">
                        <input type="number" step="1" min="0" class="ws-input-big form-control" data-field="duration_seconds" placeholder="seconds">
                        <span class="input-group-text bg-light text-muted small">sec</span>
                    </div>
                </div>
                <div><input type="number" step="0.5" min="0" max="10" class="ws-input-big" data-field="rir" placeholder="RIR"></div>
                <button type="button" class="btn-more-toggle" onclick="toggleSetMore(this)"><i class="bi bi-sliders"></i></button>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn-save-set w-100" onclick="saveSetRow(this)">
                        <i class="bi bi-check2"></i> Save
                    </button>
                </div>
                <div class="set-more-fields col-12" style="grid-column: 1 / -1;">
                    <div><label class="small text-muted">Form</label><select class="form-select form-select-sm" data-field="form_rating"><option value="">—</option><option value="5">5/5 ⭐</option><option value="4">4/5</option><option value="3">3/5</option></select></div>
                    <div><label class="small text-muted">Pain</label><input type="number" min="0" max="10" class="form-control form-control-sm" data-field="pain_level" placeholder="0"></div>
                    <div><label class="small text-muted">Note</label><input type="text" class="form-control form-control-sm" data-field="note" placeholder="Optional"></div>
                    <div class="d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="openSkipSetModal(${card.dataset.exerciseId}, ${count}, '${card.querySelector('.ws-ex-name')?.textContent || 'Exercise'}')">
                            <i class="bi bi-slash-circle me-1"></i> Skip Set ${count}
                        </button>
                    </div>
                </div>
            `;
        } else {
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
                    <div class="d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="openSkipSetModal(${card.dataset.exerciseId}, ${count}, '${card.querySelector('.ws-ex-name')?.textContent || 'Exercise'}')">
                            <i class="bi bi-slash-circle me-1"></i> Skip Set ${count}
                        </button>
                    </div>
                </div>
            `;
        }

        list.appendChild(row);
        row.querySelector('[data-field]').focus();
    }

    // Autofill Previous Session Values
    document.querySelectorAll('.use-previous-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const card = btn.closest('[data-exercise-card]');
            const prevSets = JSON.parse(btn.dataset.previous || '[]');
            prevSets.forEach(ps => {
                const row = card.querySelector(`[data-set-number="${ps.set_number}"]`);
                if (row) {
                    if (ps.weight !== null && row.querySelector('[data-field="weight"]')) {
                        row.querySelector('[data-field="weight"]').value = ps.weight;
                    }
                    if (ps.reps !== null && row.querySelector('[data-field="reps"]')) {
                        row.querySelector('[data-field="reps"]').value = ps.reps;
                    }
                    if (ps.duration_seconds !== null && row.querySelector('[data-field="duration_seconds"]')) {
                        row.querySelector('[data-field="duration_seconds"]').value = ps.duration_seconds;
                    }
                }
            });
            toast('✓ Last session values filled!');
        });
    });

    // Skip Modal Logic (Supports both Movement and Individual Set)
    function openSkipModal(exerciseId, exerciseName) {
        document.getElementById('skipMode').value = 'exercise';
        document.getElementById('skipExerciseId').value = exerciseId;
        document.getElementById('skipSetNumber').value = '';
        document.getElementById('skipMovementTitle').textContent = 'Skip Entire Exercise';
        document.getElementById('skipMovementPrompt').textContent = `Why are you skipping all sets for "${exerciseName}"?`;
        document.getElementById('skipReasonInput').value = '';
        const modal = new bootstrap.Modal(document.getElementById('skipMovementModal'));
        modal.show();
    }

    function openSkipSetModal(exerciseId, setNumber, exerciseName) {
        document.getElementById('skipMode').value = 'set';
        document.getElementById('skipExerciseId').value = exerciseId;
        document.getElementById('skipSetNumber').value = setNumber;
        document.getElementById('skipMovementTitle').textContent = `Skip Set #${setNumber}`;
        document.getElementById('skipMovementPrompt').textContent = `Reason for skipping Set #${setNumber} of "${exerciseName}"?`;
        document.getElementById('skipReasonInput').value = '';
        const modal = new bootstrap.Modal(document.getElementById('skipMovementModal'));
        modal.show();
    }

    function setQuickSkipReason(reason) {
        document.getElementById('skipReasonInput').value = reason;
    }

    async function submitSkipAction() {
        const mode = document.getElementById('skipMode').value;
        const exerciseId = document.getElementById('skipExerciseId').value;
        const setNumber = document.getElementById('skipSetNumber').value || '1';
        const reason = document.getElementById('skipReasonInput').value.trim() || 'Skipped';
        const card = document.querySelector(`[data-exercise-card][data-exercise-id="${exerciseId}"]`);

        const payload = {
            workout_exercise_id: exerciseId,
            set_number: parseInt(setNumber, 10),
            completed: false
        };

        if (mode === 'exercise') {
            payload.skip_reason = reason;
        } else {
            payload.note = 'Skipped: ' + reason;
        }

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

            if (res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('skipMovementModal'))?.hide();
                if (mode === 'exercise' && card) {
                    card.classList.add('is-skipped');
                    const status = card.querySelector('[data-exercise-status]');
                    if (status) {
                        status.textContent = 'Skipped: ' + reason;
                        status.className = 'badge bg-danger text-white';
                    }
                    toast('✓ Exercise marked as skipped.');
                } else if (mode === 'set' && card) {
                    const row = card.querySelector(`[data-set-row][data-set-number="${setNumber}"]`);
                    if (row) {
                        row.classList.add('is-skipped');
                        const btn = row.querySelector('.btn-save-set');
                        if (btn) {
                            btn.classList.add('btn-outline-danger');
                            btn.innerHTML = `<i class="bi bi-slash-circle"></i> Skipped`;
                        }
                    }
                    toast(`✓ Set #${setNumber} marked as skipped.`);
                }
            } else {
                toast('Error recording skip');
            }
        } catch (e) {
            toast('Error connecting to server');
        }
    }
</script>
@endpush
