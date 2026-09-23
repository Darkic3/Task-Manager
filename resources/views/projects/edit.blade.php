@extends('layouts.app')

@section('title', 'Edit ' . $project->name)

@push('styles')
<style>
/* ── Project Edit – minimal ──────────────────────────────── */
.main-content { padding:20px 24px 48px; background:#fafafa; min-height:100vh; }
.pe-wrap { max-width:720px; margin:0 auto; }

.pe-topbar { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
.pe-back {
    display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:500;
    color:#8b8d98; text-decoration:none; min-width:0;
}
.pe-back:hover { color:#7c3aed; }
.pe-back span { color:#1f2328; font-weight:600; max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

.pe-form {
    background:white; border:1px solid #e5e7eb; border-radius:8px; padding:22px;
}
.pe-title-input {
    width:100%; font-size:18px; font-weight:700; color:#1f2328; line-height:1.4;
    padding:8px 10px; margin:4px 0 12px; border:none; border-radius:6px; outline:none;
    background:transparent; word-break:break-word;
}
.pe-title-input:focus { background:#f7f8fa; box-shadow:inset 0 0 0 1.5px #7c3aed; }
.pe-inline-lbl { font-size:10.5px; font-weight:700; color:#aeb2ba; text-transform:uppercase; letter-spacing:.4px; }

.pe-chip-row { display:flex; align-items:center; flex-wrap:wrap; gap:6px; margin-bottom:12px; }
.pe-sep { color:#dfe1e6; }

.pe-chip-opt { position:relative; }
.pe-chip-opt input { position:absolute; opacity:0; width:0; height:0; }
.pe-chip {
    display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:20px;
    border:1px solid #e5e7eb; font-size:11.5px; font-weight:600; color:#6b6f78;
    cursor:pointer; user-select:none; transition:all .12s; background:white;
}
.pe-chip:hover { border-color:#c4b5fd; color:#7c3aed; }
.pe-dot { width:7px; height:7px; border-radius:50%; background:currentColor; flex-shrink:0; }
.chip-not-started .pe-dot { background:#9ca3af; }
.chip-in-progress .pe-dot { background:#7c3aed; }
.chip-completed   .pe-dot { background:#29774b; }
.chip-closed      .pe-dot { background:#aeb2ba; }
.chip-not-started input:checked + .pe-chip { color:#6b6f78; border-color:#9ca3af; background:#eef0f2; }
.chip-in-progress input:checked + .pe-chip { color:#7c3aed; border-color:#7c3aed; background:#ece9fd; }
.chip-completed   input:checked + .pe-chip { color:#29774b; border-color:#29774b; background:#e3f5ec; }
.chip-closed      input:checked + .pe-chip { color:#aeb2ba; border-color:#aeb2ba; background:#f2f3f5; }

.pe-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:14px; }
.pe-field label {
    display:block; font-size:11px; font-weight:600; color:#8b8d98; margin-bottom:5px;
}
.pe-input {
    width:100%; border:1px solid #e5e7eb; border-radius:6px; padding:8px 10px;
    font-size:13px; color:#1f2328; background:white; outline:none; box-sizing:border-box;
}
.pe-input:focus { border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.08); }
.pe-input.is-invalid { border-color:#e5484d; }
.invalid-feedback { display:block; margin-top:4px; font-size:11px; color:#e5484d; font-weight:500; }

.pe-editor-wrap {
    border:1px solid #e5e7eb; border-radius:6px; overflow:hidden; margin-bottom:12px; transition:border-color .12s;
}
.pe-editor-wrap:focus-within { border-color:#7c3aed; }
.pe-editor-wrap .ql-toolbar { border:none; border-bottom:1px solid #eef0f2; background:#fafbfc; padding:6px 10px; }
.pe-editor-wrap .ql-container { border:none; font-family:inherit; font-size:13.5px; }
.pe-editor-wrap .ql-editor { min-height:120px; padding:10px 12px; color:#3d4149; line-height:1.7; }
.pe-editor-wrap .ql-editor.ql-blank::before { color:#aeb2ba; font-style:normal; left:12px; }

.pe-actions-bar {
    display:flex; align-items:center; justify-content:flex-end; gap:8px;
    margin-top:18px; padding-top:14px; border-top:1px solid #eef0f2;
}
.pe-cancel {
    padding:7px 16px; border-radius:6px; border:1px solid #e5e7eb; background:white;
    color:#3d4149; font-size:13px; font-weight:600; text-decoration:none;
}
.pe-cancel:hover { border-color:#c4b5fd; color:#7c3aed; }
.pe-save {
    padding:7px 20px; border-radius:6px; border:none; background:#7c3aed; color:white;
    font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px;
}
.pe-save:hover { background:#6d28d9; }

.pe-danger {
    margin-top:14px; display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap;
    border:1px solid #fdebec; border-radius:6px; padding:12px 16px; background:white;
}
.pe-danger-text h6 { font-size:13px; font-weight:600; color:#1f2328; margin:0 0 2px; }
.pe-danger-text p  { font-size:12px; color:#8b8d98; margin:0; }
.pe-btn-danger {
    padding:6px 16px; border-radius:6px; border:1px solid #fca5a5; background:white;
    color:#e5484d; font-size:12.5px; font-weight:600; cursor:pointer; white-space:nowrap;
    display:inline-flex; align-items:center; gap:6px;
}
.pe-btn-danger:hover { background:#e5484d; border-color:#e5484d; color:white; }

@media(max-width:640px) {
    .main-content { padding:14px 14px 40px; }
    .pe-form { padding:16px; }
    .pe-grid { grid-template-columns:1fr; }
    .pe-back span { max-width:120px; }
}
</style>
@endpush

@section('content')
<div class="main-content">
<div class="pe-wrap">

    <div class="pe-topbar">
        <a href="{{ route('projects.show', $project) }}" class="pe-back">
            <i class="bi bi-arrow-left"></i> Back to <span>{{ $project->name }}</span>
        </a>
    </div>

    <form action="{{ route('projects.update', $project) }}" method="POST" id="editProjectForm" class="pe-form">
        @csrf
        @method('PUT')

        @php
            $statusMap = [
                'not_started' => ['label'=>'Not Started', 'class'=>'not-started'],
                'in_progress' => ['label'=>'In Progress', 'class'=>'in-progress'],
                'completed'   => ['label'=>'Completed',   'class'=>'completed'],
                'closed'      => ['label'=>'Closed',      'class'=>'closed'],
            ];
        @endphp

        {{-- Name --}}
        <label for="name" class="pe-inline-lbl">Project name</label>
        <input type="text" name="name" id="name"
               class="pe-title-input {{ $errors->has('name') ? 'is-invalid' : '' }}"
               value="{{ old('name', $project->name) }}" placeholder="Project name" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror

        {{-- Status chips --}}
        <div class="pe-chip-row">
            <span class="pe-inline-lbl" style="margin-right:2px;">Status</span>
            @foreach($statusMap as $key => $info)
                <span class="pe-chip-opt chip-{{ $info['class'] }}">
                    <input type="radio" name="status" id="status_{{ $key }}" value="{{ $key }}"
                        {{ old('status', $project->status) == $key ? 'checked' : '' }}>
                    <label for="status_{{ $key }}" class="pe-chip">
                        <span class="pe-dot"></span> {{ $info['label'] }}
                    </label>
                </span>
            @endforeach
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        {{-- Description --}}
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="pe-editor-wrap">
            <div id="quill-editor"></div>
            <textarea name="description" id="description" style="display:none;">{{ old('description', $project->description) }}</textarea>
        </div>

        {{-- Parent / Type --}}
        <div class="pe-grid">
            <div class="pe-field">
                <label for="parent_id">Parent project</label>
                <select name="parent_id" id="parent_id"
                        class="pe-input {{ $errors->has('parent_id') ? 'is-invalid' : '' }}">
                    <option value="">None (top-level)</option>
                    @foreach($parentOptions as $opt)
                        <option value="{{ $opt->id }}"
                            {{ (string) old('parent_id', $project->parent_id ?? '') === (string) $opt->id ? 'selected' : '' }}>
                            {{ $opt->name }}
                        </option>
                    @endforeach
                </select>
                @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="pe-field">
                <label for="type">Type</label>
                <select name="type" id="type"
                        class="pe-input {{ $errors->has('type') ? 'is-invalid' : '' }}">
                    @foreach(['project' => 'Project', 'subproject' => 'Sub-project', 'lesson' => 'Lesson', 'chapter' => 'Chapter', 'section' => 'Section'] as $val => $label)
                        <option value="{{ $val }}" {{ old('type', $project->type ?? 'project') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="pe-field">
                <label for="start_date">Start date</label>
                <input type="date" name="start_date" id="start_date"
                       class="pe-input {{ $errors->has('start_date') ? 'is-invalid' : '' }}"
                       value="{{ old('start_date', $project->start_date ? \Carbon\Carbon::parse($project->start_date)->format('Y-m-d') : '') }}">
                @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="pe-field">
                <label for="end_date">End date</label>
                <input type="date" name="end_date" id="end_date"
                       class="pe-input {{ $errors->has('end_date') ? 'is-invalid' : '' }}"
                       value="{{ old('end_date', $project->end_date ? \Carbon\Carbon::parse($project->end_date)->format('Y-m-d') : '') }}">
                @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="pe-field">
                <label for="budget">Budget (USD)</label>
                <input type="number" name="budget" id="budget"
                       class="pe-input {{ $errors->has('budget') ? 'is-invalid' : '' }}"
                       value="{{ old('budget', $project->budget) }}" step="0.01" min="0" placeholder="0.00">
                @error('budget')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Action bar --}}
        <div class="pe-actions-bar">
            <a href="{{ route('projects.show', $project) }}" class="pe-cancel">Cancel</a>
            <button type="submit" class="pe-save">
                <i class="bi bi-check-lg"></i>Save changes
            </button>
        </div>
    </form>

    {{-- Danger Zone — minimal red row (kept per user choice) --}}
    <div class="pe-danger">
        <div class="pe-danger-text">
            <h6>Delete this project</h6>
            <p>Permanently removes all tasks, files, and associated data. This cannot be undone.</p>
        </div>
        <form action="{{ route('projects.destroy', $project) }}" method="POST" id="deleteForm">
            @csrf
            @method('DELETE')
            <button type="button" class="pe-btn-danger" onclick="confirmDelete()">
                <i class="bi bi-trash"></i>Delete project
            </button>
        </form>
    </div>

</div>
</div>
@endsection

@push('scripts')
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editProjectForm');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');

    // Auto-set minimum end date based on start date
    startDateInput.addEventListener('change', function() {
        if (this.value) {
            endDateInput.min = this.value;
            if (endDateInput.value && endDateInput.value < this.value) {
                endDateInput.value = '';
            }
        }
    });

    // Form validation
    form.addEventListener('submit', function(e) {
        const name = document.getElementById('name').value.trim();

        if (!name) {
            e.preventDefault();
            alert('Please enter a project name.');
            return;
        }

        if (startDateInput.value && endDateInput.value) {
            if (new Date(endDateInput.value) < new Date(startDateInput.value)) {
                e.preventDefault();
                alert('End date cannot be before start date.');
                return;
            }
        }
    });

    // Initialize Quill editor
    var quill = new Quill('#quill-editor', {
        theme: 'snow',
        placeholder: 'Describe your project goals, objectives, and key deliverables…',
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

    form.addEventListener('submit', function() {
        document.getElementById('description').value = quill.root.innerHTML;
    });
});

function confirmDelete() {
    if (confirm('Are you sure you want to delete this project? This action cannot be undone and will remove all associated tasks, files, and data.')) {
        if (confirm('This is your final warning. Are you absolutely sure you want to permanently delete this project?')) {
            document.getElementById('deleteForm').submit();
        }
    }
}
</script>
@endpush
