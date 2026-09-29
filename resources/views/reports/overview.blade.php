@extends('layouts.app')

@section('title', __('Reports'))

@push('styles')
<style>
    .rp-container {
        padding: 20px 24px;
        background: #f8fafc;
        min-height: 100vh;
    }
    [dir="rtl"] .rp-container {
        text-align: right;
    }

    /* Hero Header */
    .rp-hero {
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #6366f1 100%);
        border-radius: 16px;
        padding: 20px 24px;
        color: #ffffff;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.25), 0 8px 10px -6px rgba(79, 70, 229, 0.2);
    }
    .rp-hero::before {
        content: '';
        position: absolute;
        top: -40px;
        right: -40px;
        width: 160px;
        height: 160px;
        background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    [dir="rtl"] .rp-hero::before {
        right: auto;
        left: -40px;
    }
    .rp-hero-content {
        position: relative;
        z-index: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
    .rp-hero-title {
        font-weight: 800;
        font-size: 20px;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        letter-spacing: -0.02em;
    }
    .rp-hero-title i {
        font-size: 22px;
        background: rgba(255, 255, 255, 0.2);
        padding: 6px;
        border-radius: 10px;
        backdrop-filter: blur(4px);
    }
    .rp-hero-sub {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.88);
        margin: 4px 0 0;
        font-weight: 500;
    }
    .rp-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        background: rgba(255, 255, 255, 0.18);
        border: 1px solid rgba(255, 255, 255, 0.25);
        backdrop-filter: blur(8px);
        border-radius: 30px;
        font-size: 12px;
        font-weight: 600;
        color: #ffffff;
    }

    /* Filter Toolbar */
    .rp-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }
    .rp-segmented {
        display: inline-flex;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 4px;
        gap: 3px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .rp-segmented-btn {
        padding: 6px 16px;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 600;
        color: #64748b;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .rp-segmented-btn:hover {
        color: #1e293b;
        background: #f1f5f9;
    }
    .rp-segmented-btn.active {
        background: #4f46e5;
        color: #ffffff;
        box-shadow: 0 2px 6px rgba(79, 70, 229, 0.28);
    }
    .rp-date-form {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 4px 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .rp-date-input {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 5px 10px;
        font-size: 12.5px;
        color: #1e293b;
        background: #f8fafc;
        outline: none;
        transition: border-color 0.15s ease;
    }
    .rp-date-input:focus {
        border-color: #4f46e5;
        background: #ffffff;
    }
    .rp-apply-btn {
        border: none;
        background: #4f46e5;
        color: #ffffff;
        border-radius: 8px;
        padding: 6px 16px;
        font-size: 12.5px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .rp-apply-btn:hover {
        background: #4338ca;
    }

    /* KPI Grid */
    .rp-kpis {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
        margin-bottom: 24px;
    }
    @media(max-width: 1200px) { .rp-kpis { grid-template-columns: repeat(3, 1fr); } }
    @media(max-width: 640px) { .rp-kpis { grid-template-columns: repeat(2, 1fr); } }

    .rp-kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 14px 16px;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .rp-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.06);
        border-color: #cbd5e1;
    }
    .rp-kpi-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
    }
    .rp-kpi-icon {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
    }
    .rp-kpi-val {
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
        letter-spacing: -0.02em;
    }
    .rp-kpi-label {
        font-size: 11px;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 4px;
    }
    .rp-delta-pill {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        margin-top: 8px;
        align-self: flex-start;
    }
    .rp-delta-pill.up { background: #dcfce7; color: #15803d; }
    .rp-delta-pill.down { background: #fee2e2; color: #b91c1c; }
    .rp-delta-pill.flat { background: #f1f5f9; color: #64748b; }

    /* Section Headings */
    .rp-section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 15px;
        font-weight: 800;
        color: #0f172a;
        margin: 28px 0 14px;
    }
    .rp-section-title i {
        font-size: 18px;
    }
    .rp-section-title .rp-badge-count {
        font-size: 11px;
        font-weight: 600;
        background: #f1f5f9;
        color: #475569;
        padding: 2px 8px;
        border-radius: 12px;
    }

    /* Insights Grid */
    .rp-insights-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    @media(max-width: 800px) { .rp-insights-grid { grid-template-columns: 1fr; } }

    .rp-insight-card {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 16px;
        border-inline-start-width: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        transition: transform 0.15s ease;
    }
    .rp-insight-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    }
    .rp-insight-card.good {
        border-inline-start-color: #10b981;
        background: linear-gradient(to right, rgba(16, 185, 129, 0.02), #ffffff 20%);
    }
    .rp-insight-card.warn {
        border-inline-start-color: #f59e0b;
        background: linear-gradient(to right, rgba(245, 158, 11, 0.02), #ffffff 20%);
    }
    .rp-insight-card.bad {
        border-inline-start-color: #ef4444;
        background: linear-gradient(to right, rgba(239, 68, 68, 0.02), #ffffff 20%);
    }
    .rp-insight-card.info {
        border-inline-start-color: #6366f1;
        background: linear-gradient(to right, rgba(99, 102, 241, 0.02), #ffffff 20%);
    }
    .rp-insight-icon {
        font-size: 18px;
        margin-top: 1px;
        flex-shrink: 0;
    }
    .rp-insight-card.good .rp-insight-icon { color: #10b981; }
    .rp-insight-card.warn .rp-insight-icon { color: #f59e0b; }
    .rp-insight-card.bad .rp-insight-icon { color: #ef4444; }
    .rp-insight-card.info .rp-insight-icon { color: #6366f1; }

    .rp-insight-title {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.3;
    }
    .rp-insight-body {
        font-size: 12px;
        color: #64748b;
        margin-top: 3px;
        line-height: 1.4;
    }

    /* Cards and Grids */
    .rp-grid2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        margin-bottom: 14px;
    }
    @media(max-width: 800px) { .rp-grid2 { grid-template-columns: 1fr; } }

    .rp-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        margin-bottom: 14px;
    }
    .rp-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 12px 18px;
        background: #fafbfc;
        border-bottom: 1px solid #f1f5f9;
    }
    .rp-card-title {
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .rp-card-body {
        padding: 16px 18px;
    }

    /* Charts */
    .rp-chart-container {
        display: flex;
        align-items: flex-end;
        gap: 4px;
        height: 110px;
        padding-top: 10px;
    }
    .rp-bar-col {
        flex: 1;
        height: 100%;
        display: flex;
        align-items: flex-end;
        min-width: 0;
        position: relative;
    }
    .rp-bar-inner {
        width: 100%;
        min-height: 3px;
        border-radius: 4px 4px 0 0;
        background: #c7d2fe;
        transition: all 0.2s ease;
    }
    .rp-bar-col:hover .rp-bar-inner {
        background: #4f46e5;
        box-shadow: 0 0 8px rgba(79, 70, 229, 0.4);
    }
    .rp-bar-inner.green { background: #bbf7d0; }
    .rp-bar-col:hover .rp-bar-inner.green { background: #16a34a; box-shadow: 0 0 8px rgba(22, 163, 74, 0.4); }
    .rp-bar-inner.red { background: #fecaca; }
    .rp-bar-col:hover .rp-bar-inner.red { background: #dc2626; box-shadow: 0 0 8px rgba(220, 38, 38, 0.4); }

    .rp-axis-row {
        display: flex;
        margin-top: 8px;
        border-top: 1px dashed #e2e8f0;
        padding-top: 6px;
    }
    .rp-axis-cell {
        flex: 1;
        text-align: center;
        font-size: 10px;
        color: #94a3b8;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
    }

    /* List Rows */
    .rp-list-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 12.5px;
    }
    .rp-list-row:last-child {
        border-bottom: none;
    }
    .rp-list-name {
        font-weight: 600;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 45%;
    }
    .rp-progress-track {
        flex: 1;
        height: 6px;
        background: #f1f5f9;
        border-radius: 6px;
        overflow: hidden;
    }
    .rp-progress-fill {
        height: 100%;
        background: #4f46e5;
        border-radius: 6px;
        transition: width 0.3s ease;
    }
    .rp-list-val {
        font-weight: 700;
        color: #334155;
        white-space: nowrap;
        font-feature-settings: "tnum";
    }
    .rp-pill-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }

    /* Tables */
    .rp-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
    }
    .rp-table th {
        text-align: inherit;
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        padding: 8px 10px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
        font-weight: 700;
    }
    .rp-table td {
        padding: 9px 10px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        vertical-align: middle;
    }
    .rp-table tr:last-child td {
        border-bottom: none;
    }
    .rp-table tr:hover td {
        background: #f8fafc;
    }

    /* Empty states */
    .rp-empty-state {
        text-align: center;
        color: #94a3b8;
        font-size: 12.5px;
        padding: 24px 16px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .rp-empty-state i {
        font-size: 24px;
        color: #cbd5e1;
    }
</style>
@endpush

@php
    $q = $range->toQuery();
    $link = fn ($r) => route('reports.overview', array_merge(['range' => $r], $r === 'custom' ? ['from' => $q['from'], 'to' => $q['to']] : []));
    $delta = function ($v, $suffix = '', $invert = false) {
        $prevText = __('vs prev');
        if ($v > 0) return '<span class="rp-delta-pill '.($invert ? 'down' : 'up').'"><i class="bi bi-arrow-up-short"></i> '.$v.$suffix.' '.$prevText.'</span>';
        if ($v < 0) return '<span class="rp-delta-pill '.($invert ? 'up' : 'down').'"><i class="bi bi-arrow-down-short"></i> '.abs($v).$suffix.' '.$prevText.'</span>';
        return '<span class="rp-delta-pill flat">— '.$prevText.'</span>';
    };
    $fmtDur = fn ($s) => \App\Models\TimeEntry::formatDuration((int) $s);

    $formattedRange = match ($range->preset) {
        'today' => __('Today'),
        'month' => __('This month'),
        'custom' => (app()->getLocale() === 'fa' ? app_date($range->from).' تا '.app_date($range->to) : $range->label),
        default => __('This week'),
    };
@endphp

@section('content')
<div class="rp-container">

    {{-- Hero Banner --}}
    <div class="rp-hero">
        <div class="rp-hero-content">
            <div>
                <h1 class="rp-hero-title">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>{{ __('Reports') }}</span>
                </h1>
                <p class="rp-hero-sub">
                    {{ __('Tasks') }} · {{ __('Routines') }} · {{ __('Avoid habits') }} · {{ __('Workouts') }} · {{ __('Time') }} — {{ $formattedRange }}
                </p>
            </div>
            <div class="rp-hero-badge">
                <i class="bi bi-calendar3"></i>
                <span>{{ $formattedRange }}</span>
            </div>
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <div class="rp-toolbar">
        <div class="rp-segmented">
            <a href="{{ $link('today') }}" class="rp-segmented-btn {{ $range->preset === 'today' ? 'active' : '' }}">{{ __('Today') }}</a>
            <a href="{{ $link('week') }}" class="rp-segmented-btn {{ $range->preset === 'week' ? 'active' : '' }}">{{ __('Week') }}</a>
            <a href="{{ $link('month') }}" class="rp-segmented-btn {{ $range->preset === 'month' ? 'active' : '' }}">{{ __('Month') }}</a>
            <a href="{{ $link('custom') }}" class="rp-segmented-btn {{ $range->preset === 'custom' ? 'active' : '' }}">{{ __('Custom') }}</a>
        </div>
        <form method="GET" action="{{ route('reports.overview') }}" class="rp-date-form">
            <input type="hidden" name="range" value="custom">
            <input type="date" name="from" value="{{ $q['from'] }}" class="rp-date-input" aria-label="{{ __('From') }}">
            <span style="color:#94a3b8;font-size:12px;">→</span>
            <input type="date" name="to" value="{{ $q['to'] }}" class="rp-date-input" aria-label="{{ __('To') }}">
            <button type="submit" class="rp-apply-btn">{{ __('Apply') }}</button>
        </form>
    </div>

    {{-- Headline KPIs --}}
    <div class="rp-kpis">
        <div class="rp-kpi-card">
            <div class="rp-kpi-header">
                <div class="rp-kpi-icon" style="background:#ede9fe;color:#7c3aed;">
                    <i class="bi bi-check2-square"></i>
                </div>
            </div>
            <div>
                <div class="rp-kpi-val">{{ $tasks['completed'] }}</div>
                <div class="rp-kpi-label">{{ __('Tasks done') }}</div>
            </div>
            {!! $delta($deltas['tasks_completed']) !!}
        </div>

        <div class="rp-kpi-card">
            <div class="rp-kpi-header">
                <div class="rp-kpi-icon" style="background:#e0e7ff;color:#4f46e5;">
                    <i class="bi bi-pie-chart-fill"></i>
                </div>
            </div>
            <div>
                <div class="rp-kpi-val">{{ $tasks['rate'] }}%</div>
                <div class="rp-kpi-label">{{ __('Task completion') }}</div>
            </div>
            {!! $delta($deltas['task_rate'], 'pp') !!}
        </div>

        <div class="rp-kpi-card">
            <div class="rp-kpi-header">
                <div class="rp-kpi-icon" style="background:#dbeafe;color:#2563eb;">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
            </div>
            <div>
                <div class="rp-kpi-val">{{ $routines['avg_rate'] }}%</div>
                <div class="rp-kpi-label">{{ __('Routine adherence') }}</div>
            </div>
            {!! $delta($deltas['routine_rate'], 'pp') !!}
        </div>

        <div class="rp-kpi-card">
            <div class="rp-kpi-header">
                <div class="rp-kpi-icon" style="background:#fee2e2;color:#dc2626;">
                    <i class="bi bi-shield-exclamation"></i>
                </div>
            </div>
            <div>
                <div class="rp-kpi-val">{{ $avoid['slip_total'] }}</div>
                <div class="rp-kpi-label">{{ __('Slips') }}</div>
            </div>
            {!! $delta($deltas['slips'], '', true) !!}
        </div>

        <div class="rp-kpi-card">
            <div class="rp-kpi-header">
                <div class="rp-kpi-icon" style="background:#dcfce7;color:#16a34a;">
                    <i class="bi bi-heart-pulse-fill"></i>
                </div>
            </div>
            <div>
                <div class="rp-kpi-val">{{ $workouts['completed'] }}</div>
                <div class="rp-kpi-label">{{ __('Workouts done') }}</div>
            </div>
            {!! $delta($deltas['workouts']) !!}
        </div>

        <div class="rp-kpi-card">
            <div class="rp-kpi-header">
                <div class="rp-kpi-icon" style="background:#fef3c7;color:#d97706;">
                    <i class="bi bi-stopwatch-fill"></i>
                </div>
            </div>
            <div>
                <div class="rp-kpi-val">{{ $fmtDur($time['total']) }}</div>
                <div class="rp-kpi-label">{{ __('Time tracked') }}</div>
            </div>
            {!! $delta($deltas['time'] !== 0 ? (int) round($deltas['time'] / 3600, 1) : 0, 'h') !!}
        </div>
    </div>

    {{-- Insights --}}
    <div class="rp-section-title" id="insights">
        <i class="bi bi-lightbulb-fill" style="color:#f59e0b;"></i>
        <span>{{ __('Insights') }}</span>
        @if(count($insights))
            <span class="rp-badge-count">{{ count($insights) }}</span>
        @endif
    </div>
    <div class="rp-insights-grid">
        @forelse($insights as $in)
            <div class="rp-insight-card {{ $in['tone'] }}">
                <i class="bi {{ $in['icon'] }} rp-insight-icon"></i>
                <div>
                    <div class="rp-insight-title">{{ $in['title'] }}</div>
                    <div class="rp-insight-body">{{ $in['body'] }}</div>
                </div>
            </div>
        @empty
            <div class="rp-insight-card info">
                <i class="bi bi-info-circle rp-insight-icon"></i>
                <div>
                    <div class="rp-insight-title">{{ __('Not enough data yet') }}</div>
                    <div class="rp-insight-body">{{ __('Complete tasks, check routines or log workouts to unlock insights.') }}</div>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Tasks --}}
    <div class="rp-section-title" id="tasks">
        <i class="bi bi-check2-square" style="color:#6366f1;"></i>
        <span>{{ __('Tasks') }}</span>
    </div>
    <div class="rp-grid2">
        <div class="rp-card">
            <div class="rp-card-header">
                <span class="rp-card-title"><i class="bi bi-bar-chart"></i> {{ __('Completed per day') }}</span>
            </div>
            <div class="rp-card-body">
                @if(array_sum($tasks['per_day']))
                    <div class="rp-chart-container">
                        @foreach($tasks['per_day'] as $d => $n)
                            <div class="rp-bar-col" title="{{ app_date($d) }} — {{ $n }} {{ __('Completed') }}">
                                <div class="rp-bar-inner" style="height: {{ round($n / $tasks['per_day_max'] * 100) }}%;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="rp-axis-row">
                        @foreach($tasks['per_day'] as $d => $n)
                            <span class="rp-axis-cell">{{ \Carbon\Carbon::parse($d)->format('j') }}</span>
                        @endforeach
                    </div>
                @else
                    <div class="rp-empty-state">
                        <i class="bi bi-inbox"></i>
                        <span>{{ __('Nothing completed in this range.') }}</span>
                    </div>
                @endif
            </div>
        </div>
        <div class="rp-card">
            <div class="rp-card-header">
                <span class="rp-card-title"><i class="bi bi-card-checklist"></i> {{ __('Summary') }}</span>
            </div>
            <div class="rp-card-body" style="padding-top:10px;padding-bottom:10px;">
                <div class="rp-list-row">
                    <span class="rp-list-name">{{ __('Created') }}</span>
                    <span class="rp-list-val" style="margin-inline-start:auto;">{{ $tasks['created'] }}</span>
                </div>
                <div class="rp-list-row">
                    <span class="rp-list-name">{{ __('Completed') }}</span>
                    <span class="rp-list-val" style="margin-inline-start:auto;">{{ $tasks['completed'] }}</span>
                </div>
                <div class="rp-list-row">
                    <span class="rp-list-name">{{ __('Avg. completion time') }}</span>
                    <span class="rp-list-val" style="margin-inline-start:auto;">{{ $tasks['avg_hours'] !== null ? $tasks['avg_hours'].' '.__('h') : '—' }}</span>
                </div>
                <div class="rp-list-row">
                    <span class="rp-list-name">{{ __('Overdue now') }}</span>
                    <span class="rp-list-val" style="margin-inline-start:auto;color:{{ $tasks['overdue_now'] ? '#dc2626' : 'inherit' }};font-weight:800;">{{ $tasks['overdue_now'] }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="rp-grid2">
        <div class="rp-card">
            <div class="rp-card-header">
                <span class="rp-card-title"><i class="bi bi-folder2-open"></i> {{ __('Completed by project') }}</span>
            </div>
            <div class="rp-card-body" style="padding-top:10px;padding-bottom:10px;">
                @forelse($tasks['by_project'] as $name => $n)
                    <div class="rp-list-row">
                        <span class="rp-list-name">{{ $name }}</span>
                        <div class="rp-progress-track">
                            <div class="rp-progress-fill" style="width:{{ $tasks['completed'] ? round($n / $tasks['completed'] * 100) : 0 }}%;"></div>
                        </div>
                        <span class="rp-list-val">{{ $n }}</span>
                    </div>
                @empty
                    <div class="rp-empty-state">
                        <i class="bi bi-folder-x"></i>
                        <span>{{ __('No completions to break down.') }}</span>
                    </div>
                @endforelse
            </div>
        </div>
        <div class="rp-card">
            <div class="rp-card-header">
                <span class="rp-card-title"><i class="bi bi-flag"></i> {{ __('Completed by priority') }}</span>
            </div>
            <div class="rp-card-body" style="padding-top:10px;padding-bottom:10px;">
                @php
                    $priorities = [
                        'high' => ['color' => '#dc2626', 'label' => __('High')],
                        'medium' => ['color' => '#d97706', 'label' => __('Medium')],
                        'low' => ['color' => '#16a34a', 'label' => __('Low')],
                    ];
                @endphp
                @foreach($priorities as $p => $meta)
                    <div class="rp-list-row">
                        <span class="rp-pill-tag" style="background:{{ $meta['color'] }}1a;color:{{ $meta['color'] }};">
                            {{ $meta['label'] }}
                        </span>
                        <div class="rp-progress-track">
                            <div class="rp-progress-fill" style="width:{{ $tasks['completed'] ? round(($tasks['by_priority'][$p] ?? 0) / $tasks['completed'] * 100) : 0 }}%;background:{{ $meta['color'] }};"></div>
                        </div>
                        <span class="rp-list-val">{{ $tasks['by_priority'][$p] ?? 0 }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Routines --}}
    <div class="rp-section-title" id="routines">
        <i class="bi bi-arrow-repeat" style="color:#2563eb;"></i>
        <span>{{ __('Routines') }}</span>
    </div>
    <div class="rp-grid2">
        <div class="rp-card">
            <div class="rp-card-header">
                <span class="rp-card-title"><i class="bi bi-bar-chart"></i> {{ __('Completions vs slips per day') }}</span>
            </div>
            <div class="rp-card-body">
                @if(array_sum($routines['per_day_done']) + array_sum($routines['per_day_slipped']))
                    <div class="rp-chart-container">
                        @foreach($routines['per_day_done'] as $d => $n)
                            <div class="rp-bar-col" title="{{ app_date($d) }} — {{ $n }} {{ __('Done') }} · {{ $routines['per_day_slipped'][$d] ?? 0 }} {{ __('Slips') }}">
                                <div class="rp-bar-inner green" style="height: {{ round($n / $routines['per_day_max'] * 100) }}%;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="rp-axis-row">
                        @foreach($routines['per_day_done'] as $d => $n)
                            <span class="rp-axis-cell">{{ \Carbon\Carbon::parse($d)->format('j') }}</span>
                        @endforeach
                    </div>
                @else
                    <div class="rp-empty-state">
                        <i class="bi bi-calendar-x"></i>
                        <span>{{ __('No routine activity in this range.') }}</span>
                    </div>
                @endif
            </div>
        </div>
        <div class="rp-card">
            <div class="rp-card-header">
                <span class="rp-card-title"><i class="bi bi-card-checklist"></i> {{ __('Summary') }}</span>
            </div>
            <div class="rp-card-body" style="padding-top:10px;padding-bottom:10px;">
                <div class="rp-list-row">
                    <span class="rp-list-name">{{ __('Routines (build / avoid)') }}</span>
                    <span class="rp-list-val" style="margin-inline-start:auto;">{{ $routines['total'] }} ({{ $routines['build'] }} / {{ $routines['avoid'] }})</span>
                </div>
                <div class="rp-list-row">
                    <span class="rp-list-name">{{ __('Avg. adherence') }}</span>
                    <span class="rp-list-val" style="margin-inline-start:auto;">{{ $routines['avg_rate'] }}%</span>
                </div>
                <div class="rp-list-row">
                    <span class="rp-list-name">{{ __('Build completions') }}</span>
                    <span class="rp-list-val" style="margin-inline-start:auto;">{{ $routines['build_completed'] }}</span>
                </div>
                <div class="rp-list-row">
                    <span class="rp-list-name">{{ __('Best clean streak') }}</span>
                    <span class="rp-list-val" style="margin-inline-start:auto;color:#16a34a;font-weight:800;">{{ $routines['best_clean_streak'] }}{{ __('d') }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="rp-card">
        <div class="rp-card-header">
            <span class="rp-card-title"><i class="bi bi-list-task"></i> {{ __('Per routine') }}</span>
        </div>
        <div class="rp-card-body" style="padding:4px 8px;">
            @if(count($routines['rows']))
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th>{{ __('Routine') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Done / Occ.') }}</th>
                            <th style="width:34%;">{{ __('Adherence') }}</th>
                            <th>{{ __('Slips') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($routines['rows'] as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('routines.stats', $row['id']) }}" style="color:#0f172a;font-weight:600;text-decoration:none;">
                                        {{ $row['title'] }}
                                    </a>
                                </td>
                                <td>
                                    @if($row['is_avoid'])
                                        <span class="rp-pill-tag" style="background:#fee2e2;color:#b91c1c;">{{ __('Avoid') }}</span>
                                    @else
                                        <span class="rp-pill-tag" style="background:#ede9fe;color:#5b21b6;">{{ __('Build') }}</span>
                                    @endif
                                </td>
                                <td>{{ $row['done'] }} / {{ $row['occurrences'] }}</td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div class="rp-progress-track">
                                            <div class="rp-progress-fill" style="width:{{ $row['rate'] }}%;background:{{ $row['rate'] >= 80 ? '#10b981' : ($row['rate'] >= 50 ? '#4f46e5' : '#f59e0b') }};"></div>
                                        </div>
                                        <span class="rp-list-val" style="min-width:36px;">{{ $row['rate'] }}%</span>
                                    </div>
                                </td>
                                <td>
                                    @if($row['slips'] > 0)
                                        <span style="color:#dc2626;font-weight:700;">{{ $row['slips'] }}</span>
                                    @else
                                        <span style="color:#94a3b8;">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="rp-empty-state">
                    <i class="bi bi-slash-circle"></i>
                    <span>{{ __('No routines yet.') }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Avoid habits --}}
    <div class="rp-section-title" id="avoid">
        <i class="bi bi-shield-fill-check" style="color:#dc2626;"></i>
        <span>{{ __('Avoid habits') }}</span>
    </div>
    @if($avoid['routines'])
        <div class="rp-grid2">
            <div class="rp-card">
                <div class="rp-card-header">
                    <span class="rp-card-title"><i class="bi bi-bar-chart"></i> {{ __('Slips per day') }}</span>
                </div>
                <div class="rp-card-body">
                    @if(array_sum($avoid['per_day']))
                        <div class="rp-chart-container">
                            @foreach($avoid['per_day'] as $d => $n)
                                <div class="rp-bar-col" title="{{ app_date($d) }} — {{ $n }} {{ __('slips') }}">
                                    <div class="rp-bar-inner red" style="height: {{ round($n / $avoid['per_day_max'] * 100) }}%;"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="rp-axis-row">
                            @foreach($avoid['per_day'] as $d => $n)
                                <span class="rp-axis-cell">{{ \Carbon\Carbon::parse($d)->format('j') }}</span>
                            @endforeach
                        </div>
                    @else
                        <div class="rp-empty-state">
                            <i class="bi bi-shield-check" style="color:#10b981;"></i>
                            <span style="color:#10b981;font-weight:600;">{{ __('Zero slips — perfectly clean. 🛡️') }}</span>
                        </div>
                    @endif
                </div>
            </div>
            <div class="rp-card">
                <div class="rp-card-header">
                    <span class="rp-card-title"><i class="bi bi-card-checklist"></i> {{ __('Summary') }}</span>
                </div>
                <div class="rp-card-body" style="padding-top:10px;padding-bottom:10px;">
                    <div class="rp-list-row">
                        <span class="rp-list-name">{{ __('Clean days') }}</span>
                        <span class="rp-list-val" style="margin-inline-start:auto;">{{ $avoid['clean_days'] }} / {{ $avoid['occurrences'] }} ({{ $avoid['clean_rate'] }}%)</span>
                    </div>
                    <div class="rp-list-row">
                        <span class="rp-list-name">{{ __('Slips / slip days') }}</span>
                        <span class="rp-list-val" style="margin-inline-start:auto;color:{{ $avoid['slip_total'] > 0 ? '#dc2626' : '#16a34a' }};">{{ $avoid['slip_total'] }} / {{ $avoid['slip_days'] }}</span>
                    </div>
                    <div class="rp-list-row">
                        <span class="rp-list-name">{{ __('Best clean streak') }}</span>
                        <span class="rp-list-val" style="margin-inline-start:auto;color:#16a34a;font-weight:800;">{{ $avoid['best_clean_streak'] }}{{ __('d') }}</span>
                    </div>
                    <div class="rp-list-row">
                        <span class="rp-list-name">{{ __('Cravings / notes') }}</span>
                        <span class="rp-list-val" style="margin-inline-start:auto;">{{ $avoid['cravings'] }} / {{ $avoid['notes'] }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="rp-grid2">
            <div class="rp-card">
                <div class="rp-card-header">
                    <span class="rp-card-title"><i class="bi bi-clock"></i> {{ __('Slips by time slot') }}</span>
                </div>
                <div class="rp-card-body" style="padding-top:10px;padding-bottom:10px;">
                    @forelse($avoid['by_slot'] as $slot => $n)
                        @php $pc = $avoid['periods'][$slot] ?? null; @endphp
                        <div class="rp-list-row">
                            <span class="rp-pill-tag" style="background:{{ ($pc['color'] ?? '#64748b') }}1a;color:{{ $pc['color'] ?? '#64748b' }};">
                                @if(!empty($pc['icon']))<i class="bi {{ $pc['icon'] }}"></i>@endif
                                {{ $pc['label'] ?? __(ucfirst($slot)) }}
                            </span>
                            <div class="rp-progress-track">
                                <div class="rp-progress-fill" style="width:{{ $avoid['slip_total'] ? round($n / $avoid['slip_total'] * 100) : 0 }}%;background:#ef4444;"></div>
                            </div>
                            <span class="rp-list-val">{{ $n }}</span>
                        </div>
                    @empty
                        <div class="rp-empty-state">
                            <i class="bi bi-emoji-smile"></i>
                            <span>{{ __('No slips to break down.') }}</span>
                        </div>
                    @endforelse
                </div>
            </div>
            <div class="rp-card">
                <div class="rp-card-header">
                    <span class="rp-card-title"><i class="bi bi-lightning-charge"></i> {{ __('Top triggers') }}</span>
                </div>
                <div class="rp-card-body" style="padding-top:10px;padding-bottom:10px;">
                    @forelse($avoid['triggers'] as $t => $n)
                        <div class="rp-list-row">
                            <span class="rp-list-name">{{ $t }}</span>
                            <span class="rp-list-val" style="margin-inline-start:auto;background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:12px;font-size:11px;">×{{ $n }}</span>
                        </div>
                    @empty
                        <div class="rp-empty-state">
                            <i class="bi bi-check2-circle"></i>
                            <span>{{ __('No triggers logged yet.') }}</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="rp-card">
            <div class="rp-card-header">
                <span class="rp-card-title"><i class="bi bi-chat-left-quote"></i> {{ __('Recent cravings & notes') }}</span>
            </div>
            <div class="rp-card-body" style="padding-top:10px;padding-bottom:10px;">
                @forelse($avoid['recent_notes'] as $n)
                    <div class="rp-list-row">
                        <span class="rp-pill-tag" style="background:{{ $n->kind === 'craving' ? '#fee2e2;color:#b91c1c' : '#f1f5f9;color:#475569' }};">
                            {{ $n->kind === 'craving' ? __('Craving') : __('Note') }}
                        </span>
                        <span style="color:#94a3b8;font-size:11.5px;white-space:nowrap;">{{ $n->occurred_at ? app_datetime($n->occurred_at, 'M d · H:i') : '' }}</span>
                        <span style="color:#1e293b;font-weight:500;">{{ $n->note ?: '—' }}</span>
                    </div>
                @empty
                    <div class="rp-empty-state">
                        <i class="bi bi-journal-check"></i>
                        <span>{{ __('Nothing logged yet.') }}</span>
                    </div>
                @endforelse
            </div>
        </div>
    @else
        <div class="rp-card">
            <div class="rp-empty-state">
                <i class="bi bi-shield-plus" style="font-size:32px;color:#cbd5e1;"></i>
                <span style="font-weight:600;color:#64748b;">{{ __('No avoid habits yet — create one from Routines → Habit type → Avoid.') }}</span>
            </div>
        </div>
    @endif

    {{-- Workouts --}}
    <div class="rp-section-title" id="workouts">
        <i class="bi bi-heart-pulse-fill" style="color:#16a34a;"></i>
        <span>{{ __('Workouts') }}</span>
    </div>
    <div class="rp-grid2">
        <div class="rp-card">
            <div class="rp-card-header">
                <span class="rp-card-title"><i class="bi bi-bar-chart"></i> {{ __('Sessions per day') }}</span>
            </div>
            <div class="rp-card-body">
                @if(array_sum($workouts['per_day']))
                    <div class="rp-chart-container">
                        @foreach($workouts['per_day'] as $d => $n)
                            <div class="rp-bar-col" title="{{ app_date($d) }} — {{ $n }} {{ __('Workouts done') }}">
                                <div class="rp-bar-inner green" style="height: {{ round($n / $workouts['per_day_max'] * 100) }}%;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="rp-axis-row">
                        @foreach($workouts['per_day'] as $d => $n)
                            <span class="rp-axis-cell">{{ \Carbon\Carbon::parse($d)->format('j') }}</span>
                        @endforeach
                    </div>
                @else
                    <div class="rp-empty-state">
                        <i class="bi bi-activity"></i>
                        <span>{{ __('No completed sessions in this range.') }}</span>
                    </div>
                @endif
            </div>
        </div>
        <div class="rp-card">
            <div class="rp-card-header">
                <span class="rp-card-title"><i class="bi bi-card-checklist"></i> {{ __('Summary') }}</span>
            </div>
            <div class="rp-card-body" style="padding-top:10px;padding-bottom:10px;">
                <div class="rp-list-row">
                    <span class="rp-list-name">{{ __('Sessions (completed)') }}</span>
                    <span class="rp-list-val" style="margin-inline-start:auto;">{{ $workouts['sessions'] }} ({{ $workouts['completed'] }})</span>
                </div>
                <div class="rp-list-row">
                    <span class="rp-list-name">{{ __('Minutes') }}</span>
                    <span class="rp-list-val" style="margin-inline-start:auto;">{{ $workouts['minutes'] }} {{ __('min') }}</span>
                </div>
                <div class="rp-list-row">
                    <span class="rp-list-name">{{ __('Sets / reps') }}</span>
                    <span class="rp-list-val" style="margin-inline-start:auto;">{{ $workouts['sets'] }} / {{ $workouts['reps'] }}</span>
                </div>
                <div class="rp-list-row">
                    <span class="rp-list-name">{{ __('Volume') }}</span>
                    <span class="rp-list-val" style="margin-inline-start:auto;font-weight:800;">{{ number_format($workouts['volume']) }} {{ __('kg') }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Time --}}
    <div class="rp-section-title" id="time">
        <i class="bi bi-stopwatch-fill" style="color:#7c3aed;"></i>
        <span>{{ __('Time') }}</span>
    </div>
    <div class="rp-grid2">
        <div class="rp-card">
            <div class="rp-card-header">
                <span class="rp-card-title"><i class="bi bi-bar-chart"></i> {{ __('Tracked hours per day') }}</span>
            </div>
            <div class="rp-card-body">
                @if(array_sum($time['per_day']))
                    <div class="rp-chart-container">
                        @foreach($time['per_day'] as $d => $s)
                            <div class="rp-bar-col" title="{{ app_date($d) }} — {{ $fmtDur($s) }}">
                                <div class="rp-bar-inner" style="height: {{ round($s / $time['per_day_max'] * 100) }}%;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="rp-axis-row">
                        @foreach($time['per_day'] as $d => $s)
                            <span class="rp-axis-cell">{{ \Carbon\Carbon::parse($d)->format('j') }}</span>
                        @endforeach
                    </div>
                @else
                    <div class="rp-empty-state">
                        <i class="bi bi-stopwatch"></i>
                        <span>{{ __('No tracked time in this range.') }}</span>
                    </div>
                @endif
            </div>
        </div>
        <div class="rp-card">
            <div class="rp-card-header">
                <span class="rp-card-title">
                    <i class="bi bi-folder2-open"></i>
                    {{ __('By project · :time in :sessions sessions', ['time' => $fmtDur($time['total']), 'sessions' => $time['sessions']]) }}
                </span>
            </div>
            <div class="rp-card-body" style="padding-top:10px;padding-bottom:10px;">
                @forelse($time['by_project'] as $name => $s)
                    <div class="rp-list-row">
                        <span class="rp-list-name">{{ $name }}</span>
                        <div class="rp-progress-track">
                            <div class="rp-progress-fill" style="width:{{ $time['total'] ? round($s / $time['total'] * 100) : 0 }}%;"></div>
                        </div>
                        <span class="rp-list-val">{{ $fmtDur($s) }}</span>
                    </div>
                @empty
                    <div class="rp-empty-state">
                        <i class="bi bi-clock-history"></i>
                        <span>{{ __('No tracked time in this range yet.') }}</span>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection
