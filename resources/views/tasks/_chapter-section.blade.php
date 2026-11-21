{{-- Collapsible chapter section. Vars:
     $sectionId (string), $sectionTitle (string), $sectionUrl (string|null),
     $tasks (top-level tasks of this section), $grouped (tasks grouped by parent_id),
     $collapsed (bool), $quickProjectId (int|null — shows inline quick-add for that project) --}}
@php
    $quickProjectId = $quickProjectId ?? null;
    $completedCount = $completedCount ?? 0;
    $completedUrl = $completedUrl ?? null;
    $completedProjectId = $completedProjectId ?? 0;
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
@php $bodyId = 'cu-ch-body-' . $sectionId; @endphp
<div class="cu-chapter {{ $collapsed ? 'collapsed' : '' }}" data-chapter="{{ $sectionId }}"
     data-total="{{ $secTotal }}" data-done="{{ $secDone }}">
    <div class="cu-chapter-head" data-chapter-toggle="{{ $sectionId }}">
        <button type="button" class="cu-ch-chevron"
                aria-expanded="{{ $collapsed ? 'false' : 'true' }}" aria-controls="{{ $bodyId }}"
                title="{{ __('Collapse / expand') }}">
            <i class="bi bi-chevron-down"></i>
        </button>
        <span class="cu-ch-heading">
            @if($sectionUrl)
                <a href="{{ $sectionUrl }}" class="cu-chapter-title cu-ch-nav" title="{{ $sectionTitle }}">{{ $sectionTitle }}</a>
                <a href="{{ $sectionUrl }}" class="cu-ch-open" title="{{ __('View Details') }}"
                   aria-label="{{ __('View Details') }}">
                    <i class="bi bi-box-arrow-up-left"></i>
                </a>
            @else
                <span class="cu-chapter-title" title="{{ $sectionTitle }}">{{ $sectionTitle }}</span>
            @endif
        </span>
        <span class="cu-chapter-progress">
            <span class="cu-chapter-pb"><span class="cu-chapter-pb-fill" style="width:{{ $secPct }}%;"></span></span>
            <span class="cu-chapter-count" title="{{ __(':done of :total done', ['done' => $secDone, 'total' => $secTotal]) }}">{{ $secDone }}/{{ $secTotal }}</span>
        </span>
        <span class="cu-col-count cu-chapter-open" title="{{ __('Unfinished') }}">{{ $secOpen }} {{ __('open') }}</span>
        <button type="button" class="cu-ch-add cu-ch-add--quick"
                data-ch-quickadd="{{ (int) ($quickProjectId ?? 0) }}" data-section="{{ $sectionId }}"
                title="{{ __('Quick add task') }} (A)">
            <i class="bi bi-plus-lg"></i><span>{{ __('New') }}</span><span class="cu-kbd">A</span>
        </button>
    </div>
    <div class="cu-chapter-body" id="{{ $bodyId }}">
        {{-- Project-bound sections fix the project by the button; only
             unbound sections (chapters) ask for one. --}}
        <div class="cu-ch-quickform" data-ch-quickform="{{ $sectionId }}"
             data-ch-project="{{ $quickProjectId === null ? '' : (int) $quickProjectId }}" hidden>
            <i class="bi bi-plus-lg"></i>
            <input type="text" data-ch-quickinput maxlength="255" autocomplete="off"
                   placeholder="{{ __('Task title… Enter to add') }}" aria-label="{{ __('New task title') }}">
            <span class="cu-ch-quickhint"><kbd>&#8629;</kbd> {{ __('add') }} · <kbd>esc</kbd></span>
            @if($quickProjectId === null)
                <select class="cu-input cu-select" data-ch-quickproject>
                    <option value="">{{ __('No Project') }}</option>
                    @foreach($projects as $proj)
                        <option value="{{ $proj->id }}" {{ old('project_id', '') == $proj->id ? 'selected' : '' }}>{{ $proj->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>
        @foreach($tasks as $t)
            @include('tasks._chapter-row', ['task' => $t, 'grouped' => $grouped, 'depth' => 0, 'rootId' => $sectionId])
        @endforeach
        @if($completedCount > 0 && $completedUrl)
            <div class="cu-completed-acc" data-project="{{ $completedProjectId }}"
                 data-completed-url="{{ $completedUrl }}"
                 data-total="{{ $completedCount }}" data-loaded="0" data-page="0">
                <button type="button" class="cu-completed-toggle" aria-expanded="false">
                    <i class="bi bi-chevron-right cu-completed-chevron"></i>
                    {{ __('Completed') }} ({{ $completedCount }})
                </button>
                <div class="cu-completed-body" style="display:none;">
                    <div class="cu-completed-list"></div>
                    <button type="button" class="cu-completed-more btn btn-sm btn-link" style="display:none;">{{ __('Load more') }}</button>
                </div>
            </div>
        @endif
    </div>
</div>
