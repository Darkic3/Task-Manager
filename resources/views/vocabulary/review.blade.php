@extends('layouts.app')

@section('title', __('Review'))

@push('styles')
<style>
.lv-page { max-width:680px; margin:0 auto; }

/* ── Compact hero ── */
.lv-hero {
    position:relative; overflow:hidden;
    background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 55%,#9333ea 100%);
    border-radius:18px; padding:18px 20px; margin-bottom:14px; color:#fff;
    box-shadow:0 18px 36px -18px rgba(79,70,229,.55);
}
.lv-hero::before {
    content:''; position:absolute; width:170px; height:170px; top:-70px;
    inset-inline-end:-45px; border-radius:50%; background:rgba(255,255,255,.10);
}
.lv-hero-in { position:relative; z-index:1; display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.lv-hero-ico {
    width:44px; height:44px; flex:none; border-radius:13px;
    display:grid; place-items:center; font-size:1.3rem;
    background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.35);
}
.lv-hero-txt { flex:1; min-width:140px; }
.lv-hero-txt h1 { margin:0; font-size:1.15rem; font-weight:800; }
.lv-hero-txt p { margin:1px 0 0; font-size:.76rem; color:rgba(255,255,255,.82); }
.lv-hero select {
    background:rgba(255,255,255,.16); color:#fff; border:1px solid rgba(255,255,255,.35);
    border-radius:10px; font-size:.78rem; font-weight:700; padding:7px 10px;
    max-width:150px;
}
.lv-hero select option { color:#111827; }
.lv-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:8px 14px; border-radius:10px; font-size:.8rem; font-weight:700;
    text-decoration:none; cursor:pointer; border:1px solid transparent;
    transition:transform .15s, background .15s; white-space:nowrap;
}
.lv-btn:active { transform:scale(.97); }
.lv-btn-glass { background:rgba(255,255,255,.16); color:#fff; border-color:rgba(255,255,255,.35); }
.lv-btn-glass:hover { background:rgba(255,255,255,.26); color:#fff; }

/* ── Timer ── */
.lv-timer {
    background:#fff; border:1px solid #e9ebf1; border-radius:14px;
    padding:10px 14px; margin-bottom:12px;
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    box-shadow:0 4px 14px -8px rgba(15,23,42,.10);
}
.lv-timer .form-control { width:auto; padding:.3rem .6rem; font-size:.78rem; border-radius:9px !important; }
.lv-timer-clock { font-variant-numeric:tabular-nums; font-weight:800; font-size:1.05rem;
    color:#111827; min-width:60px; text-align:center; }
.lv-timer-bar { flex:1; min-width:90px; height:7px; background:#eef2ff; border-radius:99px; overflow:hidden; }
.lv-timer-bar > div { height:100%; background:linear-gradient(90deg,#6366f1,#a78bfa);
    width:0%; transition:width .5s linear; }
.lv-tbtn { border:1px solid #e5e7eb; background:#fff; border-radius:9px; padding:5px 12px;
    font-size:.76rem; font-weight:800; color:#4b5563; cursor:pointer; transition:all .15s; }
.lv-tbtn:hover:not(:disabled) { border-color:#6366f1; color:#4f46e5; }
.lv-tbtn:disabled { opacity:.4; cursor:default; }
.lv-overlay { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex;
    align-items:center; justify-content:center; z-index:2000; padding:1rem; backdrop-filter:blur(3px); }
.lv-overlay-card { background:#fff; border-radius:18px; padding:2rem 1.6rem; text-align:center;
    max-width:360px; width:100%; box-shadow:0 24px 48px rgba(0,0,0,.25); }

/* ── Session progress ── */
.lv-progress { height:9px; background:#fff; border:1px solid #e9ebf1; border-radius:99px;
    overflow:hidden; margin-bottom:6px; box-shadow:inset 0 1px 3px rgba(15,23,42,.06); }
.lv-progress > div { height:100%; background:linear-gradient(90deg,#6366f1,#a78bfa);
    border-radius:99px; transition:width .4s; }
.lv-progress-txt { font-size:.76rem; color:#6b7280; font-weight:600; margin-bottom:12px;
    display:flex; align-items:center; gap:6px; }

/* ── Flashcard ── */
.lv-flash {
    position:relative; overflow:hidden; text-align:center;
    background:#fff; border:1px solid #e9ebf1; border-radius:20px;
    padding:0 0 26px; box-shadow:0 24px 48px -20px rgba(79,70,229,.30);
}
.lv-flash-ribbon { height:8px; background:linear-gradient(90deg,#4f46e5,#8b5cf6,#d946ef); }
.lv-flash-top { display:flex; align-items:center; justify-content:center; gap:8px;
    padding:14px 20px 0; flex-wrap:wrap; }
.lv-deckname { font-size:.7rem; font-weight:800; text-transform:uppercase;
    letter-spacing:.06em; color:#6366f1; }
.lv-boxpill { display:inline-flex; align-items:center; gap:5px; font-size:.66rem; font-weight:800;
    color:#4f46e5; background:#eef2ff; border-radius:999px; padding:3px 11px; }
.lv-boxpill .lv-dots { display:inline-flex; gap:3px; }
.lv-boxpill .lv-dots i { width:6px; height:6px; border-radius:50%; background:#c7d2fe; }
.lv-boxpill .lv-dots i.on { background:#4f46e5; }
.lv-prompt { font-size:2.4rem; font-weight:800; color:#111827; margin:14px 20px 6px;
    line-height:1.25; overflow-wrap:anywhere; }
.lv-dir-hint { font-size:.72rem; color:#9ca3af; font-weight:600; }
.lv-answer { display:none; padding:0 24px; animation:lv-rise .3s ease; }
@keyframes lv-rise { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }
.lv-answer hr { border:none; border-top:1px dashed #e5e7eb; margin:16px auto; max-width:220px; }
.lv-ans { font-size:1.5rem; font-weight:800; color:#059669; overflow-wrap:anywhere; }
.lv-ex { display:inline-block; font-size:.82rem; color:#6b7280; font-style:italic;
    background:#f8fafc; border-radius:10px; padding:7px 14px; margin-top:10px; max-width:100%; }
.lv-grade { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:18px; }
.lv-grade button {
    border:none; border-radius:13px; padding:14px 10px; font-weight:800; font-size:.95rem;
    cursor:pointer; transition:transform .15s, filter .15s;
    display:flex; align-items:center; justify-content:center; gap:8px;
}
.lv-grade button:hover { filter:brightness(.96); transform:translateY(-1px); }
.lv-grade button:active { transform:scale(.98); }
.lv-forgot { background:linear-gradient(135deg,#fee2e2,#fecaca); color:#b91c1c;
    box-shadow:0 10px 20px -10px rgba(185,28,28,.4); }
.lv-remembered { background:linear-gradient(135deg,#d1fae5,#a7f3d0); color:#047857;
    box-shadow:0 10px 20px -10px rgba(4,120,87,.4); }
.lv-kbd { font-size:.62rem; font-weight:800; background:rgba(0,0,0,.12); border-radius:5px;
    padding:1px 6px; }
.lv-show {
    margin-top:18px; border:none; cursor:pointer;
    background:linear-gradient(135deg,#4f46e5,#8b5cf6); color:#fff;
    font-weight:800; font-size:.9rem; padding:12px 34px; border-radius:12px;
    box-shadow:0 12px 24px -10px rgba(99,102,241,.6);
    transition:transform .15s, filter .15s;
    display:inline-flex; align-items:center; gap:8px;
}
.lv-show:hover { filter:brightness(1.06); transform:translateY(-1px); }
.lv-tip { font-size:.74rem; color:#9ca3af; text-align:center; margin-top:12px; }
.lv-tip kbd { font-family:inherit; font-size:.66rem; font-weight:800; background:#eef2ff;
    color:#4f46e5; border-radius:5px; padding:1px 7px; }
/* ── Undo bar (five seconds) ── */
.lv-undo {
    position:fixed; bottom:22px; inset-inline:0; margin-inline:auto;
    width:min(430px, calc(100% - 24px)); z-index:1500;
    display:flex; align-items:center; gap:9px; overflow:hidden;
    background:#111827; color:#f9fafb; border-radius:14px;
    padding:11px 12px 12px; box-shadow:0 18px 34px -14px rgba(15,23,42,.65);
    animation:lvUndoIn .25s ease;
}
.lv-undo > .bi { font-size:1.05rem; color:#a5b4fc; }
.lv-undo-txt { flex:1; min-width:0; font-size:.78rem; font-weight:700; }
.lv-undo-txt strong { color:#fff; }
.lv-undo form { flex:none; margin:0; }
.lv-undo-btn {
    border:none; cursor:pointer; border-radius:9px; padding:7px 14px;
    font-size:.78rem; font-weight:800; color:#312e81; background:#c7d2fe;
    transition:filter .15s;
}
.lv-undo-btn:hover { filter:brightness(1.07); }
.lv-undo-bar {
    position:absolute; bottom:0; inset-inline:0; height:3px;
    background:linear-gradient(90deg,#818cf8,#c4b5fd);
    transform-origin:left center; animation:lvUndoBar 5s linear forwards;
}
[dir="rtl"] .lv-undo-bar { transform-origin:right center; }
.lv-undo.is-out { animation:lvUndoOut .2s ease forwards; }
@keyframes lvUndoIn { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:none; } }
@keyframes lvUndoOut { to { opacity:0; transform:translateY(10px); } }
@keyframes lvUndoBar { from { transform:scaleX(1); } to { transform:scaleX(0); } }

.lv-empty { text-align:center; padding:56px 20px; background:#fff;
    border:1.5px dashed #d9defc; border-radius:18px; color:#8b8d98; }
.lv-empty .lv-big { font-size:2.6rem; }
.lv-empty h5 { color:#111827; font-weight:800; margin:.6rem 0; }
.lv-empty p { font-size:.85rem; margin-bottom:14px; }
</style>
@endpush

@section('content')
<div class="lv-page">
    <header class="lv-hero">
        <div class="lv-hero-in">
            <div class="lv-hero-ico"><i class="bi bi-lightning-charge"></i></div>
            <div class="lv-hero-txt">
                <h1>{{ __('Review') }}</h1>
                <p>{{ __('Answer honestly — failing is part of learning.') }}</p>
            </div>
            <form method="GET" action="{{ route('vocabulary.review.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;">
                <select name="deck_id" onchange="this.form.submit()">
                    <option value="all">{{ __('All decks') }}</option>
                    @foreach($decks as $d)
                    <option value="{{ $d->id }}" {{ (string) $deckId === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                    @endforeach
                </select>
                <select name="dir" onchange="this.form.submit()" title="{{ __('Direction') }}">
                    @foreach($dirs as $value => $label)
                    <option value="{{ $value }}" {{ $dir === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('vocabulary.index') }}" class="lv-btn lv-btn-glass"><i class="bi bi-grid"></i>{{ __('Decks') }}</a>
        </div>
    </header>

    @if($sessionCount > 0)
    <div class="lv-timer" id="vbTimer" role="timer" aria-label="{{ __('Focus timer') }}">
        <i class="bi bi-stopwatch" style="color:#6366f1;"></i>
        <select id="vbTimerPreset" class="form-control" title="{{ __('Focus timer') }}">
            <option value="0">{{ __('Off') }}</option>
            <option value="5">5 {{ __('min') }}</option>
            <option value="10">10 {{ __('min') }}</option>
            <option value="15">15 {{ __('min') }}</option>
            <option value="25">25 {{ __('min') }}</option>
        </select>
        <span class="lv-timer-clock" id="vbTimerClock">--:--</span>
        <div class="lv-timer-bar"><div id="vbTimerFill"></div></div>
        <button type="button" class="lv-tbtn" id="vbTimerStart">{{ __('Start') }}</button>
        <button type="button" class="lv-tbtn" id="vbTimerPause" disabled>{{ __('Pause') }}</button>
        <button type="button" class="lv-tbtn" id="vbTimerReset" disabled>{{ __('Reset') }}</button>
    </div>
    <div class="lv-overlay" id="vbTimerOverlay" hidden>
        <div class="lv-overlay-card">
            <div style="font-size:2.2rem;">⏰</div>
            <h5 style="color:#111827;font-weight:800;margin:.5rem 0;">{{ __("Time's up!") }}</h5>
            <p style="font-size:.85rem;color:#6b7280;" id="vbTimerDoneText"></p>
            <div style="display:flex;gap:.5rem;justify-content:center;margin-top:1rem;flex-wrap:wrap;">
                <button type="button" class="lv-btn" id="vbTimerKeep" style="background:#f3f4f6;color:#374151;">{{ __('Keep going') }}</button>
                <a href="{{ route('vocabulary.index') }}" class="lv-btn" style="background:linear-gradient(135deg,#4f46e5,#8b5cf6);color:#fff;">{{ __('Back to decks') }}</a>
            </div>
        </div>
    </div>
    @push('scripts')
    <script>
    (function () {
        var KEY = 'vocab_focus_v1';
        var preset = document.getElementById('vbTimerPreset');
        var clock = document.getElementById('vbTimerClock');
        var fill = document.getElementById('vbTimerFill');
        var startBtn = document.getElementById('vbTimerStart');
        var pauseBtn = document.getElementById('vbTimerPause');
        var resetBtn = document.getElementById('vbTimerReset');
        var overlay = document.getElementById('vbTimerOverlay');
        var doneText = document.getElementById('vbTimerDoneText');
        var keepBtn = document.getElementById('vbTimerKeep');
        if (!preset) return;

        function load() {
            try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; }
        }
        function save(s) { localStorage.setItem(KEY, JSON.stringify(s)); }
        function fmt(ms) {
            var s = Math.max(0, Math.ceil(ms / 1000));
            var m = Math.floor(s / 60), r = s % 60;
            return (m < 10 ? '0' + m : m) + ':' + (r < 10 ? '0' + r : r);
        }

        var st = load();
        if (st.minutes) preset.value = String(st.minutes);
        var total = (st.minutes || 0) * 60000;
        var remaining = st.remaining != null ? st.remaining : total;
        var running = !!(st.running && st.deadline && st.deadline > Date.now() && total > 0);
        if (st.running && !running) { remaining = 0; } // expired while away
        var done = st.done || 0;
        var timer = null;

        function render() {
            clock.textContent = total > 0 ? fmt(remaining) : '--:--';
            fill.style.width = total > 0 ? (100 * (1 - remaining / total)).toFixed(1) + '%' : '0%';
            startBtn.disabled = running || total <= 0;
            pauseBtn.disabled = !running;
            resetBtn.disabled = total <= 0 && remaining <= 0;
            preset.disabled = running;
        }
        function tick() {
            var s = load();
            remaining = Math.max(0, (s.deadline || 0) - Date.now());
            if (remaining <= 0) { finish(); return; }
            render();
        }
        function finish() {
            clearInterval(timer); timer = null; running = false;
            remaining = 0;
            save({ minutes: st.minutes || 10, remaining: 0, running: false, done: done });
            doneText.textContent = @json(__('You reviewed :count cards in this session.')).replace(':count', done);
            overlay.hidden = false;
            render();
        }

        preset.addEventListener('change', function () {
            var m = parseInt(preset.value, 10) || 0;
            clearInterval(timer); timer = null; running = false;
            st = { minutes: m, remaining: m * 60000, running: false, done: 0 };
            done = 0; total = m * 60000; remaining = total;
            save(st); render();
        });
        startBtn.addEventListener('click', function () {
            var m = parseInt(preset.value, 10) || 0;
            if (m <= 0) return;
            total = m * 60000;
            if (remaining <= 0) { remaining = total; done = 0; }
            running = true;
            save({ minutes: m, remaining: remaining, running: true, deadline: Date.now() + remaining, done: done });
            overlay.hidden = true;
            clearInterval(timer); timer = setInterval(tick, 500); tick();
            render();
        });
        pauseBtn.addEventListener('click', function () {
            clearInterval(timer); timer = null; running = false;
            save({ minutes: st.minutes, remaining: remaining, running: false, done: done });
            render();
        });
        resetBtn.addEventListener('click', function () {
            clearInterval(timer); timer = null; running = false;
            done = 0; remaining = total;
            save({ minutes: st.minutes, remaining: remaining, running: false, done: 0 });
            overlay.hidden = true; render();
        });
        keepBtn.addEventListener('click', function () {
            overlay.hidden = true;
            save({ minutes: st.minutes, remaining: 0, running: false, done: 0 });
            done = 0; remaining = 0; render();
        });

        // Count graded cards across page reloads while the timer runs.
        document.querySelectorAll('form[action*="/grade"]').forEach(function (f) {
            f.addEventListener('submit', function () {
                var s = load();
                save({ minutes: s.minutes, remaining: s.remaining, running: s.running, deadline: s.deadline, done: (s.done || 0) + 1 });
            });
        });

        if (running) { timer = setInterval(tick, 500); tick(); }
        render();
    })();
    </script>
    @endpush
    <div class="lv-progress"><div style="width:{{ $sessionCount > 0 ? round(($reviewedCount / $sessionCount) * 100) : 0 }}%"></div></div>
    <p class="lv-progress-txt"><i class="bi bi-flag"></i>{{ __(':done of :total in this session · :due due total', ['done' => app_num($reviewedCount), 'total' => app_num($sessionCount), 'due' => app_num($totalDue)]) }}</p>
    @endif

    @if(!$current)
    <div class="lv-empty">
        <div class="lv-big">🎉</div>
        <h5>{{ __('All caught up!') }}</h5>
        <p>{{ __('No cards are due right now. Add new words or come back tomorrow.') }}</p>
        <a href="{{ route('vocabulary.cards.index') }}" class="lv-btn" style="background:linear-gradient(135deg,#4f46e5,#8b5cf6);color:#fff;">{{ __('Manage cards') }}</a>
    </div>
    @else
    <div class="lv-flash">
        <div class="lv-flash-ribbon"></div>
        <div class="lv-flash-top">
            <span class="lv-deckname">{{ $current->deck->name ?? '' }}</span>
            <span class="lv-boxpill">{{ __('Box :n', ['n' => $current->box]) }}<span class="lv-dots">@for($i = 1; $i <= 5; $i++)<i class="{{ $i <= $current->box ? 'on' : '' }}"></i>@endfor</span></span>
        </div>
        <div class="lv-prompt">{{ $prompt }}</div>
        <div class="lv-dir-hint">{{ $dir === 'back' ? __('What is the word for this meaning?') : __('What does this mean?') }}</div>

        <div class="lv-answer" id="vbAnswer">
            <hr>
            <div class="lv-ans">{{ $answer }}</div>
            @if($showExample)<div class="lv-ex"><i class="bi bi-quote"></i> {{ $current->example }}</div>@endif
            <div class="lv-grade">
                <form method="POST" action="{{ route('vocabulary.cards.grade', $current) }}">
                    @csrf
                    <input type="hidden" name="remembered" value="0">
                    <input type="hidden" name="deck_id" value="{{ $deckId }}">
                    <input type="hidden" name="dir" value="{{ $dir }}">
                    <button class="lv-forgot" id="vbForgot" type="submit" style="width:100%;"><span>❌</span> {{ __('I forgot') }} <span class="lv-kbd">1</span></button>
                </form>
                <form method="POST" action="{{ route('vocabulary.cards.grade', $current) }}">
                    @csrf
                    <input type="hidden" name="remembered" value="1">
                    <input type="hidden" name="deck_id" value="{{ $deckId }}">
                    <input type="hidden" name="dir" value="{{ $dir }}">
                    <button class="lv-remembered" id="vbRemembered" type="submit" style="width:100%;"><span>✅</span> {{ __('I remembered') }} <span class="lv-kbd">2</span></button>
                </form>
            </div>
        </div>

        <button id="vbShow" class="lv-show"><i class="bi bi-eye"></i>{{ __('Show meaning') }} <span class="lv-kbd" style="background:rgba(255,255,255,.25);">Space</span></button>
    </div>
    <p class="lv-tip">{{ __('Tip: answer honestly — forgot sends the card back to Box 1.') }}<br>
        <kbd>Space</kbd> {{ __('to reveal') }} · <kbd>1</kbd> {{ __('forgot') }} · <kbd>2</kbd> {{ __('remembered') }}</p>
    @endif

    @if(session('vocab_undo'))
    <div class="lv-undo" id="vbUndo" role="status">
        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
        <span class="lv-undo-txt">{{ __('Card graded.') }} <strong>{{ \Illuminate\Support\Str::limit(session('vocab_undo')['front'] ?? '', 24) }}</strong></span>
        <form method="POST" action="{{ route('vocabulary.reviews.undo') }}">
            @csrf
            <input type="hidden" name="review_id" value="{{ session('vocab_undo')['review_id'] }}">
            <input type="hidden" name="deck_id" value="{{ $deckId }}">
            <input type="hidden" name="dir" value="{{ $dir }}">
            <button type="submit" class="lv-undo-btn">{{ __('Undo') }}</button>
        </form>
        <span class="lv-undo-bar" aria-hidden="true"></span>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    function reveal() {
        var ans = document.getElementById('vbAnswer');
        var btn = document.getElementById('vbShow');
        if (!ans || !btn || ans.style.display === 'block') return;
        ans.style.display = 'block';
        btn.style.display = 'none';
    }
    function answersVisible() {
        var ans = document.getElementById('vbAnswer');
        return !!ans && ans.style.display === 'block';
    }
    document.getElementById('vbShow')?.addEventListener('click', reveal);
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, select, textarea')) return;
        if (e.code === 'Space') { e.preventDefault(); reveal(); return; }
        // Grading only works once the answer is on screen — a stray 1 or 2
        // must never mark a card the user has not seen yet.
        if (!answersVisible()) return;
        if (e.key === '1') { document.getElementById('vbForgot')?.click(); }
        else if (e.key === '2') { document.getElementById('vbRemembered')?.click(); }
    });

    // Undo bar fades away after its five seconds are up.
    var undo = document.getElementById('vbUndo');
    if (undo) {
        window.setTimeout(function () {
            undo.classList.add('is-out');
            window.setTimeout(function () { undo.remove(); }, 220);
        }, 5000);
    }
})();
</script>
@endpush
