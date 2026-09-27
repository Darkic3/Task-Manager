@extends('layouts.app')

@section('title', 'Workout Plans')

@push('styles')
<style>
    .wp-shell{padding:22px 24px 48px;background:linear-gradient(180deg,#f6f8fd 0%,#f2f5fb 100%);min-height:100vh}
    .wp-wrap{max-width:1180px;margin:auto}
    .wp-head{display:flex;align-items:center;gap:14px;margin-bottom:22px;flex-wrap:wrap}
    .wp-head-main{flex:1;min-width:220px}
    .wp-breadcrumb{display:inline-flex;align-items:center;gap:6px;font-size:11.5px;font-weight:700;color:#818cf8;text-decoration:none;margin-bottom:6px}
    .wp-breadcrumb:hover{color:#4338ca;text-decoration:none}
    .wp-head h1{margin:0;font-size:24px;font-weight:800;color:#0f172a;letter-spacing:-.02em;display:flex;align-items:center;gap:10px}
    .wp-head h1 .wp-h1-icon{width:40px;height:40px;border-radius:11px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;display:grid;place-items:center;font-size:1.1rem;box-shadow:0 6px 16px rgba(99,102,241,.35)}
    .wp-head p{margin:4px 0 0;color:#64748b;font-size:13px}
    .wp-actions{display:flex;gap:8px;flex-wrap:wrap}
    .wp-btn{display:inline-flex;align-items:center;gap:6px;border:1px solid #dbe3ee;border-radius:10px;padding:8px 14px;font-size:12.5px;font-weight:700;text-decoration:none;color:#475569;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04);transition:all .15s ease}
    .wp-btn:hover{color:#4338ca;border-color:#a5b4fc;background:#f8faff;transform:translateY(-1px);box-shadow:0 3px 8px rgba(15,23,42,.06)}
    .wp-btn.primary{background:linear-gradient(135deg,#4f46e5,#6366f1);border-color:#4f46e5;color:#fff;box-shadow:0 4px 12px rgba(79,70,229,.28)}
    .wp-btn.primary:hover{background:linear-gradient(135deg,#4338ca,#4f46e5);color:#fff;box-shadow:0 6px 16px rgba(79,70,229,.35)}
    .wp-btn.icon{padding:8px 10px}
    .wp-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}
    .wp-stat{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(15,23,42,.04);display:flex;align-items:center;gap:12px}
    .wp-stat-icon{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:1rem;flex-shrink:0}
    .wp-stat-icon.indigo{background:#eef2ff;color:#4338ca}
    .wp-stat-icon.green{background:#dcfce7;color:#15803d}
    .wp-stat-icon.amber{background:#fef3c7;color:#b45309}
    .wp-stat-icon.sky{background:#e0f2fe;color:#0369a1}
    .wp-stat-value{font-size:20px;font-weight:800;color:#0f172a;line-height:1.1}
    .wp-stat-label{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-top:2px}
    .wp-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .wp-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;box-shadow:0 1px 2px rgba(15,23,42,.05);transition:box-shadow .18s ease,transform .18s ease,border-color .18s ease;display:flex;flex-direction:column;position:relative;overflow:hidden}
    .wp-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#6366f1,#8b5cf6,#a78bfa);opacity:0;transition:opacity .18s ease}
    .wp-card:hover{box-shadow:0 12px 28px rgba(15,23,42,.1);transform:translateY(-2px);border-color:#c7d2fe}
    .wp-card:hover::before{opacity:1}
    .wp-card-top{display:flex;align-items:flex-start;gap:10px}
    .wp-card-top>div:first-child{flex:1;min-width:0}
    .wp-title{font-size:15.5px;font-weight:800;color:#1e293b;line-height:1.3}
    .wp-sub{font-size:12px;color:#64748b;margin-top:4px;display:flex;align-items:center;gap:5px;flex-wrap:wrap}
    .wp-sub .dot{width:3px;height:3px;border-radius:50%;background:#cbd5e1;display:inline-block}
    .wp-status{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;border-radius:20px;padding:4px 10px;background:#eef2ff;color:#4338ca;display:inline-flex;align-items:center;gap:4px;flex-shrink:0}
    .wp-status::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor}
    .wp-status.active{background:#dcfce7;color:#15803d}
    .wp-status.draft{background:#fef3c7;color:#b45309}
    .wp-days{display:grid;grid-template-columns:repeat(7,1fr);gap:5px;margin:16px 0 14px}
    .wp-day{border-radius:9px;background:#f8fafc;border:1px solid #eef2f7;padding:9px 3px;text-align:center;transition:transform .15s ease}
    .wp-day:hover{transform:translateY(-1px)}
    .wp-day strong{display:block;font-size:9.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;font-weight:800}
    .wp-day span{display:block;font-size:10.5px;color:#334155;margin-top:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .wp-day.training{background:#eef2ff;border-color:#c7d2fe}
    .wp-day.training strong{color:#6366f1}
    .wp-day.training span{color:#4338ca;font-weight:700}
    .wp-day.recovery{background:#ecfeff;border-color:#a5f3fc}
    .wp-day.recovery strong{color:#06b6d4}
    .wp-day.recovery span{color:#0e7490;font-weight:700}
    .wp-day.rest span{color:#94a3b8}
    .wp-foot{display:flex;align-items:center;border-top:1px solid #eef2f7;padding-top:13px;margin-top:auto;gap:8px}
    .wp-foot small{color:#94a3b8;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:4px}
    .wp-foot small i{color:#c7d2fe}
    .wp-actions-foot{display:flex;gap:6px;margin-left:auto}
    .wp-actions-foot .form{display:inline-flex}
    .wp-btn-sm{padding:7px 11px;font-size:11.5px;border-radius:9px}
    .wp-start{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:14px;border:1px solid #bbf7d0;background:linear-gradient(135deg,#f0fdf4,#ecfdf5);color:#15803d;border-radius:11px;padding:10px;font-size:12.5px;font-weight:800;text-decoration:none;transition:all .15s ease}
    .wp-start:hover{background:linear-gradient(135deg,#dcfce7,#d1fae5);border-color:#86efac;transform:translateY(-1px)}
    .wp-start i{font-size:1rem}
    .wp-empty{text-align:center;padding:70px 24px;background:#fff;border:2px dashed #d3dcec;border-radius:20px;color:#64748b}
    .wp-empty .wp-empty-icon{width:74px;height:74px;border-radius:22px;background:linear-gradient(135deg,#eef2ff,#f3e8ff);display:grid;place-items:center;margin:0 auto 16px;font-size:2rem;color:#6366f1}
    .wp-empty strong{display:block;font-size:16px;color:#1e293b;margin-bottom:5px}
    .wp-empty p{font-size:13px;color:#94a3b8}
    @media(max-width:820px){.wp-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.wp-list{grid-template-columns:1fr}}
    @media(max-width:600px){.wp-shell{padding:14px 12px 40px}.wp-head .wp-btn span{display:none}.wp-btn.icon{display:none}.wp-actions{width:100%;justify-content:flex-start}.wp-head h1{font-size:20px}.wp-start{padding:9px}}
</style>
@endpush

@php
    $activePlans = $plans->where('status', 'active');
    $totalMovements = $plans->sum(fn ($plan) => $plan->days->sum('exercises_count'));
    $todayPlans = $plans->filter(function ($plan) {
        $todayDay = $plan->days->firstWhere('weekday', strtolower(now()->format('l')));
        return $todayDay && in_array($todayDay->type, ['training', 'recovery'], true);
    });
@endphp

@section('content')
<div class="wp-shell"><div class="wp-wrap">
    <div class="wp-head">
        <div class="wp-head-main">
            <a class="wp-breadcrumb" href="{{ route('dashboard') }}"><i class="bi bi-house-door"></i> Dashboard <i class="bi bi-chevron-right" style="font-size:.7em"></i> Workouts</a>
            <h1><span class="wp-h1-icon"><i class="bi bi-activity"></i></span> Workout Plans</h1>
            <p>Build a complete week, reuse your exercise library, and keep every cycle separate.</p>
        </div>
        <div class="wp-actions">
            <a class="wp-btn" href="{{ route('workouts.exercises.index') }}"><i class="bi bi-heart-pulse"></i><span class="d-none d-lg-inline">Exercise library</span></a>
            <a class="wp-btn" href="{{ route('workouts.imports.create') }}"><i class="bi bi-stars"></i><span class="d-none d-lg-inline">Import with AI</span></a>
            <a class="wp-btn" href="{{ route('workouts.reports.index') }}"><i class="bi bi-bar-chart"></i><span class="d-none d-lg-inline">Reports</span></a>
            <a class="wp-btn primary" href="{{ route('workouts.plans.create') }}"><i class="bi bi-plus-lg"></i><span>New plan</span></a>
        </div>
    </div>

    @if($plans->count())
        <div class="wp-stats">
            <div class="wp-stat"><div class="wp-stat-icon indigo"><i class="bi bi-collection"></i></div><div><div class="wp-stat-value">{{ $plans->count() }}</div><div class="wp-stat-label">Total plans</div></div></div>
            <div class="wp-stat"><div class="wp-stat-icon green"><i class="bi bi-play-circle"></i></div><div><div class="wp-stat-value">{{ $activePlans->count() }}</div><div class="wp-stat-label">Active plans</div></div></div>
            <div class="wp-stat"><div class="wp-stat-icon amber"><i class="bi bi-calendar-check"></i></div><div><div class="wp-stat-value">{{ $todayPlans->count() }}</div><div class="wp-stat-label">Sessions today</div></div></div>
            <div class="wp-stat"><div class="wp-stat-icon sky"><i class="bi bi-repeat"></i></div><div><div class="wp-stat-value">{{ $totalMovements }}</div><div class="wp-stat-label">Planned movements</div></div></div>
        </div>
    @endif

    @if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif

    @if($plans->count())
        <div class="wp-list">
            @foreach($plans as $plan)
                <article class="wp-card">
                    <div class="wp-card-top">
                        <div>
                            <div class="wp-title">{{ $plan->title }}</div>
                            <div class="wp-sub">{{ $plan->goal ?: 'No goal set' }}@if($plan->week_number)<span class="dot"></span>Week {{ $plan->week_number }}@endif</div>
                        </div>
                        <span class="wp-status {{ $plan->status }}">{{ $plan->status }}</span>
                    </div>
                    <div class="wp-days">@foreach($plan->orderedDays() as $day)<div class="wp-day {{ $day->type }}" title="{{ $day->title }}"><strong>{{ __(substr($day->weekday, 0, 3)) }}</strong><span>{{ $day->type === 'rest' ? 'Rest' : $day->title }}</span></div>@endforeach</div>
                    <div class="wp-foot">
                        <small><i class="bi bi-calendar3"></i> {{ $plan->days_count }} days</small>
                        <small><i class="bi bi-heart-pulse"></i> {{ $plan->days->sum('exercises_count') }} movements</small>
                        <small><i class="bi bi-arrow-clockwise"></i> Cycle {{ $plan->cycle_no }}</small>
                        <div class="wp-actions-foot">
                            <a class="wp-btn wp-btn-sm" href="{{ route('workouts.plans.edit', $plan) }}" title="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('workouts.plans.new-cycle', $plan) }}" onsubmit="return confirm('Create a new cycle from this plan?')">@csrf<button class="wp-btn wp-btn-sm" type="submit" title="New cycle"><i class="bi bi-files"></i></button></form>
                            <form method="POST" action="{{ route('workouts.plans.destroy', $plan) }}" onsubmit="return confirm('Archive this workout plan?')">@csrf @method('DELETE')<button class="wp-btn wp-btn-sm" type="submit" title="Archive"><i class="bi bi-archive"></i></button></form>
                        </div>
                    </div>
                    @php $todayDay = $plan->days->firstWhere('weekday', strtolower(now()->format('l'))); @endphp
                    @if($todayDay && in_array($todayDay->type, ['training', 'recovery'], true))
                        <form method="POST" action="{{ route('workouts.sessions.start', $todayDay) }}">@csrf<input type="hidden" name="date" value="{{ now()->toDateString() }}"><button class="wp-start border-0 w-100" type="submit"><i class="bi bi-play-fill"></i> Start today’s {{ $todayDay->title }}</button></form>
                    @else
                        <div class="wp-start" style="background:#f8fafc;border-color:#eef2f7;color:#94a3b8"><i class="bi bi-moon-stars"></i> Nothing on today’s schedule</div>
                    @endif
                </article>
            @endforeach
        </div>
    @else
        <div class="wp-empty">
            <div class="wp-empty-icon"><i class="bi bi-calendar2-week"></i></div>
            <strong>No workout plans yet</strong>
            <p class="mb-3">Create your first structured weekly plan.</p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a class="wp-btn primary" href="{{ route('workouts.plans.create') }}"><i class="bi bi-plus-lg"></i> Build a plan</a>
                <a class="wp-btn" href="{{ route('workouts.imports.create') }}"><i class="bi bi-stars"></i> Import with AI</a>
            </div>
        </div>
    @endif
</div></div>
@endsection
