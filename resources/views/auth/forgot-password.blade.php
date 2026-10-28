@php($isFa = app()->getLocale() === 'fa')
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isFa ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Reset Password') }} — {{ __('TaskManager') }}</title>
    <style>
        * { box-sizing:border-box; }
        body { min-height:100vh; margin:0; display:grid; place-items:center; padding:24px; color:#172033; background:linear-gradient(135deg,#eef2ff,#f8fafc 55%,#ede9fe); font:16px/1.7 system-ui,-apple-system,"Segoe UI",sans-serif; }
        .card { width:min(440px,100%); padding:clamp(24px,6vw,42px); border:1px solid #e2e8f0; border-radius:24px; background:#fff; box-shadow:0 24px 70px #3730a322; }
        .brand { display:flex; align-items:center; gap:12px; margin-bottom:28px; color:#4f46e5; font-weight:800; font-size:1.1rem; }
        .brand img { width:44px; height:44px; border-radius:13px; }
        h1 { margin:0; color:#0f172a; font-size:1.7rem; }
        p { color:#64748b; }
        label { display:block; margin:16px 0 6px; font-weight:700; font-size:.9rem; }
        input { width:100%; min-height:49px; padding:10px 13px; border:1px solid #cbd5e1; border-radius:11px; background:#f8fafc; font:inherit; direction:ltr; text-align:left; }
        button { width:100%; min-height:51px; margin-top:22px; border:0; border-radius:11px; color:#fff; background:linear-gradient(110deg,#4f46e5,#8b5cf6); font:inherit; font-weight:700; cursor:pointer; }
        .status,.error { margin-top:12px; font-size:.88rem; }
        .status { color:#047857; }.error { color:#b91c1c; }
        .foot { margin:20px 0 0; text-align:center; font-size:.9rem; }
        a { color:#4f46e5; font-weight:700; text-decoration:none; }
        @media(max-width:480px) { body { padding:0; }.card { min-height:100vh; border:0; border-radius:0; } }
    </style>
</head>
<body>
<main class="card">
    <div class="brand"><img src="{{ asset('assets/img/logo-circle.png') }}" alt=""><span>{{ __('TaskManager') }}</span></div>
    <h1>{{ __('Reset Password') }}</h1>
    <p>{{ __('Enter the email address you added to your profile. If it is linked to an account, we will send a reset link.') }}</p>

    @if (session('status'))<p class="status" role="status">{{ session('status') }}</p>@endif
    @error('email')<p class="error" role="alert">{{ $message }}</p>@enderror

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label for="email">{{ __('Email Address') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        <button type="submit">{{ __('Send reset link') }}</button>
    </form>
    <p class="foot"><a href="{{ route('login') }}">{{ __('Back to sign in') }}</a></p>
</main>
</body>
</html>
