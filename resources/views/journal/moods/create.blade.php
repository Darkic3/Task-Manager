@extends('layouts.app')

@section('title', __('New mood'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <h1 class="h3 mb-3">{{ __('New mood') }}</h1>
    <form method="POST" action="{{ route('journal.moods.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">{{ __('Name') }}</label>
            <input type="text" name="name" class="form-control" required maxlength="80">
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Category') }}</label>
            <select name="category" class="form-select" required>
                <option value="positive">positive</option>
                <option value="negative">negative</option>
                <option value="neutral">neutral</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Color') }}</label>
            <input type="text" name="color" class="form-control" placeholder="#7c3aed">
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Icon') }}</label>
            <input type="text" name="icon" class="form-control" placeholder="bi-emoji-smile">
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Sort order') }}</label>
            <input type="number" name="sort_order" class="form-control" value="0" min="0">
        </div>
        <button class="btn btn-primary">{{ __('Create') }}</button>
        <a href="{{ route('journal.moods.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </form>
</div>
@endsection
