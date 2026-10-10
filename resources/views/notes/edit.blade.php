@extends('layouts.app')

@section('title', __('Edit note'))

@include('notes._styles')

@section('content')
<div class="nt-page-head">
    <a href="{{ route('notes.show', $note) }}" class="nt-page-back" title="{{ __('Back to note') }}">
        <i class="bi bi-arrow-right"></i>
    </a>
    <span class="nt-head-ico"><i class="bi bi-pencil-square"></i></span>
    <div>
        <h1 class="nt-head-title">{{ $note->title }}</h1>
        <p class="nt-head-sub">
            {{ __('Last saved') }} {{ $note->updated_at->diffForHumans() }} ·
            {{ app_num($note->revisions()->count()) }} {{ __('versions') }}
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
@endsection