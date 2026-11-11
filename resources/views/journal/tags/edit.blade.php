@extends('layouts.app')

@section('title', __('Edit tag'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <div class="jn-page">
        <div class="jn-head">
            <div>
                <h1>{{ __('Edit tag') }}</h1>
            </div>
            <a href="{{ route('journal.tags.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
        </div>

        <form method="POST" action="{{ route('journal.tags.update', $journalTag) }}">
            @csrf @method('PATCH')
            <div class="jn-card">
                <div class="jn-field">
                    <label>{{ __('Name') }}</label>
                    <input type="text" name="name" class="jn-input" required maxlength="80" value="{{ $journalTag->name }}">
                </div>
            </div>

            @include('components.color-icon-picker', [
                'color' => $journalTag->color ?: '#7c3aed',
                'icon' => null,
                'icons' => [],
                'colorFieldName' => 'color',
            ])

            <div class="jn-savebar">
                <button class="btn btn-primary">{{ __('Update') }}</button>
                <a href="{{ route('journal.tags.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
