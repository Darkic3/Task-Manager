{{-- Recursive child rows for the grouped list view.
     Vars: $nodes (Collection of Project), $depth (int), $rootId (int). --}}
@foreach($nodes as $child)
    @php
        $ctotal = $child->tasks->count();
        $cdone = $child->tasks->where('status', 'completed')->count();
        $cprog = $ctotal > 0 ? ($cdone / $ctotal) * 100 : 0;
        $colors = ['#6366f1','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899'];
        $ccolor = $colors[strlen($child->name) % count($colors)];
        $cchip = match($child->status) { 'in_progress'=>'in-progress','completed'=>'completed','closed'=>'closed',default=>'not-started'};
        $clabel = match($child->status) { 'in_progress'=>'In Progress','completed'=>'Completed','closed'=>'Closed',default=>'Not Started'};
        $coverdue = $child->end_date && $child->end_date->isPast() && !in_array($child->status, ['completed','closed']);
        $typeLabels = ['project' => 'Project', 'subproject' => 'Sub-project', 'lesson' => 'Lesson', 'chapter' => 'Chapter', 'section' => 'Section'];
        $tlabel = $typeLabels[$child->type] ?? ucfirst((string) $child->type);
    @endphp
    <div class="cu-list-row project-item is-child" style="--sub-depth:{{ $depth }};"
         data-root="{{ $rootId }}"
         data-name="{{ mb_strtolower($child->name) }}"
         data-status="{{ $child->status }}"
         data-progress="{{ $cprog }}"
         data-created="{{ $child->created_at->timestamp }}">
        <div class="cu-list-name">
            <span class="cu-child-guides" aria-hidden="true"></span>
            <div class="cu-list-avatar" style="background:{{ $ccolor }};">{{ name_initials($child->name) }}</div>
            <span title="{{ $child->name }}">{{ $child->name }}</span>
            <span class="cu-tree-type">{{ __($tlabel) }}</span>
        </div>
        <div class="cu-list-meta col-meta">
            @if($child->end_date)
                <span class="{{ $coverdue ? 'text-danger fw-semibold' : '' }}" style="font-size:11px;">
                    @if($coverdue)<i class="bi bi-exclamation-circle me-1"></i>{{ __('Overdue') }}
                    @else {{ $child->end_date->format('M d, Y') }}
                    @endif
                </span>
            @else <span style="color:#d3d5db;">—</span>
            @endif
        </div>
        <div class="cu-list-progress col-prog">
            <div class="cu-list-pb">
                <div class="cu-list-pb-fill" style="background:{{ $ccolor }}; width:{{ $cprog }}%;"></div>
            </div>
            <span>{{ round($cprog) }}%</span>
        </div>
        <div><span class="cu-chip {{ $cchip }}"><i class="bi bi-circle-fill" style="font-size:6px;"></i>{{ __($clabel) }}</span></div>
        <div class="cu-list-actions">
            <a href="{{ route('projects.tasks.index', $child) }}" class="cu-action-btn" title="{{ __('Tasks') }}"><i class="bi bi-list-task"></i></a>
            <a href="{{ route('projects.show', $child) }}" class="cu-action-btn" title="{{ __('View') }}"><i class="bi bi-eye"></i></a>
            <a href="{{ route('projects.edit', $child) }}" class="cu-action-btn" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
        </div>
    </div>
    @if($child->childrenRecursive->count() > 0)
        @include('projects._list-children', ['nodes' => $child->childrenRecursive, 'depth' => $depth + 1, 'rootId' => $rootId])
    @endif
@endforeach
