@extends('layouts.app')

@section('title', __('Feature management').' | '.__('TaskManager'))

@section('content')
<div class="container-fluid py-3" style="max-width:1100px;">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small fw-bold text-primary mb-1">{{ __('Administrator') }}</div>
            <h1 class="h3 fw-bold mb-2">{{ __('Feature management') }}</h1>
            <p class="text-secondary mb-0">{{ __('Publish or lock modules for regular accounts. Your administrator account can always preview every module.') }}</p>
        </div>
        <span class="badge rounded-pill text-bg-primary px-3 py-2">{{ __('Admin preview is always enabled') }}</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    <div class="row g-3">
        @foreach ($modules as $key => $module)
            @php($flag = $updates->get($key))
            <div class="col-12 col-xl-6">
                <section class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <h2 class="h5 fw-bold mb-1">{{ __($module['label']) }}</h2>
                                <p class="text-secondary small mb-3">{{ __($module['description']) }}</p>
                            </div>
                            @if ($module['published'])
                                <span class="badge text-bg-success">{{ __('Published') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Locked') }}</span>
                            @endif
                        </div>

                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pt-3 border-top">
                            <div class="small text-secondary">
                                @if ($flag?->updater)
                                    {{ __('Last changed by') }} <strong>{{ $flag->updater->username }}</strong>
                                    <span class="d-block">{{ $flag->updated_at?->diffForHumans() }}</span>
                                @else
                                    {{ __('Using the default publication setting') }}
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.features.update', $key) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="enabled" value="{{ $module['published'] ? '0' : '1' }}">
                                <button type="submit" class="btn {{ $module['published'] ? 'btn-outline-danger' : 'btn-success' }}">
                                    <i class="bi {{ $module['published'] ? 'bi-lock-fill' : 'bi-unlock-fill' }} me-1"></i>
                                    {{ $module['published'] ? __('Lock for users') : __('Publish for users') }}
                                </button>
                            </form>
                        </div>
                        <p class="small text-primary mt-3 mb-0">{{ __('You retain access while this module is locked for regular accounts.') }}</p>
                    </div>
                </section>
            </div>
        @endforeach
    </div>
</div>
@endsection
