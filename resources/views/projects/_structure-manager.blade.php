{{-- Structure Manager: chapters/lessons tree with inline create, move (drag + menu + bulk), quick tasks.
     Vars: $project (root, with childrenRecursive + tasks loaded) --}}
@php
    $direct = $project->relationLoaded('childrenRecursive') ? $project->childrenRecursive : $project->children;
    // Flatten tree for destination dropdowns: [id, name, depth, path]
    $destOptions = [];
    $flat = function ($nodes, $depth = 0, $path = []) use (&$flat, &$destOptions) {
        foreach ($nodes as $n) {
            $destOptions[] = ['id' => (int) $n->id, 'name' => (string) $n->name, 'depth' => $depth,
                'type' => (string) ($n->type ?? 'project'),
                'path' => implode(' / ', array_merge($path, [$n->name]))];
            $ck = $n->relationLoaded('childrenRecursive') ? $n->childrenRecursive : $n->children;
            if ($ck->count()) $flat($ck, $depth + 1, array_merge($path, [$n->name]));
        }
    };
    $flat($direct);
    $containers = $direct->filter(fn ($c) => $c->childrenRecursive->count() > 0 || in_array($c->type ?? '', ['chapter','section','project','subproject'], true))->values();
    $ungrouped = $direct->filter(fn ($c) => ! ($c->childrenRecursive->count() > 0 || in_array($c->type ?? '', ['chapter','section','project','subproject'], true)))->values();
