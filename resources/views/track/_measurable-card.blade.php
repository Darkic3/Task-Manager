{{-- Measurable habit: 30-day calendar series + zoomed line chart + trend/avg/range --}}
@php
    $r = $card['routine'];
    $isTime = $card['is_time'] ?? $r->isTimeValue();
    $mode = $card['mode'] ?? ($r->tracking_mode === 'sets' ? 'sets' : 'value');
    $series = $card['series'] ?? [];
    $unit = $card['unit'] ?? $r->value_unit;
    $fmtVal = function ($v) use ($isTime) {
        if ($v === null) return '—';
        if ($isTime) return \App\Models\Routine::minutesToTimeValue($v) ?? '—';
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    };
    $fmtDelta = function ($d) use ($isTime) {
        if ($d === null) return '';
        if ($isTime) {
            $m = (int) round(abs($d));
            if ($m >= 60) return intdiv($m, 60) . 'h ' . ($m % 60 ? ($m % 60 . 'm') : '');
            return $m . 'm';
        }
        return rtrim(rtrim(number_format((float) $d, 2, '.', ''), '0'), '.');
    };
    $fmtAxisDate = function ($d) {
        try { return \Carbon\Carbon::parse($d)->format('M j'); } catch (\Throwable $e) { return (string) $d; }
    };
    // Chart geometry
    $cMin = $card['chart_min'] ?? 0;
    $cMax = $card['chart_max'] ?? 1;
    if ($cMax <= $cMin) { $cMax = $cMin + 1; }
    $W = 300; $H = 72; $n = max(count($series), 1);
    $xAt = fn ($i) => $n > 1 ? round($i / ($n - 1) * $W, 1) : round($W / 2, 1);
    $yAt = function ($v) use ($cMin, $cMax, $H) {
        $t = ($v - $cMin) / max(0.0001, $cMax - $cMin);
        $t = max(0, min(1, $t));
        return round($H - $t * $H, 1);
    };
    $segments = []; $cur = [];
    foreach ($series as $i => $s) {
        if ($s['value'] === null) { if ($cur) { $segments[] = $cur; $cur = []; } continue; }
        $cur[] = $xAt($i) . ',' . $yAt($s['value']);
    }
    if ($cur) $segments[] = $cur;
    $avgY = isset($card['avg']) && $card['avg'] !== null ? $yAt($card['avg']) : null;
    $firstD = $series ? $series[0]['date'] : null;
    $lastD = $series ? end($series)['date'] : null;
    // Delta semantics: for clock times, earlier = good (green).
    $delta = $card['delta'] ?? null;
    if ($delta !== null) {
        if ($isTime) { $dCls = $delta > 0 ? 'down' : ($delta < 0 ? 'up' : 'flat'); $dArrow = $delta > 0 ? '▼' : ($delta < 0 ? '▲' : '●'); }
        else { $dCls = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'); $dArrow = $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '●'); }
        $dWord = $isTime ? ($delta > 0 ? __('later') : ($delta < 0 ? __('earlier') : '')) : '';
    }
    // Trend sentence
    $trend = $card['trend'] ?? null;
    $trendTxt = null; $trendCls = '';
    if ($trend !== null && count($card['values'] ?? []) >= 3) {
        $total = $trend * (count($card['values']) - 1);
        if (abs($trend) < 0.0005 || $trend == 0.0) { $trendTxt = __('Stable'); $trendCls = 'flat'; }
        elseif ($isTime) {
            $trendTxt = ($trend < 0 ? __('Getting earlier') : __('Getting later')) . ' · ' . $fmtDelta(abs($total)) . ' / ' . __('window');
            $trendCls = $trend < 0 ? 'up' : 'down';
        } else {
            $trendTxt = ($trend > 0 ? __('Trending up') : __('Trending down')) . ' · ' . ($trend > 0 ? '+' : '−') . $fmtDelta(abs($total)) . ' / ' . __('window');
            $trendCls = $trend > 0 ? 'up' : 'down';
        }
    }
@endphp
<div class="tk-card tk-m">
    <div class="tk-card-head">
        <span class="tk-card-title">{{ $r->title }}</span>
        <span class="tk-kind">📊 {{ __($mode === 'sets' ? 'Sets' : ucfirst($r->value_kind ?? 'value')) }}</span>
    </div>
    <div class="tk-card-body">
        @if(($card['latest'] ?? null) !== null)
            <div class="tk-m-top">
                <div>
                    <div class="tk-latest">
                        {{ $fmtVal($card['latest']) }}
                        @if(!$isTime)<small>{{ $unit ?? ($card['latest_suffix'] ?? '') }}</small>@endif
                    </div>
                    <div class="tk-date">{{ __('Latest') }} · {{ $fmtAxisDate($card['latest_date']) }}
                        @if($mode === 'sets' && !empty($card['latest_sets_count'])) · {{ $card['latest_sets_count'] }} {{ __('sets') }}@endif
                    </div>
                </div>
                <div class="tk-m-badges">
                    @if($delta !== null)
                        <span class="tk-delta {{ $dCls }}" title="{{ __('vs previous') }} ({{ $card['delta_prev_date'] ?? '' }})">{{ $dArrow }} {{ $fmtDelta($delta) }}{{ $dWord ? ' ' . $dWord : '' }}</span>
                    @endif
                    <span class="tk-pill {{ ($card['rate'] ?? 0) >= 70 ? 'info' : (($card['rate'] ?? 0) >= 40 ? 'violet' : 'danger') }}">{{ $card['logged_count'] ?? 0 }}/{{ $card['sched_count'] ?? 0 }} · {{ $card['rate'] ?? 0 }}%</span>
                </div>
            </div>

            {{-- Zoomed line chart: min/max axis, avg line, gaps for missed days --}}
            <div class="tk-chart" role="img" aria-label="{{ __('30-day trend') }}">
                <div class="tk-chart-y">
                    <span>{{ $fmtVal($cMax) }}</span>
                    <span>{{ $fmtVal(($cMax + $cMin) / 2) }}</span>
                    <span>{{ $fmtVal($cMin) }}</span>
                </div>
                <div class="tk-chart-main">
                    <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none" class="tk-svg">
                        {{-- gridlines --}}
                        <line x1="0" y1="0" x2="{{ $W }}" y2="0" class="tk-grid" />
                        <line x1="0" y1="{{ round($H / 2, 1) }}" x2="{{ $W }}" y2="{{ round($H / 2, 1) }}" class="tk-grid" />
                        <line x1="0" y1="{{ $H }}" x2="{{ $W }}" y2="{{ $H }}" class="tk-grid" />
                        @if($avgY !== null)
                            <line x1="0" y1="{{ $avgY }}" x2="{{ $W }}" y2="{{ $avgY }}" class="tk-avg" />
                        @endif
                        @foreach($segments as $seg)
                            <polyline points="{{ implode(' ', $seg) }}" class="tk-line" />
                        @endforeach
                        @foreach($series as $i => $s)
                            @if($s['value'] !== null)
                                <circle cx="{{ $xAt($i) }}" cy="{{ $yAt($s['value']) }}" r="6" class="tk-dot">
                                    <title>{{ $s['date'] }} — {{ $fmtVal($s['value']) }}</title>
                                </circle>
                            @endif
                        @endforeach
                    </svg>
                    <div class="tk-chart-x">
                        <span>{{ $firstD ? $fmtAxisDate($firstD) : '' }}</span>
                        <span>{{ __('30d') }}</span>
                        <span>{{ $lastD ? $fmtAxisDate($lastD) : '' }}</span>
                    </div>
                </div>
            </div>

            {{-- 30-day adherence strip: logged / missed / off --}}
            <div class="tk-strip" title="{{ __('Last 30 days — green logged, red missed, grey unscheduled') }}">
                @foreach($series as $s)
                    @php
                        $cls = $s['state'] === 'logged' ? 'done' : ($s['state'] === 'missed' ? 'missed' : ($s['state'] === 'today' ? 'today' : ''));
                        $tip = $s['date'] . ($s['value'] !== null ? ' — ' . $fmtVal($s['value']) : ' — ' . __($s['state'] === 'missed' ? 'missed' : $s['state']));
                    @endphp
                    <span class="tk-sq {{ $cls }}" title="{{ $tip }}"></span>
                @endforeach
            </div>

            {{-- Summary stats --}}
            <div class="tk-m-stats">
                <div class="tk-m-stat"><span class="tk-m-lbl">{{ __('7d avg') }}</span><b>{{ $fmtVal($card['avg7'] ?? null) }}</b></div>
                <div class="tk-m-stat"><span class="tk-m-lbl">{{ __('30d avg') }}</span><b>{{ $fmtVal($card['avg'] ?? null) }}</b></div>
                @if($mode === 'value' && $isTime)
                    <div class="tk-m-stat"><span class="tk-m-lbl">{{ __('Earliest') }}</span><b>{{ $fmtVal($card['min'] ?? null) }}</b><small>{{ isset($card['min_date']) ? $fmtAxisDate($card['min_date']) : '' }}</small></div>
                    <div class="tk-m-stat"><span class="tk-m-lbl">{{ __('Latest') }}</span><b>{{ $fmtVal($card['max'] ?? null) }}</b><small>{{ isset($card['max_date']) ? $fmtAxisDate($card['max_date']) : '' }}</small></div>
                @else
                    <div class="tk-m-stat"><span class="tk-m-lbl">{{ __('Low') }}</span><b>{{ $fmtVal($card['min'] ?? null) }}</b></div>
                    <div class="tk-m-stat"><span class="tk-m-lbl">{{ __('High') }}</span><b>{{ $fmtVal($card['max'] ?? null) }}</b></div>
                @endif
            </div>
            @if($trendTxt)
                <div class="tk-trend {{ $trendCls }}">● {{ $trendTxt }}</div>
            @endif
        @else
            <div class="tk-date">{{ __('No logs yet — log from the Day page.') }}</div>
            @if(!empty($series))
                <div class="tk-strip">
                    @foreach($series as $s)
                        <span class="tk-sq {{ $s['state'] === 'today' ? 'today' : '' }}" title="{{ $s['date'] }}"></span>
                    @endforeach
                </div>
            @endif
        @endif
        @if(!empty($card['per_step']))
            <div class="tk-steps">
                <div class="tk-m-lbl">{{ __('Last session') }} ({{ $card['latest_date'] ?? '' }})</div>
                @foreach($card['per_step'] as $ps)
                    <div class="tk-step"><b>{{ $ps['name'] }}</b> — {{ implode(' · ', $ps['sets']) }}</div>
                @endforeach
            </div>
        @endif
    </div>
    <div class="tk-card-foot">
        <a href="{{ route('routines.stats', $r) }}">{{ __('Full stats') }}</a>
        <a href="{{ route('routines.edit', $r) }}">{{ __('Edit routine') }}</a>
    </div>
</div>
