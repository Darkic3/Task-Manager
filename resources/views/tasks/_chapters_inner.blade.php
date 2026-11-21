@php
    $flatAll   = collect($tasks)->flatten();
    $groupedBy = $flatAll->groupBy('parent_id');
    $chapterRoots = $taskRoots->filter(fn ($t) => $groupedBy->has($t->id))->values();
    $singleRoots  = $taskRoots->reject(fn ($t) => $groupedBy->has($t->id))->values();
@endphp
@foreach($chapterRoots as $root)
    @include('tasks._chapter-section', [
        'sectionId'    => 'ch-' . $root->id,
        'sectionTitle' => $root->title,
        'sectionUrl'   => route('tasks.show', $root->id),
        'tasks'        => collect([$root]),
        'grouped'      => $groupedBy,
        'collapsed'    => $root->status === 'completed',
    ])
@endforeach
@if($singleRoots->count() > 0)
    @include('tasks._chapter-section', [
        'sectionId'    => 'ch-other',
        'sectionTitle' => __('Other tasks'),
        'sectionUrl'   => null,
        'tasks'        => $singleRoots,
        'grouped'      => $groupedBy,
        'collapsed'    => false,
    ])
@endif