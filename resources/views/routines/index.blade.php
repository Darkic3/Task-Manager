@extends('layouts.app')

@section('title', 'Routines')

@push('styles')
<style>
/* ── Routines hub – minimal ──────────────────────────────── */
.main-content { padding:20px 24px 48px; background:#fafafa; min-height:100vh; }
.rh-wrap { max-width:860px; margin:0 auto; }

.rh-topbar { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.rh-title { font-size:19px; font-weight:700; color:#1f2328; margin:0; flex:1; }
.rh-count {
    font-size:12px; color:#8b8d98; font-weight:600;
}
.rh-btn {
    display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:6px;
    border:1px solid #e5e7eb; background:white; color:#6b6f78; font-size:12px; font-weight:600;
    text-decoration:none; transition:all .12s;
}
.rh-btn:hover { border-color:#7c3aed; color:#7c3aed; background:#f7f5ff; }
.rh-btn.primary { background:#7c3aed; border-color:#7c3aed; color:white; }
.rh-btn.primary:hover { background:#6d28d9; color:white; }

.rh-score {
    display:flex; align-items:center; gap:14px;
    background:white; border:1px solid #e5e7eb; border-radius:8px;
    padding:14px 18px; margin-bottom:16px;
}
.rh-score-pct { font-size:26px; font-weight:800; color:#1f2328; line-height:1; }
.rh-score-lbl { font-size:11px; color:#8b8d98; font-weight:600; text-transform:uppercase; letter-spacing:.4px; margin-top:3px; }
.rh-score-bar { flex:1; height:5px; background:#eef0f2; border-radius:4px; overflow:hidden; }
.rh-score-fill { height:100%; background:#30a46c; border-radius:4px; }
.rh-score-cnt { font-size:12.5px; color:#6b6f78; font-weight:600; white-space:nowrap; }

.rh-filters { display:flex; gap:6px; margin-bottom:12px; flex-wrap:wrap; }
.rh-chip {
    display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:20px;
    border:1px solid #e5e7eb; background:white; color:#6b6f78; font-size:11.5px; font-weight:600;
    cursor:pointer; user-select:none; text-decoration:none; transition:all .12s;
}
.rh-chip:hover { border-color:#c4b5fd; color:#7c3aed; }
.rh-chip.active { background:#7c3aed; border-color:#7c3aed; color:white; }
.rh-chip small { opacity:.75; font-weight:700; }

.rh-list { display:flex; flex-direction:column; gap:8px; }
.rh-card {
    display:flex; align-items:center; gap:10px;
    background:white; border:1px solid #e5e7eb; border-radius:8px; padding:10px 12px;
    transition:border-color .12s;
}
.rh-card:hover { border-color:#d3d7de; }
.rh-card.hidden { display:none; }
.rh-body { flex:1; min-width:0; }
.rh-row-title { font-size:13.5px; font-weight:600; color:#1f2328; line-height:1.35; word-break:break-word; }
.rh-card:hover .rh-row-title { color:#7c3aed; }
.rh-row-meta { display:flex; align-items:center; flex-wrap:wrap; gap:6px 10px; margin-top:4px; }
.rh-pill {
    display:inline-flex; align-items:center; gap:4px; padding:1px 8px; border-radius:20px;
    font-size:10.5px; font-weight:600; background:#f2f3f5; color:#6b6f78;
}
.rh-pill i { font-size:10px; }
.rh-flame {
    font-size:11px; font-weight:700; color:#d97706; background:#fdf4de;
    border-radius:20px; padding:1px 7px;
}
.rh-rate { font-size:11px; font-weight:700; color:#29774b; }
.rh-rate.x { color:#c1c4cc; font-weight:600; }
.last7{display:inline-flex;gap:3px;align-items:center;}
.last7 .sq{width:7px;height:7px;border-radius:2.5px;display:inline-block;}
.sq-done{background:#30a46c;}
.sq-missed{background:#e3e5e9;}
.sq-na{background:#f2f3f5;}
.sq-future,.sq-today{background:transparent;box-shadow:inset 0 0 0 1px #e8eaef;}

.rh-actions { flex-shrink:0; display:flex; align-items:center; gap:4px; }
.rh-link-btn {
    width:26px;height:26px;border-radius:6px;display:flex;align-items:center;justify-content:center;
    color:#8b8d98;font-size:12.5px;text-decoration:none;border:1px solid transparent;
}
.rh-link-btn:hover { color:#7c3aed; background:#f7f5ff; }
.rh-link-btn.text-danger:hover { color:#e5484d; background:#fef2f2; }
.rh-menu .dropdown-menu { font-size:13px; border-radius:8px; --bs-dropdown-min-width:130px; }
.rh-kebab {
    width:26px;height:26px;border:none;background:transparent;color:#aeb2ba;
    display:flex;align-items:center;justify-content:center;cursor:pointer;border-radius:5px;
    font-size:13px;
}
.rh-kebab:hover { background:#f0f1f3; color:#1f2328; }

.rh-empty {
    text-align:center; padding:50px 20px; background:white; border:1px solid #e5e7eb; border-radius:8px;
    color:#8b8d98;
}
.rh-empty i { font-size:32px; color:#c1c4cc; display:block; margin-bottom:10px; }
.rh-empty h5 { font-weight:700; color:#1f2328; margin-bottom:5px; }
.rh-empty p { font-size:13px; margin-bottom:14px; }

@media(max-width:640px) {
    .main-content { padding:14px 14px 40px; }
    .rh-row-meta .rh-rate { display:none; }
}
</style>
@endpush

@section('content')
<div class="main-content">
<div class="rh-wrap">

    <div class="rh-topbar">
        <h1 class="rh-title">Routines</h1>
        <span class="rh-count">{{ $routines->count() }}</span>
        <a href="{{ route('routines.create') }}" class="rh-btn primary"><i class="bi bi-plus-lg"></i> New Routine</a>
    </div>

    {{-- Weekly consistency score --}}
    <div class="rh-score">
        <div>
            <div class="rh-score-pct">{{ $weekly['rate'] }}%</div>
            <div class="rh-score-lbl">This week</div>
        </div>
        <div class="rh-score-bar"><div class="rh-score-fill" style="width:{{ $weekly['rate'] }}%;"></div></div>
        <div class="rh-score-cnt">{{ $weekly['done'] }}/{{ $weekly['total'] }} done</div>
    </div>

    {{-- Frequency filter --}}
    @php $counts = ['all' => $routines->count(), 'daily' => $routines->where('frequency','daily')->count(), 'weekly' => $routines->where('frequency','weekly')->count(), 'monthly' => $routines->where('frequency','monthly')->count()]; @endphp
    <div class="rh-filters" id="rhFilters">
        @foreach(['all' => 'All', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $key => $label)
            <button class="rh-chip {{ ($key === 'all') ? 'active' : '' }}" data-filter="{{ $key }}">
                {{ $label }} <small>{{ $counts[$key] }}</small>
            </button>
        @endforeach
    </div>

    <div class="rh-list" id="rhList">
        @forelse($routines as $routine)
            <div class="rh-item" data-frequency="{{ $routine->frequency }}">
                <div class="rh-card">
                    <div class="rh-body">
                        <div class="rh-row-title">{{ $routine->title }}</div>
                        <div class="rh-row-meta">
                            <span class="rh-pill"><i class="bi bi-arrow-repeat"></i> {{ $routine->recurrenceLabel() }}</span>
                            @if($routine->timeLabel())
                                <span class="rh-pill"><i class="bi bi-clock"></i> {{ $routine->timeLabel() }}</span>
                            @endif
                            @if(($routine->ringStreak ?? 0) > 0)
                                <span class="rh-flame" title="Current streak">🔥{{ $routine->ringStreak }}</span>
                            @endif
                            @if($routine->ringLast7 !== null)
                                <span class="last7" title="Last 7 days">
                                    @foreach($routine->ringLast7 as $sq)
                                        <i class="sq sq-{{ $sq['state'] }}"></i>
                                    @endforeach
                                </span>
                            @endif
                            <span class="rh-rate {{ ($routine->ringRate ?? 0) === 0 ? 'rh-rate-dim' : '' }}">
                                {{ $routine->ringRate ?? 0 }}%
                            </span>
                        </div>
                    </div>
                    <div class="rh-actions">
                        <a href="{{ route('routines.stats', $routine->id) }}" class="rh-link-btn" title="Stats"><i class="bi bi-graph-up"></i></a>
                        <div class="dropdown rh-menu">
                            <button class="rh-kebab" data-bs-toggle="dropdown" aria-expanded="false" title="More">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('routines.stats', $routine->id) }}"><i class="bi bi-graph-up me-2"></i>Stats</a></li>
                                <li><a class="dropdown-item" href="{{ route('routines.edit', $routine->id) }}"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item text-danger" onclick="archiveRoutine{{ md5($routine->id) }}()">
                                        <i class="bi bi-archive me-2"></i>Archive
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <form action="{{ route('routines.destroy', $routine->id) }}" method="POST" id="archiveForm{{ md5($routine->id) }}" style="display:none;">
                    @csrf @method('DELETE')
                </form>
            </div>
        @empty
            <div class="rh-empty">
                <i class="bi bi-arrow-repeat"></i>
                <h5>No routines yet</h5>
                <p>Build your first daily or weekly routine to get started.</p>
                <a href="{{ route('routines.create') }}" class="rh-btn primary" style="display:inline-flex;"><i class="bi bi-plus-lg"></i>Create Routine</a>
            </div>
        @endforelse
    </div>

</div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    /* Frequency filter chips */
    const chips = document.querySelectorAll('#rhFilters .rh-chip');
    const items = document.querySelectorAll('.rh-item');
    chips.forEach(chip => {
        chip.addEventListener('click', () => {
            chips.forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            const f = chip.dataset.filter;
            items.forEach(item => {
                const show = f === 'all' || item.dataset.frequency === f;
                item.style.display = show ? '' : 'none';
            });
        });
    });

    /* Archive (soft delete) — keeps completion history */
    window.archiveRoutine = function(hmac) { };
    @forelse($routines as $routine)
    window['archiveRoutine{{ md5($routine->id) }}'] = function() {
        if (confirm('Archive "{{ $routine->title }}"? Its history stays saved but it disappears from your lists.')) {
            document.getElementById('archiveForm{{ md5($routine->id) }}').submit();
        }
    };
    @empty
    @endforelse
});
</script>
@endpush
