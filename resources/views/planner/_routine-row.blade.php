@php
    $routineDate = $routineDate ?? now();
    $toggleable  = $toggleable ?? true;
    $record      = $toggleable ? $routine->completionRecord($routineDate) : null;
    $isDone      = $record !== null;
    $doneAt      = $record?->completed_at;
    $freqColors  = ['daily' => '#7c3aed', 'weekly' => '#2563eb', 'monthly' => '#d97706', 'every_n_days' => '#0e7490'];
    $fc          = $freqColors[$routine->frequency] ?? '#7c3aed';
    $freqIcons   = ['daily' => 'bi-sun', 'weekly' => 'bi-calendar-week', 'monthly' => 'bi-calendar-month', 'every_n_days' => 'bi-arrow-left-right'];
    $fi          = $freqIcons[$routine->frequency] ?? 'bi-arrow-repeat';
    $accent      = $routine->periodColor() ?: $fc;

    /* Habit Ring (package A): adherence ring + streak flame + last-7 dots */
    $ringRate    = $routine->ringRate ?? null;
    $ringStreak  = $routine->ringStreak ?? null;
    $ringLast7   = $routine->ringLast7 ?? null;
    $ringC       = 2 * M_PI * 15.5;
    $ringOffset  = $ringRate !== null ? $ringC - ($ringC * $ringRate / 100) : 0;

    /* Tracking: value mode shows one input, sets mode shows per-step set inputs */
    $trackMode   = $routine->tracking_mode ?? 'none';
    $logValues   = $toggleable ? ($routine->logValues ?? []) : [];
    $logUrl      = $toggleable && $trackMode !== 'none' ? route('planner.routines.log', $routine) : null;

    /* Details (steps + logging) start collapsed to keep the day view tidy */
    $hasSteps    = $toggleable && ! empty($routine->ringSteps) && count($routine->ringSteps) > 0;
    $hasDetails  = $toggleable && ($hasSteps || $logUrl);

    /* Big or tracked routines are easier to fill in a modal than an accordion. */
    $stepCount   = ! empty($routine->ringSteps) ? count($routine->ringSteps) : 0;
    $useModal    = $hasDetails && ($stepCount > 5 || $trackMode !== 'none');
    $modalSub    = ($toggleable ? ucfirst($routine->frequency) : $routine->recurrenceLabel());
    if ($stepCount > 0) {
        $modalSub .= ' · ' . $stepCount . ' steps';
    }
