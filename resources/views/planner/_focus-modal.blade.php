{{-- Full-Screen Focus Workstation Modal --}}
<div class="focus-workstation-overlay" id="focusWorkstationModal" style="display: none;">
    <div class="focus-workstation-card">
        {{-- Focus Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center gap-2">
                <span class="focus-badge-pulse"></span>
                <span class="text-uppercase fw-bold text-muted small" style="letter-spacing: 0.1em;">Focus Mode • Workstation</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="closeFocusWorkstation()">
                Exit (Esc) <i class="bi bi-x-lg ms-1"></i>
            </button>
        </div>

        {{-- Active Task Title & Project --}}
        <div class="text-center mb-4">
            <span class="badge bg-purple-subtle text-purple px-3 py-1 rounded-pill mb-2" id="focusProjectName">General Focus</span>
            <h2 class="focus-task-title fw-bold" id="focusTaskTitle">Pick a task to focus on</h2>
        </div>

        {{-- Pomodoro Timer Dial --}}
        <div class="focus-timer-container text-center mb-4">
            <div class="focus-clock-display font-monospace" id="focusTimerClock">25:00</div>
            
            <div class="d-flex justify-content-center gap-2 mb-3">
                <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" onclick="setFocusPreset(15)">15m</button>
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" onclick="setFocusPreset(25)">25m</button>
                <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" onclick="setFocusPreset(50)">50m</button>
            </div>

            <div class="d-flex justify-content-center gap-3">
                <button type="button" class="btn btn-lg btn-success rounded-pill px-4 d-inline-flex align-items-center gap-2 shadow" id="focusToggleTimerBtn" onclick="toggleFocusTimer()">
                    <i class="bi bi-play-fill fs-5"></i> Start Focus
                </button>
                <button type="button" class="btn btn-lg btn-light border rounded-pill px-3" onclick="resetFocusTimer()" title="Reset Timer">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </div>
        </div>

        {{-- Subtasks Checklist in Focus Mode --}}
        <div class="focus-subtasks-card p-3 rounded-3 bg-light border mb-4 text-start">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold small text-uppercase text-muted"><i class="bi bi-check2-square text-primary"></i> Checklist</span>
                <span class="badge bg-white text-dark border small" id="focusChecklistRatio">0/0</span>
            </div>
            <div class="focus-checklist-items" id="focusChecklistItems" style="max-height: 140px; overflow-y: auto;">
                <div class="text-muted small text-center py-2">No subtasks found for this task</div>
            </div>
        </div>

        {{-- Bottom Action: Complete & Next --}}
        <div class="d-flex justify-content-center gap-3">
            <button type="button" class="btn btn-primary rounded-pill px-4 py-2 d-inline-flex align-items-center gap-2" id="focusCompleteBtn" onclick="completeFocusTaskAndNext()">
                <i class="bi bi-check2-circle fs-5"></i> Complete & Next Task
            </button>
        </div>
    </div>
</div>

<style>
    .focus-workstation-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(15, 23, 42, 0.94);
        backdrop-filter: blur(12px);
        z-index: 1080;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
    }
    .focus-workstation-card {
        background: #ffffff;
        border-radius: 1.25rem;
        width: 100%;
        max-width: 580px;
        padding: 2rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
    }
    .focus-badge-pulse {
        width: 10px; height: 10px; background: #ef4444;
        border-radius: 50%; display: inline-block;
        animation: pulse-red 1.5s infinite;
    }
    @keyframes pulse-red {
        0% { transform: scale(0.95); opacity: 0.8; }
        50% { transform: scale(1.2); opacity: 1; }
        100% { transform: scale(0.95); opacity: 0.8; }
    }
    .focus-task-title {
        font-size: 1.4rem;
        color: #0f172a;
        line-height: 1.35;
    }
    .focus-clock-display {
        font-size: 3.5rem;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #1e1b4b;
        margin-bottom: 0.5rem;
    }
    .focus-checklist-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0;
        font-size: 0.875rem;
    }
    .focus-checklist-item.done span {
        text-decoration: line-through;
        color: #94a3b8;
    }
</style>

