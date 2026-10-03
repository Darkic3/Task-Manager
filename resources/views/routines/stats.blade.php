@extends('layouts.app')

@section('title', __('Routine Stats'))

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
    .hm-violated { background: #ef4444; }
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
            <a href="{{ route('routines.index') }}" class="rs-back"><i class="bi bi-arrow-left"></i> {{ __('Routines') }}</a>
        </div>
        <h1 class="rs-header-title">{{ $routine->title }}</h1>
        <p class="rs-header-sub">
            @if(!empty($avoid))
                <span style="background:#fee2e2;color:#b91c1c;border-radius:20px;padding:1px 8px;font-weight:700;">🚫 ترک‌کردنی</span> &middot;
            @endif
            {{ $routine->recurrenceLabel() }}
            @if($routine->timeLabel()) &middot; {{ $routine->timeLabel() }} @endif
            @if(($routine->cycle_no ?? 1) > 1) &middot; {{ __('Cycle') }} {{ $routine->cycle_no }} @endif
            @if(!empty($prevCycle))
                &middot; {{ __('prev cycle') }}: {{ $prevCycle['completions'] }} {{ __('done') }}{{ $prevCycle['last_value'] !== null ? ', ' . __('last') . ' ' . $prevCycle['last_value'] . ' ' . ($prevCycle['unit'] ?? '') : '' }}
            @endif
        </p>
        <form method="POST" action="{{ route('routines.new-cycle', $routine) }}" style="margin-top:8px;position:relative;z-index:1;"
              onsubmit="return confirm(@json(__('Start a new cycle? The current one will be archived with its history.')));">
            @csrf
            <button type="submit" style="background:white;border:1px solid #e3e4e8;border-radius:8px;padding:5px 12px;font-size:12px;font-weight:600;color:#7c3aed;cursor:pointer;">
                <i class="bi bi-arrow-repeat"></i> {{ __('Start new cycle') }}
            </button>
        </form>
    </div>

    {{-- Stat cards --}}
    <div class="rs-stats">
        <div class="rs-stat">
            <div class="rs-stat-icon" style="background:#fef3c7;color:#d97706;"><i class="bi bi-fire"></i></div>
            <div class="rs-stat-val">{{ $streak['current'] }}</div>
            <div class="rs-stat-label">{{ !empty($avoid) ? __('Clean streak') : __('Current Streak') }}</div>
            <div class="rs-stat-sub">{{ $streak['current'] === 1 ? __('day') : __('days') }} {{ __('in a row') }}</div>
        </div>
        <div class="rs-stat">
            <div class="rs-stat-icon" style="background:#ede9fe;color:#7c3aed;"><i class="bi bi-trophy"></i></div>
            <div class="rs-stat-val">{{ $streak['best'] }}</div>
            <div class="rs-stat-label">{{ __('Best Streak') }}</div>
            <div class="rs-stat-sub">{{ __('all-time record') }}</div>
        </div>
        <div class="rs-stat">
            <div class="rs-stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="rs-stat-val">{{ $adherence['rate'] }}%</div>
            <div class="rs-stat-label">{{ !empty($avoid) ? __('Clean rate · 30d') : __('Adherence · 30d') }}</div>
            <div class="rs-stat-sub">{{ $adherence['completed'] }}/{{ $adherence['total'] }} {{ !empty($avoid) ? __('clean') : __('completed') }}</div>
        </div>
        <div class="rs-stat">
            <div class="rs-stat-icon" style="background:#dbeafe;color:#2563eb;"><i class="bi bi-check2-circle"></i></div>
            <div class="rs-stat-val">{{ !empty($avoid) ? $avoid['slip_total'] : $streak['completed'] }}</div>
            <div class="rs-stat-label">{{ !empty($avoid) ? __('Total slips') : __('Total Completed') }}</div>
            <div class="rs-stat-sub">{{ !empty($avoid) ? $avoid['slip_days'].' '.__('days with a slip').' · '.$avoid['cravings'].' '.__('cravings') : $streak['rate'].'% '.__('over last year') }}</div>
        </div>
    </div>

    {{-- Heatmap --}}
    <div class="rs-card">
        <div class="rs-card-head">
            <i class="bi bi-grid-3x3-gap-fill" style="color:#7c3aed;"></i>
            <span class="rs-card-title">{{ __('Activity') }}</span>
            <div class="rs-legend">
                @if(!empty($avoid))
                    <span class="dot" style="background:#ef4444;"></span> {{ __('Slipped') }}
                    <span class="dot" style="background:#7c3aed;margin-left:6px;"></span> {{ __('Clean') }}
                @else
                    <span class="dot" style="background:#fecaca;"></span> {{ __('Missed') }}
                    <span class="dot" style="background:#7c3aed;margin-left:6px;"></span> {{ __('Done') }}
                @endif
                <span class="dot" style="background:#f1f2f4;margin-left:6px;"></span> {{ __('Off') }}
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
                        <span>{{ __('Sat') }}</span><span></span><span>{{ __('Mon') }}</span><span></span><span>{{ __('Wed') }}</span><span></span><span>{{ __('Fri') }}</span>
                    </div>
                    <div class="hm-grid">
                        @foreach($weeks as $week)
                            <div class="hm-col">
                                @foreach($week as $cell)
                                    @php
                                        $isV = !empty($cell['violated']);
                                        $cls = $cell['future']
                                            ? 'hm-future'
                                            : (! $cell['occurs'] ? 'hm-na' : ($isV ? 'hm-violated' : ($cell['completed'] ? 'hm-done' : 'hm-missed')));
                                        $state = $cell['future']
                                            ? 'Upcoming'
                                            : (! $cell['occurs'] ? 'Not scheduled' : ($isV ? 'Slipped' : ($cell['completed'] ? (!empty($avoid) ? 'Clean' : 'Completed') : 'Missed')));
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

    {{-- Avoid analytics: slips/day + triggers/locations/mood/peak hour (Phase 4) --}}
    @if(!empty($avoid))
        <div class="rs-card" style="margin-top:14px;">
            <div class="rs-card-head">
                <i class="bi bi-bar-chart" style="color:#b91c1c;"></i>
                <span class="rs-card-title">{{ __('Slips per day') }}</span>
                <span class="rs-legend">{{ __('last 30 days') }}</span>
            </div>
            <div class="rs-card-body">
                @if(array_sum($avoid['per_day']))
                    <div class="tr-chart" style="height:90px;">
                        @foreach($avoid['per_day'] as $d => $n)
                            <div class="tr-bar-wrap" title="{{ $d }} — {{ $n }} {{ __('slips') }}">
                                <div class="tr-bar {{ $n === $avoid['per_day_max'] ? 'is-peak' : '' }}" style="height:{{ $n ? max(8, round($n / $avoid['per_day_max'] * 100)) : 2 }}%;{{ $n ? 'background:#ef4444;' : '' }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="tr-axis">
                        @foreach($avoid['per_day'] as $d => $n)
                            <span>{{ substr($d, 8, 2) === '01' || substr($d, 8, 2) === '15' ? substr($d, 5, 5) : '' }}</span>
                        @endforeach
                    </div>
                @else
                    <div class="tr-empty">🛡️ {{ __('Zero slips — perfectly clean.') }}</div>
                @endif
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:14px;">
            <div class="rs-card">
                <div class="rs-card-head"><i class="bi bi-lightning-charge" style="color:#b91c1c;"></i><span class="rs-card-title">{{ __('Top triggers') }}</span></div>
                <div class="rs-card-body" style="padding-top:8px;padding-bottom:8px;">
                    @forelse($avoid['top_triggers'] as $t => $n)
                        <div style="display:flex;justify-content:space-between;font-size:12px;padding:5px 0;border-bottom:1px solid #f0f1f3;"><span style="color:#1a1d23;font-weight:600;">{{ $t }}</span><span style="background:#fee2e2;color:#b91c1c;padding:1px 8px;border-radius:12px;font-weight:700;">×{{ $n }}</span></div>
                    @empty
                        <div class="tr-empty">{{ __('No triggers logged yet.') }}</div>
                    @endforelse
                </div>
            </div>
            <div class="rs-card">
                <div class="rs-card-head"><i class="bi bi-geo-alt" style="color:#0369a1;"></i><span class="rs-card-title">{{ __('Top locations') }}</span></div>
                <div class="rs-card-body" style="padding-top:8px;padding-bottom:8px;">
                    @forelse($avoid['top_locations'] as $t => $n)
                        <div style="display:flex;justify-content:space-between;font-size:12px;padding:5px 0;border-bottom:1px solid #f0f1f3;"><span style="color:#1a1d23;font-weight:600;">{{ $t }}</span><span style="background:#e0f2fe;color:#0369a1;padding:1px 8px;border-radius:12px;font-weight:700;">×{{ $n }}</span></div>
                    @empty
                        <div class="tr-empty">{{ __('Nothing logged yet.') }}</div>
                    @endforelse
                </div>
            </div>
            <div class="rs-card">
                <div class="rs-card-head"><i class="bi bi-emoji-smile" style="color:#7c3aed;"></i><span class="rs-card-title">{{ __('Mood & peak hour') }}</span></div>
                <div class="rs-card-body" style="padding-top:8px;padding-bottom:8px;font-size:12px;color:#3d4149;">
                    <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f0f1f3;"><span>{{ __('Avg mood') }}</span><b>{{ $avoid['mood_avg'] !== null ? $avoid['mood_avg'] . ' / 10 (' . $avoid['mood_count'] . ')' : '—' }}</b></div>
                    <div style="display:flex;justify-content:space-between;padding:5px 0;"><span>{{ __('Peak hour') }}</span><b>{{ sprintf('%02d:00', $avoid['peak_hour']) }}</b></div>
                    <div style="font-size:11px;color:#8a8f98;">{{ __('Exact timestamps power this analysis.') }}</div>
                </div>
            </div>
        </div>
    @endif

        {{-- Logged values (tracked routines) --}}
    @if(!empty($valueStats))
        <div class="rs-card" style="margin-top:14px;">
            <div class="rs-card-head">
                <i class="bi bi-graph-up" style="color:#0e7490;"></i>
                <span class="rs-card-title">{{ __('Value History') }} — {{ $valueStats['label'] }}</span>
                <span class="rs-legend">{{ __('last 90 days') }}</span>
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
                            <div class="tr-lbl">{{ __('Latest') }} ({{ $valueStats['latest']['date'] }})</div>
                        </div>
                        <div class="tr-stat">
                            <div class="tr-val">{{ $fmtV($valueStats['pr']) }} {{ $unitV }}</div>
                            <div class="tr-lbl">{{ __('Personal record') }}</div>
                        </div>
                        <div class="tr-stat">
                            <div class="tr-val">{{ $fmtV($valueStats['avg']) }}{{ $valueStats['avg'] !== null && $unitV !== '' ? ' ' . $unitV : '' }}</div>
                            <div class="tr-lbl">{{ $valueStats['mode'] === 'value' ? __('Average') : __('Sessions') }} ({{ count($pts) }})</div>
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
                        <div style="margin-top:14px;font-size:12px;font-weight:700;color:#1a1d23;">{{ __('Last session') }} ({{ $valueStats['last_date'] }})</div>
                        @foreach($valueStats['per_step'] as $ps)
                            <div style="font-size:12px;color:#3d4149;margin-top:4px;">
                                <b>{{ $ps['name'] }}</b> — {{ implode(' · ', $ps['sets']) }}
                                <span style="color:#8a8f98;">({{ __('best') }} {{ $ps['best'] }})</span>
                            </div>
                        @endforeach
                    @endif
                @else
                    <div class="tr-empty">
                        <i class="bi bi-graph-up me-1"></i>{{ __('No values logged yet. Log from the Day page to start the chart.') }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Today's slips - read-only history (undo lives in My Day toast, 10 min) --}}
    @if(!empty($avoid) && !empty($avoid['today_violations']) && $avoid['today_violations']->isNotEmpty())
        <div class="rs-card" style="margin-top:14px;">
            <div class="rs-card-head">
                <i class="bi bi-exclamation-triangle" style="color:#b91c1c;"></i>
                <span class="rs-card-title">{{ __('Today\'s slips') }}</span>
                <span class="rs-legend">{{ __('Undo is available in My Day for 10 minutes') }}</span>
            </div>
            <div class="rs-card-body" style="padding-top:8px;padding-bottom:8px;" id="todayViolationsList">
                @foreach($avoid['today_violations'] as $v)
                    <div style="display:flex;gap:8px;padding:7px 0;border-bottom:1px solid #f0f1f3;font-size:12px;align-items:center;flex-wrap:wrap;">
                        <span style="font-weight:700;color:#b91c1c;white-space:nowrap;">{{ __('Slip') }} @if($routine->count_violations)×{{ $v->quantity }}@endif</span>
                        <span style="color:#8a8f98;white-space:nowrap;">{{ $v->occurred_at ? $v->occurred_at->format('H:i') : '' }}</span>
                        @if($v->trigger)<span style="color:#b91c1c;font-weight:600;">{{ $v->trigger }}</span>@endif
                        @if($v->location)<span style="color:#0369a1;">📍 {{ $v->location }}</span>@endif
                        @if($v->mood)<span style="background:#f5f3ff;color:#7c3aed;padding:1px 8px;border-radius:12px;font-weight:700;">{{ $v->mood }}/10</span>@endif
                        <span style="color:#1a1d23;flex:1;min-width:120px;">{{ $v->note ?: '—' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Cravings & notes (avoid habits) — read-only history --}}
    @if(!empty($avoid))
        <div class="rs-card" style="margin-top:14px;">
            <div class="rs-card-head">
                <i class="bi bi-journal-text" style="color:#b91c1c;"></i>
                <span class="rs-card-title">{{ __('Cravings & notes') }}</span>
                <span class="rs-legend">{{ __('latest first') }}</span>
            </div>
            <div class="rs-card-body" style="padding-top:8px;padding-bottom:8px;">
                @forelse($avoid['recent_notes'] as $n)
                    <div style="display:flex;gap:8px;padding:7px 0;border-bottom:1px solid #f0f1f3;font-size:12px;align-items:center;flex-wrap:wrap;">
                        <span style="font-weight:700;color:{{ $n->kind === 'craving' ? '#b91c1c' : '#6b7280' }};white-space:nowrap;">{{ $n->kind === 'craving' ? 'وسوسه' : 'یادداشت' }}</span>
                        <span style="color:#8a8f98;white-space:nowrap;">{{ $n->occurred_at ? $n->occurred_at->format('M d · H:i') : '' }}</span>
                        @if($n->trigger)<span style="color:#b91c1c;font-weight:600;">{{ $n->trigger }}</span>@endif
                        @if($n->location)<span style="color:#0369a1;">📍 {{ $n->location }}</span>@endif
                        @if($n->mood)<span style="background:#f5f3ff;color:#7c3aed;padding:1px 8px;border-radius:12px;font-weight:700;">{{ $n->mood }}/10</span>@endif
                        <span style="color:#1a1d23;flex:1;min-width:120px;">{{ $n->note ?: '—' }}</span>
                    </div>
                @empty
                    <div class="tr-empty">{{ __('No cravings or notes logged yet.') }}</div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Completion tracker --}}
    @if(empty($avoid))
    <div class="rs-card" style="margin-top:14px;">        <div class="rs-card-head">
            <i class="bi bi-stopwatch" style="color:#7c3aed;"></i>
            <span class="rs-card-title">{{ __('Completion Tracker') }}</span>
            <span class="rs-legend">{{ $tracker['count'] }} {{ __('tracked') }} · {{ __('last 3 months') }}</span>
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
                        <div class="tr-lbl">{{ __('Average time') }}</div>
                    </div>
                    <div class="tr-stat">
                        <div class="tr-val">
                            @if($off === null)
                                —
                            @else
                                {{ abs($off) }} {{ __('min') }} {{ $off >= 0 ? __('later') : __('earlier') }}
                            @endif
                        </div>
                        <div class="tr-lbl">{{ __('Vs scheduled') }}</div>
                    </div>
                    <div class="tr-stat">
                        <div class="tr-val">{{ $peakLabel }}</div>
                        <div class="tr-lbl">{{ __('Peak hour') }}</div>
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
                    <i class="bi bi-stopwatch me-1"></i>{{ __('No completion times recorded yet. Check off this routine to start tracking.') }}
                </div>
            @endif
        </div>
    </div>
    @endif

</div>
@endsection
