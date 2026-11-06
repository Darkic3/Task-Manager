{{-- Recursive subtask row. Vars: $task, $depth --}}
@php
    $ck = $task->relationLoaded('childrenRecursive') ? $task->childrenRecursive : collect();
    $isDone = $task->status === 'completed';
@endphp
<div class="pt-kid {{ $isDone ? 'is-done' : '' }} depth-{{ min(3, $depth) }}">
    <input type="checkbox" class="pt-check" data-pt-toggle="{{ $task->id }}" {{ $isDone ? 'checked' : '' }}>
    <a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
    @if(! is_null($task->weight))
        <span class="pt-chip">📄 {{ rtrim(rtrim(number_format((float) $task->weight, 1, '.', ''), '0'), '.') }}</span>
    @endif
</div>
@foreach($ck as $sub)
    @include('projects._project-task-kid', ['task' => $sub, 'depth' => $depth + 1])
@endforeach
