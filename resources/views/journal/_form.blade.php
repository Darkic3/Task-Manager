@php
    $isEdit = $entry->exists;
    $action = $isEdit ? route('journal.update', $entry) : route('journal.store');
@endphp

<form method="POST" action="{{ $action }}">
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

    <div class="mb-3">
        <label class="form-label">{{ __('Title (optional)') }}</label>
        <input type="text" name="title" class="form-control" maxlength="255" value="{{ old('title', $entry->title) }}">
    </div>

    <div class="mb-3">
        <label class="form-label">{{ __('Content') }} *</label>
        <textarea name="content" class="form-control" rows="12" required>{{ old('content', $entry->content) }}</textarea>
        <div class="form-text">{{ __('Markdown supported.') }}</div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <label class="form-label">{{ __('Date') }}</label>
            <input type="date" name="entry_date" class="form-control" required value="{{ old('entry_date', $entry->entry_date?->toDateString() ?? today()->toDateString()) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">{{ __('Book') }}</label>
            <select name="book_id" class="form-select">
                <option value="">{{ __('— None —') }}</option>
                @foreach ($books as $b)
                    <option value="{{ $b->id }}" {{ (int)old('book_id', $entry->book_id) === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ __('Mood (1-10)') }}</label>
            <input type="number" name="mood_score" class="form-control" min="1" max="10" value="{{ old('mood_score', $entry->mood_score) }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ __('Energy (1-5)') }}</label>
            <input type="number" name="energy" class="form-control" min="1" max="5" value="{{ old('energy', $entry->energy) }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ __('Status') }}</label>
            <select name="status" class="form-select">
                @foreach (\App\Models\JournalEntry::STATUSES as $s)
                    <option value="{{ $s }}" {{ old('status', $entry->status) === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <label class="form-label">{{ __('Sleep hours') }}</label>
            <input type="number" step="0.25" name="sleep_hours" class="form-control" min="0" max="24" value="{{ old('sleep_hours', $entry->sleep_hours) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">{{ __('Sleep quality (1-5)') }}</label>
            <input type="number" name="sleep_quality" class="form-control" min="1" max="5" value="{{ old('sleep_quality', $entry->sleep_quality) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">{{ __('Weather') }}</label>
            <input type="text" name="weather_condition" class="form-control" maxlength="60" value="{{ old('weather_condition', $entry->weather_condition) }}" placeholder="{{ __('e.g. sunny') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">{{ __('Temp') }}</label>
            <input type="number" step="0.1" name="weather_temp" class="form-control" value="{{ old('weather_temp', $entry->weather_temp) }}">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">{{ __('Location') }}</label>
        <input type="text" name="location_name" class="form-control" maxlength="191" value="{{ old('location_name', $entry->location_name) }}">
    </div>

    <div class="mb-3">
        <label class="form-label">{{ __('Tags') }}</label>
        <div>
            @foreach ($tags as $tag)
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" id="tag_{{ $tag->id }}"
                        {{ in_array($tag->id, old('tag_ids', $selectedTags)) ? 'checked' : '' }}>
                    <label class="form-check-label" for="tag_{{ $tag->id }}">{{ $tag->name }}</label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">{{ __('Moods') }}</label>
        <div>
            @foreach ($moods as $mood)
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="mood_ids[]" value="{{ $mood->id }}" id="mood_{{ $mood->id }}"
                        {{ in_array($mood->id, old('mood_ids', $selectedMoods)) ? 'checked' : '' }}>
                    <label class="form-check-label" for="mood_{{ $mood->id }}">{{ $mood->name }}</label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mb-3">
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="is_pinned" value="1" {{ old('is_pinned', $entry->is_pinned) ? 'checked' : '' }}>
            <label class="form-check-label">{{ __('Pin') }}</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="is_favorite" value="1" {{ old('is_favorite', $entry->is_favorite) ? 'checked' : '' }}>
            <label class="form-check-label">{{ __('Favorite') }}</label>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">{{ $isEdit ? __('Update') : __('Create') }}</button>
    <a href="{{ $isEdit ? route('journal.show', $entry) : route('journal.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
</form>
