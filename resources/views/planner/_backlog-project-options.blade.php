{{-- Project <option> list for the backlog ("add existing task") picker.
     All + No project + the full tree (sub-projects indented, full path as tooltip).
     Expects: $backlogProjects (Collection of id, name, parent_id). --}}
<option value="">{{ __('All projects') }}</option>
<option value="none">{{ __('No project') }}</option>
@php
    $blById = ($backlogProjects ?? collect())->keyBy('id');
    $blDepthOf = function ($p) use ($blById) {
        $d = 0;
        $cur = $p;
        $g = 0;
        while ($cur->parent_id && isset($blById[$cur->parent_id]) && $g++ < 20) {
            $d++;
            $cur = $blById[$cur->parent_id];
        }

        return $d;
    };
    $blPathOf = function ($p) use ($blById) {
        $parts = [$p->name];
        $cur = $p;
        $g = 0;
        while ($cur->parent_id && isset($blById[$cur->parent_id]) && $g++ < 20) {
            $cur = $blById[$cur->parent_id];
            array_unshift($parts, $cur->name);
        }

        return implode(' / ', $parts);
    };
@endphp
@foreach(($backlogProjects ?? collect()) as $bp)
    <option value="{{ $bp->id }}" title="{{ $blPathOf($bp) }}">{{ str_repeat('— ', $blDepthOf($bp)) }}{{ $bp->name }}</option>
@endforeach
