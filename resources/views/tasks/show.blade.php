@extends('layouts.app')

@section('title', $task->title . ' - Task Details')

@push('styles')
<style>
/* ── Task Show – minimal ─────────────────────────────────── */
.ts-wrap { padding:20px 24px 48px; max-width:860px; margin:0 auto; }

.ts-topbar { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.ts-back {
    display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:500;
    color:#8b8d98; text-decoration:none;
}
.ts-back:hover { color:#7c3aed; }
.ts-id { font-size:11px; font-weight:600; letter-spacing:.06em; color:#c1c4cc; text-transform:uppercase; }
.ts-top-actions { margin-left:auto; display:flex; gap:6px; }
.ts-icon-btn {
    display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:6px;
    border:1px solid #e5e7eb; background:white; color:#6b6f78; font-size:12px; font-weight:600;
    text-decoration:none; cursor:pointer; transition:all .12s;
}
.ts-icon-btn:hover { border-color:#7c3aed; color:#7c3aed; background:#f7f5ff; }
.ts-icon-btn.danger:hover { border-color:#e5484d; color:#e5484d; background:#fef2f2; }

.ts-title {
    font-size:22px; font-weight:700; color:#1f2328; line-height:1.35;
    margin:0 0 10px; word-break:break-word;
}
.ts-title.is-done { color:#9ca0aa; text-decoration:line-through; text-decoration-color:#c7cad1; }

.ts-attr-line { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:6px; }
.ts-chip {
    display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:20px;
    font-size:11px; font-weight:600; cursor:pointer; border:none;
}
.ts-chip.to_do       { background:#eef0f2; color:#6b6f78; }
.ts-chip.in_progress { background:#ece9fd; color:#7c3aed; }
.ts-chip.on_hold     { background:#fdf4de; color:#ad6800; }
.ts-chip.in_review   { background:#e2f0fd; color:#0b6bcb; }
.ts-chip.completed   { background:#e3f5ec; color:#29774b; }
.ts-chip:hover { filter:brightness(.96); }
.ts-dot { width:7px; height:7px; border-radius:50%; background:currentColor; }
.ts-prio { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.4px; }
.ts-prio.high { color:#e5484d; } .ts-prio.medium { color:#ad6800; } .ts-prio.low { color:#29774b; }
.ts-attr-sep { color:#dfe1e6; }
.ts-attr { font-size:12.5px; color:#6b6f78; display:inline-flex; align-items:center; gap:5px; }
.ts-attr a { color:#7c3aed; text-decoration:none; }
.ts-attr a:hover { text-decoration:underline; }
.ts-attr.overdue { color:#e5484d; font-weight:600; }

.ts-progress { display:flex; align-items:center; gap:10px; margin:14px 0 4px; }
.ts-progress-bar { flex:1; height:5px; background:#eef0f2; border-radius:4px; overflow:hidden; }
.ts-progress-fill { height:100%; background:#30a46c; border-radius:4px; }
.ts-progress-lbl { font-size:11.5px; font-weight:600; color:#6b6f78; white-space:nowrap; }

.ts-divider { border:none; border-top:1px solid #eef0f2; margin:20px 0; }

.ts-h {
    font-size:12px; font-weight:700; color:#8b8d98; text-transform:uppercase;
    letter-spacing:.5px; margin:0 0 10px;
}
.ts-description { font-size:14px; line-height:1.8; color:#3d4149; }
.ts-description p { margin:0 0 10px; }
.ts-empty { color:#c1c4cc; font-size:13px; font-style:italic; }
.ts-details { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:8px 20px; }
.ts-detail-lbl { font-size:10.5px; font-weight:600; color:#aeb2ba; text-transform:uppercase; letter-spacing:.4px; }
.ts-detail-val { font-size:13px; color:#1f2328; margin-top:1px; }

.ts-sub-row {
    display:flex; align-items:center; gap:9px; padding:8px 10px; border-radius:6px;
    text-decoration:none;
}
.ts-sub-row:hover { background:#f7f8fa; }
.ts-sub-check {
    width:16px; height:16px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:13px;
    color:#c1c4cc;
}
.ts-sub-check.done { color:#30a46c; }
.ts-sub-title { flex:1; font-size:13.5px; color:#1f2328; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ts-sub-row.is-done .ts-sub-title { color:#9ca0aa; text-decoration:line-through; }

.ts-cl-item {
    display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:6px;
}
.ts-cl-item:hover { background:#f7f8fa; }
.ts-cl-item.completed .ts-cl-text { text-decoration:line-through; color:#9ca0aa; }
.ts-cl-check {
    width:17px; height:17px; border-radius:5px; border:1.5px solid #d3d7de; cursor:pointer;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
    transition:all .12s; color:#fff; font-size:11px; background:white; padding:0;
}
.ts-cl-check.checked { background:#7c3aed; border-color:#7c3aed; }
.ts-cl-text { flex:1; font-size:13.5px; color:#3d4149; }
.ts-cl-del {
    opacity:0; background:none; border:none; cursor:pointer; color:#c1c4cc;
    padding:4px; border-radius:4px; font-size:12px;
}
.ts-cl-item:hover .ts-cl-del { opacity:1; }
.ts-cl-del:hover { color:#e5484d; }
.ts-cl-add { display:flex; gap:8px; margin-top:10px; }
.ts-cl-input {
    flex:1; border:1px solid #e5e7eb; border-radius:6px; padding:7px 10px;
    font-size:13px; outline:none; color:#1f2328;
}
.ts-cl-input:focus { border-color:#7c3aed; }
.ts-cl-input::placeholder { color:#aeb2ba; }
.ts-cl-btn {
    display:inline-flex; align-items:center; gap:5px; padding:7px 14px; border-radius:6px;
    background:#7c3aed; color:#fff; font-size:12.5px; font-weight:600; border:none;
    cursor:pointer; white-space:nowrap;
}
.ts-cl-btn:hover { background:#6d28d9; }

/* Status modal — minimal */
.ts-modal-overlay {
    position:fixed; inset:0; background:rgba(20,22,28,.45); z-index:9000;
    display:flex; align-items:center; justify-content:center;
}
.ts-modal-box { background:#fff; border-radius:10px; width:400px; max-width:94vw; overflow:hidden; }
.ts-modal-head {
    padding:14px 18px; border-bottom:1px solid #eef0f2;
    display:flex; align-items:center; justify-content:space-between;
}
.ts-modal-head h5 { margin:0; font-size:14px; font-weight:700; color:#1f2328; }
.ts-modal-x {
    background:none; border:none; color:#8b8d98; cursor:pointer; font-size:15px;
    width:28px; height:28px; border-radius:6px; display:flex; align-items:center; justify-content:center;
}
.ts-modal-x:hover { background:#f0f1f3; color:#1f2328; }
.ts-modal-body { padding:14px 18px; }
.ts-status-opt {
    display:flex; align-items:center; gap:10px; padding:9px 12px;
    border:1px solid #e5e7eb; border-radius:8px; cursor:pointer; margin-bottom:6px;
}
.ts-status-opt:last-child { margin-bottom:0; }
.ts-status-opt:hover { border-color:#c4b5fd; }
.ts-status-opt.selected { border-color:#7c3aed; background:#f7f5ff; }
.ts-status-opt input { display:none; }
.ts-modal-foot { padding:12px 18px; border-top:1px solid #eef0f2; display:flex; justify-content:flex-end; gap:8px; }
.ts-modal-cancel {
    padding:7px 16px; border-radius:6px; border:1px solid #e5e7eb; background:#fff;
    font-size:12.5px; font-weight:600; color:#3d4149; cursor:pointer;
}
.ts-modal-save {
    padding:7px 16px; border-radius:6px; border:none; background:#7c3aed; color:#fff;
    font-size:12.5px; font-weight:600; cursor:pointer;
}
.ts-modal-save:hover { background:#6d28d9; }

@media(max-width:640px) {
    .ts-wrap { padding:14px 14px 40px; }
    .ts-title { font-size:18px; }
}
</style>
@endpush

@section('content')
@php
    $statusLabel = ucwords(str_replace('_', ' ', $task->status));

    $checklistTotal = $task->checklistItems->count();
    $checklistDone  = $task->checklistItems->where('completed', true)->count();

    $overdue = $task->due_date
        && \Carbon\Carbon::parse($task->due_date)->startOfDay()->lt(now()->startOfDay())
        && $task->status !== 'completed';

    $progressPct = $task->status === 'completed' ? 100
        : (int) round($task->progressPercent());

    $wEff = $task->effectiveWeight();
    $wManual = max(0, (float) ($task->weight ?? 1));
    $wFmt = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.');
@endphp

<div class="ts-wrap">

    <div class="ts-topbar">
        @if($task->project)
            <a href="{{ route('projects.tasks.index', $task->project) }}" class="ts-back">
                <i class="bi bi-arrow-left"></i> {{ $task->project->name }}
            </a>
        @else
            <a href="{{ route('tasks.index') }}" class="ts-back">
                <i class="bi bi-arrow-left"></i> Tasks
            </a>
        @endif
        <span class="ts-id">TASK-{{ str_pad($task->id, 4, '0', STR_PAD_LEFT) }}</span>
        <div class="ts-top-actions">
            <a href="{{ route('tasks.edit', $task->id) }}" class="ts-icon-btn">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <button class="ts-icon-btn danger" onclick="confirmDelete()">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>

    <h1 class="ts-title {{ $task->status === 'completed' ? 'is-done' : '' }}">{{ $task->title }}</h1>

    <div class="ts-attr-line">
        <button class="ts-chip {{ $task->status }}" onclick="openStatusModal()" title="Change status">
            <span class="ts-dot"></span>{{ $statusLabel }}
        </button>
        <span class="ts-prio {{ $task->priority }}">{{ $task->priority }}</span>
        @if($task->due_date)
            <span class="ts-attr-sep">·</span>
            <span class="ts-attr {{ $overdue ? 'overdue' : '' }}" title="{{ \Carbon\Carbon::parse($task->due_date)->format('M d, Y') }}">
                <i class="bi bi-calendar-event"></i>
                {{ \Carbon\Carbon::parse($task->due_date)->format('M d') }}
                @if($overdue)
                    · Overdue
                @endif
            </span>
        @endif
        @if($task->user)
            <span class="ts-attr-sep">·</span>
            <span class="ts-attr"><i class="bi bi-person"></i>{{ $task->user->name }}</span>
        @endif
        @if($task->parent)
            <span class="ts-attr-sep">·</span>
            <span class="ts-attr">Subtask of <a href="{{ route('tasks.show', $task->parent) }}">{{ $task->parent->title }}</a></span>
        @endif
    </div>

    <div class="ts-progress">
        <div class="ts-progress-bar"><div class="ts-progress-fill" style="width:{{ $progressPct }}%;"></div></div>
        <span class="ts-progress-lbl">{{ $progressPct }}%</span>
    </div>

    @if($task->description)
        <hr class="ts-divider">
        <div class="ts-description">{!! $task->description !!}</div>
    @endif

    @if($task->children->count() > 0)
        <hr class="ts-divider">
        <h2 class="ts-h">Subtasks ({{ $task->children->where('status', 'completed')->count() }}/{{ $task->children->count() }})</h2>
        <div>
            @foreach($task->children as $child)
                <a href="{{ route('tasks.show', $child) }}" class="ts-sub-row {{ $child->status === 'completed' ? 'is-done' : '' }}">
                    <span class="ts-sub-check {{ $child->status === 'completed' ? 'done' : '' }}">
                        <i class="bi {{ $child->status === 'completed' ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
                    </span>
                    <span class="ts-sub-title">{{ $child->title }}</span>
                </a>
            @endforeach
        </div>
    @endif

    <hr class="ts-divider">
    <h2 class="ts-h">
        Checklist
        @if($checklistTotal > 0)
            <span style="font-weight:400; text-transform:none; letter-spacing:0;">· {{ $checklistDone }}/{{ $checklistTotal }}</span>
        @endif
    </h2>
    <div id="cl-items">
        @forelse($task->checklistItems as $item)
            <div class="ts-cl-item {{ $item->completed ? 'completed' : '' }}" data-id="{{ $item->id }}">
                <button class="ts-cl-check {{ $item->completed ? 'checked' : '' }}"
                     onclick="toggleChecklistItem({{ $item->id }})">
                    @if($item->completed)
                        <i class="bi bi-check" style="font-size:11px;"></i>
                    @endif
                </button>
                <div class="ts-cl-text">{{ $item->name }}</div>
                <button class="ts-cl-del" onclick="deleteChecklistItem({{ $item->id }})" title="Delete">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        @empty
            <div id="cl-empty" class="ts-empty">No checklist items yet.</div>
        @endforelse
    </div>
    <form class="ts-cl-add" onsubmit="addChecklistItem(event)">
        <input type="text" class="ts-cl-input" id="cl-input" placeholder="Add checklist item…" required>
        <button type="submit" class="ts-cl-btn"><i class="bi bi-plus"></i> Add</button>
    </form>

    <hr class="ts-divider">
    <h2 class="ts-h">Details</h2>
    <div class="ts-details">
        @if($task->project)
            <div>
                <div class="ts-detail-lbl">Project</div>
                <div class="ts-detail-val"><a href="{{ route('projects.show', $task->project) }}" style="color:#7c3aed;text-decoration:none;">{{ $task->project->name }}</a></div>
            </div>
        @endif
        @if($task->estimated_hours)
            <div>
                <div class="ts-detail-lbl">Estimated</div>
                <div class="ts-detail-val">{{ $task->estimatedLabel() }}</div>
            </div>
        @endif
        @if(((float) ($task->weight ?? 1)) !== 1.0 || $task->auto_weight)
            <div>
                <div class="ts-detail-lbl">Weight</div>
                <div class="ts-detail-val">×{{ $wFmt($wEff) }} <span style="color:#8b8d98;font-size:11.5px;">({{ $task->auto_weight ? 'auto' : 'manual' }})</span></div>
            </div>
        @endif
        @php $spent = $task->totalTimeSeconds(); @endphp
        @if($spent > 0)
            <div>
                <div class="ts-detail-lbl">Time spent</div>
                <div class="ts-detail-val">{{ \App\Models\TimeEntry::formatDuration($spent) }}</div>
            </div>
        @endif
        <div>
            <div class="ts-detail-lbl">Created</div>
            <div class="ts-detail-val">{{ $task->created_at->format('M d, Y') }}</div>
        </div>
        @if($task->updated_at->ne($task->created_at))
            <div>
                <div class="ts-detail-lbl">Updated</div>
                <div class="ts-detail-val">{{ $task->updated_at->format('M d, Y') }}</div>
            </div>
        @endif
    </div>

</div>

{{-- Delete form --}}
<form id="deleteForm" action="{{ route('tasks.destroy', $task->id) }}" method="POST" style="display:none;">
    @csrf @method('DELETE')
</form>

{{-- Status Modal --}}
<div id="statusModal" class="ts-modal-overlay" style="display:none;" onclick="if(event.target===this)closeStatusModal()">
    <div class="ts-modal-box">
        <div class="ts-modal-head">
            <h5>Change status</h5>
            <button class="ts-modal-x" onclick="closeStatusModal()"><i class="bi bi-x"></i></button>
        </div>
        <div class="ts-modal-body">
            @foreach([
                'to_do'       => 'To Do',
                'in_progress' => 'In Progress',
                'on_hold'     => 'On Hold',
                'in_review'   => 'In Review',
                'completed'   => 'Completed',
            ] as $val => $label)
            <div class="ts-status-opt {{ $task->status === $val ? 'selected' : '' }}" onclick="selectStatus('{{ $val }}', this)">
                <span class="ts-chip {{ $val }}"><span class="ts-dot"></span>{{ $label }}</span>
            </div>
            @endforeach
        </div>
        <div class="ts-modal-foot">
            <button class="ts-modal-cancel" onclick="closeStatusModal()">Cancel</button>
            <button class="ts-modal-save" onclick="saveStatus()">Update</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const TASK_ID = '{{ $task->id }}';
const CSRF    = '{{ csrf_token() }}';
let selectedStatus = '{{ $task->status }}';

/* ── Status modal ── */
function openStatusModal() {
    document.getElementById('statusModal').style.display = 'flex';
}
function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}
function selectStatus(val, el) {
    selectedStatus = val;
    document.querySelectorAll('.ts-status-opt').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
}
function saveStatus() {
    const current = '{{ $task->status }}';
    if (selectedStatus === current) { closeStatusModal(); return; }
    fetch(`/tasks/${TASK_ID}/update-status`, {
        method: 'POST',
        headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ status: selectedStatus })
    })
    .then(r => r.json())
    .then(() => { location.reload(); })
    .catch(() => showToast('Failed to update status', false));
}

/* ── Delete ── */
function confirmDelete() {
    if (confirm('Delete this task? This action cannot be undone.')) {
        document.getElementById('deleteForm').submit();
    }
}

/* ── Toast ── */
function showToast(msg, ok = true) {
    const d = document.createElement('div');
    d.style.cssText = `position:fixed;bottom:22px;left:50%;transform:translateX(-50%);z-index:9999;
        padding:9px 18px;border-radius:8px;font-size:13px;color:#fff;
        background:${ok?'#1f2328':'#e5484d'};box-shadow:0 4px 16px rgba(0,0,0,.2);`;
    d.textContent = msg;
    document.body.appendChild(d);
    setTimeout(() => d.remove(), 3000);
}

/* ── Checklist ── */
function updateChecklistUI() {
    const items = document.querySelectorAll('#cl-items .ts-cl-item');
    const done  = document.querySelectorAll('#cl-items .ts-cl-item.completed');
    const empty = document.getElementById('cl-empty');
    if (empty) empty.style.display = items.length === 0 ? 'block' : 'none';
}

function toggleChecklistItem(id) {
    const row = document.querySelector(`#cl-items [data-id="${id}"]`);
    const box = row.querySelector('.ts-cl-check');
    fetch(`/checklist-items/${id}/update-status`, {
        headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF }
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            row.classList.toggle('completed');
            box.classList.toggle('checked');
            box.innerHTML = row.classList.contains('completed')
                ? '<i class="bi bi-check" style="font-size:11px;"></i>' : '';
        }
    })
    .catch(() => showToast('Failed to update item', false));
}

function addChecklistItem(e) {
    e.preventDefault();
    const input = document.getElementById('cl-input');
    const name  = input.value.trim();
    if (!name) return;

    fetch('/checklist-items', {
        method: 'POST',
        headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ task_id: TASK_ID, name })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const empty = document.getElementById('cl-empty');
            if (empty) empty.remove();
            const container = document.getElementById('cl-items');
            const div = document.createElement('div');
            div.className = 'ts-cl-item';
            div.setAttribute('data-id', d.data.id);
            div.innerHTML = `
                <button class="ts-cl-check" onclick="toggleChecklistItem(${d.data.id})"></button>
                <div class="ts-cl-text"></div>
                <button class="ts-cl-del" onclick="deleteChecklistItem(${d.data.id})" title="Delete">
                    <i class="bi bi-trash"></i>
                </button>`;
            div.querySelector('.ts-cl-text').textContent = d.data.name;
            container.appendChild(div);
            input.value = '';
            updateChecklistUI();
            showToast('Item added');
        }
    })
    .catch(() => showToast('Failed to add item', false));
}

function deleteChecklistItem(id) {
    if (!confirm('Delete this checklist item?')) return;
    fetch(`/checklist-items/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF }
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.querySelector(`#cl-items [data-id="${id}"]`).remove();
            updateChecklistUI();
            showToast('Item removed');
        }
    })
    .catch(() => showToast('Failed to delete item', false));
}

document.addEventListener('DOMContentLoaded', updateChecklistUI);
</script>
@endpush
