@extends('layouts.app')

@section('title', $entry->title ?: __('Journal entry'))

@section('content')
<div class="container py-4">
    <div class="mb-3">
        <a href="{{ route('journal.index') }}" class="btn btn-link">&larr; {{ __('Back') }}</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <article>
        <header class="mb-3">
            <h1 class="h2 mb-1">
                {{ $entry->title ?: __('Untitled entry') }}
                @if ($entry->is_pinned) <i class="bi bi-pin-angle-fill text-warning"></i> @endif
                @if ($entry->is_favorite) <i class="bi bi-star-fill text-warning"></i> @endif
            </h1>
            <div class="text-muted small">
                {{ $entry->entry_date?->format('Y-m-d') }}
                @if ($entry->book) · {{ $entry->book->name }} @endif
                @if ($entry->mood_score) · {{ __('Mood') }} {{ $entry->mood_score }}/10 @endif
                @if ($entry->energy) · {{ __('Energy') }} {{ $entry->energy }}/5 @endif
                @if ($entry->sleep_hours) · {{ __('Sleep') }} {{ $entry->sleep_hours }}h @endif
                · {{ $entry->word_count }} {{ __('words') }}
            </div>
            @if ($entry->tags->count())
                <div class="mt-2">
                    @foreach ($entry->tags as $tag)
                        <span class="badge" style="background-color: {{ $tag->color ?? '#6c757d' }};">{{ $tag->name }}</span>
                    @endforeach
                </div>
            @endif
            @if ($entry->location_name)
                <div class="text-muted small mt-1"><i class="bi bi-geo-alt"></i> {{ $entry->location_name }}</div>
            @endif
            @if ($entry->weather_condition)
                <div class="text-muted small"><i class="bi bi-cloud"></i> {{ $entry->weather_condition }} @if($entry->weather_temp)({{ $entry->weather_temp }}°)@endif</div>
            @endif
        </header>

        <div class="journal-content mb-3">
            {!! \App\Support\MarkdownRenderer::toHtml((string) $entry->content) !!}
        </div>

        @if ($entry->moods->count())
            <div class="mb-3">
                @foreach ($entry->moods as $mood)
                    <span class="badge" style="background-color: {{ $mood->color ?? '#6c757d' }};">
                        {{ $mood->name }} ({{ $mood->pivot->intensity }}/5)
                    </span>
                @endforeach
            </div>
        @endif

        @if ($entry->links->count())
            <div class="mb-3">
                <h5>{{ __('Linked to') }}</h5>
                <ul class="list-group">
                    @foreach ($entry->links as $link)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <span class="badge bg-secondary">{{ class_basename($link->linkable_type) }}</span>
                                {{ $link->linkable?->title ?? ('#'.$link->linkable_id) }}
                                @if ($link->label) — {{ $link->label }} @endif
                            </span>
                            <form method="POST" action="{{ route('journal.links.destroy', [$entry, $link]) }}" style="display:inline" onsubmit="return confirm('{{ __('Remove link?') }}');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('Remove') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-4">
            <h5>{{ __('Add link') }}</h5>
            <form method="POST" action="{{ route('journal.links.store', $entry) }}" class="row g-2">
                @csrf
                <div class="col-md-3">
                    <select name="linkable_type" class="form-select" required>
                        <option value="task">{{ __('Task') }}</option>
                        <option value="routine">{{ __('Routine') }}</option>
                        <option value="law">{{ __('Law') }}</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <select name="linkable_id" class="form-select" required>
                        <optgroup label="{{ __('Tasks') }}">
                            @foreach ($tasks as $t)<option value="{{ $t->id }}">{{ $t->title }}</option>@endforeach
                        </optgroup>
                        <optgroup label="{{ __('Routines') }}">
                            @foreach ($routines as $r)<option value="{{ $r->id }}">{{ $r->title }}</option>@endforeach
                        </optgroup>
                        <optgroup label="{{ __('Laws') }}">
                            @foreach ($laws as $l)<option value="{{ $l->id }}">{{ $l->title }}</option>@endforeach
                        </optgroup>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="text" name="label" class="form-control" placeholder="{{ __('Label') }}">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100">{{ __('Add') }}</button>
                </div>
            </form>
        </div>
    </article>

    <div class="mt-4 d-flex gap-2">
        <a href="{{ route('journal.edit', $entry) }}" class="btn btn-outline-primary">{{ __('Edit') }}</a>
        <a href="{{ route('journal.revisions', $entry) }}" class="btn btn-outline-secondary">{{ __('History') }}</a>
        <form method="POST" action="{{ route('journal.destroy', $entry) }}" onsubmit="return confirm('{{ __('Delete this entry?') }}');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">{{ __('Delete') }}</button>
        </form>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSel = document.querySelector('select[name="linkable_type"]');
    const idSel = document.querySelector('select[name="linkable_id"]');
    if (!typeSel || !idSel) return;
    function filter() {
        const type = typeSel.value;
        Array.from(idSel.querySelectorAll('optgroup')).forEach(g => {
            const label = g.getAttribute('label').toLowerCase();
            const match = (type === 'task' && label.includes('task')) ||
                          (type === 'routine' && label.includes('routine')) ||
                          (type === 'law' && label.includes('law'));
            g.hidden = !match;
        });
        // reset selection if hidden
        const selected = idSel.options[idSel.selectedIndex];
        if (selected && selected.parentElement && selected.parentElement.hidden) {
            const firstVisible = idSel.querySelector('optgroup:not([hidden]) option');
            if (firstVisible) idSel.value = firstVisible.value;
        }
    }
    typeSel.addEventListener('change', filter);
    filter();
});
</script>
@endpush

@endsection
