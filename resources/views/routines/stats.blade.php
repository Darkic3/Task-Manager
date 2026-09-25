@extends('layouts.app')

@section('title', 'Routine Stats')

@push('styles')
<style>
    .main-content { padding: 14px 16px; background: #f7f8fa; min-height: 100vh; }

    /* ── Header (minimal) ───────────────────────────────── */
    .rs-header {
        background: transparent; border: none; box-shadow: none; border-radius: 0;
        padding: 0 0 14px; color: #1f2328; margin-bottom: 14px; position: relative; overflow: visible;
    }
    .rs-header::before { display: none; }
    .rs-back {
        display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:500;
        color:#8a8f98; text-decoration:none;
    }
    .rs-back:hover { color:#7c3aed; }
    .rs-header-title { font-weight: 700; font-size: 19px; margin: 12px 0 0; position: relative; color:#1f2328; word-break:break-word; }
    .rs-header-sub   { font-size: 12px; opacity: .8; margin: 2px 0 0; position: relative; color:#8a8f98; }

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

    /* ── Tracker ────────────────────────────────────────── */
    .tr-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 18px; }
    @media(max-width:600px) { .tr-stats { grid-template-columns: 1fr; } }
    .tr-stat { background: #fafbfc; border: 1px solid #eceef1; border-radius: 8px; padding: 12px 14px; }
    .tr-val { font-size: 17px; font-weight: 800; color: #1a1d23; line-height: 1.2; }
    .tr-lbl { font-size: 11px; color: #8a8f98; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; margin-top: 4px; }
    .tr-chart { display: flex; align-items: flex-end; gap: 3px; height: 90px; }
    .tr-bar-wrap { flex: 1; height: 100%; display: flex; align-items: flex-end; }
    .tr-bar { width: 100%; min-height: 2px; border-radius: 3px 3px 0 0; background: #ddd6fe; transition: background .15s; }
    .tr-bar-wrap:hover .tr-bar { background: #7c3aed; }
    .tr-bar.is-peak { background: #7c3aed; }
    .tr-axis { display: flex; margin-top: 6px; }
    .tr-axis span { flex: 1; text-align: center; font-size: 9px; color: #adb0b8; font-weight: 600; white-space: nowrap; }
    .tr-empty { padding: 18px; text-align: center; color: #adb0b8; font-size: 12.5px; }
</style>
@endpush

@section('content')
<div class="main-content">

    {{-- Header --}}
    <div class="rs-header">
        <div class="d-flex align-items-center" style="position:relative;z-index:1;">
            <a href="{{ route('routines.index') }}" class="rs-back"><i class="bi bi-arrow-left"></i> Routines</a>
        </div>
        <h1 class="rs-header-title">{{ $routine->title }}</h1>
        <p class="rs-header-sub">
            {{ $routine->recurrenceLabel() }}
            @if($routine->timeLabel()) &middot; {{ $routine->timeLabel() }} @endif
            @if(($routine->cycle_no ?? 1) > 1) &middot; Cycle {{ $routine->cycle_no }} @endif
            @if(!empty($prevCycle))
                &middot; prev cycle: {{ $prevCycle['completions'] }} done{{ $prevCycle['last_value'] !== null ? ', last ' . $prevCycle['last_value'] . ' ' . ($prevCycle['unit'] ?? '') : '' }}
            @endif
        </p>
        <form method="POST" action="{{ route('routines.new-cycle', $routine) }}" style="margin-top:8px;position:relative;z-index:1;"
              onsubmit="return confirm('Start a new cycle? The current one will be archived with its history.');">
            @csrf
            <button type="submit" style="background:white;border:1px solid #e3e4e8;border-radius:8px;padding:5px 12px;font-size:12px;font-weight:600;color:#7c3aed;cursor:pointer;">
                <i class="bi bi-arrow-repeat"></i> Start new cycle
            </button>
        </form>
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

        {{-- Logged values (tracked routines) --}}
    @if(!empty($valueStats))
        <div class="rs-card" style="margin-top:14px;">
            <div class="rs-card-head">
                <i class="bi bi-graph-up" style="color:#0e7490;"></i>
                <span class="rs-card-title">Value History — {{ $valueStats['label'] }}</span>
                <span class="rs-legend">last 90 days</span>
            </div>
            <div class="rs-card-body">
                @if(count($valueStats['points']))
                    @php
                        $pts = $valueStats['points'];
                        $vals = array_column($pts, 'value');
                        $min = min($vals); $max = max($vals);
                        $span = max(0.0001, $max - $min);
                        $isTimeV = $valueStats['is_time'] ?? false;
                        $fmtV = fn ($v) => $v === null ? '—' : ($isTimeV ? \App\Models\Routine::minutesToTimeValue($v) : $v);
                        $unitV = $isTimeV ? '' : ($valueStats['unit'] ?? '');
                    @endphp
                    <div class="tr-stats">
                        <div class="tr-stat">
                            <div class="tr-val">{{ $fmtV($valueStats['latest']['value']) }} {{ $unitV }}</div>
                            <div class="tr-lbl">Latest ({{ $valueStats['latest']['date'] }})</div>
                        </div>
                        <div class="tr-stat">
                            <div class="tr-val">{{ $fmtV($valueStats['pr']) }} {{ $unitV }}</div>
                            <div class="tr-lbl">Personal record</div>
                        </div>
                        <div class="tr-stat">
                            <div class="tr-val">{{ $fmtV($valueStats['avg']) }}{{ $valueStats['avg'] !== null && $unitV !== '' ? ' ' . $unitV : '' }}</div>
                            <div class="tr-lbl">{{ $valueStats['mode'] === 'value' ? 'Average' : 'Sessions' }} ({{ count($pts) }})</div>
                        </div>
                    </div>
                    <div class="tr-chart" style="height:110px;">
                        @foreach($pts as $p)
                            @php $h = 8 + round(($p['value'] - $min) / $span * 92); @endphp
                            <div class="tr-bar-wrap" title="{{ $p['date'] }} — {{ $fmtV($p['value']) }}">
                                <div class="tr-bar {{ $p['value'] == $max ? 'is-peak' : '' }}" style="height:{{ $h }}%;"></div>
                            </div>
                        @endforeach
                    </div>
                    @if(!empty($valueStats['per_step']))
                        <div style="margin-top:14px;font-size:12px;font-weight:700;color:#1a1d23;">Last session ({{ $valueStats['last_date'] }})</div>
                        @foreach($valueStats['per_step'] as $ps)
                            <div style="font-size:12px;color:#3d4149;margin-top:4px;">
                                <b>{{ $ps['name'] }}</b> — {{ implode(' · ', $ps['sets']) }}
                                <span style="color:#8a8f98;">(best {{ $ps['best'] }})</span>
                            </div>
                        @endforeach
                    @endif
                @else
                    <div class="tr-empty">
                        <i class="bi bi-graph-up me-1"></i>No values logged yet. Log from the Day page to start the chart.
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Completion tracker --}}
    <div class="rs-card" style="margin-top:14px;">        <div class="rs-card-head">
            <i class="bi bi-stopwatch" style="color:#7c3aed;"></i>
            <span class="rs-card-title">Completion Tracker</span>
            <span class="rs-legend">{{ $tracker['count'] }} tracked · last 3 months</span>
        </div>
        <div class="rs-card-body">
            @if($tracker['count'])
                @php
                    $avg = $tracker['avgMinutes'];
                    $avgLabel = sprintf('%d:%02d %s', intdiv($avg, 60) % 12 ?: 12, $avg % 60, $avg < 720 ? 'AM' : 'PM');
                    $peakHour = array_search(max($tracker['hours']), $tracker['hours']);
                    $peakLabel = sprintf('%d %s', $peakHour % 12 ?: 12, $peakHour < 12 ? 'AM' : 'PM');
                    $maxHour = max($tracker['hours']) ?: 1;
                    $off = $tracker['avgOffset'];
                @endphp
                <div class="tr-stats">
                    <div class="tr-stat">
                        <div class="tr-val">{{ $avgLabel }}</div>
                        <div class="tr-lbl">Average time</div>
                    </div>
                    <div class="tr-stat">
                        <div class="tr-val">
                            @if($off === null)
                                —
                            @else
                                {{ abs($off) }} min {{ $off >= 0 ? 'later' : 'earlier' }}
                            @endif
                        </div>
                        <div class="tr-lbl">Vs scheduled</div>
                    </div>
                    <div class="tr-stat">
                        <div class="tr-val">{{ $peakLabel }}</div>
                        <div class="tr-lbl">Peak hour</div>
                    </div>
                </div>
                <div class="tr-chart">
                    @for($h = 0; $h < 24; $h++)
                        <div class="tr-bar-wrap" title="{{ sprintf('%02d:00', $h) }} — {{ $tracker['hours'][$h] }} completed">
                            <div class="tr-bar {{ $maxHour > 0 && $tracker['hours'][$h] === $maxHour ? 'is-peak' : '' }}"
                                 style="height: {{ round($tracker['hours'][$h] / $maxHour * 100) }}%;"></div>
                        </div>
                    @endfor
                </div>
                <div class="tr-axis">
                    @for($h = 0; $h < 24; $h++)
                        <span>{{ in_array($h, [0, 6, 12, 18]) ? (($h % 12) ?: 12).($h < 12 ? 'am' : 'pm') : '' }}</span>
                    @endfor
                </div>
            @else
                <div class="tr-empty">
                    <i class="bi bi-stopwatch me-1"></i>No completion times recorded yet. Check off this routine to start tracking.
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
