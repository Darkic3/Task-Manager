@php
    $compact = $compact ?? false;
    $labels = $note->relationLoaded('labels') ? $note->labels : collect();
    $mood = $note->mood ? note_mood_icon($note->mood) : null;
@endphp

<article class="nt-card {{ $note->is_pinned ? 'is-pinned' : '' }} {{ $note->status === \App\Models\Note::STATUS_ARCHIVED ? 'is-archived' : '' }}"
         data-note-id="{{ $note->id }}">

    <div class="nt-card-top">
        <span class="nt-card-kind" title="{{ note_kind_label($note->kind) }}">
            <i class="bi {{ note_kind_icon($note->kind) }}"></i>
        </span>

        <h3 class="nt-card-title">
            <a href="{{ route('notes.show', $note) }}" style="color:inherit;text-decoration:none;">{{ $note->title }}</a>
        </h3>

        <button type="button" class="nt-star {{ $note->is_favorite ? 'is-on' : '' }}"
                data-fav="{{ $note->id }}"
                title="{{ $note->is_favorite ? __('Remove favourite') : __('Add favourite') }}"
                aria-label="{{ __('Toggle favourite') }}">
            <i class="bi {{ $note->is_favorite ? 'bi-star-fill' : 'bi-star' }}"></i>
        </button>

        @if ($note->is_pinned)
            <span class="nt-badge nt-badge-kind" title="{{ __('Pinned') }}"><i class="bi bi-pin-angle-fill"></i></span>
        @endif
    </div>

    @if (! $compact && $note->excerpt)
        <p class="nt-card-excerpt">{{ $note->excerpt }}</p>
    @endif

    <div class="nt-card-foot">
        @if ($note->effectiveDate())
            <span class="nt-meta">
                <i class="bi bi-clock"></i>{{ app_date($note->effectiveDate()) }}
            </span>
        @endif

        @if ($note->notebook)
            <span class="nt-sep">·</span>
            <a class="nt-badge nt-badge-nb" href="{{ request()->fullUrlWithQuery(['notebook' => $note->notebook_id]) }}"
               style="text-decoration:none;">
                <i class="bi {{ $note->notebook->icon ?: 'bi-folder' }}"></i>{{ $note->notebook->title }}
            </a>
        @endif

        @if ($note->category)
            <span class="nt-badge nt-badge-legacy" title="{{ __('Legacy category') }}">{{ $note->category }}</span>
        @endif

        @foreach ($labels->take(3) as $label)
            <span class="nt-badge nt-badge-lbl" style="background:{{ $label->color }}1a;color:{{ $label->color }}">#{{ $label->name }}</span>
        @endforeach

        @if ($mood)
            <span class="nt-sep">·</span>
            <span class="nt-mood" title="{{ __('Mood') }}">{{ $mood['emoji'] }}</span>
        @endif

        <span class="ms-auto nt-meta">
            {{ app_num($note->word_count) }} {{ __('words') }}
        </span>
    </div>
</article>