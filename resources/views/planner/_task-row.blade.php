@php
    $isDone = $task->status === 'completed';
    $priorityColors = ['high' => '#dc2626', 'medium' => '#d97706', 'low' => '#16a34a'];
    $pc = $priorityColors[$task->priority] ?? '#94a3b8';
    $due = $task->due_date ? \Carbon\Carbon::parse($task->due_date) : null;
    $isOverdue = $due && ! $isDone && $due->lt(today());
    $periodLabel = method_exists($task, 'periodLabel') ? $task->periodLabel() : null;
    $periodColor = method_exists($task, 'periodColor') ? $task->periodColor() : null;
    $periodIcon  = method_exists($task, 'periodIcon') ? $task->periodIcon() : null;
    $timeLabel   = method_exists($task, 'dueTimeLabel') ? $task->dueTimeLabel() : null;
    $postponeMode = $postpone ?? 'tomorrow';
    $canDrag = !empty($draggable) && ! $isDone;
@endphp
<div class="pl-task {{ $isDone ? 'is-done' : '' }}"
     data-task-item
     data-id="{{ $task->id }}"
     data-period="{{ $task->time_period ?: 'anytime' }}"
     data-completed="{{ $isDone ? 1 : 0 }}"
     data-count="{{ !empty($count) ? 1 : 0 }}"
     @if($canDrag) draggable="true" @endif
     style="border-left:3px solid {{ $pc }};">

    <label class="pl-check" title="{{ $isDone ? 'Mark as not done' : 'Mark as done' }}">
        <input type="checkbox"
               {{ $isDone ? 'checked' : '' }}
               data-url="{{ route('planner.tasks.toggle', $task) }}"
               data-id="{{ $task->id }}"
               onchange="toggleTask(this)">
        <span class="pl-check-box"><i class="bi bi-check-lg"></i></span>
    </label>

    <div class="pl-task-body">
        <div class="pl-task-title">{{ $task->title }}</div>
        <div class="pl-task-meta">
            <span class="pl-priority" style="color:{{ $pc }};background:{{ $pc }}1a;">{{ ucfirst($task->priority) }}</span>
            @if($task->project)
                <span class="pl-proj"><i class="bi bi-folder"></i> {{ $task->project->name }}</span>
            @endif
            @if($periodLabel)
                <span class="pl-priority" data-period-chip style="color:{{ $periodColor ?: '#64748b' }};background:{{ $periodColor ?: '#64748b' }}1a;text-transform:none;">
                    <i class="bi {{ $periodIcon ?: 'bi-clock' }}"></i> {{ $periodLabel }}
                </span>
            @endif
            @if($timeLabel)
                <span class="pl-due"><i class="bi bi-clock"></i> {{ $timeLabel }}</span>
            @endif
            @if($task->estimatedLabel())
                <span class="pl-due"><i class="bi bi-hourglass-split"></i> {{ $task->estimatedLabel() }}</span>
            @endif
            @if($due && empty($hideDue))
                <span class="pl-due {{ $isOverdue ? 'overdue' : '' }}">
                    <i class="bi bi-calendar-event"></i>
                    {{ $due->format('M d') }}
                    @if($isOverdue) · Overdue @endif
                </span>
            @endif
        </div>
    </div>

    <div class="pl-task-actions">
        @if(! $isDone)
            <button type="button"
                    class="pl-task-act {{ $postponeMode === 'today' ? 'pl-task-act-pull' : 'pl-task-act-move' }}"
                    data-postpone="{{ $postponeMode }}"
                    data-id="{{ $task->id }}"
                    title="{{ $postponeMode === 'today' ? 'Pull into today' : 'Postpone to tomorrow' }}"
                    aria-label="{{ $postponeMode === 'today' ? 'Pull into today' : 'Postpone to tomorrow' }}">
                <i class="bi {{ $postponeMode === 'today' ? 'bi-arrow-counterclockwise' : 'bi-arrow-90deg-down' }}"></i>
            </button>
            <button type="button"
                    class="pl-task-act pl-task-act-danger"
                    data-clear-day
                    data-id="{{ $task->id }}"
                    title="Remove from My Day"
                    aria-label="Remove from My Day">
                <i class="bi bi-x-lg"></i>
            </button>
        @endif
        <a href="{{ route('tasks.show', $task->id) }}" class="pl-task-open" title="Open task details">
            <span>Open</span>
            <i class="bi bi-arrow-up-right"></i>
        </a>
    </div>
</div>
