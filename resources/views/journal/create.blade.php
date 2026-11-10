@extends('layouts.app')

@section('title', __('New journal entry'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <div class="jn-page">
        <div class="jn-head">
            <div>
                <h1>{{ __('New journal entry') }}</h1>
                <div class="jn-sub">{{ __('Write it down. Markdown supported.') }}</div>
            </div>
        </div>

        @if (! empty($prompt))
            <div class="jn-prompt">
                <i class="bi bi-lightbulb"></i>
                <div>
                    <strong>{{ __('Prompt of the day:') }}</strong> {{ $prompt }}
                </div>
            </div>
        @endif

        @include('journal._form', [
            'entry' => $entry,
            'books' => $books,
            'tags' => $tags,
            'moods' => $moods,
            'selectedTags' => $selectedTags,
            'selectedMoods' => $selectedMoods,
        ])
    </div>
</div>
@endsection
