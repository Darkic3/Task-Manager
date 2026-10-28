@php($isFa = app()->getLocale() === 'fa')
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isFa ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    <title>{{ __('Create an account') }} — {{ __('TaskManager') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { min-height:100vh; margin:0; display:grid; place-items:center; padding:24px; color:#172033; background:linear-gradient(135deg,#eef2ff,#f8fafc 55%,#ede9fe); font:16px/1.7 system-ui,-apple-system,"Segoe UI",sans-serif; }
        .card { width:min(440px,100%); padding:clamp(24px,6vw,42px); border:1px solid #e2e8f0; border-radius:24px; background:#fff; box-shadow:0 24px 70px #3730a322; }
        .brand { display:flex; align-items:center; gap:12px; margin-bottom:28px; color:#4f46e5; font-weight:800; font-size:1.1rem; }
        .brand img { width:44px; height:44px; border-radius:13px; }
        h1 { margin:0; color:#0f172a; font-size:1.8rem; }
        .intro { margin:5px 0 24px; color:#64748b; }
        label { display:block; margin:16px 0 6px; font-weight:700; font-size:.9rem; }
        input { width:100%; min-height:49px; padding:10px 13px; border:1px solid #cbd5e1; border-radius:11px; background:#f8fafc; font:inherit; direction:ltr; text-align:left; }
        input:focus { outline:3px solid #c7d2fe; border-color:#6366f1; background:#fff; }
        .hint,.error { margin:5px 0 0; color:#64748b; font-size:.82rem; }
        .error { color:#b91c1c; }
        .alert { margin:16px 0; padding:12px 14px; border:1px solid #fecaca; border-radius:10px; color:#991b1b; background:#fef2f2; }
        button { width:100%; min-height:51px; margin-top:24px; border:0; border-radius:11px; color:#fff; background:linear-gradient(110deg,#4f46e5,#8b5cf6); font-family:inherit; font-size:1rem; font-weight:700; cursor:pointer; }
        button:hover { filter:brightness(1.05); }
        .foot { margin:20px 0 0; text-align:center; color:#64748b; font-size:.9rem; }
        a { color:#4f46e5; font-weight:700; text-decoration:none; }
        a:hover { text-decoration:underline; }
        @media (max-width:480px) { body { padding:0; } .card { min-height:100vh; border:0; border-radius:0; } }
    </style>
</head>
<body>
<main class="card">
    <div class="brand"><img src="{{ asset('assets/img/logo-circle.png') }}" alt=""><span>{{ __('TaskManager') }}</span></div>
    <h1>{{ __('Create an account') }}</h1>
    <p class="intro">{{ __('Choose a username and password to get started.') }}</p>

    @if ($errors->any())
        <div class="alert" role="alert">{{ __('Please check the fields below and try again.') }}</div>
    @endif

    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <label for="username">{{ __('Username') }}</label>
        <input id="username" name="username" value="{{ old('username') }}" required minlength="3" maxlength="30" autocomplete="username" autocapitalize="off" spellcheck="false" autofocus>
        <p class="hint">{{ __('Use 3–30 English letters, numbers, underscores or hyphens.') }}</p>
        @error('username')<p class="error" role="alert">{{ $message }}</p>@enderror

        <label for="password">{{ __('Password') }}</label>
        <input id="password" name="password" type="password" required minlength="10" autocomplete="new-password">
        @error('password')<p class="error" role="alert">{{ $message }}</p>@enderror

        <label for="password_confirmation">{{ __('Confirm Password') }}</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="10" autocomplete="new-password">

        <button type="submit">{{ __('Create an account') }}</button>
    </form>

    <p class="foot">{{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Sign In') }}</a></p>
</main>
</body>
</html>
