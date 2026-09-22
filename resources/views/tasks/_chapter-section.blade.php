{{-- Collapsible chapter section. Vars:
     $sectionId (string), $sectionTitle (string), $sectionUrl (string|null),
     $tasks (top-level tasks of this section), $grouped (tasks grouped by parent_id),
     $collapsed (bool) --}}
@php
    $secTotal = 0;
    $secDone = 0;
    $stack = $tasks->all();
    $guard = 0;
    while (! empty($stack) && $guard++ < 5000) {
        $t = array_shift($stack);
        $secTotal++;
        if ($t->status === 'completed') $secDone++;
        foreach ($grouped->get($t->id, collect()) as $kid) $stack[] = $kid;
    }
    $secOpen = $secTotal - $secDone;
    $secPct = $secTotal > 0 ? round($secDone / $secTotal * 100) : 0;
@endphp
<div class="cu-chapter {{ $collapsed ? 'collapsed' : '' }}" data-chapter="{{ $sectionId }}"
     data-total="{{ $secTotal }}" data-done="{{ $secDone }}">
    <div class="cu-chapter-head" data-chapter-toggle="{{ $sectionId }}">
        <button class="cu-col-chevron" tabindex="-1"><i class="bi bi-chevron-down"></i></button>
        @if($sectionUrl)
            <a href="{{ $sectionUrl }}" class="cu-chapter-title">{{ $sectionTitle }}</a>
        @else
            <span class="cu-chapter-title">{{ $sectionTitle }}</span>
        @endif
        <span class="cu-chapter-progress">
            <span class="cu-chapter-pb"><span class="cu-chapter-pb-fill" style="width:{{ $secPct }}%;"></span></span>
            <span class="cu-chapter-count" title="{{ $secDone }} of {{ $secTotal }} done">{{ $secDone }}/{{ $secTotal }}</span>
        </span>
        <span class="cu-col-count cu-chapter-open" title="Unfinished">{{ $secOpen }} open</span>
    </div>
    <div class="cu-chapter-body">
        @foreach($tasks as $t)
            @include('tasks._chapter-row', ['task' => $t, 'grouped' => $grouped, 'depth' => 0, 'rootId' => $sectionId])
        @endforeach
    </div>
</div>
