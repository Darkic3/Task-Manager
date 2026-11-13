@extends('layouts.app')

@section('title', $plan->title . ' | ' . __('Workout Plan Overview'))

@push('styles')
<style>
    .po-shell { padding: 24px 20px 60px; background: #f8fafc; min-height: 100vh; }
    .po-wrap { max-width: 1100px; margin: auto; }
    .po-head { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 22px; box-shadow: 0 2px 6px rgba(15,23,42,.04); margin-bottom: 18px; }
    .po-title { margin: 0; font-size: 24px; font-weight: 900; color: #0f172a; letter-spacing: -.02em; }
    .po-sub { font-size: 13px; color: #64748b; margin-top: 4px; }
    .po-badge { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; border-radius: 20px; padding: 4px 12px; display: inline-flex; align-items: center; gap: 5px; }
    .po-badge.training { background: #eef2ff; color: #4338ca; }
    .po-badge.recovery { background: #ecfeff; color: #0e7490; }
    .po-badge.rest { background: #f1f5f9; color: #64748b; }
    .po-day { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 18px 20px; margin-bottom: 16px; box-shadow: 0 2px 6px rgba(15,23,42,.04); }
    .po-day-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
    .po-day-title { font-size: 17px; font-weight: 800; color: #0f172a; margin: 0; }
    .po-day-sub { font-size: 12.5px; color: #64748b; }
    .po-ex { border: 1px solid #eef2f7; border-radius: 14px; padding: 12px 14px; margin-bottom: 10px; background: #fafcff; }
    .po-ex-name { font-size: 15px; font-weight: 800; color: #0f172a; }
    .po-ex-section { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: .04em; }
    .po-ex-target { font-size: 12.5px; color: #475569; font-weight: 700; }
    .po-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
    .po-pill { font-size: 11.5px; font-weight: 700; background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; border-radius: 20px; padding: 3px 10px; display: inline-flex; align-items: center; gap: 5px; }
    .po-pill i { color: #818cf8; }
    .po-note { font-size: 12.5px; color: #64748b; margin-top: 6px; }
    .po-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; }
    .po-btn { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 16px; font-size: 13px; font-weight: 700; text-decoration: none; color: #475569; background: #fff; }
    .po-btn:hover { border-color: #c7d2fe; color: #4338ca; background: #f8faff; }
    .po-btn.primary { background: linear-gradient(135deg,#4f46e5,#6366f1); border-color: #4f46e5; color: #fff; }
    .po-btn.primary:hover { color: #fff; background: linear-gradient(135deg,#4338ca,#4f46e5); }
    .po-empty { text-align: center; padding: 40px 20px; color: #64748b; }
</style>
@endpush

@section('content')
<div class="po-shell">
    <div class="po-wrap">
        <div class="po-head">
            <a href="{{ route('workouts.plans.index') }}" class="small text-muted text-decoration-none">
                <i class="bi bi-arrow-left"></i> {{ __('All Workout Plans') }}
            </a>
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mt-2">
                <div>
                    <h1 class="po-title">{{ $plan->title }}</h1>
                    <div class="po-sub">
                        {{ $plan->goal ?: __('General Fitness') }}
                        @if($plan->week_number) &bull; {{ __('Week') }} {{ $plan->week_number }} @endif
                        @if($plan->start_date) &bull; {{ __('Starts') }} {{ $plan->start_date->format('M j, Y') }} @endif
                        @if($plan->cycle_no) &bull; {{ __('Cycle') }} {{ $plan->cycle_no }} @endif
                    </div>
                </div>
                <span class="po-badge {{ $plan->status === 'active' ? 'training' : ($plan->status === 'draft' ? 'rest' : 'recovery') }}">{{ $plan->status }}</span>
            </div>
            @if($plan->description)
                <div class="alert alert-light border p-3 small mt-3 mb-0 text-secondary">{{ $plan->description }}</div>
            @endif
            <div class="po-actions">
                <a href="{{ route('workouts.plans.edit', $plan) }}" class="po-btn"><i class="bi bi-pencil"></i> {{ __('Edit Plan') }}</a>
                @if(($plan->status ?? null) !== 'active')
                    <form method="POST" action="{{ route('workouts.plans.activate', $plan) }}" class="m-0" onsubmit="return confirmSwal(this, '{{ __('Activate this plan? Other plans will move to draft.') }}', { isDelete: false })">
                        @csrf
                        <button type="submit" class="po-btn primary"><i class="bi bi-play-circle"></i> {{ __('Activate Plan') }}</button>
                    </form>
                @endif
            </div>
        </div>

        @foreach($plan->orderedDays() as $day)
            <section class="po-day">
                <div class="po-day-head">
                    <div>
                        <h2 class="po-day-title">{{ $day->title ?: ucfirst($day->weekday) }}</h2>
                        <div class="po-day-sub">{{ __(ucfirst($day->weekday)) }} &bull; {{ $day->exercises->count() }} {{ __('movements') }}</div>
                    </div>
                    <span class="po-badge {{ $day->type }}">{{ ucfirst($day->type) }}</span>
                </div>
                @if($day->notes)
                    <div class="alert alert-light border p-2 small mb-2 text-secondary">{{ $day->notes }}</div>
                @endif

                @forelse($day->exercises as $we)
                    <article class="po-ex">
                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                            <div>
                                <div class="po-ex-name">{{ $we->exercise->name ?? __('Exercise') }}</div>
                                @if($we->section)
                                    <div class="po-ex-section">{{ $we->section }}</div>
                                @endif
                            </div>
                            <span class="po-ex-target"><i class="bi bi-bullseye"></i> {{ $we->targetLabel() }}</span>
                        </div>
                        <div class="po-meta">
                            @if($we->target_sets)<span class="po-pill"><i class="bi bi-layers"></i> {{ $we->target_sets }} {{ __('sets') }}</span>@endif
                            @if($we->rep_min || $we->rep_max)<span class="po-pill"><i class="bi bi-arrow-repeat"></i> {{ $we->rep_min === $we->rep_max ? $we->rep_min : $we->rep_min . '–' . $we->rep_max }} {{ __('reps') }}</span>@endif
                            @if($we->duration_seconds)<span class="po-pill"><i class="bi bi-clock"></i> {{ gmdate('i:s', $we->duration_seconds) }}</span>@endif
                            @if($we->target_weight)<span class="po-pill"><i class="bi bi-speedometer2"></i> {{ $we->target_weight }} kg</span>@endif
                            @if($we->target_rir !== null)<span class="po-pill">RIR {{ $we->target_rir }}</span>@endif
                            @if($we->rest_seconds)<span class="po-pill"><i class="bi bi-stopwatch"></i> {{ __('Rest') }} {{ $we->rest_seconds }}s</span>@endif
                            @if($we->tempo)<span class="po-pill"><i class="bi bi-music-note"></i> {{ $we->tempo }}</span>@endif
                            @if($we->side_mode)<span class="po-pill"><i class="bi bi-arrows"></i> {{ $we->side_mode }}</span>@endif
                            @if($we->is_amrap)<span class="po-pill"><i class="bi bi-fire"></i> AMRAP</span>@endif
                            @if($we->is_circuit)<span class="po-pill"><i class="bi bi-diagram-3"></i> {{ __('Circuit') }}@if($we->circuit_rounds) × {{ $we->circuit_rounds }}@endif</span>@endif
                        </div>
                        @if($we->notes)
                            <div class="po-note"><i class="bi bi-chat-left-text me-1"></i>{{ $we->notes }}</div>
                        @endif
                    </article>
                @empty
                    <div class="po-empty">
                        <i class="bi bi-moon-stars fs-3 d-block mb-2"></i>
                        {{ __('Rest day — no movements planned.') }}
                    </div>
                @endforelse
            </section>
        @endforeach
    </div>
</div>
@endsection