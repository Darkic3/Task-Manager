@extends('layouts.app')

@section('title', $day->title . ' | ' . __('Workout Details'))

@push('styles')
<style>
    .wd-shell { padding: 24px 20px 60px; background: #f8fafc; min-height: 100vh; }
    .wd-wrap { max-width: 900px; margin: auto; }
    .wd-head-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 22px; box-shadow: 0 2px 6px rgba(15,23,42,.04); margin-bottom: 16px; }
    .wd-title { margin: 0; font-size: 24px; font-weight: 900; color: #0f172a; letter-spacing: -.02em; }
    .wd-sub { font-size: 13px; color: #64748b; margin-top: 4px; }
    .wd-badge { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; border-radius: 20px; padding: 4px 12px; display: inline-flex; align-items: center; gap: 5px; }
    .wd-badge.training { background: #eef2ff; color: #4338ca; }
    .wd-badge.recovery { background: #ecfeff; color: #0e7490; }
    .wd-badge.rest { background: #f1f5f9; color: #64748b; }
    .wd-ex { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px 18px; margin-bottom: 12px; }
    .wd-ex-name { font-size: 15.5px; font-weight: 800; color: #0f172a; }
    .wd-ex-target { font-size: 12.5px; color: #475569; font-weight: 700; }
    .wd-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .wd-pill { font-size: 11.5px; font-weight: 700; background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; border-radius: 20px; padding: 3px 10px; display: inline-flex; align-items: center; gap: 5px; }
    .wd-pill i { color: #818cf8; }
    .wd-note { font-size: 12.5px; color: #64748b; margin-top: 8px; }
    .wd-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; }
    .wd-btn-start { display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg,#10b981,#059669); color: #fff; border-radius: 12px; padding: 11px 20px; font-size: 13.5px; font-weight: 800; text-decoration: none; border: none; box-shadow: 0 4px 12px rgba(16,185,129,.25); }
    .wd-btn-start:hover { background: linear-gradient(135deg,#059669,#047857); color: #fff; }
    .wd-btn-ghost { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 11px 18px; font-size: 13px; font-weight: 700; color: #475569; background: #fff; text-decoration: none; }
    .wd-btn-ghost:hover { border-color: #c7d2fe; color: #4338ca; background: #f8faff; }
</style>
@endpush

@section('content')
<div class="wd-shell">
    <div class="wd-wrap">
        <div class="wd-head-card">
            <a href="{{ route('workouts.plans.index') }}" class="small text-muted text-decoration-none">
                <i class="bi bi-arrow-left"></i> {{ __('All Workout Plans') }}
            </a>
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mt-2">
                <div>
                    <h1 class="wd-title">{{ $day->title ?: ucfirst($day->weekday) }}</h1>
                    <div class="wd-sub">
                        {{ __(ucfirst($day->weekday)) }} &bull; {{ $day->plan->title }}
                        @if($day->plan->week_number) &bull; {{ __('Week') }} {{ $day->plan->week_number }} @endif
                    </div>
                </div>
                <span class="wd-badge {{ $day->type }}">{{ ucfirst($day->type) }}</span>
            </div>

            @if($day->notes)
                <div class="alert alert-light border p-3 small mt-3 mb-0 text-secondary">
                    <i class="bi bi-info-circle text-primary me-1"></i> <strong>{{ __('Day notes:') }}</strong> {{ $day->notes }}
                </div>
            @endif

            <div class="wd-actions">
                @if($todaySession && $todaySession->status === 'completed')
                    <a href="{{ route('workouts.sessions.show', $todaySession) }}" class="wd-btn-ghost">
                        <i class="bi bi-check-circle-fill text-success"></i> {{ __('View Today\'s Log') }}
                    </a>
                @elseif($todaySession && $todaySession->status === 'in_progress')
                    <a href="{{ route('workouts.sessions.show', $todaySession) }}" class="wd-btn-start" style="background: linear-gradient(135deg,#f59e0b,#d97706);">
                        <i class="bi bi-play-circle-fill"></i> {{ __('Continue Workout') }}
                    </a>
                @elseif(in_array($day->type, ['training', 'recovery'], true))
                    <form method="POST" action="{{ route('workouts.sessions.start', $day) }}" class="m-0">
                        @csrf
                        <input type="hidden" name="date" value="{{ now()->toDateString() }}">
                        <button class="wd-btn-start" type="submit">
                            <i class="bi bi-play-circle-fill"></i> {{ __('Start Workout Session') }}
                        </button>
                    </form>
                @endif
                <a href="{{ route('workouts.plans.edit', $day->plan) }}" class="wd-btn-ghost">
                    <i class="bi bi-pencil"></i> {{ __('Edit Plan') }}
                </a>
            </div>
        </div>

        @forelse($day->exercises as $we)
            <article class="wd-ex">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div>
                        <div class="wd-ex-name">{{ $we->exercise->name ?? __('Exercise') }}</div>
                        @if($we->section)
                            <div class="small text-muted">{{ $we->section }}</div>
                        @endif
                    </div>
                    <span class="wd-ex-target"><i class="bi bi-bullseye"></i> {{ $we->targetLabel() }}</span>
                </div>
                <div class="wd-meta">
                    @if($we->target_sets)<span class="wd-pill"><i class="bi bi-layers"></i> {{ $we->target_sets }} {{ __('sets') }}</span>@endif
                    @if($we->rep_min || $we->rep_max)<span class="wd-pill"><i class="bi bi-arrow-repeat"></i> {{ $we->rep_min === $we->rep_max ? $we->rep_min : $we->rep_min . '–' . $we->rep_max }} {{ __('reps') }}</span>@endif
                    @if($we->duration_seconds)<span class="wd-pill"><i class="bi bi-clock"></i> {{ gmdate('i:s', $we->duration_seconds) }}</span>@endif
                    @if($we->target_weight)<span class="wd-pill"><i class="bi bi-speedometer2"></i> {{ $we->target_weight }} kg</span>@endif
                    @if($we->target_rir !== null)<span class="wd-pill">RIR {{ $we->target_rir }}</span>@endif
                    @if($we->rest_seconds)<span class="wd-pill"><i class="bi bi-stopwatch"></i> {{ __('Rest') }} {{ $we->rest_seconds }}s</span>@endif
                    @if($we->tempo)<span class="wd-pill"><i class="bi bi-music-note"></i> {{ $we->tempo }}</span>@endif
                    @if($we->side_mode)<span class="wd-pill"><i class="bi bi-arrows"></i> {{ $we->side_mode }}</span>@endif
                    @if($we->is_amrap)<span class="wd-pill"><i class="bi bi-fire"></i> AMRAP</span>@endif
                    @if($we->is_circuit)<span class="wd-pill"><i class="bi bi-diagram-3"></i> {{ __('Circuit') }}@if($we->circuit_rounds) × {{ $we->circuit_rounds }}@endif</span>@endif
                </div>
                @if($we->notes)
                    <div class="wd-note"><i class="bi bi-chat-left-text me-1"></i>{{ $we->notes }}</div>
                @endif
            </article>
        @empty
            <div class="wd-ex text-center text-muted">
                <i class="bi bi-moon-stars fs-3 d-block mb-2"></i>
                {{ __('Rest day — no movements planned.') }}
            </div>
        @endforelse
    </div>
</div>
@endsection
