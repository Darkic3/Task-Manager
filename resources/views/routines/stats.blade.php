@extends('layouts.app')

@section('title', 'Routine Stats')

@push('styles')
<style>
    .main-content { padding: 14px 16px; background: #f7f8fa; min-height: 100vh; }

    /* ── Header ─────────────────────────────────────────── */
    .rs-header {
        background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
        border-radius: 10px; padding: 12px 18px; color: white;
        margin-bottom: 14px; position: relative; overflow: hidden;
        border: 1px solid #6d28d9; box-shadow: 0 2px 8px rgba(124,58,237,.3);
    }
    .rs-header::before {
        content: ''; position: absolute; top: 0; right: 0;
        width: 80px; height: 80px; background: rgba(255,255,255,.08);
        border-radius: 50%; transform: translate(20px,-20px);
    }
    .rs-header-title { font-weight: 700; font-size: 17px; margin: 0; position: relative; z-index: 1; }
    .rs-header-sub   { font-size: 12px; opacity: .85; margin: 2px 0 0; position: relative; z-index: 1; }
    .rs-back { color: rgba(255,255,255,.85); text-decoration: none; margin-right: 12px; }
    .rs-back:hover { color: #fff; }

    /* ── Stat cards ─────────────────────────────────────── */
    .rs-stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 10px; margin-bottom: 14px; }
    @media(max-width:760px) { .rs-stats { grid-template-columns: repeat(2,1fr); } }
    .rs-stat { background: white; border: 1px solid #e3e4e8; border-radius: 10px; padding: 14px; }
    .rs-stat-icon {
        width: 34px; height: 34px; border-radius: 9px; margin-bottom: 9px;
        display: flex; align-items: center; justify-content: center; font-size: 15px;
    }
    .rs-stat-val   { font-size: 24px; font-weight: 800; color: #1a1d23; line-height: 1; }
    .rs-stat-label { font-size: 11px; color: #8a8f98; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-top: 6px; }
    .rs-stat-sub   { font-size: 11px; color: #adb0b8; margin-top: 2px; }

    /* ── Card ───────────────────────────────────────────── */
    .rs-card { background: white; border: 1px solid #e3e4e8; border-radius: 10px; overflow: hidden; }
    .rs-card-head {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        padding: 11px 16px; background: #fafbfc; border-bottom: 1px solid #e3e4e8;
    }
    .rs-card-title { font-size: 13px; font-weight: 700; color: #1a1d23; }
    .rs-legend { margin-left: auto; display: flex; align-items: center; gap: 6px; font-size: 11px; color: #8a8f98; font-weight: 600; }
    .rs-legend .dot { width: 11px; height: 11px; border-radius: 3px; display: inline-block; }
    .rs-card-body { padding: 16px; overflow-x: auto; }

    /* ── Heatmap ────────────────────────────────────────── */
    .hm { display: inline-flex; flex-direction: column; gap: 4px; min-width: max-content; }
    .hm-months {
        display: grid; gap: 3px; padding-left: 26px;
        font-size: 10px; color: #8a8f98; font-weight: 700;
    }
    .hm-months span { white-space: nowrap; }
    .hm-row { display: flex; }
    .hm-weekdays {
        display: flex; flex-direction: column; gap: 3px; margin-right: 4px;
        width: 22px; font-size: 9px; color: #adb0b8; font-weight: 600;
    }
    .hm-weekdays span { height: 12px; line-height: 12px; }
    .hm-grid { display: flex; gap: 3px; }
    .hm-col { display: flex; flex-direction: column; gap: 3px; }
    .hm-cell { width: 12px; height: 12px; border-radius: 3px; display: block; transition: transform .1s; }
    .hm-cell:hover { transform: scale(1.25); }
    .hm-na     { background: #f1f2f4; }
    .hm-missed { background: #fecaca; }
    .hm-done   { background: #7c3aed; }
    .hm-future { background: #fafbfc; box-shadow: inset 0 0 0 1px #eef0f3; }
    .hm-today  { box-shadow: 0 0 0 2px #c4b5fd; }
</style>
@endpush

@section('content')
<div class="main-content">

    {{-- Header --}}
    <div class="rs-header">
        <div class="d-flex align-items-center" style="position:relative;z-index:1;">
            <a href="{{ route('routines.index') }}" class="rs-back"><i class="bi bi-arrow-left fs-5"></i></a>
            <div>
                <h1 class="rs-header-title">{{ $routine->title }}</h1>
                <p class="rs-header-sub">
                    {{ $routine->recurrenceLabel() }}
                    @if($routine->timeLabel()) &middot; {{ $routine->timeLabel() }} @endif
                </p>
            </div>
        </div>
    </div>

    {{-- Stat cards --}}
    <div class="rs-stats">
        <div class="rs-stat">
            <div class="rs-stat-icon" style="background:#fef3c7;color:#d97706;"><i class="bi bi-fire"></i></div>
            <div class="rs-stat-val">{{ $streak['current'] }}</div>
            <div class="rs-stat-label">Current Streak</div>
            <div class="rs-stat-sub">{{ $streak['current'] === 1 ? 'occurrence' : 'occurrences' }} in a row</div>
        </div>
        <div class="rs-stat">
            <div class="rs-stat-icon" style="background:#ede9fe;color:#7c3aed;"><i class="bi bi-trophy"></i></div>
            <div class="rs-stat-val">{{ $streak['best'] }}</div>
            <div class="rs-stat-label">Best Streak</div>
            <div class="rs-stat-sub">all-time record</div>
        </div>
        <div class="rs-stat">
            <div class="rs-stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="rs-stat-val">{{ $adherence['rate'] }}%</div>
            <div class="rs-stat-label">Adherence · 30d</div>
            <div class="rs-stat-sub">{{ $adherence['completed'] }}/{{ $adherence['total'] }} completed</div>
        </div>
        <div class="rs-stat">
            <div class="rs-stat-icon" style="background:#dbeafe;color:#2563eb;"><i class="bi bi-check2-circle"></i></div>
            <div class="rs-stat-val">{{ $streak['completed'] }}</div>
            <div class="rs-stat-label">Total Completed</div>
            <div class="rs-stat-sub">{{ $streak['rate'] }}% over last year</div>
        </div>
    </div>

    {{-- Heatmap --}}
    <div class="rs-card">
        <div class="rs-card-head">
            <i class="bi bi-grid-3x3-gap-fill" style="color:#7c3aed;"></i>
            <span class="rs-card-title">Activity</span>
            <div class="rs-legend">
                <span class="dot" style="background:#fecaca;"></span> Missed
                <span class="dot" style="background:#7c3aed;margin-left:6px;"></span> Done
                <span class="dot" style="background:#f1f2f4;margin-left:6px;"></span> Off
            </div>
        </div>
        <div class="rs-card-body">
            <div class="hm">
                <div class="hm-months" style="grid-template-columns: repeat(16, 12px);">
                    @foreach($monthSpans as $m)
                        <span style="grid-column: span {{ $m['span'] }};">{{ $m['label'] }}</span>
                    @endforeach
                </div>
                <div class="hm-row">
                    <div class="hm-weekdays">
                        <span>Sat</span><span></span><span>Mon</span><span></span><span>Wed</span><span></span><span>Fri</span>
                    </div>
                    <div class="hm-grid">
                        @foreach($weeks as $week)
                            <div class="hm-col">
                                @foreach($week as $cell)
                                    @php
                                        $cls = $cell['future']
                                            ? 'hm-future'
                                            : (! $cell['occurs'] ? 'hm-na' : ($cell['completed'] ? 'hm-done' : 'hm-missed'));
                                        $state = $cell['future']
                                            ? 'Upcoming'
                                            : (! $cell['occurs'] ? 'Not scheduled' : ($cell['completed'] ? 'Completed' : 'Missed'));
                                        $todayCls = $cell['date']->isToday() ? ' hm-today' : '';
                                    @endphp
                                    <span class="hm-cell {{ $cls }}{{ $todayCls }}"
                                          title="{{ $cell['date']->format('D, M j, Y') }} — {{ $state }}"></span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
