@extends('layouts.app')

@section('title', __('Journal tags'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ __('Tags') }}</h1>
        <a href="{{ route('journal.tags.create') }}" class="btn btn-primary btn-sm">{{ __('New tag') }}</a>
    </div>
    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    <ul class="list-group">
        @foreach ($tags as $t)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>
                    @if ($t->color)<span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:{{ $t->color }};margin-inline-end:6px;"></span>@endif
                    {{ $t->name }}
                </span>
                <span>
                    <a href="{{ route('journal.tags.edit', $t) }}" class="btn btn-sm btn-outline-secondary">{{ __('Edit') }}</a>
                    <form method="POST" action="{{ route('journal.tags.destroy', $t) }}" style="display:inline" onsubmit="return confirm('{{ __('Delete?') }}');">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                    </form>
                </span>
            </li>
        @endforeach
    </ul>
    <div class="mt-3"><a href="{{ route('journal.index') }}" class="btn btn-link">{{ __('Back to journal') }}</a></div>
</div>
@endsection
