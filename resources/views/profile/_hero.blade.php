{{-- Shared hero + tab bar for the profile sub-pages.
     Expects: $pfTitle, $pfSubtitle, $pfIcon (bootstrap-icons class), $pfUser --}}
<section class="pf-hero pf-hero-slim">
    <span class="pf-orb pf-orb-1" aria-hidden="true"></span>
    <span class="pf-orb pf-orb-2" aria-hidden="true"></span>

    <div class="pf-hero-main">
        <a href="{{ route('profile.show') }}" class="pf-back" aria-label="{{ __('Back') }}">
            <i class="bi bi-arrow-left"></i>
        </a>

        <div class="pf-hero-id">
            <p class="pf-eyebrow"><i class="bi {{ $pfIcon }}"></i> {{ __('Profile') }}</p>
            <h1 class="pf-name pf-hero-title">{{ $pfTitle }}</h1>
            <p class="pf-mail"><i class="bi bi-info-circle"></i> {{ $pfSubtitle }}</p>
            <ul class="pf-chips">
                <li class="pf-chip"><i class="bi bi-person"></i><span>{{ $pfUser->name }}</span></li>
                <li class="pf-chip"><i class="bi bi-envelope"></i><span dir="ltr">{{ $pfUser->email }}</span></li>
            </ul>
        </div>

        <div class="pf-hero-actions">
            <a href="{{ route('profile.show') }}" class="pf-btn pf-btn-glass">
                <i class="bi bi-person-circle"></i> {{ __('View Profile') }}
            </a>
        </div>
    </div>
</section>

@include('profile._tabs')
