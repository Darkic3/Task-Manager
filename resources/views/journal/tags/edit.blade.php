@extends('layouts.app')

@section('title', __('Edit tag'))

@section('content')
<div class="container py-4">
    <h1 class="h3 mb-3">{{ __('Edit tag') }}</h1>
    <form method="POST" action="{{ route('journal.tags.update', $journalTag) }}">
        @csrf @method('PATCH')
        <div class="mb-3">
            <label class="form-label">{{ __('Name') }}</label>
            <input type="text" name="name" class="form-control" required maxlength="80" value="{{ $journalTag->name }}">
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Color') }}</label>
            <input type="text" name="color" class="form-control" value="{{ $journalTag->color }}">
        </div>
        <button class="btn btn-primary">{{ __('Update') }}</button>
        <a href="{{ route('journal.tags.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </form>
</div>
@endsection
