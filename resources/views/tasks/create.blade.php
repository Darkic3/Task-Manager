@extends('layouts.app')

@section('title', 'Create New Task')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/tasks/style.css') }}">
@endpush

@section('content')
<div class="main-content">
    <!-- Back Link -->
    <a href="{{ route('tasks.index') }}" class="back-link">
        <i class="bi bi-arrow-left"></i>
        {{ __('Back to Tasks') }}
    </a>

    <div class="form-container">
        <div class="form-card">
            <div class="form-header">
                <div class="task-icon">
                    <i class="bi bi-plus-circle"></i>
                </div>
                <h2>{{ __('Create New Task') }}</h2>
                <p>{{ __('Define task requirements and assign to team members') }}</p>
            </div>

            <div class="form-body">
                @if($errors->any())
                    <div class="invalid-feedback d-block mb-3" role="alert">
                        {{ __('Please fix the following errors:') }}
                        <ul class="mb-0 mt-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('tasks.store') }}" method="POST" id="createTaskForm">
                    @csrf

                    <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                    <input type="hidden" name="status" value="to_do">

                    <div class="form-group">
                        <label for="title" class="form-label">{{ __('Task Title') }} *</label>
                        <div class="input-icon">
                            <i class="bi bi-card-text"></i>
                            <input type="text"
                                   name="title"
                                   id="title"
                                   class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}"
                                   value="{{ old('title') }}"
                                   placeholder="{{ __('Enter task title') }}"
                                   required>
                        </div>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">{{ __('Description') }}</label>
                        <div class="editor-container">
                            <div id="quill-editor" style="height: 150px;"></div>
                            <textarea name="description" id="description" style="display: none;">{{ old('description') }}</textarea>
                        </div>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="due_date" class="form-label">{{ __('Due Date') }}</label>
                            <div class="input-icon">
                                <i class="bi bi-calendar-event"></i>
                                <x-jalali-date name="due_date" id="due_date" :value="old('due_date')"
                                    class="form-control {{ $errors->has('due_date') ? 'is-invalid' : '' }}" />
                            </div>
                            @error('due_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('Estimated Time') }}</label>
                            <div style="display:flex; gap:8px;">
                                <div class="input-icon" style="flex:1;">
                                    <i class="bi bi-clock"></i>
                                    <input type="number"
                                           name="est_hours"
                                           id="est_hours"
                                           class="form-control {{ $errors->has('est_hours') ? 'is-invalid' : '' }}"
                                           value="{{ old('est_hours') }}"
                                           min="0"
                                           max="999"
                                           step="1"
                                           placeholder="{{ __('Hrs') }}">
                                </div>
                                <div style="flex:1;">
                                    <input type="number"
                                           name="est_minutes"
                                           id="est_minutes"
                                           class="form-control {{ $errors->has('est_minutes') ? 'is-invalid' : '' }}"
                                           value="{{ old('est_minutes') }}"
                                           min="0"
                                           max="59"
                                           step="1"
                                           placeholder="{{ __('Min') }}">
                                </div>
                            </div>
                            @error('est_hours')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @error('est_minutes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('Priority') }} *</label>
                        <div class="priority-options">
                            <div class="priority-option">
                                <input type="radio"
                                       name="priority"
                                       id="priority_low"
                                       value="low"
                                       {{ old('priority') == 'low' ? 'checked' : '' }}>
                                <label for="priority_low" class="priority-low">
                                    <span class="priority-dot"></span>
                                    {{ __('Low Priority') }}
                                </label>
                            </div>

                            <div class="priority-option">
                                <input type="radio"
                                       name="priority"
                                       id="priority_medium"
                                       value="medium"
                                       {{ old('priority') == 'medium' || !old('priority') ? 'checked' : '' }}>
                                <label for="priority_medium" class="priority-medium">
                                    <span class="priority-dot"></span>
                                    {{ __('Medium Priority') }}
                                </label>
                            </div>

                            <div class="priority-option">
                                <input type="radio"
                                       name="priority"
                                       id="priority_high"
                                       value="high"
                                       {{ old('priority') == 'high' ? 'checked' : '' }}>
                                <label for="priority_high" class="priority-high">
                                    <span class="priority-dot"></span>
                                    {{ __('High Priority') }}
                                </label>
                            </div>
                        </div>
                        @error('priority')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('tasks.index') }}" class="btn-secondary">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn-primary">
                            <i class="bi bi-check-lg me-2"></i>{{ __('Create Task') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Quill editor
    var quill = new Quill('#quill-editor', {
        theme: 'snow',
        placeholder: 'Describe the task requirements, goals, and any specific instructions...',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                ['blockquote', 'code-block'],
                [{ 'header': 1 }, { 'header': 2 }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link'],
                [{ 'color': [] }, { 'background': [] }],
                ['clean']
            ]
        }
    });

    // Set initial content if available
    var initialContent = document.getElementById('description').value;
    if (initialContent) {
        quill.root.innerHTML = initialContent;
    }

    // Update hidden textarea when content changes
    quill.on('text-change', function() {
        document.getElementById('description').value = quill.root.innerHTML;
    });

    // Update hidden textarea before form submission
    document.querySelector('#createTaskForm').addEventListener('submit', function() {
        document.getElementById('description').value = quill.root.innerHTML;
    });

    // Form validation
    const form = document.getElementById('createTaskForm');
    const dueDateInput = document.getElementById('due_date');

    form.addEventListener('submit', function(e) {
        const title = document.getElementById('title').value.trim();

        if (!title) {
            e.preventDefault();
            alertSwal('{{ __('Please enter a task title.') }}', null, 'warning');
            return;
        }

        // Check if due date is not in the past
        if (dueDateInput.value) {
            const dueDate = new Date(dueDateInput.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (dueDate < today) {
                e.preventDefault();
                alertSwal('{{ __('Due date cannot be in the past.') }}', null, 'warning');
                return;
            }
        }
    });

    // Set minimum date for due date input to today
    const today = new Date().toISOString().split('T')[0];
    dueDateInput.min = today;
});
</script>

<!-- Quill.js CSS and JS -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
@endpush
