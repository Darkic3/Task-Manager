{{-- Shared law form (create + edit) --}}
<div class="cu-section">
    <div class="cu-section-header">
        <span class="cu-section-icon purple"><i class="bi bi-shield-check"></i></span>
        <span class="cu-section-title">{{ __('Law') }}</span>
        <span class="cu-section-sub">{{ __('Title, coefficient and time window') }}</span>
    </div>
    <div class="cu-section-body">
        <div class="cu-field">
            <label for="title" class="cu-label">{{ __('Title') }} <span style="color:#dc2626;">*</span></label>
            <input type="text" name="title" id="title" maxlength="255" required
                   class="cu-input {{ $errors->has('title') ? 'is-invalid' : '' }}"
                   value="{{ old('title', $law->title) }}" placeholder="{{ __('e.g. No phone after 11pm') }}">
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="cu-field">
            <label for="description" class="cu-label">{{ __('Description') }} <span style="font-weight:400;text-transform:none;letter-spacing:0;">({{ __('Optional') }})</span></label>
            <textarea name="description" id="description" rows="3" maxlength="2000"
                      class="cu-input {{ $errors->has('description') ? 'is-invalid' : '' }}"
                      placeholder="{{ __('Why does this law matter to you?') }}">{{ old('description', $law->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="cu-field-row">
            <div class="cu-field" style="margin-bottom:0;">
                <label for="coefficient" class="cu-label">{{ __('Coefficient') }} <span style="color:#dc2626;">*</span></label>
                <input type="number" name="coefficient" id="coefficient" required min="1" max="10" step="1"
                       class="cu-input {{ $errors->has('coefficient') ? 'is-invalid' : '' }}"
                       value="{{ old('coefficient', $law->coefficient ?? 1) }}">
                <div style="font-size:11px;color:#8a8f98;margin-top:4px;">{{ __('1 = light · 10 = life-changing. Every event scores ± coefficient.') }}</div>
                @error('coefficient')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="cu-field" style="margin-bottom:0;">
                <label for="color" class="cu-label">{{ __('Color') }}</label>
                <input type="color" name="color" id="color" value="{{ old('color', $law->color ?: '#7c3aed') }}"
                       class="cu-input {{ $errors->has('color') ? 'is-invalid' : '' }}" style="height:38px;padding:4px 8px;">
                @error('color')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="cu-section">
    <div class="cu-section-header">
        <span class="cu-section-icon blue"><i class="bi bi-calendar-range"></i></span>
        <span class="cu-section-title">{{ __('Time window') }}</span>
        <span class="cu-section-sub">{{ __('Expired laws never renew') }}</span>
    </div>
    <div class="cu-section-body">
        <div class="cu-field-row">
            <div class="cu-field" style="margin-bottom:0;">
                <label for="starts_on" class="cu-label">{{ __('Starts on') }} <span style="color:#dc2626;">*</span></label>
                <input type="date" name="starts_on" id="starts_on" required
                       class="cu-input {{ $errors->has('starts_on') ? 'is-invalid' : '' }}"
                       value="{{ old('starts_on', $law->starts_on ? \Carbon\Carbon::parse($law->starts_on)->toDateString() : today()->toDateString()) }}">
                @error('starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="cu-field" style="margin-bottom:0;">
                <label for="ends_on" class="cu-label">{{ __('Ends on') }} <span style="font-weight:400;text-transform:none;letter-spacing:0;">({{ __('empty = ongoing') }})</span></label>
                <input type="date" name="ends_on" id="ends_on"
                       class="cu-input {{ $errors->has('ends_on') ? 'is-invalid' : '' }}"
                       value="{{ old('ends_on', $law->ends_on ? \Carbon\Carbon::parse($law->ends_on)->toDateString() : '') }}">
                @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="cu-section">
    <div class="cu-section-header">
        <span class="cu-section-icon green"><i class="bi bi-palette"></i></span>
        <span class="cu-section-title">{{ __('Icon') }}</span>
        <span class="cu-section-sub">{{ __('Optional') }}</span>
    </div>
    <div class="cu-section-body">
        @php $icons = ['bi-shield-check','bi-lightning-charge','bi-book','bi-heart-pulse','bi-moon','bi-phone','bi-cash-coin','bi-briefcase','bi-people','bi-trophy','bi-flag','bi-star']; @endphp
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @foreach($icons as $ic)
            <label style="cursor:pointer;">
                <input type="radio" name="icon" value="{{ $ic }}" {{ old('icon', $law->icon ?: 'bi-shield-check') === $ic ? 'checked' : '' }} style="display:none;">
                <span class="cu-icon-opt" data-icon="{{ $ic }}" style="width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;border:2px solid #e5e7eb;background:white;color:#6b6f78;"><i class="bi {{ $ic }}"></i></span>
            </label>
            @endforeach
        </div>
        @error('icon')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<script>
document.querySelectorAll('.cu-icon-opt').forEach(el => {
    const paint = () => {
        const checked = document.querySelector('input[name="icon"]:checked');
        document.querySelectorAll('.cu-icon-opt').forEach(o => {
            const on = checked && o.dataset.icon === checked.value;
            o.style.borderColor = on ? '#7c3aed' : '#e5e7eb';
            o.style.background = on ? '#f7f5ff' : 'white';
            o.style.color = on ? '#7c3aed' : '#6b6f78';
        });
    };
    el.parentElement.querySelector('input').addEventListener('change', paint);
    paint();
});
</script>
