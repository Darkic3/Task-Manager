@extends('layouts.app')

@section('title', __('Notes'))

@include('notes._styles')

@push('styles')
<style>
    .nt-head {
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        margin-bottom: 13px;
    }
    .nt-head-ico {
        width: 42px; height: 42px; flex: none; border-radius: 13px;
        display: grid; place-items: center; font-size: 1.15rem; color: #fff;
        background: linear-gradient(135deg, #7c3aed, #5b21b6);
        box-shadow: 0 10px 20px -10px rgba(91,33,182,.7);
    }
    .nt-head-title { margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--gray-900); line-height: 1.35; }
    .nt-head-sub { font-size: .78rem; color: var(--gray-500); font-weight: 600; margin: 1px 0 0; }
</style>
@endpush

@section('content')
<div class="nt-head">
    <span class="nt-head-ico"><i class="bi bi-journal-richtext"></i></span>
    <div>
        <h1 class="nt-head-title">{{ __('Notes') }}</h1>
        <p class="nt-head-sub">{{ __('Capture, connect and filter everything you write down') }}</p>
    </div>
    <div class="ms-auto d-flex gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'markdown']) }}" class="btn btn-outline"
           onclick="event.preventDefault();window.location.href='{{ route('notes.export.markdown', request()->query()) }}';">
            <i class="bi bi-download"></i>{{ __('Export MD') }}
        </a>
        <a href="{{ route('notes.create') }}" class="btn btn-brand">
            <i class="bi bi-plus-lg"></i>{{ __('New note') }}
        </a>
    </div>
</div>

<div class="nt-shell">
    {{-- Filters --}}
    <div id="nt-filters-wrap">
        @include('notes.partials._sidebar')
    </div>

    {{-- Main column --}}
    <div>
        @include('notes.partials._quick_capture')

        @include('notes.partials._density_strip')

        {{-- Toolbar --}}
        <div class="nt-toolbar">
            <div class="nt-search-wrap">
                <i class="bi bi-search"></i>
                <input type="search" class="nt-search" id="ntSearch"
                       placeholder="{{ __('Search title, body, labels, people…') }}"
                       value="{{ $filters['search'] ?? '' }}" autocomplete="off">
                <button type="button" class="nt-search-clear {{ $filters['search'] ? 'is-on' : '' }}" id="ntSearchClear"
                        aria-label="{{ __('Clear search') }}"><i class="bi bi-x-lg"></i></button>
            </div>

            <span class="nt-count" id="ntCount">{{ app_num($notes->total()) }} {{ __('notes') }}</span>

            <div class="nt-seg" role="group" aria-label="{{ __('View') }}">
                <a class="nt-seg-btn {{ $filters['view'] !== 'timeline' ? 'is-active' : '' }}" id="ntViewList"
                   href="{{ request()->fullUrlWithQuery(['view' => null]) }}">
                    <i class="bi bi-list-ul"></i><span>{{ __('List') }}</span>
                </a>
                <a class="nt-seg-btn {{ $filters['view'] === 'timeline' ? 'is-active' : '' }}" id="ntViewTimeline"
                   href="{{ request()->fullUrlWithQuery(['view' => 'timeline']) }}">
                    <i class="bi bi-clock-history"></i><span>{{ __('Timeline') }}</span>
                </a>
            </div>

            <div class="nt-seg" role="group" aria-label="{{ __('Sort') }}">
                @foreach (['recent' => __('Newest'), 'oldest' => __('Oldest'), 'title' => __('A–Z')] as $sKey => $sLabel)
                    <a class="nt-seg-btn {{ ($filters['sort'] ?? 'recent') === $sKey ? 'is-active' : '' }}"
                       href="{{ request()->fullUrlWithQuery(['sort' => $sKey === 'recent' ? null : $sKey]) }}">{{ $sLabel }}</a>
                @endforeach
            </div>
        </div>

        {{-- Active filter chips --}}
        @php
            $chips = [];
            if ($filters['search'] ?? null) {
                $chips[] = ['label' => __('Search').': '.$filters['search'], 'url' => request()->fullUrlWithQuery(['search' => null])];
            }
            foreach ((array) ($filters['kind'] ?? []) as $k) {
                $chips[] = ['label' => note_kind_label($k), 'url' => request()->fullUrlWithQuery(['kind' => array_values(array_diff((array) $filters['kind'], [$k])) ?: null])];
            }
            foreach ((array) ($filters['label'] ?? []) as $lid) {
                $lab = $labels->firstWhere('id', (int) $lid);
                $chips[] = ['label' => '#'.($lab->name ?? $lid), 'url' => request()->fullUrlWithQuery(['label' => array_values(array_diff((array) $filters['label'], [$lid])) ?: null])];
            }
            if (! empty($filters['favorite'])) {
                $chips[] = ['label' => __('Favorites'), 'url' => request()->fullUrlWithQuery(['favorite' => null])];
            }
            if (! empty($filters['pinned'])) {
                $chips[] = ['label' => __('Pinned'), 'url' => request()->fullUrlWithQuery(['pinned' => null])];
            }
            if (! empty($filters['archived'])) {
                $chips[] = ['label' => __('Archived'), 'url' => request()->fullUrlWithQuery(['archived' => null])];
            }
            if (! empty($filters['from'])) {
                $chips[] = ['label' => app_date($filters['from']), 'url' => request()->fullUrlWithQuery(['from' => null, 'to' => null])];
            }
        @endphp

        @if ($chips)
            <div class="nt-chipbar">
                @foreach ($chips as $chip)
                    <span class="nt-chip">{{ $chip['label'] }}<a href="{{ $chip['url'] }}" aria-label="{{ __('Remove filter') }}"><i class="bi bi-x"></i></a></span>
                @endforeach
                <a href="{{ route('notes.index') }}" class="nt-chip" style="background:#f1f5f9;border-color:var(--gray-200);color:var(--gray-600);text-decoration:none;">
                    {{ __('Clear all') }}
                </a>
            </div>
        @endif

        {{-- Results --}}
        <div id="nt-results" style="transition:opacity .15s;">
            @include('notes.partials._results')
        </div>
    </div>
