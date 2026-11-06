{{-- Recursive structure node. Vars: $node (Project, relations loaded), $depth (int), $rootId (int), $destOptions (flat list) --}}
@php
    $kids = $node->relationLoaded('childrenRecursive') ? $node->childrenRecursive : $node->children;
    $hasKids = $kids->count() > 0;
    $isContainer = $hasKids || in_array($node->type ?? '', ['chapter', 'section', 'project', 'subproject'], true);
    $directTotal = $node->tasks->count();
    $directDone = $node->tasks->where('status', 'completed')->count();
    // subtree aggregate (in-memory, mirrors controller)
    $walk = function ($n) use (&$walk) {
        $t = $n->relationLoaded('tasks') ? $n->tasks->count() : $n->tasks()->count();
        $d = $n->relationLoaded('tasks') ? $n->tasks->where('status', 'completed')->count() : $n->tasks()->where('status', 'completed')->count();
        $ck = $n->relationLoaded('childrenRecursive') ? $n->childrenRecursive : ($n->relationLoaded('children') ? $n->children : collect());
        foreach ($ck as $c) { $s = $walk($c); $t += $s[0]; $d += $s[1]; }
        return [$t, $d];
    };
    [$aggTotal, $aggDone] = $walk($node);
    $pct = $aggTotal > 0 ? round($aggDone / $aggTotal * 100) : round($node->progressPercent());
    $typeMeta = [
        'project' => ['c' => '#6366f1', 'i' => '📦', 'l' => 'Project'],
        'subproject' => ['c' => '#7c3aed', 'i' => '🗂', 'l' => 'Sub-project'],
        'chapter' => ['c' => '#f59e0b', 'i' => '📁', 'l' => 'Chapter'],
        'lesson' => ['c' => '#06b6d4', 'i' => '📄', 'l' => 'Lesson'],
        'section' => ['c' => '#ec4899', 'i' => '📑', 'l' => 'Section'],
    ];
    $meta = $typeMeta[$node->type] ?? ['c' => '#6b7280', 'i' => '📄', 'l' => ucfirst((string) $node->type)];
    $tcolor = $meta['c'];
@endphp
<div class="st-node {{ $isContainer ? 'st-container' : 'st-leaf' }}" data-node-id="{{ $node->id }}" data-node-type="{{ $node->type }}"
     style="--tc:{{ $tcolor }}; --tb:{{ $tcolor }}0d; --tt:{{ $tcolor }}22; --st-depth:{{ $depth }};">
    <div class="st-row {{ $hasKids ? 'st-has-kids' : '' }}" draggable="false" data-row-id="{{ $node->id }}">
        <span class="st-drag" title="{{ __('Drag to move') }}" draggable="true" data-drag-id="{{ $node->id }}"><i class="bi bi-grip-vertical"></i></span>
        <input type="checkbox" class="st-check" data-check-id="{{ $node->id }}" title="{{ __('Select') }}">
        @if($hasKids)
            <button type="button" class="st-chev" data-st-toggle="{{ $node->id }}" aria-expanded="true" title="{{ __('Collapse / expand') }}">
                <i class="bi bi-chevron-down"></i>
            </button>
        @else
            <span class="st-chev-sp"></span>
        @endif
        <span class="st-type" style="color:{{ $tcolor }};background:{{ $tcolor }}14;border-color:{{ $tcolor }}40;">{{ $meta['i'] }} <span class="st-type-txt">{{ __($meta['l']) }}</span></span>
        <a href="{{ route('projects.show', $node) }}" class="st-name" title="{{ $node->name }}">{{ $node->name }}</a>
        <span class="st-counts" title="{{ __(':done of :total tasks (subtree)', ['done' => $aggDone, 'total' => $aggTotal]) }}">{{ app_num($aggDone) }}/{{ app_num($aggTotal) }}</span>
        <span class="st-pb"><span class="st-pb-fill" style="width:{{ $pct }}%;background:linear-gradient(90deg, {{ $tcolor }}, {{ $tcolor }}cc);"></span></span>
        <span class="st-pct">{{ app_num($pct) }}%</span>
        <span class="st-actions">
            @if($isContainer)
                <button type="button" class="st-icon-btn" data-st-add="lesson" data-st-parent="{{ $node->id }}" title="{{ __('Add lesson here') }}"><i class="bi bi-file-earmark-plus"></i></button>
                <button type="button" class="st-icon-btn" data-st-add="chapter" data-st-parent="{{ $node->id }}" title="{{ __('Add sub-chapter') }}"><i class="bi bi-folder-plus"></i></button>
            @endif
            <button type="button" class="st-icon-btn" data-st-task="{{ $node->id }}" title="{{ __('Quick add task') }}"><i class="bi bi-plus-lg"></i></button>
            <button type="button" class="st-icon-btn st-menu-btn" data-st-menu="{{ $node->id }}"
                data-st-name="{{ $node->name }}" data-st-type="{{ $node->type }}"
                data-st-edit="{{ route('projects.edit', $node) }}" title="{{ __('Move / more') }}"><i class="bi bi-three-dots"></i></button>
        </span>
    </div>

    {{-- drop target strip (appears on dragover) --}}
    <div class="st-drop" data-drop-id="{{ $node->id }}" hidden>🎯 {{ __('Drop here → move into :name', ['name' => $node->name]) }}</div>

    @if($node->tasks->count() > 0 || true)
        <div class="st-tasks" data-tasks-body="{{ $node->id }}">
            @foreach($node->tasks->take(6) as $t)
                <span class="st-task {{ $t->status === 'completed' ? 'done' : '' }}"
                      draggable="true" data-ctid="{{ $t->id }}"
                      data-ctprior="{{ $t->priority }}" data-ctstatus="{{ $t->status }}"
                      data-cturl="{{ route('tasks.show', $t) }}"
                      title="{{ __('Drag task to move it to another chapter') }}">
                    <input type="checkbox" data-task-toggle="{{ $t->id }}" {{ $t->status === 'completed' ? 'checked' : '' }}>
                    <span class="st-task-title" data-cttitle="{{ $t->id }}" title="{{ $t->title }}">{{ $t->title }}</span>
                    <span class="st-cact">
                        <button type="button" data-ctrename="{{ $t->id }}" title="{{ __('Rename') }}"><i class="bi bi-pencil"></i></button>
                        <button type="button" data-ctdel="{{ $t->id }}" title="{{ __('Delete') }}"><i class="bi bi-x-lg"></i></button>
                    </span>
                </span>
            @endforeach
            @if($directTotal > 6)
                <a class="st-more" href="{{ route('projects.tasks.index', $node) }}">{{ __(':n more →', ['n' => app_num($directTotal - 6)]) }}</a>
            @endif
            <span class="st-quick" data-quick-wrap="{{ $node->id }}" hidden>
                <input type="text" data-quick-input="{{ $node->id }}" maxlength="255" placeholder="{{ __('Task title… Enter to add') }}">
            </span>
        </div>
    @endif

    @if($hasKids)
        <div class="st-kids" data-kids-body="{{ $node->id }}">
            @foreach($kids as $kid)
                @include('projects._structure-node', ['node' => $kid, 'depth' => $depth + 1, 'rootId' => $rootId, 'destOptions' => $destOptions])
            @endforeach
        </div>
    @endif
</div>
