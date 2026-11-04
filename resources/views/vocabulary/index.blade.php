@extends('layouts.app')

@section('title', __('Vocabulary'))

@push('styles')
<style>
.lv-page { max-width:1000px; margin:0 auto; }

/* ── Hero ── */
.lv-hero {
    position:relative; overflow:hidden;
    background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 55%,#9333ea 100%);
    border-radius:18px; padding:24px; margin-bottom:16px; color:#fff;
    box-shadow:0 18px 36px -18px rgba(79,70,229,.55);
}
.lv-hero::before, .lv-hero::after {
    content:''; position:absolute; border-radius:50%;
    background:rgba(255,255,255,.10); pointer-events:none;
}
.lv-hero::before { width:220px; height:220px; top:-90px; inset-inline-end:-60px; }
.lv-hero::after { width:130px; height:130px; bottom:-65px; inset-inline-end:130px; background:rgba(255,255,255,.07); }
.lv-hero-in { position:relative; z-index:1; display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.lv-hero-ico {
    width:52px; height:52px; flex:none; border-radius:15px;
    display:grid; place-items:center; font-size:1.5rem;
    background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.35);
}
.lv-hero-txt { flex:1; min-width:180px; }
.lv-hero-txt h1 { margin:0; font-size:1.35rem; font-weight:800; line-height:1.3; }
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

