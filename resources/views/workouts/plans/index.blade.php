@extends('layouts.app')

@section('title', 'Workout Plans')

@push('styles')
<style>
    .wp-shell{padding:22px 24px 48px;background:#f7f9fc;min-height:100vh}.wp-wrap{max-width:1120px;margin:auto}.wp-head{display:flex;align-items:center;gap:12px;margin-bottom:18px}.wp-head-main{flex:1}.wp-head h1{margin:0;font-size:22px;font-weight:800;color:#0f172a}.wp-head p{margin:4px 0 0;color:#64748b;font-size:12px}.wp-btn{display:inline-flex;align-items:center;gap:6px;border:1px solid #dbe3ee;border-radius:9px;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none;color:#475569;background:#fff}.wp-btn:hover{color:#4338ca;border-color:#a5b4fc;background:#f8faff}.wp-btn.primary{background:#4f46e5;border-color:#4f46e5;color:#fff}.wp-btn.primary:hover{background:#4338ca;color:#fff}.wp-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.wp-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:17px;box-shadow:0 5px 18px rgba(15,23,42,.04)}.wp-card-top{display:flex;align-items:flex-start;gap:10px}.wp-card-top>div:first-child{flex:1}.wp-title{font-size:15px;font-weight:800;color:#1e293b}.wp-sub{font-size:11px;color:#64748b;margin-top:4px}.wp-status{font-size:10px;font-weight:800;text-transform:uppercase;border-radius:20px;padding:4px 8px;background:#eef2ff;color:#4338ca}.wp-status.active{background:#dcfce7;color:#15803d}.wp-days{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin:16px 0}.wp-day{border-radius:7px;background:#f8fafc;border:1px solid #eef2f7;padding:7px 3px;text-align:center}.wp-day strong{display:block;font-size:10px;color:#64748b;text-transform:uppercase}.wp-day span{display:block;font-size:10px;color:#1e293b;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.wp-day.training{background:#eef2ff;border-color:#c7d2fe}.wp-day.training span{color:#4338ca;font-weight:700}.wp-day.recovery{background:#ecfeff;border-color:#a5f3fc}.wp-day.rest span{color:#94a3b8}.wp-foot{display:flex;align-items:center;border-top:1px solid #eef2f7;padding-top:12px;gap:8px}.wp-foot small{color:#94a3b8;flex:1}.wp-actions{display:flex;gap:6px}.wp-empty{text-align:center;padding:70px 20px;color:#64748b;background:#fff;border:1px solid #e2e8f0;border-radius:14px}.wp-empty i{display:block;font-size:38px;color:#a5b4fc;margin-bottom:10px}@media(max-width:700px){.wp-shell{padding:14px 12px 40px}.wp-list{grid-template-columns:1fr}.wp-head .wp-btn{padding:8px}.wp-head .wp-btn span{display:none}}@media(max-width:420px){.wp-days{gap:2px}.wp-day{padding:6px 1px}.wp-day span{font-size:9px}}
</style>
@endpush

@section('content')
<div class="wp-shell"><div class="wp-wrap">
    <div class="wp-head"><div class="wp-head-main"><h1>Workout Plans</h1><p>Build a complete week, reuse your exercise library, and keep every cycle separate.</p></div><a class="wp-btn" href="{{ route('workouts.exercises.index') }}"><i class="bi bi-barbell"></i><span>Exercise library</span></a><a class="wp-btn primary" href="{{ route('workouts.plans.create') }}"><i class="bi bi-plus-lg"></i><span>New plan</span></a></div>
    @if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
    @if($plans->count())
        <div class="wp-list">
            @foreach($plans as $plan)
                <article class="wp-card"><div class="wp-card-top"><div><div class="wp-title">{{ $plan->title }}</div><div class="wp-sub">{{ $plan->goal ?: 'No goal set' }} @if($plan->week_number) · Week {{ $plan->week_number }} @endif</div></div><span class="wp-status {{ $plan->status }}">{{ $plan->status }}</span></div>
                    <div class="wp-days">@foreach($plan->days as $day)<div class="wp-day {{ $day->type }}"><strong>{{ substr($day->weekday, 0, 3) }}</strong><span>{{ $day->title }}</span></div>@endforeach</div>
                    <div class="wp-foot"><small>{{ $plan->days_count }} days · {{ $plan->days->sum('exercises_count') }} movements · Cycle {{ $plan->cycle_no }}</small><div class="wp-actions"><a class="wp-btn" href="{{ route('workouts.plans.edit', $plan) }}"><i class="bi bi-pencil"></i> Edit</a><form method="POST" action="{{ route('workouts.plans.destroy', $plan) }}" onsubmit="return confirm('Archive this workout plan?')">@csrf @method('DELETE')<button class="wp-btn" type="submit"><i class="bi bi-archive"></i></button></form></div></div>
                </article>
            @endforeach
        </div>
    @else
        <div class="wp-empty"><i class="bi bi-calendar2-week"></i><strong>No workout plans yet</strong><p class="mb-3">Create your first structured weekly plan.</p><a class="wp-btn primary" href="{{ route('workouts.plans.create') }}">Build a plan</a></div>
    @endif
</div></div>
@endsection
