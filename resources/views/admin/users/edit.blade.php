@extends('layouts.app')

@section('title', __('Edit account').' | '.__('TaskManager'))

@section('content')
<div class="container-fluid py-3" style="max-width:1000px;">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small fw-bold text-primary mb-1">{{ __('Administrator') }}</div>
            <h1 class="h3 fw-bold mb-2">{{ $user->name }}</h1>
            <p class="text-secondary mb-0 d-flex align-items-center gap-2 flex-wrap">
                <span dir="ltr">{{ '@'.$user->username }}</span>
                @if ($user->is_admin)
                    <span class="badge text-bg-primary">{{ __('Administrator') }}</span>
                @else
                    <span class="badge text-bg-light border">{{ __('User') }}</span>
                @endif
                @php($state = $user->status())
                @if ($state === 'active')
                    <span class="badge text-bg-success">{{ __('Active') }}</span>
                @elseif ($state === 'banned')
                    <span class="badge text-bg-warning">{{ __('Suspended') }}</span>
                @else
                    <span class="badge text-bg-secondary">{{ __('Deleted') }}</span>
                @endif
            </p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-right"></i> {{ __('Back to accounts') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('admin.users.update', $user->id) }}" class="card border-0 shadow-sm">
                @csrf
                @method('PUT')
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold mb-3">{{ __('Profile') }}</h2>
                    @include('admin.users._form', ['user' => $user])
                </div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> {{ __('Save changes') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <section class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold mb-1">{{ __('Role') }}</h2>
                    <p class="small text-secondary mb-3">{{ __('Administrators can manage every account and every module.') }}</p>
                    @if ($isSelf)
                        <p class="small text-secondary mb-0">{{ __('You cannot change your own role.') }}</p>
                    @else
                        <form method="POST" action="{{ route('admin.users.role', $user->id) }}">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="btn {{ $user->is_admin ? 'btn-outline-warning' : 'btn-outline-primary' }} w-100">
                                <i class="bi {{ $user->is_admin ? 'bi-shield-fill-exclamation' : 'bi-shield-fill-check' }}"></i>
                                {{ $user->is_admin ? __('Revoke administrator access') : __('Make administrator') }}
                            </button>
                        </form>
                        @if ($user->is_admin && $adminCount <= 1)
                            <p class="small text-warning mt-2 mb-0">{{ __('At least one administrator must remain.') }}</p>
                        @endif
                    @endif
                </div>
            </section>

            <section class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold mb-1">{{ __('Access') }}</h2>
                    <p class="small text-secondary mb-3">{{ __('A suspended account keeps its data but cannot sign in.') }}</p>
                    @if ($isSelf)
                        <p class="small text-secondary mb-0">{{ __('You cannot suspend your own account.') }}</p>
                    @elseif ($state === 'deleted')
                        <p class="small text-secondary mb-0">{{ __('This account is deleted.') }}</p>
                    @else
                        <form method="POST" action="{{ route('admin.users.status', $user->id) }}"
                              onsubmit="return confirmSwal(this, @json($user->is_active === false ? __('Re-activate this account?') : __('Suspend this account? It cannot sign in until it is re-activated.')), { isDelete: {{ $user->is_active === false ? 'false' : 'true' }} })">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="btn {{ $user->is_active === false ? 'btn-outline-success' : 'btn-outline-warning' }} w-100">
                                <i class="bi {{ $user->is_active === false ? 'bi-unlock' : 'bi-lock' }}"></i>
                                {{ $user->is_active === false ? __('Re-activate account') : __('Suspend account') }}
                            </button>
                        </form>
                    @endif
                </div>
            </section>

            @if ($state !== 'deleted')
                <section class="card border-0 shadow-sm border-danger-subtle">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-bold mb-1 text-danger">{{ __('Delete account') }}</h2>
                        <p class="small text-secondary mb-3">{{ __('Nothing is erased: the account is hidden and all of its data stays available for a restore.') }}</p>
                        @if ($isSelf)
                            <p class="small text-secondary mb-0">{{ __('You cannot delete your own account.') }}</p>
                        @else
                            <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}"
                                  onsubmit="return confirmSwal(this, @json(__('Delete this account? Its data is kept and the account can be restored later.')), { isDelete: true })">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    <i class="bi bi-trash"></i> {{ __('Delete account') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
