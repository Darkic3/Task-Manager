@php
    $isFa = app()->getLocale() === 'fa';
    $nowHour = (int) now()->format('H');
    $isNight = $nowHour < 6 || $nowHour >= 19;
    $brandDate = $isFa
        ? strtr(\Morilog\Jalali\Jalalian::fromCarbon(now())->format('l، d F Y'), ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹'])
        : now()->format('l, F j, Y');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isFa ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#4f46e5">
    <meta name="color-scheme" content="light">
    <title>{{ __('Sign In') }} — {{ __('Task Manager') }}</title>
    <link rel="shortcut icon" href="{{ asset('assets/img/logo-circle.png') }}" type="image/x-icon">
    <style>
        /* ── Fonts (self-hosted, variable) ─────────────────── */
        @font-face {
            font-family: 'Vazirmatn';
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
            src: url('{{ asset('assets/fonts/vazirmatn-arabic.woff2') }}') format('woff2');
            unicode-range: U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC;
        }
        @font-face {
            font-family: 'Vazirmatn';
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
            src: url('{{ asset('assets/fonts/vazirmatn-latin-ext.woff2') }}') format('woff2');
            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
        }
        @font-face {
            font-family: 'Vazirmatn';
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
            src: url('{{ asset('assets/fonts/vazirmatn-latin.woff2') }}') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
            src: url('{{ asset('assets/fonts/inter-latin-ext.woff2') }}') format('woff2');
            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
        }
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
            src: url('{{ asset('assets/fonts/inter-latin.woff2') }}') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }

        /* ── Tokens ────────────────────────────────────────── */
        :root {
            --brand-500: #6366f1;
            --brand-600: #4f46e5;
            --brand-700: #4338ca;
            --violet-500: #8b5cf6;

            --ink-900: #0f172a;
            --ink-700: #334155;
            --ink-500: #64748b;
            --ink-400: #94a3b8;
            --line: #e2e8f0;
            --line-soft: #eef2f7;

            --danger-600: #dc2626;
            --danger-700: #b91c1c;

            --radius-lg: 28px;
            --radius-md: 13px;
            --radius-sm: 9px;

            --shadow-card:
                0 1px 2px rgba(15, 23, 42, .05),
                0 10px 24px -8px rgba(15, 23, 42, .08),
                0 36px 70px -22px rgba(79, 70, 229, .32);
        }

        *, *::before, *::after { box-sizing: border-box; }

        html { -webkit-text-size-adjust: 100%; }

        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            padding: clamp(16px, 3.5vw, 40px);
            font-family: {{ $isFa ? "'Vazirmatn', 'Segoe UI', Tahoma, sans-serif" : "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" }};
            font-size: 15px;
            line-height: 1.6;
            color: var(--ink-700);
            background-color: #eef1fe;
            background-image:
                radial-gradient(900px 540px at 12% -10%, rgba(99, 102, 241, .20), transparent 60%),
                radial-gradient(820px 520px at 96% 112%, rgba(139, 92, 246, .18), transparent 58%),
                linear-gradient(180deg, #f6f7ff 0%, #eaeefd 100%);
            background-attachment: fixed;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }

        /* ── Persian typography polish ─────────────────────── */
        html[dir="rtl"] body {
            line-height: 1.9;
            letter-spacing: 0;
            word-spacing: .02em;
        }
        html[dir="rtl"] h1,
        html[dir="rtl"] h2,
        html[dir="rtl"] h3 {
            letter-spacing: 0;
            line-height: 1.75;
            font-weight: 800;
        }
        html[dir="rtl"] button,
        html[dir="rtl"] input,
        html[dir="rtl"] label { letter-spacing: 0; }
        html[dir="rtl"] .brand-name,
        html[dir="rtl"] .submit,
        html[dir="rtl"] .lang-btn { text-transform: none; }

        /* ── Shell ─────────────────────────────────────────── */
        .auth {
            width: min(1060px, 100%);
            margin: auto;
            display: flex;
            background: #fff;
            border: 1px solid rgba(226, 232, 240, .9);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-card);
            animation: auth-in .6s cubic-bezier(.22, .85, .3, 1) both;
        }
        @keyframes auth-in {
            from { opacity: 0; transform: translateY(20px) scale(.985); }
            to   { opacity: 1; transform: none; }
        }

        /* ── Brand panel ───────────────────────────────────── */
        .brand {
            position: relative;
            flex: 1.05;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 30px;
            padding: clamp(28px, 3.4vw, 46px);
            color: #fff;
            background:
                linear-gradient(155deg, var(--brand-700) 0%, var(--brand-600) 30%, var(--brand-500) 56%, var(--violet-500) 82%, #a78bfa 100%);
            overflow: hidden;
            isolation: isolate;
        }
        .orb {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, .10);
            pointer-events: none;
        }
        .orb-1 { width: 340px; height: 340px; top: -110px; inset-inline-end: -100px; animation: drift 16s ease-in-out infinite alternate; }
        .orb-2 { width: 230px; height: 230px; bottom: -80px; inset-inline-start: -70px; background: rgba(255, 255, 255, .08); animation: drift 20s ease-in-out infinite alternate-reverse; }
        .orb-3 { width: 130px; height: 130px; top: 44%; inset-inline-start: 12%; background: rgba(255, 255, 255, .07); animation: drift 13s ease-in-out infinite alternate; }
        @keyframes drift {
            from { transform: translate3d(0, 0, 0) scale(1); }
            to   { transform: translate3d(-22px, 26px, 0) scale(1.07); }
        }
        .brand-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, .08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .08) 1px, transparent 1px);
            background-size: 42px 42px;
            -webkit-mask-image: radial-gradient(120% 95% at 70% 0%, #000 10%, transparent 72%);
            mask-image: radial-gradient(120% 95% at 70% 0%, #000 10%, transparent 72%);
            pointer-events: none;
        }

        .brand-top {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 13px;
        }
        .brand-logo {
            width: 54px;
            height: 54px;
            flex: none;
            border-radius: 17px;
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .34);
            display: grid;
            place-items: center;
            overflow: hidden;
            box-shadow: 0 12px 26px rgba(30, 27, 75, .22);
        }
        .brand-logo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .brand-name {
            font-size: 1.08rem;
            font-weight: 800;
            letter-spacing: .1px;
            text-shadow: 0 1px 2px rgba(30, 27, 75, .18);
        }

        .brand-mid { position: relative; z-index: 1; }
        .brand-headline {
            margin: 0 0 12px;
            font-size: clamp(1.6rem, 2.4vw, 2.25rem);
            font-weight: 800;
            line-height: 1.35;
            text-wrap: balance;
            text-shadow: 0 2px 10px rgba(30, 27, 75, .16);
        }
        html[dir="rtl"] .brand-headline {
            font-size: clamp(1.42rem, 2.1vw, 2rem);
            line-height: 1.85;
        }
        .brand-sub {
            margin: 0 0 24px;
            max-width: 44ch;
            font-size: .94rem;
            color: rgba(255, 255, 255, .84);
        }
        html[dir="rtl"] .brand-sub { line-height: 2; }

        .brand-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 10px;
        }
        .brand-list li {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 11px 15px;
            border-radius: 15px;
            background: rgba(255, 255, 255, .11);
            border: 1px solid rgba(255, 255, 255, .18);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            font-size: .88rem;
            font-weight: 600;
            color: rgba(255, 255, 255, .95);
        }
        .brand-ico {
            width: 27px;
            height: 27px;
            flex: none;
            border-radius: 9px;
            background: rgba(255, 255, 255, .22);
            display: grid;
            place-items: center;
        }
        .brand-ico svg { width: 14px; height: 14px; }

        .brand-card {
            position: relative;
            z-index: 1;
            padding: 17px 19px;
            border-radius: 19px;
            background: rgba(255, 255, 255, .15);
            border: 1px solid rgba(255, 255, 255, .28);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 20px 40px rgba(30, 27, 75, .22);
        }
        .brand-date-top {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: .85rem;
            font-weight: 700;
            color: rgba(255, 255, 255, .92);
        }
        .brand-date-top svg { width: 17px; height: 17px; flex: none; }
        .brand-date-day {
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1.4;
            color: #fff;
            text-shadow: 0 2px 10px rgba(30, 27, 75, .18);
        }
        html[dir="rtl"] .brand-date-day {
            font-size: 1.42rem;
            line-height: 1.8;
            font-variant-numeric: tabular-nums;
        }
        .brand-card-meta {
            margin-top: 11px;
            font-size: .78rem;
            color: rgba(255, 255, 255, .8);
        }

        /* ── Form panel ────────────────────────────────────── */
        .panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: clamp(24px, 3vw, 42px);
            background:
                radial-gradient(520px 300px at 100% 0%, rgba(99, 102, 241, .07), transparent 62%),
                #fff;
        }
        .panel-bar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 10px;
        }
        .lang-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 15px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink-500);
            font-size: .8rem;
            font-weight: 700;
            font-family: inherit;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
            transition: color .18s ease, border-color .18s ease, background .18s ease, transform .18s ease;
        }
        .lang-btn svg { width: 15px; height: 15px; }
        .lang-btn:hover {
            color: var(--brand-600);
            border-color: #c7d2fe;
            background: #eef2ff;
            transform: translateY(-1px);
        }

        .panel-body {
            width: min(400px, 100%);
            margin-inline: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-block: 6px;
        }
        .panel-logo {
            display: none;
            align-items: center;
            gap: 11px;
            margin-bottom: 22px;
        }
        .panel-logo img {
            width: 44px;
            height: 44px;
            border-radius: 13px;
            display: block;
            box-shadow: 0 6px 16px rgba(79, 70, 229, .22);
        }
        .panel-logo span { font-weight: 800; font-size: 1.02rem; color: var(--ink-900); }

        .panel-title {
            margin: 0 0 7px;
            font-size: clamp(1.55rem, 2.1vw, 1.95rem);
            font-weight: 800;
            line-height: 1.35;
            color: var(--ink-900);
            text-wrap: balance;
        }
        html[dir="rtl"] .panel-title { line-height: 1.8; }
        .panel-sub {
            margin: 0 0 26px;
            font-size: .92rem;
            color: var(--ink-500);
        }

        /* Alert */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 15px;
            margin-bottom: 20px;
            border-radius: var(--radius-md);
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--danger-700);
            font-size: .83rem;
            font-weight: 600;
            animation: fade-in .35s ease both;
        }
        .alert svg { width: 17px; height: 17px; flex: none; margin-top: 2px; }
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: none; }
        }

        /* Fields */
        .field { margin-bottom: 17px; }
        .field-label {
            display: block;
            margin-bottom: 8px;
            font-size: .83rem;
            font-weight: 700;
            color: var(--ink-700);
        }
        .field-box { position: relative; }
        .field-ico {
            position: absolute;
            inset-inline-start: 15px;
            top: 50%;
            transform: translateY(-50%);
            display: grid;
            place-items: center;
            color: var(--ink-400);
            pointer-events: none;
            transition: color .18s ease;
        }
        .field-ico svg { width: 18px; height: 18px; }
        .field-box:focus-within .field-ico { color: var(--brand-500); }

        .field-input {
            width: 100%;
            height: 51px;
            padding: 0 47px;
            border: 1.5px solid var(--line);
            border-radius: var(--radius-md);
            background: #f8fafc;
            color: var(--ink-900);
            font-size: .95rem;
            font-family: inherit;
            direction: ltr;
            text-align: left;
            outline: none;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }
        html[dir="rtl"] .field-input { text-align: right; }
        .field-input::placeholder { color: var(--ink-400); opacity: 1; }
        .field-input:hover { border-color: #c7d2fe; }
        .field-input:focus {
            border-color: var(--brand-500);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, .15);
        }
        .field-input.is-invalid { border-color: #f87171; background: #fff; }
        .field-input.is-invalid:focus { box-shadow: 0 0 0 4px rgba(248, 113, 113, .18); }
        .field-input:-webkit-autofill,
        .field-input:-webkit-autofill:hover,
        .field-input:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--ink-900);
            caret-color: var(--ink-900);
            box-shadow: 0 0 0 1000px #fff inset;
            border-color: var(--brand-500);
        }

        .field-toggle {
            position: absolute;
            inset-inline-end: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 35px;
            height: 35px;
            display: grid;
            place-items: center;
            border: 0;
            border-radius: var(--radius-sm);
            background: transparent;
            color: var(--ink-400);
            cursor: pointer;
            transition: color .15s ease, background .15s ease;
        }
        .field-toggle:hover { color: var(--brand-600); background: #eef2ff; }
        .field-toggle svg { width: 18px; height: 18px; }

        .field-err {
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 7px 2px 0;
            font-size: .79rem;
            font-weight: 600;
            color: var(--danger-600);
        }
        .field-err svg { width: 14px; height: 14px; flex: none; }

        /* Remember row */
        .row-between {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 2px 0 22px;
        }
        .check {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            cursor: pointer;
            user-select: none;
        }
        .check input {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
            margin: 0;
        }
        .check-box {
            width: 19px;
            height: 19px;
            flex: none;
            border-radius: 6px;
            border: 1.5px solid #cbd5e1;
            background: #fff;
            display: grid;
            place-items: center;
            transition: background .16s ease, border-color .16s ease, box-shadow .16s ease;
        }
        .check-box svg {
            width: 12px;
            height: 12px;
            color: #fff;
            opacity: 0;
            transform: scale(.5);
            transition: opacity .16s ease, transform .16s ease;
        }
        .check input:checked + .check-box {
            background: linear-gradient(135deg, var(--brand-500), var(--violet-500));
            border-color: transparent;
            box-shadow: 0 4px 10px rgba(99, 102, 241, .38);
        }
        .check input:checked + .check-box svg { opacity: 1; transform: none; }
        .check input:focus-visible + .check-box {
            box-shadow: 0 0 0 4px rgba(99, 102, 241, .25);
        }
        .check-label {
            font-size: .86rem;
            font-weight: 600;
            color: var(--ink-500);
            transition: color .15s ease;
        }
        .check:hover .check-label { color: var(--ink-700); }

        /* Submit */
        .submit {
            position: relative;
            width: 100%;
            height: 53px;
            border: 0;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--brand-600) 0%, var(--brand-500) 48%, var(--violet-500) 100%);
            color: #fff;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            overflow: hidden;
            box-shadow: 0 12px 24px -8px rgba(79, 70, 229, .55);
            transition: transform .16s ease, box-shadow .16s ease, filter .16s ease;
        }
        .submit::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(115deg, transparent 32%, rgba(255, 255, 255, .32) 50%, transparent 68%);
            transform: translateX(-130%);
            transition: transform .65s ease;
        }
        html[dir="rtl"] .submit::after { transform: translateX(130%); }
        .submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 34px -10px rgba(79, 70, 229, .6);
        }
        .submit:hover::after { transform: translateX(130%); }
        html[dir="rtl"] .submit:hover::after { transform: translateX(-130%); }
        .submit:active { transform: translateY(0) scale(.99); }
        .submit:focus-visible {
            outline: 3px solid rgba(99, 102, 241, .45);
            outline-offset: 3px;
        }
        .submit:disabled { cursor: wait; filter: saturate(.92); opacity: .94; transform: none; }
        .submit-ico {
            display: grid;
            place-items: center;
        }
        .submit-ico svg { width: 18px; height: 18px; }
        html[dir="rtl"] .submit-ico { transform: scaleX(-1); }
        .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2.5px solid rgba(255, 255, 255, .38);
            border-top-color: #fff;
            animation: spin .7s linear infinite;
        }
        .submit.is-loading .spinner { display: block; }
        .submit.is-loading .submit-ico { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Footer */
        .panel-foot {
            margin-top: 20px;
            text-align: center;
            font-size: .78rem;
            color: var(--ink-400);
        }
        .panel-foot a {
            color: var(--brand-600);
            font-weight: 700;
            text-decoration: none;
        }
        .panel-foot a:hover { text-decoration: underline; }
        .panel-foot .heart {
            width: 12px;
            height: 12px;
            color: #ef4444;
            vertical-align: -1px;
        }

        /* ── Responsive ────────────────────────────────────── */
        @media (max-width: 900px) {
            .auth { flex-direction: column; border-radius: 24px; }
            .brand {
                flex: none;
                padding: 26px 24px;
                gap: 22px;
            }
            .brand-card { display: none; }
            .brand-sub { margin-bottom: 18px; }
        }
        @media (max-width: 560px) {
            body { padding: 0; background-attachment: scroll; }
            .auth {
                margin: 0;
                min-height: 100dvh;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }
            .brand {
                padding: 22px 20px;
                gap: 16px;
            }
            .brand-sub,
            .brand-list { display: none; }
            .brand-headline {
                margin: 0;
                font-size: 1.18rem;
            }
            html[dir="rtl"] .brand-headline { font-size: 1.12rem; line-height: 1.75; }
            .panel { padding: 22px 18px 18px; }
            .panel-logo { display: none; }
            .field-input { height: 53px; font-size: 16px; }
            .submit { height: 55px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>
<body>

<div class="auth">
    {{-- ── Brand / marketing panel ── --}}
    <aside class="brand">
        <div class="brand-grid" aria-hidden="true"></div>
        <span class="orb orb-1" aria-hidden="true"></span>
        <span class="orb orb-2" aria-hidden="true"></span>
        <span class="orb orb-3" aria-hidden="true"></span>

        <div class="brand-top">
            <div class="brand-logo">
                <img src="{{ asset('assets/img/logo-circle.png') }}" alt="{{ __('TaskManager') }}" width="54" height="54">
            </div>
            <div class="brand-name">{{ __('TaskManager') }}</div>
        </div>

        <div class="brand-mid">
            <h2 class="brand-headline">{{ __('Everything you need, in one place') }}</h2>
            <p class="brand-sub">{{ __('A calm, organized space for your projects, tasks, daily plan and reports.') }}</p>
            <ul class="brand-list">
                <li>
                    <span class="brand-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                    </span>
                    <span>{{ __('Smart daily planning') }}</span>
                </li>
                <li>
                    <span class="brand-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                    </span>
                    <span>{{ __('Track projects and tasks') }}</span>
                </li>
                <li>
                    <span class="brand-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                    </span>
                    <span>{{ __('Visual progress reports') }}</span>
                </li>
            </ul>
        </div>

        <div class="brand-card">
            <div class="brand-date-top">
                @if ($isNight)
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                @endif
                <span>{{ app_greeting() }}</span>
            </div>
            <div class="brand-date-day">{{ $brandDate }}</div>
            <div class="brand-card-meta">{{ __('Start your day with focus') }}</div>
        </div>
    </aside>

    {{-- ── Form panel ── --}}
    <main class="panel">
        <div class="panel-bar">
            <a class="lang-btn" href="{{ route('locale.switch', $isFa ? 'en' : 'fa') }}" aria-label="{{ __('Language') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18"/></svg>
                <span>{{ $isFa ? __('English') : __('Persian') }}</span>
            </a>
        </div>

        <div class="panel-body">
            <div class="panel-logo">
                <img src="{{ asset('assets/img/logo-circle.png') }}" alt="" width="44" height="44">
                <span>{{ __('TaskManager') }}</span>
            </div>

            <h1 class="panel-title">{{ __('Welcome back') }}</h1>
            <p class="panel-sub">{{ __('Sign in to your account to continue') }}</p>

            @if ($errors->has('email') && $errors->first('email') === __('auth.failed'))
                <div class="alert" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 2.7 17.2A2 2 0 0 0 4.4 20h15.2a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4.5"/><circle cx="12" cy="16.6" r="1" fill="currentColor" stroke="none"/></svg>
                    <span>{{ __('auth.failed') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" id="login-form">
                @csrf

                <div class="field">
                    <label class="field-label" for="email">{{ __('Email') }}</label>
                    <div class="field-box">
                        <span class="field-ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="19" height="15" rx="3"/><path d="m3.5 7.5 8.5 6 8.5-6"/></svg>
                        </span>
                        <input type="email" id="email" name="email"
                               class="field-input @error('email') is-invalid @enderror"
                               value="{{ old('email') }}"
                               placeholder="you@example.com"
                               required autofocus
                               autocomplete="email" inputmode="email"
                               autocapitalize="off" autocorrect="off" spellcheck="false">
                    </div>
                    @error('email')
                        @if ($message !== __('auth.failed'))
                            <p class="field-err" role="alert">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4.5"/><circle cx="12" cy="16" r="1" fill="currentColor" stroke="none"/></svg>
                                {{ $message }}
                            </p>
                        @endif
                    @enderror
                </div>

                <div class="field">
                    <label class="field-label" for="password">{{ __('Password') }}</label>
                    <div class="field-box">
                        <span class="field-ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15.5" r="1.4" fill="currentColor" stroke="none"/></svg>
                        </span>
                        <input type="password" id="password" name="password"
                               class="field-input @error('password') is-invalid @enderror"
                               placeholder="••••••••"
                               required autocomplete="current-password">
                        <button type="button" class="field-toggle" id="pw-toggle"
                                aria-label="{{ __('Show password') }}" aria-pressed="false">
                            <svg id="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12S6 5.8 12 5.8 21.5 12 21.5 12 18 18.2 12 18.2 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><path d="M4 4 20 20"/><path d="M10.6 6.2A10.4 10.4 0 0 1 12 6c6 0 9.5 6 9.5 6a17.6 17.6 0 0 1-2.7 3.5"/><path d="M6.3 8.3A16.6 16.6 0 0 0 2.5 12S6 18 12 18a10 10 0 0 0 3.7-.7"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="field-err" role="alert">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4.5"/><circle cx="12" cy="16" r="1" fill="currentColor" stroke="none"/></svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="row-between">
                    <label class="check" for="remember">
                        <input type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                        <span class="check-box" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                        </span>
                        <span class="check-label">{{ __('Remember me') }}</span>
                    </label>
                </div>

                <button type="submit" class="submit" id="submit-btn">
                    <span class="submit-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13"/><path d="m12 5 7 7-7 7"/></svg>
                    </span>
                    <span class="submit-label">{{ __('Sign In') }}</span>
                    <span class="spinner" aria-hidden="true"></span>
                </button>
            </form>
        </div>

        <footer class="panel-foot">
            {{ __('Developed with love by') }}
            <svg class="heart" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 20.6s-7.4-4.4-9.5-8.5C.7 8.5 2.5 5 6.1 5c2 0 3.3 1.1 3.9 2.1C10.6 6.1 11.9 5 13.9 5c3.6 0 5.4 3.5 3.6 7.1-2.1 4.1-5.5 8.5-5.5 8.5Z"/></svg>
            <a href="https://github.com/Darkic3" target="_blank" rel="noopener noreferrer">Ahora</a>
        </footer>
    </main>
</div>

<script>
    (function () {
        var pwInput = document.getElementById('password');
        var pwToggle = document.getElementById('pw-toggle');
        var iconEye = document.getElementById('icon-eye');
        var iconEyeOff = document.getElementById('icon-eye-off');
        var labelShow = @json(__('Show password'));
        var labelHide = @json(__('Hide password'));

        if (pwToggle && pwInput) {
            pwToggle.addEventListener('click', function () {
                var revealed = pwInput.type === 'password';
                pwInput.type = revealed ? 'text' : 'password';
                pwToggle.setAttribute('aria-pressed', revealed ? 'true' : 'false');
                pwToggle.setAttribute('aria-label', revealed ? labelHide : labelShow);
                if (iconEye && iconEyeOff) {
                    iconEye.style.display = revealed ? 'none' : '';
                    iconEyeOff.style.display = revealed ? '' : 'none';
                }
                pwInput.focus();
            });
        }

        var form = document.getElementById('login-form');
        var btn = document.getElementById('submit-btn');
        if (form && btn) {
            form.addEventListener('submit', function (event) {
                if (btn.classList.contains('is-loading')) {
                    event.preventDefault();
                    return;
                }
                btn.classList.add('is-loading');
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
            });
        }
    })();
</script>
</body>
</html>
