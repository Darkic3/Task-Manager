@extends('layouts.app')

@section('title', __('Review'))

@push('styles')
<style>
.vb-wrap { max-width:640px; margin:0 auto; }
.vb-topbar { display:flex; align-items:center; gap:10px; margin-bottom:12px; flex-wrap:wrap; }
.vb-title { font-size:19px; font-weight:700; color:#1f2328; margin:0; flex:1; }
.vb-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:6px; border:1px solid #e5e7eb; background:white; color:#6b6f78; font-size:12px; font-weight:600; text-decoration:none; cursor:pointer; }
.vb-btn:hover { border-color:#6366f1; color:#6366f1; text-decoration:none; }
.vb-flash { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:2.2rem 1.5rem; text-align:center; box-shadow:0 6px 20px rgba(0,0,0,.06); }
.vb-deck { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#6366f1; }
.vb-front { font-size:2rem; font-weight:800; color:#111827; margin:.6rem 0; }
.vb-back { font-size:1.25rem; font-weight:700; color:#059669; margin:.6rem 0; }
.vb-example { font-size:.85rem; color:#6b7280; margin-top:.5rem; }
.vb-progress { height:8px; background:#eef2ff; border-radius:99px; overflow:hidden; margin-bottom:1rem; }
.vb-progress > div { height:100%; background:linear-gradient(90deg,#6366f1,#8b5cf6); }
.vb-grade { display:grid; grid-template-columns:1fr 1fr; gap:.6rem; margin-top:1.2rem; }
.vb-grade button { border:none; border-radius:10px; padding:.8rem; font-weight:800; font-size:.95rem; cursor:pointer; }
.vb-forgot { background:#fee2e2; color:#b91c1c; }
.vb-forgot:hover { background:#fecaca; }
.vb-remembered { background:#dcfce7; color:#15803d; }
.vb-remembered:hover { background:#bbf7d0; }
.vb-empty { text-align:center; padding:50px 20px; background:white; border:1px solid #e5e7eb; border-radius:12px; color:#8b8d98; }
</style>
@endpush

@section('content')
<div class="vb-wrap">
    <div class="vb-topbar">
        <h1 class="vb-title">{{ __('Review') }}</h1>
        <a href="{{ route('vocabulary.index') }}" class="vb-btn"><i class="bi bi-arrow-left"></i>{{ __('Decks') }}</a>
    </div>

    <form method="GET" action="{{ route('vocabulary.review.index') }}" style="display:flex;gap:.4rem;margin-bottom:1rem;flex-wrap:wrap;">
        <select name="deck_id" class="form-control" style="flex:1;min-width:130px;" onchange="this.form.submit()">
            <option value="all">{{ __('All decks') }}</option>
            @foreach($decks as $d)
            <option value="{{ $d->id }}" {{ (string) $deckId === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
            @endforeach
        </select>
        <select name="dir" class="form-control" style="flex:1;min-width:130px;" onchange="this.form.submit()" title="{{ __('Direction') }}">
            @foreach($dirs as $value => $label)
            <option value="{{ $value }}" {{ $dir === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    @if($sessionCount > 0)
    <div class="vb-progress"><div style="width:{{ $sessionCount > 0 ? round(($reviewedCount / $sessionCount) * 100) : 0 }}%"></div></div>
    <p style="font-size:.78rem;color:#6b7280;margin:-.4rem 0 .8rem;">{{ __(':done of :total in this session · :due due total', ['done' => app_num($reviewedCount), 'total' => app_num($sessionCount), 'due' => app_num($totalDue)]) }}</p>
    @endif

    @if(!$current)
    <div class="vb-empty">
        <div style="font-size:2.4rem;">🎉</div>
        <h5 style="color:#111827;font-weight:800;margin:.6rem 0;">{{ __('All caught up!') }}</h5>
        <p>{{ __('No cards are due right now. Add new words or come back tomorrow.') }}</p>
        <a href="{{ route('vocabulary.cards.index') }}" class="vb-btn">{{ __('Manage cards') }}</a>
    </div>
    @else
    <div class="vb-flash">
        <div class="vb-deck">{{ $current->deck->name ?? '' }} · {{ __('Box :n', ['n' => $current->box]) }}</div>
        <div class="vb-front">{{ $prompt }}</div>

        <div id="vbAnswer" style="display:none;">
            <hr>
            <div class="vb-back">{{ $answer }}</div>
            @if($showExample)<div class="vb-example">{{ $current->example }}</div>@endif
            <div class="vb-grade">
                <form method="POST" action="{{ route('vocabulary.cards.grade', $current) }}">
                    @csrf
                    <input type="hidden" name="remembered" value="0">
                    <input type="hidden" name="deck_id" value="{{ $deckId }}">
                    <input type="hidden" name="dir" value="{{ $dir }}">
                    <button class="vb-forgot" type="submit" style="width:100%;">❌ {{ __('I forgot') }}</button>
                </form>
                <form method="POST" action="{{ route('vocabulary.cards.grade', $current) }}">
                    @csrf
                    <input type="hidden" name="remembered" value="1">
                    <input type="hidden" name="deck_id" value="{{ $deckId }}">
                    <input type="hidden" name="dir" value="{{ $dir }}">
                    <button class="vb-remembered" type="submit" style="width:100%;">✅ {{ __('I remembered') }}</button>
                </form>
            </div>
        </div>

        <button id="vbShow" class="vb-btn" style="margin-top:.6rem;" onclick="document.getElementById('vbAnswer').style.display='block';this.style.display='none';">{{ __('Show meaning') }}</button>
    </div>
    <p style="font-size:.75rem;color:#9ca3af;text-align:center;margin-top:.7rem;">{{ __('Tip: answer honestly — forgot sends the card back to Box 1.') }}</p>
    @endif
</div>
@endsection
