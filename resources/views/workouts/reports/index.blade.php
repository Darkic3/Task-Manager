@extends('layouts.app')

@section('title', 'Workout Reports & Muscle Analytics')

@push('styles')
<style>
    .wr-shell { padding: 22px 24px 60px; background: #f8fafc; min-height: 100vh; }
    .wr-wrap { max-width: 1100px; margin: auto; }
    .wr-head { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 1.25rem; }
    .wr-head-main { flex: 1; }
    .wr-head h1 { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0; }
    .wr-head p { font-size: 0.8125rem; color: #64748b; margin: 4px 0 0; }
    
    .wr-btn {
        display: inline-flex; align-items: center; gap: 6px; border: 1px solid #dbe3ee;
        border-radius: 9px; padding: 7px 12px; font-size: 12px; font-weight: 700;
        text-decoration: none; color: #475569; background: #fff; transition: all 0.15s;
    }
    .wr-btn:hover { color: #4338ca; border-color: #a5b4fc; background: #f8faff; }
    .wr-btn.primary { background: #4f46e5; border-color: #4f46e5; color: #fff; }

    .wr-card { background: #fff; border: 1px solid #e2e8f0; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
    
    .wr-toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; padding: 10px 14px; margin-bottom: 1rem; }
    .wr-date { font-size: 0.875rem; font-weight: 700; color: #1e293b; flex: 1; text-align: center; }
    .wr-toggle { display: flex; background: #f1f5f9; padding: 3px; border-radius: 8px; }
    .wr-toggle a { padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; text-decoration: none; color: #64748b; }
    .wr-toggle a.active { background: #fff; color: #4338ca; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }

    .wr-stats { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-bottom: 1.25rem; }
    .wr-stat { padding: 12px 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: var(--radius-md); }
    .wr-stat-value { font-size: 1.25rem; font-weight: 800; color: #1e293b; }
    .wr-stat-label { font-size: 0.6875rem; color: #64748b; text-transform: uppercase; font-weight: 700; margin-top: 4px; }

    .wr-grid { display: grid; grid-template-columns: repeat(7, minmax(120px, 1fr)); gap: 8px; overflow-x: auto; padding-bottom: 8px; }
    .wr-day { min-height: 160px; background: #fff; border: 1px solid #e2e8f0; border-radius: var(--radius-md); padding: 10px; }
    .wr-day.today { border-color: #6366f1; box-shadow: 0 0 0 2px #e0e7ff; }
    .wr-day-head { display: flex; justify-content: space-between; gap: 5px; }
    .wr-day-name { font-size: 10px; text-transform: uppercase; font-weight: 800; color: #64748b; }
    .wr-day-num { font-size: 13px; font-weight: 800; color: #1e293b; }
    .wr-day-summary { font-size: 10px; color: #64748b; margin: 6px 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px; }

    .wr-session {
        display: block; padding: 6px 8px; border-radius: 6px; background: #eef2ff;
        color: #3730a3; text-decoration: none; margin-top: 5px; font-size: 11px; font-weight: 700;
    }
    .wr-session.completed { background: #dcfce7; color: #166534; }
    .wr-session.skipped { background: #fef2f2; color: #b91c1c; }

    .muscle-chip {
        display: flex; align-items: center; justify-content: space-between;
        padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
    }

    @media(max-width: 850px) {
        .wr-stats { grid-template-columns: repeat(3, 1fr); }
    }
    @media(max-width: 550px) {
        .wr-stats { grid-template-columns: repeat(2, 1fr); }
    }
</style>
@endpush

@php
    $previousDate = ($mode === 'day' ? $date : $start)->copy()->subDays($mode === 'day' ? 1 : 7);
    $nextDate = ($mode === 'day' ? $date : $start)->copy()->addDays($mode === 'day' ? 1 : 7);
    $label = $mode === 'day' ? $date->format('D, M j, Y') : $start->format('M j') . ' – ' . $end->format('M j, Y');
@endphp

@section('content')
<div class="wr-shell">
    <div class="wr-wrap">

        {{-- Header --}}
        <div class="wr-head">
            <div class="wr-head-main">
                <h1>Workout Reports & Muscle Analytics</h1>
                <p>Track weekly volume, muscle distribution, progressive overload, and session adherence.</p>
            </div>
            <a class="wr-btn" href="{{ route('workouts.plans.index') }}"><i class="bi bi-calendar3"></i><span>Plans</span></a>
            <a class="wr-btn" href="{{ route('workouts.exercises.index') }}"><i class="bi bi-heart-pulse"></i><span>Exercises</span></a>
        </div>

        {{-- Toolbar --}}
        <div class="wr-card wr-toolbar">
            <div class="wr-toggle">
                <a class="{{ $mode === 'day' ? 'active' : '' }}" href="{{ route('workouts.reports.index', ['view' => 'day', 'date' => $date->toDateString()]) }}">Daily</a>
                <a class="{{ $mode === 'week' ? 'active' : '' }}" href="{{ route('workouts.reports.index', ['view' => 'week', 'date' => $date->toDateString()]) }}">Weekly</a>
            </div>
            <a class="wr-btn" href="{{ route('workouts.reports.index', ['view' => $mode, 'date' => $previousDate->toDateString()]) }}"><i class="bi bi-chevron-left"></i></a>
            <div class="wr-date">{{ $label }}</div>
            <a class="wr-btn" href="{{ route('workouts.reports.index', ['view' => $mode, 'date' => $nextDate->toDateString()]) }}"><i class="bi bi-chevron-right"></i></a>
            <a class="wr-btn" href="{{ route('workouts.reports.index', ['view' => $mode]) }}">Today</a>
        </div>

        {{-- Top Summary Stats --}}
        <div class="wr-stats">
            <div class="wr-stat">
                <div class="wr-stat-value">{{ $summary['completed_sessions'] }}/{{ $summary['scheduled'] ?: $summary['sessions'] }}</div>
                <div class="wr-stat-label">Sessions Done</div>
            </div>
            <div class="wr-stat">
                <div class="wr-stat-value">{{ $summary['completed_exercises'] }}/{{ $summary['exercises'] }}</div>
                <div class="wr-stat-label">Exercises</div>
            </div>
            <div class="wr-stat">
                <div class="wr-stat-value text-primary">{{ $summary['sets'] }}</div>
                <div class="wr-stat-label">Total Sets</div>
            </div>
            <div class="wr-stat">
                <div class="wr-stat-value">{{ $summary['reps'] }}</div>
                <div class="wr-stat-label">Total Reps</div>
            </div>
            <div class="wr-stat">
                <div class="wr-stat-value text-success">{{ number_format($summary['volume']) }}</div>
                <div class="wr-stat-label">Volume (kg)</div>
            </div>
            <div class="wr-stat">
                <div class="wr-stat-value {{ $summary['pain_events'] ? 'text-danger' : 'text-muted' }}">{{ $summary['pain_events'] }}</div>
                <div class="wr-stat-label">Pain Flags</div>
            </div>
        </div>

        {{-- Weekly Muscle Volume Radar & Distribution Section --}}
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="wr-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-pie-chart text-primary"></i>
                            <h6 class="fw-bold m-0">Weekly Muscle Volume Radar</h6>
                        </div>
                        <span class="badge bg-light text-dark border small">Target: 10-20 sets/muscle</span>
                    </div>
                    <div style="height: 250px; position: relative;" class="d-flex align-items-center justify-content-center">
                        <canvas id="muscleRadarChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="wr-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-layers text-success"></i>
                            <h6 class="fw-bold m-0">Muscle Group Volume Breakdown</h6>
                        </div>
                        <span class="small text-muted">{{ array_sum($muscleVolume) }} Total Sets</span>
                    </div>

                    <div class="d-flex flex-column gap-2">
                        @foreach($muscleVolume as $group => $sets)
                            @php
                                $status = $sets >= 10 ? 'Optimal (Hypertrophy)' : ($sets > 0 ? 'Maintenance' : 'Resting');
                                $badgeClass = $sets >= 10 ? 'bg-success text-white' : ($sets > 0 ? 'bg-primary-subtle text-primary' : 'bg-light text-muted border');
                            @endphp
                            <div class="muscle-chip">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold text-dark small" style="width: 80px;">{{ $group }}</span>
                                    <div class="progress" style="width: 140px; height: 6px;">
                                        <div class="progress-bar {{ $sets >= 10 ? 'bg-success' : 'bg-primary' }}" style="width: {{ min(round(($sets / 20) * 100), 100) }}%"></div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="small fw-bold">{{ $sets }} sets</span>
                                    <span class="badge {{ $badgeClass }} rounded-pill px-2" style="font-size: 10px;">{{ $status }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Week Calendar Grid --}}
        @if($mode === 'week')
            <div class="wr-grid mb-4">
                @foreach($days as $day)
                    <div class="wr-day {{ $day['date']->isToday() ? 'today' : '' }}">
                        <div class="wr-day-head">
                            <span class="wr-day-name">{{ $day['date']->format('D') }}</span>
                            <span class="wr-day-num">{{ $day['date']->format('j') }}</span>
                        </div>
                        <div class="wr-day-summary">
                            {{ $day['summary']['completed_sessions'] }}/{{ $day['scheduled'] ?: $day['summary']['sessions'] }} planned • {{ $day['summary']['sets'] }} sets
                        </div>
                        @forelse($day['sessions'] as $session)
                            <a class="wr-session {{ $session->status }}" href="{{ route('workouts.sessions.show', $session) }}">
                                {{ $session->day->title }}
                                <div class="small opacity-75">{{ $session->status }} • {{ $session->durationMinutes() ?? 0 }}m</div>
                            </a>
                        @empty
                            <span class="text-muted small" style="font-size:11px;">Rest Day</span>
                        @endforelse
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Sessions Table --}}
        <div class="wr-card p-0 overflow-hidden">
            <div class="p-3 border-bottom bg-light fw-bold">
                {{ $mode === 'day' ? 'Session details' : 'Sessions in this period' }}
            </div>
            <div class="list-group list-group-flush">
                @forelse($sessions as $session)
                    <div class="list-group-item d-flex align-items-center justify-content-between p-3 flex-wrap gap-2">
                        <div>
                            <span class="text-muted small d-block">{{ $session->workout_date->format('M j, Y') }}</span>
                            <a class="fw-bold text-dark text-decoration-none" href="{{ route('workouts.sessions.show', $session) }}">
                                {{ $session->day->title }}
                            </a>
                            <span class="text-muted small ms-2">• {{ $session->day->plan->title }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge bg-light text-dark border">{{ $session->exerciseLogs->where('completed', true)->count() }}/{{ $session->day->exercises->count() }} movements</span>
                            <span class="badge bg-primary-subtle text-primary">{{ $session->exerciseLogs->flatMap->setLogs->where('completed', true)->count() }} sets</span>
                            <span class="small text-muted">{{ $session->durationMinutes() ?? 0 }} min</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-bar-chart fs-2 mb-2 d-block text-primary opacity-50"></i>
                        No workout sessions recorded in this timeframe.
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const radarCanvas = document.getElementById('muscleRadarChart');
    if (radarCanvas) {
        const labels = @json(array_keys($muscleVolume));
        const data = @json(array_values($muscleVolume));

        new Chart(radarCanvas.getContext('2d'), {
            type: 'radar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Sets Completed This Week',
                    data: data,
                    fill: true,
                    backgroundColor: 'rgba(99, 102, 241, 0.2)',
                    borderColor: '#6366f1',
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#6366f1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        suggestedMax: 20,
                        ticks: { stepSize: 5 }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});
</script>
@endpush
