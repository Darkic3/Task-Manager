@extends('layouts.app')

@section('title', __('Vocabulary'))

@push('styles')
<style>
.vb-wrap { max-width:960px; margin:0 auto; }
.vb-topbar { display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
.vb-title { font-size:19px; font-weight:700; color:#1f2328; margin:0; flex:1; }
.vb-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:6px; border:1px solid #e5e7eb; background:white; color:#6b6f78; font-size:12px; font-weight:600; text-decoration:none; cursor:pointer; }
.vb-btn:hover { border-color:#6366f1; color:#6366f1; background:#f5f6ff; text-decoration:none; }
.vb-btn.primary { background:#6366f1; border-color:#6366f1; color:white; }
.vb-btn.primary:hover { background:#4f46e5; color:white; }
.vb-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:.6rem; margin-bottom:1rem; }
.vb-tile { background:#fff; border-radius:10px; padding:.8rem 1rem; border:1px solid #e5e7eb; }
.vb-tile .v { font-size:1.4rem; font-weight:700; color:#111827; line-height:1; }
.vb-tile .l { font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; }
.vb-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:.8rem; }
.vb-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:1rem; }
.vb-card:hover { border-color:#d3d7de; }
.vb-boxrow { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:1rem; }
.vb-box { flex:1; min-width:100px; background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:.6rem; text-align:center; }
.vb-box b { display:block; font-size:1.2rem; color:#111827; }
.vb-box span { font-size:.7rem; color:#6b7280; font-weight:600; }
.vb-form { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:1rem; margin-bottom:1rem; }
.vb-form h6 { font-weight:700; margin:0 0 .6rem; font-size:.85rem; color:#1f2328; }
.vb-row { display:flex; gap:.5rem; flex-wrap:wrap; }
.vb-row .form-control { flex:1; min-width:140px; }
.vb-meta { font-size:.75rem; color:#6b7280; margin-top:4px; display:flex; gap:8px; flex-wrap:wrap; }
.vb-pill { display:inline-flex; align-items:center; padding:1px 8px; border-radius:20px; font-size:10.5px; font-weight:700; background:#eef2ff; color:#4f46e5; }
.vb-pill.due { background:#fef3c7; color:#b45309; }
.vb-pill.done { background:#dcfce7; color:#15803d; }
.vb-inline { display:inline; }
.vb-danger { color:#b91c1c; background:none; border:none; font-size:.75rem; font-weight:600; cursor:pointer; padding:0; }
.vb-danger:hover { text-decoration:underline; }
.vb-empty { text-align:center; padding:40px 20px; background:white; border:1px solid #e5e7eb; border-radius:8px; color:#8b8d98; }
</style>
@endpush

@section('content')
<div class="vb-wrap">
    <div class="vb-topbar">
        <h1 class="vb-title">{{ __('Vocabulary') }}</h1>
            <a href="{{ route('vocabulary.cards.index') }}" class="vb-btn"><i class="bi bi-list-ul"></i>{{ __('All cards') }}</a>
            <a href="{{ route('vocabulary.imports.create') }}" class="vb-btn"><i class="bi bi-upload"></i>{{ __('Import CSV') }}</a>
        <a href="{{ route('vocabulary.review.index') }}" class="vb-btn primary"><i class="bi bi-play-fill"></i>{{ __('Start review') }} ({{ app_num($stats['due']) }})</a>
    </div>

    <div class="vb-stats">
        <div class="vb-tile"><div class="v">{{ app_num($stats['due']) }}</div><div class="l">{{ __('Due today') }}</div></div>
        <div class="vb-tile"><div class="v">{{ app_num($stats['total']) }}</div><div class="l">{{ __('Total cards') }}</div></div>
        <div class="vb-tile"><div class="v">{{ app_num($stats['mastered']) }}</div><div class="l">{{ __('Mastered') }}</div></div>
        <div class="vb-tile"><div class="v">{{ app_num($stats['decks']) }}</div><div class="l">{{ __('Decks') }}</div></div>
    </div>

    <div class="vb-boxrow">
        @foreach([1 => __('Box 1 · daily'), 2 => __('Box 2 · 3 days'), 3 => __('Box 3 · weekly'), 4 => __('Box 4 · 2 weeks'), 5 => __('Box 5 · monthly')] as $box => $label)
        <div class="vb-box"><b>{{ app_num($boxCounts[$box] ?? 0) }}</b><span>{{ $label }}</span></div>
        @endforeach
    </div>

    <div class="vb-form">
        <h6>{{ __('New deck') }}</h6>
        <form method="POST" action="{{ route('vocabulary.decks.store') }}">
            @csrf
            <div class="vb-row">
                <input name="name" class="form-control" required maxlength="100" placeholder="{{ __('Deck name — e.g. English') }}">
                <input name="color" type="color" class="form-control" style="max-width:60px;" value="#6366f1" title="{{ __('Color') }}">
                <button class="vb-btn primary" type="submit"><i class="bi bi-plus-lg"></i>{{ __('Add') }}</button>
            </div>
        </form>
    </div>

    @if($decks->isNotEmpty())
    <div class="vb-form">
        <h6>{{ __('Quick add card') }} <span style="color:#6b7280;font-weight:600;">· {{ app_num($newToday ?? 0) }}/{{ app_num($newLimit ?? 20) }} {{ __('New today') }}</span></h6>
        <form method="POST" action="{{ route('vocabulary.cards.store') }}">
            @csrf
            <div class="vb-row">
                <select name="deck_id" class="form-control" required>
                    @foreach($decks as $d)
                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select>
                <input name="front" class="form-control" required maxlength="255" placeholder="{{ __('Word') }}">
                <input name="back" class="form-control" required maxlength="2000" placeholder="{{ __('Meaning') }}">
                <button class="vb-btn primary" type="submit"><i class="bi bi-plus-lg"></i>{{ __('Add') }}</button>
            </div>
        </form>
    </div>
    @endif

    @if($decks->isEmpty())
    <div class="vb-empty">
        <h5>{{ __('No decks yet') }}</h5>
        <p>{{ __('Create your first deck, then add words to it every day.') }}</p>
    </div>
    @else
    <div class="vb-grid">
        @foreach($decks as $d)
        <div class="vb-card">
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="width:12px;height:12px;border-radius:4px;background:{{ $d->color ?: '#6366f1' }};flex-shrink:0;"></span>
                <strong style="flex:1;font-size:.9rem;">{{ $d->name }}</strong>
                @if(($dueByDeck[$d->id] ?? 0) > 0)
                <span class="vb-pill due">{{ app_num($dueByDeck[$d->id]) }} {{ __('due') }}</span>
                @else
                <span class="vb-pill done">{{ __('clear') }}</span>
                @endif
            </div>
            <div class="vb-meta">
                <span>{{ app_num($d->cards_count) }} {{ __('cards') }}</span>
                <a href="{{ route('vocabulary.cards.index', ['deck_id' => $d->id]) }}">{{ __('Cards') }}</a>
                <a href="{{ route('vocabulary.review.index', ['deck_id' => $d->id]) }}">{{ __('Review') }}</a>
            </div>
            <div class="vb-meta" style="margin-top:.5rem;">
                <form method="POST" action="{{ route('vocabulary.decks.update', $d) }}" class="vb-inline">
                    @csrf @method('PUT')
                    <input type="hidden" name="color" value="{{ $d->color ?: '#6366f1' }}">
                    <input name="name" value="{{ $d->name }}" maxlength="100" required style="border:1px solid #e5e7eb;border-radius:6px;padding:2px 8px;font-size:.8rem;width:140px;">
                    <button class="vb-btn" style="padding:2px 8px;" type="submit">{{ __('Save') }}</button>
                </form>
                <form method="POST" action="{{ route('vocabulary.decks.destroy', $d) }}" class="vb-inline" onsubmit="return confirm('{{ __('Delete this deck and all its cards?') }}')">
                    @csrf @method('DELETE')
                    <button class="vb-danger" type="submit">{{ __('Delete') }}</button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
