{{-- Recursive compact row for a task inside a chapter section.
     Vars: $task, $grouped (tasks grouped by parent_id), $depth, $rootId --}}
@php
    $isDone = $task->status === 'completed';
    $chOverdue = $task->due_date
        && \Carbon\Carbon::parse($task->due_date)->startOfDay()->lt(now()->startOfDay())
        && $task->status !== 'completed';
    $chDueRel = null;
    if ($task->due_date) {
        $d = \Carbon\Carbon::parse($task->due_date)->startOfDay();
        $dd = (int) round(now()->copy()->startOfDay()->diffInDays($d));
        $chDueRel = $dd === 0 ? 'Today'
            : ($dd === 1 ? 'Tomorrow'
            : ($dd === -1 ? '1d late'
            : ($dd < 0 ? abs($dd) . 'd late' : $dd . 'd left')));
    }
    $kids = $grouped->get($task->id, collect());
@endphp
<div class="cu-ch-row {{ $isDone ? 'is-done' : '' }}"
     data-id="{{ $task->id }}"
     data-root="{{ $rootId }}"
     data-title="{{ strtolower($task->title) }}"
     data-priority="{{ $task->priority }}"
     data-project="{{ $task->project_id }}"
     data-status="{{ $task->status }}"
     data-due="{{ $task->due_date ?? '' }}"
     style="padding-left:calc(10px + {{ $depth }} * 20px);">
    <button class="cu-check {{ $isDone ? 'done' : '' }}"
            title="{{ $isDone ? 'Mark as To Do' : 'Mark as Completed' }}">
        <i class="bi {{ $isDone ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
    </button>
    <a href="{{ route('tasks.show', $task->id) }}" class="cu-task-title" title="{{ $task->title }}">
        {{ $task->title }}
    </a>
    @if($task->due_date)
        <span class="cu-due {{ $chOverdue ? 'overdue' : '' }}" title="{{ $d->format('M d, Y') }}">
            {{ $chDueRel }}
        </span>
    @endif
    @if($kids->count() > 0)
        <span class="cu-mini" title="{{ $kids->count() }} sub-items">
            <i class="bi bi-diagram-3"></i>{{ $kids->count() }}
        </span>
    @endif
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
@foreach($kids as $kid)
    @include('tasks._chapter-row', ['task' => $kid, 'grouped' => $grouped, 'depth' => $depth + 1, 'rootId' => $rootId])
@endforeach
