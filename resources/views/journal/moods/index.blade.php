@extends('layouts.app')

@section('title', __('Journal moods'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ __('Moods') }}</h1>
        <a href="{{ route('journal.moods.create') }}" class="btn btn-primary btn-sm">{{ __('New mood') }}</a>
    </div>
    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    <ul class="list-group">
        @foreach ($moods as $m)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>
                    @if ($m->color)<span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:{{ $m->color }};margin-inline-end:6px;"></span>@endif
                    {{ $m->name }}
                    <span class="badge bg-secondary ms-2">{{ $m->category }}</span>
                </span>
                <span>
                    <a href="{{ route('journal.moods.edit', $m) }}" class="btn btn-sm btn-outline-secondary">{{ __('Edit') }}</a>
                    <form method="POST" action="{{ route('journal.moods.destroy', $m) }}" style="display:inline" onsubmit="return confirm('{{ __('Delete?') }}');">
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
