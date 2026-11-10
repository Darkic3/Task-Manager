@extends('layouts.app')

@section('title', __('New journal entry'))

@section('content')
<div class="container py-4">
    <h1 class="h3 mb-3">{{ __('New journal entry') }}</h1>

    @if (! empty($prompt))
        <div class="alert alert-light border">
            <strong>{{ __('Prompt of the day:') }}</strong> {{ $prompt }}
        </div>
    @endif

    <div class="mb-3">
        <label class="form-label">{{ __('Template') }}</label>
        <select id="templateSelect" class="form-select">
            <option value="">{{ __('— Blank —') }}</option>
            <option value="morning">{{ __('Morning pages') }}</option>
            <option value="gratitude">{{ __('Gratitude') }}</option>
            <option value="reflection">{{ __('Reflection') }}</option>
            <option value="weekly">{{ __('Weekly review') }}</option>
        </select>
    </div>

    @include('journal._form', [
        'entry' => $entry,
        'books' => $books,
        'tags' => $tags,
        'moods' => $moods,
        'selectedTags' => $selectedTags,
        'selectedMoods' => $selectedMoods,
    ])
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('templateSelect');
    if (!sel) return;
    const templates = {
        morning: { title: '{{ __('Morning pages') }}', content: '{{ __("I woke up feeling...\n\nToday I intend to...\n\nOne thing I'm looking forward to...") }}' },
        gratitude: { title: '{{ __('Gratitude') }}', content: '{{ __("Today I am grateful for:\n1.\n2.\n3.\n\nWhy these matter to me...") }}' },
        reflection: { title: '{{ __('Reflection') }}', content: '{{ __("What went well today:\n\nWhat could have gone better:\n\nWhat I learned:\n\nTomorrow I will...") }}' },
        weekly: { title: '{{ __('Weekly review') }}', content: '{{ __("Highlights of this week:\n\nChallenges:\n\nLessons:\n\nFocus for next week:") }}' },
    };
    sel.addEventListener('change', function () {
        const t = templates[this.value];
        if (!t) return;
        const titleInput = document.querySelector('input[name="title"]');
        const contentInput = document.querySelector('textarea[name="content"]');
        if (titleInput && !titleInput.value) titleInput.value = t.title;
        if (contentInput) contentInput.value = t.content;
    });
});
</script>
@endpush

@endsection
