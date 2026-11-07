{{-- Discovery journey bar for dashboard (and reusable elsewhere) --}}
@php
    $ob = $onboarding ?? ['ordered' => [], 'active' => 'planner', 'total_done' => 0, 'total_steps' => 1, 'percent' => 0, 'modules' => []];
    $obNames = [
        'planner' => __('My Day'), 'tasks' => __('Tasks'), 'projects' => __('Projects'),
        'routines' => __('Routines'), 'workouts' => __('Workouts'), 'time_tracking' => __('Time'),
        'notes' => __('Notes'), 'track' => __('Track'), 'reports' => __('Reports'),
    ];
    $obIcons = [
        'planner' => 'bi-sun-fill', 'tasks' => 'bi-check2-square', 'projects' => 'bi-folder-fill',
        'routines' => 'bi-arrow-repeat', 'workouts' => 'bi-heart-pulse-fill', 'time_tracking' => 'bi-stopwatch',
        'notes' => 'bi-journal-text', 'track' => 'bi-graph-up-arrow', 'reports' => 'bi-bar-chart-fill',
    ];
    $obRoutes = [
        'planner' => 'planner.index', 'tasks' => 'tasks.index', 'projects' => 'projects.index',
        'routines' => 'routines.index', 'workouts' => 'workouts.plans.index', 'time_tracking' => 'time.reports',
        'notes' => 'notes.index', 'track' => 'track.index', 'reports' => 'reports.overview',
    ];
@endphp
@if(! empty($ob['modules']))
<div class="ob-journey" id="obJourney">
    <div class="ob-journey-head">
        <span class="ob-journey-ico">🗺️</span>
        <div style="flex:1;min-width:200px;">
            <p class="ob-journey-title">{{ __('Your discovery journey') }} · {{ app_num($ob['percent']) }}%</p>
            <p class="ob-journey-sub">
                @if(($ob['total_done'] ?? 0) >= ($ob['total_steps'] ?? 1))
                    {{ __('Everything discovered — you mastered the loop! ✨') }}
                @else
                    {{ __('Next quest:') }} <strong>{{ $obNames[$ob['active']] ?? $ob['active'] }}</strong>
                    · {{ app_num($ob['total_done']) }}/{{ app_num($ob['total_steps']) }} {{ __('steps') }}
                @endif
            </p>
        </div>
        @if(isset($obRoutes[$ob['active']]) && ($ob['total_done'] ?? 0) < ($ob['total_steps'] ?? 1))
            <a href="{{ route($obRoutes[$ob['active']]) }}" class="btn btn-light btn-sm fw-bold">
                <i class="bi bi-play-fill"></i> {{ __('Go to quest') }}
            </a>
        @endif
    </div>
    <div class="ob-journey-bar" role="progressbar" aria-valuenow="{{ $ob['percent'] }}" aria-valuemin="0" aria-valuemax="100">
        <div class="ob-journey-fill" style="width:{{ $ob['percent'] }}%"></div>
    </div>
    <div class="ob-modules">
        @foreach(($ob['ordered'] ?? []) as $mod)
            @php $info = $ob['modules'][$mod] ?? null; if (! $info) continue; @endphp
            <a href="{{ isset($obRoutes[$mod]) ? route($obRoutes[$mod]) : '#' }}" class="ob-mod {{ $mod === $ob['active'] ? 'is-active' : '' }} {{ $info['mastered'] ? 'is-done' : '' }} {{ $info['done'] > 0 && ! $info['mastered'] ? 'is-progress' : '' }}">
                @if($mod === $ob['active'] && ! $info['mastered'])<span class="ob-pulse-dot"></span>@endif
                @if($info['mastered'])<i class="bi bi-check-circle-fill text-success"></i>@else<i class="bi {{ $obIcons[$mod] ?? 'bi-circle' }}"></i>@endif
                <span>{{ $obNames[$mod] ?? $mod }}</span>
                <small>{{ app_num($info['done']) }}/{{ app_num($info['total']) }}</small>
            </a>
        @endforeach
    </div>
</div>
@endif
