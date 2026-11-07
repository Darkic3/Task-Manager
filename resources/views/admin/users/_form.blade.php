@php
    /** @var \App\Models\User|null $user */
    $user = $user ?? null;
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="username" class="form-label">{{ __('Username') }}</label>
        <input type="text" id="username" name="username" dir="ltr" required maxlength="30"
               class="form-control @error('username') is-invalid @enderror"
               value="{{ old('username', $user?->username) }}">
        <div class="form-text">{{ __('Letters, numbers, dash and underscore only.') }}</div>
        @error('username')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="col-md-6">
        <label for="name" class="form-label">{{ __('Display name') }}</label>
        <input type="text" id="name" name="name" required maxlength="255"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $user?->name) }}">
        @error('name')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">{{ __('Email') }} <span class="text-secondary small">({{ __('optional') }})</span></label>
        <input type="email" id="email" name="email" dir="ltr"
               class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $user?->email) }}">
        @error('email')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="col-md-6">
        <label for="locale" class="form-label">{{ __('Language') }}</label>
        <select id="locale" name="locale" class="form-select @error('locale') is-invalid @enderror">
            <option value="en" {{ old('locale', $user?->locale ?? app()->getLocale()) === 'en' ? 'selected' : '' }}>{{ __('English') }}</option>
            <option value="fa" {{ old('locale', $user?->locale ?? app()->getLocale()) === 'fa' ? 'selected' : '' }}>{{ __('فارسی') }}</option>
        </select>
        @error('locale')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
    </div>

    @if (! $user)
        <div class="col-md-6">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <input type="password" id="password" name="password" required minlength="10" autocomplete="new-password"
                   class="form-control @error('password') is-invalid @enderror">
            <div class="form-text">{{ __('At least 10 characters. Share it with the user over a secure channel.') }}</div>
            @error('password')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label d-block">{{ __('Role') }}</label>
            <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" name="is_admin" value="1" id="is_admin"
                       {{ old('is_admin') ? 'checked' : '' }}>
                <label class="form-check-label" for="is_admin">{{ __('Administrator — full access to every module') }}</label>
            </div>
        </div>
    @else
        <div class="col-md-6">
            <label for="phone" class="form-label">{{ __('Phone') }}</label>
            <input type="text" id="phone" name="phone" dir="ltr" maxlength="20"
                   class="form-control @error('phone') is-invalid @enderror"
                   value="{{ old('phone', $user->phone) }}">
            @error('phone')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="col-md-6">
            <label for="location" class="form-label">{{ __('Location') }}</label>
            <input type="text" id="location" name="location" maxlength="255"
                   class="form-control @error('location') is-invalid @enderror"
                   value="{{ old('location', $user->location) }}">
            @error('location')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="col-md-6">
            <label for="website" class="form-label">{{ __('Website') }}</label>
            <input type="url" id="website" name="website" dir="ltr" maxlength="255"
                   class="form-control @error('website') is-invalid @enderror"
                   value="{{ old('website', $user->website) }}">
            @error('website')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="col-12">
            <label for="bio" class="form-label">{{ __('Bio') }}</label>
            <textarea id="bio" name="bio" rows="3" maxlength="500"
                      class="form-control @error('bio') is-invalid @enderror">{{ old('bio', $user->bio) }}</textarea>
            @error('bio')<p class="text-danger small mb-0 mt-1">{{ $message }}</p>@enderror
        </div>
    @endif
</div>
