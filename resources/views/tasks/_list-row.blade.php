{{-- Single row of the List view. Vars: $task --}}
<div class="cu-list-row" data-title="{{ strtolower($task->title) }}" data-priority="{{ $task->priority }}" data-project="{{ $task->project_id }}" data-status="{{ $task->status }}" data-due="{{ $task->due_date ?? '' }}">
    <div>
        <div class="cu-list-title">{{ $task->title }}</div>
        <div class="cu-list-sub">
            <span class="cu-status-chip {{ $task->status }}">
                <i class="bi bi-circle-fill" style="font-size:5px;"></i>
                {{ ucwords(str_replace('_',' ',$task->status)) }}
            </span>
        </div>
    </div>
    <div class="cu-list-project">
        @if($task->project)
            <i class="bi bi-folder" style="color:#8b8d98;font-size:11px;"></i>
            {{ $task->project->name }}
        @else
            <span style="color:#c4c9d4;">—</span>
        @endif
    </div>
    <div><span class="cu-priority {{ $task->priority }}">{{ ucfirst($task->priority) }}</span></div>
    <div style="font-size:12px;color:#3d4149;">
        @if($task->user)
            <div style="display:flex;align-items:center;gap:6px;">
                <div class="cu-assignee" style="width:24px;height:24px;font-size:10px;">{{ name_initials($task->user->name) }}</div>
                <span>{{ $task->user->name }}</span>
            </div>
        @else
            <span style="color:#c4c9d4;">{{ __('Unassigned') }}</span>
        @endif
    </div>
    <div class="cu-due {{ $task->due_date && \Carbon\Carbon::parse($task->due_date)->startOfDay()->lt(now()->startOfDay()) && $task->status !== 'completed' ? 'overdue' : '' }}" style="font-size:12px;">
        @if($task->due_date)
            <i class="bi bi-calendar-event" style="font-size:11px;"></i>
            {{ \Carbon\Carbon::parse($task->due_date)->format('M d, Y') }}
        @else
            <span style="color:#c4c9d4;">—</span>
        @endif
    </div>
    <div class="cu-list-actions">
        @if($task->status !== 'completed')
            <button type="button" class="cu-task-btn {{ $task->time_period && $task->due_date && \Carbon\Carbon::parse($task->due_date)->startOfDay()->gte(now()->startOfDay()) ? 'is-set' : '' }}"
                    data-add-day data-id="{{ $task->id }}" data-title="{{ $task->title }}"
                    data-period="{{ $task->time_period }}"
                    data-date="{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->toDateString() : '' }}"
                    title="{{ __('Schedule task') }} (T)">
                <i class="bi bi-calendar-plus"></i>
            </button>
        @endif
        <a href="{{ route('tasks.show', $task->id) }}" class="cu-task-btn" title="View"><i class="bi bi-eye"></i></a>
        <a href="{{ route('tasks.edit', $task->id) }}" class="cu-task-btn" title="Edit"><i class="bi bi-pencil"></i></a>
    </div>
</div>
