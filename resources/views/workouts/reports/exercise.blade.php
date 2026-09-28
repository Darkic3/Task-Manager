@extends('layouts.app')

@section('title', $exercise->name . ' • Performance History & 1RM Progression')

@push('styles')
<style>
    .eh-shell { padding: 22px 24px 60px; background: #f8fafc; min-height: 100vh; }
    .eh-wrap { max-width: 950px; margin: auto; }
    
    .eh-head { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 1.25rem; }
    .eh-main { flex: 1; }
    .eh-title { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 4px 0 2px; }
    .eh-sub { font-size: 0.8125rem; color: #64748b; }
    
    .eh-btn {
        display: inline-flex; align-items: center; gap: 6px; border: 1px solid #dbe3ee;
        border-radius: 9px; padding: 7px 12px; font-size: 12px; font-weight: 700;
        text-decoration: none; color: #475569; background: #fff;
    }
    .eh-btn:hover { color: #4338ca; border-color: #a5b4fc; background: #f8faff; }

    .eh-stats { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-bottom: 1.25rem; }
    .eh-stat, .eh-card { background: #fff; border: 1px solid #e2e8f0; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
    .eh-stat { padding: 12px 14px; }
    .eh-val { font-size: 1.25rem; font-weight: 800; color: #1e293b; }
    .eh-lbl { font-size: 0.6875rem; color: #64748b; text-transform: uppercase; font-weight: 700; margin-top: 4px; }

    .eh-table { width: 100%; border-collapse: collapse; }
    .eh-table th, .eh-table td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; text-align: left; font-size: 12px; }
    .eh-table th { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; }
    .eh-table tr:last-child td { border-bottom: 0; }

    @media(max-width: 768px) {
        .eh-stats { grid-template-columns: repeat(3, 1fr); }
    }
    @media(max-width: 550px) {
        .eh-stats { grid-template-columns: repeat(2, 1fr); }
    }
</style>
@endpush

@section('content')
<div class="eh-shell">
    <div class="eh-wrap">

        {{-- Header --}}
        <div class="eh-head">
            <div class="eh-main">
                <a class="small text-muted text-decoration-none" href="{{ route('workouts.exercises.index') }}">
                    <i class="bi bi-arrow-left"></i> Exercise library
                </a>
                <div class="eh-title">{{ $exercise->name }}</div>
                <div class="eh-sub">Progressive overload history, 1RM progression, and session breakdown</div>
            </div>
            <a class="eh-btn" href="{{ route('workouts.reports.index') }}">
                <i class="bi bi-bar-chart"></i><span>All Reports</span>
            </a>
        </div>

        {{-- 6 Stats Cards --}}
        <div class="eh-stats">
            <div class="eh-stat">
                <div class="eh-val">{{ $stats['sessions'] }}</div>
                <div class="eh-lbl">Sessions</div>
            </div>
            <div class="eh-stat">
                <div class="eh-val text-primary">{{ $stats['sets'] }}</div>
                <div class="eh-lbl">Total Sets</div>
            </div>
            <div class="eh-stat">
                <div class="eh-val">{{ $stats['reps'] }}</div>
                <div class="eh-lbl">Total Reps</div>
            </div>
            <div class="eh-stat">
                <div class="eh-val text-success">{{ number_format($stats['volume']) }}</div>
                <div class="eh-lbl">Volume (kg)</div>
            </div>
            <div class="eh-stat">
                <div class="eh-val text-warning">{{ $stats['best_weight'] ? $stats['best_weight'] . ' kg' : '—' }}</div>
                <div class="eh-lbl">Best Weight</div>
            </div>
            <div class="eh-stat" style="background: linear-gradient(135deg, #fffbeb, #fef3c7); border-color: #fde68a;">
                <div class="eh-val text-warning fw-black">🏆 {{ $stats['all_time_1rm'] ? $stats['all_time_1rm'] . ' kg' : '—' }}</div>
                <div class="eh-lbl text-warning font-sans fw-bold">All-Time 1RM</div>
            </div>
        </div>

        {{-- Progressive Overload Chart (1RM & Volume Progression) --}}
        @if(isset($chartSessions) && $chartSessions->count())
        <div class="eh-card p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-graph-up-arrow text-primary"></i>
                    <h6 class="fw-bold m-0">Progressive Overload Trend (1RM & Volume)</h6>
                </div>
                <span class="badge bg-light text-muted border small">History Progression</span>
            </div>
            <div style="height: 240px; position: relative;">
                <canvas id="overloadChart"></canvas>
            </div>
        </div>
        @endif

        {{-- Session History Table --}}
        <div class="eh-card overflow-hidden">
            <div class="p-3 border-bottom bg-light fw-bold">Session History & PR Milestones</div>
            @if($sessions->count())
                <div class="table-responsive">
                    <table class="eh-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Sets</th>
                                <th>Total Reps</th>
                                <th>Max Weight</th>
                                <th>Est. 1RM</th>
                                <th>Volume (kg)</th>
                                <th>Avg RIR</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sessions as $session)
                                <tr>
                                    <td><strong>{{ \Carbon\Carbon::parse($session['date'])->format('M j, Y') }}</strong></td>
                                    <td><span class="badge bg-light text-dark border">{{ $session['sets'] }}</span></td>
                                    <td>{{ $session['reps'] }}</td>
                                    <td><strong class="text-primary">{{ $session['max_weight'] ? $session['max_weight'] . ' kg' : '—' }}</strong></td>
                                    <td><span class="badge bg-warning-subtle text-warning fw-bold">{{ $session['best_1rm'] ? $session['best_1rm'] . ' kg' : '—' }}</span></td>
                                    <td><span class="text-success fw-semibold">{{ number_format($session['volume']) }}</span></td>
                                    <td>{{ $session['avg_rir'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-graph-up fs-2 mb-2 d-block text-primary opacity-50"></i>
                    No completed sessions recorded for this movement yet.
                </div>
            @endif
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartCanvas = document.getElementById('overloadChart');
    if (chartCanvas) {
        const sessions = @json($chartSessions ?? []);
        const labels = sessions.map(s => s.date);
        const rmData = sessions.map(s => s.best_1rm || 0);
        const volData = sessions.map(s => s.volume || 0);

        new Chart(chartCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Est. 1RM (kg)',
                        data: rmData,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        borderWidth: 2.5,
                        tension: 0.3,
                        yAxisID: 'y',
                        pointBackgroundColor: '#f59e0b'
                    },
                    {
                        label: 'Total Volume (kg)',
                        data: volData,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.05)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y1',
                        fill: true,
                        pointBackgroundColor: '#6366f1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: '1RM (kg)' },
                        grid: { color: '#f1f5f9' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Volume (kg)' },
                        grid: { drawOnChartArea: false }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
});
</script>
@endpush
