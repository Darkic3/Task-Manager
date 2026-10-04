@extends('layouts.app')

@section('title', __('Workout Plans | Weekly Cycles & Training Programs'))

@push('styles')
<style>
    .wp-shell {
        padding: 24px 20px 60px;
        background: #f8fafc;
        min-height: 100vh;
    }
    .wp-wrap {
        max-width: 1200px;
        margin: auto;
    }
    
    /* Header */
    .wp-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }
    .wp-head-main {
        flex: 1;
        min-width: 260px;
    }
    .wp-breadcrumb {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 700;
        color: var(--primary-600);
        text-decoration: none;
        margin-bottom: 6px;
    }
    .wp-breadcrumb:hover {
        color: var(--primary-800);
    }
    .wp-title-h1 {
        margin: 0;
        font-size: 26px;
        font-weight: 900;
        color: #0f172a;
        letter-spacing: -0.02em;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .wp-title-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff;
        display: grid;
        place-items: center;
        font-size: 1.25rem;
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.28);
    }
    .wp-subtext {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 13.5px;
    }
    
    /* Buttons */
    .wp-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    .wp-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 12.5px;
        font-weight: 700;
        text-decoration: none;
        color: #475569;
        background: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        transition: all 0.15s ease;
    }
    .wp-btn:hover {
        color: var(--primary-700);
        border-color: #c7d2fe;
        background: #f8faff;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.06);
    }
    .wp-btn.primary {
        background: linear-gradient(135deg, #4f46e5, #6366f1);
        border-color: #4f46e5;
        color: #fff;
        box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
    }
    .wp-btn.primary:hover {
        background: linear-gradient(135deg, #4338ca, #4f46e5);
        color: #fff;
        box-shadow: 0 6px 18px rgba(79, 70, 229, 0.4);
    }

    /* Metric Stat Cards */
    .wp-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 22px;
    }
    .wp-stat {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 14px 16px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
        display: flex;
        align-items: center;
        gap: 12px;
        transition: transform 0.15s ease, border-color 0.15s ease;
    }
    .wp-stat:hover {
        transform: translateY(-1px);
        border-color: #cbd5e1;
    }
    .wp-stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }
    .wp-stat-icon.indigo { background: #eef2ff; color: #4338ca; }
    .wp-stat-icon.green  { background: #dcfce7; color: #15803d; }
    .wp-stat-icon.amber  { background: #fef3c7; color: #b45309; }
    .wp-stat-icon.sky    { background: #e0f2fe; color: #0369a1; }
    .wp-stat-value {
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
    }
    .wp-stat-label {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #94a3b8;
        margin-top: 2px;
    }

    /* Plans Grid */
    .wp-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }
    .wp-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
    }
    .wp-card:hover {
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        transform: translateY(-2px);
        border-color: #c7d2fe;
    }
    .wp-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3.5px;
        background: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899);
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    .wp-card:hover::before { opacity: 1; }

    .wp-card-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 12px;
    }
    .wp-title {
        font-size: 17px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.3;
    }
    .wp-sub {
        font-size: 12.5px;
        color: #64748b;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .wp-sub .dot {
        width: 3.5px;
        height: 3.5px;
        border-radius: 50%;
        background: #cbd5e1;
        display: inline-block;
    }
    
    .wp-status {
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-radius: 20px;
        padding: 4px 10px;
        background: #eef2ff;
        color: #4338ca;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        flex-shrink: 0;
    }
    .wp-status::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }
    .wp-status.active { background: #dcfce7; color: #15803d; }
    .wp-status.draft  { background: #fef3c7; color: #b45309; }

    /* Modern Day Cards Strip (Fixed Layout) */
    .wp-days-container {
        margin: 14px 0 16px;
    }
    .wp-days-scroll {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 6px;
    }
    .wp-day-tile {
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid #eef2f7;
        padding: 9px 6px;
        text-align: center;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 80px;
        position: relative;
        transition: all 0.15s ease;
        text-decoration: none;
    }
    .wp-day-tile:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.04);
    }
    .wp-day-tile.is-today {
        border-color: #6366f1 !important;
        background: #ffffff !important;
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25);
    }
    .wp-day-tile.is-today::after {
        content: '{{ __('TODAY') }}';
        position: absolute;
        top: -6px;
        left: 50%;
        transform: translateX(-50%);
        background: #6366f1;
        color: white;
        font-size: 8px;
        font-weight: 800;
        padding: 1px 5px;
        border-radius: 6px;
        letter-spacing: 0.05em;
    }

    .wp-day-weekday {
        font-size: 10px;
        color: #64748b;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .wp-day-name {
        font-size: 11px;
        font-weight: 700;
        color: #1e293b;
        margin: 3px 0;
        line-height: 1.25;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }
    .wp-day-badge {
        font-size: 9.5px;
        font-weight: 700;
        border-radius: 10px;
        padding: 2px 4px;
        margin-top: auto;
    }

    .wp-day-tile.training {
        background: #f5f3ff;
        border-color: #ede9fe;
    }
    .wp-day-tile.training .wp-day-weekday { color: #7c3aed; }
    .wp-day-tile.training .wp-day-name { color: #5b21b6; }
    .wp-day-tile.training .wp-day-badge { background: #ede9fe; color: #6d28d9; }

    .wp-day-tile.recovery {
        background: #ecfeff;
        border-color: #cffafe;
    }
    .wp-day-tile.recovery .wp-day-weekday { color: #0891b2; }
    .wp-day-tile.recovery .wp-day-name { color: #0e7490; }
    .wp-day-tile.recovery .wp-day-badge { background: #cffafe; color: #0891b2; }

    .wp-day-tile.rest {
        background: #f8fafc;
        border-color: #f1f5f9;
        opacity: 0.85;
    }
    .wp-day-tile.rest .wp-day-weekday { color: #94a3b8; }
    .wp-day-tile.rest .wp-day-name { color: #94a3b8; font-weight: 600; }
    .wp-day-tile.rest .wp-day-badge { background: #f1f5f9; color: #94a3b8; }

    /* Footer & Quick Actions */
    .wp-card-foot {
        display: flex;
        align-items: center;
        border-top: 1px solid #f1f5f9;
        padding-top: 14px;
        margin-top: auto;
        gap: 12px;
        flex-wrap: wrap;
    }
    .wp-meta-pill {
        color: #64748b;
        font-size: 11.5px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .wp-meta-pill i { color: #818cf8; }

    .wp-actions-group {
        display: flex;
        gap: 6px;
        margin-left: auto;
    }
    .wp-icon-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.15s;
    }
    .wp-icon-btn:hover {
        background: #f8fafc;
        color: var(--primary-700);
        border-color: #c7d2fe;
    }

    /* Start Workout Today Banner */
    .wp-start-today {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 14px;
        background: linear-gradient(135deg, #10b981, #059669);
        color: #fff;
        border-radius: 12px;
        padding: 11px 16px;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        transition: all 0.15s ease;
    }
    .wp-start-today:hover {
        background: linear-gradient(135deg, #059669, #047857);
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
    }
    .wp-start-today.rest {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #64748b;
        box-shadow: none;
        cursor: default;
    }

    /* Empty state */
    .wp-empty {
        text-align: center;
        padding: 70px 24px;
        background: #fff;
        border: 2px dashed #cbd5e1;
        border-radius: 20px;
        color: #64748b;
    }
    .wp-empty-icon {
        width: 74px;
        height: 74px;
        border-radius: 22px;
        background: linear-gradient(135deg, #eef2ff, #f3e8ff);
        display: grid;
        place-items: center;
        margin: 0 auto 16px;
        font-size: 2rem;
        color: #6366f1;
    }

    @media (max-width: 992px) {
        .wp-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .wp-list { grid-template-columns: 1fr; }
        .wp-days-scroll {
            overflow-x: auto;
            padding-bottom: 6px;
            grid-template-columns: repeat(7, minmax(80px, 1fr));
        }
    }
    @media (max-width: 600px) {
        .wp-shell { padding: 16px 12px 60px; }
        .wp-title-h1 { font-size: 22px; }
        .wp-stats { grid-template-columns: 1fr 1fr; gap: 8px; }
        .wp-actions { width: 100%; }
        .wp-actions .wp-btn { flex: 1; justify-content: center; }
    }
</style>
@endpush

@php
    $todayWeekday = strtolower(now()->format('l'));
    $activePlans = $plans->where('status', 'active');
    $totalMovements = $plans->sum(fn ($plan) => $plan->days->sum('exercises_count'));
    $todayPlans = $plans->filter(function ($plan) use ($todayWeekday) {
        $todayDay = $plan->days->firstWhere('weekday', $todayWeekday);
        return $todayDay && in_array($todayDay->type, ['training', 'recovery'], true);
    });
@endphp

@section('content')
<div class="wp-shell">
    <div class="wp-wrap">
        
        {{-- Top Header --}}
        <div class="wp-head">
            <div class="wp-head-main">
                <a class="wp-breadcrumb" href="{{ route('dashboard') }}">
                    <i class="bi bi-house-door"></i> {{ __('Dashboard') }} <i class="bi bi-chevron-right" style="font-size: 0.7em"></i> {{ __('Workouts') }}
                </a>
                <h1 class="wp-title-h1">
                    <span class="wp-title-icon"><i class="bi bi-activity"></i></span>
                    {{ __('Workout Plans & Cycles') }}
                </h1>
                <p class="wp-subtext">{{ __('Structure your weekly training split, track progressive overload, and reuse exercise libraries.') }}</p>
            </div>
            <div class="wp-actions">
                <a class="wp-btn" href="{{ route('workouts.exercises.index') }}" title="{{ __('Exercise Library') }}">
                    <i class="bi bi-heart-pulse text-danger"></i> <span>{{ __('Exercises') }}</span>
                </a>
                <a class="wp-btn" href="{{ route('workouts.imports.create') }}" title="{{ __('Import Plan with AI') }}">
                    <i class="bi bi-stars text-primary"></i> <span>{{ __('Import with AI') }}</span>
                </a>
                <a class="wp-btn" href="{{ route('workouts.reports.index') }}" title="{{ __('Volume Analytics') }}">
                    <i class="bi bi-bar-chart text-success"></i> <span>{{ __('Reports') }}</span>
                </a>
                <a class="wp-btn primary" href="{{ route('workouts.plans.create') }}">
                    <i class="bi bi-plus-lg"></i> <span>{{ __('New Plan') }}</span>
                </a>
            </div>
        </div>

        {{-- Stat Metric Counters --}}
        @if($plans->count())
            <div class="wp-stats">
                <div class="wp-stat">
                    <div class="wp-stat-icon indigo"><i class="bi bi-collection-play"></i></div>
                    <div>
                        <div class="wp-stat-value">{{ $plans->count() }}</div>
                        <div class="wp-stat-label">{{ __('Total Plans') }}</div>
                    </div>
                </div>
                <div class="wp-stat">
                    <div class="wp-stat-icon green"><i class="bi bi-play-circle-fill"></i></div>
                    <div>
                        <div class="wp-stat-value">{{ $activePlans->count() }}</div>
                        <div class="wp-stat-label">{{ __('Active Plans') }}</div>
                    </div>
                </div>
                <div class="wp-stat">
                    <div class="wp-stat-icon amber"><i class="bi bi-fire"></i></div>
                    <div>
                        <div class="wp-stat-value">{{ $todayPlans->count() }}</div>
                        <div class="wp-stat-label">{{ __('Scheduled Today') }}</div>
                    </div>
                </div>
                <div class="wp-stat">
                    <div class="wp-stat-icon sky"><i class="bi bi-lightning-charge"></i></div>
                    <div>
                        <div class="wp-stat-value">{{ $totalMovements }}</div>
                        <div class="wp-stat-label">{{ __('Planned Movements') }}</div>
                    </div>
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 p-3 rounded-4 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                <div class="fw-semibold">{{ session('success') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
            </div>
        @endif

        {{-- Plans List --}}
        @if($plans->count())
            <div class="wp-list">
                @foreach($plans as $plan)
                    @php
                        $todayDay = $plan->days->firstWhere('weekday', $todayWeekday);
                        $isTodayTraining = $todayDay && in_array($todayDay->type, ['training', 'recovery'], true);
                    @endphp
                    <article class="wp-card">
                        {{-- Card Header --}}
                        <div class="wp-card-head">
                            <div>
                                <h3 class="wp-title m-0">{{ $plan->title }}</h3>
                                <div class="wp-sub">
                                    <span>{{ $plan->goal ?: __('General Fitness') }}</span>
                                    @if($plan->week_number)
                                        <span class="dot"></span>
                                        <span class="badge bg-primary-subtle text-primary border" style="font-size: 10px;">{{ __('Week') }} {{ $plan->week_number }}</span>
                                    @endif
                                    @if($plan->start_date)
                                        <span class="dot"></span>
                                        <span>{{ __('Starts') }} {{ $plan->start_date->format('M j') }}</span>
                                    @endif
                                </div>
                            </div>
                            <span class="wp-status {{ $plan->status }}">{{ $plan->status }}</span>
                        </div>

                        {{-- 7-Day Visual Strip --}}
                        <div class="wp-days-container">
                            <div class="wp-days-scroll">
                                @foreach($plan->orderedDays() as $day)
                                    @php
                                        $isDayToday = ($day->weekday === $todayWeekday);
                                        $exCount = $day->exercises_count ?? $day->exercises->count();
                                    @endphp
                                    <div class="wp-day-tile {{ $day->type }} {{ $isDayToday ? 'is-today' : '' }}" title="{{ $day->title }} ({{ ucfirst($day->weekday) }})">
                                        <div class="wp-day-weekday">{{ strtoupper(substr($day->weekday, 0, 3)) }}</div>
                                        <div class="wp-day-name">{{ $day->type === 'rest' ? 'Rest' : $day->title }}</div>
                                        <div class="wp-day-badge">
                                            @if($day->type === 'training')
                                                {{ $exCount }} moves
                                            @elseif($day->type === 'recovery')
                                                Recovery
                                            @else
                                                Rest
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Card Footer & Meta --}}
                        <div class="wp-card-foot">
                            <span class="wp-meta-pill">
                                <i class="bi bi-calendar3"></i> {{ $plan->days_count }} days
                            </span>
                            <span class="wp-meta-pill">
                                <i class="bi bi-heart-pulse"></i> {{ $plan->days->sum('exercises_count') }} exercises
                            </span>
                            <span class="wp-meta-pill">
                                <i class="bi bi-arrow-repeat"></i> Cycle {{ $plan->cycle_no }}
                            </span>

                            <div class="wp-actions-group">
                                <a class="wp-icon-btn" href="{{ route('workouts.plans.edit', $plan) }}" title="Edit Plan">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('workouts.plans.new-cycle', $plan) }}" onsubmit="return confirmSwal(this, '{{ __('Create a new cycle from this plan?') }}', { isDelete: false })" class="d-inline">
                                    @csrf
                                    <button class="wp-icon-btn" type="submit" title="Duplicate as New Cycle">
                                        <i class="bi bi-files"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('workouts.plans.destroy', $plan) }}" onsubmit="return confirmSwal(this, '{{ __('Archive this workout plan?') }}', { isDelete: true })" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="wp-icon-btn text-danger" type="submit" title="Archive Plan">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Start Today Action --}}
                        @if($isTodayTraining)
                            <form method="POST" action="{{ route('workouts.sessions.start', $todayDay) }}" class="mt-2">
                                @csrf
                                <input type="hidden" name="date" value="{{ now()->toDateString() }}">
                                <button class="wp-start-today w-100 border-0" type="submit">
                                    <i class="bi bi-play-circle-fill fs-6"></i>
                                    <span>Start Today’s Session: {{ $todayDay->title }} ({{ $todayDay->exercises->count() }} movements)</span>
                                </button>
                            </form>
                        @else
                            <div class="wp-start-today rest mt-2">
                                <i class="bi bi-moon-stars text-muted"></i>
                                <span>No session planned for today (Rest Day)</span>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @else
            <div class="wp-empty">
                <div class="wp-empty-icon"><i class="bi bi-calendar2-week"></i></div>
                <h4 class="fw-bold text-dark">No workout plans yet</h4>
                <p class="mb-4">Build your first structured weekly routine or paste any text to import with AI.</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a class="wp-btn primary" href="{{ route('workouts.plans.create') }}">
                        <i class="bi bi-plus-lg"></i> Build a Plan Manually
                    </a>
                    <a class="wp-btn" href="{{ route('workouts.imports.create') }}">
                        <i class="bi bi-stars text-primary"></i> Import with AI
                    </a>
                </div>
            </div>
        @endif

    </div>
</div>
@endsection
