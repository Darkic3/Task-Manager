{{-- Recursive nested project group for the Tasks "Projects" view.
      First glance shows ONLY root/parent projects; clicking a parent expands
      its chapters (child projects), and clicking a chapter expands its own
      tasks + deeper sub-projects.

      Vars:
        $proj (Project), $tasksByProject (Collection project_id => tasks),
        $projectChildren (array parent_id => Project[]), $projectAggregates (array id => [total,done,open]),
        $depth (int, default 0), $collapsed (bool, default true) --}}
@php
    $depth = $depth ?? 0;
    $collapsed = $collapsed ?? true;
    $pid = (int) $proj->id;
    $directAll = $tasksByProject->get($pid, collect());
    $pGrouped = $directAll->groupBy('parent_id');
    $pRoots = $directAll->filter(fn ($t) => $t->parent_id === null)->values()
        ->sortBy(fn ($t) => $t->status === 'completed' ? 1 : 0)->values();
    $agg = $projectAggregates[$pid] ?? ['total' => $directAll->count(), 'done' => $directAll->where('status', 'completed')->count(), 'open' => $directAll->where('status', '!=', 'completed')->count()];
    $aggTotal = $agg['total'] ?? 0;
    $aggDone = $agg['done'] ?? 0;
    $aggOpen = $agg['open'] ?? 0;
    $aggPct = $aggTotal > 0 ? round($aggDone / $aggTotal * 100) : 0;
    // Only branches that actually contain tasks stay visible (empty leaves pruned).
    $kids = collect($projectChildren[$pid] ?? [])->filter(fn ($c) => (($projectAggregates[(int) $c->id]['total'] ?? 0) > 0))->values();
    $typeLabels = ['project' => 'Project', 'subproject' => 'Sub-project', 'lesson' => 'Lesson', 'chapter' => 'Chapter', 'section' => 'Section'];
    $typeColors = ['project' => '#6366f1', 'subproject' => '#7c3aed', 'lesson' => '#06b6d4', 'chapter' => '#f59e0b', 'section' => '#ec4899'];
    $tlabel = $typeLabels[$proj->type] ?? ucfirst((string) ($proj->type ?? 'project'));
    $tcolor = $typeColors[$proj->type] ?? '#6b7280';
    $bodyId = 'cu-ch-body-p-' . $pid . ($depth > 0 ? '-d' . $depth : '');
@endphp
<div class="cu-chapter is-group {{ $depth > 0 ? 'is-child' : '' }} {{ $collapsed ? 'collapsed' : '' }}"
     data-chapter="p-{{ $pid }}" data-project="{{ $pid }}" data-depth="{{ $depth }}"
     data-agg-total="{{ $aggTotal }}" data-agg-done="{{ $aggDone }}" style="--proj-depth:{{ $depth }};">
    <div class="cu-chapter-head" data-chapter-toggle="p-{{ $pid }}">
        <button type="button" class="cu-ch-chevron"
                aria-expanded="{{ $collapsed ? 'false' : 'true' }}" aria-controls="{{ $bodyId }}"
                title="{{ __('Collapse / expand') }}">
            <i class="bi bi-chevron-down"></i>
        </button>
        @if($depth > 0)
            <span class="cu-sub-type" style="color:{{ $tcolor }};background:{{ $tcolor }}14;border-color:{{ $tcolor }}33;">{{ __($tlabel) }}</span>
        @endif
        <span class="cu-ch-heading">
            <a href="{{ route('projects.tasks.index', $proj) }}" class="cu-chapter-title cu-ch-nav" title="{{ $proj->name }}">{{ $proj->name }}</a>
            <a href="{{ route('projects.tasks.index', $proj) }}" class="cu-ch-open" title="{{ __('View Details') }}"
               aria-label="{{ __('View Details') }}">
                <i class="bi bi-box-arrow-up-left"></i>
            </a>
            @if($kids->count() > 0)
                <span class="cu-sub-count" title="{{ __('Sub-projects') }}">{{ $kids->count() }}</span>
            @endif
        </span>
        <span class="cu-chapter-progress" title="{{ __('Including all sub-projects') }}: {{ $aggDone }}/{{ $aggTotal }}">
            <span class="cu-chapter-pb"><span class="cu-chapter-pb-fill" style="width:{{ $aggPct }}%;"></span></span>
            <span class="cu-chapter-count">{{ $aggDone }}/{{ $aggTotal }}</span>
        </span>
        <span class="cu-col-count cu-chapter-open" title="{{ __('Unfinished (including sub-projects)') }}">{{ $aggOpen }} {{ __('open') }}</span>
        <button type="button" class="cu-ch-add cu-ch-add--quick"
                data-ch-quickadd="{{ $pid }}" data-section="p-{{ $pid }}"
                title="{{ __('Quick add task') }} (A)">
            <i class="bi bi-plus-lg"></i><span>{{ __('New') }}</span><span class="cu-kbd">A</span>
        </button>
    </div>
    <div class="cu-chapter-body" id="{{ $bodyId }}">
        <div class="cu-ch-quickform" data-ch-quickform="p-{{ $pid }}"
             data-ch-project="{{ $pid }}" hidden>
            <i class="bi bi-plus-lg"></i>
            <input type="text" data-ch-quickinput maxlength="255" autocomplete="off"
                   placeholder="{{ __('Task title… Enter to add') }}" aria-label="{{ __('New task title') }}">
            <span class="cu-ch-quickhint"><kbd>&#8629;</kbd> {{ __('add') }} · <kbd>esc</kbd></span>
        </div>
        @foreach($pRoots as $t)
            @include('tasks._chapter-row', ['task' => $t, 'grouped' => $pGrouped, 'depth' => 0, 'rootId' => 'p-' . $pid])
        @endforeach
        @foreach($kids as $child)
            @include('tasks._project-group', [
                'proj' => $child,
                'tasksByProject' => $tasksByProject,
                'projectChildren' => $projectChildren,
                'projectAggregates' => $projectAggregates,
                'depth' => $depth + 1,
                'collapsed' => true,
            ])
        @endforeach
    </div>
</div>
