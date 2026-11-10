@extends('layouts.app')

@section('title', $entry->title ?: __('Journal entry'))

@section('content')
@include('journal._styles')
<div class="container py-4">
    <div class="jn-page">
        <div class="jn-head">
            <div>
                <h1>{{ $entry->title ?: __('Untitled entry') }}</h1>
                <div class="jn-sub">
                    {{ $entry->entry_date?->format('Y-m-d') }}
                    @if ($entry->book) · {{ $entry->book->name }} @endif
                    @if ($entry->mood_score) · {{ __('Mood') }} {{ $entry->mood_score }}/10 @endif
                    @if ($entry->energy) · {{ __('Energy') }} {{ $entry->energy }}/5 @endif
                    @if ($entry->sleep_hours) · {{ __('Sleep') }} {{ $entry->sleep_hours }}h @endif
                    · {{ $entry->word_count }} {{ __('words') }}
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('journal.edit', $entry) }}" class="btn btn-outline-primary btn-sm">{{ __('Edit') }}</a>
                <a href="{{ route('journal.revisions', $entry) }}" class="btn btn-outline-secondary btn-sm">{{ __('History') }}</a>
            </div>
        </div>

        @if ($entry->tags->count() || $entry->moods->count())
            <div class="jn-card">
                @if ($entry->tags->count())
                    <div class="jn-chips mb-2">
                        @foreach ($entry->tags as $tag)
                            <span class="jn-chip is-on" style="cursor:default;">
                                @if ($tag->color)<span style="width:8px;height:8px;border-radius:50%;background:{{ $tag->color }};display:inline-block;"></span>@endif
                                {{ $tag->name }}
                            </span>
                        @endforeach
                    </div>
                @endif
                @if ($entry->moods->count())
                    <div class="jn-chips">
                        @foreach ($entry->moods as $mood)
                            <span class="jn-chip is-on" style="cursor:default;">
                                @if ($mood->color)<span style="width:8px;height:8px;border-radius:50%;background:{{ $mood->color }};display:inline-block;"></span>@endif
                                {{ $mood->name }} ({{ $mood->pivot->intensity }}/5)
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if ($entry->location_name || $entry->weather_condition)
            <div class="jn-card">
                <div class="text-muted small">
                    @if ($entry->location_name)<i class="bi bi-geo-alt"></i> {{ $entry->location_name }} @endif
                    @if ($entry->weather_condition)<span class="ms-3"><i class="bi bi-cloud"></i> {{ $entry->weather_condition }} @if($entry->weather_temp)({{ $entry->weather_temp }}°)@endif</span>@endif
                </div>
            </div>
        @endif

        <div class="jn-card">
            <div class="journal-content">
                {!! \App\Support\MarkdownRenderer::toHtml((string) $entry->content) !!}
            </div>
        </div>

        @if ($entry->links->count())
            <div class="jn-card">
                <h3 class="jn-card-title"><i class="bi bi-link-45deg"></i>{{ __('Linked to') }}</h3>
                <ul class="list-group list-group-flush">
                    @foreach ($entry->links as $link)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
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

        <div class="jn-card">
            <h3 class="jn-card-title"><i class="bi bi-link-45deg"></i>{{ __('Add link') }}</h3>
            <form method="POST" action="{{ route('journal.links.store', $entry) }}" class="row g-2">
                @csrf
                <div class="col-md-3">
                    <select name="linkable_type" class="jn-select" required>
                        <option value="task">{{ __('Task') }}</option>
                        <option value="routine">{{ __('Routine') }}</option>
                        <option value="law">{{ __('Law') }}</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <select name="linkable_id" class="jn-select" required>
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
                    <input type="text" name="label" class="jn-input" placeholder="{{ __('Label') }}">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100">{{ __('Add') }}</button>
                </div>
            </form>
        </div>

        <div class="mt-3">
            <form method="POST" action="{{ route('journal.destroy', $entry) }}" onsubmit="return confirm('{{ __('Delete this entry?') }}');" style="display:inline;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Delete') }}</button>
            </form>
        </div>
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
