@extends('layouts.app')

@section('title', __('Track'))

@push('styles')
<style>
    .main-content { padding:14px 16px; background:#f7f8fa; min-height:100vh; }
    .tk-header {
        background:linear-gradient(135deg,#0e7490 0%,#155e75 100%);
        border-radius:10px; padding:12px 18px; color:white; margin-bottom:14px;
        border:1px solid #0e7490; box-shadow:0 2px 8px rgba(14,116,144,.3);
    }
    .tk-header-title{font-weight:700;font-size:18px;margin:0;}
    .tk-header-sub{font-size:12.5px;opacity:.85;margin:2px 0 0;}
    /* ── Tabs ── */
    .tk-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;}
    .tk-tab{
        display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:12.5px;font-weight:700;text-decoration:none;
        background:white;border:1px solid #e3e4e8;color:#64748b;transition:all .12s;
    }
    .tk-tab small{font-weight:800;background:#f1f5f9;border-radius:12px;padding:0 7px;font-size:11px;}
    .tk-tab:hover{border-color:#0e7490;color:#0e7490;}
    .tk-tab.active{background:#0e7490;border-color:#0e7490;color:white;}
    .tk-tab.active small{background:rgba(255,255,255,.22);}
    .tk-tab.avoid.active{background:#b91c1c;border-color:#b91c1c;}
    .tk-filters{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;}
    .tk-chip{
        padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;
        background:white;border:1px solid #e3e4e8;color:#8a8f98;
    }
    .tk-chip.active{background:#0e7490;border-color:#0e7490;color:white;}
    .tk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;}
    .tk-card{background:white;border:1px solid #e3e4e8;border-radius:10px;overflow:hidden;}
    .tk-card-head{padding:10px 16px;background:#fafbfc;border-bottom:1px solid #e3e4e8;display:flex;align-items:center;gap:8px;}
    .tk-card-title{font-size:13px;font-weight:700;color:#1a1d23;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .tk-kind{font-size:10.5px;font-weight:700;padding:1px 8px;border-radius:20px;background:#ecfeff;color:#0e7490;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap;}
    .tk-kind.build{background:#dcfce7;color:#15803d;}
    .tk-kind.avoid{background:#fee2e2;color:#b91c1c;}
    .tk-card-body{padding:12px 16px;}
    .tk-latest{font-size:26px;font-weight:800;color:#1a1d23;line-height:1;}
    .tk-latest small{font-size:13px;color:#8a8f98;font-weight:600;}
    .tk-delta{font-size:12px;font-weight:700;margin-top:2px;}
    .tk-delta.up{color:#16a34a;} .tk-delta.down{color:#dc2626;} .tk-delta.flat{color:#8a8f98;}
    .tk-date{font-size:11px;color:#adb0b8;margin-top:2px;}
    .tk-spark{display:flex;align-items:flex-end;gap:2px;height:44px;margin-top:10px;}
    .tk-bar{flex:1;min-height:2px;background:#a5f3fc;border-radius:2px 2px 0 0;}
    .tk-bar:last-child{background:#0e7490;}
    .tk-bar.slip{background:#fca5a5;}
    .tk-bar.slip:last-child{background:#dc2626;}
    .tk-steps{margin-top:10px;display:flex;flex-direction:column;gap:4px;}
    .tk-step{font-size:11.5px;color:#3d4149;background:#fafbfc;border:1px solid #eef0f3;border-radius:7px;padding:4px 9px;}
    .tk-step b{color:#0e7490;}
    .tk-card-foot{padding:8px 16px;border-top:1px solid #eef0f3;display:flex;gap:12px;}
    .tk-card-foot a{font-size:12px;font-weight:600;color:#7c3aed;text-decoration:none;}
    .tk-empty{background:white;border:1px dashed #d8dae0;border-radius:10px;padding:34px;text-align:center;color:#8a8f98;font-size:13px;}
    .tk-empty a{color:#7c3aed;font-weight:700;}
    /* ── Avoid summary strip ── */
    .tk-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:14px;}
    .tk-sum{background:white;border:1px solid #e3e4e8;border-radius:10px;padding:10px 14px;}
    .tk-sum.red{border-inline-start:3px solid #ef4444;}
    .tk-sum.green{border-inline-start:3px solid #16a34a;}
    .tk-sum-val{font-size:20px;font-weight:800;color:#1a1d23;line-height:1.2;}
    .tk-sum-lbl{font-size:10.5px;color:#8a8f98;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-top:2px;}
    /* ── Build ring + strips ── */
    .tk-ring-row{display:flex;align-items:center;gap:10px;}
    .tk-ring{width:44px;height:44px;flex-shrink:0;}
    .tk-strip{display:flex;gap:3px;margin-top:10px;}
    .tk-sq{flex:1;height:10px;border-radius:3px;background:#f1f2f4;}
    .tk-sq.done{background:#16a34a;} .tk-sq.missed{background:#fecaca;}
    .tk-sq.today{box-shadow:inset 0 0 0 1.5px #0e7490;background:#ecfeff;}
    .tk-sq.future{background:#fafbfc;box-shadow:inset 0 0 0 1px #eef0f3;}
    .tk-sq.violated{background:#ef4444;}
    .tk-meta{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;}
    .tk-pill{font-size:11px;font-weight:600;background:#f8fafc;border:1px solid #eef0f3;border-radius:12px;padding:2px 9px;color:#475569;}
    .tk-pill.danger{background:#fef2f2;border-color:#fecaca;color:#b91c1c;}
    .tk-pill.info{background:#f0f9ff;border-color:#bae6fd;color:#0369a1;}
    .tk-pill.violet{background:#f5f3ff;border-color:#ddd6fe;color:#7c3aed;}
    .tk-flame{font-size:12px;font-weight:800;color:#d97706;}
    .tk-section{margin-bottom:18px;}
    .tk-section-head{display:flex;align-items:center;gap:8px;margin-bottom:10px;}
    .tk-section-title{font-size:13px;font-weight:800;color:#1a1d23;}
    .tk-section-count{font-size:11px;font-weight:800;color:#8a8f98;background:white;border:1px solid #e3e4e8;border-radius:12px;padding:0 8px;}
    .tk-section-link{margin-inline-start:auto;font-size:12px;font-weight:700;color:#0e7490;text-decoration:none;}
</style>
@endpush

@section('content')
<div class="main-content">
    <div class="tk-header">
        <h1 class="tk-header-title">{{ __('Track') }}</h1>
        <p class="tk-header-sub">{{ __('Build · Measurable · Avoid — each habit type tracked its own way') }}</p>
    </div>

    @php
        $tab = $tab ?? 'all';
        $tabUrl = fn ($t) => route('track.index', array_filter(['tab' => $t === 'all' ? null : $t]));
    @endphp
    <div class="tk-tabs">
        <a href="{{ $tabUrl('all') }}" class="tk-tab {{ $tab === 'all' ? 'active' : '' }}">🌐 {{ __('All') }} <small>{{ $counts['all'] ?? 0 }}</small></a>
        <a href="{{ $tabUrl('build') }}" class="tk-tab {{ $tab === 'build' ? 'active' : '' }}">🌱 {{ __('Build') }} <small>{{ $counts['build'] ?? 0 }}</small></a>
        <a href="{{ $tabUrl('measurable') }}" class="tk-tab {{ $tab === 'measurable' ? 'active' : '' }}">📊 {{ __('Measurable') }} <small>{{ $counts['measurable'] ?? 0 }}</small></a>
        <a href="{{ $tabUrl('avoid') }}" class="tk-tab avoid {{ $tab === 'avoid' ? 'active' : '' }}">🛡️ {{ __('Avoid') }} <small>{{ $counts['avoid'] ?? 0 }}</small></a>
    </div>

    {{-- ══ Avoid summary strip ══ --}}
    @if(in_array($tab, ['all', 'avoid']) && !empty($avoidSummary) && ($avoidSummary['routines'] ?? 0))
        <div class="tk-summary">
            <div class="tk-sum green"><div class="tk-sum-val">{{ $avoidSummary['clean_rate'] }}%</div><div class="tk-sum-lbl">{{ __('Clean rate') }}</div></div>
            <div class="tk-sum green"><div class="tk-sum-val">{{ $avoidSummary['clean_days'] }}</div><div class="tk-sum-lbl">{{ __('Clean days') }}</div></div>
            <div class="tk-sum red"><div class="tk-sum-val">{{ $avoidSummary['slip_total'] }}</div><div class="tk-sum-lbl">{{ __('Slips') }} / {{ $avoidSummary['slip_days'] }} {{ __('days') }}</div></div>
            <div class="tk-sum"><div class="tk-sum-val">🔥{{ $avoidSummary['best_clean_streak'] }}</div><div class="tk-sum-lbl">{{ __('Best clean streak') }}</div></div>
            <div class="tk-sum"><div class="tk-sum-val">{{ $avoidSummary['mood_avg'] !== null ? $avoidSummary['mood_avg'] . '/10' : '—' }}</div><div class="tk-sum-lbl">{{ __('Avg mood') }} · ⏰ {{ sprintf('%02d:00', $avoidSummary['peak_hour'] ?? 0) }}</div></div>
            <div class="tk-sum"><div class="tk-sum-val">{{ $avoidSummary['cravings'] }}/{{ $avoidSummary['notes'] }}</div><div class="tk-sum-lbl">{{ __('Cravings / notes') }}</div></div>
        </div>
    @endif

    @if($tab === 'all')
        {{-- ══ BUILD section ══ --}}
        <div class="tk-section">
            <div class="tk-section-head">
                <span class="tk-section-title">🌱 {{ __('Build habits') }}</span>
                <span class="tk-section-count">{{ $buildCards->count() }}</span>
                <a class="tk-section-link" href="{{ $tabUrl('build') }}">{{ __('View tab') }} ←</a>
            </div>
            @if($buildCards->count())
                <div class="tk-grid">
                    @foreach($buildCards->take(6) as $card)
                        @include('track._build-card', ['card' => $card])
                    @endforeach
                </div>
            @else
                <div class="tk-empty">{{ __('No plain build habits yet.') }} <a href="{{ route('routines.create') }}">{{ __('Add one') }}</a></div>
            @endif
        </div>

        {{-- ══ MEASURABLE section ══ --}}
        <div class="tk-section">
            <div class="tk-section-head">
                <span class="tk-section-title">📊 {{ __('Measurable') }}</span>
                <span class="tk-section-count">{{ $cards->count() }}</span>
                <a class="tk-section-link" href="{{ $tabUrl('measurable') }}">{{ __('View tab') }} ←</a>
            </div>
            @if($cards->count())
                <div class="tk-grid">
                    @foreach($cards->take(6) as $card)
                        @include('track._measurable-card', ['card' => $card])
                    @endforeach
                </div>
            @else
                <div class="tk-empty">{{ __('No tracked routines yet. Edit a routine and enable Tracking (value or sets).') }}</div>
            @endif
        </div>

        {{-- ══ AVOID section ══ --}}
        <div class="tk-section">
            <div class="tk-section-head">
                <span class="tk-section-title">🛡️ {{ __('Avoid habits') }}</span>
                <span class="tk-section-count">{{ $avoidCards->count() }}</span>
                <a class="tk-section-link" href="{{ $tabUrl('avoid') }}">{{ __('View tab') }} ←</a>
            </div>
            @if($avoidCards->count())
                <div class="tk-grid">
                    @foreach($avoidCards->take(6) as $card)
                        @include('track._avoid-card', ['card' => $card])
                    @endforeach
                </div>
            @else
                <div class="tk-empty">{{ __('No avoid habits yet.') }} <a href="{{ route('routines.create') }}">{{ __('Add one') }}</a></div>
            @endif
        </div>
    @endif

    @if($tab === 'build')
        @if($buildCards->count())
            <div class="tk-grid">
                @foreach($buildCards as $card)
                    @include('track._build-card', ['card' => $card])
                @endforeach
            </div>
        @else
            <div class="tk-empty">🌱 {{ __('No plain build habits yet.') }} <a href="{{ route('routines.create') }}">{{ __('Add one') }}</a></div>
        @endif
    @endif

    @if($tab === 'measurable')
        @if($kinds->count())
            <div class="tk-filters">
                <a href="{{ route('track.index', ['tab' => 'measurable']) }}" class="tk-chip {{ !$kind ? 'active' : '' }}">{{ __('All') }}</a>
                @foreach($kinds as $k)
                    <a href="{{ route('track.index', ['tab' => 'measurable', 'kind' => $k]) }}" class="tk-chip {{ $kind === $k ? 'active' : '' }}">{{ __(ucfirst($k)) }}</a>
                @endforeach
            </div>
        @endif
        @if($cards->count())
            <div class="tk-grid">
                @foreach($cards as $card)
                    @include('track._measurable-card', ['card' => $card])
                @endforeach
            </div>
        @else
            <div class="tk-empty">{{ __('No tracked routines yet. Edit a routine and enable Tracking (value or sets).') }}</div>
        @endif
    @endif

    @if($tab === 'avoid')
        @if($avoidCards->count())
            <div class="tk-grid">
                @foreach($avoidCards as $card)
                    @include('track._avoid-card', ['card' => $card])
                @endforeach
            </div>
        @else
            <div class="tk-empty">🛡️ {{ __('No avoid habits yet.') }} <a href="{{ route('routines.create') }}">{{ __('Add one') }}</a></div>
        @endif
    @endif
</div>
@endsection
