@extends('layouts.app')
@section('title', __('Change Password'))
@push('styles')
@include('profile._style')
@endpush

@section('content')
@php
    $initials = collect(preg_split('/\s+/u', trim($user->name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $initials = $initials !== '' ? $initials : mb_strtoupper(mb_substr($user->name, 0, 2));
@endphp

<div class="pf-page">

    @include('profile._hero', [
        'pfTitle' => __('Change Password'),
        'pfSubtitle' => __('Keep your account secure'),
        'pfIcon' => 'bi-shield-lock',
        'pfUser' => $user,
    ])

    <form action="{{ route('profile.password.update') }}" method="POST" id="pw-form" novalidate>
        @csrf
        @method('PUT')

        <div class="pf-grid">
            <div class="pf-col">

                <section class="pf-card" style="animation-delay:.14s;">
                    <header class="pf-card-hd">
                        <span class="pf-card-ico rose"><i class="bi bi-key"></i></span>
                        <div>
                            <h2 class="pf-card-title">{{ __('Update Password') }}</h2>
                            <p class="pf-card-sub">{{ __('Use at least 8 characters with a mix of letters, numbers, and symbols.') }}</p>
                        </div>
                    </header>
                    <div class="pf-card-bd">

                        <div class="pf-field">
                            <label for="current_password" class="pf-label">{{ __('Current Password') }} <span class="pf-req">*</span></label>
                            <div class="pf-input-wrap">
                                <input type="password" id="current_password" name="current_password" required
                                       autocomplete="current-password"
                                       class="pf-input @error('current_password') is-invalid @enderror">
                                <button type="button" class="pf-reveal" data-reveal="current_password"
                                        aria-label="{{ __('Show password') }}" title="{{ __('Show password') }}">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            @error('current_password')<p class="pf-err">{{ $message }}</p>@enderror
                        </div>

                        <div class="pf-field">
                            <label for="password" class="pf-label">{{ __('New Password') }} <span class="pf-req">*</span></label>
                            <div class="pf-input-wrap">
                                <input type="password" id="password" name="password" required
                                       autocomplete="new-password"
                                       class="pf-input @error('password') is-invalid @enderror">
                                <button type="button" class="pf-reveal" data-reveal="password"
                                        aria-label="{{ __('Show password') }}" title="{{ __('Show password') }}">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>

                            <div class="pf-strength">
                                <div class="pf-strength-track"><div class="pf-strength-fill" id="strength-fill"></div></div>
                                <div class="pf-strength-text" id="strength-text"></div>
                            </div>

                            <ul class="pf-req" id="req-list">
                                <li id="req-len"><i class="bi bi-circle"></i> {{ __('At least 8 characters') }}</li>
                                <li id="req-upper"><i class="bi bi-circle"></i> {{ __('One uppercase letter') }}</li>
                                <li id="req-lower"><i class="bi bi-circle"></i> {{ __('One lowercase letter') }}</li>
                                <li id="req-num"><i class="bi bi-circle"></i> {{ __('One number') }}</li>
                                <li id="req-sym"><i class="bi bi-circle"></i> {{ __('One special character') }}</li>
                            </ul>

                            @error('password')<p class="pf-err">{{ $message }}</p>@enderror
                        </div>

                        <div class="pf-field">
                            <label for="password_confirmation" class="pf-label">{{ __('Confirm New Password') }} <span class="pf-req">*</span></label>
                            <div class="pf-input-wrap">
                                <input type="password" id="password_confirmation" name="password_confirmation" required
                                       autocomplete="new-password" class="pf-input">
                                <button type="button" class="pf-reveal" data-reveal="password_confirmation"
                                        aria-label="{{ __('Show password') }}" title="{{ __('Show password') }}">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="pf-match" id="match-msg" role="status" aria-live="polite"></div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Side column --}}
            <aside class="pf-col pf-col-side">
                <section class="pf-card" style="animation-delay:.17s;">
                    <header class="pf-card-hd">
                        <span class="pf-card-ico violet"><i class="bi bi-person-circle"></i></span>
                        <div>
                            <h2 class="pf-card-title">{{ __('Account') }}</h2>
                            <p class="pf-card-sub">{{ __('Signed in as') }}</p>
                        </div>
                    </header>
                    <div class="pf-card-bd">
                        <div style="text-align:center;margin-bottom:14px;">
                            <div class="pf-avatar-ring" style="width:76px;height:76px;margin:0 auto;">
                                @if($user->avatar)
                                    <img src="{{ Storage::url($user->avatar) }}" alt="{{ $user->name }}" class="pf-avatar-img">
                                @else
                                    <div class="pf-avatar-init" style="font-size:1.5rem;" aria-hidden="true">{{ $initials }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="pf-note pf-note-amber">
                            <i class="bi bi-shield-exclamation"></i>
                            <span>
                                <strong>{{ __('Stay safe') }}</strong>
                                {{ __('Never share your password and sign out of shared devices.') }}
                            </span>
                        </div>

                        <div class="pf-note pf-note-sky" style="margin-top:12px;">
                            <i class="bi bi-clock-history"></i>
                            <span>
                                <strong>{{ __('Last updated') }}</strong>
                                {{ app_diff_for_humans($user->updated_at) }}
                            </span>
                        </div>
                    </div>
                </section>

                <section class="pf-card" style="animation-delay:.23s;">
                    <header class="pf-card-hd">
                        <span class="pf-card-ico green"><i class="bi bi-shield-check"></i></span>
                        <div>
                            <h2 class="pf-card-title">{{ __('Password tips') }}</h2>
                            <p class="pf-card-sub">{{ __('A strong password in 3 steps') }}</p>
                        </div>
                    </header>
                    <div class="pf-card-bd">
                        <ul class="pf-tips">
                            <li>
                                <span class="pf-tips-ico"><i class="bi bi-123"></i></span>
                                <span><strong>{{ __('Make it long') }}</strong>{{ __('Longer phrases are harder to guess than short symbols.') }}</span>
                            </li>
                            <li>
                                <span class="pf-tips-ico"><i class="bi bi-shuffle"></i></span>
                                <span><strong>{{ __('Make it unique') }}</strong>{{ __('Use a different password for every account.') }}</span>
                            </li>
                            <li>
                                <span class="pf-tips-ico"><i class="bi bi-key-fill"></i></span>
                                <span><strong>{{ __('Keep it safe') }}</strong>{{ __('Store it in a password manager, not in a note.') }}</span>
                            </li>
                        </ul>
                    </div>
                </section>
            </aside>
        </div>

        <div class="pf-actions">
            <p class="pf-actions-note"><i class="bi bi-info-circle"></i> {{ __('You will stay signed in on this device.') }}</p>
            <span class="pf-actions-spacer"></span>
            <a href="{{ route('profile.show') }}" class="pf-btn pf-btn-soft"><i class="bi bi-x-lg"></i> {{ __('Cancel') }}</a>
            <button type="submit" class="pf-btn pf-btn-solid"><i class="bi bi-check-lg"></i> {{ __('Update Password') }}</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var I18N = {
        labels: ['', @json(__('Weak')), @json(__('Fair')), @json(__('Good')), @json(__('Strong')), @json(__('Very Strong'))],
        colors: ['', '#dc2626', '#d97706', '#2563eb', '#059669', '#047857'],
        match: @json(__('Passwords match')),
        noMatch: @json(__('Passwords do not match')),
        show: @json(__('Show password')),
        hide: @json(__('Hide password'))
    };

    // Reveal / hide password
    document.querySelectorAll('[data-reveal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(this.dataset.reveal);
            var icon = this.querySelector('i');
            var isText = input.type === 'text';
            input.type = isText ? 'password' : 'text';
            icon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
            this.setAttribute('aria-label', isText ? I18N.show : I18N.hide);
            this.setAttribute('title', isText ? I18N.show : I18N.hide);
        });
    });

    var password = document.getElementById('password');
    var confirmField = document.getElementById('password_confirmation');
    var matchMsg = document.getElementById('match-msg');

    function markReq(id, met) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.toggle('met', met);
        el.querySelector('i').className = met ? 'bi bi-check-circle-fill' : 'bi bi-circle';
    }

    function checkMatch() {
        if (!password || !confirmField || !matchMsg) return;
        if (!confirmField.value.length) {
            matchMsg.textContent = '';
            matchMsg.className = 'pf-match';
            return;
        }
        var ok = password.value === confirmField.value;
        matchMsg.textContent = (ok ? '\u2713 ' : '\u2715 ') + (ok ? I18N.match : I18N.noMatch);
        matchMsg.className = 'pf-match ' + (ok ? 'ok' : 'bad');
    }

    function checkStrength() {
        if (!password) return;
        var val = password.value;
        var rules = [
            ['req-len', val.length >= 8],
            ['req-upper', /[A-Z]/.test(val)],
            ['req-lower', /[a-z]/.test(val)],
            ['req-num', /\d/.test(val)],
            ['req-sym', /[!@#$%^&*(),.?":{}|<>_\-]/.test(val)]
        ];
        rules.forEach(function (rule) { markReq(rule[0], rule[1]); });

        var score = rules.filter(function (rule) { return rule[1]; }).length;
        var fill = document.getElementById('strength-fill');
        var text = document.getElementById('strength-text');
        var color = I18N.colors[score] || '#cbd5e1';
        fill.style.width = (score * 20) + '%';
        fill.style.background = color;
        text.textContent = val.length ? (I18N.labels[score] || '') : '';
        text.style.color = color;
        checkMatch();
    }

    if (password) password.addEventListener('input', checkStrength);
    if (confirmField) confirmField.addEventListener('input', checkMatch);
});
</script>
@endpush
