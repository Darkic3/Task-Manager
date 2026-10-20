@extends('layouts.app')

@section('title', __('Live Workout Session') . ' | ' . ($workoutSession->day?->title ?? $workoutSession->day_title ?? ''))

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

    /* Live Session Timer */
    .ws-timer-pill {
        font-variant-numeric: tabular-nums; letter-spacing: 0.02em;
        min-width: 5.5rem; justify-content: center;
    }
    .ws-timer-clock { font-weight: 800; font-size: 0.9375rem; line-height: 1.1; }
    .ws-timer-pill.is-running {
        background: #eef2ff; color: #3730a3; border: 1px solid #c7d2fe;
    }
    .ws-timer-pill.is-running .ws-timer-clock { color: #4338ca; }
    .ws-timer-pill.is-running .bi-stopwatch-fill { animation: ws-timer-pulse 2s ease-in-out infinite; }
    @keyframes ws-timer-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.45; } }
    .ws-timer-pill.is-finished { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
    .ws-timer-pill.is-finished .ws-timer-clock { color: #166534; }

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
        position: relative;
        display: grid;
        grid-template-columns: 36px minmax(70px, 1.2fr) minmax(70px, 1fr) 40px 104px;
        gap: 8px; align-items: center;
        padding: 8px 12px; border-bottom: 1px solid #f8fafc;
        transition: background 0.15s ease;
    }
    .set-row-clean.time-mode {
        grid-template-columns: 36px minmax(120px, 2fr) minmax(70px, 1fr) 40px 104px;
    }
    @media (max-width: 480px) {
        .set-row-clean,
        .set-row-clean.time-mode { grid-template-columns: 30px 1fr 1fr 34px 94px; gap: 6px; padding: 8px; }
    }
    .set-row-clean:hover { background: #fafbfc; }
    .set-row-clean.is-saved { background: #f0fdf4; }
    .set-row-clean.is-skipped { background: #fef2f2; text-decoration: line-through; }

    /* PR record row: soft golden wash + a gold edge on the reading-start
       side (logical property, so it mirrors correctly in RTL). */
    .set-row-clean.is-pr {
        background: linear-gradient(90deg, rgba(251, 191, 36, 0.16), rgba(251, 191, 36, 0.05) 55%, rgba(240, 253, 244, 0.6));
        border-inline-start: 3px solid #f59e0b;
    }
    .set-row-clean.is-pr:hover {
        background: linear-gradient(90deg, rgba(251, 191, 36, 0.2), rgba(251, 191, 36, 0.08) 55%, rgba(240, 253, 244, 0.7));
    }
    .set-row-clean.is-pr .set-num-badge {
        background: linear-gradient(135deg, #fbbf24, #d97706);
        color: #fff;
        box-shadow: 0 2px 6px rgba(217, 119, 6, 0.4);
    }
    
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
        padding: 8px 6px; border-radius: 8px; font-size: 0.8125rem; font-weight: 700;
        display: flex; align-items: center; justify-content: center; gap: 4px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        border: none; background: #e0e7ff; color: #4338ca; cursor: pointer; transition: all 0.15s;
    }
    .btn-save-set i { flex-shrink: 0; font-size: 0.9em; }
    .btn-save-set:hover { background: #c7d2fe; }
    .btn-save-set.saved { background: #16a34a; color: white; }
    .btn-save-set.saved i { font-size: 1.05em; }
    .btn-save-set:disabled { opacity: 0.6; cursor: default; }
    .btn-save-set.saved:hover { background: #16a34a; }

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

    /* PR Golden Badge — floats over the row corner so it never joins the
       5-column grid (a 6th grid item would wrap into a full-width strip
       and push the save button out of the row). */
    .pr-badge {
        position: absolute;
        top: -10px;
        inset-inline-start: 10px;
        z-index: 3;
        display: inline-flex; align-items: center; gap: 3px;
        background: linear-gradient(135deg, #fbbf24, #d97706);
        color: white; font-size: 10px; font-weight: 800;
        white-space: nowrap;
        padding: 2px 7px; border-radius: 12px;
        border: 1px solid #fff;
        box-shadow: 0 2px 6px rgba(217, 119, 6, 0.3);
        animation: pop-in 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275),
                   pr-glow 2.6s ease-in-out 0.5s infinite;
    }
    @keyframes pr-glow {
        0%, 100% { box-shadow: 0 2px 6px rgba(217, 119, 6, 0.3); }
        50% { box-shadow: 0 2px 14px rgba(217, 119, 6, 0.6); }
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
                        <i class="bi bi-arrow-left"></i> {{ __('All Workout Plans') }}
                    </a>
                    <h1 class="ws-title mt-1">{{ $workoutSession->displayDayTitle() }}</h1>
                    <div class="ws-sub">
                        @php $wsWeekday = $workoutSession->weekday ?? $workoutSession->day?->weekday; @endphp
                        @if($wsWeekday) {{ __(ucfirst($wsWeekday)) }} • @endif{{ $workoutSession->workout_date->format('Y-m-d') }}@if($workoutSession->displayPlanTitle()) • {{ $workoutSession->displayPlanTitle() }}@endif
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="ws-summary-pill ws-timer-pill {{ $workoutSession->isRunning() ? 'is-running' : 'is-finished' }}"
                          id="sessionTimerPill"
                          data-started-at-ms="{{ $timerStartedAtMs }}"
                          data-server-now-ms="{{ $serverNowMs }}"
                          data-finished-seconds="{{ $finishedSeconds ?? '' }}"
                          role="timer"
                          aria-live="off">
                        <i class="bi {{ $workoutSession->isRunning() ? 'bi-stopwatch-fill' : 'bi-stopwatch' }} {{ $workoutSession->isRunning() ? 'text-primary' : 'text-muted' }}"></i>
                        <span class="ws-timer-clock font-monospace" id="sessionDuration">{{ \App\Models\WorkoutSession::formatDuration($finishedSeconds ?? $workoutSession->elapsedSeconds()) }}</span>
                    </span>
                    <span class="ws-summary-pill">
                        <i class="bi bi-check2-circle text-success"></i> <strong id="doneCount">{{ $completedCount }}</strong>/{{ $exerciseCount }} {{ __('Movements') }}
                    </span>
                </div>
            </div>

        <div class="progress" style="height: 6px;">
            <div class="progress-bar bg-primary" id="progressFill" style="width: {{ $exerciseCount ? round($completedCount / $exerciseCount * 100) : 0 }}%"></div>
        </div>

        <div class="mt-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="small text-muted fw-bold">{{ __('Bodyweight (kg)') }}</div>
                    <div class="small text-muted">{{ __('Saved for this session') }}</div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <input
                        type="number"
                        step="0.1"
                        min="0"
                        max="600"
                        id="bodyweightInput"
                        class="form-control form-control-sm"
                        style="width: 140px;"
                        value="{{ $workoutSession->bodyweight_kg }}"
                        placeholder="kg"
                    />
                    <button type="button" class="btn btn-sm btn-primary" id="bodyweightSaveBtn">
                        <i class="bi bi-save2 me-1"></i>{{ __('Save') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

        @if($workoutSession->day->notes)
            <div class="alert alert-light border p-3 small mb-3 text-secondary">
                <i class="bi bi-info-circle text-primary me-1"></i> <strong>{{ __('Day notes:') }}</strong> {{ $workoutSession->day->notes }}
            </div>
        @endif

        {{-- Exercise Cards --}}
        @forelse($workoutSession->day->exercises as $workoutExercise)
            @php
                $log = $exerciseLogs->get($workoutExercise->id);
                $previous = $previousByExercise->get($workoutExercise->exercise_id);
                $targetSets = $workoutExercise->requiredSetCount();
                $setRows = max($targetSets, (int) ($log?->setLogs?->max('set_number') ?? 0));

                $sideMode = $workoutExercise->side_mode ?: 'bilateral';
                
                // Check if this movement is time/duration based (e.g. mobility, stretching, planks)
                $isTimedMovement = !empty($workoutExercise->duration_seconds) 
                    || (empty($workoutExercise->rep_min) && empty($workoutExercise->rep_max) && empty($workoutExercise->target_weight));
                $isSkipped = !empty($log?->skip_reason);
            @endphp
            <article class="ws-card-ex {{ $log?->completed ? 'is-completed' : '' }} {{ $isSkipped ? 'is-skipped' : '' }}" 
                     data-exercise-card 
                     data-exercise-id="{{ $workoutExercise->id }}" 
                     data-rest="{{ $workoutExercise->rest_seconds ?: 90 }}"
                     data-is-timed="{{ $isTimedMovement ? '1' : '0' }}"
                     data-side-mode="{{ $sideMode }}">
                
                {{-- Exercise Head --}}
                <div class="ws-card-ex-head">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="ws-ex-name">{{ $workoutExercise->exercise->name }}</span>
                            @if($isSkipped)
                                <span class="badge bg-danger text-white" data-exercise-status style="font-size:10px;">
                                    {{ __('Skipped:') }} {{ $log->skip_reason }}
                                </span>
                            @else
                                <span class="badge {{ $log?->completed ? 'bg-success text-white' : 'bg-light text-muted border' }}" data-exercise-status style="font-size:10px;">
                                    {{ $log?->completed ? __('Completed') : __('Open') }}
                                </span>
                            @endif
                            @if($isTimedMovement)
                                <span class="badge bg-info-subtle text-info border" style="font-size:10px;"><i class="bi bi-clock"></i> {{ __('Timed / Mobility') }}</span>
                            @endif
                        </div>
                        <div class="small text-muted mt-1 d-flex flex-wrap gap-2">
                            <span><i class="bi bi-bullseye"></i> {{ $workoutExercise->targetLabel() }}</span>
                            @if($workoutExercise->target_rir !== null)
                                <span>• RIR {{ $workoutExercise->target_rir }}</span>
                            @endif
                            @if($workoutExercise->rest_seconds)
                                <span>• {{ __('Rest') }} {{ $workoutExercise->rest_seconds }}{{ __('s') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="startRestCountdown({{ $workoutExercise->rest_seconds ?: 90 }})">
                            <i class="bi bi-stopwatch"></i> {{ __('Rest') }}
                        </button>
                        @if($previous)
                            <button type="button" class="btn btn-sm btn-light border text-primary rounded-pill use-previous-btn" data-previous="{{ json_encode($previous['sets']) }}" title="{{ __('Copy last session values') }}">
                                <i class="bi bi-copy"></i> {{ __('Autofill') }}
                            </button>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="openSkipModal({{ $workoutExercise->id }}, '{{ addslashes($workoutExercise->exercise->name) }}')" title="{{ __('Skip this entire exercise') }}">
                            <i class="bi bi-skip-forward"></i> {{ __('Skip') }}
                        </button>
                    </div>
                </div>

                {{-- Previous Session Mini Info --}}
                @if($previous)
                <div class="bg-light px-3 py-2 border-bottom d-flex align-items-center justify-content-between text-muted small" style="font-size:11px;">
                    <div>
                        <i class="bi bi-clock-history me-1"></i> {{ __('Last session') }} ({{ $previous['date'] }}): 
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
                        <span>{{ __('Set') }}</span>
                        @if($isTimedMovement)
                            <span>{{ __('Duration (Seconds / Mins)') }}</span>
                            <span>{{ __('RIR / Effort') }}</span>
                        @else
                            <span>{{ __('Weight (kg)') }}</span>
                            <span>{{ __('Reps') }}</span>
                        @endif
                        <span></span>
                        <span class="text-center">{{ __('Action') }}</span>
                    </div>

                    @for($setNumber = 1; $setNumber <= $setRows; $setNumber++)
                        @php
                            $set = $log?->setLogs?->firstWhere('set_number', $setNumber);

                            $setLeft = $sideMode === 'per_side'
                                ? $log?->setLogs?->firstWhere(fn($r) => (int) $r->set_number === $setNumber && ($r->side ?? 'both') === 'left')
                                : null;
                            $setRight = $sideMode === 'per_side'
                                ? $log?->setLogs?->firstWhere(fn($r) => (int) $r->set_number === $setNumber && ($r->side ?? 'both') === 'right')
                                : null;

                            $prevLeft = $previous ? collect($previous['sets'])->firstWhere(fn($r) => (int) ($r['set_number'] ?? 0) === $setNumber && ($r['side'] ?? 'both') === 'left') : null;
                            $prevRight = $previous ? collect($previous['sets'])->firstWhere(fn($r) => (int) ($r['set_number'] ?? 0) === $setNumber && ($r['side'] ?? 'both') === 'right') : null;

                            $prevAny = $previous ? collect($previous['sets'])->firstWhere('set_number', $setNumber) : null;
                            $ghostWeightAny = $prevAny['weight'] ?? '';
                            $ghostRepsAny = $prevAny['reps'] ?? $workoutExercise->rep_min ?? '';
                            $ghostDurationAny = $prevAny['duration_seconds'] ?? $workoutExercise->duration_seconds ?? '45';

                            $ghostWeightLeft = $prevLeft['weight'] ?? '';
                            $ghostRepsLeft = $prevLeft['reps'] ?? $workoutExercise->rep_min ?? '';
                            $ghostWeightRight = $prevRight['weight'] ?? '';
                            $ghostRepsRight = $prevRight['reps'] ?? $workoutExercise->rep_min ?? '';
                        @endphp
                        <div class="set-row-clean {{ $isTimedMovement ? 'time-mode' : '' }} {{ $sideMode === 'per_side' ? (($setLeft?->completed && $setRight?->completed) ? 'is-saved' : '') : (($set?->completed) ? 'is-saved' : '') }} {{ $sideMode === 'per_side' ? (($setLeft?->is_pr || $setRight?->is_pr) ? 'is-pr' : '') : (($set?->is_pr) ? 'is-pr' : '') }}"
                              data-set-row
                              data-set-number="{{ $setNumber }}">

                            <div class="set-num-badge">{{ $setNumber }}</div>
                            @if($sideMode === 'per_side' ? ($setLeft?->is_pr || $setRight?->is_pr) : ($set?->is_pr))
                                <span class="pr-badge">{{ __('🏆 NEW PR!') }}</span>
                            @endif

                            @if($isTimedMovement)
                                {{-- Timed / Mobility Mode Input --}}
                                <div>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="1" min="0" class="ws-input-big form-control" data-field="duration_seconds" 
                                               value="{{ $set?->duration_seconds }}" 
                                         placeholder="{{ $ghostDurationAny ? $ghostDurationAny . 's' : __('seconds') }}">
                                        <span class="input-group-text bg-light text-muted small">{{ __('sec') }}</span>
                                    </div>
                                </div>
                                <div>
                                    <input type="number" step="0.5" min="0" max="10" class="ws-input-big" data-field="rir" 
                                           value="{{ $set?->rir }}" 
                                           placeholder="{{ __('RIR (0-10)') }}">
                                </div>
                            @else
                                {{-- Weight & Reps Lifting Mode Input --}}
                                @if($sideMode === 'per_side')
                                    <div>
                                        <div class="small text-muted fw-bold">L</div>
                                        <input type="number" step="0.5" min="0" class="ws-input-big" data-field="weight" data-side="left"
                                               value="{{ $setLeft?->weight }}"
                                               placeholder="{{ $ghostWeightLeft ? $ghostWeightLeft . ' kg' : __('kg') }}">
                                        <div class="small text-muted fw-bold mt-1">R</div>
                                        <input type="number" step="0.5" min="0" class="ws-input-big" data-field="weight" data-side="right"
                                               value="{{ $setRight?->weight }}"
                                               placeholder="{{ $ghostWeightRight ? $ghostWeightRight . ' kg' : __('kg') }}">
                                    </div>

                                    <div>
                                        <div class="small text-muted fw-bold">L</div>
                                        <input type="number" step="1" min="0" class="ws-input-big" data-field="reps" data-side="left"
                                               value="{{ $setLeft?->reps }}"
                                               placeholder="{{ $ghostRepsLeft ? $ghostRepsLeft . ' reps' : __('reps') }}">
                                        <div class="small text-muted fw-bold mt-1">R</div>
                                        <input type="number" step="1" min="0" class="ws-input-big" data-field="reps" data-side="right"
                                               value="{{ $setRight?->reps }}"
                                               placeholder="{{ $ghostRepsRight ? $ghostRepsRight . ' reps' : __('reps') }}">
                                    </div>
                                @else
                                    @if($sideMode === 'alternating')
                                        @php
                                            $altSide = $set?->side ?? 'left';
                                        @endphp
                                    @endif

                                    <div>
                                        <input type="number" step="0.5" min="0" class="ws-input-big" data-field="weight" 
                                               value="{{ $set?->weight }}" 
                                               placeholder="{{ $ghostWeightAny ? $ghostWeightAny . ' kg' : __('kg') }}">
                                    </div>

                                    <div>
                                        @if($sideMode === 'alternating')
                                            <select class="form-select form-select-sm" data-field="side">
                                                <option value="left" @selected(($altSide ?? 'left') === 'left')>Left</option>
                                                <option value="right" @selected(($altSide ?? 'left') === 'right')>Right</option>
                                            </select>
                                        @endif
                                        <input type="number" step="1" min="0" class="ws-input-big" data-field="reps" 
                                               value="{{ $set?->reps }}" 
                                               placeholder="{{ $ghostRepsAny ? $ghostRepsAny . ' reps' : __('reps') }}">
                                    </div>
                                @endif
                            @endif

                            <button type="button" class="btn-more-toggle" onclick="toggleSetMore(this)" title="{{ __('Advanced set options') }}">
                                <i class="bi bi-sliders"></i>
                            </button>

                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn-save-set w-100 {{ $set?->completed ? 'saved' : '' }}" onclick="saveSetRow(this)">
                                    <i class="bi {{ $set?->completed ? 'bi-check-lg' : 'bi-check2' }}"></i>
                                    <span>{{ $set?->completed ? __('Done') : __('Save') }}</span>
                                </button>
                            </div>

                            {{-- Collapsible Advanced More Options --}}
                            <div class="set-more-fields col-12" style="grid-column: 1 / -1;">
                                @if(!$isTimedMovement)
                                    @if($sideMode === 'per_side')
                                        <div>
                                            <label class="small text-muted">{{ __('RIR (in reserve)') }} (L)</label>
                                            <input type="number" min="0" max="10" step="0.5" class="form-control form-control-sm" data-field="rir" data-side="left" value="{{ $setLeft?->rir }}" placeholder="{{ __('Target:') }} {{ $workoutExercise->target_rir ?? '—' }}">
                                        </div>
                                        <div>
                                            <label class="small text-muted">{{ __('RIR (in reserve)') }} (R)</label>
                                            <input type="number" min="0" max="10" step="0.5" class="form-control form-control-sm" data-field="rir" data-side="right" value="{{ $setRight?->rir }}" placeholder="{{ __('Target:') }} {{ $workoutExercise->target_rir ?? '—' }}">
                                        </div>
                                    @else
                                        <div>
                                            <label class="small text-muted">{{ __('RIR (in reserve)') }}</label>
                                            <input type="number" min="0" max="10" step="0.5" class="form-control form-control-sm" data-field="rir" value="{{ $set?->rir }}" placeholder="{{ __('Target:') }} {{ $workoutExercise->target_rir ?? '—' }}">
                                        </div>
                                    @endif
                                @endif

                                @if($sideMode === 'per_side')
                                    <div>
                                        <label class="small text-muted">{{ __('Form Rating') }} (L)</label>
                                        <select class="form-select form-select-sm" data-field="form_rating" data-side="left">
                                            <option value="">—</option>
                                            @for($r=1; $r<=5; $r++)
                                                <option value="{{ $r }}" @selected((int) ($setLeft?->form_rating ?? 0) === $r)>{{ $r }}/5 ⭐</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div>
                                        <label class="small text-muted">{{ __('Form Rating') }} (R)</label>
                                        <select class="form-select form-select-sm" data-field="form_rating" data-side="right">
                                            <option value="">—</option>
                                            @for($r=1; $r<=5; $r++)
                                                <option value="{{ $r }}" @selected((int) ($setRight?->form_rating ?? 0) === $r)>{{ $r }}/5 ⭐</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div>
                                        <label class="small text-muted">{{ __('Pain Level (0-10)') }} (L)</label>
                                        <input type="number" min="0" max="10" class="form-control form-control-sm" data-field="pain_level" data-side="left" value="{{ $setLeft?->pain_level }}" placeholder="0">
                                    </div>
                                    <div>
                                        <label class="small text-muted">{{ __('Pain Level (0-10)') }} (R)</label>
                                        <input type="number" min="0" max="10" class="form-control form-control-sm" data-field="pain_level" data-side="right" value="{{ $setRight?->pain_level }}" placeholder="0">
                                    </div>
                                    <div>
                                        <label class="small text-muted">{{ __('Set Note / Reason') }} (L)</label>
                                        <input type="text" class="form-control form-control-sm" data-field="note" data-side="left" value="{{ $setLeft?->note }}" placeholder="{{ __('Optional notes') }}">
                                    </div>
                                    <div>
                                        <label class="small text-muted">{{ __('Set Note / Reason') }} (R)</label>
                                        <input type="text" class="form-control form-control-sm" data-field="note" data-side="right" value="{{ $setRight?->note }}" placeholder="{{ __('Optional notes') }}">
                                    </div>
                                @else
                                    <div>
                                        <label class="small text-muted">{{ __('Form Rating') }}</label>
                                        <select class="form-select form-select-sm" data-field="form_rating">
                                            <option value="">—</option>
                                            @for($r=1; $r<=5; $r++)
                                                <option value="{{ $r }}" @selected((int) $set?->form_rating === $r)>{{ $r }}/5 ⭐</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div>
                                        <label class="small text-muted">{{ __('Pain Level (0-10)') }}</label>
                                        <input type="number" min="0" max="10" class="form-control form-control-sm" data-field="pain_level" value="{{ $set?->pain_level }}" placeholder="0">
                                    </div>
                                    <div>
                                        <label class="small text-muted">{{ __('Set Note / Reason') }}</label>
                                        <input type="text" class="form-control form-control-sm" data-field="note" value="{{ $set?->note }}" placeholder="{{ __('Optional notes') }}">
                                    </div>
                                @endif
                                <div class="d-flex align-items-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="openSkipSetModal({{ $workoutExercise->id }}, {{ $setNumber }}, '{{ addslashes($workoutExercise->exercise->name) }}')">
                                        <i class="bi bi-slash-circle me-1"></i> {{ __('Skip Set :num', ['num' => $setNumber]) }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>

                {{-- Add Set & Movement Notes --}}
                <div class="px-3 pb-3 pt-1 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 rounded-pill" onclick="addNewSetRow(this)">
                            <i class="bi bi-plus-lg"></i> {{ __('Add Set') }}
                        </button>
                        <button type="button" class="btn btn-sm {{ $log?->note ? 'btn-primary' : 'btn-outline-secondary' }} d-inline-flex align-items-center gap-1 rounded-pill" onclick="toggleExNote(this)" title="{{ __('Movement note (during training)') }}" data-ex-note-toggle>
                            <i class="bi bi-journal-text"></i> {{ __('Movement Note') }}
                            @if($log?->note)<i class="bi bi-check-circle-fill" data-ex-note-flag></i>@endif
                        </button>
                    </div>
                    <span class="small text-muted exercise-1rm-display" id="ex-1rm-{{ $workoutExercise->id }}"></span>
                </div>

                {{-- Movement (Exercise) Note --}}
                <div class="ex-note-wrap px-3 pb-3" data-ex-note-wrap @if(! $log?->note) hidden @endif>
                    <label class="small text-muted mb-1 d-flex align-items-center gap-1">
                        <i class="bi bi-journal-text"></i> {{ __('Movement note (during training)') }}
                    </label>
                    <textarea class="form-control form-control-sm" rows="2" maxlength="2000" data-ex-note
                              placeholder="{{ __('How did this movement feel? Technique, tempo, pain, cues…') }}">{{ $log?->note }}</textarea>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="small text-muted" data-ex-note-status></span>
                        <button type="button" class="btn btn-sm btn-primary" onclick="saveExNote(this)">
                            <i class="bi bi-check-lg"></i> {{ __('Save Note') }}
                        </button>
                    </div>
                </div>
            </article>
        @empty
            <div class="text-center py-5 bg-white rounded-3 border">
                <i class="bi bi-cup-hot text-warning fs-1 mb-2 d-block"></i>
                <h5>{{ __('No movements planned for today') }}</h5>
                <p class="text-muted small">{{ __('Enjoy your recovery day!') }}</p>
            </div>
        @endforelse

        {{-- Finish Session Card --}}
        <form class="ws-header-card mt-4" method="POST" action="{{ route('workouts.sessions.finish', $workoutSession) }}">
            @csrf
            @method('PATCH')
            <h5 class="fw-bold mb-3">
                <i class="bi bi-flag-fill text-primary me-1"></i> {{ __('Finish Workout Session') }}
                @if($finishedSeconds !== null)
                    <span class="badge bg-success-subtle text-success border ms-2" style="font-size:11px;">
                        {{ __('Final duration') }}: <span class="font-monospace">{{ \App\Models\WorkoutSession::formatDuration($finishedSeconds) }}</span>
                    </span>
                @else
                    <span class="badge bg-light text-muted border ms-2" style="font-size:11px;">
                        <i class="bi bi-record-circle text-danger me-1"></i>{{ __('Recording') }}
                        <span class="font-monospace">{{ \App\Models\WorkoutSession::formatDuration($workoutSession->elapsedSeconds()) }}</span>
                    </span>
                @endif
            </h5>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted">{{ __('Session Summary / How did it feel?') }}</label>
                    <textarea class="form-control" name="session_note" rows="2" placeholder="{{ __('Great energy, increased bench press weight...') }}">{{ $workoutSession->session_note }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted">{{ __('Pain / Limitations (if any)') }}</label>
                    <textarea class="form-control" name="pain_note" rows="2" placeholder="{{ __('Slight shoulder tightness on second set...') }}">{{ $workoutSession->pain_note }}</textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button type="submit" name="status" value="skipped" class="btn btn-light border px-3">{{ __('Mark Skipped') }}</button>
                <button type="submit" name="status" value="completed" class="btn btn-success fw-bold px-4 shadow-sm">
                    <i class="bi bi-check-circle-fill me-1"></i> {{ __('Finish Workout') }}
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
                <h6 class="modal-title fw-bold" id="skipMovementTitle">{{ __('Skip Movement') }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-3" id="skipMovementPrompt">{{ __('Why are you skipping this?') }}</p>
                <input type="hidden" id="skipExerciseId">
                <input type="hidden" id="skipSetNumber">
                <input type="hidden" id="skipMode" value="exercise">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">{{ __('Reason (Optional)') }}</label>
                    <input type="text" class="form-control" id="skipReasonInput" placeholder="{{ __('e.g. Equipment busy, muscle fatigue, joint discomfort') }}" onkeydown="if(event.key==='Enter'){event.preventDefault();submitSkipAction();}">
                </div>
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <button type="button" class="btn btn-sm btn-light border" onclick="setQuickSkipReason('{{ __('Equipment Busy') }}')">{{ __('Equipment Busy') }}</button>
                    <button type="button" class="btn btn-sm btn-light border" onclick="setQuickSkipReason('{{ __('Joint Discomfort / Pain') }}')">{{ __('Joint Discomfort') }}</button>
                    <button type="button" class="btn btn-sm btn-light border" onclick="setQuickSkipReason('{{ __('Fatigue / Ran out of time') }}')">{{ __('Out of Time') }}</button>
                    <button type="button" class="btn btn-sm btn-light border" onclick="setQuickSkipReason('{{ __('Form breakdown / Overload') }}')">{{ __('Form Breakdown') }}</button>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-danger px-4" id="skipSubmitBtn" onclick="submitSkipAction()">
                    <i class="bi bi-skip-forward me-1"></i> {{ __('Confirm Skip') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Floating Auto-Rest Timer Bar --}}
<div class="floating-rest-bar" id="floatingRestBar">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-stopwatch-fill text-warning"></i>
        <span class="small text-white-50">{{ __('Rest:') }}</span>
        <span class="rest-clock" id="floatingRestClock">01:30</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-dark border-secondary text-white rounded-pill px-2 py-0" onclick="adjustRestTimer(30)" style="font-size:11px;">+30s</button>
        <button type="button" class="btn btn-sm btn-dark border-secondary text-white rounded-pill px-2 py-0" onclick="adjustRestTimer(-30)" style="font-size:11px;">-30s</button>
        <button type="button" class="btn btn-sm btn-danger rounded-pill px-3 py-1 ms-1" onclick="stopRestTimer()" style="font-size:11px;">{{ __('Skip') }}</button>
    </div>
</div>

<div class="ws-toast" id="toast"></div>
@endsection

@push('scripts')
<script>
    const WS_TOKEN = '{{ csrf_token() }}';
    const SET_URL = '{{ route("workouts.sessions.sets.store", $workoutSession) }}';
    const EX_NOTE_URL = '{{ route("workouts.sessions.exercise-note.store", $workoutSession) }}';
    const BODYWEIGHT_URL = '{{ route("workouts.sessions.bodyweight.store", $workoutSession) }}';
    const I18N_WS = {
        done: @json(__('Done')),
        save: @json(__('Save')),
        completed: @json(__('Completed')),
        inProgress: @json(__('In progress')),
        recording: @json(__('Recording')),
        finalDuration: @json(__('Final duration')),
        sessionFinished: @json(__('Session finished in :time')),
        recordingStopped: @json(__('⏹ Timer stopped — session saved.')),
        restComplete: @json(__('🔔 Rest Time Complete! Next Set Ready.')),
        prCelebration: @json(__('🏆 INCREDIBLE! New Personal Record (PR) achieved!')),
        newPr: @json(__('🏆 NEW PR!')),
        est1rm: @json(__('Est. 1RM:')),
        lastSessionFilled: @json(__('✓ Last session values filled!')),
        skipEntireExercise: @json(__('Skip Entire Exercise')),
        skipSetTitle: @json(__('Skip Set #:num')),
        skipPromptExercise: @json(__('Why are you skipping all sets for ":name"?')),
        skipPromptSet: @json(__('Reason for skipping Set #:num of ":name"?')),
        exerciseSkippedToast: @json(__('✓ Exercise marked as skipped.')),
        setSkippedToast: @json(__('✓ Set #:num marked as skipped.')),
        skippedLabel: @json(__('Skipped:')),
        errorSavingSet: @json(__('Error saving set')),
        noteSaved: @json(__('Movement note saved.')),
        errorRecordingSkip: @json(__('Error recording skip')),
        errorConnectingServer: @json(__('Error connecting to server')),
        seconds: @json(__('seconds')),
        sec: @json(__('sec')),
        rir: @json(__('RIR')),
        form: @json(__('Form Rating')),
        pain: @json(__('Pain Level (0-10)')),
        note: @json(__('Set Note / Reason')),
        optional: @json(__('Optional notes')),
        skipSetBtn: @json(__('Skip Set :num')),
        kg: @json(__('kg')),
        reps: @json(__('reps'))
    };

    // Save bodyweight for this session (used for later weekly analytics)
    async function saveBodyweight() {
        const input = document.getElementById('bodyweightInput');
        if (!input) return;
        const btn = document.getElementById('bodyweightSaveBtn');
        const value = input.value;

        const payload = { bodyweight_kg: value === '' ? null : Number(value) };

        btn && (btn.disabled = true);
        try {
            const res = await fetch(BODYWEIGHT_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': WS_TOKEN
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Error');
            toast('✓ Saved');
        } catch (e) {
            toast(e.message);
        } finally {
            btn && (btn.disabled = false);
        }
    }

    document.getElementById('bodyweightSaveBtn')?.addEventListener('click', saveBodyweight);

    // ── Live Session Timer ───────────────────────────────────────────────
    // Elapsed time is always derived from the server-anchored start instant,
    // never from a client-side counter, so refreshes and a wrong local clock
    // cannot corrupt the value. The server stamps the final duration on finish.
    const sessionTimer = (() => {
        const pill = document.getElementById('sessionTimerPill');
        const clock = document.getElementById('sessionDuration');
        if (!pill || !clock) return null;

        const startedAtMs = parseInt(pill.dataset.startedAtMs, 10) || 0;
        const serverNowMs = parseInt(pill.dataset.serverNowMs, 10) || 0;
        const finishedRaw = pill.dataset.finishedSeconds;
        const finishedSeconds = finishedRaw === '' ? null : parseInt(finishedRaw, 10);
        // Client clock is only used to measure the passage of time between
        // ticks; the absolute anchor stays on the server.
        const offsetMs = Date.now() - serverNowMs;

        const pad = (n) => String(n).padStart(2, '0');

        const format = (total) => {
            const secs = Math.max(0, total);
            const h = Math.floor(secs / 3600);
            const m = Math.floor((secs % 3600) / 60);
            const s = secs % 60;
            return h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
        };

        const render = (total) => { clock.textContent = format(total); };

        if (finishedSeconds !== null) {
            render(finishedSeconds);
            return { running: false, seconds: finishedSeconds };
        }

        if (!startedAtMs) {
            render(0);
            return { running: false, seconds: 0 };
        }

        const current = () => Math.floor((Date.now() - offsetMs - startedAtMs) / 1000);
        render(current());

        const tick = setInterval(() => render(current()), 1000);

        return {
            running: true,
            seconds: current(),
            stop() { clearInterval(tick); render(finishedSeconds ?? current()); }
        };
    })();

    // Freeze the clock on the exact second the finish form is submitted.
    document.querySelectorAll('form[action$="/finish"]').forEach(form => {
        form.addEventListener('submit', () => {
            if (sessionTimer && sessionTimer.running) sessionTimer.stop();
        });
    });

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

    // Toggle the whole-movement (exercise) note editor
    function toggleExNote(btn) {
        const card = btn.closest('[data-exercise-card]');
        const wrap = card?.querySelector('[data-ex-note-wrap]');
        if (!wrap) return;
        wrap.hidden = !wrap.hidden;
        if (!wrap.hidden) wrap.querySelector('[data-ex-note]')?.focus();
    }

    // Save the whole-movement (exercise) note for this session
    async function saveExNote(btn) {
        const card = btn.closest('[data-exercise-card]');
        const textarea = card?.querySelector('[data-ex-note]');
        const status = card?.querySelector('[data-ex-note-status]');
        const toggle = card?.querySelector('[data-ex-note-toggle]');
        if (!textarea) return;

        btn.disabled = true;
        if (status) status.textContent = '';

        try {
            const res = await fetch(EX_NOTE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': WS_TOKEN
                },
                body: JSON.stringify({
                    workout_exercise_id: card.dataset.exerciseId,
                    note: textarea.value
                })
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || I18N_WS.errorSavingSet);

            const hasNote = !!(data.note && data.note.length);
            let flag = toggle?.querySelector('[data-ex-note-flag]');
            if (hasNote && toggle && !flag) {
                flag = document.createElement('i');
                flag.className = 'bi bi-check-circle-fill';
                flag.setAttribute('data-ex-note-flag', '');
                toggle.appendChild(flag);
            } else if (!hasNote && flag) {
                flag.remove();
            }
            if (toggle) {
                toggle.classList.toggle('btn-primary', hasNote);
                toggle.classList.toggle('btn-outline-secondary', !hasNote);
            }

            if (status) status.textContent = I18N_WS.noteSaved;
            toast(I18N_WS.noteSaved);
        } catch (e) {
            if (status) status.textContent = I18N_WS.errorSavingSet;
        } finally {
            btn.disabled = false;
        }
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
                toast(I18N_WS.restComplete);
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

        const sideMode = card.dataset.sideMode || 'bilateral';
        const setNumber = row.dataset.setNumber;

        const base = {
            workout_exercise_id: card.dataset.exerciseId,
            set_number: setNumber,
            completed: true,
        };

        const post = async (payload) => {
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
            if (!res.ok) throw new Error(data.message || I18N_WS.errorSavingSet);
            return data;
        };

        try {
            let results = [];

            if (sideMode === 'per_side') {
                const sides = ['left', 'right'];
                results = await Promise.all(
                    sides.map(side => {
                        const payload = { ...base, side };
                        row.querySelectorAll('[data-field][data-side]').forEach(input => {
                            if ((input.dataset.side || '') !== side) return;
                            if (input.value !== '') payload[input.dataset.field] = input.value;
                        });
                        // note/reps/weight fields are side-scoped in per_side mode.
                        // Timed + side inputs (if any) still work via same rule.
                        return post(payload);
                    })
                );
            } else {
                // bilateral or alternating: collect any data-field inputs in the row.
                const payload = { ...base };
                row.querySelectorAll('[data-field]').forEach(input => {
                    if (input.value !== '') payload[input.dataset.field] = input.value;
                });
                results = [await post(payload)];
            }

            const exerciseCompleted = results.some(r => !!r.exercise_completed);
            const anyPr = results.some(r => !!r.is_pr);
            const bestEstimated1rm = Math.max(0, ...results.map(r => Number(r.estimated_1rm || 0)));

            row.classList.add('is-saved');
            btn.classList.add('saved');
            btn.innerHTML = `<i class="bi bi-check-lg"></i><span>${I18N_WS.done}</span>`;

            // PR Hunter Celebration!
            if (anyPr) {
                playChime();
                row.classList.add('is-pr');
                let prEl = row.querySelector('.pr-badge');
                if (!prEl) {
                    prEl = document.createElement('span');
                    prEl.className = 'pr-badge';
                    prEl.innerHTML = I18N_WS.newPr;
                    row.querySelector('.set-num-badge').after(prEl);
                }
                toast(I18N_WS.prCelebration);
            } else {
                row.classList.remove('is-pr');
                row.querySelector('.pr-badge')?.remove();
            }

            // Update 1RM display
            if (bestEstimated1rm > 0) {
                const ex1rmEl = document.getElementById('ex-1rm-' + card.dataset.exerciseId);
                if (ex1rmEl) ex1rmEl.innerHTML = `${I18N_WS.est1rm} <strong>${bestEstimated1rm} kg</strong>`;
            }

            // Update completed badge
            const status = card.querySelector('[data-exercise-status]');
            if (status) {
                status.textContent = exerciseCompleted ? I18N_WS.completed : I18N_WS.inProgress;
                status.className = `badge ${exerciseCompleted ? 'bg-success text-white' : 'bg-light text-muted border'}`;
                card.classList.toggle('is-completed', exerciseCompleted);
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
        const sideMode = card.dataset.sideMode || 'bilateral';

        const row = document.createElement('div');
        row.className = `set-row-clean ${isTimed ? 'time-mode' : ''}`;
        row.dataset.setRow = '1';
        row.dataset.setNumber = count;

        if (isTimed) {
            row.innerHTML = `
                <div class="set-num-badge">${count}</div>
                <div>
                    <div class="input-group input-group-sm">
                        <input type="number" step="1" min="0" class="ws-input-big form-control" data-field="duration_seconds" placeholder="${I18N_WS.seconds}">
                        <span class="input-group-text bg-light text-muted small">${I18N_WS.sec}</span>
                    </div>
                </div>
                <div><input type="number" step="0.5" min="0" max="10" class="ws-input-big" data-field="rir" placeholder="${I18N_WS.rir}"></div>
                <button type="button" class="btn-more-toggle" onclick="toggleSetMore(this)"><i class="bi bi-sliders"></i></button>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn-save-set w-100" onclick="saveSetRow(this)">
                        <i class="bi bi-check2"></i> ${I18N_WS.save}
                    </button>
                </div>
                <div class="set-more-fields col-12" style="grid-column: 1 / -1;">
                    <div><label class="small text-muted">${I18N_WS.form}</label><select class="form-select form-select-sm" data-field="form_rating"><option value="">—</option><option value="5">5/5 ⭐</option><option value="4">4/5</option><option value="3">3/5</option></select></div>
                    <div><label class="small text-muted">${I18N_WS.pain}</label><input type="number" min="0" max="10" class="form-control form-control-sm" data-field="pain_level" placeholder="0"></div>
                    <div><label class="small text-muted">${I18N_WS.note}</label><input type="text" class="form-control form-control-sm" data-field="note" placeholder="${I18N_WS.optional}"></div>
                    <div class="d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="openSkipSetModal(${card.dataset.exerciseId}, ${count}, '${card.querySelector('.ws-ex-name')?.textContent || 'Exercise'}')">
                            <i class="bi bi-slash-circle me-1"></i> ${I18N_WS.skipSetBtn.replace(':num', count)}
                        </button>
                    </div>
                </div>
            `;
        } else if (sideMode === 'per_side') {
            row.innerHTML = `
                <div class="set-num-badge">${count}</div>
                <div>
                    <div class="small text-muted fw-bold">L</div>
                    <input type="number" step="0.5" min="0" class="ws-input-big" data-field="weight" data-side="left" placeholder="${I18N_WS.kg}">
                    <div class="small text-muted fw-bold mt-1">R</div>
                    <input type="number" step="0.5" min="0" class="ws-input-big" data-field="weight" data-side="right" placeholder="${I18N_WS.kg}">
                </div>
                <div>
                    <div class="small text-muted fw-bold">L</div>
                    <input type="number" step="1" min="0" class="ws-input-big" data-field="reps" data-side="left" placeholder="${I18N_WS.reps}">
                    <div class="small text-muted fw-bold mt-1">R</div>
                    <input type="number" step="1" min="0" class="ws-input-big" data-field="reps" data-side="right" placeholder="${I18N_WS.reps}">
                </div>
                <button type="button" class="btn-more-toggle" onclick="toggleSetMore(this)"><i class="bi bi-sliders"></i></button>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn-save-set w-100" onclick="saveSetRow(this)">
                        <i class="bi bi-check2"></i> ${I18N_WS.save}
                    </button>
                </div>
                <div class="set-more-fields col-12" style="grid-column: 1 / -1;">
                    <div><label class="small text-muted">${I18N_WS.rir} (L)</label><input type="number" min="0" max="10" step="0.5" class="form-control form-control-sm" data-field="rir" data-side="left" placeholder="${I18N_WS.rir}"></div>
                    <div><label class="small text-muted">${I18N_WS.rir} (R)</label><input type="number" min="0" max="10" step="0.5" class="form-control form-control-sm" data-field="rir" data-side="right" placeholder="${I18N_WS.rir}"></div>
                    <div><label class="small text-muted">${I18N_WS.form} (L)</label><select class="form-select form-select-sm" data-field="form_rating" data-side="left"><option value="">—</option><option value="5">5/5 ⭐</option><option value="4">4/5</option><option value="3">3/5</option></select></div>
                    <div><label class="small text-muted">${I18N_WS.form} (R)</label><select class="form-select form-select-sm" data-field="form_rating" data-side="right"><option value="">—</option><option value="5">5/5 ⭐</option><option value="4">4/5</option><option value="3">3/5</option></select></div>
                    <div><label class="small text-muted">${I18N_WS.pain} (L)</label><input type="number" min="0" max="10" class="form-control form-control-sm" data-field="pain_level" data-side="left" placeholder="0"></div>
                    <div><label class="small text-muted">${I18N_WS.pain} (R)</label><input type="number" min="0" max="10" class="form-control form-control-sm" data-field="pain_level" data-side="right" placeholder="0"></div>
                    <div><label class="small text-muted">${I18N_WS.note} (L)</label><input type="text" class="form-control form-control-sm" data-field="note" data-side="left" placeholder="${I18N_WS.optional}"></div>
                    <div><label class="small text-muted">${I18N_WS.note} (R)</label><input type="text" class="form-control form-control-sm" data-field="note" data-side="right" placeholder="${I18N_WS.optional}"></div>
                    <div class="d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="openSkipSetModal(${card.dataset.exerciseId}, ${count}, '${card.querySelector('.ws-ex-name')?.textContent || 'Exercise'}')">
                            <i class="bi bi-slash-circle me-1"></i> ${I18N_WS.skipSetBtn.replace(':num', count)}
                        </button>
                    </div>
                </div>
            `;
        } else {
            // bilateral or alternating
            const altSelect = sideMode === 'alternating'
                ? `<select class="form-select form-select-sm" data-field="side"><option value="left">Left</option><option value="right">Right</option></select>`
                : '';

            row.innerHTML = `
                <div class="set-num-badge">${count}</div>
                <div><input type="number" step="0.5" min="0" class="ws-input-big" data-field="weight" placeholder="${I18N_WS.kg}"></div>
                <div>${altSelect}<input type="number" step="1" min="0" class="ws-input-big" data-field="reps" placeholder="${I18N_WS.reps}"></div>
                <button type="button" class="btn-more-toggle" onclick="toggleSetMore(this)"><i class="bi bi-sliders"></i></button>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn-save-set w-100" onclick="saveSetRow(this)">
                        <i class="bi bi-check2"></i> ${I18N_WS.save}
                    </button>
                </div>
                <div class="set-more-fields col-12" style="grid-column: 1 / -1;">
                    <div><label class="small text-muted">${I18N_WS.rir}</label><input type="number" min="0" max="10" step="0.5" class="form-control form-control-sm" data-field="rir" placeholder="${I18N_WS.rir}"></div>
                    <div><label class="small text-muted">${I18N_WS.form}</label><select class="form-select form-select-sm" data-field="form_rating"><option value="">—</option><option value="5">5/5 ⭐</option><option value="4">4/5</option><option value="3">3/5</option></select></div>
                    <div><label class="small text-muted">${I18N_WS.pain}</label><input type="number" min="0" max="10" class="form-control form-control-sm" data-field="pain_level" placeholder="0"></div>
                    <div><label class="small text-muted">${I18N_WS.note}</label><input type="text" class="form-control form-control-sm" data-field="note" placeholder="${I18N_WS.optional}"></div>
                    <div class="d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="openSkipSetModal(${card.dataset.exerciseId}, ${count}, '${card.querySelector('.ws-ex-name')?.textContent || 'Exercise'}')">
                            <i class="bi bi-slash-circle me-1"></i> ${I18N_WS.skipSetBtn.replace(':num', count)}
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
                    const psSide = ps.side || 'both';

                    const setInput = (field, side) => {
                        return row.querySelector(`[data-field="${field}"][data-side="${side}"]`) || row.querySelector(`[data-field="${field}"]`);
                    };

                    if (ps.weight !== null) {
                        const el = setInput('weight', psSide);
                        if (el) el.value = ps.weight;
                    }
                    if (ps.reps !== null) {
                        const el = setInput('reps', psSide);
                        if (el) el.value = ps.reps;
                    }
                    if (ps.duration_seconds !== null) {
                        const el = setInput('duration_seconds', psSide);
                        if (el) el.value = ps.duration_seconds;
                    }
                }
            });
            toast(I18N_WS.lastSessionFilled);
        });
    });

    // Skip Modal Logic (Supports both Movement and Individual Set)
    function openSkipModal(exerciseId, exerciseName) {
        document.getElementById('skipMode').value = 'exercise';
        document.getElementById('skipExerciseId').value = exerciseId;
        document.getElementById('skipSetNumber').value = '';
        document.getElementById('skipMovementTitle').textContent = I18N_WS.skipEntireExercise;
        document.getElementById('skipMovementPrompt').textContent = I18N_WS.skipPromptExercise.replace(':name', exerciseName);
        document.getElementById('skipReasonInput').value = '';
        const modal = new bootstrap.Modal(document.getElementById('skipMovementModal'));
        modal.show();
    }

    function openSkipSetModal(exerciseId, setNumber, exerciseName) {
        document.getElementById('skipMode').value = 'set';
        document.getElementById('skipExerciseId').value = exerciseId;
        document.getElementById('skipSetNumber').value = setNumber;
        document.getElementById('skipMovementTitle').textContent = I18N_WS.skipSetTitle.replace(':num', setNumber);
        document.getElementById('skipMovementPrompt').textContent = I18N_WS.skipPromptSet.replace(':num', setNumber).replace(':name', exerciseName);
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

        const sideMode = card?.dataset.sideMode || 'bilateral';
        const base = {
            workout_exercise_id: exerciseId,
            set_number: parseInt(setNumber, 10),
            completed: false
        };

        const payloads = [];
        if (mode === 'exercise') {
            payloads.push({ ...base, skip_reason: reason });
        } else {
            if (sideMode === 'per_side') {
                payloads.push({ ...base, side: 'left', note: 'Skipped: ' + reason });
                payloads.push({ ...base, side: 'right', note: 'Skipped: ' + reason });
            } else {
                payloads.push({ ...base, note: 'Skipped: ' + reason, side: 'both' });
            }
        }

        try {
            const results = await Promise.all(payloads.map(p => fetch(SET_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': WS_TOKEN
                },
                body: JSON.stringify(p)
            })));

            const ok = results.every(r => r.ok);

            if (ok) {
                bootstrap.Modal.getInstance(document.getElementById('skipMovementModal'))?.hide();
                if (mode === 'exercise' && card) {
                    card.classList.add('is-skipped');
                    const status = card.querySelector('[data-exercise-status]');
                    if (status) {
                        status.textContent = `${I18N_WS.skippedLabel} ${reason}`;
                        status.className = 'badge bg-danger text-white';
                    }
                    toast(I18N_WS.exerciseSkippedToast);
                } else if (mode === 'set' && card) {
                    const row = card.querySelector(`[data-set-row][data-set-number="${setNumber}"]`);
                    if (row) {
                        row.classList.add('is-skipped');
                        const btn = row.querySelector('.btn-save-set');
                        if (btn) {
                            btn.classList.add('btn-outline-danger');
                            btn.innerHTML = `<i class="bi bi-slash-circle"></i> ${reason}`;
                        }
                    }
                    toast(I18N_WS.setSkippedToast.replace(':num', setNumber));
                }
            } else {
                toast(I18N_WS.errorRecordingSkip);
            }
        } catch (e) {
            toast(I18N_WS.errorConnectingServer);
        }
    }
</script>
@endpush
