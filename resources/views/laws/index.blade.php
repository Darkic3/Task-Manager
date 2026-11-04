@extends('layouts.app')

@section('title', __('Laws'))

@push('styles')
<style>
.main-content { padding:20px 24px 48px; background:#fafafa; min-height:100vh; }
.lw-wrap { max-width:860px; margin:0 auto; }
.lw-topbar { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.lw-title { font-size:19px; font-weight:700; color:#1f2328; margin:0; flex:1; }
.lw-count { font-size:12px; color:#8b8d98; font-weight:600; }
.lw-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:6px; border:1px solid #e5e7eb; background:white; color:#6b6f78; font-size:12px; font-weight:600; text-decoration:none; transition:all .12s; cursor:pointer; }
.lw-btn:hover { border-color:#7c3aed; color:#7c3aed; background:#f7f5ff; }
.lw-btn.primary { background:#7c3aed; border-color:#7c3aed; color:white; }
.lw-btn.primary:hover { background:#6d28d9; color:white; }
.lw-tabs { display:flex; gap:6px; margin-bottom:12px; flex-wrap:wrap; }
.lw-chip { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:20px; border:1px solid #e5e7eb; background:white; color:#6b6f78; font-size:11.5px; font-weight:600; text-decoration:none; transition:all .12s; }
.lw-chip:hover { border-color:#c4b5fd; color:#7c3aed; }
.lw-chip.active { background:#7c3aed; border-color:#7c3aed; color:white; }
.lw-chip small { opacity:.75; font-weight:700; }
.lw-list { display:flex; flex-direction:column; gap:8px; }
.lw-card { display:flex; align-items:center; gap:12px; background:white; border:1px solid #e5e7eb; border-radius:8px; padding:12px 14px; transition:border-color .12s; }
.lw-card:hover { border-color:#d3d7de; }
.lw-avatar { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:17px; color:white; flex-shrink:0; }
.lw-body { flex:1; min-width:0; }
.lw-row-title { font-size:13.5px; font-weight:600; color:#1f2328; line-height:1.35; }
a.lw-row-title:hover { color:#7c3aed; }
.lw-row-meta { display:flex; align-items:center; flex-wrap:wrap; gap:6px 10px; margin-top:4px; }
.lw-pill { display:inline-flex; align-items:center; gap:4px; padding:1px 8px; border-radius:20px; font-size:10.5px; font-weight:600; background:#f2f3f5; color:#6b6f78; }
.lw-pill.coeff { background:#ede9fe; color:#7c3aed; }
.lw-pill.xp-pos { background:#dcfce7; color:#15803d; }
.lw-pill.xp-neg { background:#fee2e2; color:#b91c1c; }
.lw-pill.st-expired { background:#fef3c7; color:#b45309; }
.lw-pill.st-paused { background:#e0e7ff; color:#4338ca; }
.lw-pill.st-archived { background:#f2f3f5; color:#8b8d98; }
.lw-actions { flex-shrink:0; display:flex; align-items:center; gap:4px; }
.lw-empty { text-align:center; padding:50px 20px; background:white; border:1px solid #e5e7eb; border-radius:8px; color:#8b8d98; }
.lw-empty i { font-size:32px; color:#c1c4cc; display:block; margin-bottom:10px; }
.lw-empty h5 { font-weight:700; color:#1f2328; margin-bottom:5px; }
.lw-empty p { font-size:13px; margin-bottom:14px; }
@media(max-width:640px) { .main-content { padding:14px 12px 40px; } .lw-actions { display:none; } }
</style>
@endpush

@section('content')
<div class="lw-wrap">
    <div class="lw-topbar">
        <h1 class="lw-title">{{ __('Laws') }}</h1>
        <span class="lw-count">{{ app_num($counts[$tab] ?? $laws->count()) }} {{ __('items') }}</span>
        <a href="{{ route('laws.create') }}" class="lw-btn primary"><i class="bi bi-plus-lg"></i>{{ __('New Law') }}</a>
    </div>

    <p style="font-size:12.5px;color:#8b8d98;margin:-8px 0 14px;">{{ __('Your personal charter: each law carries a coefficient, and every linked activity scores for or against it.') }}</p>

    <div class="lw-tabs">
        @foreach(['active' => __('Active'), 'paused' => __('Paused'), 'expired' => __('Expired'), 'archived' => __('Archived'), 'all' => __('All')] as $key => $label)
        <a href="{{ route('laws.index', ['tab' => $key]) }}" class="lw-chip {{ $tab === $key ? 'active' : '' }}">{{ $label }} <small>{{ app_num($counts[$key]) }}</small></a>
        @endforeach
    </div>

    @if($laws->isEmpty())
    <div class="lw-empty">
        <i class="bi bi-shield-check"></i>
        <h5>{{ __('No laws here yet') }}</h5>
        <p>{{ __('Define your first law — give it a coefficient and a time window.') }}</p>
        <a href="{{ route('laws.create') }}" class="lw-btn primary"><i class="bi bi-plus-lg"></i>{{ __('New Law') }}</a>
    </div>
    @else
    <div class="lw-list">
        @foreach($laws as $law)
        @php
            $xp = $xpByLaw->get($law->id);
            $total = (int) ($xp->total ?? 0);
            $eff = $law->effectiveStatus();
        @endphp
        <div class="lw-card">
            <div class="lw-avatar" style="background:{{ $law->color ?: '#7c3aed' }};"><i class="bi {{ $law->icon ?: 'bi-shield-check' }}"></i></div>
            <div class="lw-body">
                <a class="lw-row-title" href="{{ route('laws.show', $law) }}" style="text-decoration:none;">{{ $law->title }}</a>
                <div class="lw-row-meta">
                    <span class="lw-pill coeff"><i class="bi bi-lightning-charge"></i>×{{ app_num($law->coefficient) }}</span>
                    <span class="lw-pill"><i class="bi bi-calendar-range"></i>{{ $law->windowLabel() }}</span>
                    @if($eff === 'expired')<span class="lw-pill st-expired">{{ __('Expired') }}</span>
                    @elseif($eff === 'paused')<span class="lw-pill st-paused">{{ __('Paused') }}</span>
                    @elseif($eff === 'archived')<span class="lw-pill st-archived">{{ __('Archived') }}</span>@endif
                    @if($total !== 0)<span class="lw-pill {{ $total > 0 ? 'xp-pos' : 'xp-neg' }}">{{ $total > 0 ? '+' : '' }}{{ app_num($total) }} XP</span>@endif
                    <span class="lw-pill">+{{ app_num($xp->plus ?? 0) }} / −{{ app_num($xp->minus ?? 0) }}</span>
                </div>
            </div>
            <div class="lw-actions">
                <a class="lw-btn" style="border:none;" href="{{ route('laws.show', $law) }}" title="{{ __('Law details') }}"><i class="bi bi-eye"></i></a>
                <a class="lw-btn" style="border:none;" href="{{ route('laws.edit', $law) }}" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
