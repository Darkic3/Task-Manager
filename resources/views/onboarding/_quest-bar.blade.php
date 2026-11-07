{{-- Per-module quest bar: 3–4 steps with spotlight on the current step.
     Expects: $questModule (string), $onboarding (snapshot). --}}
@php
    $ob = $onboarding ?? ['modules' => [], 'active' => 'planner'];
    $mod = $questModule ?? 'planner';
    $info = $ob['modules'][$mod] ?? null;
@endphp
@if($info && ! $info['mastered'])
<div class="ob-quest" id="obQuest-{{ $mod }}">
    <div class="ob-quest-top">
        <span class="ob-quest-badge">🎯 {{ __('Quest') }} · {{ app_num($info['done']) }}/{{ app_num($info['total']) }}</span>
        <p class="ob-quest-title">{{ __('Level up this section — one small step at a time') }}</p>
        <span class="ms-auto text-muted" style="font-size:.72rem;">{{ app_num($info['percent']) }}%</span>
    </div>
    <div class="ob-quest-steps">
        @foreach($info['steps'] as $i => $s)
            @php
                $done = in_array($s['key'], $info['done_steps'] ?? [], true);
                $isCurrent = ! $done && ($i === 0 || in_array($info['steps'][$i-1]['key'], $info['done_steps'] ?? [], true));
            @endphp
            <button type="button" class="ob-step {{ $done ? 'is-done' : '' }} {{ $isCurrent ? 'is-current ob-spot-target' : '' }}"
                    data-ob-module="{{ $mod }}" data-ob-step="{{ $s['key'] }}"
                    data-ob-route="{{ route($s['route']) }}"
                    title="{{ $s['desc'] }}">
                <span class="ob-step-check"><i class="bi bi-check-lg"></i></span>
                <span><b>{{ $s['label'] }}</b><small>{{ $s['desc'] }}</small></span>
            </button>
        @endforeach
    </div>
    @if($mod === 'planner')
        <div class="ob-seed-row">
            <span class="text-muted" style="font-size:.72rem;align-self:center;">{{ __('Empty day? Start instantly:') }}</span>
            <form method="POST" action="{{ route('onboarding.seed') }}" class="d-inline">
                @csrf
                <input type="hidden" name="kind" value="task">
                <button type="submit" class="ob-seed-btn">⚡ {{ __('Seed a sample task for today') }}</button>
            </form>
        </div>
    @endif
</div>
<script>
(function () {
    var bar = document.getElementById('obQuest-{{ $mod }}');
    if (!bar || bar.dataset.bound) return;
    bar.dataset.bound = '1';
    bar.querySelectorAll('.ob-step').forEach(function (btn) {
        btn.addEventListener('click', function () {
            // Optimistic check + navigate to where the step happens.
            fetch(@json(route('onboarding.step')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ module: btn.dataset.obModule, step: btn.dataset.obStep }),
            }).catch(function () {}).finally(function () {
                if (btn.dataset.obRoute) { window.location.href = btn.dataset.obRoute; }
            });
        });
    });
    // Gentle spotlight: scroll the current step into view once.
    var cur = bar.querySelector('.is-current');
    if (cur) { setTimeout(function () { try { cur.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch (e) {} }, 400); }
})();
</script>
@endif
