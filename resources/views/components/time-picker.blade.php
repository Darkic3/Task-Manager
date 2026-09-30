{{-- Shared Persian-friendly time picker.
    Emits "HH:MM" through a hidden input (data-tp-value).
    JS API: TimePicker.getMinutes(id) / TimePicker.setValue(id, 'HH:MM') / TimePicker.setNow(id)

    Props: id (required), inputId, name, value ("HH:MM", default now),
           presets (array of "HH:MM"), showNow, showAdjust, compact
--}}
@props([
    'id',
    'inputId' => null,
    'name' => null,
    'value' => null,
    'presets' => ['06:00', '06:30', '07:00', '07:30', '08:00'],
    'showNow' => true,
    'showAdjust' => true,
    'compact' => false,
])
@php
    $tpIsFa = app()->getLocale() === 'fa';
    $tpInitial = $value ?: now()->format('H:i');
    $tpFa = fn($s) => strtr((string) $s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
@endphp
@push('styles')
<style>
    .tp-display {
        display: flex; align-items: center; justify-content: center; gap: 12px;
        background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%);
        border: 1.5px solid #e0e7ff; border-radius: 14px; padding: 14px 12px;
    }
    .tp-col { display: flex; flex-direction: column; align-items: center; gap: 6px; }
    .tp-digits {
        font-size: 2.1rem; font-weight: 800; color: #1e1b4b; line-height: 1;
        min-width: 2ch; text-align: center; font-variant-numeric: tabular-nums;
    }
    .tp-sep { font-size: 1.7rem; font-weight: 800; color: #a5b4fc; line-height: 1; }
    .tp-cap { font-size: .68rem; font-weight: 700; color: #6366f1; }
    .tp-step {
        width: 34px; height: 34px; border-radius: 50%;
        border: 1.5px solid #c7d2fe; background: #fff; color: #4f46e5;
        font-size: 1.15rem; font-weight: 700; line-height: 1; cursor: pointer;
        display: grid; place-items: center; padding: 0;
        transition: background .15s ease, color .15s ease, transform .12s ease;
    }
    .tp-step:hover { background: #4f46e5; border-color: #4f46e5; color: #fff; }
    .tp-step:active { transform: scale(.93); }
    .tp-step:focus-visible { outline: 3px solid rgba(99, 102, 241, .4); outline-offset: 2px; }
    .tp-period {
        font-size: .78rem; font-weight: 800; color: #7c3aed;
        background: #ede9fe; padding: 4px 12px; border-radius: 999px; white-space: nowrap;
    }
    .tp-presets { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; justify-content: center; }
    .tp-chip {
        padding: 7px 14px; border-radius: 999px; border: 1.5px solid #e0e7ff;
        background: #fff; color: #475569; font-size: .82rem; font-weight: 700;
        cursor: pointer; font-variant-numeric: tabular-nums;
        transition: border-color .15s ease, color .15s ease, background .15s ease, transform .12s ease;
    }
    .tp-chip:hover { border-color: #6366f1; color: #4f46e5; background: #eef2ff; transform: translateY(-1px); }
    .tp-chip.is-active {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border-color: transparent; color: #fff;
        box-shadow: 0 4px 10px rgba(99, 102, 241, .35);
    }
    .tp-chip-now { border-style: dashed; }
    /* Compact variant (inline rows) */
    .tp-compact .tp-display { padding: 8px 10px; gap: 8px; border-radius: 12px; }
    .tp-compact .tp-digits { font-size: 1.25rem; }
    .tp-compact .tp-period { font-size: .7rem; padding: 3px 10px; }
    .tp-step-sm { width: 30px; height: 30px; font-size: 1rem; }
    .tp-compact .tp-chip { padding: 5px 12px; font-size: .76rem; }
    @media (prefers-reduced-motion: reduce) {
        .tp-step, .tp-chip { transition: none; }
    }
</style>
@endpush
<div class="tp{{ $compact ? ' tp-compact' : '' }}" id="{{ $id }}" data-tp data-tp-fa="{{ $tpIsFa ? '1' : '0' }}"
     data-tp-p-morning="{{ __('Morning') }}" data-tp-p-afternoon="{{ __('Afternoon') }}" data-tp-p-night="{{ __('Night') }}">
    <input type="hidden" @if($inputId)id="{{ $inputId }}"@endif @if($name)name="{{ $name }}"@endif value="{{ $tpInitial }}" data-tp-value>
    @if($compact)
        <div class="tp-display" dir="ltr" role="group" aria-label="{{ __('Adjust time') }}">
            <button type="button" class="tp-step tp-step-sm" data-tp-adjust="-15" title="{{ __('Adjust time') }}" aria-label="-15">−</button>
            <div class="tp-digits" data-tp-hm>{{ $tpIsFa ? $tpFa($tpInitial) : $tpInitial }}</div>
            <div class="tp-period" data-tp-period></div>
            <button type="button" class="tp-step tp-step-sm" data-tp-adjust="15" title="{{ __('Adjust time') }}" aria-label="+15">+</button>
            @if($showNow)
                <button type="button" class="tp-chip tp-chip-now" data-tp-preset="now">{{ __('Now') }}</button>
            @endif
        </div>
    @else
        <div class="tp-display" dir="ltr" role="group" aria-label="{{ __('Adjust time') }}">
            <div class="tp-col">
                <button type="button" class="tp-step" data-tp-step="h:1" aria-label="{{ __('Hour') }} +">+</button>
                <div class="tp-digits" data-tp-h></div>
                <div class="tp-cap">{{ __('Hour') }}</div>
                <button type="button" class="tp-step" data-tp-step="h:-1" aria-label="{{ __('Hour') }} −">−</button>
            </div>
            <div class="tp-sep" aria-hidden="true">:</div>
            <div class="tp-col">
                <button type="button" class="tp-step" data-tp-step="m:1" aria-label="{{ __('Minute') }} +">+</button>
                <div class="tp-digits" data-tp-m></div>
                <div class="tp-cap">{{ __('Minute') }}</div>
                <button type="button" class="tp-step" data-tp-step="m:-1" aria-label="{{ __('Minute') }} −">−</button>
            </div>
            <div class="tp-period" data-tp-period></div>
        </div>
        <div class="tp-presets">
            @if($showNow)
                <button type="button" class="tp-chip tp-chip-now" data-tp-preset="now">{{ __('Now') }}</button>
            @endif
            @foreach($presets as $preset)
                <button type="button" class="tp-chip" data-tp-preset="{{ $preset }}">{{ $tpIsFa ? $tpFa($preset) : $preset }}</button>
            @endforeach
            @if($showAdjust)
                <button type="button" class="tp-chip" data-tp-adjust="-15" title="{{ __('Adjust time') }}">−{{ $tpIsFa ? $tpFa('15') : '15' }}</button>
                <button type="button" class="tp-chip" data-tp-adjust="15" title="{{ __('Adjust time') }}">+{{ $tpIsFa ? $tpFa('15') : '15' }}</button>
            @endif
        </div>
    @endif
</div>
@push('scripts')
<script>
(function () {
    if (window.TimePicker) return;
    var FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
    function pad(n) { return String(n).padStart(2, '0'); }
    function faDigits(s, fa) {
        s = String(s);
        return fa ? s.replace(/[0-9]/g, function (d) { return FA_DIGITS[Number(d)]; }) : s;
    }
    function isFa(box) { return box.getAttribute('data-tp-fa') === '1'; }
    function periodWord(h, box) {
        if (!isFa(box)) return h < 12 ? 'AM' : 'PM';
        if (h >= 5 && h < 12) return box.getAttribute('data-tp-p-morning') || '';
        if (h >= 12 && h < 17) return box.getAttribute('data-tp-p-afternoon') || '';
        return box.getAttribute('data-tp-p-night') || '';
    }
    function parseHM(v) {
        if (!v) return null;
        var parts = String(v).split(':');
        var h = parseInt(parts[0], 10), m = parseInt(parts[1] || '0', 10);
        if (isNaN(h) || isNaN(m)) return null;
        return { h: ((h % 24) + 24) % 24, m: ((m % 60) + 60) % 60 };
    }
    function nowHM() {
        var n = new Date();
        return { h: n.getHours(), m: n.getMinutes() };
    }
    function render(box) {
        var fa = isFa(box);
        var val = parseHM(box.querySelector('[data-tp-value]').value) || nowHM();
        var hs = pad(val.h), ms = pad(val.m);
        var hEl = box.querySelector('[data-tp-h]');
        var mEl = box.querySelector('[data-tp-m]');
        var hmEl = box.querySelector('[data-tp-hm]');
        if (hEl) hEl.textContent = faDigits(hs, fa);
        if (mEl) mEl.textContent = faDigits(ms, fa);
        if (hmEl) hmEl.textContent = faDigits(hs + ':' + ms, fa);
        var pEl = box.querySelector('[data-tp-period]');
        if (pEl) pEl.textContent = periodWord(val.h, box);
        var cur = hs + ':' + ms;
        box.querySelectorAll('[data-tp-preset]').forEach(function (chip) {
            var p = chip.getAttribute('data-tp-preset');
            chip.classList.toggle('is-active', p !== 'now' && p === cur);
        });
    }
    function write(box, h, m) {
        h = ((h % 24) + 24) % 24;
        m = ((m % 60) + 60) % 60;
        box.querySelector('[data-tp-value]').value = pad(h) + ':' + pad(m);
        render(box);
    }
    function current(box) {
        return parseHM(box.querySelector('[data-tp-value]').value) || nowHM();
    }
    function api() {
        return {
            getValue: function (id) {
                var box = document.getElementById(id);
                if (!box) return null;
                var v = parseHM(box.querySelector('[data-tp-value]').value);
                return v ? pad(v.h) + ':' + pad(v.m) : null;
            },
            getMinutes: function (id) {
                var box = document.getElementById(id);
                if (!box) return null;
                var v = parseHM(box.querySelector('[data-tp-value]').value);
                return v ? v.h * 60 + v.m : null;
            },
            setValue: function (id, hm) {
                var box = document.getElementById(id);
                if (!box) return;
                var v = parseHM(hm) || nowHM();
                write(box, v.h, v.m);
            },
            setNow: function (id) {
                var box = document.getElementById(id);
                if (!box) return;
                var n = nowHM();
                write(box, n.h, n.m);
            },
            step: function (id, part, delta) {
                var box = document.getElementById(id);
                if (!box) return;
                var v = current(box);
                if (part === 'h') write(box, v.h + delta, v.m);
                else write(box, v.h, v.m + delta);
            },
            adjust: function (id, delta) {
                var box = document.getElementById(id);
                if (!box) return;
                var v = current(box);
                var total = (((v.h * 60 + v.m + delta) % 1440) + 1440) % 1440;
                write(box, Math.floor(total / 60), total % 60);
            }
        };
    }
    window.TimePicker = api();
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-tp-step],[data-tp-preset],[data-tp-adjust]');
        if (!btn) return;
        var box = btn.closest('[data-tp]');
        if (!box || !box.id) return;
        e.preventDefault();
        if (btn.hasAttribute('data-tp-step')) {
            var parts = btn.getAttribute('data-tp-step').split(':');
            window.TimePicker.step(box.id, parts[0], parseInt(parts[1], 10) || 0);
        } else if (btn.hasAttribute('data-tp-preset')) {
            var p = btn.getAttribute('data-tp-preset');
            if (p === 'now') window.TimePicker.setNow(box.id);
            else window.TimePicker.setValue(box.id, p);
        } else if (btn.hasAttribute('data-tp-adjust')) {
            window.TimePicker.adjust(box.id, parseInt(btn.getAttribute('data-tp-adjust'), 10) || 0);
        }
    });
    function initAll() {
        document.querySelectorAll('[data-tp]').forEach(render);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
</script>
@endpush