@endphp
<div class="pl-task pl-routine {{ $isDone ? 'is-done' : '' }}"
     @if($toggleable)
     data-routine-item
     data-id="{{ $routine->id }}"
     data-date="{{ $routineDate->toDateString() }}"
     data-completed="{{ $isDone ? 1 : 0 }}"
     data-count="{{ !empty($count) ? 1 : 0 }}"
     @if($useModal)
     data-modal="1"
     data-modal-title="{{ $routine->title }}"
     data-modal-sub="{{ $modalSub }}"
     @endif
     @endif
     style="border-left:3px solid {{ $accent }};">

    @if($toggleable)
        <label class="pl-habit" title="{{ $isDone ? 'Mark as not done' : 'Mark as done' }}{{ $ringRate !== null ? ' · ' . $ringRate . '% adherence (30d)' : '' }}">
            <input type="checkbox"
                   {{ $isDone ? 'checked' : '' }}
                   data-url="{{ route('planner.routines.toggle', $routine) }}"
                   data-id="{{ $routine->id }}"
                   data-date="{{ $routineDate->toDateString() }}"
                   onchange="toggleRoutine(this)">
            @if($ringRate !== null)
                <span class="habit-ring">
                    <svg viewBox="0 0 36 36" aria-hidden="true">
                        <circle class="ring-bg" cx="18" cy="18" r="15.5"></circle>
                        <circle class="ring-fg"
                                style="stroke-dasharray:{{ number_format($ringC, 1, '.', '') }};stroke-dashoffset:{{ number_format($ringOffset, 1, '.', '') }};"></circle>
                    </svg>
                    <span class="routine-check-box"><i class="bi bi-check-lg"></i></span>
                </span>
            @else
                <span class="routine-check-box"><i class="bi bi-check-lg"></i></span>
            @endif
        </label>
    @else
        <span class="pl-routine-static" title="Scheduled for another day">
            <i class="bi bi-calendar3"></i>
        </span>
    @endif

    <div class="pl-task-body">
        <div class="pl-task-title">
            {{ $routine->title }}
            @if($isDone && $toggleable && $ringStreak !== null && $ringStreak > 0)
                <span class="flame" title="{{ $ringStreak }} in a row">🔥{{ $ringStreak }}</span>
            @endif
            @if($toggleable && ! empty($routine->ringSteps) && $routine->ringSteps->count() > 0)
                @php $stepsDone = $routine->ringSteps->where('completed', true)->count(); @endphp
                <span class="steps-count {{ $stepsDone === $routine->ringSteps->count() ? 'all' : '' }}">
                    {{ $stepsDone }}/{{ $routine->ringSteps->count() }}
                </span>
            @endif
            @if($hasDetails)
                @if($useModal)
                    <button type="button" class="pl-expand pl-expand-modal" onclick="openRoutineModal(this)" title="Open details">
                        <i class="bi bi-arrows-angle-expand"></i>
                    </button>
                @else
                    <button type="button" class="pl-expand" onclick="toggleRoutineDetails(this)" title="Show details">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                @endif
            @endif
        </div>
        <div class="pl-task-meta">
            @if($routine->time_period)
                <span class="pl-priority" style="color:{{ $accent }};background:{{ $accent }}1a;text-transform:none;">
                    <i class="bi {{ $routine->periodIcon() }}"></i> {{ $routine->periodLabel() }}
                </span>
            @else
                <span class="pl-priority" style="color:{{ $fc }};background:{{ $fc }}1a;text-transform:none;">
                    <i class="bi {{ $fi }}"></i> {{ $toggleable ? ucfirst($routine->frequency) : $routine->recurrenceLabel() }}
                </span>
                @if($routine->timeLabel())
                    <span class="pl-due"><i class="bi bi-clock"></i> {{ $routine->timeLabel() }}</span>
                @endif
            @endif
            @if($isDone && $doneAt)
                <span class="pl-due" style="color:#16a34a;"><i class="bi bi-check2"></i> {{ $doneAt->format('g:i A') }}</span>
            @endif
            @if($ringLast7 !== null && $toggleable)
                <span class="last7" title="Last 7 days">
                    @foreach($ringLast7 as $sq)
                        <i class="sq sq-{{ $sq['state'] }}"></i>
                    @endforeach
                </span>
            @endif
        </div>

        @if($hasDetails)
        <div class="pl-details" data-details>
        @endif
        @if($toggleable && ! empty($routine->ringSteps) && count($routine->ringSteps) > 0)
            <div class="pl-steps">
                @foreach($routine->ringSteps as $step)
                    <button type="button"
                            class="pl-step {{ $step['completed'] ? 'done' : '' }}"
                            data-step-item data-id="{{ $step['id'] }}"
                            data-routine="{{ $routine->id }}"
                            data-date="{{ $routineDate->toDateString() }}"
                            data-url="{{ route('planner.check-items.toggle', $step['id']) }}"
                            data-tracked="{{ $trackMode }}"
                            data-logged="{{ !empty($step['sets']) ? 1 : 0 }}"
                            onclick="toggleCheckItem(this)">
                        <i class="bi {{ $step['completed'] ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
                        {{ $step['name'] }}
                    </button>
                @endforeach
            </div>
        @endif

        @if($logUrl && $trackMode === 'value')
            <div class="pl-log" data-log-value data-routine="{{ $routine->id }}" data-date="{{ $routineDate->toDateString() }}" data-url="{{ $logUrl }}">
                <input type="number" step="any" min="0" placeholder="{{ $routine->trackingLabel() }}"
                       value="{{ $logValues['value'] ?? '' }}" aria-label="{{ $routine->trackingLabel() }}">
                <button type="button" onclick="logRoutineValue(this)">ثبت</button>
                @if(isset($logValues['value']))<span class="pl-log-saved">✓ {{ $logValues['value'] }}</span>@endif
            </div>
        @endif

        @if($logUrl && $trackMode === 'sets' && ! empty($routine->ringSteps) && count($routine->ringSteps) > 0)
            <div class="pl-logsets">
                @foreach($routine->ringSteps as $step)
                    @php $logged = $step['sets'] ?? []; $target = max(1, (int) ($step['target_sets'] ?? 1)); @endphp
                    <div class="pl-logset" data-log-sets data-item="{{ $step['id'] }}" data-routine="{{ $routine->id }}" data-date="{{ $routineDate->toDateString() }}" data-url="{{ $logUrl }}">
                        <span class="pl-logset-name">{{ $step['name'] }}{{ $step['unit'] ? ' (' . $step['unit'] . ')' : '' }}</span>
                        @for($s = 1; $s <= $target; $s++)
                            <input type="number" step="any" min="0" data-set="{{ $s }}" placeholder="S{{ $s }}"
                                   value="{{ $logged[$s] ?? '' }}" aria-label="{{ $step['name'] }} set {{ $s }}">
                        @endfor
                        <button type="button" onclick="logRoutineSets(this)">ثبت</button>
                    </div>
                @endforeach
            </div>
        @endif
        @if($hasDetails)
        </div>
        @endif
    </div>
</div>
