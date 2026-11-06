{{-- Recursive structure node. Vars: $node (Project, relations loaded), $depth (int), $rootId (int), $destOptions (flat list), $isLast (bool) --}}
@php
    $kids = $node->relationLoaded('childrenRecursive') ? $node->childrenRecursive : $node->children;
    $hasKids = $kids->count() > 0;
    $isContainer = $hasKids || in_array($node->type ?? '', ['chapter', 'section', 'project', 'subproject'], true);
    $directTotal = $node->tasks->count();
    $directDone = $node->tasks->where('status', 'completed')->count();
    $aggTotal = $directTotal; $aggDone = $directDone;
    $stack = [$node];
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
    $typeColors = ['project' => '#6366f1', 'subproject' => '#7c3aed', 'chapter' => '#f59e0b', 'lesson' => '#06b6d4', 'section' => '#ec4899'];
    $tcolor = $typeColors[$node->type] ?? '#6b7280';
    $typeLabels = ['project' => 'Project', 'subproject' => 'Sub-project', 'lesson' => 'Lesson', 'chapter' => 'Chapter', 'section' => 'Section'];
    $tlabel = $typeLabels[$node->type] ?? ucfirst((string) $node->type);
    $collapsed = false;
@endphp
<div class="st-node {{ $isContainer ? 'st-container' : 'st-leaf' }}" data-node-id="{{ $node->id }}" data-node-type="{{ $node->type }}" style="--st-depth:{{ $depth }};">
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
        <span class="st-type" style="color:{{ $tcolor }};background:{{ $tcolor }}14;border-color:{{ $tcolor }}33;">{{ __($tlabel) }}</span>
        <a href="{{ route('projects.show', $node) }}" class="st-name" title="{{ $node->name }}">{{ $node->name }}</a>
        <span class="st-counts" title="{{ __(':done of :total tasks (subtree)', ['done' => $aggDone, 'total' => $aggTotal]) }}">{{ $aggDone }}/{{ $aggTotal }}</span>
        <span class="st-pb"><span class="st-pb-fill" style="width:{{ $pct }}%;background:{{ $tcolor }};"></span></span>
        <span class="st-pct">{{ $pct }}%</span>
        <span class="st-actions">
            @if($isContainer)
                <button type="button" class="st-icon-btn" data-st-add="lesson" data-st-parent="{{ $node->id }}" title="{{ __('Add lesson here') }}"><i class="bi bi-file-earmark-plus"></i></button>
                <button type="button" class="st-icon-btn" data-st-add="chapter" data-st-parent="{{ $node->id }}" title="{{ __('Add sub-chapter') }}"><i class="bi bi-folder-plus"></i></button>
            @endif
            <button type="button" class="st-icon-btn" data-st-task="{{ $node->id }}" title="{{ __('Quick add task') }}"><i class="bi bi-plus-lg"></i></button>
            <span class="st-menu-wrap">
                <button type="button" class="st-icon-btn" data-st-menu="{{ $node->id }}" title="{{ __('Move / more') }}"><i class="bi bi-three-dots"></i></button>
                <span class="st-menu" data-st-menu-body="{{ $node->id }}" hidden>
                    <span class="st-menu-lbl">{{ __('Move to…') }}</span>
                    <button type="button" data-st-move="{{ $node->id }}" data-st-dest="">📥 {{ __('Ungrouped (root)') }}</button>
                    @foreach($destOptions as $opt)
                        @if((int) $opt['id'] !== (int) $node->id && (int) $opt['id'] !== (int) $rootId)
                            <button type="button" data-st-move="{{ $node->id }}" data-st-dest="{{ $opt['id'] }}">{{ str_repeat('— ', min(4, $opt['depth'])) }}{{ $opt['name'] }}</button>
                        @endif
                    @endforeach
                    <span class="st-menu-sep"></span>
                    <span class="st-menu-lbl">{{ __('Convert to') }}</span>
                    <span class="st-menu-row">
                        @foreach(['chapter' => '📁', 'lesson' => '📄', 'section' => '📑'] as $tv => $ico)
                            <button type="button" data-st-convert="{{ $node->id }}" data-st-type="{{ $tv }}" title="{{ __($typeLabels[$tv]) }}">{{ $ico }}</button>
                        @endforeach
                    </span>
                    <span class="st-menu-sep"></span>
                    <a href="{{ route('projects.edit', $node) }}">{{ __('Edit…') }}</a>
                </span>
            </span>
        </span>
    </div>

    {{-- drop target strip (appears on dragover) --}}
    <div class="st-drop" data-drop-id="{{ $node->id }}" hidden>{{ __('Drop here → move into :name', ['name' => $node->name]) }}</div>

    @if($directTotal > 0 || true)
        <div class="st-tasks" data-tasks-body="{{ $node->id }}">
            @foreach($node->tasks->take(6) as $t)
                <label class="st-task {{ $t->status === 'completed' ? 'done' : '' }}">
                    <input type="checkbox" data-task-toggle="{{ $t->id }}" {{ $t->status === 'completed' ? 'checked' : '' }}>
                    <span class="st-task-title">{{ $t->title }}</span>
                </label>
            @endforeach
            @if($directTotal > 6)
                <a class="st-more" href="{{ route('projects.tasks.index', $node) }}">{{ __(':n more →', ['n' => $directTotal - 6]) }}</a>
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
