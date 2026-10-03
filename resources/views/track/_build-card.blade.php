{{-- Plain build habit: adherence ring + streak + 14-day strip --}}
@php $r = $card['routine']; @endphp
<div class="tk-card">
    <div class="tk-card-head">
        <span class="tk-card-title">{{ $r->title }}</span>
        <span class="tk-kind build">🌱 {{ __('Build') }}</span>
    </div>
    <div class="tk-card-body">
        <div class="tk-ring-row">
            <svg class="tk-ring" viewBox="0 0 36 36" aria-hidden="true">
                <circle cx="18" cy="18" r="15.5" fill="none" stroke="#eef0f3" stroke-width="4"></circle>
                <circle cx="18" cy="18" r="15.5" fill="none" stroke="#16a34a" stroke-width="4"
                        stroke-dasharray="97.4" stroke-dashoffset="{{ number_format(97.4 - (97.4 * ($card['rate'] ?? 0) / 100), 1, '.', '') }}"
                        stroke-linecap="round" transform="rotate(-90 18 18)"></circle>
            </svg>
            <div>
                <div class="tk-latest">{{ $card['rate'] }}% <small>{{ $card['done'] }}/{{ $card['total'] }}</small></div>
                <div class="tk-date">{{ __('Adherence · 30d') }} @if($card['streak'] > 0)<span class="tk-flame">🔥{{ $card['streak'] }}</span>@endif</div>
            </div>
        </div>
        <div class="tk-strip" title="{{ __('Last 14 days') }}">
            @foreach($card['last14'] as $sq)
                <span class="tk-sq {{ $sq['state'] }}" title="{{ $sq['date'] }}"></span>
            @endforeach
        </div>
        <div class="tk-meta">
            <span class="tk-pill">{{ $r->recurrenceLabel() }}</span>
            @if($card['steps'] > 0)<span class="tk-pill">{{ $card['steps'] }} {{ __('steps') }}</span>@endif
            @if($card['best'] > 0)<span class="tk-pill">🏆 {{ $card['best'] }}</span>@endif
        </div>
    </div>
    <div class="tk-card-foot">
        <a href="{{ route('routines.stats', $r) }}">{{ __('Full stats') }}</a>
        <a href="{{ route('routines.edit', $r) }}">{{ __('Edit routine') }}</a>
    </div>
</div>
