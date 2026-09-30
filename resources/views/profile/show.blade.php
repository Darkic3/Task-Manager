@extends('layouts.app')
@section('title', __('My Profile'))
@push('styles')
@include('profile._style')
@endpush


@section('content')
@php
    $isFa = app()->getLocale() === 'fa';

    $initials = name_initials($user->name, 2);

    $profileFields = [
        (bool) $user->avatar,
        (bool) $user->phone,
        (bool) $user->location,
        (bool) $user->website,
        (bool) $user->bio,
    ];
    $completeness = (int) round(count(array_filter($profileFields)) / count($profileFields) * 100);

    $memberSince = $isFa
        ? app_num(app_date($user->created_at, 'Y/m/d'))
        : $user->created_at->format('M Y');
    $memberSinceFull = $isFa
        ? app_num(app_date($user->created_at, 'Y/m/d'))
        : $user->created_at->format('F d, Y');

    $site = fn ($url) => Str::limit(preg_replace('#^https?://#i', '', (string) $url), 30);

    $wakeRoutine = $user->wakeRoutine;
@endphp

<div class="pf-page">

    {{-- Hero --}}
    <section class="pf-hero">
        <span class="pf-orb pf-orb-1" aria-hidden="true"></span>
        <span class="pf-orb pf-orb-2" aria-hidden="true"></span>

        <div class="pf-hero-main">
            <div class="pf-avatar-ring">
                @if($user->avatar)
                    <img src="{{ Storage::url($user->avatar) }}" alt="{{ $user->name }}" class="pf-avatar-img">
                @else
                    <div class="pf-avatar-init" aria-hidden="true">{{ $initials }}</div>
                @endif
                <span class="pf-avatar-dot" aria-hidden="true"></span>
            </div>

            <div class="pf-hero-id">
                <p class="pf-eyebrow"><i class="bi bi-shield-check"></i> {{ __('Account') }}</p>
                <h1 class="pf-name">{{ $user->name }}</h1>
                <p class="pf-mail"><i class="bi bi-envelope"></i> {{ $user->email }}</p>

                <ul class="pf-chips">
                    <li class="pf-chip @if(!$user->location) is-empty @endif">
                        <i class="bi bi-geo-alt"></i>
                        <span>{{ $user->location ?: __('Not set') }}</span>
                    </li>
                    <li class="pf-chip @if(!$user->phone) is-empty @endif">
                        <i class="bi bi-telephone"></i>
                        <span dir="ltr">{{ $user->phone ?: __('Not set') }}</span>
                    </li>
                    @if($user->website)
                        <li>
                            <a href="{{ $user->website }}" target="_blank" rel="noopener noreferrer" class="pf-chip">
                                <i class="bi bi-globe2"></i>
                                <span dir="ltr">{{ $site($user->website) }}</span>
                            </a>
                        </li>
                    @endif
                    <li class="pf-chip">
                        <i class="bi bi-calendar3"></i>
                        <span>{{ __('Member Since') }} {{ $memberSince }}</span>
                    </li>
                </ul>
            </div>

            <div class="pf-hero-actions">
                <a href="{{ route('profile.edit') }}" class="pf-btn pf-btn-glass">
                    <i class="bi bi-pencil-square"></i> {{ __('Edit Profile') }}
                </a>
            </div>
        </div>

        <div class="pf-meter">
            <div class="pf-meter-head">
                <span class="pf-meter-label"><i class="bi bi-speedometer2"></i> {{ __('Profile completeness') }}</span>
                <span class="pf-meter-value pf-num">{{ app_num($completeness) }}%</span>
            </div>
            <div class="pf-meter-track" role="progressbar" aria-valuenow="{{ $completeness }}" aria-valuemin="0"
                 aria-valuemax="100" aria-label="{{ __('Profile completeness') }}">
                <div class="pf-meter-fill" style="width: {{ max($completeness, 4) }}%"></div>
            </div>
        </div>
    </section>

