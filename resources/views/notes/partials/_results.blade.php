@if ($notes->total() === 0)
    <div class="nt-empty">
        <i class="bi bi-journal-x"></i>
        <h3>{{ __('No notes found') }}</h3>
        <p>{{ __('Try another filter, or capture something new above.') }}</p>
        <a href="{{ route('notes.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i>{{ __('New note') }}
        </a>
    </div>
@else
    @if ($filters['view'] === 'timeline')
        <div class="nt-panel-head" style="background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-lg) var(--radius-lg) 0 0;border-bottom:none;">
            <i class="bi bi-clock-history"></i>{{ __('Timeline') }}
            <span class="nt-count ms-auto">{{ app_num($notes->total()) }} {{ __('notes') }}</span>
        </div>
        <div class="nt-panel" style="border-radius:0 0 var(--radius-lg) var(--radius-lg);padding:14px;">
            <div class="nt-timeline">
                @php
                    $items = collect($notes->items())->filter(fn ($n) => $n->effectiveDate());
                    $grouped = $items->groupBy(fn ($n) => $n->effectiveDate()->format('Y-m'));
                @endphp

                @foreach ($grouped as $ym => $monthNotes)
                    @php
                        $first = $monthNotes->first()->effectiveDate();
                        $monthLabel = app()->getLocale() === 'fa'
                            ? \Morilog\Jalali\Jalalian::fromCarbon($first)->format('%B %Y')
                            : $first->format('F Y');
                    @endphp

                    <div class="nt-tl-month"><i class="bi bi-calendar3"></i>{{ $monthLabel }}</div>

                    @foreach ($monthNotes as $note)
                        @php $date = $note->effectiveDate(); @endphp
                        <div class="nt-tl-item">
                            <span class="nt-tl-dot"></span>
                            <div class="nt-tl-day">
                                <i class="bi {{ note_kind_icon($note->kind) }}"></i>
                                {{ app_date($date, app()->getLocale() === 'fa' ? 'D d MMM' : 'D d MMM') }}
                                <span class="nt-badge nt-badge-kind">{{ note_kind_label($note->kind) }}</span>
                            </div>
                            @include('notes.partials._card', ['note' => $note, 'compact' => true])
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    @else
        <div class="nt-list">
            @foreach ($notes as $note)
                @include('notes.partials._card', ['note' => $note])
            @endforeach
        </div>
    @endif

    @if ($notes->hasPages())
        <div class="nt-pager">
            {{ $notes->links() }}
        </div>
    @endif
@endif