@extends('layouts.app')

@section('title', __('Vocabulary cards'))

@push('styles')
<style>
.vb-wrap { max-width:960px; margin:0 auto; }
.vb-topbar { display:flex; align-items:center; gap:10px; margin-bottom:12px; flex-wrap:wrap; }
.vb-title { font-size:19px; font-weight:700; color:#1f2328; margin:0; flex:1; }
.vb-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:6px; border:1px solid #e5e7eb; background:white; color:#6b6f78; font-size:12px; font-weight:600; text-decoration:none; cursor:pointer; }
.vb-btn:hover { border-color:#6366f1; color:#6366f1; text-decoration:none; }
.vb-btn.primary { background:#6366f1; border-color:#6366f1; color:white; }
.vb-filter { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:.65rem 1rem; margin-bottom:1rem; display:flex; gap:.6rem; flex-wrap:wrap; align-items:center; }
.vb-filter .form-control { width:auto; min-width:130px; }
.vb-row-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:.7rem .9rem; margin-bottom:.5rem; }
.vb-row-card .head { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.vb-word { font-weight:700; font-size:.9rem; color:#111827; }
.vb-meaning { color:#374151; font-size:.85rem; }
.vb-pill { display:inline-flex; align-items:center; padding:1px 8px; border-radius:20px; font-size:10.5px; font-weight:700; background:#eef2ff; color:#4f46e5; }
.vb-pill.due { background:#fef3c7; color:#b45309; }
.vb-meta { font-size:.72rem; color:#6b7280; margin-top:4px; display:flex; gap:8px; flex-wrap:wrap; }
.vb-danger { color:#b91c1c; background:none; border:none; font-size:.75rem; font-weight:600; cursor:pointer; padding:0; }
.vb-danger:hover { text-decoration:underline; }
.vb-edit { display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.5rem; }
.vb-edit .form-control { flex:1; min-width:120px; font-size:.8rem; padding:.3rem .6rem; }
</style>
@endpush

@section('content')
<div class="vb-wrap">
    <div class="vb-topbar">
        <h1 class="vb-title">{{ __('Vocabulary cards') }}</h1>
        <a href="{{ route('vocabulary.index') }}" class="vb-btn"><i class="bi bi-arrow-left"></i>{{ __('Decks') }}</a>
        <a href="{{ route('vocabulary.review.index') }}" class="vb-btn primary"><i class="bi bi-play-fill"></i>{{ __('Start review') }}</a>
    </div>

    <form method="GET" action="{{ route('vocabulary.cards.index') }}" class="vb-filter">
        <input name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search word or meaning') }}">
        <select name="deck_id" class="form-control">
            <option value="all">{{ __('All decks') }}</option>
            @foreach($decks as $d)
            <option value="{{ $d->id }}" {{ ($filters['deck_id'] ?? 'all') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
            @endforeach
        </select>
        <select name="box" class="form-control">
            <option value="all">{{ __('All boxes') }}</option>
            @for($b = 1; $b <= 5; $b++)
            <option value="{{ $b }}" {{ ($filters['box'] ?? 'all') == (string) $b ? 'selected' : '' }}>{{ __('Box :n', ['n' => $b]) }}</option>
            @endfor
        </select>
        <select name="filter" class="form-control">
            <option value="">{{ __('All') }}</option>
            <option value="due" {{ ($filters['filter'] ?? '') === 'due' ? 'selected' : '' }}>{{ __('Due') }}</option>
            <option value="learning" {{ ($filters['filter'] ?? '') === 'learning' ? 'selected' : '' }}>{{ __('Learning') }}</option>
            <option value="mastered" {{ ($filters['filter'] ?? '') === 'mastered' ? 'selected' : '' }}>{{ __('Mastered') }}</option>
        </select>
        <button class="vb-btn" type="submit">{{ __('Filter') }}</button>
    </form>

    @if($decks->isNotEmpty())
    <form method="POST" action="{{ route('vocabulary.cards.store') }}" class="vb-row-card">
        @csrf
        <div class="vb-edit">
            <select name="deck_id" class="form-control" required>
                @foreach($decks as $d)
                <option value="{{ $d->id }}" {{ ($filters['deck_id'] ?? '') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
            </select>
            <input name="front" class="form-control" required maxlength="255" placeholder="{{ __('Word') }}">
            <input name="back" class="form-control" required maxlength="2000" placeholder="{{ __('Meaning') }}">
            <button class="vb-btn primary" type="submit">{{ __('Add') }}</button>
        </div>
    </form>
    @endif

    @forelse($cards as $card)
    <div class="vb-row-card">
        <div class="head">
            <span class="vb-word">{{ $card->front }}</span>
            <span style="color:#9ca3af;">→</span>
            <span class="vb-meaning">{{ $card->back }}</span>
            <span class="vb-pill">{{ __('Box :n', ['n' => $card->box]) }}</span>
            @if($card->is_due)<span class="vb-pill due">{{ __('due') }}</span>@endif
        </div>
        <div class="vb-meta">
            <span>{{ $card->deck->name ?? '' }}</span>
            <span>{{ __('Next') }}: {{ $card->next_review_at?->format('Y-m-d') }}</span>
            <span>{{ app_num($card->review_count) }} {{ __('reviews') }}</span>
            @if($card->example)<span>{{ __('Ex') }}: {{ \Illuminate\Support\Str::limit($card->example, 80) }}</span>@endif
        </div>
        <div class="vb-edit">
            <form method="POST" action="{{ route('vocabulary.cards.update', $card) }}" style="display:flex;gap:.4rem;flex:1;flex-wrap:wrap;">
                @csrf @method('PUT')
                <input type="hidden" name="deck_id" value="{{ $card->deck_id }}">
                <input name="front" class="form-control" value="{{ $card->front }}" required maxlength="255">
                <input name="back" class="form-control" value="{{ $card->back }}" required maxlength="2000">
                <button class="vb-btn" type="submit">{{ __('Save') }}</button>
            </form>
            <form method="POST" action="{{ route('vocabulary.cards.reset', $card) }}">
                @csrf
                <button class="vb-btn" type="submit" title="{{ __('Back to Box 1') }}"><i class="bi bi-arrow-counterclockwise"></i></button>
            </form>
            <form method="POST" action="{{ route('vocabulary.cards.destroy', $card) }}" onsubmit="return confirm('{{ __('Delete this card?') }}')">
                @csrf @method('DELETE')
                <button class="vb-danger" type="submit">{{ __('Delete') }}</button>
            </form>
        </div>
    </div>
    @empty
    <div class="vb-row-card" style="text-align:center;color:#8b8d98;">{{ __('No cards found.') }}</div>
    @endforelse

    <div style="margin-top:1rem;">{{ $cards->links() }}</div>
</div>
@endsection
