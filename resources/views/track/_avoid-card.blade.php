{{-- Avoid habit: clean metrics + red slip spark + trigger/location/mood + history links --}}
@php $r = $card['routine']; @endphp
<div class="tk-card" style="{{ ($card['violated_today'] ?? false) ? 'border-color:#fca5a5;' : '' }}">
    <div class="tk-card-head">
        <span class="tk-card-title">{{ $r->title }}</span>
        <span class="tk-kind avoid">🚫 {{ __('Avoid') }}</span>
    </div>
    <div class="tk-card-body">
        <div class="tk-ring-row">
            <svg class="tk-ring" viewBox="0 0 36 36" aria-hidden="true">
                <circle cx="18" cy="18" r="15.5" fill="none" stroke="#eef0f3" stroke-width="4"></circle>
                <circle cx="18" cy="18" r="15.5" fill="none" stroke="{{ ($card['violated_today'] ?? false) ? '#ef4444' : '#16a34a' }}" stroke-width="4"
                        stroke-dasharray="97.4" stroke-dashoffset="{{ number_format(97.4 - (97.4 * ($card['clean_rate'] ?? 0) / 100), 1, '.', '') }}"
                        stroke-linecap="round" transform="rotate(-90 18 18)"></circle>
            </svg>
            <div>
                <div class="tk-latest">{{ $card['clean_rate'] }}% <small>{{ $card['clean'] }}/{{ $card['total'] }} {{ __('clean') }}</small></div>
                <div class="tk-date">
                    @if($card['streak'] > 0)<span class="tk-flame">🛡️{{ $card['streak'] }}</span> {{ __('clean streak') }} ·@endif
                    @if($card['violated_today'])
                        <span style="color:#b91c1c;font-weight:700;">{{ __('Slipped today') }}{{ ($r->count_violations ?? false) ? ' ×' . $card['today_qty'] : '' }}</span>
                    @else
                        <span style="color:#15803d;font-weight:700;">{{ __('Clean so far') }}</span>
                    @endif
                </div>
            </div>
        </div>
        @if(!empty($card['last7']))
            <div class="tk-strip" title="{{ __('Last 7 days') }}">
                @foreach($card['last7'] as $sq)
                    <span class="tk-sq sq-{{ $sq['state'] ?? 'na' }}" style="@if(($sq['state'] ?? '')==='done')background:#16a34a;@elseif(($sq['state'] ?? '')==='violated')background:#ef4444;@elseif(($sq['state'] ?? '')==='today')box-shadow:inset 0 0 0 1.5px #b91c1c;background:#fef2f2;@elseif(($sq['state'] ?? '')==='future')background:#fafbfc;box-shadow:inset 0 0 0 1px #eef0f3;@endif"></span>
                @endforeach
            </div>
        @endif
        @php $perDay = $card['per_day'] ?? []; $maxSlip = $card['per_day_max'] ?? 1; @endphp
        @if(array_sum($perDay))
            <div class="tk-spark" title="{{ __('Slips · last 30 days') }}">
                @foreach($perDay as $d => $n)
                    <div class="tk-bar slip" style="height:{{ $n ? max(8, round($n / $maxSlip * 100)) : 4 }}%;{{ $n ? '' : 'opacity:.3;' }}" title="{{ $d }}: {{ $n }}"></div>
                @endforeach
            </div>
        @endif
        <div class="tk-meta">
            @if(!empty($card['top_trigger']))<span class="tk-pill danger">⚡ {{ \Illuminate\Support\Str::limit($card['top_trigger'], 18) }} ×{{ $card['top_trigger_n'] }}</span>@endif
            @if(!empty($card['top_location']))<span class="tk-pill info">📍 {{ \Illuminate\Support\Str::limit($card['top_location'], 18) }}</span>@endif
            @if($card['mood_avg'] !== null)<span class="tk-pill violet">😊 {{ $card['mood_avg'] }}/10</span>@endif
            <span class="tk-pill">⏰ {{ sprintf('%02d:00', $card['peak_hour'] ?? 0) }}</span>
            @if(!empty($card['last_entry']))
                <span class="tk-pill" title="{{ __('Last entry') }}">{{ $card['last_entry']['at']->format('m/d H:i') }}{{ !empty($card['last_entry']['x']) ? ' · ' . \Illuminate\Support\Str::limit($card['last_entry']['x'], 16) : '' }}</span>
            @endif
        </div>
    </div>
    <div class="tk-card-foot">
        <a href="{{ route('routines.stats', $r) }}">{{ __('Full stats') }}</a>
        <a href="{{ route('planner.index') }}">{{ __('My Day') }}</a>
    </div>
</div>
