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
@endphp
<div class="pl-task pl-routine {{ $isDone ? 'is-done' : '' }}"
     @if($toggleable)
     data-routine-item
     data-id="{{ $routine->id }}"
     data-date="{{ $routineDate->toDateString() }}"
     data-completed="{{ $isDone ? 1 : 0 }}"
     data-count="{{ !empty($count) ? 1 : 0 }}"
     @endif
     style="border-left:3px solid {{ $fc }};">

    @if($toggleable)
        <label class="pl-check" title="{{ $isDone ? 'Mark as not done' : 'Mark as done' }}">
            <input type="checkbox"
                   {{ $isDone ? 'checked' : '' }}
                   data-url="{{ route('planner.routines.toggle', $routine) }}"
                   data-id="{{ $routine->id }}"
                   data-date="{{ $routineDate->toDateString() }}"
                   onchange="toggleRoutine(this)">
            <span class="pl-check-box"><i class="bi bi-check-lg"></i></span>
        </label>
    @else
        <span class="pl-routine-static" title="Scheduled for another day">
            <i class="bi bi-calendar3"></i>
        </span>
    @endif

    <div class="pl-task-body">
        <div class="pl-task-title">{{ $routine->title }}</div>
        <div class="pl-task-meta">
            <span class="pl-priority" style="color:{{ $fc }};background:{{ $fc }}1a;text-transform:none;">
                <i class="bi {{ $fi }}"></i> {{ $toggleable ? ucfirst($routine->frequency) : $routine->recurrenceLabel() }}
            </span>
            @if($routine->timeLabel())
                <span class="pl-due"><i class="bi bi-clock"></i> {{ $routine->timeLabel() }}</span>
            @endif
            @if($isDone && $doneAt)
                <span class="pl-due" style="color:#16a34a;"><i class="bi bi-check2"></i> {{ $doneAt->format('g:i A') }}</span>
            @endif
        </div>
    </div>
</div>
