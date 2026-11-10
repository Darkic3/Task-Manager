@extends('layouts.app')

@section('title', __('Revisions'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <div class="mb-3">
        <a href="{{ route('journal.show', $entry) }}" class="btn btn-link">&larr; {{ __('Back') }}</a>
    </div>
    <h1 class="h3 mb-3">{{ __('Revision history') }}</h1>
    @if ($revisions->isEmpty())
        <div class="alert alert-info">{{ __('No revisions yet.') }}</div>
    @else
        <ul class="list-group">
            @foreach ($revisions as $rev)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold">{{ $rev->title ?: __('Untitled') }}</div>
                        <div class="text-muted small">{{ $rev->edited_at?->format('Y-m-d H:i') }} · {{ str_word_count((string)$rev->content) }} {{ __('words') }}</div>
                    </div>
                    <form method="POST" action="{{ route('journal.revisions.restore', [$entry, $rev]) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary">{{ __('Restore') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
