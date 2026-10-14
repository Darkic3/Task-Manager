{{-- Today's Workout — secondary rail card (compact, never competes with Next-Up hero) --}}
@if(isset($todayWorkoutDay))
<div class="pl-section pl-workout-mini">
    <div class="pl-section-head">
        <div class="d-flex align-items-center gap-2 min-w-0">
            <div style="width: 28px; height: 28px; border-radius: 9px; background: linear-gradient(135deg,#7c3aed,#6366f1); color: #fff; display: grid; place-items: center; font-size: 14px; flex-shrink:0;">
                <i class="bi bi-lightning-charge-fill"></i>
            </div>
            <span class="min-w-0">
                <span class="pl-section-title d-block text-truncate">{{ __("Workout") }}</span>
                <span class="pl-head-sub d-block text-truncate">{{ $activeWorkoutPlan->title ?? __('Active Plan') }}</span>
            </span>
        </div>
        <span class="badge {{ $todayWorkoutDay->isTraining() ? 'bg-primary' : 'bg-secondary' }} text-white rounded-pill px-2 py-1 ms-auto" style="font-size: 10px;">
            {{ $todayWorkoutDay->isTraining() ? __('Training') : __('Recovery') }}
        </span>
    </div>
    <div class="pl-section-body">
        @if($todayWorkoutDay->isTraining())
            <h6 class="fw-bold mb-1 text-dark" style="font-size:13.5px;">
                <a href="{{ route('workouts.days.show', $todayWorkoutDay) }}" class="text-dark text-decoration-none">
                    {{ $todayWorkoutDay->title ?: __(ucfirst($todayWorkoutDay->weekday)) }}
                </a>
            </h6>
            <div class="d-flex flex-wrap gap-1 mt-1 mb-2">
                @foreach($todayWorkoutDay->exercises->take(4) as $we)
                    <span class="badge bg-light text-dark border fw-semibold pl-workout-chip">
                        {{ __($we->exercise->name ?? 'Exercise') }} <span class="text-muted fw-normal">×{{ $we->target_sets }}</span>
                    </span>
                @endforeach
                @if($todayWorkoutDay->exercises->count() > 4)
                    <span class="badge bg-light text-muted border pl-workout-chip">+{{ $todayWorkoutDay->exercises->count() - 4 }}</span>
                @endif
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if(isset($todayWorkoutSession) && $todayWorkoutSession->status === 'completed')
                    <div class="badge bg-success text-white px-3 py-2 rounded-pill d-inline-flex align-items-center gap-1">
                        <i class="bi bi-check-circle-fill"></i> {{ __('Done') }} ({{ $todayWorkoutSession->durationMinutes() ?? 45 }}{{ __('m') }})
                    </div>
                @elseif(isset($todayWorkoutSession) && $todayWorkoutSession->status === 'in_progress')
                    <a href="{{ route('workouts.sessions.show', $todayWorkoutSession) }}" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1 fw-bold px-3 py-2 rounded-pill flex-fill justify-content-center">
                        <i class="bi bi-play-circle-fill"></i> {{ __('Continue') }}
                    </a>
                @else
                    <form action="{{ route('workouts.sessions.start', $todayWorkoutDay) }}" method="POST" class="m-0 flex-fill d-flex">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary d-inline-flex align-items-center justify-content-center gap-1 fw-bold px-3 py-2 rounded-pill flex-fill" style="background: linear-gradient(135deg, #4f46e5, #7c3aed); border: none;">
                            <i class="bi bi-play-fill"></i> {{ __('Start') }}
                        </button>
                    </form>
                @endif
                <a href="{{ route('workouts.days.show', $todayWorkoutDay) }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 fw-bold px-3 py-2 rounded-pill" title="{{ __('View Details') }}">
                    <i class="bi bi-eye"></i>
                </a>
            </div>
        @else
            <div class="text-muted small d-flex align-items-center gap-2">
                <i class="bi bi-cup-hot text-warning fs-5"></i>
                <span>{{ __('Recovery day — hydrate & stretch.') }}</span>
            </div>
            <a href="{{ route('workouts.days.show', $todayWorkoutDay) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold mt-2 w-100">
                <i class="bi bi-eye"></i> {{ __('View') }}
            </a>
        @endif
    </div>
</div>
@endif
