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
.sq-violated{background:#ef4444;}

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

/* Period group heads + drag reordering */
.rh-group-head {
    display:flex; align-items:center; gap:7px;
    font-size:11px; font-weight:800; letter-spacing:.05em; text-transform:uppercase;
    color:#64748b; padding:14px 4px 6px; border-bottom:1px solid #eef0f4; margin-bottom:6px;
}
.rh-group-head i { font-size:13px; }
.rh-group-head small { margin-left:auto; color:#adb0b8; font-weight:700; }
.rh-item { cursor:grab; }
.rh-item.dragging { opacity:.4; }
.rh-item.drop-before .rh-card { box-shadow:inset 0 3px 0 0 #7c3aed; }
.rh-item.drop-after .rh-card { box-shadow:inset 0 -3px 0 0 #7c3aed; }
.rh-item[data-kind="step"] .rh-card { border-left:3px solid #0ea5e9; background:#f8fdff; }
.rh-step-tag {
    display:inline-flex; align-items:center; gap:4px; padding:1px 8px; border-radius:20px;
    font-size:10.5px; font-weight:700; background:#e0f2fe; color:#0369a1;
}
.rh-step-name { font-weight:700; color:#0c4a6e; }
.rh-item[style*="display: none"] { cursor:default; }
.rh-hint { font-size:11.5px; color:#8b8d98; margin:0 0 10px 2px; }
.rh-hint i { color:#0ea5e9; }
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
    @php $counts = ['all' => $routines->count(), 'daily' => $routines->where('frequency','daily')->count(), 'weekly' => $routines->where('frequency','weekly')->count(), 'monthly' => $routines->where('frequency','monthly')->count(), 'every_n_days' => $routines->where('frequency','every_n_days')->count()]; @endphp
    <div class="rh-filters" id="rhFilters">
        @foreach(['all' => 'All', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'every_n_days' => 'Every N days'] as $key => $label)
            <button class="rh-chip {{ ($key === 'all') ? 'active' : '' }}" data-filter="{{ $key }}">
                {{ $label }} <small>{{ $counts[$key] }}</small>
            </button>
        @endforeach
    </div>

    <div class="rh-list" id="rhList">
        @php
            $items = $displayItems ?? [];
            // Group counts for heads (exploded steps count in their own slot).
            $groupCounts = [];
            foreach ($items as $it) { $groupCounts[$it['period']] = ($groupCounts[$it['period']] ?? 0) + 1; }
            $currentGroup = null;
        @endphp
        <p class="rh-hint"><i class="bi bi-info-circle"></i> روتین‌هایی که خودشان زمان ندارند ولی استپ‌هایشان زمان دارد (مثل Cobra Pose) اینجا خرد شده‌اند — هر استپ در تایم خودش قرار گرفته تا بتوانی نسبت به بقیه روتین‌ها سورت و اولویت‌بندی کنی. جابه‌جایی درون هر گروه ذخیره می‌شود؛ انداختن استپ در گروه دیگر، تایمش را عوض می‌کند.</p>
        @forelse($items as $it)
            @php
                $routine = $it['routine'];
                $step = $it['step'] ?? null;
                $isStep = ($it['kind'] ?? 'routine') === 'step';
                $pk = $it['period'];
                $pd = $pk === 'anytime'
                    ? ['label' => 'No schedule', 'icon' => 'bi-inbox', 'color' => '#64748b']
                    : (config("routines.periods.{$pk}") ?? ['label' => ucfirst($pk), 'icon' => 'bi-clock', 'color' => '#64748b']);
                $rowId = $isStep ? ('step-'.$step->id) : ('routine-'.$routine->id);
            @endphp
            @if($pk !== $currentGroup)
                @php $currentGroup = $pk; @endphp
                <div class="rh-group-head" data-group-head="{{ $pk }}" data-period="{{ $pk }}">
                    <i class="bi {{ $pd['icon'] }}" style="color:{{ $pd['color'] }};"></i>
                    <span>{{ $pd['label'] }}</span>
                    <small>{{ $groupCounts[$pk] ?? 0 }}</small>
                </div>
            @endif
            <div class="rh-item" data-kind="{{ $isStep ? 'step' : 'routine' }}" data-frequency="{{ $routine->frequency }}" data-period="{{ $pk }}" data-id="{{ $isStep ? $step->id : $routine->id }}" data-routine-id="{{ $routine->id }}" draggable="true">
                <div class="rh-card">
                    <div class="rh-body">
                        <div class="rh-row-title">
                            @if($isStep)
                                {{ $routine->title }} <span style="color:#94a3b8;">→</span> <span class="rh-step-name">{{ $step->name }}</span>
                                <span class="rh-step-tag" title="این ردیف یک استپ زمان‌دار است — قابل سورت مستقل"><i class="bi bi-list-check"></i>step</span>
                            @else
                                {{ $routine->title }}
                                @if(!empty($it['remainder']))
                                    <span class="rh-step-tag" title="{{ $it['remainder_count'] ?? 0 }} استپ بدون زمان"><i class="bi bi-inbox"></i>other steps</span>
                                @endif
                            @endif
                        </div>
                        <div class="rh-row-meta">
                            <span class="rh-pill"><i class="bi bi-arrow-repeat"></i> {{ $routine->recurrenceLabel() }}</span>
                            @if(($routine->behavior_type ?? 'build') === 'avoid')
                                <span class="rh-pill" style="background:#fee2e2;color:#b91c1c;" title="Forbidden habit — staying clean is the goal"><i class="bi bi-slash-circle"></i> ترک‌کردنی</span>
                            @endif
                            @if($isStep)
                                @php $sl = $step->scheduleLabel(); $si = $step->scheduleIcon(); $sc = $step->scheduleColor() ?? '#0369a1'; @endphp
                                @if($sl)
                                    <span class="rh-pill" style="background:{{ $sc }}1a;color:{{ $sc }};"><i class="bi {{ $si }}"></i> {{ $sl }}</span>
                                @endif
                            @elseif($routine->timeLabel())
                                <span class="rh-pill"><i class="bi bi-clock"></i> {{ $routine->timeLabel() }}</span>
                            @endif
                            @if(($routine->ringStreak ?? 0) > 0)
                                <span class="rh-flame" title="{{ ($routine->behavior_type ?? 'build') === 'avoid' ? 'Clean days in a row' : 'Current streak' }}">{{ ($routine->behavior_type ?? 'build') === 'avoid' ? '🛡️' : '🔥' }}{{ $routine->ringStreak }}</span>
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
                        <a href="{{ route('routines.edit', $routine->id) }}" class="rh-link-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                        <div class="dropdown rh-menu">
                            <button class="rh-kebab" data-bs-toggle="dropdown" aria-expanded="false" title="More">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('routines.stats', $routine->id) }}"><i class="bi bi-graph-up me-2"></i>Stats</a></li>
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
    {{-- One hidden archive form per routine (step rows share the parent form) --}}
    @foreach($routines as $ar)
        <form action="{{ route('routines.destroy', $ar->id) }}" method="POST" id="archiveForm{{ md5($ar->id) }}" style="display:none;">
            @csrf @method('DELETE')
        </form>
    @endforeach

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
            syncGroupHeads();
        });
    });

    function syncGroupHeads() {
        document.querySelectorAll('.rh-group-head').forEach(head => {
            let el = head.nextElementSibling, visible = 0;
            while (el && !el.classList.contains('rh-group-head')) {
                if (el.classList.contains('rh-item') && el.style.display !== 'none') visible++;
                el = el.nextElementSibling;
            }
            head.style.display = visible ? '' : 'none';
        });
    }
    syncGroupHeads();

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

    /* ── Drag & drop: routines + exploded timed steps ──
       - Same-group drop reorders priorities (routines → routines.sort_order,
         steps → checklist_items.sort_order, so each Cobra-style step keeps
         its own rank against the other routines in that time slot).
       - Dropping a STEP into another time group retimes it there.
       - Dropping a ROUTINE into another group moves its schedule there. */
    (function () {
        const list = document.getElementById('rhList');
        if (!list) return;
        const REORDER_URL = '{{ route('routines.reorder') }}';
        const STEPS_URL = '{{ route('routines.steps.reorder') }}';
        let dragItem = null, dropTarget = null, dropPos = null, dropGroup = null;

        const clearMarks = () => {
            list.querySelectorAll('.drop-before,.drop-after')
                .forEach(el => el.classList.remove('drop-before', 'drop-after'));
            list.querySelectorAll('.rh-group-head').forEach(h => h.style.outline = '');
            dropTarget = null;
            dropPos = null;
            dropGroup = null;
        };

        const post = async (url, items) => {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ items }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
        };

        list.addEventListener('dragstart', e => {
            const item = e.target.closest('.rh-item');
            if (!item) return;
            dragItem = item;
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', item.dataset.kind + ':' + item.dataset.id); } catch (_) {}
        });

        list.addEventListener('dragend', () => {
            dragItem?.classList.remove('dragging');
            clearMarks();
            dragItem = null;
        });

        list.addEventListener('dragover', e => {
            if (!dragItem) return;
            const head = e.target.closest('.rh-group-head');
            const item = e.target.closest('.rh-item');
            // Allow dropping on empty group heads too (cross-group move).
            if (head && !item) {
                e.preventDefault();
                clearMarks();
                dropGroup = head.dataset.period;
                head.style.outline = '2px dashed #7c3aed';
                return;
            }
            if (!item || item === dragItem) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            clearMarks();
            const r = item.getBoundingClientRect();
            dropTarget = item;
            dropGroup = item.dataset.period;
            dropPos = e.clientY < r.top + r.height / 2 ? 'before' : 'after';
            item.classList.add('drop-' + dropPos);
        });

        list.addEventListener('drop', async e => {
            if (!dragItem) return;
            e.preventDefault();
            const fromPeriod = dragItem.dataset.period;
            let toPeriod = fromPeriod;
            if (dropTarget) {
                toPeriod = dropTarget.dataset.period;
                dropPos === 'before'
                    ? dropTarget.parentNode.insertBefore(dragItem, dropTarget)
                    : dropTarget.parentNode.insertBefore(dragItem, dropTarget.nextSibling);
            } else if (dropGroup) {
                toPeriod = dropGroup;
                // Append at end of that group.
                const head = list.querySelector(`.rh-group-head[data-period="${toPeriod}"]`);
                let el = head ? head.nextElementSibling : null, last = null;
                while (el && !el.classList.contains('rh-group-head')) {
                    if (el.classList.contains('rh-item')) last = el;
                    el = el.nextElementSibling;
                }
                last ? last.parentNode.insertBefore(dragItem, last.nextSibling)
                     : head?.parentNode.insertBefore(dragItem, head.nextSibling);
            } else {
                clearMarks();
                return;
            }
            const moved = fromPeriod !== toPeriod;
            dragItem.dataset.period = toPeriod;
            clearMarks();
            syncGroupHeads();
            try {
                if (moved) {
                    // Persist the retime first, then the new order in target group.
                    if (dragItem.dataset.kind === 'step') {
                        await post(STEPS_URL, [{ id: Number(dragItem.dataset.id), sort_order: 0, time_period: toPeriod }]);
                    } else {
                        await post(REORDER_URL, [{ id: Number(dragItem.dataset.id), sort_order: 0, time_period: toPeriod }]);
                    }
                    // Reload so groups/counts/sort keys rebuild cleanly.
                    location.reload();
                    return;
                }
                // Same-group reorder: persist per-kind orders sharing one numeric space.
                const groupItems = [...list.querySelectorAll(`.rh-item[data-period="${toPeriod}"]`)];
                const routines = groupItems.filter(el => el.dataset.kind !== 'step')
                    .map((el, i) => ({ el, order: groupItems.indexOf(el) * 10 }));
                const steps = groupItems.filter(el => el.dataset.kind === 'step')
                    .map(el => ({ el, order: groupItems.indexOf(el) * 10 }));
                if (routines.length) {
                    await post(REORDER_URL, routines.map(r => ({ id: Number(r.el.dataset.id), sort_order: r.order })));
                }
                if (steps.length) {
                    await post(STEPS_URL, steps.map(s => ({ id: Number(s.el.dataset.id), sort_order: s.order })));
                }
            } catch (err) {
                console.error('[Routines] reorder failed', err);
            }
        });
    })();
});
</script>
@endpush
