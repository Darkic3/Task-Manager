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
.vb-timer { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:.55rem .8rem; margin-bottom:1rem; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
.vb-timer .form-control { width:auto; padding:.25rem .5rem; font-size:.8rem; }
.vb-timer-clock { font-variant-numeric:tabular-nums; font-weight:800; font-size:1rem; color:#111827; min-width:58px; text-align:center; }
.vb-timer-bar { flex:1; min-width:80px; height:6px; background:#eef2ff; border-radius:99px; overflow:hidden; }
.vb-timer-bar > div { height:100%; background:linear-gradient(90deg,#6366f1,#8b5cf6); width:0%; transition:width .5s linear; }
.vb-timer-btn { border:1px solid #e5e7eb; background:#fff; border-radius:7px; padding:.25rem .6rem; font-size:.78rem; font-weight:700; color:#4b5563; cursor:pointer; }
.vb-timer-btn:hover { border-color:#6366f1; color:#6366f1; }
.vb-timer-btn:disabled { opacity:.45; cursor:default; }
.vb-overlay { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:2000; padding:1rem; }
.vb-overlay-card { background:#fff; border-radius:14px; padding:2rem 1.6rem; text-align:center; max-width:360px; width:100%; box-shadow:0 24px 48px rgba(0,0,0,.25); }
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
    <div class="vb-timer" id="vbTimer" role="timer" aria-label="{{ __('Focus timer') }}">
        <i class="bi bi-stopwatch" style="color:#6366f1;"></i>
        <select id="vbTimerPreset" class="form-control" title="{{ __('Focus timer') }}">
            <option value="0">{{ __('Off') }}</option>
            <option value="5">5 {{ __('min') }}</option>
            <option value="10">10 {{ __('min') }}</option>
            <option value="15">15 {{ __('min') }}</option>
            <option value="25">25 {{ __('min') }}</option>
        </select>
        <span class="vb-timer-clock" id="vbTimerClock">--:--</span>
        <div class="vb-timer-bar"><div id="vbTimerFill"></div></div>
        <button type="button" class="vb-timer-btn" id="vbTimerStart">{{ __('Start') }}</button>
        <button type="button" class="vb-timer-btn" id="vbTimerPause" disabled>{{ __('Pause') }}</button>
        <button type="button" class="vb-timer-btn" id="vbTimerReset" disabled>{{ __('Reset') }}</button>
    </div>
    <div class="vb-overlay" id="vbTimerOverlay" hidden>
        <div class="vb-overlay-card">
            <div style="font-size:2.2rem;">⏰</div>
            <h5 style="color:#111827;font-weight:800;margin:.5rem 0;">{{ __("Time's up!") }}</h5>
            <p style="font-size:.85rem;color:#6b7280;" id="vbTimerDoneText"></p>
            <div style="display:flex;gap:.5rem;justify-content:center;margin-top:1rem;flex-wrap:wrap;">
                <button type="button" class="vb-btn" id="vbTimerKeep">{{ __('Keep going') }}</button>
                <a href="{{ route('vocabulary.index') }}" class="vb-btn primary">{{ __('Back to decks') }}</a>
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
