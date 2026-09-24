@extends('layouts.app')

@section('title', 'Track')

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
    .tk-kind{font-size:10.5px;font-weight:700;padding:1px 8px;border-radius:20px;background:#ecfeff;color:#0e7490;text-transform:uppercase;letter-spacing:.3px;}
    .tk-card-body{padding:12px 16px;}
    .tk-latest{font-size:26px;font-weight:800;color:#1a1d23;line-height:1;}
    .tk-latest small{font-size:13px;color:#8a8f98;font-weight:600;}
    .tk-delta{font-size:12px;font-weight:700;margin-top:2px;}
    .tk-delta.up{color:#16a34a;} .tk-delta.down{color:#dc2626;} .tk-delta.flat{color:#8a8f98;}
    .tk-date{font-size:11px;color:#adb0b8;margin-top:2px;}
    .tk-spark{display:flex;align-items:flex-end;gap:2px;height:44px;margin-top:10px;}
    .tk-bar{flex:1;min-height:2px;background:#a5f3fc;border-radius:2px 2px 0 0;}
    .tk-bar:last-child{background:#0e7490;}
    .tk-steps{margin-top:10px;display:flex;flex-direction:column;gap:4px;}
    .tk-step{font-size:11.5px;color:#3d4149;background:#fafbfc;border:1px solid #eef0f3;border-radius:7px;padding:4px 9px;}
    .tk-step b{color:#0e7490;}
    .tk-card-foot{padding:8px 16px;border-top:1px solid #eef0f3;display:flex;gap:12px;}
    .tk-card-foot a{font-size:12px;font-weight:600;color:#7c3aed;text-decoration:none;}
    .tk-empty{background:white;border:1px dashed #d8dae0;border-radius:10px;padding:34px;text-align:center;color:#8a8f98;font-size:13px;}
</style>
@endpush

@section('content')
<div class="main-content">
    <div class="tk-header">
        <h1 class="tk-header-title">Track</h1>
        <p class="tk-header-sub">Measurable routines — latest values at a glance</p>
    </div>

    @if($kinds->count())
        <div class="tk-filters">
            <a href="{{ route('track.index') }}" class="tk-chip {{ !$kind ? 'active' : '' }}">All</a>
            @foreach($kinds as $k)
                <a href="{{ route('track.index', ['kind' => $k]) }}" class="tk-chip {{ $kind === $k ? 'active' : '' }}">{{ ucfirst($k) }}</a>
            @endforeach
        </div>
    @endif

    @if($cards->count())
        <div class="tk-grid">
            @foreach($cards as $card)
                @php
                    $r = $card['routine'];
                    $spark = $card['spark'] ?? [];
                    $max = $spark ? max(max($spark), 1) : 1;
                @endphp
                <div class="tk-card">
                    <div class="tk-card-head">
                        <span class="tk-card-title">{{ $r->title }}</span>
                        <span class="tk-kind">{{ $r->tracking_mode === 'sets' ? 'sets' : ($r->value_kind ?? 'value') }}</span>
                    </div>
                    <div class="tk-card-body">
                        @if($card['latest'] !== null)
                            <div class="tk-latest">{{ rtrim(rtrim(number_format($card['latest'], 2, '.', ''), '0'), '.') }} <small>{{ $card['unit'] ?? ($card['latest_suffix'] ?? '') }}</small></div>
                            @if($card['delta'] !== null)
                                <div class="tk-delta {{ $card['delta'] > 0 ? 'up' : ($card['delta'] < 0 ? 'down' : 'flat') }}">
                                    {{ $card['delta'] > 0 ? '▲' : ($card['delta'] < 0 ? '▼' : '●') }} {{ $card['delta'] }}
                                </div>
                            @endif
                            <div class="tk-date">{{ $card['latest_date'] }}</div>
                        @else
                            <div class="tk-date">No logs yet — log from the Day page.</div>
                        @endif
                        @if(count($spark))
                            <div class="tk-spark" title="Last {{ count($spark) }} days">
                                @foreach($spark as $i => $v)
                                    <div class="tk-bar" style="height:{{ max(4, round($v / $max * 100)) }}%;" title="{{ $card['spark_labels'][$i] ?? '' }}: {{ $v }}"></div>
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
                        <a href="{{ route('routines.stats', $r) }}">Full stats</a>
                        <a href="{{ route('routines.edit', $r) }}">Edit routine</a>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="tk-empty">
            No tracked routines yet. Edit a routine and enable Tracking (value or sets).
        </div>
    @endif
</div>
@endsection
