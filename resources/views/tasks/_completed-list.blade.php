{{-- Rendered by TaskController::completedTasks for the lazy completed accordion. --}}
@foreach($roots as $t)
    @include('tasks._chapter-row', ['task' => $t, 'grouped' => $grouped, 'depth' => 0, 'rootId' => 'completed-p-' . ($projectId ?? 'none')])
@endforeach