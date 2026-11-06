{{-- Recursive subtask row. Vars: $task, $depth --}}
@php
    $ck = $task->relationLoaded('childrenRecursive') ? $task->childrenRecursive : collect();
    $isDone = $task->status === 'completed';
@endphp
<div class="pt-kid {{ $isDone ? 'is-done' : '' }} depth-{{ min(3, $depth) }}"
     data-kid="{{ $task->id }}" data-task-status="{{ $task->status }}" data-task-priority="{{ $task->priority ?? 'medium' }}">
    <input type="checkbox" class="pt-check" data-pt-toggle="{{ $task->id }}" {{ $isDone ? 'checked' : '' }}>
    <a href="{{ route('tasks.show', $task) }}" data-kidtitle="{{ $task->id }}">{{ $task->title }}</a>
    @if(! is_null($task->weight))
        <span class="pt-chip">📄 {{ app_num(rtrim(rtrim(number_format((float) $task->weight, 1, '.', ''), '0'), '.')) }}</span>
    @endif
    <span class="pt-kact">
        <button type="button" data-krename="{{ $task->id }}" title="{{ __('Rename') }}"><i class="bi bi-pencil"></i></button>
        <button type="button" data-kdel="{{ $task->id }}" title="{{ __('Delete') }}"><i class="bi bi-x-lg"></i></button>
    </span>
</div>
@foreach($ck as $sub)
    @include('projects._project-task-kid', ['task' => $sub, 'depth' => $depth + 1])
@endforeach
