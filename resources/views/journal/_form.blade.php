@php
    $isEdit = $entry->exists;
    $action = $isEdit ? route('journal.update', $entry) : route('journal.store');
@endphp

@include('journal._styles')

<form method="POST" action="{{ $action }}" id="jnForm">
    @csrf
    @if ($isEdit)
        @method('PATCH')
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="jn-card">
        <div class="jn-field">
            <label for="jnTitle">{{ __('Title (optional)') }}</label>
            <input type="text" id="jnTitle" name="title" class="jn-input" maxlength="255" value="{{ old('title', $entry->title) }}" placeholder="{{ __('Title — what is this entry about?') }}">
        </div>
    </div>

    <div class="jn-card">
        <div class="jn-field">
            <label for="jnContent">{{ __('Content') }} *</label>
            <textarea id="jnContent" name="content" class="jn-textarea" required placeholder="{{ __('Write your thoughts…') }}">{{ old('content', $entry->content) }}</textarea>
            <div class="form-text">{{ __('Markdown supported.') }}</div>
        </div>
    </div>

    <div class="jn-card">
        <h3 class="jn-card-title"><i class="bi bi-sliders"></i>{{ __('Details') }}</h3>
        <div class="jn-grid">
            <div class="jn-field">
                <label>{{ __('Date') }}</label>
                <input type="date" name="entry_date" class="jn-input" required value="{{ old('entry_date', $entry->entry_date?->toDateString() ?? today()->toDateString()) }}">
            </div>
            <div class="jn-field">
                <label>{{ __('Book') }}</label>
                <select name="book_id" class="jn-select">
                    <option value="">{{ __('— None —') }}</option>
                    @foreach ($books as $b)
                        <option value="{{ $b->id }}" {{ (int)old('book_id', $entry->book_id) === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="jn-field">
                <label>{{ __('Status') }}</label>
                <select name="status" class="jn-select">
                    @foreach (\App\Models\JournalEntry::STATUSES as $s)
                        <option value="{{ $s }}" {{ old('status', $entry->status) === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="jn-field">
                <label>{{ __('Mood (1-10)') }}</label>
                <div class="jn-score" id="jnMoodScore">
                    @for ($i = 1; $i <= 10; $i++)
                        <button type="button" data-value="{{ $i }}" class="{{ (int)old('mood_score', $entry->mood_score) === $i ? 'is-on' : '' }}">{{ $i }}</button>
                    @endfor
                </div>
                <input type="hidden" name="mood_score" id="jnMoodScoreInput" value="{{ old('mood_score', $entry->mood_score) }}">
            </div>
        </div>
    </div>

    <div class="jn-card">
        <h3 class="jn-card-title"><i class="bi bi-heart-pulse"></i>{{ __('Wellbeing') }}</h3>
        <div class="jn-grid">
            <div class="jn-field">
                <label>{{ __('Energy (1-5)') }}</label>
                <div class="jn-score" id="jnEnergy">
                    @for ($i = 1; $i <= 5; $i++)
                        <button type="button" data-value="{{ $i }}" class="{{ (int)old('energy', $entry->energy) === $i ? 'is-on' : '' }}">{{ $i }}</button>
                    @endfor
                </div>
                <input type="hidden" name="energy" id="jnEnergyInput" value="{{ old('energy', $entry->energy) }}">
            </div>
            <div class="jn-field">
                <label>{{ __('Sleep hours') }}</label>
                <input type="number" step="0.25" name="sleep_hours" class="jn-input" min="0" max="24" value="{{ old('sleep_hours', $entry->sleep_hours) }}">
            </div>
            <div class="jn-field">
                <label>{{ __('Sleep quality (1-5)') }}</label>
                <input type="number" name="sleep_quality" class="jn-input" min="1" max="5" value="{{ old('sleep_quality', $entry->sleep_quality) }}">
            </div>
            <div class="jn-field">
                <label>{{ __('Weather') }}</label>
                <input type="text" name="weather_condition" class="jn-input" maxlength="60" value="{{ old('weather_condition', $entry->weather_condition) }}" placeholder="{{ __('e.g. sunny') }}">
            </div>
            <div class="jn-field">
                <label>{{ __('Temp') }}</label>
                <input type="number" step="0.1" name="weather_temp" class="jn-input" value="{{ old('weather_temp', $entry->weather_temp) }}">
            </div>
            <div class="jn-field">
                <label>{{ __('Location') }}</label>
                <input type="text" name="location_name" class="jn-input" maxlength="191" value="{{ old('location_name', $entry->location_name) }}">
            </div>
        </div>
    </div>

    <div class="jn-card">
        <h3 class="jn-card-title"><i class="bi bi-tag"></i>{{ __('Tags') }}</h3>
        <div class="jn-chips" id="jnTags">
            @foreach ($tags as $tag)
                <label class="jn-chip {{ in_array($tag->id, old('tag_ids', $selectedTags)) ? 'is-on' : '' }}">
                    <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" hidden {{ in_array($tag->id, old('tag_ids', $selectedTags)) ? 'checked' : '' }}>
                    @if ($tag->color)<span style="width:8px;height:8px;border-radius:50%;background:{{ $tag->color }};display:inline-block;"></span>@endif
                    {{ $tag->name }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="jn-card">
        <h3 class="jn-card-title"><i class="bi bi-emoji-smile"></i>{{ __('Moods') }}</h3>
        <div class="jn-chips" id="jnMoods">
            @foreach ($moods as $mood)
                <label class="jn-chip {{ in_array($mood->id, old('mood_ids', $selectedMoods)) ? 'is-on' : '' }}">
                    <input type="checkbox" name="mood_ids[]" value="{{ $mood->id }}" hidden {{ in_array($mood->id, old('mood_ids', $selectedMoods)) ? 'checked' : '' }}>
                    @if ($mood->color)<span style="width:8px;height:8px;border-radius:50%;background:{{ $mood->color }};display:inline-block;"></span>@endif
                    {{ $mood->name }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="jn-card">
        <div class="jn-chips">
            <label class="jn-chip {{ old('is_pinned', $entry->is_pinned) ? 'is-on' : '' }}">
                <input type="checkbox" name="is_pinned" value="1" hidden {{ old('is_pinned', $entry->is_pinned) ? 'checked' : '' }}>
                <i class="bi bi-pin-angle"></i> {{ __('Pin') }}
            </label>
            <label class="jn-chip {{ old('is_favorite', $entry->is_favorite) ? 'is-on' : '' }}">
                <input type="checkbox" name="is_favorite" value="1" hidden {{ old('is_favorite', $entry->is_favorite) ? 'checked' : '' }}>
                <i class="bi bi-star"></i> {{ __('Favorite') }}
            </label>
        </div>
    </div>

    <div class="jn-savebar">
        <button type="submit" class="btn btn-primary" id="jnSave">
            <i class="bi bi-check-lg"></i> {{ $isEdit ? __('Update') : __('Create') }}
        </button>
        <a href="{{ $isEdit ? route('journal.show', $entry) : route('journal.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        <span class="jn-autosave" id="jnAutosave"></span>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // chip toggles
    document.querySelectorAll('.jn-chip').forEach(chip => {
        const cb = chip.querySelector('input[type="checkbox"]');
        if (!cb) return;
        chip.addEventListener('click', function (e) {
            if (e.target === cb) return;
            cb.checked = !cb.checked;
            chip.classList.toggle('is-on', cb.checked);
        });
    });

    // score selectors
    function wireScore(containerId, inputId) {
        const container = document.getElementById(containerId);
        const input = document.getElementById(inputId);
        if (!container || !input) return;
        container.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', function () {
                const already = this.classList.contains('is-on');
                container.querySelectorAll('button').forEach(b => b.classList.remove('is-on'));
                if (already) { input.value = ''; return; }
                this.classList.add('is-on');
                input.value = this.dataset.value;
            });
        });
    }
    wireScore('jnMoodScore', 'jnMoodScoreInput');
    wireScore('jnEnergy', 'jnEnergyInput');

    @if ($isEdit)
    // autosave
    const form = document.getElementById('jnForm');
    const url = form.getAttribute('action');
    const token = document.querySelector('meta[name="csrf-token"]')?.content || form.querySelector('input[name="_token"]')?.value;
    const indicator = document.getElementById('jnAutosave');
    let timer = null;
    function schedule() { clearTimeout(timer); timer = setTimeout(save, 4000); }
    async function save() {
        const data = {
            title: form.querySelector('input[name="title"]')?.value || null,
            content: form.querySelector('textarea[name="content"]')?.value || '',
            entry_date: form.querySelector('input[name="entry_date"]')?.value,
            status: form.querySelector('select[name="status"]')?.value || 'active',
        };
        if (indicator) indicator.textContent = '{{ __('Saving…') }}';
        try {
            const res = await fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token,'X-Requested-With':'XMLHttpRequest' },
                body: JSON.stringify(data),
            });
            if (indicator) indicator.textContent = res.ok ? '{{ __('Saved') }}' : '{{ __('Save failed') }}';
        } catch (e) {
            if (indicator) indicator.textContent = '{{ __('Save failed') }}';
        }
    }
    form.querySelectorAll('input, textarea, select').forEach(el => {
        el.addEventListener('input', schedule);
        el.addEventListener('change', schedule);
    });
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            save();
        }
    });
    @endif
});
</script>
@endpush
