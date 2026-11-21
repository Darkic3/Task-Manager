@php
    $view = $view ?? 'projects';
@endphp

@if($view === 'kanban')
    @include('tasks._kanban_inner')
@elseif($view === 'list')
    @include('tasks._list_inner')
@elseif($view === 'tree')
    @include('tasks._tree_inner')
@elseif($view === 'chapters')
    @include('tasks._chapters_inner')
@else
    @include('tasks._projects_inner')
@endif