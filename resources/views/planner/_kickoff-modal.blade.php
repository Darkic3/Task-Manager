{{-- Morning Kickoff Wizard Modal --}}
<div class="modal fade" id="morningKickoffModal" tabindex="-1" aria-labelledby="morningKickoffTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 bg-gradient text-white p-4" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <div>
                    <span class="badge bg-white text-dark rounded-pill px-3 py-1 mb-2 font-sans fw-bold" style="font-size: 11px;">
                        <i class="bi bi-sunrise-fill text-warning"></i> {{ __('Morning Kickoff Ritual') }}
                    </span>
                    <h4 class="modal-title fw-bold m-0" id="morningKickoffTitle">{{ __('Design Your Victorious Day 🌅') }}</h4>
                    <p class="small text-white-50 m-0 mt-1">{{ __('Take 30 seconds to align your priorities and eliminate backlog friction.') }}</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body p-4">
                {{-- Overdue cleanup --}}
                @if(isset($overdue) && $overdue->count())
                <div class="mb-4 p-3 rounded-3 bg-danger-subtle border border-danger-subtle">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-danger small">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ __('You have :count overdue tasks from previous days:', ['count' => $overdue->count()]) }}
                        </span>
                        <button type="button" class="btn btn-sm btn-danger py-0 px-2 rounded-pill" onclick="postponeAllOverdueKickoff()" style="font-size: 11px;">
                            {{ __('Move All to Today') }}
                        </button>
                    </div>
                    <div class="small text-muted mb-2">{{ __('Pull them into today\'s focus or let them roll to tomorrow to start clean.') }}</div>
                </div>
                @endif

                {{-- Select Top 3 Must-Win Targets --}}
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-bullseye text-primary me-1"></i> {{ __('Select Your Top 3 Must-Win Tasks for Today') }}</h6>
                    <p class="text-muted small mb-3">{{ __('Focus on the highest leverage work first.') }}</p>

                    <div class="list-group">
                        @php $pendingTasks = isset($pending) ? $pending : collect(); @endphp
                        @forelse($pendingTasks->take(6) as $task)
                        <label class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-3 rounded-3 border mb-2 cursor-pointer">
                            <div class="d-flex align-items-center gap-3">
                                <input type="checkbox" class="form-check-input kickoff-task-check" value="{{ $task->id }}" {{ $loop->index < 3 ? 'checked' : '' }}>
                                <div>
                                    <div class="fw-medium text-dark">{{ $task->title }}</div>
                                    <div class="small text-muted">{{ $task->project->name ?? __('No Project') }} • {{ __('Est') }}: {{ $task->estimated_hours ? $task->estimated_hours . 'h' : '30m' }}</div>
                                </div>
                            </div>
                            <span class="badge {{ $task->priority === 'high' ? 'bg-danger text-white' : 'bg-light text-dark border' }} rounded-pill text-uppercase" style="font-size:10px;">
                                {{ __(ucfirst($task->priority)) }}
                            </span>
                        </label>
                        @empty
                        <div class="text-center py-3 text-muted small">{{ __('No pending tasks for today. Add new tasks to kick off!') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-warning text-dark fw-bold px-4 shadow-sm" onclick="finishMorningKickoff()">
                    <i class="bi bi-rocket-takeoff-fill me-1"></i> {{ __('Start My Day!') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Evening Shutdown Ritual Modal --}}
<div class="modal fade" id="eveningShutdownModal" tabindex="-1" aria-labelledby="eveningShutdownTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 bg-gradient text-white p-4" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);">
                <div>
                    <span class="badge bg-white text-dark rounded-pill px-3 py-1 mb-2 font-sans fw-bold" style="font-size: 11px;">
                        <i class="bi bi-moon-stars-fill text-warning"></i> {{ __('Evening Shutdown Ritual') }}
                    </span>
                    <h4 class="modal-title fw-bold m-0" id="eveningShutdownTitle">{{ __('Wrap Up & Disconnect 🌙') }}</h4>
                    <p class="small text-white-50 m-0 mt-1">{{ __('Review today\'s wins, clear the mental clutter, and recharge for tomorrow.') }}</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body p-4">
                {{-- Scorecard Stats --}}
                <div class="row g-3 mb-4 text-center">
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="fs-4 fw-bold text-success">{{ isset($done) ? $done->count() : 0 }}</div>
                            <div class="small text-muted">{{ __('Tasks Finished') }}</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="fs-4 fw-bold text-primary">{{ isset($routineDone) ? $routineDone : 0 }}/{{ isset($routineTotal) ? $routineTotal : 0 }}</div>
                            <div class="small text-muted">{{ __('Routines Built') }}</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="fs-4 fw-bold text-warning">{{ isset($pending) ? $pending->count() : 0 }}</div>
                            <div class="small text-muted">{{ __('Pending Left') }}</div>
                        </div>
                    </div>
                </div>

                {{-- Leftover tasks resolver --}}
                @if(isset($pending) && $pending->count())
                <div class="mb-4 p-3 rounded-3 bg-light border">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold small">{{ __('Move Leftover Tasks to Tomorrow?') }}</div>
                            <div class="small text-muted">{{ __('Automatically move remaining open tasks to tomorrow\'s planner so you start with zero backlog anxiety.') }}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="postponeLeftoverToTomorrow()">
                            <i class="bi bi-arrow-right-circle"></i> {{ __('Move to Tomorrow') }}
                        </button>
                    </div>
                </div>
                @endif

                {{-- Daily Reflection Note --}}
                <div class="mb-3">
                    <label class="form-label fw-bold small"><i class="bi bi-journal-text text-secondary me-1"></i> {{ __('Quick Reflection & Gratitude Note') }}</label>
                    <textarea class="form-control" id="eveningReflectionNote" rows="2" placeholder="{{ __('What went well today? What is one thing to improve tomorrow?') }}"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">{{ __('Close') }}</button>
                <button type="button" class="btn btn-primary px-4 shadow-sm" onclick="finishEveningShutdown()">
                    <i class="bi bi-check-circle-fill me-1"></i> {{ __('Complete Shutdown') }}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function openMorningKickoff() {
        const modalEl = document.getElementById('morningKickoffModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    }

    function openEveningShutdown() {
        const modalEl = document.getElementById('eveningShutdownModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    }

    async function postponeAllOverdueKickoff() {
        try {
            const res = await fetch('{{ route("planner.tasks.postpone-all") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            if (res.ok) window.location.reload();
        } catch (e) {
            console.error(e);
        }
    }

    function finishMorningKickoff() {
        const modalEl = document.getElementById('morningKickoffModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getInstance(modalEl)?.hide();
        }
        if (typeof plShowToast === 'function') {
            plShowToast(@json(__('🌅 Morning Kickoff Completed! Have a productive day!')));
        }
    }

    async function postponeLeftoverToTomorrow() {
        try {
            const res = await fetch('{{ route("planner.tasks.postpone-all") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            if (res.ok) {
                alertSwal('{{ __('All remaining tasks moved to tomorrow!') }}', null, 'success').then(() => window.location.reload());
            }
        } catch (e) {
            console.error(e);
        }
    }

    function finishEveningShutdown() {
        const modalEl = document.getElementById('eveningShutdownModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getInstance(modalEl)?.hide();
        }
        alertSwal('{{ __('🌙 Great job today! Have a restful evening.') }}', null, 'success');
    }
</script>
