{{-- Project's own tasks (root tasks + nested subtasks), with quick-add + status toggle + filters.
     Vars: $project (tasks.childrenRecursive, tasks.checklistItems, tasks.timeEntries loaded) --}}
@php
    $roots = $project->tasks->filter(fn ($t) => $t->parent_id === null)->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
    $totalDirect = $project->tasks->count();
    $doneDirect = $project->tasks->where('status', 'completed')->count();
    $openDirect = $totalDirect - $doneDirect;
@endphp
@push('styles')
<style>
    .pt-wrap { border:1px solid #e5e7eb; border-radius:10px; background:white; overflow:hidden; }
    .pt-toolbar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:10px 12px; background:#fafbfc; border-bottom:1px solid #eef0f2; }
    .pt-filter { display:flex; gap:4px; background:#eef0f2; border-radius:8px; padding:2px; }
    .pt-filter button { border:none; background:none; font-size:11.5px; font-weight:700; color:#6b6f78; border-radius:6px; padding:4px 10px; cursor:pointer; }
    .pt-filter button.active { background:white; color:#1f2328; box-shadow:0 1px 2px rgba(0,0,0,.08); }
    .pt-quick { display:flex; gap:6px; padding:10px 12px; border-bottom:1px solid #eef0f2; }
    .pt-quick input { flex:1; border:1px solid #e5e7eb; border-radius:8px; padding:7px 10px; font-size:13px; outline:none; min-width:0; }
    .pt-quick input:focus { border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.08); }
    .pt-quick button { border:none; background:#7c3aed; color:white; border-radius:8px; padding:7px 14px; font-size:12.5px; font-weight:700; cursor:pointer; white-space:nowrap; }
    .pt-quick button:hover { background:#6d28d9; }
    .pt-list { padding:6px; }
    .pt-item { border:1px solid #eef0f2; border-radius:8px; margin-bottom:6px; background:white; overflow:hidden; }
    .pt-item:last-child { margin-bottom:0; }
    .pt-item.is-done { background:#fafbfa; }
    .pt-row { display:flex; align-items:center; gap:9px; padding:9px 10px; }
    .pt-check { appearance:none; -webkit-appearance:none; width:19px; height:19px; border-radius:50%; border:2px solid #c3c6cb; cursor:pointer; flex-shrink:0; position:relative; transition:all .12s; margin:0; }
    .pt-check:hover { border-color:#30a46c; }
    .pt-check:checked { background:#30a46c; border-color:#30a46c; }
    .pt-check:checked::after { content:'✓'; position:absolute; inset:0; color:white; font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; }
    .pt-main { flex:1; min-width:0; }
    .pt-title { font-size:13.5px; font-weight:600; color:#1f2328; text-decoration:none; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .pt-title:hover { color:#7c3aed; }
    .pt-item.is-done .pt-title { text-decoration:line-through; color:#9aa0a8; }
    .pt-meta { display:flex; align-items:center; gap:5px; flex-wrap:wrap; margin-top:3px; }
    .pt-chip { display:inline-flex; align-items:center; gap:4px; font-size:10.5px; font-weight:600; border-radius:10px; padding:1px 8px; border:1px solid #eef0f2; background:#f7f8fa; color:#6b6f78; }
    .pt-chip.pri-high { background:#fdebec; border-color:#f8c9cc; color:#c4303f; }
    .pt-chip.pri-medium { background:#fdf4de; border-color:#f5dfa8; color:#ad6800; }
    .pt-chip.pri-low { background:#eef0f2; color:#6b6f78; }
    .pt-chip.overdue { background:#fdebec; border-color:#f8c9cc; color:#c4303f; }
    .pt-chip.failed { background:#fdebec; border-color:#e5484d; color:#c4303f; }
    .pt-subbar { display:flex; align-items:center; gap:6px; margin-top:4px; }
    .pt-subbar .bar { flex:1; max-width:140px; height:4px; background:#eef0f2; border-radius:4px; overflow:hidden; }
    .pt-subbar .bar > span { display:block; height:100%; background:#30a46c; border-radius:4px; }
    .pt-subbar .lbl { font-size:10.5px; color:#8b8d98; font-weight:600; }
    .pt-toggle-kids { border:none; background:none; color:#8b8d98; cursor:pointer; font-size:11px; padding:2px 5px; border-radius:5px; flex-shrink:0; }
    .pt-toggle-kids:hover { color:#7c3aed; background:#f5f3ff; }
    .pt-actions { display:flex; gap:2px; flex-shrink:0; }
    .pt-actions a { color:#b6bac2; font-size:13px; padding:3px 6px; border-radius:6px; text-decoration:none; }
    .pt-actions a:hover { color:#7c3aed; background:#f5f3ff; }
    .pt-kids { border-top:1px dashed #eef0f2; padding:4px 6px 6px 30px; display:flex; flex-direction:column; gap:2px; }
    [dir="rtl"] .pt-kids { padding:4px 30px 6px 6px; }
    .pt-kid { display:flex; align-items:center; gap:8px; padding:5px 6px; border-radius:6px; font-size:12.5px; }
    .pt-kid:hover { background:#f7f8fa; }
    .pt-kid .pt-check { width:16px; height:16px; }
    .pt-kid a { color:#3d4149; text-decoration:none; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; min-width:0; }
    .pt-kid a:hover { color:#7c3aed; }
    .pt-kid.is-done a { text-decoration:line-through; color:#9aa0a8; }
    .pt-kid .depth-2 { margin-inline-start:18px; } .pt-kid .depth-3 { margin-inline-start:36px; }
    .pt-empty { padding:22px; text-align:center; color:#8b8d98; font-size:13px; }
    .pt-note { font-size:11px; color:#8b8d98; }
</style>
@endpush

<div class="pt-wrap" id="pt-manager" data-project-id="{{ $project->id }}">
    <div class="pt-toolbar">
        <div class="pt-filter" role="tablist">
            <button type="button" data-pt-filter="all" class="active">{{ __('All') }} · {{ $totalDirect }}</button>
            <button type="button" data-pt-filter="open">{{ __('Open') }} · {{ $openDirect }}</button>
            <button type="button" data-pt-filter="done">{{ __('Done') }} · {{ $doneDirect }}</button>
        </div>
        <span class="spacer" style="flex:1;"></span>
        <a href="{{ route('projects.tasks.index', $project) }}" class="pj-mini-btn"><i class="bi bi-kanban"></i> {{ __('Board + full add') }}</a>
    </div>
    <div class="pt-quick">
        <input type="text" id="pt-quickinput" maxlength="255" placeholder="{{ __('New task in this lesson… Enter to add') }}" aria-label="{{ __('New task') }}">
        <button type="button" id="pt-quickadd"><i class="bi bi-plus-lg"></i> {{ __('Add') }}</button>
    </div>
    <div class="pt-list" id="pt-list">
        @forelse($roots as $t)
            @php
                $isDone = $t->status === 'completed';
                $kids = $t->relationLoaded('childrenRecursive') ? $t->childrenRecursive : collect();
                $kidTotal = 0; $kidDone = 0;
                $kw = function ($nodes) use (&$kw, &$kidTotal, &$kidDone) {
                    foreach ($nodes as $k) {
                        $kidTotal++; if ($k->status === 'completed') $kidDone++;
                        $ck = $k->relationLoaded('childrenRecursive') ? $k->childrenRecursive : collect();
                        if ($ck->count()) $kw($ck);
                    }
                };
                $kw($kids);
                $overdue = $t->due_date && \Carbon\Carbon::parse($t->due_date)->startOfDay()->lt(now()->startOfDay()) && ! $isDone;
                $checkTotal = $t->relationLoaded('checklistItems') ? $t->checklistItems->count() : 0;
                $checkDone = $t->relationLoaded('checklistItems') ? $t->checklistItems->where('is_completed', true)->count() : 0;
            @endphp
            <div class="pt-item {{ $isDone ? 'is-done' : '' }}" data-pt-item="{{ $t->id }}" data-pt-status="{{ $isDone ? 'done' : 'open' }}">
                <div class="pt-row">
                    <input type="checkbox" class="pt-check" data-pt-toggle="{{ $t->id }}" {{ $isDone ? 'checked' : '' }} title="{{ $isDone ? __('Mark as To Do') : __('Mark as Completed') }}">
                    <div class="pt-main">
                        <a class="pt-title" href="{{ route('tasks.show', $t) }}">{{ $t->title }}</a>
                        <div class="pt-meta">
                            @if($t->priority !== 'medium')
                                <span class="pt-chip pri-{{ $t->priority }}">{{ __(ucfirst($t->priority)) }}</span>
                            @endif
                            @if(! is_null($t->weight))
                                <span class="pt-chip" title="{{ __('Pages') }}">📄 {{ rtrim(rtrim(number_format((float) $t->weight, 1, '.', ''), '0'), '.') }}</span>
                            @endif
                            @if($t->estimatedLabel())
                                <span class="pt-chip">⏱ {{ $t->estimatedLabel() }}</span>
                            @endif
                            @if($t->due_date)
                                <span class="pt-chip {{ $overdue ? 'overdue' : '' }}">📅 {{ $t->due_date->format('M d') }}</span>
                            @endif
                            @if($t->dueTimeLabel())
                                <span class="pt-chip">🕐 {{ $t->dueTimeLabel() }}</span>
                            @endif
                            @if($t->isFailed())
                                <span class="pt-chip failed">⚠️ {{ __('Failed') }}</span>
                            @endif
                            @if($checkTotal > 0)
                                <span class="pt-chip">☑ {{ $checkDone }}/{{ $checkTotal }}</span>
                            @endif
                        </div>
                        @if($kidTotal > 0)
                            <div class="pt-subbar">
                                <span class="bar"><span style="width:{{ round($kidDone / max(1, $kidTotal) * 100) }}%;"></span></span>
                                <span class="lbl">{{ $kidDone }}/{{ $kidTotal }} {{ __('subtasks') }}</span>
                            </div>
                        @endif
                    </div>
                    @if($kidTotal > 0)
                        <button type="button" class="pt-toggle-kids" data-pt-kids="{{ $t->id }}"><i class="bi bi-chevron-down"></i></button>
                    @endif
                    <span class="pt-actions">
                        <a href="{{ route('tasks.show', $t) }}" title="{{ __('View') }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('tasks.edit', $t) }}" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                    </span>
                </div>
                @if($kidTotal > 0)
                    <div class="pt-kids" data-pt-kids-body="{{ $t->id }}">
                        @foreach($kids as $k)
                            @include('projects._project-task-kid', ['task' => $k, 'depth' => 1])
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="pt-empty">
                {{ __('No tasks in this lesson yet. Add the first one above 👆') }}
            </div>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
(function () {
    const mgr = document.getElementById('pt-manager');
    if (!mgr || mgr.dataset.ptWired) return;
    mgr.dataset.ptWired = '1';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const headers = { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json' };
    const toast = (msg, icon) => { window.alertSwal ? alertSwal(msg, null, icon || 'success') : alert(msg); };

    /* filters */
    mgr.querySelectorAll('[data-pt-filter]').forEach(b => b.addEventListener('click', () => {
        mgr.querySelectorAll('[data-pt-filter]').forEach(x => x.classList.toggle('active', x === b));
        const f = b.dataset.ptFilter;
        mgr.querySelectorAll('[data-pt-item]').forEach(it => {
            it.style.display = (f === 'all' || it.dataset.ptStatus === (f === 'done' ? 'done' : 'open')) ? '' : 'none';
        });
    }));

    /* expand / collapse subtasks */
    mgr.addEventListener('click', (e) => {
        const b = e.target.closest('[data-pt-kids]');
        if (!b) return;
        const body = mgr.querySelector('[data-pt-kids-body="' + b.dataset.ptKids + '"]');
        if (!body) return;
        const hidden = body.style.display === 'none';
        body.style.display = hidden ? '' : 'none';
        b.innerHTML = '<i class="bi bi-chevron-' + (hidden ? 'down' : 'left') + '"></i>';
    });

    /* status toggle (root + kids) */
    mgr.addEventListener('change', async (e) => {
        const c = e.target.closest('[data-pt-toggle]');
        if (!c) return;
        const id = c.dataset.ptToggle;
        const next = c.checked ? 'completed' : 'to_do';
        try {
            const res = await fetch('{{ url('tasks') }}/' + id + '/update-status', {
                method: 'POST', headers, body: JSON.stringify({ status: next }) });
            const j = await res.json().catch(() => ({}));
            if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
            const item = c.closest('[data-pt-item]');
            if (item) {
                item.classList.toggle('is-done', c.checked);
                item.dataset.ptStatus = c.checked ? 'done' : 'open';
            } else {
                c.closest('.pt-kid')?.classList.toggle('is-done', c.checked);
            }
        } catch (err) { toast(err.message, 'error'); c.checked = !c.checked; }
    });

    /* quick add */
    const input = document.getElementById('pt-quickinput');
    const send = async () => {
        const title = (input.value || '').trim();
        if (!title) { toast(@json(__('Please enter a task title.')), 'warning'); return; }
        input.disabled = true;
        try {
            const res = await fetch(@json(route('tasks.quick-store')), { method: 'POST', headers,
                body: JSON.stringify({ title, project_id: parseInt(mgr.dataset.projectId) }) });
            const j = await res.json().catch(() => ({}));
            if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
            location.reload();
        } catch (err) { toast(err.message, 'error'); input.disabled = false; }
    };
    document.getElementById('pt-quickadd')?.addEventListener('click', send);
    input?.addEventListener('keydown', (e) => { if (e.key === 'Enter') send(); });
})();
</script>
@endpush
