@extends('layouts.app')

@section('title', __('Edit mood'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <h1 class="h3 mb-3">{{ __('Edit mood') }}</h1>
    <form method="POST" action="{{ route('journal.moods.update', $journalMood) }}">
        @csrf @method('PATCH')
        <div class="mb-3">
            <label class="form-label">{{ __('Name') }}</label>
            <input type="text" name="name" class="form-control" required maxlength="80" value="{{ $journalMood->name }}">
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Category') }}</label>
            <select name="category" class="form-select" required>
                @foreach (['positive','negative','neutral'] as $c)
                    <option value="{{ $c }}" {{ $journalMood->category === $c ? 'selected' : '' }}>{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Color') }}</label>
            <input type="text" name="color" class="form-control" value="{{ $journalMood->color }}">
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Icon') }}</label>
            <input type="text" name="icon" class="form-control" value="{{ $journalMood->icon }}">
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Sort order') }}</label>
            <input type="number" name="sort_order" class="form-control" value="{{ $journalMood->sort_order }}" min="0">
        </div>
        <button class="btn btn-primary">{{ __('Update') }}</button>
        <a href="{{ route('journal.moods.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </form>
</div>
@endsection
