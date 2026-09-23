@extends('layouts.app')

@section('title', 'Edit ' . $task->title)

@push('styles')
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
/* ── Task Edit – minimal ─────────────────────────────────── */
.main-content { padding:20px 24px 48px; background:#fafafa; min-height:100vh; }
.te-wrap { max-width:720px; margin:0 auto; }

.te-topbar { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
.te-back {
    display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:500;
    color:#8b8d98; text-decoration:none; min-width:0;
}
.te-back:hover { color:#7c3aed; }
.te-back span { color:#1f2328; font-weight:600; max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.te-actions { margin-left:auto; display:flex; gap:6px; align-items:center; }
.te-btn {
    display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:6px;
    border:1px solid #e5e7eb; background:white; color:#6b6f78; font-size:12px; font-weight:600;
    text-decoration:none; cursor:pointer; transition:all .12s;
}
.te-btn:hover { border-color:#7c3aed; color:#7c3aed; background:#f7f5ff; }

.te-form {
    background:white; border:1px solid #e5e7eb; border-radius:8px; padding:22px;
}
.te-title-input {
    width:100%; font-size:18px; font-weight:700; color:#1f2328; line-height:1.4;
    padding:8px 10px; margin:14px 0 4px; border:none; border-radius:6px; outline:none;
    background:transparent; word-break:break-word;
}
.te-title-input:focus { background:#f7f8fa; box-shadow:inset 0 0 0 1.5px #7c3aed; }

.te-chip-row { display:flex; align-items:center; flex-wrap:wrap; gap:6px; margin-top:12px; padding-top:12px; border-top:1px solid #f0f1f3; }
.te-sep { color:#dfe1e6; }
.te-inline-lbl { font-size:10.5px; font-weight:700; color:#aeb2ba; text-transform:uppercase; letter-spacing:.4px; margin-right:2px; }

.te-chip-opt { position:relative; }
.te-chip-opt input { position:absolute; opacity:0; width:0; height:0; }
.te-chip {
    display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:20px;
    border:1px solid #e5e7eb; font-size:11.5px; font-weight:600; color:#6b6f78;
    cursor:pointer; user-select:none; transition:all .12s; background:white;
}
.te-chip:hover { border-color:#c4b5fd; color:#7c3aed; }
.te-dot { width:7px; height:7px; border-radius:50%; background:currentColor; flex-shrink:0; }
.chip-to-do       .te-chip-dot { background:#9ca3af; }
.chip-in-progress .te-chip-dot { background:#7c3aed; }
.chip-on-hold     .te-chip-dot { background:#ad6800; }
.chip-in-review   .te-chip-dot { background:#0b6bcb; }
.chip-completed   .te-chip-dot { background:#29774b; }
.chip-to-do       input:checked + .te-chip { color:#6b6f78; border-color:#9ca3af; background:#eef0f2; }
.chip-in-progress input:checked + .te-chip { color:#7c3aed; border-color:#7c3aed; background:#ece9fd; }
.chip-on-hold     input:checked + .te-chip { color:#ad6800; border-color:#ad6800; background:#fdf4de; }
.chip-in-review   input:checked + .te-chip { color:#0b6bcb; border-color:#0b6bcb; background:#e2f0fd; }
.chip-completed   input:checked + .te-chip { color:#29774b; border-color:#29774b; background:#e3f5ec; }
.chip-low    .te-chip-dot { background:#29774b; }
.chip-medium .te-chip-dot { background:#ad6800; }
.chip-high   .te-chip-dot { background:#e5484d; }
.chip-low    input:checked + .te-chip { color:#29774b; border-color:#29774b; background:#e3f5ec; }
.chip-medium input:checked + .te-chip { color:#ad6800; border-color:#ad6800; background:#fdf4de; }
.chip-high   input:checked + .te-chip { color:#e5484d; border-color:#e5484d; background:#fdebec; }

.te-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:16px; }
.te-grid-3 { grid-template-columns:1fr 1fr 1fr; }
.te-field label {
    display:block; font-size:11px; font-weight:600; color:#8b8d98; margin-bottom:5px;
}
.te-input {
    width:100%; border:1px solid #e5e7eb; border-radius:6px; padding:8px 10px;
    font-size:13px; color:#1f2328; background:white; outline:none; box-sizing:border-box;
}
.te-input:focus { border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.08); }
.te-input.is-invalid { border-color:#e5484d; }
.invalid-feedback { display:block; margin-top:4px; font-size:11px; color:#e5484d; font-weight:500; }

.te-editor-wrap {
    border:1px solid #e5e7eb; border-radius:6px; overflow:hidden; margin-top:16px; transition:border-color .12s;
}
.te-editor-wrap:focus-within { border-color:#7c3aed; }
.te-editor-wrap .ql-toolbar { border:none; border-bottom:1px solid #eef0f2; background:#fafbfc; padding:6px 10px; }
.te-editor-wrap .ql-container { border:none; font-family:inherit; font-size:13.5px; }
.te-editor-wrap .ql-editor { min-height:120px; padding:10px 12px; color:#3d4149; line-height:1.7; }
.te-editor-wrap .ql-editor.ql-blank::before { color:#aeb2ba; font-style:normal; left:12px; }

.te-more { margin:16px 0 0; border:1px solid #eef0f2; border-radius:6px; padding:10px 12px; }
.te-more summary {
    font-size:12px; font-weight:600; color:#7c3aed; cursor:pointer; list-style:none;
    display:flex; align-items:center; gap:5px; user-select:none;
}
.te-more summary::-webkit-details-marker { display:none; }
.te-more summary::before { content:'\f282'; font-family:'bootstrap-icons'; font-size:10px; transition:transform .15s; }
.te-more[open] summary::before { transform:rotate(90deg); }

.te-hint { font-size:11px; color:#8b8d98; margin-top:4px; }
.te-hint strong { color:#7c3aed; font-weight:600; }

.te-actions-bar {
    display:flex; align-items:center; justify-content:flex-end; gap:8px;
    margin-top:18px; padding-top:14px; border-top:1px solid #eef0f2;
}
.te-cancel {
    padding:7px 16px; border-radius:6px; border:1px solid #e5e7eb; background:white;
    color:#3d4149; font-size:13px; font-weight:600; text-decoration:none;
}
.te-cancel:hover, .te-cancel:hover { border-color:#c4b5fd; color:#7c3aed; }
.te-save {
    padding:7px 20px; border-radius:6px; border:none; background:#7c3aed; color:white;
    font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px;
}
.te-save:hover { background:#6d28d9; }

.te-danger {
    margin-top:14px; display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap;
    border:1px solid #fdebec; border-radius:6px; padding:12px 16px; background:white;
}
.te-danger-text h6 { font-size:13px; font-weight:600; color:#1f2328; margin:0 0 2px; }
.te-danger-text p  { font-size:12px; color:#8b8d98; margin:0; }
.te-btn-danger {
    padding:6px 16px; border-radius:6px; border:1px solid #fca5a5; background:white;
    color:#e5484d; font-size:12.5px; font-weight:600; cursor:pointer; white-space:nowrap;
    display:inline-flex; align-items:center; gap:6px;
}
.te-btn-danger:hover { background:#e5484d; border-color:#e5484d; color:white; }

@media(max-width:640px) {
    .main-content { padding:14px 14px 40px; }
    .te-form { padding:16px; }
    .te-grid, .te-grid-3 { grid-template-columns:1fr; }
    .te-back span { max-width:120px; }
}
</style>
@endpush

@section('content')
<div class="main-content">
<div class="te-wrap">

    <div class="te-topbar">
        <a href="{{ route('tasks.show', $task->id) }}" class="te-back">
            <i class="bi bi-arrow-left"></i> Back to <span>{{ $task->title }}</span>
        </a>
        <div class="te-actions">
            <span class="te-hint">TASK-{{ str_pad($task->id, 4, '0', STR_PAD_LEFT) }}</span>
        </div>
    </div>

    @php
        $statusMap = [
            'to_do'       => ['label'=>'To Do',       'class'=>'to-do',       'ini'=>'to_do'],
            'in_progress' => ['label'=>'In Progress', 'class'=>'in-progress', 'ini'=>'in_progress'],
            'on_hold'     => ['label'=>'On Hold',     'class'=>'on-hold',     'ini'=>'on_hold'],
            'in_review'   => ['label'=>'In Review',   'class'=>'in-review',   'ini'=>'in_review'],
            'completed'   => ['label'=>'Completed',   'class'=>'completed',   'ini'=>'completed'],
        ];
        $priorityMap = ['low','medium','high'];
        [$estH, $estM] = \App\Models\Task::splitHours(old('estimated_hours', $task->estimated_hours));
        $estH = old('est_hours', $estH);
        $estM = old('est_minutes', $estM);
        $wEffNow = $task->effectiveWeight();
        $wFmtNow = rtrim(rtrim(number_format($wEffNow, 2), '0'), '.');
    @endphp

    <form action="{{ route('tasks.update', $task->id) }}" method="POST" id="editTaskForm"
          class="te-form {{ $errors->has('title') ? 'has-errors' : '' }}">

        @csrf
        @method('PUT')

        {{-- Title --}}
        <label for="title" class="te-inline-lbl">Task title</label>
        <input type="text" name="title" id="title"
               class="te-title-input {{ $errors->has('title') ? 'is-invalid' : '' }}"
               value="{{ old('title', $task->title) }}" placeholder="Task title" required>
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror

        {{-- Status + Priority chips --}}
        <div class="te-chip-row">
            <span class="te-inline-lbl">Status</span>
            @foreach($statusMap as $key => $info)
                <span class="te-chip-opt chip-{{ $info['class'] }}">
                    <input type="radio" name="status" id="status_{{ $key }}" value="{{ $key }}"
                        {{ old('status', $task->status) == $key ? 'checked' : '' }}>
                    <label for="status_{{ $key }}" class="te-chip">
                        <span class="te-dot"></span> {{ $info['label'] }}
                    </label>
                </span>
            @endforeach
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="te-chip-row">
            <span class="te-inline-lbl">Priority</span>
            @foreach($priorityMap as $key)
                <span class="te-chip-opt chip-{{ $key }}">
                    <input type="radio" name="priority" id="priority_{{ $key }}" value="{{ $key }}"
                        {{ old('priority', $task->priority) == $key ? 'checked' : '' }}>
                    <label for="priority_{{ $key }}" class="te-chip">
                        <span class="te-dot"></span> {{ ucfirst($key) }}
                    </label>
                </span>
            @endforeach
            @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        {{-- Description --}}
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="te-editor-wrap">
            <div id="quill-editor"></div>
            <textarea name="description" id="description" style="display:none;">{{ old('description', $task->description) }}</textarea>
        </div>

        {{-- Schedule / Assignment grid --}}
        <div class="te-grid">
            <div class="te-field">
                <label for="due_date">Due date</label>
                <input type="date" name="due_date" id="due_date"
                       class="te-input {{ $errors->has('due_date') ? 'is-invalid' : '' }}"
                       value="{{ old('due_date', $task->due_date ? $task->due_date->format('Y-m-d') : '') }}">
                @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="te-field">
                <label>Estimated time</label>
                <div style="display:flex; gap:6px;">
                    <input type="number" name="est_hours" id="est_hours"
                           class="te-input {{ $errors->has('est_hours') ? 'is-invalid' : '' }}"
                           value="{{ $estH }}" min="0" max="999" step="1" placeholder="Hrs" title="Hours">
                    <input type="number" name="est_minutes" id="est_minutes"
                           class="te-input {{ $errors->has('est_minutes') ? 'is-invalid' : '' }}"
                           value="{{ $estM }}" min="0" max="59" step="1" placeholder="Min" title="Minutes">
                </div>
                @error('est_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('est_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="te-field">
                <label for="project_id">Project</label>
                <select name="project_id" id="project_id"
                        class="te-input {{ $errors->has('project_id') ? 'is-invalid' : '' }}">
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}"
                            {{ old('project_id', $task->project_id) == $project->id ? 'selected' : '' }}>
                            {{ $project->name }}
                        </option>
                    @endforeach
                </select>
                @error('project_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="te-field">
                <label for="user_id">Assigned to</label>
                <select name="user_id" id="user_id"
                        class="te-input {{ $errors->has('user_id') ? 'is-invalid' : '' }}">
                    @foreach($users as $user)
                        <option value="{{ $user->id }}"
                            {{ old('user_id', $task->user_id) == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
                @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Advanced options (collapsed) --}}
        <details class="te-more">
            <summary>More options</summary>
            <div class="te-grid te-grid-3" style="margin-top:12px;">
                <div class="te-field">
                    <label for="parent_id">Parent task</label>
                    <select name="parent_id" id="parent_id"
                            class="te-input {{ $errors->has('parent_id') ? 'is-invalid' : '' }}">
                        <option value="">None (top-level)</option>
                        @foreach($parentOptions as $opt)
                            <option value="{{ $opt->id }}"
                                {{ (string) old('parent_id', $task->parent_id ?? '') === (string) $opt->id ? 'selected' : '' }}>
                                {{ $opt->title }}
                            </option>
                        @endforeach
                    </select>
                    @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="te-field">
                    <label for="weight">Weight</label>
                    <input type="number" name="weight" id="weight"
                           class="te-input {{ $errors->has('weight') ? 'is-invalid' : '' }}"
                           value="{{ old('weight', $task->weight ?? 1) }}" min="0" max="99" step="0.25">
                    @error('weight')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="te-hint">Effective now: <strong>×{{ $wFmtNow }}</strong>
                        @if($task->auto_weight && $task->ownTimeSeconds() > 0)
                            (auto-boosted by tracked time)
                        @endif
                    </div>
                </div>
                <div class="te-field">
                    <label>Auto weight</label>
                    <div class="form-check" style="padding-top:6px;">
                        <input type="hidden" name="auto_weight" value="0">
                        <input type="checkbox" name="auto_weight" value="1" class="form-check-input" id="autoWeight"
                            {{ old('auto_weight', $task->auto_weight ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="autoWeight" style="font-size:12px;color:#3d4149;">
                            Increases with time spent
                        </label>
                    </div>
                </div>
            </div>
        </details>

        {{-- Action bar --}}
        <div class="te-actions-bar">
            <a href="{{ route('tasks.show', $task->id) }}" class="te-cancel">Cancel</a>
            <button type="submit" class="te-save">
                <i class="bi bi-check-lg"></i>Save changes
            </button>
        </div>
    </form>

    {{-- Danger Zone — minimal red row (kept per user choice) --}}
    <div class="te-danger">
        <div class="te-danger-text">
            <h6>Delete this task</h6>
            <p>Permanently removes this task and its checklist items. This cannot be undone.</p>
        </div>
        <form action="{{ route('tasks.destroy', $task->id) }}" method="POST" id="deleteForm">
            @csrf
            @method('DELETE')
            <button type="button" class="te-btn-danger" onclick="confirmDelete()">
                <i class="bi bi-trash"></i>Delete task
            </button>
        </form>
    </div>

</div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var quill = new Quill('#quill-editor', {
        theme: 'snow',
        placeholder: 'Describe the task requirements, goals, and any specific instructions…',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                ['blockquote', 'code-block'],
                [{ 'header': 1 }, { 'header': 2 }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link'],
                ['clean']
            ]
        }
    });

    var initialContent = document.getElementById('description').value;
    if (initialContent) {
        quill.root.innerHTML = initialContent;
    }

    quill.on('text-change', function() {
        document.getElementById('description').value = quill.root.innerHTML;
    });

    document.getElementById('editTaskForm').addEventListener('submit', function() {
        document.getElementById('description').value = quill.root.innerHTML;
    });
});

function confirmDelete() {
    if (confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
        document.getElementById('deleteForm').submit();
    }
}
</script>
@endpush
