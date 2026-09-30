@php
    // Filters arrive as a full set from NoteController, or a minimal
    // ['notebook' => ...] when rendered standalone from NotebookController.
    $filters = $filters ?? [];
    $currentNotebook = $filters['notebook'] ?? null;
    $currentKinds = (array) ($filters['kind'] ?? []);
    $currentLabels = (array) ($filters['label'] ?? []);
    $facets = $facets ?? ['total' => 0, 'kind' => [], 'today' => 0, 'favorite' => 0, 'pinned' => 0, 'archived' => 0, 'unfiled' => 0];
    $kindMeta = $kindMeta ?? note_kind_meta();

    $qs = function (array $overrides = []) use ($filters) {
        $base = array_filter([
            'search'   => $filters['search']   ?? null,
            'kind'     => $filters['kind']     ?? null,
            'notebook' => $filters['notebook'] ?? null,
            'label'    => $filters['label']    ?? null,
            'favorite' => ! empty($filters['favorite']) ? 1 : null,
            'pinned'   => ! empty($filters['pinned']) ? 1 : null,
            'archived' => ! empty($filters['archived']) ? 1 : null,
            'from'     => $filters['from']     ?? null,
            'to'       => $filters['to']       ?? null,
            'linked_type' => $filters['linked_type'] ?? null,
            'linked_id'   => $filters['linked_id']   ?? null,
            'sort'     => $filters['sort']     ?? null,
            'view'     => $filters['view']     ?? null,
        ], fn ($v) => $v !== null && $v !== []);

        return request()->fullUrlWithQuery(array_merge($base, $overrides));
    };

    $isBase = fn (array $overrides) => $qs($overrides) === request()->fullUrl();
@endphp

<div class="nt-filters" id="nt-filters" data-collapsed="{{ request()->is('notes') ? 0 : 0 }}">

    {{-- Smart views --}}
    <div class="nt-fgroup">
        <div class="nt-fgroup-title">
            <i class="bi bi-collection"></i>{{ __('Views') }}
        </div>

        <a class="nt-flink {{ $isBase(['notebook' => null, 'kind' => [], 'label' => [], 'favorite' => null, 'pinned' => null, 'archived' => null, 'search' => null]) ? 'is-active' : '' }}"
           href="{{ $qs(['notebook' => null, 'kind' => [], 'label' => [], 'favorite' => null, 'pinned' => null, 'archived' => null, 'search' => null]) }}">
            <span class="nt-flink-ico"><i class="bi bi-journals"></i></span>
            <span class="nt-flink-text">{{ __('All notes') }}</span>
            <span class="nt-flink-n">{{ app_num($facets['total'] ?? 0) }}</span>
        </a>

        <a class="nt-flink {{ ! empty($filters['favorite']) ? 'is-active' : '' }}"
           href="{{ $qs(['favorite' => empty($filters['favorite']) ? 1 : null, 'pinned' => null, 'archived' => null]) }}">
            <span class="nt-flink-ico"><i class="bi bi-star-fill"></i></span>
            <span class="nt-flink-text">{{ __('Favorites') }}</span>
            <span class="nt-flink-n">{{ app_num($facets['favorite'] ?? 0) }}</span>
        </a>

        <a class="nt-flink {{ ! empty($filters['pinned']) ? 'is-active' : '' }}"
           href="{{ $qs(['pinned' => empty($filters['pinned']) ? 1 : null, 'favorite' => null, 'archived' => null]) }}">
            <span class="nt-flink-ico"><i class="bi bi-pin-angle-fill"></i></span>
            <span class="nt-flink-text">{{ __('Pinned') }}</span>
            <span class="nt-flink-n">{{ app_num($facets['pinned'] ?? 0) }}</span>
        </a>

        <a class="nt-flink {{ ! empty($filters['archived']) ? 'is-active' : '' }}"
           href="{{ $qs(['archived' => empty($filters['archived']) ? 1 : null]) }}">
            <span class="nt-flink-ico"><i class="bi bi-archive"></i></span>
            <span class="nt-flink-text">{{ __('Archived') }}</span>
            <span class="nt-flink-n">{{ app_num($facets['archived'] ?? 0) }}</span>
        </a>
    </div>

    {{-- Notebooks --}}
    <div class="nt-fgroup">
        <div class="nt-fgroup-title">
            <i class="bi bi-folder2-open"></i>{{ __('Notebooks') }}
            <button type="button" class="btn btn-outline ms-auto" style="padding:1px 7px;font-size:.7rem;"
                    data-bs-toggle="modal" data-bs-target="#ntNotebookModal"
                    title="{{ __('New notebook') }}">
                <i class="bi bi-plus-lg"></i>
            </button>
        </div>

        <a class="nt-flink {{ $currentNotebook === null ? 'is-active' : '' }}"
           href="{{ $qs(['notebook' => null]) }}">
            <span class="nt-flink-ico"><i class="bi bi-inbox"></i></span>
            <span class="nt-flink-text">{{ __('Unfiled') }}</span>
            <span class="nt-flink-n">{{ app_num($facets['unfiled'] ?? 0) }}</span>
        </a>

        @include('notes.partials._notebook_tree', ['notebooks' => $notebooks ?? collect(), 'filters' => $filters])
    </div>

    {{-- Note types --}}
    <div class="nt-fgroup">
        <div class="nt-fgroup-title">
            <i class="bi bi-tags"></i>{{ __('Type') }}
        </div>

        <div class="nt-kind-row">
            @foreach ($kindMeta as $kindKey => $kindInfo)
                @php $kindCount = (int) ($facets['kind'][$kindKey] ?? 0); @endphp
                @if ($kindCount > 0 || in_array($kindKey, $currentKinds, true))
                    @php $isOn = in_array($kindKey, $currentKinds, true); @endphp
                    <a class="nt-kind-chip {{ $isOn ? 'is-active' : '' }}"
                       href="{{ $qs(['kind' => $isOn ? [] : [$kindKey]]) }}">
                        <i class="bi {{ $kindInfo['icon'] }}"></i>
                        {{ $kindInfo['label'] }}
                        <span class="nt-kind-chip-n">{{ app_num($kindCount) }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Labels --}}
    @if (! empty($labels) && $labels->count())
        <div class="nt-fgroup">
            <div class="nt-fgroup-title">
                <i class="bi bi-bookmark"></i>{{ __('Labels') }}
            </div>

            @foreach ($labels as $label)
                @php $on = in_array((int) $label->id, array_map('intval', $currentLabels), true); @endphp
                <a class="nt-flink" href="{{ $qs(['label' => $on ? [] : [$label->id]]) }}"
                   style="{{ $on ? 'background:var(--primary-50);color:var(--primary-700)' : '' }}">
                    <span class="nt-lbl-dot" style="background:{{ $label->color }}"></span>
                    <span class="nt-flink-text">{{ $label->name }}</span>
                    <span class="nt-flink-n">{{ app_num($on ? ($label->filtered_count ?? 0) : $label->notes_count) }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>