@extends('layouts.app')

@section('title', __('New mood'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <div class="jn-page">
        <div class="jn-head">
            <div>
                <h1>{{ __('New mood') }}</h1>
            </div>
            <a href="{{ route('journal.moods.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
        </div>

        <form method="POST" action="{{ route('journal.moods.store') }}">
            @csrf
            <div class="jn-card">
                <div class="jn-grid">
                    <div class="jn-field">
                        <label>{{ __('Name') }}</label>
                        <input type="text" name="name" class="jn-input" required maxlength="80">
                    </div>
                    <div class="jn-field">
                        <label>{{ __('Category') }}</label>
                        <select name="category" class="jn-select" required>
                            <option value="positive">positive</option>
                            <option value="negative">negative</option>
                            <option value="neutral">neutral</option>
                        </select>
                    </div>
                    <div class="jn-field">
                        <label>{{ __('Sort order') }}</label>
                        <input type="number" name="sort_order" class="jn-input" value="0" min="0">
                    </div>
                </div>
            </div>

            @include('components.color-icon-picker', [
                'color' => '#7c3aed',
                'icon' => 'bi-emoji-smile',
                'colorFieldName' => 'color',
                'iconFieldName' => 'icon',
            ])

            <div class="jn-savebar">
                <button class="btn btn-primary">{{ __('Create') }}</button>
                <a href="{{ route('journal.moods.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
