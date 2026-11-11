@extends('layouts.app')

@section('title', __('New tag'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <div class="jn-page">
        <div class="jn-head">
            <div>
                <h1>{{ __('New tag') }}</h1>
            </div>
            <a href="{{ route('journal.tags.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
        </div>

        <form method="POST" action="{{ route('journal.tags.store') }}">
            @csrf
            <div class="jn-card">
                <div class="jn-field">
                    <label>{{ __('Name') }}</label>
                    <input type="text" name="name" class="jn-input" required maxlength="80">
                </div>
            </div>

            @include('components.color-icon-picker', [
                'color' => '#7c3aed',
                'icon' => null,
                'icons' => [],
                'colorFieldName' => 'color',
            ])

            <div class="jn-savebar">
                <button class="btn btn-primary">{{ __('Create') }}</button>
                <a href="{{ route('journal.tags.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
