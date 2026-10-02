@php
    $isDone = $task->status === 'completed';
    $isFailed = ! $isDone && ! empty($task->failed_at);
    $priorityColors = ['high' => '#dc2626', 'medium' => '#d97706', 'low' => '#16a34a'];
    $pc = $isFailed ? '#dc2626' : ($priorityColors[$task->priority] ?? '#94a3b8');
    $due = $task->due_date ? \Carbon\Carbon::parse($task->due_date) : null;
    $isOverdue = $due && ! $isDone && $due->lt(today());
    $periodLabel = method_exists($task, 'periodLabel') ? $task->periodLabel() : null;
    $periodColor = method_exists($task, 'periodColor') ? $task->periodColor() : null;
    $periodIcon  = method_exists($task, 'periodIcon') ? $task->periodIcon() : null;
    $timeLabel   = method_exists($task, 'dueTimeLabel') ? $task->dueTimeLabel() : null;
    $postponeMode = $postpone ?? 'tomorrow';
    $canDrag = !empty($draggable) && ! $isDone;
@endphp
<div class="pl-task {{ $isDone ? 'is-done' : '' }} {{ $isFailed ? 'is-failed' : '' }}"
     data-task-item
     data-id="{{ $task->id }}"
     data-period="{{ $task->time_period ?: 'anytime' }}"
     data-completed="{{ $isDone ? 1 : 0 }}"
     data-failed="{{ $isFailed ? 1 : 0 }}"
     data-count="{{ !empty($count) ? 1 : 0 }}"
     @if($canDrag) draggable="true" @endif
     style="border-left:3px solid {{ $pc }};">

    <label class="pl-check" title="{{ $isDone ? __('Mark as not done') : __('Mark as done') }}">
        <input type="checkbox"
               {{ $isDone ? 'checked' : '' }}
               data-url="{{ route('planner.tasks.toggle', $task) }}"
               data-id="{{ $task->id }}"
               onchange="toggleTask(this)">
        <span class="pl-check-box"><i class="bi bi-check-lg"></i></span>
    </label>

    <div class="pl-task-body" ondblclick="window.location='{{ route('tasks.show', $task->id) }}'">
        <div class="pl-task-title">{{ $task->title }}</div>
        <div class="pl-task-meta">
            @if($isFailed)
                <span class="pl-fail-tag" @if(!empty($task->fail_note)) title="{{ $task->fail_note }}" @endif>
                    <i class="bi bi-x-circle-fill"></i> {{ __('Failed') }}
                </span>
            @endif            <span class="pl-priority" style="color:{{ $pc }};background:{{ $pc }}1a;">{{ __(ucfirst($task->priority)) }}</span>
            @if($task->project)
                <span class="pl-proj"><i class="bi bi-folder"></i> {{ $task->project->name }}</span>
            @endif
            @if($periodLabel)
                <span class="pl-priority" data-period-chip style="color:{{ $periodColor ?: '#64748b' }};background:{{ $periodColor ?: '#64748b' }}1a;text-transform:none;">
                    <i class="bi {{ $periodIcon ?: 'bi-clock' }}"></i> {{ __($periodLabel) }}
                </span>
            @endif
            @if($timeLabel)
                <span class="pl-due"><i class="bi bi-clock"></i> {{ $timeLabel }}</span>
            @endif
            
            {{-- Interactive Estimate Badge / Quick Chips --}}
            <div class="pl-task-estimate-wrapper" data-task-estimate-wrap>
                <span class="pl-due pl-due-clickable" data-estimate-badge onclick="event.stopPropagation(); toggleEstimatePicker({{ $task->id }})" title="{{ __('Click to change estimated time') }}">
                    <i class="bi bi-hourglass-split"></i>
                    <span data-estimate-text>{{ $task->estimatedLabel() ?: __('Add Est') }}</span>
                </span>
                <div class="pl-estimate-popover" id="plEstimatePopover-{{ $task->id }}" style="display:none;">
                    <span class="small text-muted fw-bold d-block mb-1">{{ __('Estimated Time') }}:</span>
                    <div class="d-flex gap-1 flex-wrap">
                        <button type="button" class="pl-est-btn" onclick="setQuickEstimate({{ $task->id }}, 0.25)">15m</button>
                        <button type="button" class="pl-est-btn" onclick="setQuickEstimate({{ $task->id }}, 0.5)">30m</button>
                        <button type="button" class="pl-est-btn" onclick="setQuickEstimate({{ $task->id }}, 1)">1h</button>
                        <button type="button" class="pl-est-btn" onclick="setQuickEstimate({{ $task->id }}, 2)">2h</button>
                        <button type="button" class="pl-est-btn" onclick="setQuickEstimate({{ $task->id }}, 3)">3h</button>
                    </div>
                </div>
            </div>

            @if($due && empty($hideDue))
                <span class="pl-due {{ $isOverdue ? 'overdue' : '' }}">
                    <i class="bi bi-calendar-event"></i>
                    {{ $due->format('M d') }}
                    @if($isOverdue) · {{ __('Overdue') }} @endif
                </span>
            @endif
        </div>
        @if($isFailed && !empty($task->fail_note))
            <div class="pl-fail-note" title="{{ $task->fail_note }}">
                <i class="bi bi-chat-left-text"></i> {{ \Illuminate\Support\Str::limit($task->fail_note, 120) }}
            </div>
        @endif
    </div>

    {{-- Quick Actions Bar --}}
    <div class="pl-task-actions">
        @if(! $isDone)
            {{-- Quick Start Timer --}}
            <button type="button"
                    class="pl-task-act pl-task-act-play"
                    onclick="event.stopPropagation(); plStartTaskTimer({{ $task->id }}, '{{ addslashes($task->title) }}', '{{ addslashes($task->project->name ?? 'My Day') }}')"
                    title="{{ __('Start Timer') }}"
                    aria-label="{{ __('Start Timer') }}">
                <i class="bi bi-play-fill"></i>
            </button>

            {{-- Focus Mode --}}
            <button type="button"
                    class="pl-task-act pl-task-act-focus"
                    onclick="event.stopPropagation(); openFocusWorkstation({{ $task->id }}, '{{ addslashes($task->title) }}', '{{ addslashes($task->project->name ?? 'My Day') }}')"
                    title="{{ __('Focus Mode • Workstation') }}"
                    aria-label="{{ __('Focus Mode • Workstation') }}">
                <i class="bi bi-bullseye"></i>
            </button>

            {{-- Postpone --}}
            <button type="button"
                    class="pl-task-act {{ $postponeMode === 'today' ? 'pl-task-act-pull' : 'pl-task-act-move' }}"
                    data-postpone="{{ $postponeMode }}"
                    data-id="{{ $task->id }}"
                    title="{{ $postponeMode === 'today' ? __('Pull into today') : __('Postpone to tomorrow') }}"
                    aria-label="{{ $postponeMode === 'today' ? __('Pull into today') : __('Postpone to tomorrow') }}">
                <i class="bi {{ $postponeMode === 'today' ? 'bi-arrow-counterclockwise' : 'bi-arrow-90deg-down' }}"></i>
            </button>

            {{-- Clear Day --}}
            <button type="button"
                    class="pl-task-act pl-task-act-danger"
                    data-clear-day
                    data-id="{{ $task->id }}"
                    title="{{ __('Remove from My Day') }}"
                    aria-label="{{ __('Remove from My Day') }}">
                <i class="bi bi-x-lg"></i>
            </button>

            {{-- Fail (explicit "won't do it" with note + optional reschedule) --}}
            <button type="button"
                    class="pl-task-act pl-task-act-fail {{ $isFailed ? 'active' : '' }}"
                    data-fail-task
                    data-id="{{ $task->id }}"
                    data-fail-url="{{ route('planner.tasks.fail', $task) }}"
                    data-unfail-url="{{ route('planner.tasks.unfail', $task) }}"
                    title="{{ $isFailed ? __('Undo fail') : __('Mark as failed') }}"
                    aria-label="{{ $isFailed ? __('Undo fail') : __('Mark as failed') }}">
                <i class="bi {{ $isFailed ? 'bi-arrow-counterclockwise' : 'bi-x-octagon' }}"></i>
            </button>
        @endif
        <a href="{{ route('tasks.show', $task->id) }}" class="pl-task-open" title="{{ __('Open task details') }}">
            <span>{{ __('Open') }}</span>
            <i class="bi bi-arrow-up-right"></i>
        </a>
    </div>
</div>
