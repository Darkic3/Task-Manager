@php
    $routineDate = $routineDate ?? now();
    $toggleable  = $toggleable ?? true;
    $record      = $toggleable ? $routine->completionRecord($routineDate) : null;
    $isDone      = $record !== null;
    $doneAt      = $record?->completed_at;
    $freqColors  = ['daily' => '#7c3aed', 'weekly' => '#2563eb', 'monthly' => '#d97706'];
    $fc          = $freqColors[$routine->frequency] ?? '#7c3aed';
    $freqIcons   = ['daily' => 'bi-sun', 'weekly' => 'bi-calendar-week', 'monthly' => 'bi-calendar-month'];
    $fi          = $freqIcons[$routine->frequency] ?? 'bi-arrow-repeat';
    $accent      = $routine->periodColor() ?: $fc;

    /* Habit Ring (package A): adherence ring + streak flame + last-7 dots */
    $ringRate    = $routine->ringRate ?? null;
    $ringStreak  = $routine->ringStreak ?? null;
    $ringLast7   = $routine->ringLast7 ?? null;
    $ringC       = 2 * M_PI * 15.5;
    $ringOffset  = $ringRate !== null ? $ringC - ($ringC * $ringRate / 100) : 0;
@endphp
<div class="pl-task pl-routine {{ $isDone ? 'is-done' : '' }}"
     @if($toggleable)
     data-routine-item
     data-id="{{ $routine->id }}"
     data-date="{{ $routineDate->toDateString() }}"
     data-completed="{{ $isDone ? 1 : 0 }}"
     data-count="{{ !empty($count) ? 1 : 0 }}"
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
    </div>
</div>
