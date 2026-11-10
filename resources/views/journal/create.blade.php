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

    @include('journal._form', [
        'entry' => $entry,
        'books' => $books,
        'tags' => $tags,
        'moods' => $moods,
        'selectedTags' => $selectedTags,
        'selectedMoods' => $selectedMoods,
    ])
</div>
@endsection
