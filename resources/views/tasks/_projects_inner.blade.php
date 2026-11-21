@php
    $flatAll = collect($tasks)->flatten();
    $orphanTasks = $flatAll->filter(fn ($t) => $t->project_id === null)->values()
        ->sortBy(fn ($t) => $t->status === 'completed' ? 1 : 0)->values();
    $tasksByProject = $flatAll->filter(fn ($t) => $t->project_id !== null)->groupBy(fn ($t) => (int) $t->project_id);
    $rootProjects = collect($projectRoots ?? [])->filter(
        fn ($p) => (($projectAggregates[(int) $p->id]['total'] ?? 0) > 0)
    )->values();
@endphp
@if($orphanTasks->count())
    @include('tasks._chapter-section', [
        'sectionId'    => 'p-none',
        'sectionTitle' => __('No project'),
        'sectionUrl'   => route('tasks.index'),
        'tasks'        => $orphanTasks->filter(fn ($t) => $t->parent_id === null)->values(),
        'grouped'      => $orphanTasks->groupBy('parent_id'),
        'collapsed'    => true,
        'quickProjectId' => 0,
    ])
@endif
@foreach($rootProjects as $proj)
    @include('tasks._project-group', [
        'proj' => $proj,
        'tasksByProject' => $tasksByProject,
        'projectChildren' => $projectChildren ?? [],
        'projectAggregates' => $projectAggregates ?? [],
        'depth' => 0,
        'collapsed' => true,
    ])
@endforeach