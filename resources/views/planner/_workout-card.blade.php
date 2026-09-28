{{-- Today's Workout Block in My Day --}}
@if(isset($todayWorkoutDay))
<div class="pl-section mb-3">
    <div class="pl-section-head d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-activity" style="color:#ef4444; font-size:15px;"></i>
            <span class="pl-section-title">Today's Workout Session</span>
            <span class="badge bg-light text-dark border ms-1" style="font-size:10px;">{{ $activeWorkoutPlan->title ?? 'Active Plan' }}</span>
        </div>
        <span class="badge {{ $todayWorkoutDay->isTraining() ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }} rounded-pill" style="font-size:11px;">
            {{ $todayWorkoutDay->isTraining() ? 'Training Day' : 'Rest Day' }}
        </span>
    </div>
    <div class="pl-section-body p-3">
        @if($todayWorkoutDay->isTraining())
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <div>
                    <h6 class="fw-bold mb-1 text-dark">{{ $todayWorkoutDay->title ?: ucfirst($todayWorkoutDay->weekday) }}</h6>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        @foreach($todayWorkoutDay->exercises as $we)
                            <span class="badge bg-light text-dark border fw-normal" style="font-size:11px;">
                                {{ $we->exercise->name ?? 'Exercise' }} ({{ $we->target_sets }} sets)
                            </span>
                        @endforeach
                    </div>
                </div>

                <div class="pt-1">
                    @if(isset($todayWorkoutSession) && $todayWorkoutSession->status === 'completed')
                        <div class="badge bg-success-subtle text-success p-2 px-3 rounded-pill d-inline-flex align-items-center gap-1">
                            <i class="bi bi-check-circle-fill"></i> Completed Today ({{ $todayWorkoutSession->durationMinutes() ?? 45 }}m)
                        </div>
                    @elseif(isset($todayWorkoutSession) && $todayWorkoutSession->status === 'in_progress')
                        <a href="{{ route('workouts.sessions.show', $todayWorkoutSession) }}" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1 fw-bold px-3 shadow-sm">
                            <i class="bi bi-play-circle-fill"></i> Continue Workout
                        </a>
                    @else
                        <form action="{{ route('workouts.sessions.start', $todayWorkoutDay) }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1 fw-bold px-3 shadow-sm">
                                <i class="bi bi-lightning-charge-fill"></i> Start Workout Session
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @else
            <div class="text-center py-2 text-muted small">
                <i class="bi bi-cup-hot text-warning me-1"></i> Active rest & recovery day. Focus on mobility, hydration, and nutrition!
            </div>
        @endif
    </div>
</div>
@endif
