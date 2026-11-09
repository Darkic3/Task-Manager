@extends('layouts.app')

@section('title', __('Journal'))

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ __('Journal') }}</h1>
        <a href="{{ route('journal.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> {{ __('New entry') }}
        </a>
    </div>

    <form method="GET" action="{{ route('journal.index') }}" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('Search…') }}">
        </div>
        <div class="col-md-2">
            <select name="book_id" class="form-select">
                <option value="">{{ __('All books') }}</option>
                @foreach ($books as $b)
                    <option value="{{ $b->id }}" {{ (int)($filters['bookId'] ?? 0) === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="tag_id" class="form-select">
                <option value="">{{ __('All tags') }}</option>
                @foreach ($tags as $t)
                    <option value="{{ $t->id }}" {{ (int)($filters['tagId'] ?? 0) === $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control" placeholder="{{ __('From') }}">
        </div>
        <div class="col-md-2">
            <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control" placeholder="{{ __('To') }}">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-outline-secondary">{{ __('Filter') }}</button>
            <a href="{{ route('journal.index') }}" class="btn btn-link">{{ __('Reset') }}</a>
        </div>
    </form>

    @if ($entries->isEmpty())
        <div class="alert alert-info">{{ __('No journal entries yet.') }}</div>
    @else
        <div class="list-group">
            @foreach ($entries as $entry)
                <a href="{{ route('journal.show', $entry) }}" class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="fw-bold">
                                {{ $entry->title ?: __('Untitled entry') }}
                                @if ($entry->is_pinned) <i class="bi bi-pin-angle-fill text-warning"></i> @endif
                                @if ($entry->is_favorite) <i class="bi bi-star-fill text-warning"></i> @endif
                            </div>
                            <div class="text-muted small">
                                {{ $entry->entry_date?->format('Y-m-d') }}
                                @if ($entry->book) · {{ $entry->book->name }} @endif
                                @if ($entry->mood_score) · {{ __('Mood') }} {{ $entry->mood_score }}/10 @endif
                                · {{ $entry->word_count }} {{ __('words') }}
                            </div>
                            @if ($entry->tags->count())
                                <div class="mt-1">
                                    @foreach ($entry->tags as $tag)
                                        <span class="badge bg-secondary" style="background-color: {{ $tag->color ?? '#6c757d' }} !important;">{{ $tag->name }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="text-muted small">
                            {{ $entry->created_at?->diffForHumans() }}
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-3">
            {{ $entries->links() }}
        </div>
    @endif
</div>
@endsection
