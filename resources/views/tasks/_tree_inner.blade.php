@forelse($taskRoots as $task)
    @include('tasks._tree-node', ['task' => $task, 'depth' => 0])
@empty
    <div class="cu-empty">
        <div class="cu-empty-icon"><i class="bi bi-diagram-3"></i></div>
        <h5>{{ __('No tasks yet') }}</h5>
        <p>{{ __('Create your first task to get started.') }}</p>
    </div>
@endforelse