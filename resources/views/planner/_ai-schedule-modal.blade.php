{{-- Lina AI Daily Planner & Schedule Optimizer Modal --}}
<div class="modal fade" id="linaScheduleModal" tabindex="-1" aria-labelledby="linaScheduleTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 text-white p-4" style="background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);">
                <div>
                    <span class="badge bg-white text-dark rounded-pill px-3 py-1 mb-2 font-sans fw-bold" style="font-size: 11px;">
                        <i class="bi bi-stars text-primary"></i> {{ __('Lina AI Copilot') }}
                    </span>
                    <h4 class="modal-title fw-bold m-0" id="linaScheduleTitle">{{ __('Smart Daily Schedule Optimizer ✨') }}</h4>
                    <p class="small text-white-50 m-0 mt-1">{{ __("Let Lina organize your day's tasks according to cognitive load, priority, and energy curves.") }}</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body p-4">
                {{-- Lina Recommendation / Briefing Box --}}
                <div class="p-3 rounded-3 bg-purple-subtle text-purple border border-purple-subtle mb-4" id="linaAiBriefingBox">
                    <div class="d-flex align-items-center gap-2 mb-1 fw-bold">
                        <i class="bi bi-chat-quote-fill"></i> {{ __("Lina's Daily Strategy:") }}
                    </div>
                    <div class="small" id="linaAiBriefingText">
                        {{ __('Click "Optimize My Schedule" below to let me analyze your open tasks and structure them into Morning (Deep Work), Afternoon (Execution), and Evening (Wrap-up).') }}
                    </div>
                </div>

                {{-- Preview Grid --}}
                <div class="row g-3 mb-3 text-start">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <div class="fw-bold small text-primary mb-2"><i class="bi bi-sunrise"></i> {{ __('Morning (Deep Focus)') }}</div>
                            <div class="text-muted small">{{ __('High priority & high estimated tasks when your willpower is fresh.') }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <div class="fw-bold small text-warning mb-2"><i class="bi bi-sun"></i> {{ __('Afternoon (Execution)') }}</div>
                            <div class="text-muted small">{{ __('Medium priority tasks, reviews, and active project execution.') }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <div class="fw-bold small text-secondary mb-2"><i class="bi bi-moon"></i> {{ __('Evening (Wrap-up)') }}</div>
                            <div class="text-muted small">{{ __('Low cognitive load, routine maintenance, and inbox cleanup.') }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">{{ __('Close') }}</button>
                <button type="button" class="btn btn-primary px-4 shadow-sm fw-semibold d-inline-flex align-items-center gap-2" id="linaOptimizeBtn" onclick="runLinaAiSchedule()">
                    <i class="bi bi-stars"></i> {{ __('Optimize My Schedule with Lina') }}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function openLinaScheduleModal() {
        const modalEl = document.getElementById('linaScheduleModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    }

    async function runLinaAiSchedule() {
        const btn = document.getElementById('linaOptimizeBtn');
        const briefing = document.getElementById('linaAiBriefingText');

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Lina is analyzing & optimizing…';
        }

        try {
            const res = await fetch('{{ route("planner.ai-optimize") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await res.json();
            if (data.ok) {
                if (briefing) briefing.textContent = data.briefing || data.message;
                setTimeout(() => {
                    window.location.reload();
                }, 1200);
            } else {
                alert(data.message || 'Optimization failed');
            }
        } catch (e) {
            console.error('Lina optimize error', e);
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Schedule Optimized!';
            }
        }
    }
</script>
