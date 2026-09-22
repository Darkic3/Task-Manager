@php
    $depth = $depth ?? 0;
    $progress = $project->progressPercent();
    $childCount = $project->children->count();
    $taskTotal = $project->tasks->count();
    $typeLabels = ['project' => 'Project', 'subproject' => 'Sub-project', 'lesson' => 'Lesson', 'chapter' => 'Chapter', 'section' => 'Section'];
    $typeLabel = $typeLabels[$project->type] ?? ucfirst((string) $project->type);
@endphp
<div class="cu-tree-node project-item"
     data-name="{{ strtolower($project->name) }}"
     data-status="{{ $project->status }}"
     data-progress="{{ $progress }}"
     data-created="{{ $project->created_at->timestamp }}"
     style="--depth:{{ $depth }};">
    <div class="cu-tree-row">
        @if($childCount > 0)
            <button type="button" class="cu-tree-toggle" data-target="tree-children-{{ $project->id }}" title="Expand/Collapse">
                <i class="bi bi-chevron-down"></i>
            </button>
        @else
            <span class="cu-tree-spacer"></span>
        @endif
        <a href="{{ route('projects.show', $project) }}" class="cu-tree-name" title="{{ $project->name }}">{{ $project->name }}</a>
        @if($project->type !== 'project')
            <span class="cu-tree-type">{{ $typeLabel }}</span>
        @endif
        <span class="cu-tree-meta">{{ $taskTotal }} task{{ $taskTotal != 1 ? 's' : '' }}</span>
        <div class="cu-tree-progress">
            <div class="cu-tree-pb"><div class="cu-tree-pb-fill" style="width:{{ $progress }}%;"></div></div>
            <span>{{ round($progress) }}%</span>
        </div>
        <div class="cu-tree-actions">
            <a href="{{ route('projects.create', ['parent' => $project->id]) }}" class="cu-action-btn" title="Add sub-project"><i class="bi bi-plus"></i></a>
            <a href="{{ route('projects.show', $project) }}" class="cu-action-btn" title="View"><i class="bi bi-eye"></i></a>
            <a href="{{ route('projects.edit', $project) }}" class="cu-action-btn" title="Edit"><i class="bi bi-pencil"></i></a>
        </div>
    </div>
    @if($childCount > 0)
        <div class="cu-tree-children" id="tree-children-{{ $project->id }}">
            @foreach($project->children as $child)
                @include('projects._tree-node', ['project' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
