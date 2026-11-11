@extends('layouts.app')

@section('title', __('Edit book'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <div class="jn-page">
        <div class="jn-head">
            <div>
                <h1>{{ __('Edit book') }}</h1>
            </div>
            <a href="{{ route('journal.books.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
        </div>

        <form method="POST" action="{{ route('journal.books.update', $journalBook) }}">
            @csrf @method('PATCH')
            <div class="jn-card">
                <div class="jn-field">
                    <label>{{ __('Name') }}</label>
                    <input type="text" name="name" class="jn-input" required maxlength="120" value="{{ $journalBook->name }}">
                </div>
            </div>

            <div class="jn-card">
                <div class="jn-field">
                    <label>{{ __('Description') }}</label>
                    <textarea name="description" class="jn-textarea" style="min-height:100px;">{{ $journalBook->description }}</textarea>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default" {{ $journalBook->is_default ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_default">{{ __('Default') }}</label>
                </div>
            </div>

            @include('components.color-icon-picker', [
                'color' => $journalBook->color ?: '#7c3aed',
                'icon' => $journalBook->icon ?: 'bi-journal',
            ])

            <div class="jn-savebar">
                <button class="btn btn-primary">{{ __('Update') }}</button>
                <a href="{{ route('journal.books.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
