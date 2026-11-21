{{-- Project <option> list for the backlog ("add existing task") picker.
     Hierarchical: grouped by root project (optgroup), DFS order, with
     indentation and a count of eligible open tasks per subtree.
     Expects: $backlogProjects (Collection of objects: id, name, parent_id,
     depth, path, root_id, subtree_task_count). --}}
<option value="">{{ __('All projects') }}</option>
<option value="none">{{ __('No project') }}</option>
@php
    $blProjects = ($backlogProjects ?? collect());
    $currentRoot = null;
@endphp
@foreach($blProjects as $bp)
    @if($currentRoot !== ($bp->root_id ?? $bp->id))
        @if($currentRoot !== null)
            </optgroup>
        @endif
        @php
            $currentRoot = $bp->root_id ?? $bp->id;
            $rootNode = $blProjects->firstWhere('id', $currentRoot);
        @endphp
        <optgroup label="{{ ($rootNode->name ?? $bp->name) }} ({{ $rootNode->subtree_task_count ?? 0 }})">
    @endif
    <option value="{{ $bp->id }}" title="{{ $bp->path ?? $bp->name }}">
        {{ str_repeat('— ', (int) ($bp->depth ?? 0)) }}{{ $bp->name }} ({{ $bp->subtree_task_count ?? 0 }})
    </option>
@endforeach
@if($currentRoot !== null)
    </optgroup>
@endif