</div>

{{-- New / edit notebook modal --}}
<div class="modal fade" id="ntNotebookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-lg);">
            <form id="ntNotebookForm" method="POST">
                @csrf
                <input type="hidden" name="_method" id="ntNotebookMethod" value="">
                <input type="hidden" name="id" id="ntNotebookId" value="">

                <div class="modal-header" style="border-bottom-color:var(--gray-200);">
                    <h5 class="modal-title" style="font-size:.95rem;font-weight:800;" id="ntNotebookModalTitle">{{ __('New notebook') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>

                <div class="modal-body">
                    <div class="nt-form-row">
                        <label class="nt-form-label" for="ntNotebookTitleInput">{{ __('Title') }}</label>
                        <input type="text" class="form-control" id="ntNotebookTitleInput" name="title" maxlength="120" required>
                    </div>
                    <div class="nt-form-row">
                        <label class="nt-form-label" for="ntNotebookParent">{{ __('Parent notebook') }}</label>
                        <select class="nt-select" id="ntNotebookParent" name="parent_id">
                            <option value="">{{ __('— none —') }}</option>
                            @foreach (Notebook::treeFor(auth()->id()) as $root)
                                <option value="{{ $root->id }}">{{ $root->pathTitle() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="nt-form-row">
                        <label class="nt-form-label" for="ntNotebookColor">{{ __('Colour') }}</label>
                        <input type="color" class="form-control form-control-color" id="ntNotebookColor" name="color" value="#6366f1">
                    </div>
                </div>

                <div class="modal-footer" style="border-top-color:var(--gray-200);">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    /* ── Debounced live search (server-side, keeps the URL shareable) ── */
    const search  = document.getElementById('ntSearch');
    const clear   = document.getElementById('ntSearchClear');
    const countEl = document.getElementById('ntCount');
    const host    = document.getElementById('nt-results');
    let t = null;

    function reload(url) {
        host.style.opacity = '.45';
        const target = new URL(url, window.location.origin);
        target.searchParams.set('partial', '1');
        fetch(target.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            host.innerHTML = data.html;
            const side = document.getElementById('nt-filters-wrap');
            if (side && data.sidebar) side.innerHTML = data.sidebar;
            countEl.textContent = data.total + ' ' + '{{ __('notes') }}';
            window.history.replaceState({}, '', target.toString().replace(/([&?]?)partial=1/, ''));
            host.style.opacity = '1';
        })
        .catch(() => { window.location.href = url; });
    }

    if (search) {
        search.addEventListener('input', () => {
            clear.classList.toggle('is-on', search.value !== '');
            clearTimeout(t);
            t = setTimeout(() => {
                const u = new URL(window.location.href);
                search.value ? u.searchParams.set('search', search.value) : u.searchParams.delete('search');
                reload(u.toString());
            }, 320);
        });
        clear.addEventListener('click', () => {
            search.value = '';
            clear.classList.remove('is-on');
            const u = new URL(window.location.href);
            u.searchParams.delete('search');
            reload(u.toString());
        });
    }

    /* ── Favourite toggle (event delegation survives AJAX swaps) ── */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-fav]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        btn.disabled = true;
        fetch(`/notes/${btn.dataset.fav}/toggle-favorite`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            btn.classList.toggle('is-on', d.is_favorite);
            btn.querySelector('i').className = d.is_favorite ? 'bi bi-star-fill' : 'bi bi-star';
        })
        .catch(() => {})
        .finally(() => { btn.disabled = false; });
    });

    /* ── Notebook create / edit ── */
    const modal = document.getElementById('ntNotebookModal');
    const form  = document.getElementById('ntNotebookForm');

    document.querySelectorAll('[data-bs-target="#ntNotebookModal"]').forEach(btn => {
        btn.addEventListener('click', function () {
            const editId = this.dataset.nbEdit;
            document.getElementById('ntNotebookModalTitle').textContent = editId ? '{{ __('Edit notebook') }}' : '{{ __('New notebook') }}';
            document.getElementById('ntNotebookId').value = editId || '';
            document.getElementById('ntNotebookMethod').value = editId ? 'PUT' : '';
            document.getElementById('ntNotebookTitleInput').value = this.dataset.nbTitle || '';
            document.getElementById('ntNotebookColor').value = this.dataset.nbColor || '#6366f1';
            document.getElementById('ntNotebookParent').value = this.dataset.nbParent || '';
            form.action = editId ? `/notebooks/${editId}` : '{{ route('notebooks.store') }}';
        });
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(form);
            if (document.getElementById('ntNotebookMethod').value) fd.set('_method', 'PUT');
            fetch(form.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(r => r.json())
            .then(() => { bootstrap.Modal.getInstance(modal).hide(); window.location.reload(); })
            .catch(() => {});
        });
    }
});
</script>
@endpush