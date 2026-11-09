@extends('layouts.app')

@section('title', __('Edit journal entry'))

@section('content')
<div class="container py-4">
    <h1 class="h3 mb-3">{{ __('Edit journal entry') }}</h1>
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
