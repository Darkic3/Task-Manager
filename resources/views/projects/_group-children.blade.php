{{-- Recursive sub-project rows inside a grouped grid card.
     Vars: $nodes (Collection of Project, with tasks + childrenRecursive loaded), $depth (int). --}}
@foreach($nodes as $child)
    @php
        $ctotal = $child->tasks->count();
        $cdone = $child->tasks->where('status', 'completed')->count();
        $cprog = $ctotal > 0 ? ($cdone / $ctotal) * 100 : 0;
        $typeColors = ['project' => '#6366f1', 'subproject' => '#7c3aed', 'lesson' => '#06b6d4', 'chapter' => '#f59e0b', 'section' => '#ec4899'];
        $tcolor = $typeColors[$child->type] ?? '#6b7280';
        $typeLabels = ['project' => 'Project', 'subproject' => 'Sub-project', 'lesson' => 'Lesson', 'chapter' => 'Chapter', 'section' => 'Section'];
        $tlabel = $typeLabels[$child->type] ?? ucfirst((string) $child->type);
        $cchip = match($child->status) { 'in_progress' => 'in-progress', 'completed' => 'completed', 'closed' => 'closed', default => 'not-started' };
        $cchipLabel = match($child->status) { 'in_progress' => 'In Progress', 'completed' => 'Completed', 'closed' => 'Closed', default => 'Not Started' };
    @endphp
    <a href="{{ route('projects.show', $child) }}" class="cu-sub-row" style="--sub-depth:{{ $depth }};"
       data-name="{{ mb_strtolower($child->name) }}" data-status="{{ $child->status }}" title="{{ $child->name }}">
        <span class="cu-sub-type" style="color:{{ $tcolor }};background:{{ $tcolor }}14;border-color:{{ $tcolor }}33;">{{ __($tlabel) }}</span>
        <span class="cu-sub-name">{{ $child->name }}</span>
        @if($ctotal > 0)
            <span class="cu-sub-meta">{{ $cdone }}/{{ $ctotal }}</span>
        @endif
        <span class="cu-sub-pb"><span class="cu-sub-pb-fill" style="width:{{ $cprog }}%;background:{{ $tcolor }};"></span></span>
        <span class="cu-sub-pct">{{ round($cprog) }}%</span>
        <span class="cu-sub-dot {{ $cchip }}" title="{{ __($cchipLabel) }}"></span>
    </a>
    @if($child->childrenRecursive->count() > 0)
        @include('projects._group-children', ['nodes' => $child->childrenRecursive, 'depth' => $depth + 1])
    @endif
@endforeach
