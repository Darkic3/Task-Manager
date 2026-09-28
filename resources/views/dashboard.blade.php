@extends('layouts.app')

@section('title', 'Daily Command Center | Task Manager')

@section('page-title', 'Daily Command Center')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-3">

    {{-- Live Active Timer Widget (if running) --}}
    @if($activeTimeEntry)
    <div class="live-timer-banner mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="timer-pulse-dot"></span>
                <div>
                    <div class="timer-banner-label">Active Time Tracking</div>
                    <div class="timer-banner-task font-monospace fw-bold">
                        {{ $activeTimeEntry->task->title ?? ($activeTimeEntry->description ?: 'Untracked Activity') }}
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
                        <i class="bi bi-stop-fill"></i> Stop
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
                Good {{ now()->format('A') === 'AM' ? 'morning' : (now()->format('H') < 18 ? 'afternoon' : 'evening') }}, {{ Auth::user()->name }} 👋
            </h2>
            <div class="text-muted small d-flex align-items-center gap-3 flex-wrap">
                <span><i class="bi bi-calendar3 me-1 text-primary"></i> {{ now()->format('l, F j, Y') }}</span>
                <span>•</span>
                <span><i class="bi bi-check-circle me-1 text-success"></i> <strong>{{ $tasksCompletedToday }}</strong> tasks done today</span>
                <span>•</span>
                <span><i class="bi bi-arrow-repeat me-1 text-warning"></i> <strong>{{ $routineDoneCount }}/{{ $routineTotalCount }}</strong> routines checked</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('planner.index') }}" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-calendar-check"></i> Open My Day
            </a>
        </div>
    </div>

    {{-- Quick Action Hub --}}
    <div class="quick-hub-bar mb-4">
        <a href="{{ route('tasks.create') }}" class="quick-hub-btn">
            <span class="quick-hub-icon bg-primary-subtle text-primary"><i class="bi bi-plus-lg"></i></span>
            <span>New Task</span>
        </a>
        <a href="{{ route('workouts.plans.index') }}" class="quick-hub-btn">
            <span class="quick-hub-icon bg-danger-subtle text-danger"><i class="bi bi-activity"></i></span>
            <span>Workouts</span>
        </a>
        <a href="{{ route('planner.index') }}" class="quick-hub-btn">
            <span class="quick-hub-icon bg-warning-subtle text-warning"><i class="bi bi-calendar-day"></i></span>
            <span>Daily Planner</span>
        </a>
        <a href="{{ route('notes.create') }}" class="quick-hub-btn">
            <span class="quick-hub-icon bg-success-subtle text-success"><i class="bi bi-journal-plus"></i></span>
            <span>Quick Note</span>
        </a>
        <a href="{{ route('reminders.create') }}" class="quick-hub-btn">
            <span class="quick-hub-icon bg-info-subtle text-info"><i class="bi bi-bell-fill"></i></span>
            <span>Set Reminder</span>
        </a>
        <a href="{{ route('ai.index') }}" class="quick-hub-btn">
            <span class="quick-hub-icon bg-purple-subtle text-purple"><i class="bi bi-stars"></i></span>
            <span>Ask Lina</span>
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
                        <h3 class="hq-title m-0">Today's Focus</h3>
                    </div>
                    <a href="{{ route('tasks.index') }}" class="hq-link">View All <i class="bi bi-arrow-right"></i></a>
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
                                        <i class="bi bi-clock"></i> {{ \Carbon\Carbon::parse($task->due_date)->isToday() ? 'Today' : \Carbon\Carbon::parse($task->due_date)->format('M d') }}
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
                                <button type="button" class="btn btn-sm btn-light border p-1 text-muted hover-primary" title="Start timer for this task" onclick="startTimerForTask({{ $task->id }}, {{ $task->project_id ?? 'null' }}, '{{ addslashes($task->title) }}')">
                                    <i class="bi bi-play-fill text-success fs-6"></i>
                                </button>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-5 px-3">
                            <div class="empty-icon-circle mx-auto mb-3 bg-success-subtle text-success">
                                <i class="bi bi-check2-all fs-4"></i>
                            </div>
                            <h6 class="fw-bold mb-1">All Clear for Today!</h6>
                            <p class="text-muted small mb-3">No urgent or high priority tasks remaining.</p>
                            <a href="{{ route('tasks.create') }}" class="btn btn-sm btn-outline-primary">+ Add New Task</a>
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
                        <h3 class="hq-title m-0">Today's Workout</h3>
                    </div>
                    <a href="{{ route('workouts.plans.index') }}" class="hq-link">Workout Hub <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="hq-card-body p-3">
                    @if($todayWorkoutDay)
                        <div class="workout-plan-preview mb-3 p-3 rounded-3 bg-light border">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-dark text-white text-uppercase" style="font-size:10px;">{{ $activeWorkoutPlan->title ?? 'Current Plan' }}</span>
                                    <h5 class="fw-bold mt-1 mb-0">{{ $todayWorkoutDay->title ?: ucfirst($todayWorkoutDay->weekday) }}</h5>
                                </div>
                                <span class="badge {{ $todayWorkoutDay->isTraining() ? 'bg-primary text-white' : 'bg-secondary text-white' }}">
                                    {{ $todayWorkoutDay->isTraining() ? 'Training Day' : 'Rest Day' }}
                                </span>
                            </div>

                            @if($todayWorkoutDay->isTraining())
                                <div class="exercise-chip-list d-flex flex-wrap gap-1 my-3">
                                    @forelse($todayWorkoutDay->exercises->take(5) as $we)
                                    <span class="badge bg-white text-dark border fw-normal" style="font-size:11px;">
                                        {{ $we->exercise->name ?? 'Exercise' }} ({{ $we->target_sets }}s)
                                    </span>
                                    @empty
                                    <span class="text-muted small">No specific exercises configured.</span>
                                    @endforelse
                                    @if($todayWorkoutDay->exercises->count() > 5)
                                    <span class="badge bg-light text-muted border" style="font-size:11px;">+{{ $todayWorkoutDay->exercises->count() - 5 }} more</span>
                                    @endif
                                </div>

                                <div class="mt-3">
                                    @if($todayWorkoutSession && $todayWorkoutSession->status === 'completed')
                                        <div class="alert alert-success d-flex align-items-center justify-content-between py-2 px-3 m-0 rounded-3">
                                            <span class="d-flex align-items-center gap-2 small fw-medium">
                                                <i class="bi bi-check-circle-fill text-success fs-5"></i> Session Completed Today!
                                            </span>
                                            <a href="{{ route('workouts.sessions.show', $todayWorkoutSession) }}" class="btn btn-sm btn-outline-success">View Log</a>
                                        </div>
                                    @elseif($todayWorkoutSession && $todayWorkoutSession->status === 'in_progress')
                                        <a href="{{ route('workouts.sessions.show', $todayWorkoutSession) }}" class="btn btn-warning w-100 d-flex align-items-center justify-content-center gap-2 py-2 fw-semibold">
                                            <i class="bi bi-play-circle-fill"></i> Continue Workout Session
                                        </a>
                                    @else
                                        <form action="{{ route('workouts.sessions.start', $todayWorkoutDay) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2 py-2 fw-semibold shadow-sm">
                                                <i class="bi bi-lightning-charge-fill"></i> Start Workout Session
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @else
                                <div class="p-3 text-center text-muted">
                                    <i class="bi bi-cup-hot text-warning fs-3 mb-2 d-block"></i>
                                    <p class="small mb-0">Active rest day. Focus on hydration, stretching, and nutrition!</p>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="text-center py-4 px-3">
                            <div class="empty-icon-circle mx-auto mb-3 bg-primary-subtle text-primary">
                                <i class="bi bi-heart-pulse fs-4"></i>
                            </div>
                            <h6 class="fw-bold mb-1">No Active Workout Plan</h6>
                            <p class="text-muted small mb-3">Set up a training plan to track exercises and daily cycles.</p>
                            <a href="{{ route('workouts.plans.create') }}" class="btn btn-sm btn-outline-primary">+ Create Workout Plan</a>
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
                        <h3 class="hq-title m-0">Habits & Routines</h3>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill bg-light text-dark border px-2" id="dbRoutineCount">{{ $routineDoneCount }}/{{ $routineTotalCount }}</span>
                        <a href="{{ route('planner.index') }}" class="hq-link">Planner <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
                <div class="hq-card-body p-0">
                    <div class="routine-quick-list" id="dbRoutineList">
                        @forelse($todayRoutines as $routine)
                            @php
                                $rAvoid = ($routine->behavior_type ?? 'build') === 'avoid';
                                $rBad = $rAvoid && ! empty($routine->avoidDayViolated);
                                $rDone = $rAvoid ? false : $routine->completedOn(now());
                            @endphp
                            <div class="routine-quick-item d-flex align-items-center justify-content-between p-3 border-bottom {{ $rDone ? 'is-done' : '' }}"
                                 data-db-routine
                                 data-id="{{ $routine->id }}"
                                 data-completed="{{ $rDone ? 1 : 0 }}">
                                <div class="d-flex align-items-center gap-3">
                                    @if($rAvoid)
                                        <span class="db-routine-check" title="{{ $rBad ? 'Slip logged today' : 'Clean so far' }}">
                                            <span class="db-check-box" style="{{ $rBad ? 'background:#fee2e2;color:#b91c1c;border-color:#fca5a5;' : 'background:#dcfce7;color:#15803d;border-color:#bbf7d0;' }}">
                                                <i class="bi {{ $rBad ? 'bi-exclamation' : 'bi-shield-check' }}"></i>
                                            </span>
                                        </span>
                                    @else
                                        <label class="db-routine-check" title="{{ $rDone ? 'Mark as not done' : 'Mark as done' }}">
                                            <input type="checkbox" {{ $rDone ? 'checked' : '' }}
                                                   data-id="{{ $routine->id }}"
                                                   data-url="{{ route('planner.routines.toggle', $routine) }}"
                                                   onchange="dbToggleRoutine(this)">
                                            <span class="db-check-box"><i class="bi bi-check-lg"></i></span>
                                        </label>
                                    @endif
                                    <div>
                                        <div class="activity-item-title fw-medium">{{ $routine->title }}</div>
                                        <div class="text-muted small" style="font-size:11px;">
                                            {{ $routine->recurrenceLabel() }}
                                            @if($routine->timeLabel())
                                                • <i class="bi bi-clock"></i> {{ $routine->timeLabel() }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <span class="badge {{ $rAvoid ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' }} rounded-pill" style="font-size:10px;">
                                    {{ $rAvoid ? 'Avoid' : 'Habit' }}
                                </span>
                            </div>
                        @empty
                            <div class="text-center py-5 px-3">
                                <div class="empty-icon-circle mx-auto mb-3 bg-warning-subtle text-warning">
                                    <i class="bi bi-sun fs-4"></i>
                                </div>
                                <h6 class="fw-bold mb-1">No Routines Scheduled</h6>
                                <p class="text-muted small mb-3">Add daily or weekly habits to build your discipline.</p>
                                <a href="{{ route('routines.create') }}" class="btn btn-sm btn-outline-warning">+ Add Routine</a>
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
                        <h3 class="hq-title m-0">Productivity Pulse (14 Days)</h3>
                    </div>
                    <span class="badge bg-light text-dark border">{{ $completedTasksThisWeek }} completed this week</span>
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
                        <h3 class="hq-title m-0">Task Health & Status</h3>
                    </div>
                    <span class="small text-muted">{{ $tasksCount }} total</span>
                </div>
                <div class="hq-card-body p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div style="width: 140px; height: 140px;">
                            <canvas id="taskStatusChart"></canvas>
                        </div>
                    </div>
                    <div class="d-flex justify-content-around text-center border-top pt-3">
                        <div>
                            <div class="small text-muted">To Do</div>
                            <div class="fw-bold text-primary">{{ $taskStatusDistribution['to_do'] }}</div>
                        </div>
                        <div>
                            <div class="small text-muted">In Progress</div>
                            <div class="fw-bold text-warning">{{ $taskStatusDistribution['in_progress'] }}</div>
                        </div>
                        <div>
                            <div class="small text-muted">Completed</div>
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
                        <h3 class="hq-title m-0">Upcoming Reminders</h3>
                    </div>
                    <a href="{{ route('reminders.index') }}" class="hq-link">View All <i class="bi bi-arrow-right"></i></a>
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
                                        {{ $reminder->date->isToday() ? 'Today' : ($reminder->date->isPast() ? 'Overdue' : $reminder->date->format('M d, Y')) }}
                                        @if($reminder->time)
                                            • {{ \Carbon\Carbon::parse($reminder->time)->format('H:i') }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <span class="badge {{ $reminder->priority === 'urgent' ? 'bg-danger' : ($reminder->priority === 'high' ? 'bg-warning text-dark' : 'bg-light text-secondary border') }}">
                                {{ ucfirst($reminder->priority ?? 'normal') }}
                            </span>
                        </div>
                        @empty
                        <div class="text-center py-4 text-muted small">No upcoming reminders</div>
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
                        <h3 class="hq-title m-0">Recent Notes</h3>
                    </div>
                    <a href="{{ route('notes.index') }}" class="hq-link">View All <i class="bi bi-arrow-right"></i></a>
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
                                    <div class="small text-muted">{{ $note->created_at->diffForHumans() }}</div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted small"></i>
                        </a>
                        @empty
                        <div class="text-center py-4 text-muted small">No notes saved yet</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

    </div>

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

    /* Dashboard routines quick-check */
    .db-routine-check { position: relative; flex-shrink: 0; cursor: pointer; display: inline-flex; }
    .db-routine-check input { position: absolute; opacity: 0; width: 0; height: 0; }
    .db-check-box {
        width: 20px; height: 20px; border: 2px solid #cbd5e1; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; color: transparent;
        font-size: 11px; transition: all .15s; background: white;
    }
    .db-routine-check:hover .db-check-box { border-color: var(--primary-600); }
    .db-routine-check input:checked + .db-check-box { background: #16a34a; border-color: #16a34a; color: white; }
    .routine-quick-item.is-done .activity-item-title { text-decoration: line-through; color: #94a3b8; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const DB_CSRF = '{{ csrf_token() }}';

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
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': DB_CSRF,
                    'Accept': 'application/json'
                },
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
            const res = await fetch('{{ route("time.start") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': DB_CSRF,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    task_id: taskId,
                    project_id: projectId
                })
            });
            if (res.ok) {
                window.location.reload();
            }
        } catch (e) {
            console.error('Error starting timer', e);
        }
    }

    // Routine quick toggle
    async function dbToggleRoutine(box) {
        box.disabled = true;
        const id = box.dataset.id;
        const url = box.dataset.url;
        try {
            const res = await fetch(url + '?date=' + encodeURIComponent(new Date().toISOString().slice(0, 10)), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': DB_CSRF, 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            document.querySelectorAll('[data-db-routine][data-id="' + id + '"]').forEach(el => {
                el.classList.toggle('is-done', !!json.completed);
                el.dataset.completed = json.completed ? '1' : '0';
            });
            dbRefreshCount();
        } catch (e) {
            box.checked = !box.checked;
            console.error('Routine toggle failed', e);
        } finally {
            box.disabled = false;
        }
    }

    function dbRefreshCount() {
        const items = document.querySelectorAll('[data-db-routine]');
        let done = 0;
        items.forEach(el => { if (el.dataset.completed === '1') done++; });
        const badge = document.getElementById('dbRoutineCount');
        if (badge && items.length) badge.textContent = done + '/' + items.length;
    }

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
