@extends('layouts.app')

@section('title', __('Daily Command Center') . ' | ' . __('TaskManager'))

@section('page-title', __('Daily Command Center'))

@section('content')
<div class="container-fluid px-3 px-lg-4 py-3">

    {{-- Live Active Timer Widget (if running) --}}
    @if($activeTimeEntry)
    <div class="live-timer-banner mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="timer-pulse-dot"></span>
                <div>
                    <div class="timer-banner-label">{{ __('Active Time Tracking') }}</div>
                    <div class="timer-banner-task font-monospace fw-bold">
                        {{ $activeTimeEntry->task->title ?? ($activeTimeEntry->description ?: __('Untracked Activity')) }}
                        @if($activeTimeEntry->project)
                            <span class="badge bg-white text-dark ms-2 opacity-75 font-sans">{{ $activeTimeEntry->project->name }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="timer-live-clock font-monospace" id="dashboardLiveTimer" data-start="{{ $activeTimeEntry->started_at->toIso8601String() }}">00:00:00</div>
                <form action="{{ route('time.stop', $activeTimeEntry) }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1">
                        <i class="bi bi-stop-fill"></i> {{ __('Stop') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Header & Daily Briefing --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="welcome-heading mb-1">
                {{ app_greeting() }}, {{ Auth::user()->name }} 👋
            </h2>
            <div class="text-muted small d-flex align-items-center gap-3 flex-wrap">
                <span><i class="bi bi-calendar3 me-1 text-primary"></i> {{ app_human_date() }}</span>
                <span>•</span>
                <span><i class="bi bi-check-circle me-1 text-success"></i> <strong>{{ $tasksCompletedToday }}</strong> {{ __('tasks done today') }}</span>
                <span>•</span>
                <span><i class="bi bi-arrow-repeat me-1 text-warning"></i> <strong id="headerRoutineCount">{{ $routineDoneCount }}/{{ $routineTotalCount }}</strong> {{ __('routines checked') }}</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('planner.index') }}" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-calendar-check"></i> {{ __('Open My Day') }}
            </a>
        </div>
    </div>

    {{-- Quick Action Hub --}}
    <div class="quick-hub-bar mb-4">
        <a href="{{ route('tasks.create') }}" class="quick-hub-btn">
            <span class="quick-hub-icon bg-primary-subtle text-primary"><i class="bi bi-plus-lg"></i></span>
            <span>{{ __('New Task') }}</span>
        </a>
        <a href="{{ route('workouts.plans.index') }}" class="quick-hub-btn">
            <span class="quick-hub-icon bg-danger-subtle text-danger"><i class="bi bi-activity"></i></span>
            <span>{{ __('Workouts') }}</span>
        </a>
        <a href="{{ route('planner.index') }}" class="quick-hub-btn">
            <span class="quick-hub-icon bg-warning-subtle text-warning"><i class="bi bi-calendar-day"></i></span>
            <span>{{ __('Daily Planner') }}</span>
        </a>
        <a href="{{ route('notes.create') }}" class="quick-hub-btn">
            <span class="quick-hub-icon bg-success-subtle text-success"><i class="bi bi-journal-plus"></i></span>
            <span>{{ __('Quick Note') }}</span>
        </a>
        <a href="{{ route('reminders.create') }}" class="quick-hub-btn">
            <span class="quick-hub-icon bg-info-subtle text-info"><i class="bi bi-bell-fill"></i></span>
            <span>{{ __('Set Reminder') }}</span>
        </a>
        <a href="{{ route('ai.index') }}" class="quick-hub-btn">
            <span class="quick-hub-icon bg-purple-subtle text-purple"><i class="bi bi-stars"></i></span>
            <span>{{ __('Ask Lina') }}</span>
        </a>
    </div>

    {{-- Core 3-Pillar Daily HQ --}}
    <div class="row g-4 mb-4">
        
        {{-- Pillar 1: Today's Focus & Top Priority Tasks --}}
        <div class="col-xl-4 col-md-6">
            <div class="hq-card h-100">
                <div class="hq-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="hq-badge-icon bg-danger-subtle text-danger"><i class="bi bi-bullseye"></i></span>
                        <h3 class="hq-title m-0">{{ __("Today's Focus") }}</h3>
                    </div>
                    <a href="{{ route('tasks.index') }}" class="hq-link">{{ __('View All') }} <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="hq-card-body p-0">
                    <div class="focus-task-list">
                        @forelse($todayFocusTasks as $task)
                        <div class="focus-task-item d-flex align-items-start gap-3 p-3 border-bottom {{ $task->status === 'completed' ? 'task-done' : '' }}" id="dash-task-{{ $task->id }}">
                            <div class="task-check-wrap pt-1">
                                <input type="checkbox" class="form-check-input task-inline-check" 
                                       id="check-task-{{ $task->id }}"
                                       data-task-id="{{ $task->id }}"
                                       data-url="{{ url('tasks/' . $task->id . '/update-status') }}"
                                       {{ $task->status === 'completed' ? 'checked' : '' }}
                                       onchange="toggleDashTaskStatus(this)">
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <div class="task-title-text fw-medium text-truncate" title="{{ $task->title }}" style="cursor:pointer;" onclick="openTaskDrawer({{ $task->id }})">
                                        {{ $task->title }}
                                    </div>
                                    <span class="badge {{ $task->priority === 'high' ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning' }} rounded-pill text-uppercase px-2" style="font-size:10px;">
                                        {{ $task->priority }}
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-1 text-muted small flex-wrap">
                                    @if($task->project)
                                    <span class="project-tag"><i class="bi bi-folder2 text-secondary"></i> {{ $task->project->name }}</span>
                                    @endif
                                    @if($task->due_date)
                                    <span class="{{ \Carbon\Carbon::parse($task->due_date)->isPast() && !\Carbon\Carbon::parse($task->due_date)->isToday() ? 'text-danger fw-medium' : '' }}">
                                        <i class="bi bi-clock"></i> {{ \Carbon\Carbon::parse($task->due_date)->isToday() ? __('Today') : app_date($task->due_date, 'M d') }}
                                    </span>
                                    @endif
                                    @if($task->checklistItems->isNotEmpty())
                                    <span>
                                        <i class="bi bi-check2-square"></i> {{ $task->checklistItems->where('completed', true)->count() }}/{{ $task->checklistItems->count() }}
                                    </span>
                                    @endif
                                </div>
                            </div>
                            <div class="task-actions-wrap pt-1">
                                <button type="button" class="btn btn-sm btn-light border p-1 text-muted hover-primary" title="{{ __('Start') }}" onclick="startTimerForTask({{ $task->id }}, {{ $task->project_id ?? 'null' }}, '{{ addslashes($task->title) }}')">
                                    <i class="bi bi-play-fill text-success fs-6"></i>
                                </button>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-5 px-3">
                            <div class="empty-icon-circle mx-auto mb-3 bg-success-subtle text-success">
                                <i class="bi bi-check2-all fs-4"></i>
                            </div>
                            <h6 class="fw-bold mb-1">{{ __('All Clear for Today!') }}</h6>
                            <p class="text-muted small mb-3">{{ __('No urgent or high priority tasks remaining.') }}</p>
                            <a href="{{ route('tasks.create') }}" class="btn btn-sm btn-outline-primary">{{ __('+ Add New Task') }}</a>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Pillar 2: Today's Workout Session --}}
        <div class="col-xl-4 col-md-6">
            <div class="hq-card h-100">
                <div class="hq-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="hq-badge-icon bg-primary-subtle text-primary"><i class="bi bi-activity"></i></span>
                        <h3 class="hq-title m-0">{{ __("Today's Workout") }}</h3>
                    </div>
                    <a href="{{ route('workouts.plans.index') }}" class="hq-link">{{ __('Workout Hub') }} <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="hq-card-body p-3">
                    @if($todayWorkoutDay)
                        <div class="workout-plan-preview mb-3 p-3 rounded-3 bg-light border">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-dark text-white text-uppercase" style="font-size:10px;">{{ $activeWorkoutPlan->title ?? __('Current Plan') }}</span>
                                    <h5 class="fw-bold mt-1 mb-0">{{ $todayWorkoutDay->title ?: ucfirst($todayWorkoutDay->weekday) }}</h5>
                                </div>
                                <span class="badge {{ $todayWorkoutDay->isTraining() ? 'bg-primary text-white' : 'bg-secondary text-white' }}">
                                    {{ $todayWorkoutDay->isTraining() ? __('Training Day') : __('Rest Day') }}
                                </span>
                            </div>

                            @if($todayWorkoutDay->isTraining())
                                <div class="exercise-chip-list d-flex flex-wrap gap-1 my-3">
                                    @forelse($todayWorkoutDay->exercises->take(5) as $we)
                                    <span class="badge bg-white text-dark border fw-normal" style="font-size:11px;">
                                        {{ $we->exercise->name ?? __('Exercise') }} ({{ $we->target_sets }}s)
                                    </span>
                                    @empty
                                    <span class="text-muted small">{{ __('No specific exercises configured.') }}</span>
                                    @endforelse
                                    @if($todayWorkoutDay->exercises->count() > 5)
                                    <span class="badge bg-light text-muted border" style="font-size:11px;">+{{ $todayWorkoutDay->exercises->count() - 5 }} {{ __('more') }}</span>
                                    @endif
                                </div>

                                <div class="mt-3">
                                    @if($todayWorkoutSession && $todayWorkoutSession->status === 'completed')
                                        <div class="alert alert-success d-flex align-items-center justify-content-between py-2 px-3 m-0 rounded-3">
                                            <span class="d-flex align-items-center gap-2 small fw-medium">
                                                <i class="bi bi-check-circle-fill text-success fs-5"></i> {{ __('Session Completed Today!') }}
                                            </span>
                                            <a href="{{ route('workouts.sessions.show', $todayWorkoutSession) }}" class="btn btn-sm btn-outline-success">{{ __('View Log') }}</a>
                                        </div>
                                    @elseif($todayWorkoutSession && $todayWorkoutSession->status === 'in_progress')
                                        <a href="{{ route('workouts.sessions.show', $todayWorkoutSession) }}" class="btn btn-warning w-100 d-flex align-items-center justify-content-center gap-2 py-2 fw-semibold">
                                            <i class="bi bi-play-circle-fill"></i> {{ __('Continue Workout Session') }}
                                        </a>
                                    @else
                                        <form action="{{ route('workouts.sessions.start', $todayWorkoutDay) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2 py-2 fw-semibold shadow-sm">
                                                <i class="bi bi-lightning-charge-fill"></i> {{ __('Start Workout Session') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @else
                                <div class="p-3 text-center text-muted">
                                    <i class="bi bi-cup-hot text-warning fs-3 mb-2 d-block"></i>
                                    <p class="small mb-0">{{ __('Active rest day. Focus on hydration, stretching, and nutrition!') }}</p>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="text-center py-4 px-3">
                            <div class="empty-icon-circle mx-auto mb-3 bg-primary-subtle text-primary">
                                <i class="bi bi-heart-pulse fs-4"></i>
                            </div>
                            <h6 class="fw-bold mb-1">{{ __('No Active Workout Plan') }}</h6>
                            <p class="text-muted small mb-3">{{ __('Set up a training plan to track exercises and daily cycles.') }}</p>
                            <a href="{{ route('workouts.plans.create') }}" class="btn btn-sm btn-outline-primary">{{ __('+ Create Workout Plan') }}</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Pillar 3: Today's Habits & Routines --}}
        <div class="col-xl-4 col-md-12">
            <div class="hq-card h-100">
                <div class="hq-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="hq-badge-icon bg-warning-subtle text-warning"><i class="bi bi-arrow-repeat"></i></span>
                        <h3 class="hq-title m-0">{{ __('Habits & Routines') }}</h3>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill bg-light text-dark border px-2 font-monospace" id="dbRoutineCount">{{ $routineDoneCount }}/{{ $routineTotalCount }}</span>
                        <a href="{{ route('planner.index') }}" class="hq-link d-inline-flex align-items-center gap-1">
                            <i class="bi bi-calendar-day"></i> {{ __('Daily Planner') }} <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <div class="hq-card-body p-2">
                    <div class="routine-quick-list d-flex flex-column gap-2" id="dbRoutineList">
                        @forelse($todayRoutines as $routine)
                            @include('planner._routine-row', [
                                'routine' => $routine,
                                'routineDate' => now(),
                                'toggleable' => true,
                                'count' => true,
                            ])
                        @empty
                            <div class="text-center py-5 px-3">
                                <div class="empty-icon-circle mx-auto mb-3 bg-warning-subtle text-warning">
                                    <i class="bi bi-sun fs-4"></i>
                                </div>
                                <h6 class="fw-bold mb-1">{{ __('No Routines Scheduled') }}</h6>
                                <p class="text-muted small mb-3">{{ __('Add daily or weekly habits to build your discipline.') }}</p>
                                <a href="{{ route('routines.create') }}" class="btn btn-sm btn-outline-warning">{{ __('+ Add Routine') }}</a>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Performance & Analytics Overview --}}
    <div class="row g-4 mb-4">
        
        {{-- Productivity Trend Chart --}}
        <div class="col-xl-8">
            <div class="hq-card h-100">
                <div class="hq-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="hq-badge-icon bg-success-subtle text-success"><i class="bi bi-graph-up"></i></span>
                        <h3 class="hq-title m-0">{{ __('Productivity Pulse (14 Days)') }}</h3>
                    </div>
                    <span class="badge bg-light text-dark border">{{ $completedTasksThisWeek }} {{ __('completed this week') }}</span>
                </div>
                <div class="hq-card-body p-3">
                    <div class="chart-container" style="position: relative; height:240px;">
                        <canvas id="productivityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Task & Priority Breakdown --}}
        <div class="col-xl-4">
            <div class="hq-card h-100">
                <div class="hq-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="hq-badge-icon bg-info-subtle text-info"><i class="bi bi-pie-chart"></i></span>
                        <h3 class="hq-title m-0">{{ __('Task Health & Status') }}</h3>
                    </div>
                    <span class="small text-muted">{{ $tasksCount }} {{ __('total') }}</span>
                </div>
                <div class="hq-card-body p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div style="width: 140px; height: 140px;">
                            <canvas id="taskStatusChart"></canvas>
                        </div>
                    </div>
                    <div class="d-flex justify-content-around text-center border-top pt-3">
                        <div>
                            <div class="small text-muted">{{ __('To Do') }}</div>
                            <div class="fw-bold text-primary">{{ $taskStatusDistribution['to_do'] }}</div>
                        </div>
                        <div>
                            <div class="small text-muted">{{ __('In Progress') }}</div>
                            <div class="fw-bold text-warning">{{ $taskStatusDistribution['in_progress'] }}</div>
                        </div>
                        <div>
                            <div class="small text-muted">{{ __('Completed') }}</div>
                            <div class="fw-bold text-success">{{ $taskStatusDistribution['completed'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Bottom Secondary Feeds: Reminders & Notes --}}
    <div class="row g-4">
        
        {{-- Upcoming Reminders --}}
        <div class="col-xl-6">
            <div class="hq-card">
                <div class="hq-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="hq-badge-icon bg-warning-subtle text-warning"><i class="bi bi-bell"></i></span>
                        <h3 class="hq-title m-0">{{ __('Upcoming Reminders') }}</h3>
                    </div>
                    <a href="{{ route('reminders.index') }}" class="hq-link">{{ __('View All') }} <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="hq-card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($upcomingReminders as $reminder)
                        <div class="list-group-item d-flex align-items-center justify-content-between p-3">
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge rounded-circle p-2 {{ $reminder->date->isToday() ? 'bg-danger text-white' : 'bg-light text-dark border' }}">
                                    <i class="bi bi-bell-fill"></i>
                                </span>
                                <div>
                                    <div class="fw-medium">{{ $reminder->title }}</div>
                                    <div class="small text-muted">
                                        {{ $reminder->date->isToday() ? __('Today') : ($reminder->date->isPast() ? __('Overdue') : app_date($reminder->date, 'M d, Y')) }}
                                        @if($reminder->time)
                                            • {{ \Carbon\Carbon::parse($reminder->time)->format('H:i') }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <span class="badge {{ $reminder->priority === 'urgent' ? 'bg-danger' : ($reminder->priority === 'high' ? 'bg-warning text-dark' : 'bg-light text-secondary border') }}">
                                {{ __(ucfirst($reminder->priority ?? 'normal')) }}
                            </span>
                        </div>
                        @empty
                        <div class="text-center py-4 text-muted small">{{ __('No upcoming reminders') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Saved Notes --}}
        <div class="col-xl-6">
            <div class="hq-card">
                <div class="hq-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="hq-badge-icon bg-success-subtle text-success"><i class="bi bi-journal-text"></i></span>
                        <h3 class="hq-title m-0">{{ __('Recent Notes') }}</h3>
                    </div>
                    <a href="{{ route('notes.index') }}" class="hq-link">{{ __('View All') }} <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="hq-card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($recentNotes as $note)
                        <a href="{{ route('notes.show', $note) }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-3">
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-light text-primary border rounded-circle p-2">
                                    <i class="bi bi-sticky"></i>
                                </span>
                                <div>
                                    <div class="fw-medium text-dark">{{ $note->title }}</div>
                                    <div class="small text-muted">{{ app_diff_for_humans($note->created_at) }}</div>
                                </div>
                            </div>
                            <i class="bi {{ app()->getLocale() === 'fa' ? 'bi-chevron-left' : 'bi-chevron-right' }} text-muted small"></i>
                        </a>
                        @empty
                        <div class="text-center py-4 text-muted small">{{ __('No notes saved yet') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Routine Detail Modal (for big or tracked routines) --}}
    <div class="pl-modal" id="plRoutineModal" hidden>
        <div class="pl-modal-backdrop" data-modal-close></div>
        <div class="pl-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="plModalTitle">
            <div class="pl-modal-head">
                <div>
                    <div class="pl-modal-title" id="plModalTitle" data-modal-title></div>
                    <div class="pl-modal-sub" data-modal-sub></div>
                </div>
                <button type="button" class="pl-modal-x" data-modal-close aria-label="{{ __('Close') }}">&times;</button>
            </div>
            <div class="pl-modal-body" data-modal-body></div>
        </div>
    </div>

    <div id="plConfetti" aria-hidden="true"></div>
    <div id="plToast" role="status"></div>

    @include('dashboard._morning-checkin-modal')

</div>
@endsection

@push('styles')
<style>
    /* ─── Command Center Design System ─── */
    .welcome-heading { font-weight: 700; font-size: 1.5rem; letter-spacing: -0.02em; color: var(--gray-900); }
    
    /* Live Timer Banner */
    .live-timer-banner {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
        color: white;
        border-radius: var(--radius-lg);
        padding: 1rem 1.5rem;
        box-shadow: 0 10px 25px -5px rgba(49, 46, 129, 0.3);
    }
    .timer-pulse-dot {
        width: 12px; height: 12px; background-color: #22c55e;
        border-radius: 50%; display: inline-block;
        box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
        animation: pulse-green 1.5s infinite;
    }
    @keyframes pulse-green {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
    .timer-banner-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.7; }
    .timer-banner-task { font-size: 1.1rem; }
    .timer-live-clock { font-size: 1.35rem; font-weight: 700; color: #86efac; }

    /* Quick Action Hub */
    .quick-hub-bar {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 0.75rem;
    }
    .quick-hub-btn {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-md);
        padding: 0.75rem 0.5rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        color: var(--gray-800);
        font-weight: 500;
        font-size: 0.8125rem;
        transition: all 0.2s ease;
        box-shadow: var(--shadow-sm);
    }
    .quick-hub-btn:hover {
        transform: translateY(-2px);
        border-color: var(--primary-500);
        box-shadow: var(--shadow-md);
        color: var(--primary-700);
    }
    .quick-hub-icon {
        width: 36px; height: 36px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem;
    }
    .bg-purple-subtle { background-color: #f3e8ff; }
    .text-purple { color: #7e22ce; }

    /* HQ Cards */
    .hq-card {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .hq-card-header {
        padding: 0.875rem 1.25rem;
        border-bottom: 1px solid var(--gray-100);
        background: #fafbfc;
    }
    .hq-badge-icon {
        width: 28px; height: 28px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.875rem;
    }
    .hq-title { font-size: 0.9375rem; font-weight: 600; color: var(--gray-800); }
    .hq-link { font-size: 0.75rem; color: var(--primary-600); text-decoration: none; font-weight: 500; }
    .hq-link:hover { text-decoration: underline; color: var(--primary-700); }

    /* Focus Tasks & Routines Interactive Checkboxes */
    .task-inline-check { width: 18px; height: 18px; cursor: pointer; border-radius: 4px; }
    .focus-task-item { transition: all 0.15s ease; }
    .focus-task-item:hover { background-color: #f8fafc; }
    .focus-task-item.task-done .task-title-text { text-decoration: line-through; color: #94a3b8; }
    .project-tag { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
    .empty-icon-circle { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }

    /* ─── Planner Habit & Routine Row Styling ─── */
    .pl-task {
        display: flex; align-items: flex-start; gap: 10px; background: white;
        border: 1px solid #eceef1; border-radius: 10px; padding: 9px 12px;
        transition: all .15s ease;
    }
    .pl-task:hover { box-shadow: 0 2px 8px rgba(0,0,0,.05); border-color: #d8dae0; }
    .pl-task.is-done { background: #fafdfb; }
    .pl-task.is-done .pl-task-title { text-decoration: line-through; color: #94a3b8; }
    .pl-task-body { flex: 1; min-width: 0; }
    .pl-task-title {
        font-size: 13px; font-weight: 600; color: #1e293b; line-height: 1.45;
        display: flex; align-items: center; gap: 4px; flex-wrap: wrap; cursor: pointer;
    }
    .pl-task-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
    .pl-priority {
        font-size: 10.5px; font-weight: 700; padding: 1px 8px; border-radius: 20px;
        text-transform: uppercase; letter-spacing: .3px; display: inline-flex; align-items: center; gap: 4px;
    }
    .pl-due { font-size: 11px; color: #8a8f98; display: inline-flex; align-items: center; gap: 4px; }
    
    .pl-expand {
        margin-inline-start: auto; flex-shrink: 0; width: 24px; height: 24px;
        display: inline-flex; align-items: center; justify-content: center;
        border: none; background: transparent; color: #94a3b8; cursor: pointer;
        border-radius: 6px; font-size: 12px; transition: all .15s;
    }
    .pl-expand:hover { color: #7c3aed; background: #faf5ff; }
    .pl-expand i { transition: transform .15s; }
    .pl-expand.open i { transform: rotate(180deg); }

    /* ── Habit Ring ── */
    .pl-habit { position: relative; flex-shrink: 0; margin-top: 1px; cursor: pointer; display: inline-flex; }
    .pl-habit input { position: absolute; opacity: 0; width: 0; height: 0; }
    .routine-check-box {
        width: 19px; height: 19px; border: 2px solid #c4c9d4; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; color: transparent;
        font-size: 10px; transition: all .15s; background: white; position: absolute;
        top: 50%; left: 50%; transform: translate(-50%, -50%);
    }
    .pl-habit:hover .routine-check-box { border-color: #7c3aed; }
    .pl-habit input:checked + .habit-ring .routine-check-box,
    .pl-habit input:checked + .routine-check-box { background: #16a34a; border-color: #16a34a; color: white; }
    .habit-ring { position: relative; width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; }
    .habit-ring svg { width: 30px; height: 30px; transform: rotate(-90deg); }
    .habit-ring .ring-bg { fill: none; stroke: #eef0f2; stroke-width: 3.5; }
    .habit-ring .ring-fg { fill: none; stroke: #b9a5f5; stroke-width: 3.5; stroke-linecap: round; transition: stroke-dashoffset .4s; }
    .pl-task.is-done .habit-ring .ring-fg { stroke: #16a34a; }
    .pl-habit input:checked + .habit-ring .ring-fg { stroke: #16a34a; }
    .flame {
        font-size: 11px; font-weight: 700; color: #d97706; background: #fdf4de;
        border-radius: 20px; padding: 0 7px; margin-inline-start: 4px; white-space: nowrap; vertical-align: 1px;
    }
    .last7 { display: inline-flex; gap: 3px; align-items: center; }
    .last7 .sq { width: 7px; height: 7px; border-radius: 2.5px; display: inline-block; }
    .sq-done { background: #30a46c; }
    .sq-missed { background: #e3e5e9; }
    .sq-na { background: #f2f3f5; }
    .sq-future, .sq-today { background: transparent; box-shadow: inset 0 0 0 1px #e8eaef; }
    .sq-violated { background: #ef4444; }

    /* ── Routine steps (sub-items) ── */
    .pl-steps { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 7px; }
    .pl-step {
        display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px;
        border: 1px solid #e5e7eb; background: #fafbfc; color: #6b6f78; font-size: 11.5px; font-weight: 600;
        cursor: pointer; transition: all .12s;
    }
    .pl-step:hover { border-color: #c4b5fd; color: #7c3aed; }
    .pl-step i { font-size: 13px; color: #c1c4cc; transition: color .12s; }
    .pl-step.done { background: #e3f5ec; border-color: #a9dfbf; color: #29774b; }
    .pl-step.done i { color: #30a46c; }
    .pl-step-schedule {
        display: inline-flex; align-items: center; gap: 3px; margin-inline-start: 2px;
        font-size: 10px; font-weight: 700; text-transform: none; white-space: nowrap;
    }
    .pl-step-schedule i { font-size: 11px; color: inherit; }
    .steps-count {
        font-size: 10.5px; font-weight: 700; color: #8a8f98; background: #f2f3f5;
        border-radius: 20px; padding: 1px 7px; margin-inline-start: 4px; vertical-align: 1px;
    }
    .steps-count.all { color: #29774b; background: #e3f5ec; }
    .steps-count.bad { color: #b91c1c; background: #fee2e2; }

    /* ── Avoid habits ── */
    .pl-avoid-shield {
        width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 15px;
    }
    .pl-avoid-shield.ok { background: #dcfce7; color: #15803d; }
    .pl-avoid-shield.bad { background: #fee2e2; color: #b91c1c; }
    .pl-avoid-tag {
        font-size: 10.5px; font-weight: 700; color: #b91c1c; background: #fee2e2;
        border-radius: 20px; padding: 1px 7px; margin-inline-start: 4px; vertical-align: 1px; white-space: nowrap;
    }
    .pl-avoid-actions { display: flex; gap: 6px; margin-top: 7px; flex-wrap: wrap; }
    .pl-avoid-btn {
        display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px;
        font-size: 11.5px; font-weight: 700; cursor: pointer; transition: all .12s; border: 1px solid #e5e7eb;
        background: #fafbfc; color: #6b6f78;
    }
    .pl-avoid-btn.slip { border-color: #fca5a5; background: #fef2f2; color: #b91c1c; }
    .pl-avoid-btn.slip:hover { background: #fee2e2; }
    .pl-avoid-btn.note:hover { border-color: #c4b5fd; color: #7c3aed; }
    .pl-avoid-panel { margin-top: 7px; }
    .pl-avoid-panel form { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .pl-avoid-panel input, .pl-avoid-panel select {
        padding: 4px 9px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 12px; outline: none;
        background: white; color: #1f2328; max-width: 100%;
    }
    .pl-avoid-panel input:focus, .pl-avoid-panel select:focus { border-color: #c4b5fd; }
    .pl-avoid-panel input[name=quantity] { width: 64px; }
    .pl-avoid-panel input[name=note] { flex: 1; min-width: 140px; }
    .pl-avoid-panel button {
        padding: 4px 14px; border-radius: 8px; border: none; background: #b91c1c; color: white;
        font-size: 12px; font-weight: 700; cursor: pointer;
    }
    .pl-avoid-panel button:hover { background: #991b1b; }
    .pl-step.avoid { cursor: default; }
    .pl-step.avoid:hover { border-color: #e5e7eb; color: #6b6f78; }
    .pl-step.avoid.violated { background: #fee2e2; border-color: #fca5a5; color: #b91c1c; }
    .pl-step.avoid.violated i { color: #dc2626; }
    .pl-step-slipcount { font-size: 10px; font-weight: 800; color: #b91c1c; }
    .pl-step-slipbtn {
        margin-inline-start: 2px; padding: 2px 10px; border-radius: 14px; border: 1px solid #fca5a5;
        background: white; color: #b91c1c; font-size: 10.5px; font-weight: 700; cursor: pointer;
    }
    .pl-step-slipbtn:hover { background: #fee2e2; }

    /* ── Metric logging ── */
    .pl-details { display: none; margin-top: 6px; padding-top: 6px; border-top: 1px dashed #eef0f3; }
    .pl-details.open { display: block; }
    .pl-log { display: flex; align-items: center; gap: 6px; margin-top: 7px; flex-wrap: wrap; }
    .pl-log input { width: 110px; padding: 4px 9px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 12px; outline: none; }
    .pl-log input:focus { border-color: #c4b5fd; }
    .pl-time-field {
        display: inline-flex; align-items: center; gap: 7px; padding: 0 11px;
        border: 1.5px solid #ddd6fe; border-radius: 11px;
        background: linear-gradient(180deg, #fdfcff 0%, #f6f3ff 100%);
        transition: border-color .15s, box-shadow .15s;
    }
    .pl-time-field > i { color: #7c3aed; font-size: 13px; }
    .pl-time-field:hover { border-color: #c4b5fd; }
    .pl-time-field:focus-within { border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,.14); }
    .pl-time-field input[type="time"] {
        border: none; background: transparent; outline: none; box-shadow: none;
        width: 90px; padding: 6px 0; font-size: 13.5px; font-weight: 700; color: #1a1d23;
        letter-spacing: .6px; font-variant-numeric: tabular-nums; color-scheme: light;
    }
    .pl-log button, .pl-logset button {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 4px 12px; border-radius: 8px; border: 1px solid #c4b5fd; background: #faf5ff;
        color: #7c3aed; font-size: 11.5px; font-weight: 700; cursor: pointer;
    }
    .pl-log button:hover, .pl-logset button:hover { background: #ede9fe; }
    .pl-log button:disabled, .pl-logset button:disabled { opacity: .5; cursor: wait; }
    .pl-log-saved {
        font-size: 11px; font-weight: 700; color: #15803d; background: #e9f9f0;
        border: 1px solid #bbf7d0; border-radius: 20px; padding: 2px 10px; font-variant-numeric: tabular-nums;
    }
    .pl-logsets { display: flex; flex-direction: column; gap: 6px; margin-top: 7px; }
    .pl-logset { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; background: #fafbfc; border: 1px solid #eef0f3; border-radius: 8px; padding: 5px 8px; }
    .pl-logset-name { font-size: 11.5px; font-weight: 700; color: #3d4149; flex: 1; min-width: 90px; }
    .pl-logset input { width: 64px; padding: 3px 7px; border: 1px solid #e5e7eb; border-radius: 7px; font-size: 11.5px; outline: none; }
    .pl-logset input:focus { border-color: #c4b5fd; }
    .pl-logset input.has-val { border-color: #a9dfbf; background: #f3fbf6; }

    /* ── Skipped / settled states + ✗ buttons (parity with planner) ── */
    .pl-routine-skip {
        width: 21px; height: 21px; flex-shrink: 0; display: inline-grid; place-items: center;
        border: 1px solid #e5e7eb; background: #fafbfc; color: #c1c4cc; border-radius: 50%;
        font-size: 9px; line-height: 1; cursor: pointer; padding: 0; transition: all .12s;
    }
    .pl-routine-skip:hover { border-color: #fca5a5; background: #fef2f2; color: #dc2626; }
    .pl-routine-skip.active { background: #ef4444; border-color: #ef4444; color: #fff; }
    .pl-routine-skip:disabled { opacity: .5; cursor: wait; }
    .pl-routine-skip:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(239,68,68,.25); }
    .pl-skip-tag {
        font-size: 10.5px; font-weight: 700; color: #b91c1c; background: #fee2e2;
        border-radius: 20px; padding: 1px 8px; display: inline-flex; align-items: center; gap: 4px;
        text-transform: none; white-space: nowrap;
    }
    .pl-task.is-skipped .pl-task-title { text-decoration: line-through; color: #94a3b8; }
    .pl-task.is-skipped .pl-task-meta { opacity: .8; }
    .pl-task.is-skipped .habit-ring .ring-fg { stroke: #ef4444; }
    .pl-task.is-skipped .habit-ring .ring-bg { stroke: #fee2e2; }
    .pl-task.is-skipped .routine-check-box { border-color: #fca5a5; color: #dc2626; }
    .pl-task.is-settled { background: #fafbfc; }
    .pl-task.is-settled .pl-task-title { color: #475569; }
    .pl-settled-tag {
        font-size: 10.5px; font-weight: 800; color: #15803d; background: #e9f9f0;
        border: 1px solid #bbf7d0; border-radius: 20px; padding: 1px 9px; margin-inline-start: 6px;
        display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; vertical-align: 1px;
    }
    .pl-habit.is-locked { cursor: not-allowed; }
    .pl-habit.is-locked input:disabled + .habit-ring,
    .pl-habit.is-locked input:disabled + .routine-check-box { cursor: not-allowed; }
    .pl-routine-static {
        flex-shrink: 0; margin-top: 1px; width: 19px; height: 19px; display: flex;
        align-items: center; justify-content: center; color: #c4c9d4; font-size: 12px;
    }

    /* ── Per-step ✗ chip + partial-fail badges ── */
    .pl-step-wrap { display: inline-flex; align-items: center; }
    .pl-step-wrap .pl-step-skip {
        width: 18px; height: 18px; flex-shrink: 0; display: inline-grid; place-items: center;
        border: 1px solid #e5e7eb; background: #fff; color: #c1c4cc; border-radius: 50%;
        font-size: 8px; line-height: 1; cursor: pointer; padding: 0; margin-inline-start: -7px;
        opacity: 0; transition: opacity .12s, all .12s; position: relative; z-index: 1;
    }
    .pl-step-wrap:hover .pl-step-skip, .pl-step-wrap:focus-within .pl-step-skip,
    .pl-step-wrap .pl-step-skip.active { opacity: 1; }
    .pl-step-wrap .pl-step-skip:hover { border-color: #fca5a5; background: #fef2f2; color: #dc2626; }
    .pl-step-wrap .pl-step-skip.active { background: #ef4444; border-color: #ef4444; color: #fff; opacity: 1; }
    .pl-step-wrap .pl-step-skip:disabled { opacity: .5; cursor: wait; }
    .pl-step-wrap.is-skipped .pl-step, .pl-step.is-skipped { background: #fef2f2; border-color: #fca5a5; color: #b91c1c; }
    .pl-step-wrap.is-skipped .pl-step i, .pl-step.is-skipped i { color: #dc2626; }
    .steps-skipped {
        font-size: 10.5px; font-weight: 800; color: #b91c1c; background: #fee2e2;
        border-radius: 20px; padding: 1px 7px; margin-inline-start: 6px; vertical-align: 1px; white-space: nowrap;
    }
    .pl-skip-rest {
        display: inline-flex; align-items: center; gap: 5px; margin-top: 7px;
        padding: 3px 12px; border-radius: 20px; border: 1px dashed #fca5a5;
        background: transparent; color: #b91c1c; font-size: 11px; font-weight: 700; cursor: pointer;
        transition: all .12s;
    }
    .pl-skip-rest:hover { background: #fef2f2; border-style: solid; }
    .pl-skip-rest:disabled { opacity: .5; cursor: wait; }
    .pl-modal-body .pl-step-wrap { width: 100%; display: flex; align-items: stretch; gap: 8px; }
    .pl-modal-body .pl-step-wrap .pl-step { flex: 1; min-width: 0; }
    .pl-modal-body .pl-step-wrap .pl-step-skip {
        opacity: 1; margin-inline-start: 0; width: 32px; height: auto; min-height: 32px;
        border-radius: 10px; font-size: 11px; align-self: stretch;
    }

    /* ── Avoid habit form rows (overrides the generic red panel button for "Now") ── */
    .pl-avoid-grid { display: flex; gap: 6px; flex-wrap: wrap; width: 100%; align-items: center; }
    .pl-avoid-grid input, .pl-avoid-grid select {
        flex: 1; min-width: 110px; padding: 7px 9px; border: 1px solid #d3d5db; border-radius: 8px;
        font-size: 12.5px; background: #fff; color: #1f2328; outline: none;
    }
    .pl-avoid-grid input:focus, .pl-avoid-grid select:focus { border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124,58,237,.12); }
    .pl-avoid-grid input[name=quantity] { flex: 0 0 76px; min-width: 76px; }
    .pl-avoid-grid select[name=mood] { flex: 0 0 112px; min-width: 112px; }
    button.pl-avoid-now {
        flex: 0 0 auto; padding: 7px 12px; border-radius: 8px; border: 1px solid #e2e8f0;
        background: #fff; color: #475569; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap;
    }
    button.pl-avoid-now:hover { border-color: #c4b5fd; color: #7c3aed; background: #faf5ff; }
    .pl-avoid-history-btn {
        padding: 4px 10px; border-radius: 16px; border: 1px solid #e2e8f0; background: #fff;
        color: #475569; font-size: 11px; font-weight: 700; cursor: pointer;
    }
    .pl-avoid-history-btn:hover { border-color: #c4b5fd; color: #7c3aed; }
    .pl-avoid-last { font-size: 11px; color: #8a8f98; }
    .pl-avoid-submit { padding: 8px 16px; border-radius: 8px; border: 1px solid #b91c1c; background: #b91c1c; color: #fff; font-weight: 800; font-size: 13px; cursor: pointer; }
    .pl-avoid-submit:hover { background: #991b1b; }
    [data-note-panel] .pl-avoid-submit { background: #0369a1; border-color: #0369a1; }
    [data-note-panel] .pl-avoid-submit:hover { background: #075985; }

    /* ── Routine detail modal ── */
    .pl-modal { position: fixed; inset: 0; z-index: 1090; display: flex; align-items: center; justify-content: center; padding: 16px; }
    .pl-modal[hidden] { display: none; }
    .pl-modal-backdrop { position: absolute; inset: 0; background: rgba(17,20,26,.5); backdrop-filter: blur(4px); }
    .pl-modal-dialog {
        position: relative; background: #fff; border-radius: 16px; width: min(560px, 96vw); max-height: 88vh;
        display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,.25); overflow: hidden;
    }
    .pl-modal-head { display: flex; align-items: flex-start; gap: 10px; padding: 16px 18px 12px; border-bottom: 1px solid #eef0f3; }
    .pl-modal-title { font-size: 15px; font-weight: 800; color: #1a1d23; }
    .pl-modal-sub { font-size: 12px; color: #8a8f98; margin-top: 2px; }
    .pl-modal-x {
        margin-inline-start: auto; border: none; background: #f2f3f5; color: #6b7385; width: 30px; height: 30px;
        border-radius: 8px; font-size: 17px; line-height: 1; cursor: pointer; flex-shrink: 0;
    }
    .pl-modal-x:hover { background: #e6e8ec; color: #1a1d23; }
    .pl-modal-body { padding: 14px 18px; overflow-y: auto; }
    .pl-modal-body .pl-details { display: block; border-top: none; padding-top: 0; margin-top: 0; }
    .pl-modal-body .pl-logsets { gap: 9px; }
    .pl-modal-body .pl-logset { padding: 9px 11px; }
    .pl-modal-body .pl-logset-name { font-size: 12.5px; }
    .pl-modal-body .pl-logset input { width: 76px; padding: 5px 9px; font-size: 12.5px; }
    .pl-modal-body .pl-steps { gap: 7px; }
    .pl-modal-body .pl-step { font-size: 12px; padding: 4px 12px; }
    .pl-modal-body .pl-time-field { width: 100%; justify-content: flex-start; }
    .pl-modal-body .pl-time-field input[type="time"] { flex: 1; width: auto; font-size: 15px; padding: 8px 0; }
    body.pl-modal-open { overflow: hidden; }

    /* Toast & Confetti */
    #plToast {
        position: fixed; bottom: 24px; inset-inline-end: 24px; background: #0f172a; color: #fff;
        padding: 10px 18px; border-radius: 12px; font-size: 13px; font-weight: 600; box-shadow: 0 10px 25px rgba(0,0,0,.2);
        z-index: 1100; opacity: 0; transform: translateY(12px); transition: all .25s ease; pointer-events: none;
    }
    #plToast.show { opacity: 1; transform: translateY(0); }
    #plConfetti { position: fixed; inset: 0; pointer-events: none; z-index: 1080; overflow: hidden; }
    #plConfetti i { position: absolute; top: -12px; width: 8px; height: 14px; border-radius: 2px; opacity: 0; animation: plFall 1.4s ease-in forwards; }
    @keyframes plFall {
        0% { opacity: 1; transform: translateY(0) rotate(0); }
        100% { opacity: 0; transform: translateY(70vh) rotate(540deg); }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    let PL_CSRF = '{{ csrf_token() }}';
    const DB_CSRF = '{{ csrf_token() }}';
    const plRawFetch = window.fetch.bind(window);

    /* Wrapper sending CSRF token with automatic 419 session refresh */
    async function plFetch(url, init = {}) {
        init.headers = Object.assign({ 'X-CSRF-TOKEN': PL_CSRF }, init.headers || {});
        let res = await plRawFetch(url, init);
        if (res.status === 419) {
            const fresh = await plRefreshCsrf();
            if (fresh) {
                init.headers['X-CSRF-TOKEN'] = fresh;
                res = await plRawFetch(url, init);
            }
            if (res.status === 419 || res.status === 401) {
                window.location.reload();
                return new Response(null, { status: 419 });
            }
        }
        return res;
    }

    async function plRefreshCsrf() {
        try {
            const page = await plRawFetch(window.location.href, {
                headers: { 'Accept': 'text/html' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const m = (await page.text()).match(/<meta\s+name=["']csrf-token["']\s+content=["']([^"']+)["']/i);
            if (m) {
                PL_CSRF = m[1];
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.content = m[1];
                return m[1];
            }
        } catch (e) { }
        return null;
    }

    function plShowToast(msg) {
        const toast = document.getElementById('plToast');
        if (!toast) return;
        toast.textContent = msg;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2200);
    }

    function maybeCelebrate() {
        const c = document.getElementById('plConfetti');
        if (!c) return;
        c.innerHTML = '';
        const colors = ['#6366f1', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6'];
        for (let i = 0; i < 28; i++) {
            const el = document.createElement('i');
            el.style.left = Math.random() * 100 + 'vw';
            el.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            el.style.animationDelay = (Math.random() * 0.4) + 's';
            c.appendChild(el);
        }
        setTimeout(() => { if (c) c.innerHTML = ''; }, 2000);
    }

    // Live ticking timer
    const liveTimerEl = document.getElementById('dashboardLiveTimer');
    if (liveTimerEl && liveTimerEl.dataset.start) {
        const startTime = new Date(liveTimerEl.dataset.start).getTime();
        setInterval(() => {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const hrs = String(Math.floor(elapsed / 3600)).padStart(2, '0');
            const mins = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
            const secs = String(elapsed % 60).padStart(2, '0');
            liveTimerEl.textContent = `${hrs}:${mins}:${secs}`;
        }, 1000);
    }

    // Toggle Task Status directly from Dashboard
    async function toggleDashTaskStatus(cb) {
        const taskId = cb.dataset.taskId;
        const url = cb.dataset.url;
        const isCompleted = cb.checked;
        const newStatus = isCompleted ? 'completed' : 'to_do';
        const row = document.getElementById('dash-task-' + taskId);

        try {
            const res = await plFetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ status: newStatus })
            });
            if (row) {
                row.classList.toggle('task-done', isCompleted);
            }
        } catch (e) {
            cb.checked = !cb.checked;
            console.error('Failed to update task status', e);
        }
    }

    // Start timer for a specific task
    async function startTimerForTask(taskId, projectId, taskTitle) {
        try {
            const res = await plFetch('{{ route("time.start") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ task_id: taskId, project_id: projectId })
            });
            if (res.ok) {
                window.location.reload();
            }
        } catch (e) {
            console.error('Error starting timer', e);
        }
    }

    /* ─── Routine & Habit Interactions (Full Planner Logic) ─── */
    window.dbToggleRoutine = toggleRoutine;
    function dbToggleRoutine(cb) {
        return toggleRoutine(cb);
    }

    async function toggleRoutine(cb) {
        const url = cb.dataset.url;
        const id  = cb.dataset.id;
        const date = cb.dataset.date;
        cb.disabled = true;
        try {
            const res = await plFetch(url + (url.includes('?') ? '&' : '?') + 'date=' + encodeURIComponent(date), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            
            applyRoutineToggle(id, date, !!json.completed);
            if (json.items && json.items.length) syncStepButtons(id, date, !!json.completed);
            if (json.completed) {
                setStreak(id, json.streak ?? null);
                plShowToast(@json(__('Done ✓')));
                maybeCelebrate();
            } else {
                setStreak(id, json.streak ?? 0, true);
            }
            refreshRoutineCounters();
        } catch (e) {
            cb.checked = !cb.checked;
            console.error('[Dashboard] routine toggle failed', e);
        } finally {
            cb.disabled = false;
        }
    }

    function applyRoutineToggle(id, date, completed) {
        document.querySelectorAll('[data-routine-item][data-id="' + id + '"]').forEach(row => {
            row.classList.toggle('is-done', completed);
            row.dataset.completed = completed ? '1' : '0';
            const box = row.querySelector('input[type="checkbox"]');
            if (box) box.checked = completed;
        });
    }

    function syncStepButtons(id, date, completed) {
        document.querySelectorAll('[data-step-item][data-routine="' + id + '"]').forEach(btn => {
            btn.classList.toggle('done', completed);
            const i = btn.querySelector('i');
            if (i) i.className = 'bi ' + (completed ? 'bi-check-circle-fill' : 'bi-circle');
        });
        refreshStepCounts();
    }

    function refreshStepCounts() {
        document.querySelectorAll('[data-routine-item]').forEach(row => {
            const steps = row.querySelectorAll('[data-step-item]');
            if (!steps.length) return;
            const done = [...steps].filter(s => s.classList.contains('done')).length;
            const badge = row.querySelector('.steps-count');
            if (badge) {
                badge.textContent = done + '/' + steps.length;
                badge.classList.toggle('all', done === steps.length);
            }
        });
    }

    function setStreak(id, count, isUndo = false) {
        document.querySelectorAll('[data-routine-item][data-id="' + id + '"]').forEach(row => {
            let flame = row.querySelector('.flame');
            if (count && count > 0) {
                if (!flame) {
                    flame = document.createElement('span');
                    flame.className = 'flame';
                    const title = row.querySelector('.pl-task-title');
                    if (title) title.appendChild(flame);
                }
                if (flame) {
                    const isAvoid = row.querySelector('.pl-avoid-shield') !== null;
                    flame.textContent = (isAvoid ? '🛡️' : '🔥') + count;
                    flame.title = count + ' in a row';
                }
            } else if (flame && isUndo) {
                flame.remove();
            }
        });
    }

    async function toggleCheckItem(btn) {
        btn.disabled = true;
        try {
            const res = await plFetch(btn.dataset.url + '?date=' + encodeURIComponent(btn.dataset.date), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
            });
            if (res.status === 422) {
                expandRoutineDetails(btn);
                return;
            }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();

            btn.classList.toggle('done', !!json.completed);
            const i = btn.querySelector('i');
            if (i) i.className = 'bi ' + (json.completed ? 'bi-check-circle-fill' : 'bi-circle');
            refreshStepCounts();

            if (json.routine_completed !== undefined) {
                applyRoutineToggle(json.routine_id, btn.dataset.date, !!json.routine_completed);
                if (json.routine_completed && json.streak != null) setStreak(json.routine_id, json.streak);
                refreshRoutineCounters();
                if (json.routine_completed) {
                    plShowToast(@json(__('Routine Completed ✓')));
                    maybeCelebrate();
                }
            }
        } catch (e) {
            console.error('[Dashboard] step toggle failed', e);
        } finally {
            btn.disabled = false;
        }
    }

    function refreshRoutineCounters() {
        const items = document.querySelectorAll('[data-routine-item][data-count="1"]');
        let done = 0;
        items.forEach(el => {
            if (el.dataset.completed === '1') done++;
        });
        const badge = document.getElementById('dbRoutineCount');
        if (badge && items.length) badge.textContent = done + '/' + items.length;
        
        const heroCounter = document.getElementById('headerRoutineCount');
        if (heroCounter && items.length) heroCounter.textContent = done + '/' + items.length;
    }

    /* ── Metric logging ── */
    function fmtLogValue(kind, v) {
        if (v === null || v === undefined || v === '') return '';
        if (kind !== 'time') return v;
        const m = ((Math.round(Number(v)) % 1440) + 1440) % 1440;
        return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
    }

    async function logRoutineValue(btn) {
        const box = btn.closest('[data-log-value]');
        const input = box.querySelector('input');
        const isTime = input.type === 'time';
        let value;
        if (isTime) {
            if (!input.value) { input.focus(); return; }
            const [h, m] = input.value.split(':').map(Number);
            value = h * 60 + (m || 0);
        } else {
            value = parseFloat(input.value);
            if (isNaN(value)) { input.focus(); return; }
        }
        btn.disabled = true;
        try {
            const res = await plFetch(box.dataset.url + '?date=' + encodeURIComponent(box.dataset.date), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ value }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            let saved = box.querySelector('.pl-log-saved');
            if (!saved) { saved = document.createElement('span'); saved.className = 'pl-log-saved'; box.appendChild(saved); }
            saved.textContent = '✓ ' + fmtLogValue(box.dataset.kind, json.values?.value ?? value);
            if (json.routine_completed) {
                applyRoutineToggle(box.dataset.routine, box.dataset.date, true);
                if (json.streak != null) setStreak(box.dataset.routine, json.streak);
                refreshRoutineCounters();
                maybeCelebrate();
            }
            if (box.closest('#plRoutineModal')) {
                closeRoutineModal();
            }
            plShowToast(@json(__('Logged ✓')));
        } catch (e) {
            console.error('[Dashboard] log value failed', e);
        } finally {
            btn.disabled = false;
        }
    }

    async function logRoutineSets(btn) {
        const box = btn.closest('[data-log-sets]');
        const sets = {};
        box.querySelectorAll('input[data-set]').forEach(inp => {
            if (inp.value !== '' && !isNaN(parseFloat(inp.value))) sets[inp.dataset.set] = parseFloat(inp.value);
        });
        if (!Object.keys(sets).length) { box.querySelector('input[data-set]')?.focus(); return; }
        btn.disabled = true;
        try {
            const res = await plFetch(box.dataset.url + '?date=' + encodeURIComponent(box.dataset.date), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ item_id: box.dataset.item, sets }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            const saved = json.values?.[box.dataset.item] || {};
            box.querySelectorAll('input[data-set]').forEach(inp => {
                inp.classList.toggle('has-val', saved[inp.dataset.set] !== undefined);
            });
            if (json.steps_done) {
                Object.entries(json.steps_done).forEach(([itemId, done]) => {
                    const chip = document.querySelector(`[data-step-item][data-id="${itemId}"]`);
                    if (chip) {
                        chip.classList.toggle('done', done);
                        const i = chip.querySelector('i');
                        if (i) i.className = 'bi ' + (done ? 'bi-check-circle-fill' : 'bi-circle');
                    }
                });
            }
            if (json.routine_completed) {
                applyRoutineToggle(box.dataset.routine, box.dataset.date, true);
                refreshRoutineCounters();
                maybeCelebrate();
            }
            if (box.closest('#plRoutineModal')) {
                closeRoutineModal();
            }
            plShowToast(@json(__('Logged ✓')));
        } catch (e) {
            console.error('[Dashboard] log sets failed', e);
        } finally {
            btn.disabled = false;
        }
    }

    /* ── Avoid habits: slip / craving / note ── */
    document.addEventListener('click', function (e) {
        const slipBtn = e.target.closest('[data-avoid-slip]');
        if (slipBtn) {
            const row = slipBtn.closest('[data-routine-item]');
            const panel = row ? row.querySelector('[data-slip-panel]') : null;
            if (panel) panel.hidden = !panel.hidden;
            return;
        }
        const noteBtn = e.target.closest('[data-avoid-note]');
        if (noteBtn) {
            const row = noteBtn.closest('[data-routine-item]');
            const panel = row ? row.querySelector('[data-note-panel]') : null;
            if (panel) panel.hidden = !panel.hidden;
            return;
        }
        const stepSlipBtn = e.target.closest('[data-avoid-step-slip]');
        if (stepSlipBtn) {
            const wrap = stepSlipBtn.closest('.pl-avoid-steps') || stepSlipBtn.closest('[data-routine-item]');
            const panels = wrap ? [...wrap.querySelectorAll('[data-step-slip-panel]')] : [];
            const stepRow = stepSlipBtn.closest('[data-step-item]');
            const idx = stepRow ? [...wrap.querySelectorAll('[data-step-item]')].indexOf(stepRow) : -1;
            const panel = idx >= 0 ? panels[idx] : null;
            if (panel) panel.hidden = !panel.hidden;
            return;
        }
    });

    function avoidPayload(form) {
        const data = { date: form.dataset.date };
        form.querySelectorAll('input, select').forEach(el => {
            if (!el.name || el.value === '') return;
            data[el.name] = el.type === 'number' ? Number(el.value) : el.value;
        });
        return data;
    }

    async function submitRoutineSlip(form) {
        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        try {
            const res = await plFetch(form.dataset.slipUrl + '?date=' + encodeURIComponent(form.dataset.date), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(avoidPayload(form)),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            await res.json();
            plShowToast(@json(__('Slip logged')));
            window.location.reload();
        } catch (e) {
            console.error('[Dashboard] slip failed', e);
            plShowToast(@json(__('Could not log slip')));
        } finally {
            btn.disabled = false;
        }
        return false;
    }

    async function submitStepSlip(form) {
        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        try {
            const res = await plFetch(form.dataset.slipUrl + '?date=' + encodeURIComponent(form.dataset.date), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(avoidPayload(form)),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            await res.json();
            plShowToast(@json(__('Slip logged')));
            window.location.reload();
        } catch (e) {
            console.error('[Dashboard] step slip failed', e);
            plShowToast(@json(__('Could not log step slip')));
        } finally {
            btn.disabled = false;
        }
        return false;
    }

    async function submitRoutineNote(form) {
        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        try {
            const res = await plFetch(form.dataset.noteUrl + '?date=' + encodeURIComponent(form.dataset.date), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(avoidPayload(form)),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            await res.json();
            plShowToast(@json(__('Saved ✓')));
            const panel = form.closest('[data-note-panel]');
            if (panel) panel.hidden = true;
            form.reset();
        } catch (e) {
            console.error('[Dashboard] note failed', e);
            plShowToast(@json(__('Could not save')));
        } finally {
            btn.disabled = false;
        }
        return false;
    }

    /* ── Details Expansion & Modal ── */
    function toggleRoutineDetails(btn) {
        const row = btn.closest('[data-routine-item]');
        if (row) toggleRoutineDetailsRow(row);
    }

    function toggleRoutineDetailsRow(row) {
        const details = row.querySelector('[data-details]');
        if (!details) return;
        const open = details.classList.toggle('open');
        const chev = row.querySelector('.pl-expand');
        if (chev) {
            chev.classList.toggle('open', open);
            chev.title = open ? 'Hide details' : 'Show details';
        }
    }

    function expandRoutineDetails(el) {
        const row = el.closest('[data-routine-item]');
        const details = row ? row.querySelector('[data-details]') : null;
        if (details && !details.classList.contains('open')) {
            toggleRoutineDetailsRow(row);
        }
    }

    const plModal = document.getElementById('plRoutineModal');
    const plModalBody = plModal ? plModal.querySelector('[data-modal-body]') : null;
    let plModalState = null;

    function openRoutineModal(el) {
        const row = el.closest && el.closest('[data-routine-item]') ? el.closest('[data-routine-item]') : el;
        const details = row.querySelector('[data-details]');
        if (!details || !plModal) return;
        if (plModalState) closeRoutineModal();

        const placeholder = document.createComment('pl-details');
        details.parentNode.insertBefore(placeholder, details);
        details.classList.add('open');
        plModalBody.appendChild(details);

        plModal.querySelector('[data-modal-title]').textContent = row.dataset.modalTitle || '';
        plModal.querySelector('[data-modal-sub]').textContent = row.dataset.modalSub || '';
        plModal.hidden = false;
        document.body.classList.add('pl-modal-open');
        plModalState = { details, placeholder };

        const firstInput = plModalBody.querySelector('input');
        if (firstInput) setTimeout(() => firstInput.focus(), 60);
    }

    function closeRoutineModal() {
        if (!plModalState) return;
        const { details, placeholder } = plModalState;
        details.classList.remove('open');
        if (placeholder.parentNode) placeholder.parentNode.insertBefore(details, placeholder);
        placeholder.remove();
        plModal.hidden = true;
        document.body.classList.remove('pl-modal-open');
        plModalState = null;
        refreshStepCounts();
    }

    if (plModal) {
        plModal.querySelectorAll('[data-modal-close]').forEach(b => b.addEventListener('click', closeRoutineModal));
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeRoutineModal();
        });
    }

    // Enter key in routine log input triggers submit
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const inp = e.target instanceof Element ? e.target.closest('.pl-log input, .pl-logset input') : null;
        if (!inp) return;
        e.preventDefault();
        const box = inp.closest('[data-log-value], [data-log-sets]');
        const btn = box ? box.querySelector('button') : null;
        if (btn) btn.click();
    });

    // Charts Initialization
    document.addEventListener('DOMContentLoaded', function() {
        // Productivity Line Chart
        const chartCanvas = document.getElementById('productivityChart');
        if (chartCanvas) {
            const ctx = chartCanvas.getContext('2d');
            fetch('{{ route("dashboard.productivity-data") }}')
                .then(r => r.json())
                .then(data => {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Tasks Completed',
                                data: data.data,
                                borderColor: '#6366f1',
                                backgroundColor: 'rgba(99, 102, 241, 0.08)',
                                borderWidth: 2.5,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#6366f1',
                                pointRadius: 3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                })
                .catch(console.error);
        }

        // Status Doughnut Chart
        const statusChartCanvas = document.getElementById('taskStatusChart');
        if (statusChartCanvas) {
            const statusCtx = statusChartCanvas.getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['To Do', 'In Progress', 'Completed'],
                    datasets: [{
                        data: [
                            {{ $taskStatusDistribution['to_do'] }},
                            {{ $taskStatusDistribution['in_progress'] }},
                            {{ $taskStatusDistribution['completed'] }}
                        ],
                        backgroundColor: ['#6366f1', '#f59e0b', '#10b981'],
                        borderWidth: 0,
                        cutout: '72%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: false } }
                }
            });
        }
    });
</script>
@endpush
