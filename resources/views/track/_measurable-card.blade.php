{{-- Measurable habit: latest value + delta + sparkline (existing logic, isolated partial) --}}
@php
    $r = $card['routine'];
    $spark = $card['spark'] ?? [];
    $max = $spark ? max(max($spark), 1) : 1;
@endphp
<div class="tk-card">
    <div class="tk-card-head">
        <span class="tk-card-title">{{ $r->title }}</span>
        <span class="tk-kind">📊 {{ __($r->tracking_mode === 'sets' ? 'Sets' : ucfirst($r->value_kind ?? 'value')) }}</span>
    </div>
    <div class="tk-card-body">
        @if($card['latest'] !== null)
            @php $isTime = $card['is_time'] ?? false; @endphp
            <div class="tk-latest">
                @if($isTime)
                    {{ \App\Models\Routine::minutesToTimeValue($card['latest']) }}
                @else
                    {{ rtrim(rtrim(number_format($card['latest'], 2, '.', ''), '0'), '.') }} <small>{{ $card['unit'] ?? ($card['latest_suffix'] ?? '') }}</small>
                @endif
            </div>
            @if($card['delta'] !== null)
                <div class="tk-delta {{ $card['delta'] > 0 ? 'up' : ($card['delta'] < 0 ? 'down' : 'flat') }}">
                    {{ $card['delta'] > 0 ? '▲' : ($card['delta'] < 0 ? '▼' : '●') }}
                    {{ $isTime ? abs((int) round($card['delta'])) . __('m') : $card['delta'] }}
                </div>
            @endif
            <div class="tk-date">{{ $card['latest_date'] }}</div>
        @else
            <div class="tk-date">{{ __('No logs yet — log from the Day page.') }}</div>
        @endif
        @if(count($spark))
            <div class="tk-spark" title="{{ __('Last :count days', ['count' => count($spark)]) }}">
                @foreach($spark as $i => $v)
                    <div class="tk-bar" style="height:{{ max(4, round($v / $max * 100)) }}%;" title="{{ $card['spark_labels'][$i] ?? '' }}: {{ ($card['is_time'] ?? false) ? \App\Models\Routine::minutesToTimeValue($v) : $v }}"></div>
                @endforeach
            </div>
        @endif
        @if(!empty($card['per_step']))
            <div class="tk-steps">
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
