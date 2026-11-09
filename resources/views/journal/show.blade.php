@extends('layouts.app')

@section('title', $entry->title ?: __('Journal entry'))

@section('content')
<div class="container py-4">
    <div class="mb-3">
        <a href="{{ route('journal.index') }}" class="btn btn-link">&larr; {{ __('Back') }}</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <article>
        <header class="mb-3">
            <h1 class="h2 mb-1">
                {{ $entry->title ?: __('Untitled entry') }}
                @if ($entry->is_pinned) <i class="bi bi-pin-angle-fill text-warning"></i> @endif
                @if ($entry->is_favorite) <i class="bi bi-star-fill text-warning"></i> @endif
            </h1>
            <div class="text-muted small">
                {{ $entry->entry_date?->format('Y-m-d') }}
                @if ($entry->book) · {{ $entry->book->name }} @endif
                @if ($entry->mood_score) · {{ __('Mood') }} {{ $entry->mood_score }}/10 @endif
                @if ($entry->energy) · {{ __('Energy') }} {{ $entry->energy }}/5 @endif
                @if ($entry->sleep_hours) · {{ __('Sleep') }} {{ $entry->sleep_hours }}h @endif
                · {{ $entry->word_count }} {{ __('words') }}
            </div>
            @if ($entry->tags->count())
                <div class="mt-2">
                    @foreach ($entry->tags as $tag)
                        <span class="badge" style="background-color: {{ $tag->color ?? '#6c757d' }};">{{ $tag->name }}</span>
                    @endforeach
                </div>
            @endif
            @if ($entry->location_name)
                <div class="text-muted small mt-1"><i class="bi bi-geo-alt"></i> {{ $entry->location_name }}</div>
            @endif
            @if ($entry->weather_condition)
                <div class="text-muted small"><i class="bi bi-cloud"></i> {{ $entry->weather_condition }} @if($entry->weather_temp)({{ $entry->weather_temp }}°)@endif</div>
            @endif
        </header>

        <div class="journal-content">
            {!! \App\Support\MarkdownRenderer::toHtml((string) $entry->content) !!}
        </div>
    </article>

    <div class="mt-4 d-flex gap-2">
        <a href="{{ route('journal.edit', $entry) }}" class="btn btn-outline-primary">{{ __('Edit') }}</a>
        <form method="POST" action="{{ route('journal.destroy', $entry) }}" onsubmit="return confirm('{{ __('Delete this entry?') }}');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">{{ __('Delete') }}</button>
        </form>
    </div>
</div>
@endsection
