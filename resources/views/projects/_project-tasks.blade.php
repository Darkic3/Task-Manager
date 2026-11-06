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
    .pt-item { border:1px solid #eef0f2; border-radius:8px; margin-bottom:6px; background:white; overflow:hidden;
        transition:box-shadow .16s, border-color .16s, opacity .2s, transform .18s, background .16s; }
    .pt-item:last-child { margin-bottom:0; }
    .pt-item.is-done { background:#fafbfa; }
    .pt-item.removing { opacity:0; transform:scale(.97); }
    .pt-item.drop-before { box-shadow:0 -3px 0 0 #7c3aed, 0 0 0 4px rgba(124,58,237,.12); border-color:#c4b5fd; }
    .pt-item.drop-after { box-shadow:0 3px 0 0 #7c3aed, 0 0 0 4px rgba(124,58,237,.12); border-color:#c4b5fd; }
    .pt-item.drag-src { opacity:.4; transform:scale(.985); }
    .pt-item.dropped { border-color:#30a46c !important; background:#f0fdf6 !important;
        box-shadow:0 0 0 4px rgba(48,164,108,.18) !important; }
    .pt-row { display:flex; align-items:center; gap:9px; padding:9px 10px; }
    .pt-drag { cursor:grab; color:#c9ccd4; font-size:13px; padding:4px 1px; border-radius:6px; flex-shrink:0; }
    .pt-drag:hover { color:#7c3aed; background:#ede9fe; }
    .pt-drag:active { cursor:grabbing; }
    .pt-actions button { border:none; background:none; color:#b6bac2; font-size:13px; padding:3px 6px; border-radius:6px; cursor:pointer; }
    .pt-actions button:hover { color:#7c3aed; background:#f5f3ff; }
    .pt-actions button.pt-del:hover { color:#c4303f; background:#fdebec; }
    .pt-kact { display:none; align-items:center; gap:0; }
    .pt-kid:hover .pt-kact { display:inline-flex; }
    .pt-kact button { border:none; background:none; color:#b6bac2; font-size:11px; padding:2px 4px; border-radius:5px; cursor:pointer; }
    .pt-kact button:hover { color:#7c3aed; background:#ede9fe; }
    .pt-kact button[data-kdel]:hover { color:#c4303f; background:#fdebec; }
    .inline-rename-wrap { display:inline-flex; align-items:center; gap:7px; background:white;
        border:2px solid #7c3aed; border-radius:12px; padding:5px 8px 5px 11px;
        box-shadow:0 0 0 4px rgba(124,58,237,.15), 0 6px 18px -4px rgba(124,58,237,.3); max-width:100%; }
    [dir="rtl"] .inline-rename-wrap { padding:5px 11px 5px 8px; }
    .pt-rename-input { border:none; outline:none; background:transparent;
        font-size:13.5px; font-weight:600; color:#1f2328; width:min(300px, 52vw); padding:1px 2px; }
    .rename-hint { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:700;
        color:#6d28d9; background:#f5f3ff; border-radius:7px; padding:3px 8px; white-space:nowrap; }
    .rename-hint kbd { font-family:inherit; background:white; border:1px solid #ddd0fa;
        border-bottom-width:2px; border-radius:5px; padding:0 6px; font-size:10px; color:#6d28d9; }
    @media (hover:none) {
        .pt-kact { display:inline-flex; }
    }
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
            <button type="button" data-pt-filter="all" class="active">{{ __('All') }} · {{ app_num($totalDirect) }}</button>
            <button type="button" data-pt-filter="open">{{ __('Open') }} · {{ app_num($openDirect) }}</button>
            <button type="button" data-pt-filter="done">{{ __('Done') }} · {{ app_num($doneDirect) }}</button>
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
            <div class="pt-item {{ $isDone ? 'is-done' : '' }}" data-pt-item="{{ $t->id }}" data-pt-status="{{ $isDone ? 'done' : 'open' }}"
                 data-task-status="{{ $t->status }}" data-task-priority="{{ $t->priority }}" data-task-url="{{ route('tasks.show', $t) }}">
                <div class="pt-row">
                    <span class="pt-drag" title="{{ __('Drag to reorder') }}" draggable="true" data-ptdrag="{{ $t->id }}"><i class="bi bi-grip-vertical"></i></span>
                    <input type="checkbox" class="pt-check" data-pt-toggle="{{ $t->id }}" {{ $isDone ? 'checked' : '' }} title="{{ $isDone ? __('Mark as To Do') : __('Mark as Completed') }}">
                    <div class="pt-main">
                        <a class="pt-title" href="{{ route('tasks.show', $t) }}">{{ $t->title }}</a>
                        <div class="pt-meta">
                            @if($t->priority !== 'medium')
                                <span class="pt-chip pri-{{ $t->priority }}">{{ __(ucfirst($t->priority)) }}</span>
                            @endif
                            @if(! is_null($t->weight))
                                <span class="pt-chip" title="{{ __('Pages') }}">📄 {{ app_num(rtrim(rtrim(number_format((float) $t->weight, 1, '.', ''), '0'), '.')) }}</span>
                            @endif
                            @if($t->estimatedLabel())
                                <span class="pt-chip">⏱ {{ $t->estimatedLabel() }}</span>
                            @endif
                            @if($t->due_date)
                                <span class="pt-chip {{ $overdue ? 'overdue' : '' }}">📅 {{ app()->getLocale() === 'fa' ? app_date($t->due_date, 'd M') : $t->due_date->format('M d') }}</span>
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
                                <span class="lbl">{{ app_num($kidDone) }}/{{ app_num($kidTotal) }} {{ __('subtasks') }}</span>
                            </div>
                        @endif
                    </div>
                    @if($kidTotal > 0)
                        <button type="button" class="pt-toggle-kids" data-pt-kids="{{ $t->id }}"><i class="bi bi-chevron-down"></i></button>
                    @endif
                    <span class="pt-actions">
                        <button type="button" data-ptrename="{{ $t->id }}" title="{{ __('Rename') }}"><i class="bi bi-input-cursor-text"></i></button>
                        <button type="button" class="pt-del" data-ptdel="{{ $t->id }}" title="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                        <a href="{{ route('tasks.show', $t) }}" title="{{ __('View') }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('tasks.edit', $t) }}" title="{{ __('Full edit') }}"><i class="bi bi-pencil"></i></a>
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

    /* inline rename (roots + subtasks) */
    const ptRename = (id, anchor) => {
        if (!anchor || anchor.querySelector('input')) return;
        const item = anchor.closest('[data-pt-item],[data-kid]');
        const old = anchor.textContent;
        anchor.innerHTML = '';
        const wrap = document.createElement('span');
        wrap.className = 'inline-rename-wrap';
        const inp = document.createElement('input');
        inp.className = 'pt-rename-input'; inp.value = old; inp.maxLength = 255;
        inp.title = @json(__('Quick rename — Enter to save, Esc to cancel'));
        inp.setAttribute('aria-label', @json(__('Rename')));
        const hint = document.createElement('span');
        hint.className = 'rename-hint';
        const k1 = document.createElement('kbd'); k1.textContent = 'Enter';
        const k2 = document.createElement('kbd'); k2.textContent = 'Esc';
        hint.appendChild(k1); hint.appendChild(k2);
        wrap.appendChild(inp); wrap.appendChild(hint);
        anchor.appendChild(wrap); inp.focus(); inp.select();
        let done = false;
        const commit = async (save) => {
            if (done) return; done = true;
            const v = inp.value.trim();
            if (!save || !v || v === old) { anchor.textContent = old; return; }
            anchor.textContent = v;
            try {
                const res = await fetch('{{ url('tasks') }}/' + id, { method: 'PUT', headers,
                    body: JSON.stringify({ title: v,
                        priority: (item && item.dataset.taskPriority) || 'medium',
                        status: (item && item.dataset.taskStatus) || 'to_do' }) });
                const j = await res.json().catch(() => ({}));
                if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
            } catch (err) { anchor.textContent = old; toast(err.message, 'error'); }
        };
        inp.addEventListener('keydown', (ev) => {
            ev.stopPropagation();
            if (ev.key === 'Enter') commit(true);
            else if (ev.key === 'Escape') commit(false);
        });
        inp.addEventListener('blur', () => commit(true));
        inp.addEventListener('click', (ev) => ev.stopPropagation());
        inp.addEventListener('dragstart', (ev) => ev.preventDefault());
    };
    const ptDelete = async (id, rootEl) => {
        const ok = await confirmSwal(@json(__('Delete this task?')), { isDelete: true });
        if (!ok) return;
        try {
            const res = await fetch('{{ url('tasks') }}/' + id, { method: 'DELETE', headers });
            const j = await res.json().catch(() => ({}));
            if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
            if (rootEl) {
                rootEl.style.transition = 'all .25s'; rootEl.style.opacity = '0';
                setTimeout(() => location.reload(), 240);
            } else location.reload();
        } catch (err) { toast(err.message, 'error'); }
    };
    mgr.addEventListener('click', (e) => {
        if (e.target.closest('input')) return;
        const rn = e.target.closest('[data-ptrename]');
        if (rn) {
            const item = rn.closest('[data-pt-item]');
            ptRename(rn.dataset.ptrename, item ? item.querySelector('.pt-title') : null);
            return;
        }
        const krn = e.target.closest('[data-krename]');
        if (krn) {
            const kid = krn.closest('[data-kid]');
            ptRename(krn.dataset.krename, kid ? kid.querySelector('[data-kidtitle]') : null);
            return;
        }
        const del = e.target.closest('[data-ptdel]');
        if (del) { ptDelete(del.dataset.ptdel, del.closest('[data-pt-item]')); return; }
        const kdel = e.target.closest('[data-kdel]');
        if (kdel) { ptDelete(kdel.dataset.kdel, kdel.closest('[data-kid]')); return; }
        const a = e.target.closest('.pt-title,[data-kidtitle]');
        if (a && a.querySelector('input')) e.preventDefault();
    });

    /* smooth auto-scroll while dragging (wired once per page; manager script may own it) */
    if (!window.__dndAutoScroll) {
        window.__dndAutoScroll = true;
        let ascInt = null;
        document.addEventListener('dragover', (e) => {
            const y = e.clientY || 0, h = window.innerHeight, M = 90;
            const dir = y < M ? -1 : (y > h - M ? 1 : 0);
            if (!dir) { if (ascInt) { clearInterval(ascInt); ascInt = null; } return; }
            if (!ascInt) ascInt = setInterval(() => window.scrollBy(0, dir * 22), 16);
        });
        const ascStop = () => { if (ascInt) { clearInterval(ascInt); ascInt = null; } };
        document.addEventListener('drop', ascStop);
        document.addEventListener('dragend', ascStop);
    }

    /* drag reorder (root tasks) */
    const list = document.getElementById('pt-list');
    let ptDragId = null;
    mgr.addEventListener('dragstart', (e) => {
        const h = e.target.closest('[data-ptdrag]');
        if (!h) return;
        ptDragId = h.dataset.ptdrag;
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', String(ptDragId)); } catch (_) {}
        const row = h.closest('[data-pt-item]');
        if (row) setTimeout(() => row.classList.add('drag-src'), 0);
    });
    mgr.addEventListener('dragend', () => {
        ptDragId = null;
        $$('[data-pt-item]', mgr).forEach(r => r.classList.remove('drag-src', 'drop-before', 'drop-after'));
    });
    if (list) list.addEventListener('dragover', (e) => {
        if (!ptDragId) return;
        const row = e.target.closest('[data-pt-item]');
        if (!row || row.dataset.ptItem === String(ptDragId)) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const r = row.getBoundingClientRect();
        const after = (e.clientY - r.top) > r.height / 2;
        row.classList.toggle('drop-before', !after);
        row.classList.toggle('drop-after', after);
        row.dataset.ptAfter = after ? '1' : '';
    });
    if (list) list.addEventListener('dragleave', (e) => {
        const row = e.target.closest('[data-pt-item]');
        if (row && !row.contains(e.relatedTarget)) row.classList.remove('drop-before', 'drop-after');
    });
    if (list) list.addEventListener('drop', async (e) => {
        if (!ptDragId) return;
        const row = e.target.closest('[data-pt-item]');
        if (!row || row.dataset.ptItem === String(ptDragId)) return;
        e.preventDefault();
        const src = mgr.querySelector('[data-pt-item="' + ptDragId + '"]');
        if (!src) return;
        if (row.dataset.ptAfter) row.after(src); else row.before(src);
        $$('[data-pt-item]', list).forEach(r => r.classList.remove('drop-before', 'drop-after'));
        src.classList.remove('drag-src');
        src.classList.add('dropped');
        setTimeout(() => src.classList.remove('dropped'), 1200);
        const tid = ptDragId; ptDragId = null;
        try {
            const items = $$('[data-pt-item]', list).map((el, i) => ({ id: parseInt(el.dataset.ptItem), parent_id: null, sort_order: i }));
            const res = await fetch(@json(route('tasks.reorder')), { method: 'POST', headers, body: JSON.stringify({ items }) });
            const j = await res.json().catch(() => ({}));
            if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
        } catch (err) { toast(err.message, 'error'); location.reload(); }
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
