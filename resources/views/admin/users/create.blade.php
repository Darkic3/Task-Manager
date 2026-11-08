@extends('layouts.app')

@section('title', __('New account').' | '.__('TaskManager'))

@section('content')
<div class="container-fluid py-3" style="max-width:900px;">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small fw-bold text-primary mb-1">{{ __('Administrator') }}</div>
            <h1 class="h3 fw-bold mb-2">{{ __('New account') }}</h1>
            <p class="text-secondary mb-0">{{ __('The new account can sign in immediately with the password you set here.') }}</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-right"></i> {{ __('Back to accounts') }}
        </a>
    </div>

    <form method="POST" action="{{ route('admin.users.store') }}" class="card border-0 shadow-sm">
        @csrf
        <div class="card-body p-4">
            @include('admin.users._form', ['user' => null])
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> {{ __('Create account') }}
            </button>
        </div>
    </form>
</div>
@endsection
