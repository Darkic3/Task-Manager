{{-- Backlinks: other notes that point at the same thing. --}}
<div class="nt-panel" id="ntBacklinks">
    <div class="nt-panel-head">
        <i class="bi bi-signpost-split"></i>{{ $heading ?? __('Linked notes') }}
    </div>

    <div class="nt-panel-body">
        @forelse ($notes as $bl)
            <a class="nt-flink" href="{{ route('notes.show', $bl) }}" style="margin-bottom:3px;">
                <span class="nt-flink-ico"><i class="bi {{ note_kind_icon($bl->kind) }}"></i></span>
                <span class="nt-flink-text">{{ $bl->title }}</span>
                <span class="nt-flink-n">{{ app_date($bl->effectiveDate()) }}</span>
            </a>
        @empty
            <p class="nt-form-help" style="margin:0">{{ __('Nothing links here yet.') }}</p>
        @endforelse
    </div>
</div>