@include('profile._tabs')


    {{-- Stats --}}
    <section class="pf-stats" aria-label="{{ __('Activity Overview') }}">
        <a href="{{ route('tasks.index') }}" class="pf-stat pf-stat-c violet">
            <span class="pf-stat-ico"><i class="bi bi-check2-square"></i></span>
            <span class="pf-stat-num pf-num">{{ app_num($user->tasks_count ?? 0) }}</span>
            <span class="pf-stat-lbl">{{ __('Tasks') }}</span>
        </a>
        <a href="{{ route('tasks.index') }}" class="pf-stat pf-stat-c green">
            <span class="pf-stat-ico"><i class="bi bi-check2-all"></i></span>
            <span class="pf-stat-num pf-num">{{ app_num($user->completed_tasks_count ?? 0) }}</span>
            <span class="pf-stat-lbl">{{ __('Completed') }}</span>
        </a>
        <a href="{{ route('projects.index') }}" class="pf-stat pf-stat-c blue">
            <span class="pf-stat-ico"><i class="bi bi-folder2-open"></i></span>
            <span class="pf-stat-num pf-num">{{ app_num($user->projects_count ?? 0) }}</span>
            <span class="pf-stat-lbl">{{ __('Projects') }}</span>
        </a>
        <a href="{{ route('routines.index') }}" class="pf-stat pf-stat-c amber">
            <span class="pf-stat-ico"><i class="bi bi-arrow-repeat"></i></span>
            <span class="pf-stat-num pf-num">{{ app_num($user->routines_count ?? 0) }}</span>
            <span class="pf-stat-lbl">{{ __('Routines') }}</span>
        </a>
        <a href="{{ route('notes.index') }}" class="pf-stat pf-stat-c sky">
            <span class="pf-stat-ico"><i class="bi bi-journal-text"></i></span>
            <span class="pf-stat-num pf-num">{{ app_num($user->notes_count ?? 0) }}</span>
            <span class="pf-stat-lbl">{{ __('Notes') }}</span>
        </a>
        <a href="{{ route('files.index') }}" class="pf-stat pf-stat-c rose">
            <span class="pf-stat-ico"><i class="bi bi-paperclip"></i></span>
            <span class="pf-stat-num pf-num">{{ app_num($user->files_count ?? 0) }}</span>
            <span class="pf-stat-lbl">{{ __('Files') }}</span>
        </a>
    </section>

    {{-- Columns --}}
    <div class="pf-grid">
        <div class="pf-col">

            {{-- Personal information --}}
            <section class="pf-card" style="animation-delay:.16s;">
                <header class="pf-card-hd">
                    <span class="pf-card-ico blue"><i class="bi bi-person-vcard"></i></span>
                    <div>
                        <h2 class="pf-card-title">{{ __('Personal Information') }}</h2>
                        <p class="pf-card-sub">{{ __('Personal details') }}</p>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="pf-card-link">{{ __('Edit') }}</a>
                </header>
                <div class="pf-card-bd tight">
                    <dl class="pf-rows">
                        <div class="pf-row">
                            <span class="pf-row-ico"><i class="bi bi-person"></i></span>
                            <div class="pf-row-txt">
                                <dt class="pf-row-lbl">{{ __('Name') }}</dt>
                                <dd class="pf-row-val">{{ $user->name }}</dd>
                            </div>
                        </div>
                        <div class="pf-row">
                            <span class="pf-row-ico"><i class="bi bi-envelope"></i></span>
                            <div class="pf-row-txt">
                                <dt class="pf-row-lbl">{{ __('Email') }}</dt>
                                <dd class="pf-row-val" dir="ltr">{{ $user->email }}</dd>
                            </div>
                        </div>
                        <div class="pf-row">
                            <span class="pf-row-ico @if(!$user->phone) muted @endif"><i class="bi bi-telephone"></i></span>
                            <div class="pf-row-txt">
                                <dt class="pf-row-lbl">{{ __('Phone') }}</dt>
                                <dd class="pf-row-val @if(!$user->phone) is-empty @endif" dir="ltr">
                                    {{ $user->phone ?: __('Not set') }}
                                </dd>
                            </div>
                        </div>
                        <div class="pf-row">
                            <span class="pf-row-ico @if(!$user->location) muted @endif"><i class="bi bi-geo-alt"></i></span>
                            <div class="pf-row-txt">
                                <dt class="pf-row-lbl">{{ __('Location') }}</dt>
                                <dd class="pf-row-val @if(!$user->location) is-empty @endif">
                                    {{ $user->location ?: __('Not set') }}
                                </dd>
                            </div>
                        </div>
                        <div class="pf-row">
                            <span class="pf-row-ico @if(!$user->website) muted @endif"><i class="bi bi-globe2"></i></span>
                            <div class="pf-row-txt">
                                <dt class="pf-row-lbl">{{ __('Website') }}</dt>
                                <dd class="pf-row-val @if(!$user->website) is-empty @endif" dir="ltr">
                                    @if($user->website)
                                        <a href="{{ $user->website }}" target="_blank" rel="noopener noreferrer">
                                            {{ $site($user->website) }}
                                            <i class="bi bi-box-arrow-up-right" style="font-size:.75em;"></i>
                                        </a>
                                    @else
                                        {{ __('Not set') }}
                                    @endif
                                </dd>
                            </div>
                        </div>
                        <div class="pf-row">
                            <span class="pf-row-ico"><i class="bi bi-calendar3"></i></span>
                            <div class="pf-row-txt">
                                <dt class="pf-row-lbl">{{ __('Member Since') }}</dt>
                                <dd class="pf-row-val">{{ $memberSinceFull }}</dd>
                            </div>
                        </div>
                    </dl>
                </div>
            </section>

            {{-- Bio --}}
            <section class="pf-card" style="animation-delay:.22s;">
                <header class="pf-card-hd">
                    <span class="pf-card-ico violet"><i class="bi bi-chat-quote"></i></span>
                    <div>
                        <h2 class="pf-card-title">{{ __('Bio') }}</h2>
                        <p class="pf-card-sub">{{ __('About you') }}</p>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="pf-card-link">{{ __('Edit') }}</a>
                </header>
                <div class="pf-card-bd">
                    @if($user->bio)
                        <p class="pf-bio pf-bio-quote">{{ $user->bio }}</p>
                    @else
                        <div class="pf-empty">
                            <i class="bi bi-pencil-square"></i>
                            <div>
                                <strong>{{ __('No bio yet') }}</strong>
                                {{ __('Add a bio to tell others about yourself') }}
                                <div>
                                    <a href="{{ route('profile.edit') }}" class="pf-btn pf-btn-solid">
                                        <i class="bi bi-plus-lg"></i> {{ __('Add it now') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Security --}}
            <section class="pf-card" style="animation-delay:.28s;">
                <header class="pf-card-hd">
                    <span class="pf-card-ico green"><i class="bi bi-shield-lock"></i></span>
                    <div>
                        <h2 class="pf-card-title">{{ __('Security & password') }}</h2>
                        <p class="pf-card-sub">{{ __('Keep your account protected') }}</p>
                    </div>
                </header>
                <div class="pf-card-bd">
                    <div class="pf-security">
                        <div class="pf-security-txt">
                            <strong>{{ __('Password') }}</strong>
                            <span>{{ __('Last updated') }} {{ app_diff_for_humans($user->updated_at) }}</span>
                        </div>
                        <a href="{{ route('profile.password') }}" class="pf-btn pf-btn-solid">
                            <i class="bi bi-key"></i> {{ __('Change Password') }}
                        </a>
                    </div>
                </div>
            </section>
        </div>

        {{-- Side column --}}
        <aside class="pf-col pf-col-side">
            <section class="pf-card" style="animation-delay:.2s;">
                <header class="pf-card-hd">
                    <span class="pf-card-ico amber"><i class="bi bi-sliders"></i></span>
                    <div>
                        <h2 class="pf-card-title">{{ __('Preferences') }}</h2>
                        <p class="pf-card-sub">{{ __('Language & routine') }}</p>
                    </div>
                </header>
                <div class="pf-card-bd tight">
                    <dl class="pf-rows">
                        <div class="pf-row">
                            <span class="pf-row-ico"><i class="bi bi-translate"></i></span>
                            <div class="pf-row-txt">
                                <dt class="pf-row-lbl">{{ __('Language') }}</dt>
                                <dd class="pf-row-val">
                                    {{ ($user->locale ?? app()->getLocale()) === 'fa' ? __('🇮🇷 Persian') : __('🇬🇧 English') }}
                                </dd>
                            </div>
                        </div>
                        <div class="pf-row">
                            <span class="pf-row-ico"><i class="bi bi-sunrise"></i></span>
                            <div class="pf-row-txt">
                                <dt class="pf-row-lbl">{{ __('Morning Check-in') }}</dt>
                                <dd class="pf-row-val">
                                    @if($user->morning_checkin_enabled && $wakeRoutine)
                                        {{ $wakeRoutine->title }}
                                        <small>{{ __('Window start') }} {{ substr((string) $user->morning_window_start, 0, 5) }} - {{ substr((string) $user->morning_window_end, 0, 5) }}</small>
                                    @else
                                        <span class="is-empty">{{ __('Not set') }}</span>
                                    @endif
                                </dd>
                            </div>
                            <span class="pf-row-badge {{ $user->morning_checkin_enabled ? 'on' : 'off' }}">
                                {{ $user->morning_checkin_enabled ? __('Enabled') : __('Disabled') }}
                            </span>
                        </div>
                    </dl>
                    <div style="padding:12px 12px 2px;">
                        <a href="{{ route('profile.edit') }}" class="pf-btn pf-btn-soft" style="width:100%;">
                            <i class="bi bi-pencil-square"></i> {{ __('Update Profile') }}
                        </a>
                    </div>
                </div>
            </section>

            <section class="pf-card" style="animation-delay:.26s;">
                <header class="pf-card-hd">
                    <span class="pf-card-ico rose"><i class="bi bi-send"></i></span>
                    <div>
                        <h2 class="pf-card-title">{{ __('Contact & links') }}</h2>
                        <p class="pf-card-sub">{{ __('Email, phone and more') }}</p>
                    </div>
                </header>
                <div class="pf-card-bd tight">
                    <dl class="pf-rows">
                        <div class="pf-row">
                            <span class="pf-row-ico"><i class="bi bi-envelope"></i></span>
                            <div class="pf-row-txt">
                                <dt class="pf-row-lbl">{{ __('Email') }}</dt>
                                <dd class="pf-row-val pf-num" dir="ltr" style="font-size:.85rem;">{{ $user->email }}</dd>
                            </div>
                        </div>
                        <div class="pf-row">
                            <span class="pf-row-ico @if(!$user->phone) muted @endif"><i class="bi bi-telephone"></i></span>
                            <div class="pf-row-txt">
                                <dt class="pf-row-lbl">{{ __('Phone') }}</dt>
                                <dd class="pf-row-val @if(!$user->phone) is-empty @endif" dir="ltr" style="font-size:.85rem;">
                                    {{ $user->phone ?: __('Not set') }}
                                </dd>
                            </div>
                        </div>
                        <div class="pf-row">
                            <span class="pf-row-ico @if(!$user->website) muted @endif"><i class="bi bi-globe2"></i></span>
                            <div class="pf-row-txt">
                                <dt class="pf-row-lbl">{{ __('Website') }}</dt>
                                <dd class="pf-row-val @if(!$user->website) is-empty @endif" dir="ltr" style="font-size:.85rem;">
                                    @if($user->website)
                                        <a href="{{ $user->website }}" target="_blank" rel="noopener noreferrer">{{ $site($user->website) }}</a>
                                    @else
                                        {{ __('Not set') }}
                                    @endif
                                </dd>
                            </div>
                        </div>
                        <div class="pf-row">
                            <span class="pf-row-ico @if(!$user->location) muted @endif"><i class="bi bi-geo-alt"></i></span>
                            <div class="pf-row-txt">
                                <dt class="pf-row-lbl">{{ __('Location') }}</dt>
                                <dd class="pf-row-val @if(!$user->location) is-empty @endif" style="font-size:.85rem;">
                                    {{ $user->location ?: __('Not set') }}
                                </dd>
                            </div>
                        </div>
                    </dl>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
