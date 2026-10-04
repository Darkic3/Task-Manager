@extends('layouts.app')
@section('title', __('Edit Profile'))
@push('styles')
@include('profile._style')
@endpush

@section('content')
@php
    $isFa = app()->getLocale() === 'fa';

    $initials = name_initials($user->name, 2);

    $bioLength = mb_strlen((string) old('bio', $user->bio));
@endphp

<div class="pf-page">

    @include('profile._hero', [
        'pfTitle' => __('Edit Profile'),
        'pfSubtitle' => __('Update your personal information'),
        'pfIcon' => 'bi-pencil-square',
        'pfUser' => $user,
    ])

    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" id="profile-form" novalidate>
        @csrf
        @method('PUT')

        <div class="pf-grid">
            <div class="pf-col">

                {{-- Basic information --}}
                <section class="pf-card" style="animation-delay:.14s;">
                    <header class="pf-card-hd">
                        <span class="pf-card-ico violet"><i class="bi bi-person-vcard"></i></span>
                        <div>
                            <h2 class="pf-card-title">{{ __('Basic Information') }}</h2>
                            <p class="pf-card-sub">{{ __('Personal details') }}</p>
                        </div>
                    </header>
                    <div class="pf-card-bd">

                        <div class="pf-field-row">
                            <div class="pf-field">
                                <label for="name" class="pf-label">{{ __('Full Name') }} <span class="pf-req">*</span></label>
                                <input type="text" id="name" name="name" required autofocus
                                       class="pf-input @error('name') is-invalid @enderror"
                                       value="{{ old('name', $user->name) }}">
                                @error('name')<p class="pf-err">{{ $message }}</p>@enderror
                            </div>
                            <div class="pf-field">
                                <label for="email" class="pf-label">{{ __('Email Address') }} <span class="pf-req">*</span></label>
                                <input type="email" id="email" name="email" required dir="ltr"
                                       class="pf-input @error('email') is-invalid @enderror"
                                       value="{{ old('email', $user->email) }}">
                                @error('email')<p class="pf-err">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="pf-field-row">
                            <div class="pf-field">
                                <label for="phone" class="pf-label">{{ __('Phone') }}</label>
                                <input type="tel" id="phone" name="phone" dir="ltr" placeholder="+1 555 000 0000"
                                       class="pf-input @error('phone') is-invalid @enderror"
                                       value="{{ old('phone', $user->phone) }}">
                                @error('phone')<p class="pf-err">{{ $message }}</p>@enderror
                            </div>
                            <div class="pf-field">
                                <label for="location" class="pf-label">{{ __('Location') }}</label>
                                <input type="text" id="location" name="location" placeholder="{{ __('City, Country') }}"
                                       class="pf-input @error('location') is-invalid @enderror"
                                       value="{{ old('location', $user->location) }}">
                                @error('location')<p class="pf-err">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="pf-field-row">
                            <div class="pf-field">
                                <label for="website" class="pf-label">{{ __('Website') }}</label>
                                <input type="url" id="website" name="website" dir="ltr" placeholder="https://example.com"
                                       class="pf-input @error('website') is-invalid @enderror"
                                       value="{{ old('website', $user->website) }}">
                                @error('website')<p class="pf-err">{{ $message }}</p>@enderror
                            </div>
                            <div class="pf-field">
                                <label for="locale" class="pf-label">{{ __('Language') }}</label>
                                <select id="locale" name="locale" class="pf-select @error('locale') is-invalid @enderror">
                                    <option value="en" {{ old('locale', $user->locale ?? 'en') === 'en' ? 'selected' : '' }}>🇬🇧 {{ __('English') }}</option>
                                    <option value="fa" {{ old('locale', $user->locale ?? 'en') === 'fa' ? 'selected' : '' }}>🇮🇷 {{ __('Persian') }}</option>
                                </select>
                                <p class="pf-hint">{{ __('The interface language changes immediately after saving.') }}</p>
                                @error('locale')<p class="pf-err">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="pf-field">
                            <div class="pf-label-row">
                                <label for="bio" class="pf-label">{{ __('Bio') }}</label>
                                <span class="pf-counter pf-num">{{ app_num($bioLength) }} / {{ app_num(500) }}</span>
                            </div>
                            <textarea id="bio" name="bio" rows="4" maxlength="500"
                                      class="pf-textarea @error('bio') is-invalid @enderror"
                                      placeholder="{{ __('Tell us about yourself...') }}">{{ old('bio', $user->bio) }}</textarea>
                            <p class="pf-hint">{{ __('Max 500 characters') }}</p>
                            @error('bio')<p class="pf-err">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                {{-- Morning check-in --}}
                <section class="pf-card" style="animation-delay:.2s;">
                    <header class="pf-card-hd">
                        <span class="pf-card-ico amber"><i class="bi bi-sunrise"></i></span>
                        <div>
                            <h2 class="pf-card-title">{{ __('Morning Check-in') }}</h2>
                            <p class="pf-card-sub">{{ __('Log your wake-up time first thing after login') }}</p>
                        </div>
                    </header>
                    <div class="pf-card-bd">
                        <label class="pf-switch">
                            <input type="checkbox" id="morning_checkin_enabled" name="morning_checkin_enabled" value="1"
                                   {{ old('morning_checkin_enabled', $user->morning_checkin_enabled) ? 'checked' : '' }}>
                            <span class="pf-switch-track" aria-hidden="true"><span class="pf-switch-thumb"></span></span>
                            <span class="pf-switch-label">{{ __('Enable morning check-in') }}</span>
                        </label>
                        <p class="pf-hint">{{ __('When enabled, you will be asked to log your wake-up time when you log in during the morning window.') }}</p>
                        @error('morning_checkin_enabled')<p class="pf-err">{{ $message }}</p>@enderror

                        <div class="pf-switch-panel" id="morning-checkin-fields">
                            <div class="pf-field">
                                <label for="wake_routine_id" class="pf-label">{{ __('Wake-up routine') }}</label>
                                <select id="wake_routine_id" name="wake_routine_id"
                                        class="pf-select @error('wake_routine_id') is-invalid @enderror">
                                    <option value="">{{ __('Select a routine') }}</option>
                                    @foreach($wakeRoutines as $routine)
                                        <option value="{{ $routine->id }}" {{ (string) old('wake_routine_id', $user->wake_routine_id ?? '') === (string) $routine->id ? 'selected' : '' }}>{{ $routine->title }}</option>
                                    @endforeach
                                </select>
                                @error('wake_routine_id')<p class="pf-err">{{ $message }}</p>@enderror
                            </div>

                            <div class="pf-field-row">
                                <div class="pf-field">
                                    <label for="morning_window_start" class="pf-label">{{ __('Window start') }}</label>
                                    <input type="time" id="morning_window_start" name="morning_window_start" dir="ltr"
                                           class="pf-input @error('morning_window_start') is-invalid @enderror"
                                           value="{{ old('morning_window_start', substr((string) ($user->morning_window_start ?? '04:00'), 0, 5)) }}">
                                    @error('morning_window_start')<p class="pf-err">{{ $message }}</p>@enderror
                                </div>
                                <div class="pf-field">
                                    <label for="morning_window_end" class="pf-label">{{ __('Window end') }}</label>
                                    <input type="time" id="morning_window_end" name="morning_window_end" dir="ltr"
                                           class="pf-input @error('morning_window_end') is-invalid @enderror"
                                           value="{{ old('morning_window_end', substr((string) ($user->morning_window_end ?? '12:00'), 0, 5)) }}">
                                    @error('morning_window_end')<p class="pf-err">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Side column --}}
            <aside class="pf-col pf-col-side">
                <section class="pf-card" style="animation-delay:.17s;">
                    <header class="pf-card-hd">
                        <span class="pf-card-ico blue"><i class="bi bi-camera"></i></span>
                        <div>
                            <h2 class="pf-card-title">{{ __('Profile Picture') }}</h2>
                            <p class="pf-card-sub">{{ __('JPG, PNG or GIF · max 2 MB') }}</p>
                        </div>
                    </header>
                    <div class="pf-card-bd">
                        <div style="text-align:center;margin-bottom:16px;">
                            <div class="pf-avatar-ring" style="margin:0 auto;">
                                @if($user->avatar)
                                    <img src="{{ Storage::url($user->avatar) }}" alt="{{ $user->name }}" class="pf-avatar-img" id="avatar-preview-img">
                                @else
                                    <div class="pf-avatar-init" id="avatar-init" aria-hidden="true">{{ $initials }}</div>
                                @endif
                            </div>
                        </div>

                        <label class="pf-drop" for="avatar-input">
                            <i class="bi bi-cloud-arrow-up"></i>
                            <strong>{{ __('Click to upload new photo') }}</strong>
                            <span>{{ __('JPG, PNG or GIF · max 2 MB') }}</span>
                            <input type="file" name="avatar" id="avatar-input" accept="image/*">
                        </label>

                        <div class="pf-filechip" id="avatar-sel">
                            <i class="bi bi-image"></i>
                            <span id="avatar-sel-name"></span>
                            <button type="button" id="avatar-clear" title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}">&times;</button>
                        </div>

                        @if($user->avatar)
                            <button type="button" class="pf-btn-ghost-danger" id="del-avatar-btn">
                                <i class="bi bi-trash"></i> {{ __('Remove Current Photo') }}
                            </button>
                        @endif
                    </div>
                </section>

                <section class="pf-card" style="animation-delay:.23s;">
                    <header class="pf-card-hd">
                        <span class="pf-card-ico green"><i class="bi bi-lightbulb"></i></span>
                        <div>
                            <h2 class="pf-card-title">{{ __('Good to know') }}</h2>
                            <p class="pf-card-sub">{{ __('Before you save') }}</p>
                        </div>
                    </header>
                    <div class="pf-card-bd">
                        <ul class="pf-tips">
                            <li>
                                <span class="pf-tips-ico"><i class="bi bi-envelope-check"></i></span>
                                <span><strong>{{ __('Email') }}</strong>{{ __('Used to sign in and to receive reminders.') }}</span>
                            </li>
                            <li>
                                <span class="pf-tips-ico"><i class="bi bi-translate"></i></span>
                                <span><strong>{{ __('Language') }}</strong>{{ __('Applies to the whole app, including dates.') }}</span>
                            </li>
                            <li>
                                <span class="pf-tips-ico"><i class="bi bi-clock-history"></i></span>
                                <span><strong>{{ __('Morning Check-in') }}</strong>{{ __('Needs a wake-up routine to be selected.') }}</span>
                            </li>
                        </ul>
                    </div>
                </section>
            </aside>
        </div>

        <div class="pf-actions">
            <p class="pf-actions-note"><i class="bi bi-info-circle"></i> {{ __('Changes are saved to your account.') }}</p>
            <span class="pf-actions-spacer"></span>
            <a href="{{ route('profile.show') }}" class="pf-btn pf-btn-soft"><i class="bi bi-x-lg"></i> {{ __('Cancel') }}</a>
            <button type="submit" class="pf-btn pf-btn-solid"><i class="bi bi-check-lg"></i> {{ __('Update Profile') }}</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var I18N = {
        tooLarge: @json(__('Max file size is 2 MB')),
        removeAvatar: @json(__('Remove your profile picture?'))
    };

    // Bio character counter
    var bio = document.getElementById('bio');
    var counter = document.querySelector('.pf-counter');
    if (bio && counter) {
        var faDigits = { '0': '۰', '1': '۱', '2': '۲', '3': '۳', '4': '۴', '5': '۵', '6': '۶', '7': '۷', '8': '۸', '9': '۹' };
        var toLocale = function (value) {
            return @json($isFa ? 'fa' : 'en') === 'fa'
                ? String(value).replace(/[0-9]/g, function (d) { return faDigits[d]; })
                : String(value);
        };
        var updateCounter = function () {
            counter.textContent = toLocale(bio.value.length) + ' / ' + toLocale(500);
        };
        bio.addEventListener('input', updateCounter);
        updateCounter();
    }

    // Avatar: preview, clear, remove
    var avatarInput = document.getElementById('avatar-input');
    var avatarSel = document.getElementById('avatar-sel');
    var avatarSelName = document.getElementById('avatar-sel-name');
    var avatarPreviewImg = document.getElementById('avatar-preview-img');
    var avatarInit = document.getElementById('avatar-init');
    var delBtn = document.getElementById('del-avatar-btn');

    if (avatarInput) {
        avatarInput.addEventListener('change', function () {
            if (!this.files.length) return;
            var file = this.files[0];
            if (file.size > 2 * 1024 * 1024) {
                alertSwal(I18N.tooLarge, null, 'warning');
                this.value = '';
                return;
            }
            avatarSelName.textContent = file.name;
            avatarSel.classList.add('show');

            var reader = new FileReader();
            reader.onload = function (e) {
                if (avatarPreviewImg) {
                    avatarPreviewImg.src = e.target.result;
                } else if (avatarInit) {
                    var img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'pf-avatar-img';
                    img.id = 'avatar-preview-img';
                    img.alt = '';
                    avatarInit.replaceWith(img);
                }
            };
            reader.readAsDataURL(file);
        });
    }

    var clearBtn = document.getElementById('avatar-clear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            avatarInput.value = '';
            avatarSel.classList.remove('show');
        });
    }

    if (delBtn) {
        delBtn.addEventListener('click', async function () {
            if (!await confirmSwal(I18N.removeAvatar, { isDelete: true })) return;
            fetch(@json(route('profile.avatar.delete')), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' }
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (d.success) location.reload();
            });
        });
    }

    // Morning check-in toggle
    var mcToggle = document.getElementById('morning_checkin_enabled');
    var mcPanel = document.getElementById('morning-checkin-fields');
    function syncMorningPanel() {
        if (!mcToggle || !mcPanel) return;
        mcPanel.classList.toggle('is-off', !mcToggle.checked);
    }
    if (mcToggle) {
        mcToggle.addEventListener('change', syncMorningPanel);
        syncMorningPanel();
    }
});
</script>
@endpush