/* ── Stat tiles ── */
.lv-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:14px; }
.lv-tile {
    background:#fff; border:1px solid #e9ebf1; border-radius:14px;
    padding:12px 14px; display:flex; align-items:center; gap:12px;
    box-shadow:0 2px 8px -4px rgba(15,23,42,.08);
    transition:transform .15s, box-shadow .15s;
}
.lv-tile:hover { transform:translateY(-2px); box-shadow:0 12px 24px -14px rgba(99,102,241,.35); }
.lv-tile-ico {
    width:42px; height:42px; flex:none; border-radius:12px;
    display:grid; place-items:center; font-size:1.15rem;
}
.lv-tile-ico.amber { background:#fef3c7; color:#d97706; }
.lv-tile-ico.indigo { background:#eef2ff; color:#4f46e5; }
.lv-tile-ico.green { background:#dcfce7; color:#059669; }
.lv-tile-ico.violet { background:#ede9ff; color:#7c3aed; }
.lv-tile .v { font-size:1.35rem; font-weight:800; color:#111827; line-height:1.1; }
.lv-tile .l { font-size:.68rem; font-weight:700; color:#6b7280; }

/* ── Box track ── */
.lv-boxtrack { display:grid; grid-template-columns:repeat(5,1fr); gap:8px; margin-bottom:14px; }
.lv-boxstep {
    background:#fff; border:1px solid #e9ebf1; border-radius:12px; padding:10px 6px;
    text-align:center; box-shadow:0 2px 8px -4px rgba(15,23,42,.08);
}
.lv-boxstep b { display:block; font-size:1.25rem; font-weight:800; color:#111827; }
.lv-boxstep span { font-size:.66rem; color:#6b7280; font-weight:700; }
.lv-boxstep .lv-track { height:5px; border-radius:99px; background:#eef2ff; margin-top:8px; overflow:hidden; }
.lv-boxstep .lv-track i { display:block; height:100%; border-radius:99px;
    background:linear-gradient(90deg,#818cf8,#a78bfa); }
@media(max-width:640px) { .lv-boxtrack { grid-template-columns:repeat(5,1fr); gap:6px; } .lv-boxstep span { display:none; } }

/* ── Sections ── */
.lv-sec {
    background:#fff; border:1px solid #e9ebf1; border-radius:16px;
    padding:18px; margin-bottom:14px;
    box-shadow:0 4px 14px -8px rgba(15,23,42,.10);
}
.lv-sec-hd { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
.lv-sec-ico {
    width:36px; height:36px; flex:none; border-radius:11px;
    display:grid; place-items:center; font-size:1rem; color:#fff;
    background:linear-gradient(135deg,#6366f1,#8b5cf6);
    box-shadow:0 8px 16px -8px rgba(99,102,241,.6);
}
.lv-sec-hd h2 { margin:0; font-size:.95rem; font-weight:800; color:#111827; flex:1; }
.lv-sec-hd .sub { font-size:.72rem; color:#6b7280; font-weight:600; }
.lv-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.lv-table th, .lv-table td { border-bottom:1px solid #eef0f3; padding:9px 10px; text-align:start; }
.lv-table th { color:#6b7280; font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; }
.lv-table tr:last-child td { border-bottom:none; }
.lv-row { display:flex; gap:8px; flex-wrap:wrap; }
.lv-row .form-control { flex:1; min-width:140px; border-radius:10px !important; }
.lv-empty { text-align:center; padding:48px 20px; background:#fff;
    border:1.5px dashed #d9defc; border-radius:16px; color:#8b8d98; }
.lv-empty .lv-empty-ico {
    width:64px; height:64px; margin:0 auto 12px; border-radius:20px;
    display:grid; place-items:center; font-size:1.8rem; color:#fff;
    background:linear-gradient(135deg,#818cf8,#a78bfa);
    box-shadow:0 12px 24px -10px rgba(124,58,237,.5);
}
.lv-empty h5 { font-weight:800; color:#1f2328; margin-bottom:4px; }
.lv-empty p { font-size:.82rem; margin:0; }

/* ── Deck grid ── */
.lv-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(270px,1fr)); gap:12px; }
.lv-deck {
    background:#fff; border:1px solid #e9ebf1; border-radius:16px; overflow:hidden;
    box-shadow:0 2px 8px -4px rgba(15,23,42,.08);
    transition:transform .15s, box-shadow .15s;
    display:flex; flex-direction:column;
}
.lv-deck:hover { transform:translateY(-3px); box-shadow:0 16px 30px -16px rgba(99,102,241,.4); }
.lv-deck-cover { height:64px; position:relative; display:flex; align-items:flex-end; padding:0 16px 10px; }
.lv-deck-cover .lv-deck-ico {
    width:38px; height:38px; border-radius:12px; background:rgba(255,255,255,.22);
    border:1px solid rgba(255,255,255,.4); display:grid; place-items:center;
    font-size:1.1rem; color:#fff; backdrop-filter:blur(3px);
    transform:translateY(14px); box-shadow:0 8px 16px -8px rgba(0,0,0,.35);
}
.lv-deck-body { padding:18px 16px 12px; }
.lv-deck-name { display:flex; align-items:center; gap:8px; }
.lv-deck-name strong { flex:1; font-size:.95rem; color:#111827; min-width:0;
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.lv-pill { display:inline-flex; align-items:center; gap:4px; padding:2px 10px; border-radius:999px;
    font-size:.66rem; font-weight:800; flex:none; }
.lv-pill.due { background:#fef3c7; color:#b45309; animation:lv-pulse 2s infinite; }
.lv-pill.done { background:#dcfce7; color:#15803d; }
@keyframes lv-pulse {
    0%,100% { box-shadow:0 0 0 0 rgba(245,158,11,.35); }
    50% { box-shadow:0 0 0 4px rgba(245,158,11,0); }
}
.lv-deck-meta { display:flex; gap:12px; margin-top:8px; font-size:.74rem; color:#6b7280; font-weight:600; }
.lv-deck-links { display:flex; gap:6px; margin-top:10px; }
.lv-deck-links a {
    flex:1; text-align:center; font-size:.74rem; font-weight:800; text-decoration:none;
    padding:7px 0; border-radius:9px; transition:all .15s;
}
.lv-deck-links a.go-cards { background:#eef2ff; color:#4f46e5; }
.lv-deck-links a.go-cards:hover { background:#e0e7ff; }
.lv-deck-links a.go-review { background:linear-gradient(135deg,#4f46e5,#8b5cf6); color:#fff; }
.lv-deck-links a.go-review:hover { filter:brightness(1.08); }
.lv-deck-edit { display:flex; gap:6px; margin-top:10px; padding-top:10px; border-top:1px dashed #eef0f3; }
.lv-deck-edit input[name="name"] { flex:1; min-width:0; border:1px solid #e5e7eb; border-radius:8px;
    padding:4px 10px; font-size:.78rem; }
.lv-mini { border:1px solid #e5e7eb; background:#fff; color:#4b5563; border-radius:8px;
    padding:4px 10px; font-size:.72rem; font-weight:700; cursor:pointer; }
.lv-mini:hover { border-color:#6366f1; color:#4f46e5; }
.lv-mini.danger { border:none; color:#b91c1c; background:none; padding:4px 6px; }
.lv-mini.danger:hover { text-decoration:underline; }
</style>
@endpush

@section('content')
<div class="lv-page">
    <header class="lv-hero">
        <div class="lv-hero-in">
            <div class="lv-hero-ico"><i class="bi bi-translate"></i></div>
            <div class="lv-hero-txt">
                <h1>{{ __('Vocabulary') }}</h1>
                <p>{{ __('Your Leitner box — a little every day beats cramming.') }}</p>
            </div>
            <div class="lv-hero-actions">
                <a href="{{ route('vocabulary.cards.index') }}" class="lv-btn lv-btn-glass"><i class="bi bi-list-ul"></i>{{ __('All cards') }}</a>
                <a href="{{ route('vocabulary.imports.create') }}" class="lv-btn lv-btn-glass"><i class="bi bi-upload"></i>{{ __('Import CSV') }}</a>
                <a href="{{ route('vocabulary.review.index') }}" class="lv-btn lv-btn-solid"><i class="bi bi-play-fill"></i>{{ __('Start review') }} ({{ app_num($stats['due']) }})</a>
            </div>
        </div>
    </header>

    <div class="lv-stats">
        <div class="lv-tile">
            <div class="lv-tile-ico amber"><i class="bi bi-alarm"></i></div>
            <div><div class="v">{{ app_num($stats['due']) }}</div><div class="l">{{ __('Due today') }}</div></div>
        </div>
        <div class="lv-tile">
            <div class="lv-tile-ico indigo"><i class="bi bi-collection"></i></div>
            <div><div class="v">{{ app_num($stats['total']) }}</div><div class="l">{{ __('Total cards') }}</div></div>
        </div>
        <div class="lv-tile">
            <div class="lv-tile-ico green"><i class="bi bi-patch-check-fill"></i></div>
            <div><div class="v">{{ app_num($stats['mastered']) }}</div><div class="l">{{ __('Mastered') }}</div></div>
        </div>
        <div class="lv-tile">
            <div class="lv-tile-ico violet"><i class="bi bi-grid"></i></div>
            <div><div class="v">{{ app_num($stats['decks']) }}</div><div class="l">{{ __('Decks') }}</div></div>
        </div>
    </div>

    <div class="lv-boxtrack">
        @php $boxMax = max(1, max($boxCounts->all() ?: [0])); @endphp
        @foreach([1 => __('Box 1 · daily'), 2 => __('Box 2 · 3 days'), 3 => __('Box 3 · weekly'), 4 => __('Box 4 · 2 weeks'), 5 => __('Box 5 · monthly')] as $box => $label)
        <div class="lv-boxstep">
            <b>{{ app_num($boxCounts[$box] ?? 0) }}</b><span>{{ $label }}</span>
            <div class="lv-track"><i style="width:{{ round(($boxCounts[$box] ?? 0) / $boxMax * 100) }}%;"></i></div>
        </div>
        @endforeach
    </div>

    <section class="lv-sec">
        <div class="lv-sec-hd">
            <div class="lv-sec-ico"><i class="bi bi-bar-chart"></i></div>
            <h2>{{ __('Progress') }}</h2>
            <span class="sub">{{ __('Last 14 days') }}@if(!is_null($overallRetention ?? null)) · {{ __('Overall retention') }}: {{ app_num($overallRetention) }}%@endif</span>
        </div>
        @if(collect($daily ?? [])->sum('reviewed') > 0)
        <div style="position:relative;height:220px;"><canvas id="vbChart"></canvas></div>
        @push('scripts')
        <script>
        (function () {
            var el = document.getElementById('vbChart');
            if (!el || typeof Chart === 'undefined') return;
            var data = @json($daily);
            new Chart(el, {
                data: {
                    labels: data.map(function (d) { return d.label; }),
                    datasets: [
                        {
                            type: 'bar',
                            label: @json(__('Reviewed')),
                            data: data.map(function (d) { return d.reviewed; }),
                            backgroundColor: 'rgba(99,102,241,.55)',
                            hoverBackgroundColor: 'rgba(79,70,229,.8)',
                            borderRadius: 5,
                            yAxisID: 'y',
                        },
                        {
                            type: 'line',
                            label: @json(__('Retention %')),
                            data: data.map(function (d) { return d.retention; }),
                            borderColor: '#059669',
                            backgroundColor: '#059669',
                            tension: .35,
                            pointRadius: 3,
                            pointBackgroundColor: '#fff',
                            pointBorderWidth: 2,
                            yAxisID: 'y1',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' }, title: { display: true, text: @json(__('Reviews')) } },
                        y1: { beginAtZero: true, max: 100, position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: function (v) { return v + '%'; } } },
                    },
                },
            });
        })();
        </script>
        @endpush
        @else
        <p style="font-size:.82rem;color:#8b8d98;margin:0;">{{ __('No review data yet — grade some cards and your retention chart appears here.') }}</p>
        @endif
        @if(!empty($deckStats))
        <div style="overflow-x:auto;margin-top:1rem;">
            <table class="lv-table">
                <thead><tr><th>{{ __('Deck') }}</th><th>{{ __('Total') }}</th><th>{{ __('Due') }}</th><th>{{ __('Mastered') }}</th><th>{{ __('7-day retention') }}</th></tr></thead>
                <tbody>
                    @foreach($deckStats as $ds)
                    <tr>
                        <td><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:{{ $ds['color'] ?: '#6366f1' }};margin-inline-end:6px;"></span><strong>{{ $ds['name'] }}</strong></td>
                        <td>{{ app_num($ds['total']) }}</td>
                        <td>@if($ds['due'] > 0)<strong style="color:#b45309;">{{ app_num($ds['due']) }}</strong>@else<span style="color:#9ca3af;">—</span>@endif</td>
                        <td>{{ app_num($ds['mastered']) }}</td>
                        <td>{{ is_null($ds['retention_7d']) ? '—' : app_num($ds['retention_7d']).'%' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </section>

    <section class="lv-sec">
        <div class="lv-sec-hd">
            <div class="lv-sec-ico"><i class="bi bi-plus-lg"></i></div>
            <h2>{{ __('New deck') }}</h2>
        </div>
        <form method="POST" action="{{ route('vocabulary.decks.store') }}">
            @csrf
            <div class="lv-row">
                <input name="name" class="form-control" required maxlength="100" placeholder="{{ __('Deck name — e.g. English') }}">
                <input name="color" type="color" class="form-control" style="max-width:64px;flex:none;" value="#6366f1" title="{{ __('Color') }}">
                <button class="lv-btn lv-btn-primary" type="submit"><i class="bi bi-plus-lg"></i>{{ __('Add') }}</button>
            </div>
        </form>
    </section>

    @if($decks->isNotEmpty())
    <section class="lv-sec">
        <div class="lv-sec-hd">
            <div class="lv-sec-ico"><i class="bi bi-lightning-charge"></i></div>
            <h2>{{ __('Quick add card') }}</h2>
            <span class="sub">{{ app_num($newToday ?? 0) }}/{{ app_num($newLimit ?? 20) }} {{ __('New today') }}</span>
        </div>
        <form method="POST" action="{{ route('vocabulary.cards.store') }}">
            @csrf
            <div class="lv-row">
                <select name="deck_id" class="form-control" required>
                    @foreach($decks as $d)
                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select>
                <input name="front" class="form-control" required maxlength="255" placeholder="{{ __('Word') }}" style="font-weight:600;">
                <input name="back" class="form-control" required maxlength="2000" placeholder="{{ __('Meaning') }}">
                <button class="lv-btn lv-btn-primary" type="submit"><i class="bi bi-plus-lg"></i>{{ __('Add') }}</button>
            </div>
        </form>
    </section>
    @endif

    @if($decks->isEmpty())
    <div class="lv-empty">
        <div class="lv-empty-ico"><i class="bi bi-collection"></i></div>
        <h5>{{ __('No decks yet') }}</h5>
        <p>{{ __('Create your first deck, then add words to it every day.') }}</p>
    </div>
    @else
    <div class="lv-grid">
        @foreach($decks as $d)
        <article class="lv-deck">
            <div class="lv-deck-cover" style="background:linear-gradient(135deg, {{ $d->color ?: '#6366f1' }}, {{ $d->color ?: '#6366f1' }}cc);">
                <div class="lv-deck-ico"><i class="bi bi-translate"></i></div>
            </div>
            <div class="lv-deck-body">
                <div class="lv-deck-name">
                    <strong>{{ $d->name }}</strong>
                    @if(($dueByDeck[$d->id] ?? 0) > 0)
                    <span class="lv-pill due"><i class="bi bi-alarm"></i>{{ app_num($dueByDeck[$d->id]) }} {{ __('due') }}</span>
                    @else
                    <span class="lv-pill done"><i class="bi bi-check"></i>{{ __('clear') }}</span>
                    @endif
                </div>
                <div class="lv-deck-meta">
                    <span><i class="bi bi-collection" style="color:#a5b4fc;"></i>{{ app_num($d->cards_count) }} {{ __('cards') }}</span>
                </div>
                <div class="lv-deck-links">
                    <a class="go-cards" href="{{ route('vocabulary.cards.index', ['deck_id' => $d->id]) }}">{{ __('Cards') }}</a>
                    <a class="go-review" href="{{ route('vocabulary.review.index', ['deck_id' => $d->id]) }}">{{ __('Review') }}</a>
                </div>
                <div class="lv-deck-edit">
                    <form method="POST" action="{{ route('vocabulary.decks.update', $d) }}" style="display:flex;gap:6px;flex:1;">
                        @csrf @method('PUT')
                        <input type="hidden" name="color" value="{{ $d->color ?: '#6366f1' }}">
                        <input name="name" value="{{ $d->name }}" maxlength="100" required>
                        <button class="lv-mini" type="submit">{{ __('Save') }}</button>
                    </form>
                    <form method="POST" action="{{ route('vocabulary.decks.destroy', $d) }}" onsubmit="return confirm('{{ __('Delete this deck and all its cards?') }}')">
                        @csrf @method('DELETE')
                        <button class="lv-mini danger" type="submit">{{ __('Delete') }}</button>
                    </form>
                </div>
            </div>
        </article>
        @endforeach
    </div>
    @endif
</div>
@endsection
