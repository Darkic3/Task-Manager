@extends('layouts.app')

@section('title', __('Edit journal entry'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <div class="jn-page">
        <div class="jn-head">
            <div>
                <h1>{{ __('Edit journal entry') }}</h1>
                <div class="jn-sub">{{ $entry->entry_date?->format('Y-m-d') }}</div>
            </div>
            <a href="{{ route('journal.show', $entry) }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
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
</div>
@endsection
