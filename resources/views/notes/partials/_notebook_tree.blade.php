{{--
    Recursive notebook tree. Rendered standalone by NotebookController@destroy,
    so it must not rely on variables outside $notebooks / $filters.
--}}
@php
    $currentNotebook = $filters['notebook'] ?? null;

    $nbUrl = function ($id) use ($filters) {
        $base = array_filter([
            'search' => $filters['search'] ?? null,
            'kind' => $filters['kind'] ?? null,
            'label' => $filters['label'] ?? null,
            'favorite' => ! empty($filters['favorite']) ? 1 : null,
            'pinned' => ! empty($filters['pinned']) ? 1 : null,
            'archived' => ! empty($filters['archived']) ? 1 : null,
            'view' => $filters['view'] ?? null,
            'sort' => $filters['sort'] ?? null,
        ], fn ($v) => $v !== null && $v !== []);

        return request()->fullUrlWithQuery(array_merge($base, ['notebook' => $id]));
    };
@endphp

@if (count($notebooks))
    @foreach ($notebooks as $nb)
        <div class="nt-nb-row">
            <a class="nt-flink {{ (int) $currentNotebook === (int) $nb->id ? 'is-active' : '' }}"
               href="{{ $nbUrl($nb->id) }}">
                <span class="nt-flink-ico"><i class="bi {{ $nb->icon ?: 'bi-folder' }}"></i></span>
                <span class="nt-flink-text" title="{{ $nb->title }}">{{ $nb->title }}</span>
                <span class="nt-flink-n">{{ app_num($nb->notes_count ?? 0) }}</span>
            </a>
            <button type="button" class="nt-linkitem-x" style="padding:3px 5px"
                    data-nb-edit="{{ $nb->id }}"
                    data-nb-title="{{ $nb->title }}"
                    data-nb-color="{{ $nb->color }}"
                    data-nb-icon="{{ $nb->icon }}"
                    data-nb-parent="{{ $nb->parent_id }}"
                    data-bs-toggle="modal" data-bs-target="#ntNotebookModal"
                    title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></button>
        </div>

        @if (count($nb->children ?? []))
            <div class="nt-nb-kids">
                @include('notes.partials._notebook_tree', ['notebooks' => $nb->children, 'filters' => $filters])
            </div>
        @endif
    @endforeach
@else
    <p class="nt-form-help" style="padding:0 9px;margin:0">
        {{ __('No notebooks yet.') }}
    </p>
@endif