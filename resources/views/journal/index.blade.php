@extends('layouts.app')

@section('title', __('Journal'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <div class="jn-page">
        <div class="jn-head">
            <div>
                <h1>{{ __('Journal') }}</h1>
                <div class="jn-sub">{{ __('Daily diary and reflections') }}</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('journal.books.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Books') }}</a>
                <a href="{{ route('journal.tags.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Tags') }}</a>
                <a href="{{ route('journal.moods.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Moods') }}</a>
                <a href="{{ route('journal.insights') }}" class="btn btn-outline-secondary btn-sm">{{ __('Insights') }}</a>
                <a href="{{ route('journal.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> {{ __('New entry') }}
                </a>
            </div>
        </div>

        <div class="jn-card">
            <form method="GET" action="{{ route('journal.index') }}" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="q" value="{{ $filters['q'] }}" class="jn-input" placeholder="{{ __('Search…') }}">
                </div>
                <div class="col-md-2">
                    <select name="book_id" class="jn-select">
                        <option value="">{{ __('All books') }}</option>
                        @foreach ($books as $b)
                            <option value="{{ $b->id }}" {{ (int)($filters['bookId'] ?? 0) === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="tag_id" class="jn-select">
                        <option value="">{{ __('All tags') }}</option>
                        @foreach ($tags as $t)
                            <option value="{{ $t->id }}" {{ (int)($filters['tagId'] ?? 0) === $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="from" value="{{ $filters['from'] }}" class="jn-input" placeholder="{{ __('From') }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="to" value="{{ $filters['to'] }}" class="jn-input" placeholder="{{ __('To') }}">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Filter') }}</button>
                    <a href="{{ route('journal.index') }}" class="btn btn-link btn-sm">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        @if ($entries->isEmpty())
            <div class="jn-card text-center text-muted">{{ __('No journal entries yet.') }}</div>
        @else
            @foreach ($entries as $entry)
                <a href="{{ route('journal.show', $entry) }}" class="jn-card" style="text-decoration:none;color:inherit;display:block;">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div style="flex:1;">
                            <div class="fw-bold mb-1">
                                {{ $entry->title ?: __('Untitled entry') }}
                                @if ($entry->is_pinned) <i class="bi bi-pin-angle-fill text-warning"></i> @endif
                                @if ($entry->is_favorite) <i class="bi bi-star-fill text-warning"></i> @endif
                            </div>
                            <div class="text-muted small mb-2">
                                {{ $entry->entry_date?->format('Y-m-d') }}
                                @if ($entry->book) · {{ $entry->book->name }} @endif
                                @if ($entry->mood_score) · {{ __('Mood') }} {{ $entry->mood_score }}/10 @endif
                                · {{ $entry->word_count }} {{ __('words') }}
                            </div>
                            <div class="text-muted small">
                                {{ \Illuminate\Support\Str::limit(\App\Support\MarkdownRenderer::toPlainText((string) $entry->content), 140) }}
                            </div>
                            @if ($entry->tags->count())
                                <div class="jn-chips mt-2">
                                    @foreach ($entry->tags as $tag)
                                        <span class="jn-chip" style="cursor:default;font-size:.75rem;">
                                            @if ($tag->color)<span style="width:6px;height:6px;border-radius:50%;background:{{ $tag->color }};display:inline-block;"></span>@endif
                                            {{ $tag->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="text-muted small">{{ $entry->created_at?->diffForHumans() }}</div>
                    </div>
                </a>
            @endforeach
            <div class="mt-3">
                {{ $entries->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
