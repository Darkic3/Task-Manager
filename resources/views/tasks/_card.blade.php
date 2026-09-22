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
        $days = (int) round(now()->copy()->startOfDay()->diffInDays($due));
        $dueRel = $days === 0 ? 'Today'
            : ($days === 1 ? 'Tomorrow'
            : ($days === -1 ? '1d late'
            : ($days < 0 ? abs($days) . 'd late'
            : ($days > 1 ? $days . 'd left' : ''))));
    }
@endphp
<div class="cu-task-card {{ $task->status === 'completed' ? 'is-done' : '' }}"
     data-id="{{ $task->id }}"
     data-title="{{ strtolower($task->title) }}"
     data-priority="{{ $task->priority }}"
     data-project="{{ $task->project_id }}"
     data-status="{{ $task->status }}"
     data-parent="{{ $task->parent_id ?? '' }}"
     data-assignee="{{ $task->user?->name ?? 'Unassigned' }}"
     style="border-left:2px solid {{ $leftColor }};">

    <div class="cu-task-main">
        <span class="cu-grip" title="Drag to move"><i class="bi bi-grip-vertical"></i></span>
        <input type="checkbox" class="cu-select-box" data-id="{{ $task->id }}" title="Select task">
        <button class="cu-check {{ $task->status === 'completed' ? 'done' : '' }}"
                title="{{ $task->status === 'completed' ? 'Mark as To Do' : 'Mark as Completed' }}">
            <i class="bi {{ $task->status === 'completed' ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
        </button>
        <a href="{{ route('tasks.show', $task->id) }}" class="cu-task-title" title="{{ $task->title }}">
            {{ $task->title }}
        </a>
    </div>

    <div class="cu-task-foot">
        @if($task->due_date)
            <span class="cu-due {{ $overdue ? 'overdue' : '' }}" title="{{ $due->format('M d, Y') }} · {{ ucfirst($task->priority) }} priority">
                <i class="bi bi-calendar-event" style="font-size:10px;"></i>
                {{ $dueRel }}
                @if($overdue)<span class="cu-overdue-tag">Overdue</span>@endif
            </span>
        @endif

        @if($task->children->count() > 0)
            <span class="cu-mini" title="{{ $task->children->count() }} subtasks">
                <i class="bi bi-diagram-3"></i>{{ $task->children->count() }}
            </span>
        @endif

        <span class="cu-assignee" title="{{ $task->user?->name ?? 'Unassigned' }}">
            {{ strtoupper(substr($task->user?->name ?? '—', 0, 1)) }}
        </span>

        <div class="dropdown cu-card-menu">
            <button class="cu-task-menu-btn" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-three-dots"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:13px;border-radius:8px;">
                <li><a class="dropdown-item" href="{{ route('tasks.show', $task->id) }}"><i class="bi bi-eye me-2"></i>View</a></li>
                <li><a class="dropdown-item" href="{{ route('tasks.edit', $task->id) }}"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item text-danger cu-del-task" data-id="{{ $task->id }}"><i class="bi bi-trash me-2"></i>Delete</button></li>
            </ul>
        </div>
    </div>
</div>
