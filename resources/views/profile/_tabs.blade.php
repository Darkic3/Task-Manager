{{-- Shared profile tab bar (highlights the current section) --}}
<nav class="pf-tabs" aria-label="{{ __('Profile') }}">
    <a href="{{ route('profile.show') }}" class="pf-tab {{ request()->routeIs('profile.show') ? 'is-active' : '' }}"
       @if(request()->routeIs('profile.show')) aria-current="page" @endif>
        <i class="bi bi-person-circle"></i> {{ __('Profile') }}
    </a>
    <a href="{{ route('profile.edit') }}" class="pf-tab {{ request()->routeIs('profile.edit') ? 'is-active' : '' }}"
       @if(request()->routeIs('profile.edit')) aria-current="page" @endif>
        <i class="bi bi-pencil-square"></i> {{ __('Edit Info') }}
    </a>
    <a href="{{ route('profile.password') }}" class="pf-tab {{ request()->routeIs('profile.password*') ? 'is-active' : '' }}"
       @if(request()->routeIs('profile.password*')) aria-current="page" @endif>
        <i class="bi bi-shield-lock"></i> {{ __('Password') }}
    </a>
</nav>
