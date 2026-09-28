@extends('layouts.app')

@section('title', 'Reports')

@push('styles')
<style>
    .main-content { padding: 14px 16px; background: #f7f8fa; min-height: 100vh; }
    .rp-header {
        background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
        border-radius: 10px; padding: 12px 18px; color: white;
        margin-bottom: 14px; position: relative; overflow: hidden;
        border: 1px solid #6d28d9; box-shadow: 0 2px 8px rgba(124,58,237,.3);
    }
    .rp-header::before {
        content: ''; position: absolute; top: 0; right: 0;
        width: 80px; height: 80px; background: rgba(255,255,255,.08);
        border-radius: 50%; transform: translate(20px,-20px);
    }
    .rp-header-title { font-weight: 700; font-size: 17px; margin: 0; position: relative; z-index: 1; }
    .rp-header-sub { font-size: 12px; opacity: .85; margin: 2px 0 0; position: relative; z-index: 1; }

    .rp-filters { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
    .rp-toggle { display: inline-flex; background: white; border: 1px solid #e3e4e8; border-radius: 8px; padding: 3px; gap: 2px; }
    .rp-toggle a {
        padding: 5px 14px; border-radius: 6px; font-size: 12px; font-weight: 700;
        color: #8a8f98; text-decoration: none;
    }
    .rp-toggle a.active { background: #7c3aed; color: white; }
    .rp-dates { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #6b7280; }
    .rp-dates input {
        border: 1px solid #e3e4e8; border-radius: 7px; padding: 5px 8px;
        font-size: 12px; color: #1a1d23; background: white; outline: none;
    }
    .rp-dates button {
        border: 1px solid #7c3aed; background: #7c3aed; color: white; border-radius: 7px;
        padding: 5px 14px; font-size: 12px; font-weight: 700; cursor: pointer;
    }

    .rp-kpis { display: grid; grid-template-columns: repeat(6,1fr); gap: 10px; margin-bottom: 14px; }
    @media(max-width:1100px) { .rp-kpis { grid-template-columns: repeat(3,1fr); } }
    @media(max-width:640px) { .rp-kpis { grid-template-columns: repeat(2,1fr); } }
    .rp-kpi { background: white; border: 1px solid #e3e4e8; border-radius: 10px; padding: 12px 14px; }
    .rp-kpi-val { font-size: 22px; font-weight: 800; color: #1a1d23; line-height: 1; }
    .rp-kpi-label { font-size: 10px; color: #8a8f98; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-top: 6px; }
    .rp-delta { display: inline-block; font-size: 10.5px; font-weight: 700; margin-top: 4px; }
    .rp-delta.up { color: #16a34a; }
    .rp-delta.down { color: #dc2626; }
    .rp-delta.flat { color: #adb0b8; }

    .rp-sec-title {
        display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 800;
        color: #1a1d23; margin: 20px 0 10px;
    }
    .rp-sec-title .rp-anchor { margin-left: auto; font-size: 11px; font-weight: 600; }
    .rp-card { background: white; border: 1px solid #e3e4e8; border-radius: 10px; overflow: hidden; margin-bottom: 14px; }
    .rp-card-head {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        padding: 11px 16px; background: #fafbfc; border-bottom: 1px solid #e3e4e8;
    }
    .rp-card-title { font-size: 13px; font-weight: 700; color: #1a1d23; }
    .rp-card-body { padding: 16px; }
    .rp-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media(max-width:800px) { .rp-grid2 { grid-template-columns: 1fr; } }
    .rp-mini { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #8a8f98; margin: 0 0 8px; }

    .rp-chart { display: flex; align-items: flex-end; gap: 3px; height: 96px; }
    .rp-bar-wrap { flex: 1; height: 100%; display: flex; align-items: flex-end; min-width: 0; }
    .rp-bar { width: 100%; min-height: 2px; border-radius: 3px 3px 0 0; background: #ddd6fe; }
    .rp-bar-wrap:hover .rp-bar { background: #7c3aed; }
    .rp-bar.red { background: #fca5a5; }
    .rp-bar-wrap:hover .rp-bar.red { background: #ef4444; }
    .rp-bar.green { background: #a7f3d0; }
    .rp-bar-wrap:hover .rp-bar.green { background: #16a34a; }
    .rp-axis { display: flex; margin-top: 6px; }
    .rp-axis span { flex: 1; text-align: center; font-size: 9px; color: #adb0b8; font-weight: 600; white-space: nowrap; overflow: hidden; }

    .rp-rowline { display: flex; align-items: center; gap: 8px; padding: 7px 0; border-bottom: 1px solid #f0f1f3; font-size: 12px; }
    .rp-rowline:last-child { border-bottom: none; }
    .rp-rowname { font-weight: 600; color: #1a1d23; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 40%; }
    .rp-barline { flex: 1; height: 5px; background: #f0f1f3; border-radius: 4px; overflow: hidden; }
    .rp-barfill { height: 100%; background: #7c3aed; border-radius: 4px; }
    .rp-rown { font-weight: 700; color: #3d4149; white-space: nowrap; }
    .rp-pill {
        display: inline-flex; align-items: center; gap: 4px; padding: 1px 8px; border-radius: 20px;
        font-size: 10px; font-weight: 700; white-space: nowrap;
    }
    .rp-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .rp-table th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #8a8f98; padding: 6px 8px; border-bottom: 1px solid #e3e4e8; }
    .rp-table td { padding: 7px 8px; border-bottom: 1px solid #f0f1f3; color: #3d4149; vertical-align: middle; }
    .rp-table tr:last-child td { border-bottom: none; }
    .rp-empty { text-align: center; color: #adb0b8; font-size: 12.5px; padding: 18px; }
</style>
@endpush

@php
    $q = $range->toQuery();
    $link = fn ($r) => route('reports.overview', array_merge(['range' => $r], $r === 'custom' ? ['from' => $q['from'], 'to' => $q['to']] : []));
    $delta = function ($v, $suffix = '', $invert = false) {
        if ($v > 0) return '<span class="rp-delta '.($invert ? 'down' : 'up').'">▲ '.$v.$suffix.' vs prev</span>';
        if ($v < 0) return '<span class="rp-delta '.($invert ? 'up' : 'down').'">▼ '.abs($v).$suffix.' vs prev</span>';
        return '<span class="rp-delta flat">— vs prev</span>';
    };
    $fmtDur = fn ($s) => \App\Models\TimeEntry::formatDuration((int) $s);
@endphp

@section('content')
<div class="main-content">

    <div class="rp-header">
        <div style="position:relative;z-index:1;">
            <h1 class="rp-header-title"><i class="bi bi-bar-chart me-2"></i>Reports</h1>
            <p class="rp-header-sub">Tasks · Routines · Avoid habits · Workouts · Time — {{ $range->label }}</p>
        </div>
    </div>

    <div class="rp-filters">
        <div class="rp-toggle">
            <a href="{{ $link('today') }}" class="{{ $range->preset === 'today' ? 'active' : '' }}">Today</a>
            <a href="{{ $link('week') }}" class="{{ $range->preset === 'week' ? 'active' : '' }}">Week</a>
            <a href="{{ $link('month') }}" class="{{ $range->preset === 'month' ? 'active' : '' }}">Month</a>
            <a href="{{ $link('custom') }}" class="{{ $range->preset === 'custom' ? 'active' : '' }}">Custom</a>
        </div>
        <form method="GET" action="{{ route('reports.overview') }}" class="rp-dates">
            <input type="hidden" name="range" value="custom">
            <input type="date" name="from" value="{{ $q['from'] }}" aria-label="From">
            <span>→</span>
            <input type="date" name="to" value="{{ $q['to'] }}" aria-label="To">
            <button type="submit">Apply</button>
        </form>
    </div>

    {{-- Headline KPIs --}}
    <div class="rp-kpis">
        <div class="rp-kpi">
            <div class="rp-kpi-val">{{ $tasks['completed'] }}</div>
            <div class="rp-kpi-label">Tasks done</div>
            {!! $delta($deltas['tasks_completed']) !!}
        </div>
        <div class="rp-kpi">
            <div class="rp-kpi-val">{{ $tasks['rate'] }}%</div>
            <div class="rp-kpi-label">Task completion</div>
            {!! $delta($deltas['task_rate'], 'pp') !!}
        </div>
        <div class="rp-kpi">
            <div class="rp-kpi-val">{{ $routines['avg_rate'] }}%</div>
            <div class="rp-kpi-label">Routine adherence</div>
            {!! $delta($deltas['routine_rate'], 'pp') !!}
        </div>
        <div class="rp-kpi">
            <div class="rp-kpi-val">{{ $avoid['slip_total'] }}</div>
            <div class="rp-kpi-label">Slips</div>
            {!! $delta($deltas['slips'], '', true) !!}
        </div>
        <div class="rp-kpi">
            <div class="rp-kpi-val">{{ $workouts['completed'] }}</div>
            <div class="rp-kpi-label">Workouts done</div>
            {!! $delta($deltas['workouts']) !!}
        </div>
        <div class="rp-kpi">
            <div class="rp-kpi-val">{{ $fmtDur($time['total']) }}</div>
            <div class="rp-kpi-label">Time tracked</div>
            {!! $delta($deltas['time'] !== 0 ? (int) round($deltas['time'] / 3600, 1) : 0, 'h') !!}
        </div>
    </div>

    {{-- Tasks --}}
    <div class="rp-sec-title" id="tasks"><i class="bi bi-check2-square" style="color:#7c3aed;"></i> Tasks</div>
    <div class="rp-grid2">
        <div class="rp-card">
            <div class="rp-card-head"><span class="rp-card-title">Completed per day</span></div>
            <div class="rp-card-body">
                @if(array_sum($tasks['per_day']))
                    <div class="rp-chart">
                        @foreach($tasks['per_day'] as $d => $n)
                            <div class="rp-bar-wrap" title="{{ $d }} — {{ $n }} done">
                                <div class="rp-bar" style="height: {{ round($n / $tasks['per_day_max'] * 100) }}%;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="rp-axis">
                        @foreach($tasks['per_day'] as $d => $n)
                            <span>{{ \Carbon\Carbon::parse($d)->format('j') }}</span>
                        @endforeach
                    </div>
                @else
                    <div class="rp-empty">Nothing completed in this range.</div>
                @endif
            </div>
        </div>
        <div class="rp-card">
            <div class="rp-card-head"><span class="rp-card-title">Summary</span></div>
            <div class="rp-card-body" style="padding-top:8px;padding-bottom:8px;">
                <div class="rp-rowline"><span class="rp-rowname">Created</span><span class="rp-rown" style="margin-left:auto;">{{ $tasks['created'] }}</span></div>
                <div class="rp-rowline"><span class="rp-rowname">Completed</span><span class="rp-rown" style="margin-left:auto;">{{ $tasks['completed'] }}</span></div>
                <div class="rp-rowline"><span class="rp-rowname">Avg. completion time</span><span class="rp-rown" style="margin-left:auto;">{{ $tasks['avg_hours'] !== null ? $tasks['avg_hours'].'h' : '—' }}</span></div>
                <div class="rp-rowline"><span class="rp-rowname">Overdue now</span><span class="rp-rown" style="margin-left:auto;color:{{ $tasks['overdue_now'] ? '#dc2626' : 'inherit' }};">{{ $tasks['overdue_now'] }}</span></div>
            </div>
        </div>
    </div>
    <div class="rp-grid2">
        <div class="rp-card">
            <div class="rp-card-head"><span class="rp-card-title">Completed by project</span></div>
            <div class="rp-card-body" style="padding-top:8px;padding-bottom:8px;">
                @forelse($tasks['by_project'] as $name => $n)
                    <div class="rp-rowline">
                        <span class="rp-rowname">{{ $name }}</span>
                        <div class="rp-barline"><div class="rp-barfill" style="width:{{ $tasks['completed'] ? round($n / $tasks['completed'] * 100) : 0 }}%;"></div></div>
                        <span class="rp-rown">{{ $n }}</span>
                    </div>
                @empty
                    <div class="rp-empty">No completions to break down.</div>
                @endforelse
            </div>
        </div>
        <div class="rp-card">
            <div class="rp-card-head"><span class="rp-card-title">Completed by priority</span></div>
            <div class="rp-card-body" style="padding-top:8px;padding-bottom:8px;">
                @forelse(['high' => '#dc2626', 'medium' => '#d97706', 'low' => '#16a34a'] as $p => $c)
                    <div class="rp-rowline">
                        <span class="rp-pill" style="background:{{ $c }}1a;color:{{ $c }};">{{ ucfirst($p) }}</span>
                        <div class="rp-barline"><div class="rp-barfill" style="width:{{ $tasks['completed'] ? round(($tasks['by_priority'][$p] ?? 0) / $tasks['completed'] * 100) : 0 }}%;background:{{ $c }};"></div></div>
                        <span class="rp-rown">{{ $tasks['by_priority'][$p] ?? 0 }}</span>
                    </div>
                @empty
                    <div class="rp-empty">—</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Routines --}}
    <div class="rp-sec-title" id="routines"><i class="bi bi-arrow-repeat" style="color:#2563eb;"></i> Routines</div>
    <div class="rp-grid2">
        <div class="rp-card">
            <div class="rp-card-head"><span class="rp-card-title">Completions vs slips per day</span></div>
            <div class="rp-card-body">
                @if(array_sum($routines['per_day_done']) + array_sum($routines['per_day_slipped']))
                    <div class="rp-chart">
                        @foreach($routines['per_day_done'] as $d => $n)
                            <div class="rp-bar-wrap" title="{{ $d }} — {{ $n }} done · {{ $routines['per_day_slipped'][$d] ?? 0 }} slipped">
                                <div class="rp-bar green" style="height: {{ round($n / $routines['per_day_max'] * 100) }}%;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="rp-axis">
                        @foreach($routines['per_day_done'] as $d => $n)
                            <span>{{ \Carbon\Carbon::parse($d)->format('j') }}</span>
                        @endforeach
                    </div>
                @else
                    <div class="rp-empty">No routine activity in this range.</div>
                @endif
            </div>
        </div>
        <div class="rp-card">
            <div class="rp-card-head"><span class="rp-card-title">Summary</span></div>
            <div class="rp-card-body" style="padding-top:8px;padding-bottom:8px;">
                <div class="rp-rowline"><span class="rp-rowname">Routines (build / avoid)</span><span class="rp-rown" style="margin-left:auto;">{{ $routines['total'] }} ({{ $routines['build'] }} / {{ $routines['avoid'] }})</span></div>
                <div class="rp-rowline"><span class="rp-rowname">Avg. adherence</span><span class="rp-rown" style="margin-left:auto;">{{ $routines['avg_rate'] }}%</span></div>
                <div class="rp-rowline"><span class="rp-rowname">Build completions</span><span class="rp-rown" style="margin-left:auto;">{{ $routines['build_completed'] }}</span></div>
                <div class="rp-rowline"><span class="rp-rowname">Best clean streak</span><span class="rp-rown" style="margin-left:auto;">{{ $routines['best_clean_streak'] }}d</span></div>
            </div>
        </div>
    </div>
    <div class="rp-card">
        <div class="rp-card-head"><span class="rp-card-title">Per routine</span></div>
        <div class="rp-card-body" style="padding:6px 8px;">
            @if(count($routines['rows']))
                <table class="rp-table">
                    <thead><tr><th>Routine</th><th>Type</th><th>Done / Occ.</th><th style="width:34%;">Adherence</th><th>Slips</th></tr></thead>
                    <tbody>
                        @foreach($routines['rows'] as $row)
                            <tr>
                                <td><a href="{{ route('routines.stats', $row['id']) }}" style="color:#1a1d23;font-weight:600;text-decoration:none;">{{ $row['title'] }}</a></td>
                                <td>
                                    @if($row['is_avoid'])
                                        <span class="rp-pill" style="background:#fee2e2;color:#b91c1c;">Avoid</span>
                                    @else
                                        <span class="rp-pill" style="background:#ede9fe;color:#5b21b6;">Build</span>
                                    @endif
                                </td>
                                <td>{{ $row['done'] }}/{{ $row['occurrences'] }}</td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <div class="rp-barline"><div class="rp-barfill" style="width:{{ $row['rate'] }}%;"></div></div>
                                        <span class="rp-rown">{{ $row['rate'] }}%</span>
                                    </div>
                                </td>
                                <td>{{ $row['slips'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="rp-empty">No routines yet.</div>
            @endif
        </div>
    </div>

    {{-- Avoid habits --}}
    <div class="rp-sec-title" id="avoid"><i class="bi bi-shield" style="color:#b91c1c;"></i> Avoid habits</div>
    @if($avoid['routines'])
        <div class="rp-grid2">
            <div class="rp-card">
                <div class="rp-card-head"><span class="rp-card-title">Slips per day</span></div>
                <div class="rp-card-body">
                    @if(array_sum($avoid['per_day']))
                        <div class="rp-chart">
                            @foreach($avoid['per_day'] as $d => $n)
                                <div class="rp-bar-wrap" title="{{ $d }} — {{ $n }} slips">
                                    <div class="rp-bar red" style="height: {{ round($n / $avoid['per_day_max'] * 100) }}%;"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="rp-axis">
                            @foreach($avoid['per_day'] as $d => $n)
                                <span>{{ \Carbon\Carbon::parse($d)->format('j') }}</span>
                            @endforeach
                        </div>
                    @else
                        <div class="rp-empty">Zero slips — perfectly clean. 🛡️</div>
                    @endif
                </div>
            </div>
            <div class="rp-card">
                <div class="rp-card-head"><span class="rp-card-title">Summary</span></div>
                <div class="rp-card-body" style="padding-top:8px;padding-bottom:8px;">
                    <div class="rp-rowline"><span class="rp-rowname">Clean days</span><span class="rp-rown" style="margin-left:auto;">{{ $avoid['clean_days'] }}/{{ $avoid['occurrences'] }} ({{ $avoid['clean_rate'] }}%)</span></div>
                    <div class="rp-rowline"><span class="rp-rowname">Slips / slip days</span><span class="rp-rown" style="margin-left:auto;">{{ $avoid['slip_total'] }} / {{ $avoid['slip_days'] }}</span></div>
                    <div class="rp-rowline"><span class="rp-rowname">Best clean streak</span><span class="rp-rown" style="margin-left:auto;">{{ $avoid['best_clean_streak'] }}d</span></div>
                    <div class="rp-rowline"><span class="rp-rowname">Cravings / notes</span><span class="rp-rown" style="margin-left:auto;">{{ $avoid['cravings'] }} / {{ $avoid['notes'] }}</span></div>
                </div>
            </div>
        </div>
        <div class="rp-grid2">
            <div class="rp-card">
                <div class="rp-card-head"><span class="rp-card-title">Slips by time slot</span></div>
                <div class="rp-card-body" style="padding-top:8px;padding-bottom:8px;">
                    @forelse($avoid['by_slot'] as $slot => $n)
                        @php $pc = $avoid['periods'][$slot] ?? null; @endphp
                        <div class="rp-rowline">
                            <span class="rp-pill" style="background:{{ ($pc['color'] ?? '#64748b') }}1a;color:{{ $pc['color'] ?? '#64748b' }};">
                                @if(!empty($pc['icon']))<i class="bi {{ $pc['icon'] }}"></i>@endif
                                {{ $pc['label'] ?? ucfirst($slot) }}
                            </span>
                            <div class="rp-barline"><div class="rp-barfill" style="width:{{ $avoid['slip_total'] ? round($n / $avoid['slip_total'] * 100) : 0 }}%;background:#ef4444;"></div></div>
                            <span class="rp-rown">{{ $n }}</span>
                        </div>
                    @empty
                        <div class="rp-empty">No slips to break down.</div>
                    @endforelse
                </div>
            </div>
            <div class="rp-card">
                <div class="rp-card-head"><span class="rp-card-title">Top triggers</span></div>
                <div class="rp-card-body" style="padding-top:8px;padding-bottom:8px;">
                    @forelse($avoid['triggers'] as $t => $n)
                        <div class="rp-rowline"><span class="rp-rowname">{{ $t }}</span><span class="rp-rown" style="margin-left:auto;">×{{ $n }}</span></div>
                    @empty
                        <div class="rp-empty">No triggers logged yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="rp-card">
            <div class="rp-card-head"><span class="rp-card-title">Recent cravings &amp; notes</span></div>
            <div class="rp-card-body" style="padding-top:8px;padding-bottom:8px;">
                @forelse($avoid['recent_notes'] as $n)
                    <div class="rp-rowline">
                        <span class="rp-pill" style="background:{{ $n->kind === 'craving' ? '#fee2e2;color:#b91c1c' : '#f2f3f5;color:#6b7280' }};">{{ $n->kind === 'craving' ? 'وسوسه' : 'یادداشت' }}</span>
                        <span style="color:#8a8f98;white-space:nowrap;">{{ $n->occurred_at ? $n->occurred_at->format('M d · H:i') : '' }}</span>
                        <span style="color:#1a1d23;">{{ $n->note ?: '—' }}</span>
                    </div>
                @empty
                    <div class="rp-empty">Nothing logged yet.</div>
                @endforelse
            </div>
        </div>
    @else
        <div class="rp-card"><div class="rp-empty">No avoid habits yet — create one from Routines → Habit type → Avoid.</div></div>
    @endif

    {{-- Workouts --}}
    <div class="rp-sec-title" id="workouts"><i class="bi bi-heart-pulse-fill" style="color:#16a34a;"></i> Workouts</div>
    <div class="rp-grid2">
        <div class="rp-card">
            <div class="rp-card-head"><span class="rp-card-title">Sessions per day</span></div>
            <div class="rp-card-body">
                @if(array_sum($workouts['per_day']))
                    <div class="rp-chart">
                        @foreach($workouts['per_day'] as $d => $n)
                            <div class="rp-bar-wrap" title="{{ $d }} — {{ $n }} sessions">
                                <div class="rp-bar green" style="height: {{ round($n / $workouts['per_day_max'] * 100) }}%;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="rp-axis">
                        @foreach($workouts['per_day'] as $d => $n)
                            <span>{{ \Carbon\Carbon::parse($d)->format('j') }}</span>
                        @endforeach
                    </div>
                @else
                    <div class="rp-empty">No completed sessions in this range.</div>
                @endif
            </div>
        </div>
        <div class="rp-card">
            <div class="rp-card-head"><span class="rp-card-title">Summary</span></div>
            <div class="rp-card-body" style="padding-top:8px;padding-bottom:8px;">
                <div class="rp-rowline"><span class="rp-rowname">Sessions (completed)</span><span class="rp-rown" style="margin-left:auto;">{{ $workouts['sessions'] }} ({{ $workouts['completed'] }})</span></div>
                <div class="rp-rowline"><span class="rp-rowname">Minutes</span><span class="rp-rown" style="margin-left:auto;">{{ $workouts['minutes'] }}</span></div>
                <div class="rp-rowline"><span class="rp-rowname">Sets / reps</span><span class="rp-rown" style="margin-left:auto;">{{ $workouts['sets'] }} / {{ $workouts['reps'] }}</span></div>
                <div class="rp-rowline"><span class="rp-rowname">Volume</span><span class="rp-rown" style="margin-left:auto;">{{ $workouts['volume'] }} kg</span></div>
            </div>
        </div>
    </div>

    {{-- Time --}}
    <div class="rp-sec-title" id="time"><i class="bi bi-stopwatch" style="color:#7c3aed;"></i> Time</div>
    <div class="rp-grid2">
        <div class="rp-card">
            <div class="rp-card-head"><span class="rp-card-title">Tracked hours per day</span></div>
            <div class="rp-card-body">
                @if(array_sum($time['per_day']))
                    <div class="rp-chart">
                        @foreach($time['per_day'] as $d => $s)
                            <div class="rp-bar-wrap" title="{{ $d }} — {{ $fmtDur($s) }}">
                                <div class="rp-bar" style="height: {{ round($s / $time['per_day_max'] * 100) }}%;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="rp-axis">
                        @foreach($time['per_day'] as $d => $s)
                            <span>{{ \Carbon\Carbon::parse($d)->format('j') }}</span>
                        @endforeach
                    </div>
                @else
                    <div class="rp-empty">No tracked time in this range.</div>
                @endif
            </div>
        </div>
        <div class="rp-card">
            <div class="rp-card-head"><span class="rp-card-title">By project · {{ $fmtDur($time['total']) }} in {{ $time['sessions'] }} sessions</span></div>
            <div class="rp-card-body" style="padding-top:8px;padding-bottom:8px;">
                @forelse($time['by_project'] as $name => $s)
                    <div class="rp-rowline">
                        <span class="rp-rowname">{{ $name }}</span>
                        <div class="rp-barline"><div class="rp-barfill" style="width:{{ $time['total'] ? round($s / $time['total'] * 100) : 0 }}%;"></div></div>
                        <span class="rp-rown">{{ $fmtDur($s) }}</span>
                    </div>
                @empty
                    <div class="rp-empty">No tracked time in this range yet.</div>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection
