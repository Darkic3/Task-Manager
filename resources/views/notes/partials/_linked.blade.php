@php
    $groups = $linkGroups ?? collect();
@endphp

<div class="nt-panel" id="ntLinked">
    <div class="nt-panel-head">
        <i class="bi bi-diagram-3"></i>{{ __('Linked to') }}
        <span class="nt-count ms-auto">{{ $groups->sum(fn ($g) => $g['items']->count()) }}</span>
    </div>

    <div class="nt-panel-body">
        @forelse ($groups as $group)
            <div class="nt-linkgroup">
                <div class="nt-linkgroup-label">{{ strtolower($group['label']) }}</div>
                @foreach ($group['items'] as $item)
                    @php
                        $href = match ($group['type']) {
                            \App\Models\NoteSubject::class => null,
                            \App\Models\Note::class => route('notes.show', $item['id']),
                            \App\Models\Project::class => route('projects.show', $item['id']),
                            \App\Models\Task::class => route('tasks.show', $item['id']),
                            default => null,
                        };
                    @endphp
                    <span class="nt-linkitem">
                        @if ($href)
                            <a href="{{ $href }}" style="color:inherit;text-decoration:none;">{{ $item['name'] }}</a>
                        @else
                            {{ $item['name'] }}
                        @endif
                        <button type="button" class="nt-linkitem-x" data-unlink="{{ $item['link_id'] }}"
                                title="{{ __('Unlink') }}" aria-label="{{ __('Unlink') }}">
                            <i class="bi bi-x"></i>
                        </button>
                    </span>
                @endforeach
            </div>
        @empty
            <p class="nt-form-help" style="margin:0">
                {{ __('Nothing linked yet. Write @name or #label in the body and they appear here.') }}
            </p>
        @endforelse
    </div>
</div>