{{-- Version history for a note. --}}
<div class="nt-panel" id="ntRevisions">
    <div class="nt-panel-head">
        <i class="bi bi-clock-history"></i>{{ __('History') }}
        <span class="nt-count ms-auto">{{ app_num($revisions->count()) }}</span>
    </div>

    <div class="nt-panel-body">
        @forelse ($revisions as $rev)
            <div class="nt-rev-item">
                <span class="nt-rev-dot"></span>
                <div class="nt-rev-body">
                    <div class="nt-rev-title">{{ $rev->summaryText() }}</div>
                    <div class="nt-rev-meta">
                        {{ app_datetime($rev->created_at) }}
                        @if ($rev->change_source === \App\Models\NoteRevision::SOURCE_AI)
                            · <span class="nt-badge nt-badge-kind">{{ __('AI') }}</span>
                        @endif
                    </div>
                </div>
                <button type="button" class="btn btn-outline" style="padding:2px 9px;font-size:.72rem;"
                        data-restore="{{ $rev->id }}">
                    {{ __('Restore') }}
                </button>
            </div>
        @empty
            <p class="nt-form-help" style="margin:0">
                {{ __('No earlier versions yet — a snapshot is taken before each edit.') }}
            </p>
        @endforelse
    </div>
</div>