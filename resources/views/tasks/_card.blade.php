@php
    $overdue = $task->due_date
        && \Carbon\Carbon::parse($task->due_date)->startOfDay()->lt(now()->startOfDay())
        && $task->status !== 'completed';
    $priorityColors = ['high' => '#e5484d', 'medium' => '#f5a623', 'low' => '#30a46c'];
    $leftColor = $priorityColors[$task->priority] ?? '#94a3b8';

    /* Relative due date: "Today", "Tomorrow", "3d left", "2d late" */
    $dueRel = null;
    if ($task->due_date) {
        $due = \Carbon\Carbon::parse($task->due_date)->startOfDay();
        $days = (int) round(now()->copy()->startOfDay()->diffInDays($due, false));
        if ($days === 0) {
            $dueRel = __('Today');
        } elseif ($days === 1) {
            $dueRel = __('Tomorrow');
        } elseif ($days === -1) {
            $dueRel = __('1d late');
        } elseif ($days < 0) {
            $dueRel = __(':days d late', ['days' => abs($days)]);
        } elseif ($days > 1) {
            $dueRel = __(':days d left', ['days' => $days]);
        }
    }

    /* Planned for today with a day-period chip */
    $plannedToday = $task->time_period && $task->due_date
        && \Carbon\Carbon::parse($task->due_date)->isToday()
        && $task->status !== 'completed';
    $plannedPeriod = $plannedToday
        ? (config("routines.periods.{$task->time_period}.label") ? __(config("routines.periods.{$task->time_period}.label")) : __(ucfirst($task->time_period)))
        : null;
    $plannedIcon = config("routines.periods.{$task->time_period}.icon") ?? 'bi-calendar-day';
@endphp
<div class="cu-task-card {{ $task->status === 'completed' ? 'is-done' : '' }}"
     data-id="{{ $task->id }}"
     data-title="{{ strtolower($task->title) }}"
     data-priority="{{ $task->priority }}"
     data-project="{{ $task->project_id }}"
     data-status="{{ $task->status }}"
     data-parent="{{ $task->parent_id ?? '' }}"
     data-assignee="{{ $task->user?->name ?? __('Unassigned') }}"
     style="border-left:2px solid {{ $leftColor }};">

    <div class="cu-task-main">
        <span class="cu-grip" title="{{ __('Drag to move') }}"><i class="bi bi-grip-vertical"></i></span>
        <input type="checkbox" class="cu-select-box" data-id="{{ $task->id }}" title="{{ __('Select task') }}">
        <button class="cu-check {{ $task->status === 'completed' ? 'done' : '' }}"
                title="{{ $task->status === 'completed' ? __('Mark as To Do') : __('Mark as Completed') }}">
            <i class="bi {{ $task->status === 'completed' ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
        </button>
        <a href="{{ route('tasks.show', $task->id) }}" class="cu-task-title" title="{{ $task->title }}" onclick="event.preventDefault(); if(typeof openTaskDrawer === 'function') openTaskDrawer({{ $task->id }}); else window.location.href='{{ route('tasks.show', $task->id) }}';">
            {{ $task->title }}
        </a>
    </div>

    <div class="cu-task-foot">
        @if($task->due_date)
            <span class="cu-due {{ $overdue ? 'overdue' : '' }}" title="{{ app_date($due) }} · {{ __(ucfirst($task->priority)) }} {{ __('Priority') }}">
                <i class="bi bi-calendar-event" style="font-size:10px;"></i>
                {{ $dueRel }}
                @if($overdue)<span class="cu-overdue-tag">{{ __('Overdue') }}</span>@endif
            </span>
        @endif

        @if($plannedToday)
            <span class="cu-mini" style="color:#7c3aed;background:#f3effe;" title="{{ __('Planned today') }} · {{ $plannedPeriod }}">
                <i class="bi {{ $plannedIcon }}"></i> {{ $plannedPeriod }}
            </span>
        @endif

        @if($task->children->count() > 0)
            <span class="cu-mini" title="{{ $task->children->count() }} {{ __('Subtasks') }}">
                <i class="bi bi-diagram-3"></i>{{ $task->children->count() }}
            </span>
        @endif

        <span class="cu-assignee" title="{{ $task->user?->name ?? __('Unassigned') }}">
            {{ strtoupper(substr($task->user?->name ?? '—', 0, 1)) }}
        </span>

        @if($task->status !== 'completed')
            <button type="button" class="cu-add-day {{ $plannedToday ? 'set' : '' }}"
                    data-add-day data-id="{{ $task->id }}" data-title="{{ $task->title }}"
                    data-period="{{ $task->time_period }}"
                    title="{{ $plannedToday ? __('Change today\'s slot') : __('Add to today\'s plan') }}">
                <i class="bi {{ $plannedToday ? 'bi-calendar2-check' : 'bi-calendar-plus' }}"></i>
            </button>
        @endif

        <div class="dropdown cu-card-menu">
            <button class="cu-task-menu-btn" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-three-dots"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:13px;border-radius:8px;">
                <li><a class="dropdown-item" href="{{ route('tasks.show', $task->id) }}"><i class="bi bi-eye me-2"></i>{{ __('View') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('tasks.edit', $task->id) }}"><i class="bi bi-pencil me-2"></i>{{ __('Edit') }}</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item text-danger cu-del-task" data-id="{{ $task->id }}"><i class="bi bi-trash me-2"></i>{{ __('Delete') }}</button></li>
            </ul>
        </div>
    </div>
</div>
