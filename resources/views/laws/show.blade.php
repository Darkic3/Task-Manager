@extends('layouts.app')

@section('title', $law->title)

@push('styles')
<style>
.main-content { padding:20px 24px 48px; background:#fafafa; min-height:100vh; }
.lw-wrap { max-width:860px; margin:0 auto; }
.lw-back { display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:500; color:#8a8f98; text-decoration:none; margin-bottom:12px; }
.lw-back:hover { color:#7c3aed; }
.lw-hero { background:white; border:1px solid #e5e7eb; border-radius:10px; padding:18px; display:flex; gap:14px; align-items:flex-start; margin-bottom:12px; }
.lw-avatar { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:21px; color:white; flex-shrink:0; }
.lw-hero-body { flex:1; min-width:0; }
.lw-hero-title { font-size:17px; font-weight:700; color:#1f2328; margin:0 0 2px; }
.lw-hero-desc { font-size:13px; color:#6b6f78; margin:0 0 8px; white-space:pre-line; }
.lw-meta { display:flex; flex-wrap:wrap; gap:6px 8px; }
.lw-pill { display:inline-flex; align-items:center; gap:4px; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:600; background:#f2f3f5; color:#6b6f78; }
.lw-pill.coeff { background:#ede9fe; color:#7c3aed; }
.lw-pill.st-expired { background:#fef3c7; color:#b45309; }
.lw-pill.st-paused { background:#e0e7ff; color:#4338ca; }
.lw-pill.st-archived { background:#f2f3f5; color:#8b8d98; }
.lw-hero-actions { display:flex; gap:6px; flex-wrap:wrap; margin-top:10px; }
.lw-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:6px; border:1px solid #e5e7eb; background:white; color:#6b6f78; font-size:12px; font-weight:600; text-decoration:none; cursor:pointer; }
.lw-btn:hover { border-color:#7c3aed; color:#7c3aed; background:#f7f5ff; }
.lw-btn.danger:hover { border-color:#e5484d; color:#e5484d; background:#fef2f2; }
.lw-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:10px; margin-bottom:12px; }
@media(max-width:640px) { .lw-grid { grid-template-columns:1fr; } .main-content { padding:14px 12px 40px; } }
.lw-stat { background:white; border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; }
.lw-stat-num { font-size:22px; font-weight:800; color:#1f2328; line-height:1.2; }
.lw-stat-num.pos { color:#15803d; } .lw-stat-num.neg { color:#b91c1c; }
.lw-stat-lbl { font-size:11px; color:#8b8d98; font-weight:600; text-transform:uppercase; letter-spacing:.4px; }
.lw-panel { background:white; border:1px solid #e5e7eb; border-radius:10px; padding:16px 18px; margin-bottom:12px; }
.lw-panel h3 { font-size:13.5px; font-weight:700; color:#1f2328; margin:0 0 4px; }
.lw-panel p.hint { font-size:12.5px; color:#8b8d98; margin:0; }
.lw-tl { display:flex; flex-direction:column; gap:0; margin-top:10px; }
.lw-ev { display:flex; gap:10px; padding:9px 0; border-top:1px solid #f0f1f3; font-size:13px; align-items:flex-start; }
.lw-ev:first-child { border-top:none; }
.lw-dot { width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; flex-shrink:0; }
.lw-dot.for { background:#dcfce7; color:#15803d; }
.lw-dot.against { background:#fee2e2; color:#b91c1c; }
.lw-ev small { color:#8b8d98; font-size:11.5px; }
.cu-input { width:100%; border:1px solid #dfe1e6; border-radius:7px; padding:9px 12px; font-size:13.5px; color:#1a1d23; background:white; }
.cu-input:focus { outline:none; border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.12); }
.invalid-feedback { color:#dc2626; font-size:12px; margin-top:4px; }
</style>
@endpush

@section('content')
<div class="lw-wrap">
    <a href="{{ route('laws.index') }}" class="lw-back"><i class="bi bi-arrow-left"></i>{{ __('Back to Laws') }}</a>

    <div class="lw-hero">
        <div class="lw-avatar" style="background:{{ $law->color ?: '#7c3aed' }};"><i class="bi {{ $law->icon ?: 'bi-shield-check' }}"></i></div>
        <div class="lw-hero-body">
            <h1 class="lw-hero-title">{{ $law->title }}</h1>
            @if($law->description)<p class="lw-hero-desc">{{ $law->description }}</p>@endif
            <div class="lw-meta">
                <span class="lw-pill coeff"><i class="bi bi-lightning-charge"></i>{{ __('Coefficient') }} ×{{ app_num($law->coefficient) }}</span>
                <span class="lw-pill"><i class="bi bi-calendar-range"></i>{{ $law->windowLabel() }}</span>
                @php $eff = $law->effectiveStatus(); @endphp
                @if($eff === 'expired')<span class="lw-pill st-expired">{{ __('Expired') }}</span>
                @elseif($eff === 'paused')<span class="lw-pill st-paused">{{ __('Paused') }}</span>
                @elseif($eff === 'archived')<span class="lw-pill st-archived">{{ __('Archived') }}</span>
                @else<span class="lw-pill" style="background:#dcfce7;color:#15803d;">{{ __('Active') }}</span>@endif
            </div>
            <div class="lw-hero-actions">
                <a href="{{ route('laws.edit', $law) }}" class="lw-btn"><i class="bi bi-pencil"></i>{{ __('Edit') }}</a>
                @if($law->status === 'active')
                <form method="POST" action="{{ route('laws.pause', $law) }}" style="display:inline;">@csrf<button class="lw-btn"><i class="bi bi-pause-circle"></i>{{ __('Pause') }}</button></form>
                @endif
                @if($law->status === 'paused')
                <form method="POST" action="{{ route('laws.resume', $law) }}" style="display:inline;">@csrf<button class="lw-btn"><i class="bi bi-play-circle"></i>{{ __('Resume') }}</button></form>
                @endif
                @if($law->status !== 'archived')
                <form method="POST" action="{{ route('laws.archive', $law) }}" style="display:inline;" onsubmit="return confirmSwal(this, '{{ __('Archive this law? History is kept.') }}', { isDelete: true });">@csrf<button class="lw-btn"><i class="bi bi-archive"></i>{{ __('Archive') }}</button></form>
                @endif
                <form method="POST" action="{{ route('laws.destroy', $law) }}" style="display:inline;" onsubmit="return confirmSwal(this, '{{ __('Delete this law and its history?') }}', { isDelete: true });">@csrf @method('DELETE')<button class="lw-btn danger"><i class="bi bi-trash"></i>{{ __('Delete') }}</button></form>
            </div>
        </div>
    </div>

    <div class="lw-grid">
        <div class="lw-stat">
            <div class="lw-stat-num {{ $stats['total'] > 0 ? 'pos' : ($stats['total'] < 0 ? 'neg' : '') }}">{{ $stats['total'] > 0 ? '+' : '' }}{{ app_num($stats['total']) }}</div>
            <div class="lw-stat-lbl">{{ __('Total XP') }}</div>
        </div>
        <div class="lw-stat">
            <div class="lw-stat-num pos">+{{ app_num($stats['plus']) }}</div>
            <div class="lw-stat-lbl">{{ __('For the law · all time') }}</div>
        </div>
        <div class="lw-stat">
            <div class="lw-stat-num neg">−{{ app_num($stats['minus']) }}</div>
            <div class="lw-stat-lbl">{{ __('Against the law · all time') }}</div>
        </div>
    </div>

    @include('laws._links')

    <div class="lw-panel">
        <h3><i class="bi bi-clock-history me-1"></i>{{ __('History') }}</h3>
        <p class="hint">{{ __('Last 7 days') }}: <b style="color:#15803d;">+{{ app_num($week['plus']) }}</b> / <b style="color:#b91c1c;">−{{ app_num($week['minus']) }}</b></p>
        @if($law->events->isEmpty())
        <p class="hint" style="margin-top:8px;">{{ __('No events recorded yet.') }}</p>
        @else
        <div class="lw-tl">
            @foreach($law->events as $ev)
            <div class="lw-ev">
                <span class="lw-dot {{ $ev->isFor() ? 'for' : 'against' }}">{{ $ev->isFor() ? '+' : '−' }}</span>
                <div>
                    <div>{{ $ev->isFor() ? __('For the law') : __('Against the law') }} · <b>{{ $ev->xp_delta > 0 ? '+' : '' }}{{ app_num($ev->xp_delta) }} XP</b></div>
                    @if($ev->note)<div style="color:#4b5563;">{{ $ev->note }}</div>@endif
                    <small>{{ app_date($ev->occurred_on) }} · {{ $ev->source === 'auto' ? __('automatic') : __('manual') }}</small>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
