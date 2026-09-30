@extends('layouts.app')

@section('title', __('Edit note'))

@include('notes._styles')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde@2.18.0/dist/easymde.min.css">
@endpush

@section('content')
<div class="d-flex align-items-center gap-3 mb-3">
    <span class="nt-head-ico" style="width:42px;height:42px;border-radius:13px;display:grid;place-items:center;font-size:1.15rem;color:#fff;background:linear-gradient(135deg,#7c3aed,#5b21b6);">
        <i class="bi bi-pencil-square"></i>
    </span>
    <div>
        <h1 class="mt-0 mb-0" style="font-size:1.2rem;font-weight:800;">{{ __('Edit note') }}</h1>
        <p class="mb-0 mt-1" style="font-size:.78rem;color:var(--gray-500);font-weight:600;">
            {{ app_num($note->revisions()->count()) }} {{ __('saved versions') }}
        </p>
    </div>
    <a href="{{ route('notes.show', $note) }}" class="btn btn-outline ms-auto">
        <i class="bi bi-eye"></i>{{ __('Reader view') }}
    </a>
</div>

@if ($errors->any())
    <div class="alert alert-danger mb-3">
        <strong>{{ __('Please fix the following:') }}</strong>
        <ul class="mb-0 mt-1 ps-3">
            @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="panel">
    <div class="card-body">
        @include('notes._editor', [
            'note' => $note,
            'notebooks' => $notebooks,
            'labels' => $labels,
            'selectedLabels' => $selectedLabels,
            'selectedFiles' => $selectedFiles,
            'kindMeta' => $kindMeta,
            'templates' => $templates ?? [],
            'action' => route('notes.update', $note),
        ])
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/easymde@2.18.0/dist/easymde.min.js"></script>
@endpush