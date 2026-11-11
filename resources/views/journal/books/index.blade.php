@extends('layouts.app')

@section('title', __('Journal books'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <div class="jn-page">
        <div class="jn-head">
            <div>
                <h1>{{ __('Books') }}</h1>
                <div class="jn-sub">{{ __('Organize your entries into books') }}</div>
            </div>
            <a href="{{ route('journal.books.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> {{ __('New book') }}
            </a>
        </div>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        @if ($books->isEmpty())
            <div class="jn-card text-center text-muted">{{ __('No books yet.') }}</div>
        @else
            <div class="row g-3">
                @foreach ($books as $b)
                    <div class="col-md-6 col-lg-4">
                        <div class="jn-card h-100">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div style="width:48px;height:48px;border-radius:12px;background:{{ $b->color ?: '#7c3aed' }};display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;">
                                    <i class="bi {{ $b->icon ?: 'bi-journal' }}"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">{{ $b->name }}</div>
                                    @if ($b->is_default)<span class="badge bg-info">{{ __('default') }}</span>@endif
                                    @if ($b->description)<div class="text-muted small">{{ \Illuminate\Support\Str::limit($b->description, 60) }}</div>@endif
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <a href="{{ route('journal.books.edit', $b) }}" class="btn btn-outline-secondary btn-sm">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('journal.books.destroy', $b) }}" style="display:inline" onsubmit="return confirm('{{ __('Delete?') }}');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-3"><a href="{{ route('journal.index') }}" class="btn btn-link">{{ __('Back to journal') }}</a></div>
    </div>
</div>
@endsection
