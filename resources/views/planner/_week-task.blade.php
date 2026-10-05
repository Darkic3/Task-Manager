{{-- Clean week-view task card. Vars: $task, $currentDate (Y-m-d of the column it renders in) --}}
@php
    $isDone = $task->status === 'completed';
    $pc = ['high' => '#dc2626', 'medium' => '#d97706', 'low' => '#16a34a'][$task->priority] ?? '#94a3b8';
    $curDate = $currentDate ?? ($task->due_date ? \Carbon\Carbon::parse($task->due_date)->toDateString() : '');
@endphp
<div class="pw-task {{ $isDone ? 'is-done' : '' }}"
     data-task-item
     data-week-task
     data-id="{{ $task->id }}"
     data-period="{{ $task->time_period ?: 'anytime' }}"
     data-completed="{{ $isDone ? 1 : 0 }}"
     data-count="0"
     style="--pw-p:{{ $pc }};">

    <label class="pw-check" title="{{ $isDone ? __('Mark as not done') : __('Mark as done') }}">
        <input type="checkbox"
               {{ $isDone ? 'checked' : '' }}
               data-url="{{ route('planner.tasks.toggle', $task) }}"
               data-id="{{ $task->id }}"
               onchange="toggleTask(this)">
        <span class="pw-check-box"><i class="bi bi-check-lg"></i></span>
    </label>

    <div class="pw-task-main">
        <div class="pw-task-title" title="{{ $task->title }}">{{ $task->title }}</div>
        <div class="pw-task-meta">
            <span class="pw-prio">{{ __(ucfirst($task->priority)) }}</span>
            @if($task->project)
                <span class="pw-proj" title="{{ $task->project->name }}"><i class="bi bi-folder"></i> {{ \Illuminate\Support\Str::limit($task->project->name, 18) }}</span>
            @endif
            @if(method_exists($task, 'estimatedLabel') && $task->estimatedLabel())
                <span class="pw-est"><i class="bi bi-hourglass-split"></i> {{ $task->estimatedLabel() }}</span>
            @endif
        </div>
    </div>

    <div class="pw-task-acts">
        <button type="button" class="pw-act" data-week-move data-id="{{ $task->id }}" data-date="{{ $curDate }}"
                title="{{ __('Move to another day') }}" aria-label="{{ __('Move to another day') }}">
            <i class="bi bi-calendar-week"></i>
        </button>
        <button type="button" class="pw-act pw-act-danger" data-week-remove data-id="{{ $task->id }}"
                title="{{ __('Remove from week') }}" aria-label="{{ __('Remove from week') }}">
            <i class="bi bi-x-lg"></i>
        </button>
        <a href="{{ route('tasks.show', $task->id) }}" class="pw-act" title="{{ __('Open task details') }}">
            <i class="bi bi-arrow-up-right"></i>
        </a>
    </div>
</div>
