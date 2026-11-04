@extends('layouts.app')

@section('title', __('Vocabulary cards'))

@push('styles')
<style>
.lv-page { max-width:1000px; margin:0 auto; }

/* ── Hero ── */
.lv-hero {
    position:relative; overflow:hidden;
    background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 55%,#9333ea 100%);
    border-radius:18px; padding:22px 24px; margin-bottom:16px; color:#fff;
    box-shadow:0 18px 36px -18px rgba(79,70,229,.55);
}
.lv-hero::before, .lv-hero::after {
    content:''; position:absolute; border-radius:50%;
    background:rgba(255,255,255,.10); pointer-events:none;
}
.lv-hero::before { width:190px; height:190px; top:-80px; inset-inline-end:-50px; }
.lv-hero::after { width:120px; height:120px; bottom:-60px; inset-inline-end:120px; background:rgba(255,255,255,.07); }
.lv-hero-in { position:relative; z-index:1; display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.lv-hero-ico {
    width:48px; height:48px; flex:none; border-radius:14px;
    display:grid; place-items:center; font-size:1.4rem;
    background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.35);
    backdrop-filter:blur(4px);
}
.lv-hero-txt { flex:1; min-width:180px; }
.lv-hero-txt h1 { margin:0; font-size:1.25rem; font-weight:800; line-height:1.3; }
.lv-hero-txt p { margin:2px 0 0; font-size:.8rem; color:rgba(255,255,255,.82); }
.lv-hero-actions { display:flex; gap:8px; flex-wrap:wrap; }
.lv-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:8px 14px; border-radius:10px; font-size:.8rem; font-weight:700;
    text-decoration:none; cursor:pointer; border:1px solid transparent;
    transition:transform .15s, box-shadow .15s, background .15s;
    white-space:nowrap;
}
.lv-btn:active { transform:scale(.97); }
.lv-btn-glass { background:rgba(255,255,255,.16); color:#fff; border-color:rgba(255,255,255,.35); }
.lv-btn-glass:hover { background:rgba(255,255,255,.26); color:#fff; }
.lv-btn-solid { background:#fff; color:#4f46e5; box-shadow:0 8px 18px -8px rgba(0,0,0,.35); }
.lv-btn-solid:hover { color:#4338ca; transform:translateY(-1px); }
.lv-btn-primary {
    background:linear-gradient(135deg,#4f46e5,#8b5cf6); color:#fff;
    box-shadow:0 10px 20px -8px rgba(99,102,241,.6);
}
.lv-btn-primary:hover { color:#fff; transform:translateY(-1px); }
.lv-btn-ghost { background:#fff; color:#4b5563; border-color:#e5e7eb; }
.lv-btn-ghost:hover { border-color:#6366f1; color:#4f46e5; }

/* ── Filter card ── */
.lv-filters {
    background:#fff; border:1px solid #e9ebf1; border-radius:14px;
    padding:12px 14px; margin-bottom:12px;
    display:flex; gap:10px; flex-wrap:wrap; align-items:center;
    box-shadow:0 4px 14px -8px rgba(15,23,42,.12);
}
.lv-search { position:relative; flex:2; min-width:200px; }
.lv-search i {
    position:absolute; top:50%; transform:translateY(-50%);
    inset-inline-start:12px; color:#9ca3af; font-size:.9rem; pointer-events:none;
}
.lv-search input { width:100%; padding-inline-start:34px !important; border-radius:10px !important; }
.lv-filters select { border-radius:10px !important; min-width:130px; }
.lv-count { margin-inline-start:auto; font-size:.75rem; font-weight:700; color:#6b7280;
    background:#f3f4f6; border-radius:999px; padding:4px 12px; white-space:nowrap; }
.lv-clear { font-size:.75rem; font-weight:700; color:#6366f1; text-decoration:none; }
.lv-clear:hover { text-decoration:underline; }

/* ── Add card ── */
.lv-add {
    background:linear-gradient(#fff,#fff) padding-box,
               linear-gradient(135deg,#c7d2fe,#e9d5ff) border-box;
    border:1.5px solid transparent; border-radius:14px;
    padding:14px 16px; margin-bottom:14px;
    box-shadow:0 6px 18px -10px rgba(99,102,241,.25);
}
.lv-add-head { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.lv-add-head h6 { margin:0; font-size:.85rem; font-weight:800; color:#1f2328; }
.lv-budget { font-size:.72rem; font-weight:800; color:#4f46e5; background:#eef2ff;
    border-radius:999px; padding:3px 11px; }
.lv-budget.full { color:#b45309; background:#fef3c7; }
.lv-add-grid { display:flex; gap:8px; flex-wrap:wrap; }
.lv-add-grid select { flex:1; min-width:140px; border-radius:10px !important; }
.lv-add-grid input { border-radius:10px !important; }
.lv-add-grid input[name="front"] { flex:2; min-width:160px; font-weight:600; }
.lv-add-grid input[name="back"] { flex:2; min-width:160px; }

/* ── Card rows ── */
.lv-card {
    display:flex; gap:14px; align-items:flex-start;
    background:#fff; border:1px solid #e9ebf1; border-radius:14px;
    padding:14px 16px; margin-bottom:10px;
    box-shadow:0 2px 8px -4px rgba(15,23,42,.08);
    transition:transform .15s, box-shadow .15s, border-color .15s;
}
.lv-card:hover { transform:translateY(-2px); border-color:#d9defc;
    box-shadow:0 12px 26px -14px rgba(99,102,241,.35); }
.lv-boxbadge {
    flex:none; width:46px; border-radius:12px; text-align:center;
    padding:8px 0 7px; color:#fff; font-weight:800; font-size:.82rem; line-height:1;
    box-shadow:0 6px 14px -6px rgba(15,23,42,.4);
}
.lv-boxbadge small { display:block; font-size:.58rem; font-weight:700; opacity:.85; margin-top:2px; }
.lv-box-1 { background:linear-gradient(135deg,#94a3b8,#64748b); }
.lv-box-2 { background:linear-gradient(135deg,#38bdf8,#0284c7); }
.lv-box-3 { background:linear-gradient(135deg,#818cf8,#4f46e5); }
.lv-box-4 { background:linear-gradient(135deg,#a78bfa,#7c3aed); }
.lv-box-5 { background:linear-gradient(135deg,#34d399,#059669); }
.lv-dots { display:flex; gap:3px; justify-content:center; margin-top:6px; }
.lv-dots i { width:5px; height:5px; border-radius:50%; background:rgba(255,255,255,.4); }
.lv-dots i.on { background:#fff; }
.lv-main { flex:1; min-width:0; }
.lv-wordrow { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.lv-word { font-weight:800; font-size:1rem; color:#111827; }
.lv-arrow { color:#c4b5fd; font-size:.85rem; }
.lv-meaning { color:#4b5563; font-size:.9rem; }
.lv-pills { display:flex; gap:6px; flex-wrap:wrap; margin-inline-start:auto; }
.lv-pill { display:inline-flex; align-items:center; gap:4px; padding:2px 10px; border-radius:999px;
    font-size:.66rem; font-weight:800; }
.lv-pill.box { background:#eef2ff; color:#4f46e5; }
.lv-pill.due { background:#fef3c7; color:#b45309; animation:lv-pulse 2s infinite; }
.lv-pill.done { background:#dcfce7; color:#15803d; }
@keyframes lv-pulse {
    0%,100% { box-shadow:0 0 0 0 rgba(245,158,11,.35); }
    50% { box-shadow:0 0 0 4px rgba(245,158,11,0); }
}
.lv-meta { display:flex; gap:6px 14px; flex-wrap:wrap; margin-top:6px;
    font-size:.72rem; color:#6b7280; }
.lv-meta span { display:inline-flex; align-items:center; gap:5px; }
.lv-meta i { color:#a5b4fc; font-size:.78rem; }
.lv-meta .ex { flex-basis:100%; color:#6b7280; background:#f8fafc; border-radius:8px;
    padding:5px 10px; margin-top:2px; font-style:italic; }
.lv-actions { display:flex; gap:2px; flex:none; }
.lv-iconbtn {
    width:32px; height:32px; border-radius:9px; border:1px solid transparent;
    background:transparent; color:#9ca3af; cursor:pointer;
    display:inline-grid; place-items:center; font-size:.9rem;
    transition:all .15s; text-decoration:none;
}
.lv-iconbtn:hover { background:#eef2ff; color:#4f46e5; border-color:#e0e7ff; }
.lv-iconbtn.danger:hover { background:#fee2e2; color:#b91c1c; border-color:#fecaca; }
.lv-edit { margin-top:10px; padding-top:10px; border-top:1px dashed #e5e7eb;
    display:flex; gap:8px; flex-wrap:wrap; }
.lv-edit input { flex:1; min-width:140px; border-radius:10px !important; font-size:.82rem; }
.lv-empty { text-align:center; padding:56px 20px; background:#fff;
    border:1.5px dashed #d9defc; border-radius:16px; color:#8b8d98; }
.lv-empty .lv-empty-ico {
    width:64px; height:64px; margin:0 auto 12px; border-radius:20px;
    display:grid; place-items:center; font-size:1.8rem; color:#fff;
    background:linear-gradient(135deg,#818cf8,#a78bfa);
    box-shadow:0 12px 24px -10px rgba(124,58,237,.5);
}
.lv-empty h5 { font-weight:800; color:#1f2328; margin-bottom:4px; }
.lv-empty p { font-size:.82rem; margin:0; }
.lv-pages { display:flex; justify-content:center; margin-top:16px; }

@media(max-width:640px) {
    .lv-hero { padding:18px; }
    .lv-card { padding:12px; gap:10px; }
    .lv-pills { margin-inline-start:0; }
    .lv-actions { flex-direction:row; }
}
</style>
@endpush

@section('content')
<div class="lv-page">
    <header class="lv-hero">
        <div class="lv-hero-in">
            <div class="lv-hero-ico"><i class="bi bi-collection"></i></div>
            <div class="lv-hero-txt">
                <h1>{{ __('Vocabulary cards') }}</h1>
                <p>{{ app_num($cards->total()) }} {{ __('cards') }} · {{ __('Box 1 · daily') }} → {{ __('Box 5 · monthly') }}</p>
            </div>
            <div class="lv-hero-actions">
                <a href="{{ route('vocabulary.index') }}" class="lv-btn lv-btn-glass"><i class="bi bi-grid"></i>{{ __('Decks') }}</a>
                <a href="{{ route('vocabulary.imports.create') }}" class="lv-btn lv-btn-glass"><i class="bi bi-upload"></i>{{ __('Import CSV') }}</a>
                <a href="{{ route('vocabulary.review.index') }}" class="lv-btn lv-btn-solid"><i class="bi bi-play-fill"></i>{{ __('Start review') }}</a>
            </div>
        </div>
    </header>

    <form method="GET" action="{{ route('vocabulary.cards.index') }}" class="lv-filters">
        <div class="lv-search">
            <i class="bi bi-search"></i>
            <input name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search word or meaning') }}">
        </div>
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
        <button class="lv-btn lv-btn-primary" type="submit"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
        @if(!empty($filters['search']) || ($filters['deck_id'] ?? 'all') !== 'all' || ($filters['box'] ?? 'all') !== 'all' || !empty($filters['filter']))
        <a href="{{ route('vocabulary.cards.index') }}" class="lv-clear">{{ __('Clear') }}</a>
        @endif
        <span class="lv-count">{{ app_num($cards->total()) }} {{ __('cards') }}</span>
    </form>

    @if($decks->isNotEmpty())
    <form method="POST" action="{{ route('vocabulary.cards.store') }}" class="lv-add">
        @csrf
        <div class="lv-add-head">
            <h6><i class="bi bi-plus-circle" style="color:#6366f1;"></i> {{ __('Quick add card') }}</h6>
            <span class="lv-budget {{ ($newToday ?? 0) >= ($newLimit ?? 20) ? 'full' : '' }}">{{ app_num($newToday ?? 0) }}/{{ app_num($newLimit ?? 20) }} {{ __('New today') }}</span>
        </div>
        <div class="lv-add-grid">
            <select name="deck_id" class="form-control" required>
                @foreach($decks as $d)
                <option value="{{ $d->id }}" {{ ($filters['deck_id'] ?? '') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
            </select>
            <input name="front" class="form-control" required maxlength="255" placeholder="{{ __('Word') }}">
            <input name="back" class="form-control" required maxlength="2000" placeholder="{{ __('Meaning') }}">
            <button class="lv-btn lv-btn-primary" type="submit"><i class="bi bi-plus-lg"></i>{{ __('Add') }}</button>
        </div>
    </form>
    @endif

    @forelse($cards as $card)
    <article class="lv-card">
        <div class="lv-boxbadge lv-box-{{ $card->box }}">
            B{{ app_num($card->box) }}<small>{{ __('Box') }}</small>
            <div class="lv-dots">
                @for($i = 1; $i <= 5; $i++)<i class="{{ $i <= $card->box ? 'on' : '' }}"></i>@endfor
            </div>
        </div>
        <div class="lv-main">
            <div class="lv-wordrow">
                <span class="lv-word">{{ $card->front }}</span>
                <i class="bi bi-arrow-right lv-arrow no-rtl" aria-hidden="true"></i>
                <span class="lv-meaning">{{ $card->back }}</span>
                <span class="lv-pills">
                    @if($card->is_due)<span class="lv-pill due"><i class="bi bi-alarm"></i>{{ __('due') }}</span>@endif
                    @if($card->is_mastered)<span class="lv-pill done"><i class="bi bi-patch-check-fill"></i>{{ __('Mastered') }}</span>@endif
                </span>
            </div>
            <div class="lv-meta">
                <span><i class="bi bi-collection"></i>{{ $card->deck->name ?? '' }}</span>
                <span><i class="bi bi-calendar-event"></i>{{ __('Next') }}: {{ $card->next_review_at?->format('Y-m-d') }}</span>
                <span><i class="bi bi-arrow-repeat"></i>{{ app_num($card->review_count) }} {{ __('reviews') }}</span>
                @if($card->example)<span class="ex"><i class="bi bi-quote"></i>{{ \Illuminate\Support\Str::limit($card->example, 80) }}</span>@endif
            </div>
            <div class="lv-edit" id="edit-{{ $card->id }}" hidden>
                <form method="POST" action="{{ route('vocabulary.cards.update', $card) }}" style="display:flex;gap:8px;flex:1;flex-wrap:wrap;">
                    @csrf @method('PUT')
                    <input type="hidden" name="deck_id" value="{{ $card->deck_id }}">
                    <input name="front" class="form-control" value="{{ $card->front }}" required maxlength="255">
                    <input name="back" class="form-control" value="{{ $card->back }}" required maxlength="2000">
                    <button class="lv-btn lv-btn-primary" type="submit"><i class="bi bi-check-lg"></i>{{ __('Save') }}</button>
                </form>
            </div>
        </div>
        <div class="lv-actions">
            <button type="button" class="lv-iconbtn" data-edit-toggle="edit-{{ $card->id }}" title="{{ __('Save') }}"><i class="bi bi-pencil"></i></button>
            <form method="POST" action="{{ route('vocabulary.cards.reset', $card) }}" style="display:inline;">
                @csrf
                <button class="lv-iconbtn" type="submit" title="{{ __('Back to Box 1') }}"><i class="bi bi-arrow-counterclockwise"></i></button>
            </form>
            <form method="POST" action="{{ route('vocabulary.cards.destroy', $card) }}" style="display:inline;" onsubmit="return confirm('{{ __('Delete this card?') }}')">
                @csrf @method('DELETE')
                <button class="lv-iconbtn danger" type="submit" title="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
            </form>
        </div>
    </article>
    @empty
    <div class="lv-empty">
        <div class="lv-empty-ico"><i class="bi bi-search"></i></div>
        <h5>{{ __('No cards found.') }}</h5>
        <p>{{ __('Try different filters or add a new word above.') }}</p>
    </div>
    @endforelse

    <div class="lv-pages">{{ $cards->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-edit-toggle]');
    if (!btn) return;
    var panel = document.getElementById(btn.getAttribute('data-edit-toggle'));
    if (panel) { panel.hidden = !panel.hidden; if (!panel.hidden) panel.querySelector('input[name="front"]')?.focus(); }
});
</script>
@endpush
