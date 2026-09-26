@php
    $depth = $depth ?? 0;
    $progress = $task->progressPercent();
    $childCount = $task->children->count();
    $weight = (float) ($task->weight ?? 1);
@endphp
<div class="cu-ttree-node" style="--depth:{{ $depth }};">
    <div class="cu-ttree-row">
        @if($childCount > 0)
            <button type="button" class="cu-tree-toggle" data-target="ttree-children-{{ $task->id }}" title="Expand/Collapse">
                <i class="bi bi-chevron-down"></i>
            </button>
        @else
            <span class="cu-tree-spacer"></span>
        @endif
        <a href="{{ route('tasks.show', $task->id) }}" class="cu-ttree-title" title="{{ $task->title }}">{{ $task->title }}</a>
        <span class="cu-status-chip {{ $task->status }}">
            <i class="bi bi-circle-fill" style="font-size:5px;"></i>
            {{ ucwords(str_replace('_', ' ', $task->status)) }}
        </span>
        @if($weight !== 1.0)
            <span class="cu-ttree-weight" title="Weight">×{{ rtrim(rtrim(number_format($weight, 2), '0'), '.') }}</span>
        @endif
        <span class="cu-ttree-meta">{{ $childCount > 0 ? $childCount.' sub' : '' }}</span>
        <div class="cu-ttree-progress">
            <div class="cu-ttree-pb"><div class="cu-ttree-pb-fill" style="width:{{ $progress }}%;"></div></div>
            <span>{{ round($progress) }}%</span>
        </div>
        <div class="cu-ttree-actions">
            @if($task->status !== 'completed')
                <button type="button" class="cu-task-btn" data-add-day data-id="{{ $task->id }}" data-title="{{ $task->title }}"
                        data-period="{{ $task->time_period }}" title="Add to today's plan"><i class="bi bi-calendar-plus"></i></button>
            @endif
            <a href="{{ route('tasks.show', $task->id) }}" class="cu-task-btn" title="View"><i class="bi bi-eye"></i></a>
            <a href="{{ route('tasks.edit', $task->id) }}" class="cu-task-btn" title="Edit"><i class="bi bi-pencil"></i></a>
        </div>
    </div>
    @if($childCount > 0)
        <div class="cu-ttree-children" id="ttree-children-{{ $task->id }}">
            @foreach($task->children as $child)
                @include('tasks._tree-node', ['task' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
