@extends('layouts.app')

@section('title', __('Workout History') . ' | ' . $workoutSession->displayDayTitle())

@push('styles')
<style>
    .wh-shell{padding:24px 20px 60px;background:#f8fafc;min-height:100vh}.wh-wrap{max-width:900px;margin:auto}
    .wh-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px 22px;box-shadow:0 2px 6px rgba(15,23,42,.04);margin-bottom:14px}
    .wh-title{margin:0;font-size:22px;font-weight:900;color:#0f172a;letter-spacing:-.02em}
    .wh-sub{font-size:13px;color:#64748b;margin-top:4px}
    .wh-badge{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;border-radius:20px;padding:4px 12px;display:inline-flex;align-items:center;gap:5px}
    .wh-badge.completed{background:#dcfce7;color:#15803d}.wh-badge.in_progress{background:#fef3c7;color:#b45309}.wh-badge.skipped{background:#fee2e2;color:#b91c1c}
    .wh-ex{border:1px solid #eef2f7;border-radius:12px;padding:14px 16px;margin-bottom:10px;background:#fff}
    .wh-ex-name{font-size:15px;font-weight:800;color:#0f172a}
    .wh-table{width:100%;border-collapse:collapse;font-size:12px;margin-top:10px}
    .wh-table th,.wh-table td{padding:7px 10px;border-bottom:1px solid #f1f5f9;text-align:left}
    .wh-table th{font-size:10px;text-transform:uppercase;color:#64748b;font-weight:700}
    .wh-table tr:last-child td{border-bottom:0}
    .wh-pill{font-size:11px;font-weight:700;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;border-radius:20px;padding:2px 10px;display:inline-flex;align-items:center;gap:5px}
</style>
@endpush

@section('content')
<div class="wh-shell">
    <div class="wh-wrap">
        <div class="wh-card">
            <a href="{{ route('workouts.reports.index') }}" class="small text-muted text-decoration-none">
                <i class="bi bi-arrow-left"></i> {{ __('Workout Reports') }}
            </a>
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mt-2">
                <div>
                    <h1 class="wh-title">{{ $workoutSession->displayDayTitle() }}</h1>
                    <div class="wh-sub">
                        @if($workoutSession->weekday) {{ __(ucfirst($workoutSession->weekday)) }} &bull; @endif
                        {{ $workoutSession->workout_date->format('Y-m-d') }}
                        @if($workoutSession->displayPlanTitle()) &bull; {{ $workoutSession->displayPlanTitle() }} @endif
                        @if($workoutSession->duration_seconds ?? $workoutSession->elapsedSeconds()) &bull; {{ \App\Models\WorkoutSession::formatDuration($workoutSession->duration_seconds ?? $workoutSession->elapsedSeconds()) }} @endif
                        @if($workoutSession->bodyweight_kg) &bull; {{ $workoutSession->bodyweight_kg }} kg @endif
                    </div>
                </div>
                <span class="wh-badge {{ $workoutSession->status }}">{{ ucfirst(str_replace('_', ' ', $workoutSession->status)) }}</span>
            </div>
            <div class="alert alert-light border p-3 small mt-3 mb-0 text-secondary">
                <i class="bi bi-archive text-warning me-1"></i>
                {{ __('The plan template for this session was edited or deleted — showing the saved history exactly as it was recorded.') }}
            </div>
            @if($workoutSession->session_note)
                <div class="small mt-3 text-secondary"><strong>{{ __('Session note:') }}</strong> {{ $workoutSession->session_note }}</div>
            @endif
            @if($workoutSession->pain_note)
                <div class="small mt-1 text-secondary"><strong>{{ __('Pain note:') }}</strong> {{ $workoutSession->pain_note }}</div>
            @endif
        </div>

        @forelse($workoutSession->exerciseLogs as $elog)
            <article class="wh-ex">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div>
                        <div class="wh-ex-name">{{ $elog->displayExerciseName() }}</div>
                        @if($elog->displayTargetLabel())
                            <div class="small text-muted mt-1"><i class="bi bi-bullseye"></i> {{ $elog->displayTargetLabel() }}</div>
                        @endif
                    </div>
                    <span class="wh-badge {{ $elog->completed ? 'completed' : 'skipped' }}">{{ $elog->completed ? __('Completed') : __('Open') }}</span>
                </div>
                @if($elog->skip_reason)
                    <div class="small text-danger mt-2"><strong>{{ __('Skipped:') }}</strong> {{ $elog->skip_reason }}</div>
                @endif
                @if($elog->note)
                    <div class="small text-secondary mt-1"><i class="bi bi-chat-left-text me-1"></i>{{ $elog->note }}</div>
                @endif
                @if($elog->setLogs->isNotEmpty())
                    <table class="wh-table">
                        <thead>
                            <tr>
                                <th>{{ __('Set') }}</th>
                                <th>{{ __('Side') }}</th>
                                <th>{{ __('Reps') }}</th>
                                <th>{{ __('Weight') }}</th>
                                <th>{{ __('RIR') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Note') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($elog->setLogs as $set)
                                <tr>
                                    <td class="fw-bold">{{ $set->set_number }}</td>
                                    <td>{{ $set->side ?? 'both' }}</td>
                                    <td>{{ $set->reps ?? '—' }}</td>
                                    <td>{{ $set->weight !== null ? $set->weight.' kg' : '—' }}</td>
                                    <td>{{ $set->rir ?? '—' }}</td>
                                    <td>
                                        @if($set->completed)
                                            <span class="wh-pill" style="background:#dcfce7;border-color:#bbf7d0;color:#15803d;">{{ __('Done') }}</span>
                                        @else
                                            <span class="wh-pill" style="background:#fee2e2;border-color:#fecaca;color:#b91c1c;">{{ __('Skipped') }}</span>
                                        @endif
                                        @if($set->is_pr)<span class="wh-pill" style="background:#fef3c7;border-color:#fde68a;color:#b45309;">PR</span>@endif
                                    </td>
                                    <td class="text-muted">{{ $set->note ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </article>
        @empty
            <div class="wh-card text-center text-muted">
                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                {{ __('No movements were logged in this session.') }}
            </div>
        @endforelse
    </div>
</div>
@endsection
