@extends('layouts.app')

@section('title', __('Edit mood'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <div class="jn-page">
        <div class="jn-head">
            <div>
                <h1>{{ __('Edit mood') }}</h1>
            </div>
            <a href="{{ route('journal.moods.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
        </div>

        <form method="POST" action="{{ route('journal.moods.update', $journalMood) }}">
            @csrf @method('PATCH')
            <div class="jn-card">
                <div class="jn-grid">
                    <div class="jn-field">
                        <label>{{ __('Name') }}</label>
                        <input type="text" name="name" class="jn-input" required maxlength="80" value="{{ $journalMood->name }}">
                    </div>
                    <div class="jn-field">
                        <label>{{ __('Category') }}</label>
                        <select name="category" class="jn-select" required>
                            @foreach (['positive','negative','neutral'] as $c)
                                <option value="{{ $c }}" {{ $journalMood->category === $c ? 'selected' : '' }}>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="jn-field">
                        <label>{{ __('Sort order') }}</label>
                        <input type="number" name="sort_order" class="jn-input" value="{{ $journalMood->sort_order }}" min="0">
                    </div>
                </div>
            </div>

            @include('components.color-icon-picker', [
                'color' => $journalMood->color ?: '#7c3aed',
                'icon' => $journalMood->icon ?: 'bi-emoji-smile',
                'colorFieldName' => 'color',
                'iconFieldName' => 'icon',
            ])

            <div class="jn-savebar">
                <button class="btn btn-primary">{{ __('Update') }}</button>
                <a href="{{ route('journal.moods.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
