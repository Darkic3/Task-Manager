{{-- Quest track picker: first-run modal, order of clicks = priority --}}
@php
    $tracks = [
        'do' => ['title' => __('Get things done daily'), 'desc' => __('My Day → Tasks → Time: a calm loop for todos, focus and timers.'), 'icon' => 'bi-lightning-charge-fill', 'color' => '#f59e0b'],
        'study' => ['title' => __('Study & big projects'), 'desc' => __('Projects → Tasks → My Day: chapters, pages and daily goals.'), 'icon' => 'bi-book-half', 'color' => '#4f46e5'],
        'habit' => ['title' => __('Build discipline & habits'), 'desc' => __('Routines → My Day → Track: wake-ups, streaks and quit-tracking.'), 'icon' => 'bi-fire', 'color' => '#ef4444'],
        'fitness' => ['title' => __('Train & log workouts'), 'desc' => __('Workouts → My Day → Reports: plans, live sessions and PRs.'), 'icon' => 'bi-heart-pulse-fill', 'color' => '#16a34a'],
    ];
@endphp
<div class="modal fade" id="obTrackModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:18px;border:1px solid #e0e7ff;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#1e1b4b,#4338ca 60%,#7c3aed);padding:20px 22px;color:#fff;">
                <h5 class="m-0 fw-bold">🎮 {{ __('How do you want to start your journey?') }}</h5>
                <p class="m-0 mt-1" style="font-size:.82rem;opacity:.85;">{{ __('Tap your priorities in order — first tap = highest priority. You can change everything later.') }}</p>
            </div>
            <form method="POST" action="{{ route('onboarding.priorities') }}" id="obTrackForm">
                @csrf
                <div class="modal-body p-3 p-md-4">
                    <div class="ob-track-grid" id="obTrackGrid">
                        @foreach($tracks as $key => $t)
                            <button type="button" class="ob-track" data-track="{{ $key }}" style="--tc:{{ $t['color'] }};" aria-pressed="false">
                                <span class="ob-track-ico" style="background:{{ $t['color'] }};"><i class="bi {{ $t['icon'] }}"></i></span>
                                <span style="flex:1;min-width:0;">
                                    <b>{{ $t['title'] }}</b>
                                    <small>{{ $t['desc'] }}</small>
                                </span>
                                <span class="ob-track-order" hidden></span>
                            </button>
                        @endforeach
                    </div>
                    <div id="obTrackHidden"></div>
                    <p class="text-muted mt-3 mb-0" style="font-size:.75rem;" id="obTrackHint">{{ __('Pick at least one path to begin — the rest stays visible but dimmed until you discover it.') }}</p>
                </div>
                <div class="modal-footer" style="border-top-color:#eef2ff;">
                    <button type="submit" class="btn btn-primary" id="obTrackSubmit" disabled>
                        <i class="bi bi-rocket-takeoff"></i> {{ __('Start my journey') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    var grid = document.getElementById('obTrackGrid');
    var form = document.getElementById('obTrackForm');
    var submit = document.getElementById('obTrackSubmit');
    var hidden = document.getElementById('obTrackHidden');
    if (!grid || !form) return;
    var order = [];
    function paint() {
        grid.querySelectorAll('.ob-track').forEach(function (btn) {
            var key = btn.dataset.track;
            var idx = order.indexOf(key);
            var picked = idx >= 0;
            btn.classList.toggle('is-picked', picked);
            btn.setAttribute('aria-pressed', picked ? 'true' : 'false');
            var badge = btn.querySelector('.ob-track-order');
            if (picked) { badge.hidden = false; badge.textContent = (idx + 1); }
            else { badge.hidden = true; }
        });
        hidden.innerHTML = '';
        order.forEach(function (key) {
            var i = document.createElement('input');
            i.type = 'hidden'; i.name = 'tracks[]'; i.value = key;
            hidden.appendChild(i);
        });
        submit.disabled = order.length === 0;
    }
    grid.querySelectorAll('.ob-track').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var key = btn.dataset.track;
            var idx = order.indexOf(key);
            if (idx >= 0) { order.splice(idx, 1); } else if (order.length < 4) { order.push(key); }
            paint();
        });
    });
    paint();
    @if(! empty($showOnboardingPicker ?? false))
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('obTrackModal');
        if (el && window.bootstrap) { new bootstrap.Modal(el).show(); }
    });
    @endif
})();
</script>
