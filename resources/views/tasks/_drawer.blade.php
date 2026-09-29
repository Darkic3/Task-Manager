{{-- Slide-over Quick Peek Drawer Component --}}
<div class="task-drawer-overlay" id="taskDrawerOverlay" onclick="closeTaskDrawer()"></div>

<div class="task-drawer" id="taskDrawer" role="dialog" aria-modal="true" aria-labelledby="drawerTaskTitle">
    {{-- Drawer Loading State --}}
    <div class="drawer-loading-indicator" id="drawerLoading">
        <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
        <span class="small text-muted">{{ __('Loading task details…') }}</span>
    </div>

    <form id="taskDrawerForm" onsubmit="saveTaskDrawer(event)">
        @csrf
        <input type="hidden" id="drawerTaskId" name="id">

        {{-- Drawer Header --}}
        <div class="drawer-header d-flex align-items-center justify-content-between p-3 border-bottom">
            <div class="d-flex align-items-center gap-2">
                {{-- Quick Status Selector --}}
                <select id="drawerTaskStatus" name="status" class="form-select form-select-sm drawer-status-select" onchange="saveTaskDrawerField('status')">
                    <option value="to_do">⚪ {{ __('To Do') }}</option>
                    <option value="in_progress">🟡 {{ __('In Progress') }}</option>
                    <option value="on_hold">🟠 {{ __('On Hold') }}</option>
                    <option value="in_review">🔵 {{ __('In Review') }}</option>
                    <option value="completed">🟢 {{ __('Completed') }}</option>
                </select>

                {{-- Quick Priority Selector --}}
                <select id="drawerTaskPriority" name="priority" class="form-select form-select-sm drawer-priority-select" onchange="saveTaskDrawerField('priority')">
                    <option value="low">🟢 {{ __('Low') }}</option>
                    <option value="medium">🟡 {{ __('Medium') }}</option>
                    <option value="high">🔴 {{ __('High') }}</option>
                </select>
            </div>

            <div class="d-flex align-items-center gap-2">
                {{-- Start Timer for this task --}}
                <button type="button" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1" id="drawerTimerBtn" onclick="startDrawerTaskTimer()" title="{{ __('Start tracking time on this task') }}">
                    <i class="bi bi-play-fill"></i> {{ __('Track Time') }}
                </button>

                {{-- Full View Link --}}
                <a href="#" id="drawerFullViewLink" class="btn btn-sm btn-light border text-muted" title="{{ __('Open full details page') }}">
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>

                {{-- Close Button --}}
                <button type="button" class="btn-close ms-1" onclick="closeTaskDrawer()" aria-label="{{ __('Close') }}"></button>
            </div>
        </div>

        {{-- Drawer Body --}}
        <div class="drawer-body p-4" style="overflow-y: auto; max-height: calc(100vh - 130px);">
            
            {{-- Title Input --}}
            <div class="mb-4">
                <input type="text" id="drawerTaskTitle" name="title" class="form-control form-control-lg drawer-title-input" placeholder="{{ __('Task title…') }}" required onblur="saveTaskDrawerField('title')">
            </div>

            {{-- Meta Grid --}}
            <div class="drawer-meta-grid p-3 rounded-3 bg-light border mb-4">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="drawer-meta-label">{{ __('Project') }}</label>
                        <select id="drawerTaskProject" name="project_id" class="form-select form-select-sm" onchange="saveTaskDrawerField('project_id')">
                            <option value="">{{ __('No Project') }}</option>
                            @if(isset($projects))
                                @foreach($projects as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <div class="col-sm-6">
                        <label class="drawer-meta-label">{{ __('Due Date') }}</label>
                        <input type="date" id="drawerTaskDueDate" name="due_date" class="form-control form-control-sm" onchange="saveTaskDrawerField('due_date')">
                    </div>

                    <div class="col-sm-6">
                        <label class="drawer-meta-label">{{ __('Estimate (Hrs / Mins)') }}</label>
                        <div class="d-flex gap-2">
                            <input type="number" id="drawerTaskEstHours" name="est_hours" class="form-control form-control-sm" placeholder="{{ __('Hrs') }}" min="0" max="999" onchange="saveTaskDrawerField('estimate')">
                            <input type="number" id="drawerTaskEstMins" name="est_minutes" class="form-control form-control-sm" placeholder="{{ __('Min') }}" min="0" max="59" onchange="saveTaskDrawerField('estimate')">
                        </div>
                    </div>

                    <div class="col-sm-6">
                        <label class="drawer-meta-label">{{ __('Created') }}</label>
                        <div class="small text-muted pt-1" id="drawerTaskCreated">{{ __('Just now') }}</div>
                    </div>
                </div>
            </div>

            {{-- Checklist / Subtasks Section --}}
            <div class="drawer-section mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check2-square text-primary"></i>
                        <span class="fw-semibold small text-uppercase">{{ __('Checklist & Subtasks') }}</span>
                    </div>
                    <span class="badge bg-light text-dark border small" id="drawerChecklistProgress">0%</span>
                </div>

                {{-- Progress Bar --}}
                <div class="progress mb-3" style="height: 5px;">
                    <div class="progress-bar bg-success" id="drawerProgressBar" role="progressbar" style="width: 0%"></div>
                </div>

                {{-- Checklist Items Container --}}
                <div class="drawer-checklist-items mb-3" id="drawerChecklistItems">
                    {{-- Rendered dynamically via JS --}}
                </div>

                {{-- Add New Checklist Item Input --}}
                <div class="input-group input-group-sm">
                    <input type="text" id="drawerNewCheckItemName" class="form-control" placeholder="{{ __('+ Add a subtask or checklist item… (Press Enter)') }}" onkeydown="if(event.key==='Enter'){event.preventDefault();addDrawerChecklistItem();}">
                    <button type="button" class="btn btn-outline-primary" onclick="addDrawerChecklistItem()">{{ __('Add') }}</button>
                </div>
            </div>

            {{-- Description Section --}}
            <div class="drawer-section mb-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-text-paragraph text-secondary"></i>
                    <span class="fw-semibold small text-uppercase">{{ __('Description / Notes') }}</span>
                </div>
                <textarea id="drawerTaskDescription" name="description" class="form-control drawer-desc-textarea" rows="4" placeholder="{{ __('Add detailed notes or requirements for this task…') }}" onblur="saveTaskDrawerField('description')"></textarea>
            </div>

        </div>

        {{-- Drawer Footer --}}
        <div class="drawer-footer d-flex align-items-center justify-content-between p-3 border-top bg-light">
            <button type="button" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1" onclick="deleteDrawerTask()">
                <i class="bi bi-trash"></i> {{ __('Delete') }}
            </button>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted" id="drawerSaveStatus">{{ __('All changes saved') }}</span>
                <button type="button" class="btn btn-sm btn-secondary" onclick="closeTaskDrawer()">{{ __('Close') }}</button>
            </div>
        </div>
    </form>
</div>

<style>
    /* ─── Slide-over Quick Peek Drawer Styles ─── */
    .task-drawer-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(2px);
        z-index: 1040;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .task-drawer-overlay.open {
        opacity: 1;
        visibility: visible;
    }

    .task-drawer {
        position: fixed;
        top: 0; right: 0; bottom: 0;
        width: 540px;
        max-width: 95vw;
        background: white;
        z-index: 1050;
        box-shadow: -10px 0 30px rgba(0,0,0,0.15);
        transform: translateX(100%);
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
    }
    .task-drawer.open {
        transform: translateX(0);
    }

    html[dir="rtl"] .task-drawer {
        right: auto;
        left: 0;
        box-shadow: 10px 0 30px rgba(0,0,0,0.15);
        transform: translateX(-100%);
    }
    html[dir="rtl"] .task-drawer.open {
        transform: translateX(0);
    }

    .drawer-loading-indicator {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(255,255,255,0.85);
        z-index: 1060;
        display: none;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .drawer-loading-indicator.show {
        display: flex;
    }

    .drawer-title-input {
        font-size: 1.25rem;
        font-weight: 600;
        border-color: transparent;
        background: transparent;
        padding-left: 0;
        padding-right: 0;
        box-shadow: none !important;
        border-bottom: 2px solid transparent;
        border-radius: 0;
    }
    .drawer-title-input:focus {
        border-bottom-color: var(--primary-600);
        background: transparent;
    }

    .drawer-meta-label {
        font-size: 0.72rem;
        text-transform: uppercase;
        font-weight: 600;
        color: var(--gray-500);
        margin-bottom: 0.25rem;
        display: block;
    }

    .drawer-status-select, .drawer-priority-select {
        font-weight: 500;
        border-radius: var(--radius-md);
    }

    .drawer-checklist-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.375rem 0.5rem;
        border-radius: var(--radius-sm);
        transition: background 0.15s ease;
    }
    .drawer-checklist-item:hover {
        background: var(--gray-50);
    }
    .drawer-checklist-item.is-done span {
        text-decoration: line-through;
        color: var(--gray-400);
    }

    .drawer-desc-textarea {
        resize: vertical;
        font-size: 0.875rem;
        border-color: var(--gray-200);
    }
</style>

<script>
    const DRAWER_CSRF = '{{ csrf_token() }}';
    let currentDrawerTask = null;

    // Open Drawer by Task ID
    async function openTaskDrawer(taskId) {
        const overlay = document.getElementById('taskDrawerOverlay');
        const drawer = document.getElementById('taskDrawer');
        const loader = document.getElementById('drawerLoading');

        if (!drawer) return;

        overlay.classList.add('open');
        drawer.classList.add('open');
        if (loader) loader.classList.add('show');

        try {
            const res = await fetch(`/tasks/${taskId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': DRAWER_CSRF
                }
            });
            if (!res.ok) throw new Error('Task load failed');
            const data = await res.json();
            currentDrawerTask = data.task;
            populateTaskDrawer(data.task);
        } catch (e) {
            console.error('Error fetching task', e);
            showDrawerStatus('Failed to load task details');
        } finally {
            if (loader) loader.classList.remove('show');
        }
    }

    // Close Drawer
    function closeTaskDrawer() {
        const overlay = document.getElementById('taskDrawerOverlay');
        const drawer = document.getElementById('taskDrawer');
        if (overlay) overlay.classList.remove('open');
        if (drawer) drawer.classList.remove('open');
        currentDrawerTask = null;
    }

    // Escape Key listener to close drawer
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeTaskDrawer();
        }
    });

    // Populate form fields
    function populateTaskDrawer(task) {
        document.getElementById('drawerTaskId').value = task.id;
        document.getElementById('drawerTaskTitle').value = task.title || '';
        document.getElementById('drawerTaskStatus').value = task.status || 'to_do';
        document.getElementById('drawerTaskPriority').value = task.priority || 'medium';
        document.getElementById('drawerTaskProject').value = task.project_id || '';
        document.getElementById('drawerTaskDueDate').value = task.due_date || '';
        document.getElementById('drawerTaskEstHours').value = task.est_hours || '';
        document.getElementById('drawerTaskEstMins').value = task.est_minutes || '';
        document.getElementById('drawerTaskDescription').value = task.description || '';
        document.getElementById('drawerTaskCreated').textContent = task.created_at_human || 'Recently';
        document.getElementById('drawerFullViewLink').href = task.show_url || `/tasks/${task.id}`;

        renderDrawerChecklist(task.checklist_items || []);
        showDrawerStatus('All changes saved');
    }

    // Render Checklist
    function renderDrawerChecklist(items) {
        const container = document.getElementById('drawerChecklistItems');
        const progressBadge = document.getElementById('drawerChecklistProgress');
        const progressBar = document.getElementById('drawerProgressBar');

        if (!container) return;
        container.innerHTML = '';

        if (!items || items.length === 0) {
            progressBadge.textContent = '0%';
            progressBar.style.width = '0%';
            return;
        }

        const completedCount = items.filter(i => i.completed).length;
        const percent = Math.round((completedCount / items.length) * 100);
        progressBadge.textContent = `${completedCount}/${items.length} (${percent}%)`;
        progressBar.style.width = `${percent}%`;

        items.forEach(item => {
            const div = document.createElement('div');
            div.className = `drawer-checklist-item ${item.completed ? 'is-done' : ''}`;
            div.innerHTML = `
                <input type="checkbox" class="form-check-input mt-0" ${item.completed ? 'checked' : ''} onchange="toggleDrawerCheckItem(${item.id})">
                <span class="flex-grow-1 small">${item.name}</span>
                <button type="button" class="btn btn-sm text-muted p-0 hover-danger" onclick="deleteDrawerCheckItem(${item.id})">
                    <i class="bi bi-x-lg" style="font-size: 11px;"></i>
                </button>
            `;
            container.appendChild(div);
        });
    }

    // Toggle checklist item
    async function toggleDrawerCheckItem(itemId) {
        try {
            await fetch(`/checklist-items/${itemId}/update-status`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': DRAWER_CSRF }
            });
            if (currentDrawerTask) {
                const item = currentDrawerTask.checklist_items.find(i => i.id === itemId);
                if (item) item.completed = !item.completed;
                renderDrawerChecklist(currentDrawerTask.checklist_items);
            }
        } catch (e) {
            console.error('Toggle checklist error', e);
        }
    }

    // Add checklist item
    async function addDrawerChecklistItem() {
        const input = document.getElementById('drawerNewCheckItemName');
        const name = input.value.trim();
        if (!name || !currentDrawerTask) return;

        try {
            const res = await fetch('/checklist-items', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': DRAWER_CSRF
                },
                body: JSON.stringify({
                    task_id: currentDrawerTask.id,
                    name: name
                })
            });
            if (res.ok) {
                const data = await res.json();
                input.value = '';
                if (!currentDrawerTask.checklist_items) currentDrawerTask.checklist_items = [];
                currentDrawerTask.checklist_items.push(data.data);
                renderDrawerChecklist(currentDrawerTask.checklist_items);
            }
        } catch (e) {
            console.error('Add checklist item failed', e);
        }
    }

    // Delete checklist item
    async function deleteDrawerCheckItem(itemId) {
        try {
            await fetch(`/checklist-items/${itemId}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': DRAWER_CSRF }
            });
            if (currentDrawerTask) {
                currentDrawerTask.checklist_items = currentDrawerTask.checklist_items.filter(i => i.id !== itemId);
                renderDrawerChecklist(currentDrawerTask.checklist_items);
            }
        } catch (e) {
            console.error('Delete checklist item failed', e);
        }
    }

    // Save field on change
    async function saveTaskDrawerField(field) {
        if (!currentDrawerTask) return;
        showDrawerStatus('Saving changes…');

        const taskId = document.getElementById('drawerTaskId').value;
        const payload = {
            title: document.getElementById('drawerTaskTitle').value,
            status: document.getElementById('drawerTaskStatus').value,
            priority: document.getElementById('drawerTaskPriority').value,
            project_id: document.getElementById('drawerTaskProject').value || null,
            due_date: document.getElementById('drawerTaskDueDate').value || null,
            est_hours: document.getElementById('drawerTaskEstHours').value || null,
            est_minutes: document.getElementById('drawerTaskEstMins').value || null,
            description: document.getElementById('drawerTaskDescription').value
        };

        try {
            const res = await fetch(`/tasks/${taskId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': DRAWER_CSRF
                },
                body: JSON.stringify(payload)
            });
            if (res.ok) {
                showDrawerStatus('Saved');
                // Update active card on page if exists
                updatePageTaskElement(taskId, payload);
            } else {
                showDrawerStatus('Error saving');
            }
        } catch (e) {
            showDrawerStatus('Connection error');
        }
    }

    function saveTaskDrawer(e) {
        e.preventDefault();
        saveTaskDrawerField('all');
    }

    function showDrawerStatus(msg) {
        const el = document.getElementById('drawerSaveStatus');
        if (el) el.textContent = msg;
    }

    // Delete task from drawer
    async function deleteDrawerTask() {
        if (!currentDrawerTask) return;
        if (!confirm('Are you sure you want to delete this task?')) return;

        try {
            const res = await fetch(`/tasks/${currentDrawerTask.id}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': DRAWER_CSRF }
            });
            if (res.ok) {
                closeTaskDrawer();
                window.location.reload();
            }
        } catch (e) {
            console.error('Delete task failed', e);
        }
    }

    // Start timer for task in drawer
    async function startDrawerTaskTimer() {
        if (!currentDrawerTask) return;
        try {
            const res = await fetch('{{ route("time.start") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': DRAWER_CSRF,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    task_id: currentDrawerTask.id,
                    project_id: currentDrawerTask.project_id
                })
            });
            if (res.ok) {
                window.location.reload();
            }
        } catch (e) {
            console.error('Timer start failed', e);
        }
    }

    // Update corresponding DOM elements if present on page
    function updatePageTaskElement(taskId, data) {
        const card = document.querySelector(`.cu-task-card[data-id="${taskId}"]`);
        if (card) {
            const titleEl = card.querySelector('.cu-task-title');
            if (titleEl) titleEl.textContent = data.title;
            card.dataset.status = data.status;
            card.dataset.priority = data.priority;
        }
    }
</script>
