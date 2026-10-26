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
    .wr-grid.month { grid-template-columns: repeat(7, minmax(95px, 1fr)); }
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
    $previousDate = match ($mode) {
        'day' => $date->copy()->subDay(),
        'week' => $start->copy()->subDays(7),
        'month' => $date->copy()->subMonth(),
        default => $start->copy(),
    };
    $nextDate = match ($mode) {
        'day' => $date->copy()->addDay(),
        'week' => $start->copy()->addDays(7),
        'month' => $date->copy()->addMonth(),
        default => $start->copy(),
    };
    $label = match ($mode) {
        'day' => $date->format('D, M j, Y'),
        'week' => $start->format('M j') . ' – ' . $end->format('M j, Y'),
        'month' => $date->format('M Y'),
        default => __('Overall'),
    };
@endphp

@section('content')
<div class="wr-shell">
    <div class="wr-wrap">

        {{-- Header --}}
        <div class="wr-head">
            <div class="wr-head-main">
                <h1>{{ __('Workout Reports & Muscle Analytics') }}</h1>
                <p>{{ __('Track weekly volume, muscle distribution, progressive overload, and session adherence.') }}</p>
            </div>
            <a class="wr-btn" href="{{ route('workouts.plans.index') }}"><i class="bi bi-calendar3"></i><span>{{ __('Plans') }}</span></a>
            <a class="wr-btn" href="{{ route('workouts.exercises.index') }}"><i class="bi bi-heart-pulse"></i><span>{{ __('Exercises') }}</span></a>
        </div>

        {{-- Toolbar --}}
        <div class="wr-card wr-toolbar">
            <div class="wr-toggle">
                <a class="{{ $mode === 'day' ? 'active' : '' }}" href="{{ route('workouts.reports.index', ['view' => 'day', 'date' => $date->toDateString()]) }}">{{ __('Daily') }}</a>
                <a class="{{ $mode === 'week' ? 'active' : '' }}" href="{{ route('workouts.reports.index', ['view' => 'week', 'date' => $date->toDateString()]) }}">{{ __('Weekly') }}</a>
                <a class="{{ $mode === 'month' ? 'active' : '' }}" href="{{ route('workouts.reports.index', ['view' => 'month', 'date' => $date->toDateString()]) }}">{{ __('Monthly') }}</a>
                <a class="{{ $mode === 'overall' ? 'active' : '' }}" href="{{ route('workouts.reports.index', ['view' => 'overall']) }}">{{ __('Overall') }}</a>
            </div>

            @if($mode !== 'overall')
                <a class="wr-btn" href="{{ route('workouts.reports.index', ['view' => $mode, 'date' => $previousDate->toDateString()]) }}" title="{{ __('Previous') }}"><i class="bi bi-chevron-left"></i></a>
            @endif
            <div class="wr-date">{{ $label }}</div>
            @if($mode !== 'overall')
                <a class="wr-btn" href="{{ route('workouts.reports.index', ['view' => $mode, 'date' => $nextDate->toDateString()]) }}" title="{{ __('Next') }}"><i class="bi bi-chevron-right"></i></a>
            @endif
            <a class="wr-btn" href="{{ $mode === 'overall' ? route('workouts.reports.index', ['view' => 'overall']) : route('workouts.reports.index', ['view' => $mode]) }}">{{ __('Today') }}</a>

            <div class="d-flex gap-2 flex-wrap">
                @php
                    $exportBase = ['view' => $mode];
                    if ($mode !== 'overall') {
                        $exportBase['date'] = $date->toDateString();
                    }
                @endphp
                <a
                    class="wr-btn"
                    href="{{ route('workouts.reports.export', $exportBase + ['download' => 1]) }}"
                    title="{{ __('Download TXT') }}"
                >
                    <i class="bi bi-download"></i><span>{{ __('Export TXT') }}</span>
                </a>
                <button
                    type="button"
                    class="wr-btn"
                    data-export-url="{{ route('workouts.reports.export', $exportBase + ['download' => 0]) }}"
                    id="wrExportViewBtn"
                    title="{{ __('View / Copy text') }}"
                >
                    <i class="bi bi-clipboard"></i><span>{{ __('View Text') }}</span>
                </button>
            </div>
        </div>

        {{-- Top Summary Stats --}}
        <div class="wr-stats">
            <div class="wr-stat">
                <div class="wr-stat-value">{{ $summary['completed_sessions'] }}/{{ $summary['scheduled'] ?: $summary['sessions'] }}</div>
                <div class="wr-stat-label">{{ __('Sessions done') }}</div>
            </div>
            <div class="wr-stat">
                <div class="wr-stat-value">{{ $summary['completed_exercises'] }}/{{ $summary['exercises'] }}</div>
                <div class="wr-stat-label">{{ __('Exercises') }}</div>
            </div>
            <div class="wr-stat">
                <div class="wr-stat-value text-primary">{{ $summary['sets'] }}</div>
                <div class="wr-stat-label">{{ __('Total Sets') }}</div>
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

        {{-- Management Insights --}}
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="wr-card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-speedometer2 text-primary"></i>
                            <h6 class="fw-bold m-0">{{ __('Adherence & Time') }}</h6>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">{{ __('Sessions done') }}</span>
                            <span class="fw-bold">{{ $summary['completed_sessions'] }}/{{ $summary['scheduled_sessions'] ?? '—' }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">{{ __('Adherence') }}</span>
                            <span class="fw-bold text-primary">{{ $summary['adherence_percent'] !== null ? $summary['adherence_percent'].'%' : '—' }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">{{ __('Training time') }}</span>
                            <span class="fw-bold">{{ $summary['minutes'] }} {{ __('min') }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">{{ __('Avg / completed session') }}</span>
                            <span class="fw-bold">{{ $summary['completed_sessions'] ? round($summary['minutes'] / $summary['completed_sessions'], 1) : '—' }} {{ __('min') }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="wr-card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-clipboard-data text-success"></i>
                            <h6 class="fw-bold m-0">{{ __('Planned vs Done') }}</h6>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">{{ __('Sets (planned/done)') }}</span>
                            <span class="fw-bold">{{ $summary['planned_vs_done_sets'] ?? '—' }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">{{ __('Weekly bodyweight avg') }}</span>
                            <span class="fw-bold">{{ $summary['bodyweight_week_avg'] !== null ? number_format($summary['bodyweight_week_avg'], 1).' kg' : '—' }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">{{ __('Change vs prev week') }}</span>
                            <span class="fw-bold text-{{ ($summary['bodyweight_week_delta'] ?? 0) >= 0 ? 'success' : 'danger' }}">
                                {{ $summary['bodyweight_week_delta'] !== null ? ( ($summary['bodyweight_week_delta'] >= 0 ? '+' : '') . number_format($summary['bodyweight_week_delta'], 2).' kg' ) : '—' }}
                            </span>
                        </div>
                    </div>

                    @if(!empty($summary['set_skip_reasons']) || !empty($summary['exercise_skip_reasons']))
                        <div class="mt-3">
                            <div class="small fw-bold text-muted mb-2">{{ __('Skipped (Top Reasons)') }}</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($summary['exercise_skip_reasons'] as $reason => $count)
                                    <span class="badge bg-danger-subtle text-danger border">{{ $reason }}: {{ $count }}</span>
                                @endforeach
                                @foreach($summary['set_skip_reasons'] as $reason => $count)
                                    <span class="badge bg-warning-subtle text-warning border">{{ $reason }}: {{ $count }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Muscle Volume Radar & Distribution Section --}}
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="wr-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-pie-chart text-primary"></i>
                            <h6 class="fw-bold m-0">Muscle Volume Radar</h6>
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

        {{-- Strength Trend Charts --}}
        @if(count($strengthCharts ?? []))
            <div class="wr-card p-3 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-graph-up-arrow text-primary"></i>
                        <h6 class="fw-bold m-0">Strength Trends</h6>
                    </div>
                    <span class="badge bg-light text-muted border small">{{ __('Reps per set') }}</span>
                </div>

                <div class="row g-3">
                    @foreach(($strengthCharts ?? []) as $key => $chart)
                        @php
                            $canvasId = 'strengthChart_' . $key;
                        @endphp
                        <div class="col-lg-6">
                            <div class="wr-card p-3 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="fw-bold" style="font-size:13px;">{{ $chart['label'] }}</div>
                                </div>
                                <div style="height: 240px; position: relative;">
                                    <canvas id="{{ $canvasId }}"></canvas>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Bodyweight + Compliance Charts --}}
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="wr-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-body-text text-success"></i>
                            <h6 class="fw-bold m-0">Bodyweight</h6>
                        </div>
                        <span class="badge bg-light text-muted border small">{{ __('Daily + weekly avg') }}</span>
                    </div>
                    <div style="height: 240px; position: relative;">
                        <canvas id="bodyweightChart"></canvas>
                    </div>
                    <div class="small text-muted mt-2 d-flex justify-content-between">
                        <span>{{ __('Weekly avg change') }}</span>
                        <span class="fw-bold {{ ($summary['bodyweight_week_delta'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $summary['bodyweight_week_delta'] !== null ? ( ($summary['bodyweight_week_delta'] >= 0 ? '+' : '') . number_format($summary['bodyweight_week_delta'], 2) . ' kg') : '—' }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="wr-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check2-circle text-primary"></i>
                            <h6 class="fw-bold m-0">Adherence & Time</h6>
                        </div>
                        <span class="badge bg-light text-muted border small">{{ __('Minutes + set completion') }}</span>
                    </div>
                    <div style="height: 240px; position: relative;">
                        <canvas id="complianceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Calendar Grid --}}
        @if($mode === 'week' || $mode === 'month')
            <div class="wr-grid mb-4 {{ $mode === 'month' ? 'month' : '' }}">
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
                                {{ $session->displayDayTitle() }}
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
                                {{ $session->displayDayTitle() }}
                            </a>
                            @if($session->displayPlanTitle())
                                <span class="text-muted small ms-2">• {{ $session->displayPlanTitle() }}</span>
                            @endif
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge bg-light text-dark border">{{ $session->exerciseLogs->where('completed', true)->count() }}/{{ $session->day?->exercises?->count() ?? $session->exerciseLogs->count() }} movements</span>
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

{{-- Export modal (view/copy) --}}
<div class="modal fade" id="wrExportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content rounded-4 shadow">
            <div class="modal-header border-0 bg-light p-3">
                <h6 class="modal-title fw-bold">{{ __('Workout Report Text') }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body p-3">
                <textarea
                    id="wrExportTextArea"
                    class="form-control font-monospace"
                    rows="18"
                    style="white-space: pre; min-height: 50vh;"
                ></textarea>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <button type="button" class="btn btn-primary" id="wrCopyTextBtn">
                    <i class="bi bi-clipboard me-1"></i>{{ __('Copy') }}
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendor/chartjs/chart.umd.js') }}"></script>
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
                    label: '{{ __('Sets Completed In This Period') }}',
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

    // Strength Charts (Pull-up / Chin-up / Pike / Bulgarian)
    const strengthCharts = @json($strengthCharts ?? []);
    Object.keys(strengthCharts).forEach((key) => {
        const canvas = document.getElementById('strengthChart_' + key);
        if (!canvas) return;
        const chart = strengthCharts[key];

        new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: chart.labels,
                datasets: chart.datasets.map(ds => ({
                    label: ds.label,
                    data: ds.data,
                    borderColor: ds.color,
                    backgroundColor: ds.color,
                    borderWidth: 2,
                    tension: 0.25,
                    pointRadius: 3,
                    spanGaps: true,
                    yAxisID: ds.yAxis || 'y',
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'bottom' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Reps' },
                        grid: { color: '#f1f5f9' }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        title: { display: true, text: 'kg' },
                        grid: { drawOnChartArea: false },
                    },
                    x: {
                        ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                        grid: { display: false }
                    }
                }
            }
        });
    });

    // Bodyweight Trend
    const bw = @json($bodyweightChart ?? null);
    const bwCanvas = document.getElementById('bodyweightChart');
    if (bwCanvas && bw && bw.labels && bw.labels.length) {
        new Chart(bwCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: bw.labels,
                datasets: [
                    {
                        label: 'Daily weight (kg)',
                        data: bw.daily,
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22,163,74,0.12)',
                        borderWidth: 2,
                        tension: 0.25,
                        pointRadius: 3,
                        spanGaps: true,
                    },
                    {
                        label: 'Weekly avg (kg)',
                        data: bw.weekly,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.08)',
                        borderWidth: 2,
                        tension: 0.2,
                        pointRadius: 2,
                        borderDash: [6, 6],
                        spanGaps: true,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'bottom' }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: { color: '#f1f5f9' },
                        title: { display: true, text: 'kg' }
                    },
                    x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 10 } }
                }
            }
        });
    }

    // Compliance & Time
    const comp = @json($complianceChart ?? null);
    const compCanvas = document.getElementById('complianceChart');
    if (compCanvas && comp && comp.labels && comp.labels.length) {
        new Chart(compCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: comp.labels,
                datasets: [
                    {
                        label: 'Minutes',
                        data: comp.minutes,
                        backgroundColor: 'rgba(79,70,229,0.25)',
                        borderColor: '#4f46e5',
                        borderWidth: 2,
                        yAxisID: 'y'
                    },
                    {
                        type: 'line',
                        label: 'Set completion %',
                        data: comp.setCompletion,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14,165,233,0.12)',
                        borderWidth: 2,
                        pointRadius: 3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'bottom' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'min' },
                        grid: { color: '#f1f5f9' }
                    },
                    y1: {
                        beginAtZero: true,
                        suggestedMax: 100,
                        title: { display: true, text: '%' },
                        grid: { drawOnChartArea: false }
                    },
                    x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 8 } }
                }
            }
        });
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('wrExportViewBtn');
    const modalEl = document.getElementById('wrExportModal');
    const textarea = document.getElementById('wrExportTextArea');
    const copyBtn = document.getElementById('wrCopyTextBtn');

    if (!btn || !modalEl || !textarea || !copyBtn) return;

    const modal = new bootstrap.Modal(modalEl);

    btn.addEventListener('click', async function() {
        textarea.value = '';
        modal.show();

        try {
            const url = btn.dataset.exportUrl;
            const res = await fetch(url, { headers: { 'Accept': 'text/plain' } });
            const text = await res.text();
            textarea.value = text;
        } catch (e) {
            textarea.value = '{{ __('Could not load export text.') }}';
        }
    });

    copyBtn.addEventListener('click', async function() {
        const text = textarea.value || '';
        if (!text) return;

        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
                copyBtn.innerHTML = '<i class="bi bi-check2 me-1"></i>{{ __('Copied') }}';
                setTimeout(() => {
                    copyBtn.innerHTML = '<i class="bi bi-clipboard me-1"></i>{{ __('Copy') }}';
                }, 1200);
            } else {
                textarea.select();
                document.execCommand('copy');
            }
        } catch (e) {}
    });
});
</script>
@endpush