<script>
    let focusTimerInterval = null;
    let focusSecondsLeft = 25 * 60;
    let focusTimerRunning = false;
    let currentFocusTaskId = null;

    function openFocusWorkstation(taskId = null, taskTitle = null, projectName = null) {
        const modal = document.getElementById('focusWorkstationModal');
        if (!modal) return;

        // If no task provided, pick the Next Up task or first pending task
        if (!taskId) {
            const nextUpEl = document.querySelector('.pl-next-card, [data-task-item][data-completed="0"]');
            if (nextUpEl && nextUpEl.dataset.id) {
                taskId = nextUpEl.dataset.id;
                taskTitle = nextUpEl.querySelector('.pl-task-title, .pl-next-title')?.textContent?.trim() || 'Active Task';
                projectName = nextUpEl.querySelector('.pl-tag-project')?.textContent?.trim() || 'My Day';
            }
        }

        currentFocusTaskId = taskId;
        document.getElementById('focusTaskTitle').textContent = taskTitle || 'Focused Execution';
        document.getElementById('focusProjectName').textContent = projectName || 'My Day Priority';

        loadFocusSubtasks(taskId);
        modal.style.display = 'flex';
        setFocusPreset(25);
    }

    function closeFocusWorkstation() {
        const modal = document.getElementById('focusWorkstationModal');
        if (modal) modal.style.display = 'none';
        if (focusTimerRunning) toggleFocusTimer();
    }

    function setFocusPreset(mins) {
        if (focusTimerRunning) toggleFocusTimer();
        focusSecondsLeft = mins * 60;
        updateFocusClockDisplay();
    }

    function updateFocusClockDisplay() {
        const mins = String(Math.floor(focusSecondsLeft / 60)).padStart(2, '0');
        const secs = String(focusSecondsLeft % 60).padStart(2, '0');
        const el = document.getElementById('focusTimerClock');
        if (el) el.textContent = `${mins}:${secs}`;
    }

    function toggleFocusTimer() {
        const btn = document.getElementById('focusToggleTimerBtn');
        if (focusTimerRunning) {
            clearInterval(focusTimerInterval);
            focusTimerRunning = false;
            if (btn) btn.innerHTML = '<i class="bi bi-play-fill fs-5"></i> Resume Focus';
        } else {
            focusTimerRunning = true;
            if (btn) btn.innerHTML = '<i class="bi bi-pause-fill fs-5"></i> Pause';
            focusTimerInterval = setInterval(() => {
                if (focusSecondsLeft > 0) {
                    focusSecondsLeft--;
                    updateFocusClockDisplay();
                } else {
                    clearInterval(focusTimerInterval);
                    focusTimerRunning = false;
                    alert('🎉 Focus Session Completed! Take a 5-minute break.');
                    if (btn) btn.innerHTML = '<i class="bi bi-play-fill fs-5"></i> Start Focus';
                }
            }, 1000);
        }
    }

    function resetFocusTimer() {
        if (focusTimerRunning) toggleFocusTimer();
        setFocusPreset(25);
    }

    async function loadFocusSubtasks(taskId) {
        const container = document.getElementById('focusChecklistItems');
        const ratio = document.getElementById('focusChecklistRatio');
        if (!container || !taskId) return;

        try {
            const res = await fetch(`/tasks/${taskId}`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            const items = json.task?.checklist_items || [];
            if (items.length === 0) {
                container.innerHTML = '<div class="text-muted small text-center py-2">No subtasks for this task</div>';
                if (ratio) ratio.textContent = '0/0';
                return;
            }
            const doneCount = items.filter(i => i.completed).length;
            if (ratio) ratio.textContent = `${doneCount}/${items.length}`;

            container.innerHTML = items.map(item => `
                <div class="focus-checklist-item ${item.completed ? 'done' : ''}">
                    <input type="checkbox" class="form-check-input" ${item.completed ? 'checked' : ''} onchange="toggleFocusCheckItem(${item.id}, this)">
                    <span class="flex-grow-1">${item.name}</span>
                </div>
            `).join('');
        } catch (e) {
            console.error(e);
        }
    }

    async function toggleFocusCheckItem(itemId, cb) {
        try {
            await fetch(`/checklist-items/${itemId}/update-status`, { headers: { 'Accept': 'application/json' } });
            cb.closest('.focus-checklist-item').classList.toggle('done', cb.checked);
        } catch (e) {
            console.error(e);
        }
    }

    async function completeFocusTaskAndNext() {
        if (!currentFocusTaskId) {
            closeFocusWorkstation();
            return;
        }

        try {
            await fetch(`/tasks/${currentFocusTaskId}/update-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ status: 'completed' })
            });
            closeFocusWorkstation();
            window.location.reload();
        } catch (e) {
            console.error(e);
        }
    }
</script>
