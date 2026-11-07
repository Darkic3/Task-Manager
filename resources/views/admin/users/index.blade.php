@extends('layouts.app')

@section('title', __('User management').' | '.__('TaskManager'))

@section('content')
<div class="container-fluid py-3" style="max-width:1180px;">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small fw-bold text-primary mb-1">{{ __('Administrator') }}</div>
            <h1 class="h3 fw-bold mb-2">{{ __('User management') }}</h1>
            <p class="text-secondary mb-0">{{ __('Create accounts, edit profiles, change roles and suspend access. Deleted accounts keep their data and can be restored.') }}</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2">
            <i class="bi bi-person-plus"></i> {{ __('New account') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    {{-- Filters --}}
    <section class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label for="q" class="form-label small mb-1">{{ __('Search') }}</label>
                    <input type="search" id="q" name="q" class="form-control" value="{{ $search }}"
                           placeholder="{{ __('Name, username or email') }}">
                </div>
                <div class="col-md-3">
                    <label for="role" class="form-label small mb-1">{{ __('Role') }}</label>
                    <select id="role" name="role" class="form-select">
                        <option value="all" {{ $role === 'all' ? 'selected' : '' }}>{{ __('All roles') }} ({{ $counts['all'] }})</option>
                        <option value="admin" {{ $role === 'admin' ? 'selected' : '' }}>{{ __('Administrators') }} ({{ $counts['admin'] }})</option>
                        <option value="user" {{ $role === 'user' ? 'selected' : '' }}>{{ __('Regular accounts') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                    <select id="status" name="status" class="form-select">
                        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>{{ __('All statuses') }}</option>
                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>{{ __('Active') }} ({{ $counts['active'] }})</option>
                        <option value="banned" {{ $status === 'banned' ? 'selected' : '' }}>{{ __('Suspended') }} ({{ $counts['banned'] }})</option>
                        <option value="deleted" {{ $status === 'deleted' ? 'selected' : '' }}>{{ __('Deleted') }} ({{ $counts['deleted'] }})</option>
                    </select>
                </div>
                <div class="col-md-1 d-grid">
                    <button class="btn btn-outline-primary" type="submit" title="{{ __('Filter') }}"><i class="bi bi-funnel"></i></button>
                </div>
            </form>
        </div>
    </section>

    {{-- Accounts --}}
    <section class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">{{ __('Account') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Role') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Joined') }}</th>
                            <th>{{ __('Last seen') }}</th>
                            <th class="text-end pe-3">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $account)
                            @php
                                $state = $account->status();
                                $seenAt = $lastSeen[$account->id] ?? null;
                            @endphp
                            <tr class="{{ $state !== 'active' ? 'table-light opacity-75' : '' }}">
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($account->avatar)
                                            <img src="{{ Storage::url($account->avatar) }}" alt="{{ $account->name }}" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">
                                        @else
                                            <span class="rounded-circle d-inline-grid place-items-center text-white fw-bold" style="width:32px;height:32px;background:linear-gradient(135deg,#6366f1,#8b5cf6);font-size:.8rem;">{{ name_initials($account->name, 2) }}</span>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-truncate">{{ $account->name }}</div>
                                            <div class="small text-secondary" dir="ltr">{{ '@'.$account->username }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="small" dir="ltr">{{ $account->email ?: '—' }}</td>
                                <td>
                                    @if ($account->is_admin)
                                        <span class="badge text-bg-primary">{{ __('Administrator') }}</span>
                                    @else
                                        <span class="badge text-bg-light border">{{ __('User') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($state === 'active')
                                        <span class="badge text-bg-success">{{ __('Active') }}</span>
                                    @elseif ($state === 'banned')
                                        <span class="badge text-bg-warning">{{ __('Suspended') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('Deleted') }}</span>
                                    @endif
                                </td>
                                <td class="small text-secondary">{{ app_date($account->created_at, 'M d, Y') }}</td>
                                <td class="small text-secondary">
                                    @if ($seenAt)
                                        {{ app_datetime(\Carbon\Carbon::createFromTimestamp($seenAt), 'M d · H:i') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                        <a href="{{ route('admin.users.edit', $account->id) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        @if ($state !== 'deleted')
                                            <form method="POST" action="{{ route('admin.users.role', $account->id) }}">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn btn-sm {{ $account->is_admin ? 'btn-outline-warning' : 'btn-outline-primary' }}"
                                                        title="{{ $account->is_admin ? __('Revoke administrator access') : __('Grant administrator access') }}">
                                                    <i class="bi {{ $account->is_admin ? 'bi-shield-fill-exclamation' : 'bi-shield-fill-check' }}"></i>
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.users.status', $account->id) }}"
                                                  onsubmit="return confirmSwal(this, @json($account->is_active === false ? __('Re-activate this account?') : __('Suspend this account? It cannot sign in until it is re-activated.')), { isDelete: {{ $account->is_active === false ? 'false' : 'true' }} })">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn btn-sm {{ $account->is_active === false ? 'btn-outline-success' : 'btn-outline-warning' }}"
                                                        title="{{ $account->is_active === false ? __('Re-activate account') : __('Suspend account') }}">
                                                    <i class="bi {{ $account->is_active === false ? 'bi-unlock' : 'bi-lock' }}"></i>
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.users.destroy', $account->id) }}"
                                                  onsubmit="return confirmSwal(this, @json(__('Delete this account? Its data is kept and the account can be restored later.')), { isDelete: true })">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete account') }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-5">{{ __('No account matches this filter.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($users->hasPages())
            <div class="card-footer bg-white">
                {{ $users->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