@endphp
@push('styles')
<style>
    .st-wrap { border:1px solid #e5e7eb; border-radius:10px; background:white; overflow:hidden; }
    .st-toolbar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:10px 12px; background:#fafbfc; border-bottom:1px solid #eef0f2; }
    .st-toolbar .st-title { font-size:12px; font-weight:700; color:#1f2328; text-transform:uppercase; letter-spacing:.5px; }
    .st-toolbar .st-sub { font-size:11px; color:#8b8d98; }
    .st-toolbar .spacer { flex:1; }
    .st-btn { display:inline-flex; align-items:center; gap:5px; border:1px solid #e5e7eb; background:white; border-radius:6px; padding:5px 11px; font-size:12px; font-weight:600; color:#3d4149; cursor:pointer; }
    .st-btn:hover { border-color:#7c3aed; color:#7c3aed; }
    .st-btn.primary { background:#7c3aed; border-color:#7c3aed; color:white; }
    .st-btn.primary:hover { background:#6d28d9; color:white; }
    .st-bulkbar { display:none; align-items:center; gap:8px; padding:8px 12px; background:#f5f3ff; border-bottom:1px solid #ddd6fe; font-size:12px; }
    .st-bulkbar.on { display:flex; }
    .st-bulkbar select { border:1px solid #c4b5fd; border-radius:6px; padding:4px 8px; font-size:12px; max-width:260px; }
    .st-zone-lbl { font-size:11px; font-weight:700; color:#8b8d98; text-transform:uppercase; letter-spacing:.5px; padding:10px 12px 2px; display:flex; align-items:center; gap:6px; }
    .st-zone-warn { margin:8px 12px; border:1px dashed #f59e0b; background:#fffbeb; border-radius:8px; padding:8px 10px; font-size:12px; color:#92400e; }
    .st-empty { padding:22px; text-align:center; color:#8b8d98; font-size:13px; }
    .st-node { padding:2px 8px 2px 0; }
    .st-row { display:flex; align-items:center; gap:7px; padding:7px 10px; border-radius:8px; border:1px solid transparent; }
    .st-row:hover { background:#f7f8fa; border-color:#eef0f2; }
    .st-node[data-node-type="chapter"] > .st-row { background:#fffdf5; }
    .st-drag { cursor:grab; color:#c3c6cb; font-size:13px; padding:2px; border-radius:4px; }
    .st-drag:hover { color:#7c3aed; background:#f5f3ff; }
    .st-check { accent-color:#7c3aed; width:14px; height:14px; }
    .st-chev { border:none; background:none; color:#8b8d98; cursor:pointer; padding:2px 4px; font-size:11px; }
    .st-node.closed > .st-row .st-chev i { transform:rotate(-90deg); display:inline-block; }
    [dir="rtl"] .st-node.closed > .st-row .st-chev i { transform:rotate(90deg); }
    .st-node.closed > .st-kids, .st-node.closed > .st-tasks { display:none; }
    .st-chev-sp { width:22px; }
    .st-type { font-size:10px; font-weight:700; border:1px solid; border-radius:12px; padding:1px 8px; white-space:nowrap; }
    .st-name { font-size:13px; font-weight:600; color:#1f2328; text-decoration:none; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; min-width:60px; }
    .st-name:hover { color:#7c3aed; }
    .st-counts { font-size:11px; color:#8b8d98; white-space:nowrap; }
    .st-pb { width:70px; height:5px; background:#eef0f2; border-radius:4px; overflow:hidden; flex-shrink:0; }
    .st-pb-fill { display:block; height:100%; border-radius:4px; }
    .st-pct { font-size:11px; font-weight:700; color:#1f2328; min-width:34px; text-align:end; }
    .st-actions { display:flex; align-items:center; gap:2px; margin-inline-start:auto; }
    .st-icon-btn { border:1px solid transparent; background:none; color:#9aa0a8; cursor:pointer; border-radius:6px; padding:3px 6px; font-size:13px; }
    .st-icon-btn:hover { color:#7c3aed; background:#f5f3ff; border-color:#e9e4fb; }
    .st-menu-wrap { position:relative; }
    .st-menu { position:absolute; inset-inline-end:0; top:calc(100% + 4px); z-index:60; background:white; border:1px solid #e5e7eb; border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.12); min-width:220px; max-height:320px; overflow:auto; padding:6px; display:flex; flex-direction:column; gap:1px; }
    .st-menu button, .st-menu a { text-align:start; border:none; background:none; font-size:12px; color:#3d4149; padding:6px 8px; border-radius:6px; cursor:pointer; text-decoration:none; display:block; width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .st-menu button:hover, .st-menu a:hover { background:#f5f3ff; color:#7c3aed; }
    .st-menu-lbl { font-size:10px; font-weight:700; color:#aeb2ba; text-transform:uppercase; letter-spacing:.4px; padding:4px 8px 2px; }
    .st-menu-sep { border-top:1px solid #eef0f2; margin:4px 0; }
    .st-menu-row { display:flex; gap:4px; padding:0 8px 4px; }
    .st-menu-row button { font-size:16px; padding:4px 8px; border:1px solid #eef0f2; }
    .st-drop { margin:2px 10px 2px 34px; border:1px dashed #7c3aed; background:#f5f3ff; color:#7c3aed; font-size:12px; border-radius:8px; padding:7px 10px; }
    .st-node.dragover > .st-row { outline:2px dashed #7c3aed; outline-offset:2px; background:#f5f3ff; }
    .st-kids { margin-inline-start:26px; border-inline-start:2px solid #f0f1f3; padding-inline-start:6px; }
    .st-tasks { display:flex; flex-wrap:wrap; gap:4px 10px; padding:0 10px 4px 62px; }
    .st-task { display:inline-flex; align-items:center; gap:5px; font-size:12px; color:#4b5563; background:#f7f8fa; border:1px solid #eef0f2; border-radius:14px; padding:2px 9px 2px 7px; cursor:pointer; max-width:100%; }
    .st-task.done .st-task-title { text-decoration:line-through; color:#9aa0a8; }
    .st-task input { accent-color:#30a46c; }
    .st-task-title { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:220px; }
    .st-more { font-size:11px; color:#7c3aed; text-decoration:none; align-self:center; }
    .st-quick input { border:1px solid #c4b5fd; border-radius:14px; padding:3px 10px; font-size:12px; outline:none; width:220px; }
    @media(max-width:640px) {
        .st-drag { display:none; }
        .st-pb { display:none; }
        .st-counts { display:none; }
        .st-kids { margin-inline-start:12px; }
        .st-tasks { padding-inline-start:34px; }
    }
</style>
@endpush

<div class="st-wrap" id="st-manager"
     data-root-id="{{ $project->id }}"
     data-store-url="{{ route('projects.nodes.store', $project) }}"
     data-move-url="{{ route('projects.nodes.move', $project) }}"
     data-structure-url="{{ route('projects.structure', $project) }}">
    <div class="st-toolbar">
        <span class="st-title">📂 {{ __('Structure') }}</span>
        <span class="st-sub">{{ __('chapters → lessons · drag, move, create') }}</span>
        <span class="spacer"></span>
        <button type="button" class="st-btn primary" data-st-add="chapter" data-st-parent="{{ $project->id }}"><i class="bi bi-folder-plus"></i> {{ __('New chapter') }}</button>
        <button type="button" class="st-btn" data-st-add="lesson" data-st-parent="{{ $project->id }}"><i class="bi bi-file-earmark-plus"></i> {{ __('New lesson') }}</button>
    </div>
    <div class="st-bulkbar" id="st-bulkbar">
        <strong id="st-bulkcount">0</strong> {{ __('selected') }}
        <select id="st-bulkdest">
            <option value="">{{ __('Move to: Ungrouped (root)') }}</option>
            @foreach($destOptions as $opt)
                @if((int) $opt['id'] !== (int) $project->id)
                    <option value="{{ $opt['id'] }}">{{ str_repeat('— ', min(4, $opt['depth'])) }}{{ $opt['name'] }} ({{ $opt['type'] }})</option>
                @endif
            @endforeach
        </select>
        <button type="button" class="st-btn primary" id="st-bulkmove"><i class="bi bi-box-arrow-in-down-left"></i> {{ __('Move') }}</button>
        <button type="button" class="st-btn" id="st-bulkclear">{{ __('Clear') }}</button>
    </div>

    @if($direct->isEmpty())
        <div class="st-empty">
            {{ __('No chapters or lessons yet. Start with a chapter, then add lessons into it.') }}
        </div>
    @else
        @if($containers->count() > 0)
            <div class="st-zone-lbl">📁 {{ __('Chapters & groups') }} · {{ $containers->count() }}</div>
            @foreach($containers as $node)
                @include('projects._structure-node', ['node' => $node, 'depth' => 0, 'rootId' => (int) $project->id, 'destOptions' => $destOptions])
            @endforeach
        @endif
        @if($ungrouped->count() > 0)
            <div class="st-zone-lbl">📄 {{ __('Ungrouped lessons') }} · {{ $ungrouped->count() }}</div>
            <div class="st-zone-warn">⚠️ {{ __('These lessons sit directly under the book. Move them into a chapter with drag & drop or the ⋯ menu.') }}</div>
            @foreach($ungrouped as $node)
                @include('projects._structure-node', ['node' => $node, 'depth' => 0, 'rootId' => (int) $project->id, 'destOptions' => $destOptions])
            @endforeach
        @endif
    @endif
</div>

{{-- Create modal (Bootstrap) --}}
<div class="modal fade pj-modal" id="stModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><span class="modal-title" id="stModalTitle">{{ __('New chapter') }}</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body">
                <label class="form-label" style="font-size:11px;font-weight:700;color:#8b8d98;">{{ __('Name') }}</label>
                <input type="text" id="stModalName" class="form-control" maxlength="255" placeholder="{{ __('e.g. Chapter 4: Attention') }}">
                <input type="hidden" id="stModalType" value="chapter">
                <input type="hidden" id="stModalParent" value="{{ $project->id }}">
                <div class="btn-group w-100 mt-2" role="group" id="stModalTypes">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-t="chapter">📁 {{ __('Chapter') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-t="lesson">📄 {{ __('Lesson') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-t="section">📑 {{ __('Section') }}</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-sm btn-primary" id="stModalSave">{{ __('Create') }}</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const mgr = document.getElementById('st-manager');
    if (!mgr) return;
    const rootId = mgr.dataset.rootId;
    const storeUrl = mgr.dataset.storeUrl;
    const moveUrl = mgr.dataset.moveUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const headers = { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json' };
    const $ = (s, r) => (r || document).querySelector(s);
    const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));

    const toast = (msg, icon) => {
        if (window.alertSwal) alertSwal(msg, null, icon || 'success');
        else alert(msg);
    };

    /* ── collapse (persisted) ── */
    const ck = 'st-collapsed-' + rootId;
    const collapsed = new Set(JSON.parse(localStorage.getItem(ck) || '[]'));
    const applyCollapsed = () => $$('.st-node[data-node-id]', mgr).forEach(n => {
        const id = n.dataset.nodeId;
        const open = !collapsed.has(String(id));
        n.classList.toggle('closed', !open);
        const b = $('[data-st-toggle="' + id + '"]', n);
        if (b) b.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    applyCollapsed();
    mgr.addEventListener('click', (e) => {
        const t = e.target.closest('[data-st-toggle]');
        if (!t) return;
        const id = String(t.dataset.stToggle);
        collapsed.has(id) ? collapsed.delete(id) : collapsed.add(id);
        localStorage.setItem(ck, JSON.stringify([...collapsed]));
        applyCollapsed();
    });

    /* ── menus ── */
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-st-menu]');
        $$('[data-st-menu-body]', mgr).forEach(m => { if (!btn || m.dataset.stMenuBody !== btn.dataset.stMenu) m.hidden = true; });
        if (btn) {
            const body = $('[data-st-menu-body="' + btn.dataset.stMenu + '"]');
            if (body) body.hidden = !body.hidden;
            e.stopPropagation();
        }
    });

    /* ── create modal ── */
    let modal = null;
    const openModal = (type, parent) => {
        $('#stModalType').value = type || 'chapter';
        $('#stModalParent').value = parent || rootId;
        $('#stModalName').value = '';
        $('#stModalTitle').textContent = (type === 'lesson') ? @json(__('New lesson')) : (type === 'section' ? @json(__('New section')) : @json(__('New chapter')));
        $$('#stModalTypes [data-t]').forEach(b => b.classList.toggle('active', b.dataset.t === ($('#stModalType').value)));
        modal = modal || new bootstrap.Modal($('#stModal'));
        modal.show();
        setTimeout(() => $('#stModalName').focus(), 300);
    };
    mgr.addEventListener('click', (e) => {
        const b = e.target.closest('[data-st-add]');
        if (b) openModal(b.dataset.stAdd, b.dataset.stParent);
    });
    document.addEventListener('click', (e) => {
        const b = e.target.closest('#stModalTypes [data-t]');
        if (b) {
            $('#stModalType').value = b.dataset.t;
            $$('#stModalTypes [data-t]').forEach(x => x.classList.toggle('active', x === b));
        }
    });
    const doCreate = async () => {
        const name = $('#stModalName').value.trim();
        if (!name) { toast(@json(__('Please enter a name.')), 'warning'); return; }
        const btn = $('#stModalSave');
        btn.disabled = true;
        try {
            const res = await fetch(storeUrl, { method: 'POST', headers, body: JSON.stringify({
                name, type: $('#stModalType').value,
                parent_node_id: $('#stModalParent').value == rootId ? null : $('#stModalParent').value }) });
            const j = await res.json();
            if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
            modal.hide();
            location.reload();
        } catch (err) { toast(err.message, 'error'); btn.disabled = false; }
    };
    $('#stModalSave')?.addEventListener('click', doCreate);
    $('#stModalName')?.addEventListener('keydown', (e) => { if (e.key === 'Enter') doCreate(); });

    /* ── move helper ── */
    const doMove = async (ids, dest) => {
        const res = await fetch(moveUrl, { method: 'PATCH', headers,
            body: JSON.stringify({ ids, destination_id: dest || null }) });
        const j = await res.json().catch(() => ({}));
        if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
        location.reload();
    };
    mgr.addEventListener('click', async (e) => {
        const b = e.target.closest('[data-st-move]');
        if (!b) return;
        b.disabled = true;
        try { await doMove([parseInt(b.dataset.stMove)], b.dataset.stDest || null); }
        catch (err) { toast(err.message, 'error'); b.disabled = false; }
    });

    /* ── convert ── */
    mgr.addEventListener('click', async (e) => {
        const b = e.target.closest('[data-st-convert]');
        if (!b) return;
        b.disabled = true;
        try {
            const res = await fetch('{{ url('projects') }}/' + b.dataset.stConvert + '/convert',
                { method: 'PATCH', headers, body: JSON.stringify({ type: b.dataset.stType }) });
            const j = await res.json().catch(() => ({}));
            if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
            location.reload();
        } catch (err) { toast(err.message, 'error'); b.disabled = false; }
    });

    /* ── bulk select ── */
    const bulkbar = $('#st-bulkbar'), bulkcount = $('#st-bulkcount');
    const selected = () => $$('.st-check:checked', mgr).map(c => parseInt(c.dataset.checkId));
    const refreshBulk = () => {
        const n = selected().length;
        bulkbar.classList.toggle('on', n > 0);
        bulkcount.textContent = n;
    };
    mgr.addEventListener('change', (e) => { if (e.target.matches('.st-check')) refreshBulk(); });
    $('#st-bulkclear')?.addEventListener('click', () => { $$('.st-check:checked', mgr).forEach(c => c.checked = false); refreshBulk(); });
    $('#st-bulkmove')?.addEventListener('click', async (e) => {
        const ids = selected();
        if (!ids.length) return;
        e.target.disabled = true;
        try { await doMove(ids, $('#st-bulkdest').value || null); }
        catch (err) { toast(err.message, 'error'); e.target.disabled = false; }
    });

    /* ── quick task: toggle + add ── */
    mgr.addEventListener('click', (e) => {
        const b = e.target.closest('[data-st-task]');
        if (!b) return;
        const wrap = $('[data-quick-wrap="' + b.dataset.stTask + '"]');
        if (wrap) { wrap.hidden = !wrap.hidden; const i = $('input', wrap); if (i && !wrap.hidden) i.focus(); }
    });
    mgr.addEventListener('keydown', async (e) => {
        const inp = e.target.closest('[data-quick-input]');
        if (!inp || e.key !== 'Enter') return;
        const title = inp.value.trim();
        if (!title) return;
        inp.disabled = true;
        try {
            const res = await fetch(@json(route('tasks.quick-store')), { method: 'POST', headers,
                body: JSON.stringify({ title, project_id: parseInt(inp.dataset.quickInput) }) });
            const j = await res.json().catch(() => ({}));
            if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
            location.reload();
        } catch (err) { toast(err.message, 'error'); inp.disabled = false; }
    });
    mgr.addEventListener('change', async (e) => {
        const c = e.target.closest('[data-task-toggle]');
        if (!c) return;
        const id = c.dataset.taskToggle;
        const next = c.checked ? 'completed' : 'to_do';
        try {
            const res = await fetch('{{ url('tasks') }}/' + id + '/update-status',
                { method: 'POST', headers, body: JSON.stringify({ status: next }) });
            const j = await res.json().catch(() => ({}));
            if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
            c.closest('.st-task')?.classList.toggle('done', c.checked);
        } catch (err) { toast(err.message, 'error'); c.checked = !c.checked; }
    });

    /* ── drag & drop (desktop) ── */
    let dragId = null;
    mgr.addEventListener('dragstart', (e) => {
        const h = e.target.closest('[data-drag-id]');
        if (!h) return;
        dragId = h.dataset.dragId;
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', dragId); } catch (_) {}
    });
    mgr.addEventListener('dragover', (e) => {
        const row = e.target.closest('.st-node[data-node-id]');
        if (!row || !dragId || row.dataset.nodeId === String(dragId)) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        row.classList.add('dragover');
        const dz = $('[data-drop-id="' + row.dataset.nodeId + '"]', row);
        if (dz) dz.hidden = false;
    });
    mgr.addEventListener('dragleave', (e) => {
        const row = e.target.closest('.st-node[data-node-id]');
        if (row && !row.contains(e.relatedTarget)) {
            row.classList.remove('dragover');
            const dz = $('[data-drop-id="' + row.dataset.nodeId + '"]', row);
            if (dz) dz.hidden = true;
        }
    });
    mgr.addEventListener('drop', async (e) => {
        const row = e.target.closest('.st-node[data-node-id]');
        if (!row || !dragId) return;
        e.preventDefault();
        const dest = row.dataset.nodeId;
        row.classList.remove('dragover');
        const dz = $('[data-drop-id="' + dest + '"]', row);
        if (dz) dz.hidden = true;
        if (String(dest) === String(dragId)) { dragId = null; return; }
        // Dropping onto itself-group = move INTO it; dropping on root handled by bulk/menu.
        try { await doMove([parseInt(dragId)], parseInt(dest)); }
        catch (err) { toast(err.message, 'error'); }
        dragId = null;
    });
    mgr.addEventListener('dragend', () => {
        dragId = null;
        $$('.st-node.dragover', mgr).forEach(n => n.classList.remove('dragover'));
        $$('.st-drop', mgr).forEach(d => d.hidden = true);
    });
})();
</script>
@endpush
