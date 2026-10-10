@extends('layouts.app')

@section('title', __('New note'))

@include('notes._styles')

@section('content')
<div class="nt-page-head">
    <a href="{{ route('notes.index') }}" class="nt-page-back" title="{{ __('Back to notes') }}">
        <i class="bi bi-arrow-right"></i>
    </a>
    <span class="nt-head-ico"><i class="bi bi-journal-plus"></i></span>
    <div>
        <h1 class="nt-head-title">{{ __('New note') }}</h1>
        <p class="nt-head-sub">{{ __('Write it down. @person and #label connect it to your graph.') }}</p>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger mb-3">
        <strong>{{ __('Please fix the following:') }}</strong>
        <ul class="mb-0 mt-1 ps-3">
            @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
    </div>
@endif

@include('notes._editor', [
    'note' => $note,
    'notebooks' => $notebooks,
    'labels' => $labels,
    'selectedLabels' => $selectedLabels,
    'selectedFiles' => $selectedFiles,
    'kindMeta' => $kindMeta,
    'templates' => $templates ?? [],
    'prefillLink' => $prefillLink ?? null,
    'action' => route('notes.store'),
])
@endsection