@extends('layouts.app')

@section('title', __('Edit book'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <h1 class="h3 mb-3">{{ __('Edit book') }}</h1>
    <form method="POST" action="{{ route('journal.books.update', $journalBook) }}">
        @csrf @method('PATCH')
        <div class="mb-3">
            <label class="form-label">{{ __('Name') }}</label>
            <input type="text" name="name" class="form-control" required maxlength="120" value="{{ $journalBook->name }}">
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Color') }}</label>
            <input type="text" name="color" class="form-control" value="{{ $journalBook->color }}">
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Icon') }}</label>
            <input type="text" name="icon" class="form-control" value="{{ $journalBook->icon }}">
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Description') }}</label>
            <textarea name="description" class="form-control">{{ $journalBook->description }}</textarea>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default" {{ $journalBook->is_default ? 'checked' : '' }}>
            <label class="form-check-label" for="is_default">{{ __('Default') }}</label>
        </div>
        <button class="btn btn-primary">{{ __('Update') }}</button>
        <a href="{{ route('journal.books.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </form>
</div>
@endsection
