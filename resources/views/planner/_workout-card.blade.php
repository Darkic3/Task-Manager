{{-- Today's Workout Block in My Day --}}
@if(isset($todayWorkoutDay))
<div class="pl-section mb-3 border-0 shadow-sm" style="background: linear-gradient(135deg, #ffffff 0%, #faf5ff 100%); border: 1px solid #ede9fe !important;">
    <div class="pl-section-head d-flex align-items-center justify-content-between" style="background: transparent; border-bottom: 1px solid #f3e8ff;">
        <div class="d-flex align-items-center gap-2">
            <div style="width: 26px; height: 26px; border-radius: 8px; background: #ede9fe; color: #7c3aed; display: grid; place-items: center; font-size: 13px;">
                <i class="bi bi-lightning-charge-fill"></i>
            </div>
            <span class="pl-section-title text-dark">Today's Workout Session</span>
            <span class="badge bg-white text-secondary border ms-1" style="font-size: 10.5px;">{{ $activeWorkoutPlan->title ?? 'Active Plan' }}</span>
        </div>
        <span class="badge {{ $todayWorkoutDay->isTraining() ? 'bg-primary text-white' : 'bg-secondary text-white' }} rounded-pill px-3 py-1" style="font-size: 10.5px; letter-spacing: 0.03em;">
            {{ $todayWorkoutDay->isTraining() ? '⚡ Training Day' : '☕ Recovery Day' }}
        </span>
    </div>
    <div class="pl-section-body p-3">
        @if($todayWorkoutDay->isTraining())
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div style="flex: 1; min-width: 220px;">
                    <h6 class="fw-bold mb-1 text-dark fs-6">{{ $todayWorkoutDay->title ?: ucfirst($todayWorkoutDay->weekday) }}</h6>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach($todayWorkoutDay->exercises as $we)
                            <span class="badge bg-white text-dark border fw-semibold shadow-xs" style="font-size: 11px; padding: 4px 8px;">
                                {{ $we->exercise->name ?? 'Exercise' }} <span class="text-muted fw-normal">({{ $we->target_sets }} sets)</span>
                            </span>
                        @endforeach
                    </div>
                </div>

                <div class="pt-1">
                    @if(isset($todayWorkoutSession) && $todayWorkoutSession->status === 'completed')
                        <div class="badge bg-success text-white p-2 px-3 rounded-pill d-inline-flex align-items-center gap-1 shadow-sm">
                            <i class="bi bi-check-circle-fill"></i> Completed Today ({{ $todayWorkoutSession->durationMinutes() ?? 45 }}m)
                        </div>
                    @elseif(isset($todayWorkoutSession) && $todayWorkoutSession->status === 'in_progress')
                        <a href="{{ route('workouts.sessions.show', $todayWorkoutSession) }}" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1 fw-bold px-3 py-2 rounded-pill shadow-sm">
                            <i class="bi bi-play-circle-fill"></i> Continue Workout
                        </a>
                    @else
                        <form action="{{ route('workouts.sessions.start', $todayWorkoutDay) }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-2 fw-bold px-4 py-2 rounded-pill shadow-sm" style="background: linear-gradient(135deg, #4f46e5, #7c3aed); border: none;">
                                <i class="bi bi-play-fill fs-6"></i> Start Workout Session
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @else
            <div class="text-center py-2 text-muted small d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-cup-hot text-warning fs-5"></i>
                <span>Active rest & recovery day. Focus on hydration, mobility, and healthy recovery!</span>
            </div>
        @endif
    </div>
</div>
@endif
