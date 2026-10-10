<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title> @yield('title') | {{ __('TaskManager') }} </title>
    <link rel="shortcut icon" href="{{ asset('assets/img/logo-circle.png') }}" type="image/x-icon">
    @if(app()->getLocale() === 'fa')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    @endif
    <style>
        /* ── Self-hosted variable fonts (no external requests) ── */
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
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
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
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @if(app()->getLocale() === 'fa')
        {{-- Jalali date picker (assets copied from shopora) — fa locale only --}}
        <link rel="stylesheet" href="{{ asset('assets/jalali/jalalidatepicker.min.css') }}">
    @endif

    @stack('styles')

    <style>
        :root {
            --primary-50: #f0f4ff;
            --primary-100: #e0edff;
            --primary-500: #6366f1;
            --primary-600: #4f46e5;
            --primary-700: #4338ca;
            --primary-900: #312e81;

            --gray-25: #fcfcfd;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;

            --success-500: #10b981;
            --success-600: #059669;
            --warning-500: #f59e0b;
            --warning-600: #d97706;
            --error-500: #ef4444;
            --error-600: #dc2626;

            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);

            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
        }

        /* ── Custom SweetAlert2 Theme ── */
        .tm-swal-popup {
            border-radius: 16px !important;
            padding: 1.5rem !important;
            font-family: inherit !important;
            background: #ffffff !important;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
            border: 1px solid var(--gray-200, #e2e8f0) !important;
        }
        .tm-swal-title {
            font-size: 1.15rem !important;
            font-weight: 700 !important;
            color: var(--gray-900, #0f172a) !important;
            margin-bottom: 0.5rem !important;
        }
        .tm-swal-html {
            font-size: 0.925rem !important;
            color: var(--gray-600, #475569) !important;
            margin-top: 0.5rem !important;
        }
        .tm-swal-actions {
            gap: 0.75rem !important;
            margin-top: 1.25rem !important;
        }
        .tm-swal-btn {
            border-radius: 10px !important;
            padding: 0.5rem 1.25rem !important;
            font-size: 0.875rem !important;
            font-weight: 600 !important;
            transition: all 0.2s ease !important;
            cursor: pointer !important;
            outline: none !important;
            border: none !important;
        }
        .tm-swal-confirm-btn {
            background-color: var(--primary-600, #4f46e5) !important;
            color: #ffffff !important;
        }
        .tm-swal-confirm-btn:hover {
            background-color: var(--primary-700, #4338ca) !important;
            transform: translateY(-1px);
        }
        .tm-swal-confirm-btn.danger {
            background-color: var(--error-600, #dc2626) !important;
        }
        .tm-swal-confirm-btn.danger:hover {
            background-color: #b91c1c !important;
        }
        .tm-swal-cancel-btn {
            background-color: var(--gray-100, #f1f5f9) !important;
            color: var(--gray-700, #334155) !important;
        }
        .tm-swal-cancel-btn:hover {
            background-color: var(--gray-200, #e2e8f0) !important;
            color: var(--gray-900, #0f172a) !important;
        }
        .tm-swal-input {
            border-radius: 10px !important;
            border: 1px solid var(--gray-200, #e2e8f0) !important;
            padding: 0.5rem 0.75rem !important;
            font-size: 0.9rem !important;
            color: var(--gray-900, #0f172a) !important;
        }
        .tm-swal-input:focus {
            border-color: var(--primary-500, #6366f1) !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18) !important;
        }
        .tm-swal-popup .swal2-icon.swal2-warning {
            border-color: #f59e0b !important;
            color: #f59e0b !important;
        }
        .tm-swal-popup .swal2-icon.swal2-error {
            border-color: #ef4444 !important;
            color: #ef4444 !important;
        }
        .tm-swal-popup .swal2-icon.swal2-success {
            border-color: #10b981 !important;
            color: #10b981 !important;
        }
        .tm-swal-popup .swal2-icon.swal2-info {
            border-color: #6366f1 !important;
            color: #6366f1 !important;
        }

        * {
            box-sizing: border-box;
        }

        body {
            display: flex;
            height: 100vh;
            margin: 0;
            overflow: hidden;
            background-color: var(--gray-25);
            font-family: {{ app()->getLocale() === 'fa' ? "'Vazirmatn', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" : "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif" }};
            font-size: 14px;
            line-height: {{ app()->getLocale() === 'fa' ? '1.6' : '1.5' }};
            color: var(--gray-700);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ── Global RTL & Persian Typography Polish ── */
        [dir="rtl"] {
            letter-spacing: normal !important;
        }
        [dir="rtl"] *, [dir="rtl"] .text-uppercase, [dir="rtl"] .badge, [dir="rtl"] button {
            letter-spacing: normal !important;
            text-transform: none !important;
        }
        [dir="rtl"] h1, [dir="rtl"] h2, [dir="rtl"] h3, [dir="rtl"] h4, [dir="rtl"] h5, [dir="rtl"] h6 {
            font-weight: 700;
            line-height: 1.45;
        }
        [dir="rtl"] .badge {
            font-weight: 600;
            padding: 0.35em 0.75em;
        }
        [dir="rtl"] .btn {
            font-weight: 600;
        }

        .btn {
            padding: 0.35rem 0.8rem;
            font-size: 0.8125rem;
            font-weight: 500;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary-600);
            color: white;
            border-color: var(--primary-600);
        }

        .btn-primary:hover {
            background-color: var(--primary-700);
            border-color: var(--primary-700);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background-color: var(--success-600);
            color: white;
            border-color: var(--success-600);
        }

        .btn-success:hover {
            background-color: var(--success-600);
            border-color: var(--success-600);
            color: white;
        }

        .btn-warning {
            background-color: var(--warning-500);
            color: white;
            border-color: var(--warning-500);
        }

        .btn-warning:hover {
            background-color: var(--warning-600);
            border-color: var(--warning-600);
            color: white;
        }

        .btn-danger {
            background-color: var(--error-500);
            color: white;
            border-color: var(--error-500);
        }

        .btn-danger:hover {
            background-color: var(--error-600);
            border-color: var(--error-600);
            color: white;
        }

        .btn-outline {
            background-color: white;
            color: var(--gray-700);
            border-color: var(--gray-200);
        }

        .btn-outline:hover {
            background-color: var(--gray-50);
            border-color: var(--gray-300);
            color: var(--gray-800);
        }

        .sidebar {
            width: 274px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcff 55%, #f6f7ff 100%);
            color: var(--gray-700);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            border-inline-end: 1px solid var(--gray-200);
            box-shadow: 1px 0 0 rgba(15, 23, 42, .02), 10px 0 34px -26px rgba(15, 23, 42, .45);
            position: relative;
            z-index: 30;
        }

        .sidebar-header {
            padding: 16px 14px 12px;
            position: relative;
        }

        .sidebar-header::after {
            content: '';
            position: absolute;
            inset: auto 14px 0 14px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gray-200) 20%, var(--gray-200) 80%, transparent);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 17px;
            text-decoration: none;
            color: var(--gray-900);
            background: linear-gradient(135deg, rgba(99, 102, 241, .10), rgba(139, 92, 246, .10));
            border: 1px solid rgba(99, 102, 241, .14);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .sidebar-brand:hover {
            transform: translateY(-1px);
            border-color: rgba(99, 102, 241, .28);
            box-shadow: 0 14px 26px -16px rgba(79, 70, 229, .65);
        }

        .sidebar-brand img {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: 0 8px 16px -8px rgba(30, 27, 75, .5);
        }

        .sidebar-brand-txt { min-width: 0; }

        .sidebar-brand-name {
            display: block;
            font-size: .95rem;
            font-weight: 800;
            color: var(--gray-900);
            line-height: 1.35;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sidebar-brand-sub {
            display: block;
            font-size: .68rem;
            font-weight: 600;
            color: var(--gray-500);
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: 14px 12px 18px;
            scrollbar-width: thin;
            scrollbar-color: #dfe3ec transparent;
        }

        .sidebar-nav::-webkit-scrollbar { width: 6px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: #dfe3ec; border-radius: 99px; }
        .sidebar-nav::-webkit-scrollbar-thumb:hover { background: #cbd2df; }

        .nav-section + .nav-section { margin-top: 20px; }

        .nav-section-title {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 0 10px 9px;
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .09em;
            color: #98a2b3;
        }

        .nav-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, var(--gray-200), transparent);
        }

        .nav-item {
            margin-bottom: 3px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 8px 11px;
            color: var(--gray-600);
            text-decoration: none;
            border-radius: 13px;
            font-size: .875rem;
            font-weight: 600;
            transition: background-color .16s ease, color .16s ease, box-shadow .16s ease, transform .16s ease;
            position: relative;
        }

        .nav-link > span:not(.nav-badge) {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .nav-link:hover {
            background-color: #f2f5fc;
            color: var(--gray-900);
        }

        .nav-link:active { transform: scale(.99); }

        .nav-link.active {
            background: linear-gradient(135deg, #eef2ff 0%, #f6f3ff 100%);
            color: var(--primary-700);
            font-weight: 700;
            box-shadow: inset 0 0 0 1px #e2e8ff;
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            inset-inline-start: -12px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 22px;
            background: linear-gradient(180deg, var(--primary-500), #8b5cf6);
            border-radius: 99px;
            box-shadow: 0 2px 8px rgba(99, 102, 241, .55);
        }

        .nav-link i {
            width: 30px;
            height: 30px;
            flex: none;
            border-radius: 9px;
            display: grid;
            place-items: center;
            font-size: .95rem;
            background: #f1f4f9;
            color: #64748b;
            transition: background-color .16s ease, color .16s ease, transform .18s ease, box-shadow .18s ease;
        }

        .nav-link:hover i {
            background: #e6ebfa;
            color: var(--primary-600);
            transform: translateY(-1px);
        }

        .nav-link.active i {
            background: linear-gradient(135deg, var(--primary-500), #8b5cf6);
            color: #fff;
            box-shadow: 0 8px 16px -8px rgba(79, 70, 229, .75);
        }

        .nav-badge {
            flex: none;
            min-width: 22px;
            padding: 2px 7px;
            background-color: #eef1f7;
            color: var(--gray-600);
            font-size: .7rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            border-radius: 999px;
            text-align: center;
        }

        .nav-link:hover .nav-badge { background-color: #e2e8f5; }

        .nav-link.active .nav-badge {
            background: #fff;
            color: var(--primary-700);
            box-shadow: 0 2px 8px -2px rgba(79, 70, 229, .35);
        }

        .sidebar-footer {
            padding: 12px;
            border-top: 1px solid var(--gray-200);
            background: rgba(255, 255, 255, .72);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 9px 10px;
            border-radius: 15px;
            border: 1px solid var(--gray-200);
            background: #fff;
            box-shadow: var(--shadow-sm);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            cursor: pointer;
        }

        .user-profile:hover {
            transform: translateY(-1px);
            border-color: #c7d2fe;
            box-shadow: 0 14px 26px -18px rgba(79, 70, 229, .7);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            flex: none;
            background: linear-gradient(135deg, var(--primary-500), #8b5cf6);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: .9rem;
            box-shadow: 0 8px 16px -10px rgba(79, 70, 229, .8);
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-weight: 700;
            font-size: .82rem;
            color: var(--gray-900);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-email {
            font-size: .7rem;
            color: var(--gray-500);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            direction: ltr;
            text-align: start;
        }

        .user-profile > i {
            flex: none;
            font-size: .95rem;
            color: var(--gray-400);
            transition: color .16s ease;
        }

        .user-profile:hover > i { color: var(--primary-600); }

        .content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background-color: var(--gray-25);
        }

        .topnav {
            position: sticky;
            top: 0;
            z-index: 20;
            flex-shrink: 0;
            background: rgba(255, 255, 255, .82);
            backdrop-filter: blur(14px) saturate(170%);
            -webkit-backdrop-filter: blur(14px) saturate(170%);
            border-bottom: 1px solid transparent;
            box-shadow: 0 0 0 rgba(15, 23, 42, 0);
            padding: 0.6rem 1.1rem;
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .topnav.is-stuck {
            border-bottom-color: var(--gray-200);
            box-shadow: 0 12px 26px -22px rgba(15, 23, 42, .55);
        }

        .topnav-container {
            display: flex;
            align-items: center;
            gap: 12px;
            max-width: 1560px;
            margin: 0 auto;
        }

        .topnav-id {
            display: flex;
            align-items: center;
            gap: 11px;
            min-width: 0;
        }

        .topnav-ico {
            width: 34px;
            height: 34px;
            flex: none;
            border-radius: 11px;
            display: grid;
            place-items: center;
            font-size: 1rem;
            background: linear-gradient(135deg, var(--primary-600), #8b5cf6);
            color: #fff;
            box-shadow: 0 10px 18px -10px rgba(79, 70, 229, .8);
        }

        .topnav-txt { min-width: 0; }

        .topnav-eyebrow {
            display: block;
            font-size: .66rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .09em;
            color: #98a2b3;
            line-height: 1.4;
        }

        .page-title {
            display: block;
            font-size: 1.02rem;
            font-weight: 800;
            color: var(--gray-900);
            line-height: 1.4;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .topnav-actions {
            margin-inline-start: auto;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: none;
        }

        .topnav-clock {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 6px 13px;
            border-radius: 999px;
            background: #f6f7fb;
            border: 1px solid var(--gray-200);
            color: var(--gray-700);
            font-size: .78rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .topnav-clock i { font-size: .9rem; color: var(--primary-600); }
        .topnav-clock-date { color: var(--gray-500); font-weight: 600; }
        .topnav-clock-time { font-variant-numeric: tabular-nums; color: var(--gray-900); }

        .topnav-clock .pulse {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10b981;
            flex: none;
            animation: topnav-pulse 2.2s ease-out infinite;
        }

        @keyframes topnav-pulse {
            0%   { box-shadow: 0 0 0 0 rgba(16, 185, 129, .55); }
            70%  { box-shadow: 0 0 0 7px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .btn-brand {
            background: linear-gradient(135deg, var(--primary-600), #8b5cf6);
            color: #fff;
            border: 1px solid transparent;
            box-shadow: 0 12px 22px -12px rgba(79, 70, 229, .85);
        }

        .btn-brand:hover,
        .btn-brand:focus {
            background: linear-gradient(135deg, var(--primary-700), #7c3aed);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 16px 26px -12px rgba(79, 70, 229, .9);
        }

        .btn-brand .dropdown-toggle::after { opacity: .75; }

        .topnav-sep {
            width: 1px;
            height: 26px;
            background: var(--gray-200);
            flex: none;
        }

        .dropdown-menu {
            border: 1px solid var(--gray-200);
            border-radius: 15px;
            box-shadow: 0 24px 48px -20px rgba(15, 23, 42, .35);
            padding: 6px;
            min-width: 190px;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: .5rem .65rem;
            border-radius: 10px;
            font-size: .84rem;
            font-weight: 600;
            color: var(--gray-700);
            transition: background-color .15s ease, color .15s ease;
        }

        .dropdown-item:hover,
        .dropdown-item:focus {
            background-color: var(--gray-100);
            color: var(--gray-900);
        }

        .dropdown-item.active {
            background-color: var(--primary-50);
            color: var(--primary-700);
        }

        .dropdown-divider { border-color: var(--gray-200); margin: 5px 2px; }

        main {
            flex-grow: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        .card {
            background-color: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            transition: box-shadow 0.15s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-body {
            padding: 1rem;
        }

        .card-header {
            padding: 0.625rem 1rem;
            border-bottom: 1px solid var(--gray-200);
            background-color: var(--gray-50);
            font-weight: 600;
            color: var(--gray-900);
        }

        footer {
            background-color: white;
            border-top: 1px solid var(--gray-200);
            flex-shrink: 0;
            padding: 0.5rem 1.25rem;
            text-align: center;
        }

        .footer-text {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .footer-text a {
            color: var(--primary-600);
            text-decoration: none;
        }

        .footer-text a:hover {
            color: var(--primary-700);
        }

        /* Form Controls */
        .form-control {
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            padding: 0.4375rem 0.75rem;
            font-size: 0.875rem;
            transition: all 0.15s ease;
        }

        .form-control:focus {
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgb(99 102 241 / 0.1);
            outline: none;
        }

        .form-label {
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        /* Alert Styles */
        .alert {
            border-radius: var(--radius-md);
            border: 1px solid;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .alert-success {
            background-color: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }

        .alert-danger {
            background-color: #fef2f2;
            border-color: #fecaca;
            color: #dc2626;
        }

        .alert-warning {
            background-color: #fffbeb;
            border-color: #fed7aa;
            color: #d97706;
        }

        /* ── Hamburger button (mobile only) ── */
        .sidebar-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 38px; height: 38px;
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            color: var(--gray-600);
            font-size: 1.15rem;
            cursor: pointer;
            flex-shrink: 0;
            box-shadow: var(--shadow-sm);
            transition: background-color .16s ease, color .16s ease, transform .16s ease, box-shadow .16s ease;
        }
        .sidebar-toggle:hover {
            background: var(--primary-50);
            border-color: var(--primary-100);
            color: var(--primary-600);
            transform: translateY(-1px);
            box-shadow: 0 10px 20px -14px rgba(79, 70, 229, .8);
        }

        /* ── Sidebar overlay (mobile backdrop) ── */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .5);
            z-index: 999;
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            animation: sidebar-fade .22s ease both;
        }
        .sidebar-overlay.active { display: block; }

        @keyframes sidebar-fade {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        /* ── RTL Overrides & Typography ── */
        html[dir="rtl"] .nav-section-title,
        html[dir="rtl"] .topnav-eyebrow {
            letter-spacing: normal;
        }

        html[dir="rtl"] .nav-link.active::before {
            border-radius: 99px;
        }

        html[dir="rtl"] .me-1 { margin-left: 0.25rem !important; margin-right: 0 !important; }
        html[dir="rtl"] .me-2 { margin-left: 0.5rem !important; margin-right: 0 !important; }
        html[dir="rtl"] .me-3 { margin-left: 1rem !important; margin-right: 0 !important; }
        html[dir="rtl"] .ms-1 { margin-right: 0.25rem !important; margin-left: 0 !important; }
        html[dir="rtl"] .ms-2 { margin-right: 0.5rem !important; margin-left: 0 !important; }
        html[dir="rtl"] .ms-3 { margin-right: 1rem !important; margin-left: 0 !important; }

        /* RTL Directional Icon Mirroring */
        html[dir="rtl"] .bi-arrow-right:not(.no-rtl),
        html[dir="rtl"] .bi-arrow-left:not(.no-rtl),
        html[dir="rtl"] .bi-chevron-right:not(.no-rtl),
        html[dir="rtl"] .bi-chevron-left:not(.no-rtl),
        html[dir="rtl"] .bi-arrow-right-short:not(.no-rtl),
        html[dir="rtl"] .bi-arrow-left-short:not(.no-rtl),
        html[dir="rtl"] .bi-arrow-right-circle:not(.no-rtl),
        html[dir="rtl"] .bi-arrow-left-circle:not(.no-rtl),
        html[dir="rtl"] .bi-box-arrow-right:not(.no-rtl),
        html[dir="rtl"] .bi-box-arrow-left:not(.no-rtl),
        html[dir="rtl"] .bi-caret-right-fill:not(.no-rtl),
        html[dir="rtl"] .bi-caret-left-fill:not(.no-rtl),
        html[dir="rtl"] .bi-chevron-double-right:not(.no-rtl),
        html[dir="rtl"] .bi-chevron-double-left:not(.no-rtl) {
            transform: scaleX(-1);
            display: inline-block;
        }

        /* ── Responsive Design ── */
        @media (max-width: 1100px) {
            .topnav-clock-date { display: none; }
        }

        @media (max-width: 900px) {
            .sidebar { width: 250px; }
        }

        @media (max-width: 768px) {
            .sidebar-toggle { display: flex; }

            .sidebar {
                position: fixed;
                inset-inline-start: -290px;
                top: 0;
                height: 100vh;
                width: 282px;
                z-index: 1000;
                box-shadow: none;
                transition: inset-inline-start .3s cubic-bezier(.4, 0, .2, 1);
            }

            .sidebar.open {
                inset-inline-start: 0;
                box-shadow: 0 0 60px rgba(15, 23, 42, .28);
            }

            .content { margin-left: 0; margin-right: 0; }

            main { padding: 0.75rem; }

            .topnav { padding: 0.55rem 0.75rem; }

            .topnav-eyebrow { display: none; }

            .page-title { font-size: .95rem; }

            .topnav-clock { padding: 6px 10px; }

            .topnav-sep { display: none; }
        }

        @media (max-width: 420px) {
            .topnav-clock { display: none; }
        }
    </style>
</head>

<body>
@php
    $navUser = auth()->user();
    $navUser->loadCount([
        'projects',
        'tasks as open_tasks_count' => fn ($query) => $query
            ->where('status', '!=', 'completed')
            ->where(fn ($q) => $q->whereHas('project', fn ($p) => $p->where('status', '!=', 'completed'))->orWhereNull('project_id')),
    ]);

    $navSections = [
        [
            'title' => __('Main'),
            'items' => [
                ['match' => '/', 'icon' => 'bi-house-door-fill', 'label' => __('Dashboard'), 'url' => route('dashboard')],
                ['match' => 'projects*', 'icon' => 'bi-folder-fill', 'label' => __('Projects'), 'url' => route('projects.index'), 'badge' => $navUser->projects_count],
                ['match' => 'tasks*', 'icon' => 'bi-check2-square', 'label' => __('Tasks'), 'url' => route('tasks.index'), 'badge' => $navUser->open_tasks_count],
                ['match' => 'planner*', 'icon' => 'bi-sun-fill', 'label' => __('My Day'), 'url' => route('planner.index')],
            ],
        ],
        [
            'title' => __('Organize'),
            'items' => [
                ['match' => 'routines*', 'icon' => 'bi-arrow-repeat', 'label' => __('Routines'), 'url' => route('routines.index')],
                ['match' => 'workouts*', 'icon' => 'bi-heart-pulse-fill', 'label' => __('Workouts'), 'url' => route('workouts.plans.index')],
                ['match' => 'track*', 'icon' => 'bi-graph-up-arrow', 'label' => __('Track'), 'url' => route('track.index')],
                ['match' => 'time*', 'icon' => 'bi-stopwatch', 'label' => __('Time'), 'url' => route('time.reports')],
                ['match' => 'reports*', 'icon' => 'bi-bar-chart-fill', 'label' => __('Reports'), 'url' => route('reports.overview')],
                ['match' => 'notes*', 'icon' => 'bi-journal-text', 'label' => __('Notes'), 'url' => route('notes.index')],
                ['match' => 'reminders*', 'icon' => 'bi-bell-fill', 'label' => __('Reminders'), 'url' => route('reminders.index')],
                ['match' => 'files*', 'icon' => 'bi-file-earmark-fill', 'label' => __('Files'), 'url' => route('files.index')],
            ],
        ],
        [
            'title' => __('Intelligence'),
            'items' => [
                ['match' => 'ai', 'icon' => 'bi-stars', 'label' => __('Lina AI'), 'url' => route('ai.index')],
                ['match' => 'ai/settings*', 'icon' => 'bi-sliders', 'label' => __('AI Settings'), 'url' => route('ai.settings')],
            ],
        ],
    ];

    $isActive = function (string $pattern) {
        return $pattern === '/'
            ? request()->is('/')
            : request()->is($pattern);
    };

    $activeItem = null;
    $activeSection = null;
    foreach ($navSections as $section) {
        foreach ($section['items'] as $item) {
            if ($isActive($item['match'])) {
                $activeItem = $item;
                $activeSection = $section['title'];
                break 2;
            }
        }
    }

    $navInitials = name_initials($navUser->name, 2);
@endphp
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="appSidebar" aria-label="{{ __('Navigation') }}">
    <div class="sidebar-header">
        <a href="{{ route('dashboard') }}" class="sidebar-brand">
            <img src="{{ asset('assets/img/logo-circle.png') }}" alt="{{ __('TaskManager') }}">
            <span class="sidebar-brand-txt">
                <span class="sidebar-brand-name">{{ __('TaskManager') }}</span>
                <span class="sidebar-brand-sub">{{ app_greeting() }}</span>
            </span>
        </a>
    </div>

    <nav class="sidebar-nav">
        @foreach($navSections as $section)
            <div class="nav-section">
                <div class="nav-section-title">{{ $section['title'] }}</div>
                <ul class="nav flex-column">
                    @foreach($section['items'] as $item)
                        <li class="nav-item">
                            <a class="nav-link {{ $isActive($item['match']) ? 'active' : '' }}"
                               href="{{ $item['url'] }}"
                               @if($isActive($item['match'])) aria-current="page" @endif>
                                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                                @if(($item['badge'] ?? null) !== null)
                                    <span class="nav-badge">{{ app_num($item['badge']) }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="sidebar-footer">
        <div class="user-profile" data-bs-toggle="dropdown" aria-expanded="false">
            @if($navUser->avatar)
                <img src="{{ Storage::url($navUser->avatar) }}" alt="{{ $navUser->name }}" class="user-avatar" style="object-fit: cover;">
            @else
                <div class="user-avatar" aria-hidden="true">{{ $navInitials ?: name_initials($navUser->name) }}</div>
            @endif
            <div class="user-info">
                <div class="user-name">{{ $navUser->name }}</div>
                <div class="user-email">{{ $navUser->email }}</div>
            </div>
            <i class="bi bi-chevron-expand" aria-hidden="true"></i>
        </div>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <a class="dropdown-item" href="{{ route('profile.show') }}">
                    <i class="bi bi-person" aria-hidden="true"></i>{{ __('Profile') }}
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="{{ route('profile.password') }}">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i>{{ __('Change Password') }}
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item" href="{{ route('locale.switch', app()->getLocale() === 'fa' ? 'en' : 'fa') }}">
                    <i class="bi bi-translate" aria-hidden="true"></i>
                    {{ app()->getLocale() === 'fa' ? 'English (انگلیسی)' : 'فارسی (Persian)' }}
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="{{ route('logout') }}" id="logout-form">
                    @csrf
                    <button type="submit" class="dropdown-item text-danger w-100">
                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i>{{ __('Logout') }}
                    </button>
                </form>
            </li>
        </ul>
    </div>
</aside>

<div class="content">
    <header class="topnav" id="topnav">
        <div class="topnav-container">
            <div class="topnav-id">
                <button class="sidebar-toggle" type="button" onclick="toggleSidebar()"
                        id="sidebarToggle" aria-label="{{ __('Menu') }}" aria-expanded="false" aria-controls="appSidebar">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <span class="topnav-ico" aria-hidden="true"><i class="bi {{ $activeItem['icon'] ?? 'bi-grid-1x2-fill' }}"></i></span>
                <span class="topnav-txt">
                    <span class="topnav-eyebrow">{{ $activeSection ?? __('TaskManager') }}</span>
                    <span class="page-title">{{ $activeItem['label'] ?? __('Dashboard') }}</span>
                </span>
            </div>

            <div class="topnav-actions">
                <span class="topnav-clock" id="currentDateTime" aria-label="{{ __('Current Time') }}">
                    <span class="pulse" aria-hidden="true"></span>
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    <span class="topnav-clock-date" id="topnavDate"></span>
                    <span class="topnav-clock-time" id="topnavTime"></span>
                </span>

                <span class="topnav-sep" aria-hidden="true"></span>

                <div class="dropdown">
                    <button class="btn btn-outline dropdown-toggle" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false" title="{{ __('Language') }}">
                        <i class="bi bi-globe2 text-primary" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline">{{ app()->getLocale() === 'fa' ? 'فارسی' : 'English' }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item {{ app()->getLocale() === 'fa' ? 'active' : '' }}"
                               href="{{ route('locale.switch', 'fa') }}">
                                <span>🇮🇷 فارسی</span>
                                @if(app()->getLocale() === 'fa')<i class="bi bi-check2 ms-auto" aria-hidden="true"></i>@endif
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ app()->getLocale() === 'en' ? 'active' : '' }}"
                               href="{{ route('locale.switch', 'en') }}">
                                <span>🇬🇧 English</span>
                                @if(app()->getLocale() === 'en')<i class="bi bi-check2 ms-auto" aria-hidden="true"></i>@endif
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="dropdown">
                    <button class="btn btn-brand dropdown-toggle" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        <span class="d-none d-md-inline">{{ __('Quick Add') }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="{{ route('tasks.create') }}">
                                <i class="bi bi-check2-square" aria-hidden="true"></i>{{ __('New Task') }}
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('projects.create') }}">
                                <i class="bi bi-folder-plus" aria-hidden="true"></i>{{ __('New Project') }}
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('notes.create') }}">
                                <i class="bi bi-journal-plus" aria-hidden="true"></i>{{ __('New Note') }}
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('reminders.create') }}">
                                <i class="bi bi-bell" aria-hidden="true"></i>{{ __('New Reminder') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

        <!-- Flash Messages -->
        @foreach(['success' => ['bi-check-circle','#166534','#f0fdf4','#86efac'], 'error' => ['bi-exclamation-circle','#991b1b','#fef2f2','#fca5a5'], 'warning' => ['bi-exclamation-triangle','#92400e','#fffbeb','#fcd34d'], 'info' => ['bi-info-circle','#1e40af','#eff6ff','#93c5fd']] as $type => [$icon,$textColor,$bgColor,$borderColor])
            @if(session($type))
            <div class="px-4 pt-3" style="max-width:100%;">
                <div role="alert" style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:8px;border:1px solid {{ $borderColor }};background:{{ $bgColor }};color:{{ $textColor }};font-size:13px;font-weight:500;line-height:1.4;">
                    <i class="bi {{ $icon }}" style="font-size:15px;flex-shrink:0;"></i>
                    <span style="flex:1;">{{ session($type) }}</span>
                    <button type="button" data-bs-dismiss="alert" aria-label="Close"
                            style="display:flex;align-items:center;justify-content:center;width:22px;height:22px;border:none;background:none;cursor:pointer;color:{{ $textColor }};opacity:.6;padding:0;flex-shrink:0;font-size:14px;line-height:1;"
                            onclick="this.closest('[role=alert]').parentElement.remove()">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            @endif
        @endforeach

        <main>
            @yield('content')
        </main>
        <footer>
            <div class="footer-text">
                &copy; {{ date('Y') }} TaskManager | Crafted with ❤️ by <a href="https://github.com/Darkic3"
                    target="_blank">Ahora</a>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ── Live clock in the top bar ──
        (function () {
            const dateEl = document.getElementById('topnavDate');
            const timeEl = document.getElementById('topnavTime');
            if (!dateEl || !timeEl) return;

            const locale = @json(app()->getLocale() === 'fa' ? 'fa-IR' : 'en-US');
            const dateOpts = { weekday: 'short', month: 'short', day: 'numeric' };
            const timeOpts = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };

            function tick() {
                const now = new Date();
                dateEl.textContent = now.toLocaleDateString(locale, dateOpts);
                timeEl.textContent = now.toLocaleTimeString(locale, timeOpts);
            }

            tick();
            setInterval(tick, 1000);
        })();

        // ── Sticky top bar shadow ──
        (function () {
            const topnav = document.getElementById('topnav');
            const main = document.querySelector('main');
            if (!topnav || !main) return;

            const sync = function () {
                topnav.classList.toggle('is-stuck', main.scrollTop > 4);
            };

            main.addEventListener('scroll', sync, { passive: true });
            sync();
        })();

        // ── Sidebar helpers ──
        const appSidebar = document.getElementById('appSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebarToggle = document.getElementById('sidebarToggle');

        function setSidebar(open) {
            appSidebar.classList.toggle('open', open);
            sidebarOverlay.classList.toggle('active', open);
            document.body.style.overflow = open ? 'hidden' : '';
            if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function openSidebar() {
            setSidebar(true);
        }

        function closeSidebar() {
            setSidebar(false);
        }

        function toggleSidebar() {
            setSidebar(!appSidebar.classList.contains('open'));
        }

        // Close sidebar when a nav link is tapped on mobile
        appSidebar.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 768) closeSidebar();
            });
        });

        // Escape closes the sidebar
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && appSidebar.classList.contains('open')) closeSidebar();
        });

        // Reset state when resizing back to desktop
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) setSidebar(false);
        });
    </script>
    @include('time._store')
    @include('time._widget')
    @include('tasks._drawer')
    @stack('scripts')
    @if(app()->getLocale() === 'fa')
        {{-- Jalali date picker (assets copied from shopora) — fa locale only.
             startWatch uses focusin delegation, so AJAX-added inputs work too.
             zIndex sits above bootstrap/pl modals. --}}
        <script src="{{ asset('assets/jalali/jalaali.js') }}"></script>
        <script src="{{ asset('assets/jalali/jalalidatepicker.min.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof jalaliDatepicker !== 'undefined') {
                    jalaliDatepicker.startWatch({ minDate: 'attr', maxDate: 'attr', time: true, zIndex: 3000 });
                }
                if (typeof window.jalaali === 'undefined') return;

                var toEnDigits = function (s) {
                    return String(s == null ? '' : s).replace(/[۰-۹]/g, function (d) {
                        return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);
                    }).replace(/[٠-٩]/g, function (d) {
                        return '٠١٢٣٤٥٦٧٨٩'.indexOf(d);
                    });
                };
                // 'YYYY/MM/DD [HH:mm]' (Jalali) → 'YYYY-MM-DD [HH:mm]' (Gregorian). '' when invalid/empty.
                window.jalaliToGregorian = function (jalaliStr, withTime) {
                    var m = toEnDigits(jalaliStr || '').match(/(\d+)\/(\d+)\/(\d+)(?:\s+(\d+):(\d+))?/);
                    if (!m) return '';
                    var jy = +m[1], jm = +m[2], jd = +m[3];
                    if (!window.jalaali.isValidJalaaliDate(jy, jm, jd)) return '';
                    var g = window.jalaali.toGregorian(jy, jm, jd);
                    var pad = function (n) { return String(n).padStart(2, '0'); };
                    var out = g.gy + '-' + pad(g.gm) + '-' + pad(g.gd);
                    if (withTime) out += ' ' + pad(m[4] == null ? 0 : m[4]) + ':' + pad(m[5] == null ? 0 : m[5]);
                    return out;
                };
                // Gregorian → Jalali display string for a visible picker input.
                window.gregorianToJalali = function (gregStr, withTime) {
                    var m = toEnDigits(gregStr || '').match(/(\d+)-(\d+)-(\d+)(?:[T\s](\d+):(\d+))?/);
                    if (!m) return '';
                    var j = window.jalaali.toJalaali(+m[1], +m[2], +m[3]);
                    var pad = function (n) { return String(n).padStart(2, '0'); };
                    var out = j.jy + '/' + pad(j.jm) + '/' + pad(j.jd);
                    if (withTime) out += ' ' + pad(m[4] == null ? 0 : m[4]) + ':' + pad(m[5] == null ? 0 : m[5]);
                    return out;
                };
                var syncHidden = function (vis) {
                    var hidden = document.getElementById(vis.getAttribute('data-target'));
                    if (!hidden) return;
                    var box = vis.closest('[data-jdp-scope]') || document;
                    var withTime = !vis.hasAttribute('data-jdp-only-date');
                    var next = window.jalaliToGregorian(vis.value, withTime);
                    if (hidden.value !== next) {
                        hidden.value = next;
                        hidden.dispatchEvent(new Event('input', { bubbles: true }));
                        hidden.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                };
                document.querySelectorAll('[data-jdp][data-target]').forEach(function (el) {
                    el.addEventListener('change', function () { syncHidden(el); });
                    el.addEventListener('input', function () { syncHidden(el); });
                    syncHidden(el);
                });
                // Refresh a visible picker from its hidden Gregorian value
                // (used after JS sets hidden values programmatically).
                window.jalaliSyncVisible = function (hiddenId) {
                    var hidden = document.getElementById(hiddenId);
                    if (!hidden) return;
                    var vis = document.getElementById(hiddenId + '-jalali');
                    if (!vis) return;
                    var withTime = !vis.hasAttribute('data-jdp-only-date');
                    vis.value = hidden.value ? window.gregorianToJalali(hidden.value, withTime) : '';
                };
            });
        </script>
    @endif
    <!-- SweetAlert2 Helpers & Locale Integration -->
    <script>
        (function() {
            const isFa = document.documentElement.lang === 'fa' || '{{ app()->getLocale() }}' === 'fa';
            
            const CustomSwal = typeof Swal !== 'undefined' ? Swal.mixin({
                customClass: {
                    popup: 'tm-swal-popup',
                    title: 'tm-swal-title',
                    htmlContainer: 'tm-swal-html',
                    confirmButton: 'tm-swal-btn tm-swal-confirm-btn',
                    cancelButton: 'tm-swal-btn tm-swal-cancel-btn',
                    actions: 'tm-swal-actions'
                },
                buttonsStyling: false
            }) : null;

            window.tmSwal = CustomSwal;

            window.confirmSwal = function(arg1, arg2, arg3) {
                if (!CustomSwal) {
                    if (arg1 instanceof HTMLElement || typeof arg1 === 'function') {
                        if (confirm(arg2 || '')) {
                            if (arg1 instanceof HTMLFormElement) arg1.submit();
                            else if (arg1 instanceof HTMLElement) arg1.closest('form')?.submit();
                            else if (typeof arg1 === 'function') arg1();
                        }
                        return false;
                    }
                    return Promise.resolve(confirm(arg1 || ''));
                }

                let formOrCb = null;
                let message = '';
                let options = {};

                if (arg1 instanceof HTMLElement || typeof arg1 === 'function') {
                    formOrCb = arg1;
                    message = arg2 || '';
                    options = arg3 || {};
                } else {
                    message = arg1 || '';
                    options = arg2 || {};
                    if (typeof arg3 === 'object') Object.assign(options, arg3);
                }

                const isDelete = options.isDelete || (message && (
                    message.toLowerCase().includes('delete') || 
                    message.toLowerCase().includes('archive') || 
                    message.toLowerCase().includes('remove') ||
                    message.includes('حذف') || 
                    message.includes('آرشیو') ||
                    message.includes('بایگانی') ||
                    message.includes('محو')
                ));

                const defaultTitle = options.title || (
                    isFa 
                        ? (isDelete ? 'آیا از انجام این کار مطمئن هستید؟' : 'تایید عملیات')
                        : (isDelete ? 'Are you sure?' : 'Confirm Action')
                );

                const defaultConfirmText = options.confirmButtonText || (
                    isFa 
                        ? (isDelete ? 'بله، انجام شود' : 'تایید') 
                        : (isDelete ? 'Yes, delete it' : 'Confirm')
                );

                const defaultCancelText = options.cancelButtonText || (isFa ? 'انصراف' : 'Cancel');

                const confirmBtnClass = isDelete 
                    ? 'tm-swal-btn tm-swal-confirm-btn danger' 
                    : 'tm-swal-btn tm-swal-confirm-btn';

                const swalOpts = {
                    title: defaultTitle,
                    text: message,
                    icon: options.icon || (isDelete ? 'warning' : 'question'),
                    showCancelButton: true,
                    confirmButtonText: defaultConfirmText,
                    cancelButtonText: defaultCancelText,
                    reverseButtons: true,
                    customClass: {
                        popup: 'tm-swal-popup',
                        title: 'tm-swal-title',
                        htmlContainer: 'tm-swal-html',
                        confirmButton: confirmBtnClass,
                        cancelButton: 'tm-swal-btn tm-swal-cancel-btn',
                        actions: 'tm-swal-actions'
                    },
                    ...options
                };

                if (formOrCb) {
                    CustomSwal.fire(swalOpts).then((result) => {
                        if (result.isConfirmed) {
                            if (formOrCb instanceof HTMLFormElement) {
                                formOrCb.submit();
                            } else if (formOrCb instanceof HTMLElement) {
                                const form = formOrCb.closest('form');
                                if (form) form.submit();
                            } else if (typeof formOrCb === 'function') {
                                formOrCb();
                            }
                        }
                    });
                    return false;
                } else {
                    return CustomSwal.fire(swalOpts).then((result) => result.isConfirmed);
                }
            };

            window.alertSwal = function(message, title, icon = 'info') {
                if (!CustomSwal) {
                    alert(message);
                    return Promise.resolve();
                }

                const defaultTitle = title || (
                    icon === 'error' ? (isFa ? 'خطا' : 'Error') :
                    icon === 'success' ? (isFa ? 'موفقیت‌آمیز' : 'Success') :
                    icon === 'warning' ? (isFa ? 'هشدار' : 'Warning') :
                    (isFa ? 'اطلاع‌رسانی' : 'Notice')
                );

                return CustomSwal.fire({
                    title: defaultTitle,
                    text: message,
                    icon: icon,
                    confirmButtonText: isFa ? 'متوجه شدم' : 'OK'
                });
            };

            window.promptSwal = function(message, defaultValue = '', options = {}) {
                if (!CustomSwal) {
                    return Promise.resolve(prompt(message, defaultValue));
                }

                return CustomSwal.fire({
                    title: options.title || message,
                    text: options.text || '',
                    icon: options.icon || 'question',
                    input: options.input || 'text',
                    inputValue: defaultValue,
                    inputAttributes: options.inputAttributes || {},
                    inputValidator: options.validator || undefined,
                    showCancelButton: true,
                    confirmButtonText: options.confirmButtonText || (isFa ? 'تایید' : 'Confirm'),
                    cancelButtonText: options.cancelButtonText || (isFa ? 'انصراف' : 'Cancel'),
                    reverseButtons: true,
                    customClass: {
                        popup: 'tm-swal-popup',
                        title: 'tm-swal-title',
                        htmlContainer: 'tm-swal-html',
                        input: 'tm-swal-input',
                        confirmButton: 'tm-swal-btn tm-swal-confirm-btn',
                        cancelButton: 'tm-swal-btn tm-swal-cancel-btn',
                        actions: 'tm-swal-actions'
                    },
                    buttonsStyling: false
                }).then((result) => (result.isConfirmed ? result.value : null));
            };
        })();
    </script>
</body>

</html>
