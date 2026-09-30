{{-- Morning wake-up check-in: first thing after login. Rendered only when $morningCheckin is set. --}}
@if(!empty($morningCheckin))
@push('styles')
<style>
    #mcModal .pl-modal-dialog { width: min(430px, 96vw); }
    #mcModal:not([hidden]) .pl-modal-dialog { animation: mc-pop .35s cubic-bezier(.22, .9, .3, 1.15) both; }
    @keyframes mc-pop {
        from { opacity: 0; transform: translateY(16px) scale(.97); }
        to { opacity: 1; transform: none; }
    }
    .mc-hero {
        position: relative; overflow: hidden; text-align: center; color: #fff;
        padding: 26px 22px 20px;
        background: linear-gradient(150deg, #4338ca 0%, #6366f1 55%, #8b5cf6 100%);
    }
    .mc-orb { position: absolute; border-radius: 50%; background: rgba(255, 255, 255, .1); pointer-events: none; }
    .mc-orb-1 { width: 170px; height: 170px; top: -70px; inset-inline-end: -50px; }
    .mc-orb-2 { width: 110px; height: 110px; bottom: -50px; inset-inline-start: -30px; background: rgba(255, 255, 255, .07); }
    .mc-x {
        position: absolute; top: 10px; inset-inline-end: 10px; z-index: 1;
        width: 30px; height: 30px; border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, .35); background: rgba(255, 255, 255, .16);
        color: #fff; font-size: 17px; line-height: 1; cursor: pointer;
    }
    .mc-x:hover { background: rgba(255, 255, 255, .3); }
    .mc-hero-icon {
        position: relative; z-index: 1; width: 48px; height: 48px; margin: 0 auto 10px;
        border-radius: 15px; background: rgba(255, 255, 255, .18);
        border: 1px solid rgba(255, 255, 255, .35);
        display: grid; place-items: center;
    }
    .mc-hero-icon svg { width: 24px; height: 24px; }
    .mc-greet { position: relative; z-index: 1; font-size: .8rem; font-weight: 700; color: rgba(255, 255, 255, .85); }
    .mc-title { position: relative; z-index: 1; margin: 4px 0 6px; font-size: 1.25rem; font-weight: 800; line-height: 1.5; }
    html[dir="rtl"] .mc-title { line-height: 1.8; }
    .mc-date { position: relative; z-index: 1; font-size: .76rem; color: rgba(255, 255, 255, .78); }
    .mc-body { padding: 18px 20px 20px; }
    .mc-routine-chip {
        display: flex; align-items: center; justify-content: center; gap: 8px;
        font-size: .8rem; font-weight: 700; color: #4f46e5;
        background: #eef2ff; border: 1px solid #e0e7ff; border-radius: 999px;
        padding: 6px 14px; margin-bottom: 14px;
    }
    .mc-routine-chip .mc-routine-label { color: #7c8aa5; font-weight: 600; }
    .mc-submit {
        width: 100%; height: 50px; margin-top: 16px; border: 0; border-radius: 12px;
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 50%, #8b5cf6 100%);
        color: #fff; font-family: inherit; font-size: .95rem; font-weight: 700; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        box-shadow: 0 10px 22px -8px rgba(79, 70, 229, .55);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .mc-submit:hover { transform: translateY(-1px); box-shadow: 0 14px 28px -8px rgba(79, 70, 229, .6); }
    .mc-submit:disabled { cursor: wait; opacity: .85; transform: none; }
    .mc-submit svg { width: 18px; height: 18px; }
    .mc-alt { display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 12px; }
    .mc-link {
        border: 0; background: none; color: #94a3b8; font-family: inherit;
        font-size: .8rem; font-weight: 600; cursor: pointer; padding: 4px 8px;
    }
    .mc-link:hover { color: #4f46e5; }
    .mc-dot { color: #cbd5e1; font-size: .7rem; }
    .mc-success { text-align: center; }
    .mc-check {
        width: 58px; height: 58px; margin: 6px auto 12px; border-radius: 50%;
        background: linear-gradient(135deg, #10b981, #34d399); color: #fff;
        display: grid; place-items: center;
        box-shadow: 0 10px 24px -6px rgba(16, 185, 129, .5);
    }
    .mc-check svg { width: 28px; height: 28px; }
    .mc-success-title { font-size: 1.1rem; font-weight: 800; color: #0f172a; }
    .mc-streak { margin: 6px 0 0; font-size: .85rem; font-weight: 700; color: #d97706; min-height: 1.2em; }
    .mc-streak:empty { display: none; }
    .mc-btn-ghost {
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        margin-top: 14px; padding: 9px 18px; border-radius: 10px;
        border: 1.5px solid #e0e7ff; background: #fff; color: #4f46e5;
        font-size: .83rem; font-weight: 700; text-decoration: none;
    }
    .mc-btn-ghost:hover { background: #eef2ff; }
    #mcSuccess .mc-submit { margin-top: 10px; }
    @media (prefers-reduced-motion: reduce) {
        #mcModal:not([hidden]) .pl-modal-dialog { animation: none; }
        .mc-submit, .mc-link { transition: none; }
    }
</style>
@endpush
<div class="pl-modal" id="mcModal" hidden>
    <div class="pl-modal-backdrop" data-mc-snooze></div>
    <div class="pl-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="mcTitle">
        <div class="mc-hero">
            <span class="mc-orb mc-orb-1" aria-hidden="true"></span>
            <span class="mc-orb mc-orb-2" aria-hidden="true"></span>
            <button type="button" class="mc-x" data-mc-snooze aria-label="{{ __('Close') }}">&times;</button>
            <div class="mc-hero-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4"/><path d="m5.7 9.7 2.1 2.1"/><path d="m18.3 9.7-2.1 2.1"/><path d="M8 18a4 4 0 0 1 8 0"/><path d="M3 18h18"/><path d="M3 21h18"/></svg>
            </div>
            <div class="mc-greet">{{ app_greeting() }}</div>
            <h3 class="mc-title" id="mcTitle">{{ __('What time did you wake up today?') }}</h3>
            <div class="mc-date">{{ app_human_date() }}</div>
        </div>
        <div class="mc-body" id="mcForm">
            <div class="mc-routine-chip">
                <i class="bi bi-alarm"></i>
                <span>{{ $morningCheckin['routine_title'] }}</span>
                <span class="mc-routine-label">{{ __($morningCheckin['value_label']) }}</span>
            </div>
            <x-time-picker id="mcTime" :value="$morningCheckin['default']" />
            <button type="button" class="mc-submit" id="mcSubmit">
                <i class="bi bi-check2-circle"></i>
                <span>{{ __('Log Wake-up') }}</span>
            </button>
            <div class="mc-alt">
                <button type="button" class="mc-link" data-mc-snooze>{{ __('Later') }}</button>
                <span class="mc-dot" aria-hidden="true">•</span>
                <button type="button" class="mc-link" data-mc-dismiss>{{ __('Not today') }}</button>
            </div>
        </div>
        <div class="mc-body mc-success" id="mcSuccess" hidden>
            <div class="mc-check" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
            </div>
            <div class="mc-success-title">{{ __('Wake-up logged!') }}</div>
            <p class="mc-streak" id="mcStreak"></p>
            <div>
                <a href="{{ route('track.index') }}" class="mc-btn-ghost">
                    <i class="bi bi-graph-up"></i> {{ __('View progress') }}
                </a>
            </div>
            <button type="button" class="mc-submit" data-mc-close>{{ __('Great, let\'s go') }}</button>
        </div>
    </div>
</div>
@push('scripts')
<script>
(function () {
    var modal = document.getElementById('mcModal');
    if (!modal || modal.dataset.mcInit) return;
    modal.dataset.mcInit = '1';
    var logUrl = @json($morningCheckin['log_url']);
    var logDate = @json($morningCheckin['date']);
    var dismissUrl = @json(route('morning-checkin.dismiss'));
    var streakWord = @json(__('day streak'));
    var loggedMsg = @json(__('Wake-up logged!'));
    var errorMsg = @json(__('Could not save your wake-up time.'));
    var mcIsFa = @json(app()->getLocale() === 'fa');
    var submit = document.getElementById('mcSubmit');
    var form = document.getElementById('mcForm');
    var success = document.getElementById('mcSuccess');

    function open() { modal.hidden = false; document.body.classList.add('pl-modal-open'); }
    function close() { modal.hidden = true; document.body.classList.remove('pl-modal-open'); }
    function postDismiss(mode) {
        try {
            plFetch(dismissUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ mode: mode })
            });
        } catch (e) { /* ignore network errors */ }
    }
    modal.querySelectorAll('[data-mc-snooze]').forEach(function (b) {
        b.addEventListener('click', function () { postDismiss('snooze'); close(); });
    });
    modal.querySelectorAll('[data-mc-dismiss]').forEach(function (b) {
        b.addEventListener('click', function () { postDismiss('today'); close(); });
    });
    modal.querySelectorAll('[data-mc-close]').forEach(function (b) {
        b.addEventListener('click', close);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) { postDismiss('snooze'); close(); }
    });
    if (submit) {
        submit.addEventListener('click', async function () {
            if (!window.TimePicker) return;
            var minutes = window.TimePicker.getMinutes('mcTime');
            if (minutes === null) return;
            submit.disabled = true;
            try {
                var res = await plFetch(logUrl + '?date=' + encodeURIComponent(logDate), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ value: minutes })
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                var json = await res.json();
                form.hidden = true;
                success.hidden = false;
                var streakEl = document.getElementById('mcStreak');
                if (streakEl) {
                    if (json.streak != null && json.streak > 1) {
                        var s = String(json.streak);
                        if (mcIsFa) s = s.replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[Number(d)]; });
                        streakEl.textContent = '🔥 ' + s + ' ' + streakWord;
                    } else {
                        streakEl.textContent = '';
                    }
                }
                if (typeof plShowToast === 'function') plShowToast(loggedMsg);
                if (typeof maybeCelebrate === 'function') maybeCelebrate();
            } catch (err) {
                submit.disabled = false;
                if (typeof plShowToast === 'function') plShowToast(errorMsg);
            }
        });
    }
    setTimeout(open, 600);
})();
</script>
@endpush
@endif
