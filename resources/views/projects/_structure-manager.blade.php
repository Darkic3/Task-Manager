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
    $statChapters = collect($destOptions)->whereIn('type', ['chapter','section'])->count();
    $statLessons = collect($destOptions)->where('type', 'lesson')->count();
@endphp
@push('styles')
<style>
    .st-wrap { border:1px solid #e7e5f4; border-radius:16px; background:white; overflow:hidden; box-shadow:0 1px 3px rgba(76,29,149,.06); }
    /* ── hero header ── */
    .st-hero { display:flex; align-items:center; gap:12px; flex-wrap:wrap; padding:14px 16px; color:white;
        background:linear-gradient(135deg, #6d28d9 0%, #4f46e5 60%, #4338ca 100%); position:relative; overflow:hidden; }
    .st-hero::before { content:''; position:absolute; top:-40px; inset-inline-end:-30px; width:150px; height:150px;
        background:rgba(255,255,255,.10); border-radius:50%; }
    .st-hero::after { content:''; position:absolute; bottom:-55px; inset-inline-end:60px; width:110px; height:110px;
        background:rgba(255,255,255,.07); border-radius:50%; }
    .st-hero-ico { width:40px; height:40px; border-radius:12px; flex-shrink:0; display:flex; align-items:center; justify-content:center;
        font-size:19px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.22); position:relative; z-index:1; }
    .st-hero-txt { position:relative; z-index:1; min-width:0; }
    .st-hero-title { font-size:14px; font-weight:800; margin:0; }
    .st-hero-sub { font-size:11.5px; margin:1px 0 0; color:rgba(255,255,255,.82); }
    .st-hero-stats { display:flex; gap:6px; margin-inline-start:auto; position:relative; z-index:1; }
    .st-stat { display:inline-flex; align-items:center; gap:5px; font-size:11.5px; font-weight:700;
        background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.20); border-radius:20px; padding:3px 11px; white-space:nowrap; }
    .st-hero-actions { display:flex; gap:6px; position:relative; z-index:1; }
    .st-btn { display:inline-flex; align-items:center; gap:5px; border-radius:8px; padding:6px 12px;
        font-size:12px; font-weight:700; cursor:pointer; transition:all .15s; white-space:nowrap; }
    .st-btn.solid { background:white; color:#5b21b6; border:1px solid white; }
    .st-btn.solid:hover { background:#f5f3ff; box-shadow:0 2px 8px rgba(0,0,0,.18); }
    .st-btn.ghost { background:rgba(255,255,255,.12); color:white; border:1px solid rgba(255,255,255,.35); }
    .st-btn.ghost:hover { background:rgba(255,255,255,.22); }
    .st-btn.plain { border:1px solid #e5e7eb; background:white; color:#3d4149; }
    .st-btn.plain:hover { border-color:#7c3aed; color:#7c3aed; }
    .st-btn.primary { background:#7c3aed; border:1px solid #7c3aed; color:white; }
    .st-btn.primary:hover { background:#6d28d9; }
    /* ── bulk bar ── */
    .st-bulkbar { display:none; align-items:center; gap:8px; padding:9px 14px; background:#f5f3ff;
        border-bottom:1px solid #ddd6fe; font-size:12px; color:#4c1d95; }
    .st-bulkbar.on { display:flex; }
    .st-bulkbar strong { background:#7c3aed; color:white; border-radius:12px; padding:1px 9px; font-size:11.5px; }
    .st-bulkbar select { border:1px solid #c4b5fd; border-radius:8px; padding:5px 9px; font-size:12px; max-width:260px; background:white; }
    /* ── zones ── */
    .st-zone { padding:12px 14px 14px; }
    .st-zone + .st-zone { border-top:1px solid #f1f0f9; }
    .st-zone-head { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
    .st-zone-pill { display:inline-flex; align-items:center; gap:6px; font-size:11.5px; font-weight:800; color:#4c1d95;
        background:#f5f3ff; border:1px solid #e9e4fb; border-radius:20px; padding:3px 12px; }
    .st-zone-pill .n { background:#7c3aed; color:white; border-radius:10px; padding:0 7px; font-size:10.5px; }
    .st-zone-pill.amber { color:#92400e; background:#fffbeb; border-color:#fde5b8; }
    .st-zone-pill.amber .n { background:#f59e0b; }
    .st-zone-hint { font-size:11px; color:#8b8d98; }
    .st-zone-box { border:1.5px dashed #f5c86e; background:#fffdf5; border-radius:12px; padding:10px; }
    .st-zone-box .st-node:last-child { margin-bottom:0; }
    /* ── empty ── */
    .st-empty { padding:34px 20px; text-align:center; }
    .st-empty-ico { width:60px; height:60px; border-radius:20px; margin:0 auto 12px; display:flex; align-items:center; justify-content:center;
        font-size:26px; background:linear-gradient(135deg, #ede9fe, #dbeafe); }
    .st-empty-t { font-size:13.5px; font-weight:700; color:#1f2328; margin-bottom:3px; }
    .st-empty-s { font-size:12px; color:#8b8d98; margin-bottom:14px; }
    .st-empty-cta { display:flex; gap:8px; justify-content:center; flex-wrap:wrap; }
    /* ── nodes ── */
    .st-node { margin-bottom:6px; }
    .st-node:last-child { margin-bottom:0; }
    .st-row { display:flex; align-items:center; gap:8px; padding:9px 11px; border-radius:12px;
        border:1px solid #eef0f2; background:white; transition:background .15s, border-color .15s, box-shadow .18s, transform .18s; }
    .st-row:hover { border-color:#ddd0fa; background:#fbfaff; box-shadow:0 2px 8px -2px rgba(124,58,237,.14); }
    .st-node.st-container > .st-row { border-inline-start:3px solid var(--tc, #7c3aed); background:linear-gradient(0deg, var(--tb, #f5f3ff), var(--tb, #f5f3ff) 0), white; }
    .st-node.dragover > .st-row { outline:2px dashed #7c3aed; outline-offset:2px; background:#f5f3ff; border-color:#7c3aed;
        transform:scale(1.012); box-shadow:0 8px 22px -6px rgba(124,58,237,.4); }
    .st-row.dropped { border-color:#30a46c !important; background:#f0fdf6 !important;
        box-shadow:0 0 0 4px rgba(48,164,108,.18) !important; }
    .st-drag { cursor:grab; color:#c9ccd4; font-size:14px; padding:4px 2px; border-radius:6px; flex-shrink:0; }
    .st-drag:hover { color:#7c3aed; background:#ede9fe; }
    .st-drag:active { cursor:grabbing; }
    .st-check { accent-color:#7c3aed; width:15px; height:15px; flex-shrink:0; cursor:pointer; }
    .st-chev { border:none; width:24px; height:24px; border-radius:50%; background:#f1f0f9; color:#6d6f7a; cursor:pointer;
        padding:0; font-size:11px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; transition:all .13s; }
    .st-chev:hover { background:#ede9fe; color:#7c3aed; }
    .st-node.closed > .st-row .st-chev i { transform:rotate(-90deg); display:inline-block; }
    [dir="rtl"] .st-node.closed > .st-row .st-chev i { transform:rotate(90deg); }
    .st-node.closed > .st-kids, .st-node.closed > .st-tasks { display:none; }
    .st-chev-sp { width:24px; flex-shrink:0; }
    .st-type { display:inline-flex; align-items:center; gap:4px; font-size:10.5px; font-weight:800; white-space:nowrap;
        border:1px solid; border-radius:20px; padding:2px 9px; flex-shrink:0; }
    .st-name { font-size:13.5px; font-weight:700; color:#1f2328; text-decoration:none; overflow:hidden;
        text-overflow:ellipsis; white-space:nowrap; flex:1; min-width:50px; }
    .st-name:hover { color:#7c3aed; }
    .st-counts { font-size:11px; font-weight:700; color:#6d6f7a; background:#f4f4f6; border-radius:10px; padding:2px 8px; white-space:nowrap; flex-shrink:0; }
    .st-pb { width:76px; height:6px; border-radius:4px; overflow:hidden; flex-shrink:0; background:var(--tt, #eef0f2); }
    .st-pb-fill { display:block; height:100%; border-radius:4px; transition:width .3s; }
    .st-pct { font-size:11px; font-weight:800; color:#1f2328; min-width:40px; text-align:end; flex-shrink:0; }
    .st-actions { display:flex; align-items:center; gap:1px; margin-inline-start:auto; flex-shrink:0; }
    .st-icon-btn { border:1px solid transparent; background:none; color:#a2a5ae; cursor:pointer; border-radius:8px;
        padding:4px 7px; font-size:13px; transition:all .12s; }
    .st-icon-btn:hover { color:#7c3aed; background:#ede9fe; border-color:#ddd0fa; }
    /* ── floating popover (appended positioning: fixed, never clipped) ── */
    #st-pop { position:fixed; z-index:1050; background:white; border:1px solid #e9e7f5; border-radius:14px;
        box-shadow:0 18px 48px rgba(76,29,149,.22), 0 2px 8px rgba(76,29,149,.10);
        width:264px; max-height:min(70vh, 380px); display:flex; flex-direction:column; overflow:hidden;
        animation:st-pop-in .14s ease-out; }
    @keyframes st-pop-in { from { opacity:0; transform:translateY(-5px) scale(.98); } to { opacity:1; transform:none; } }
    .st-pop-head { display:flex; align-items:center; gap:8px; padding:10px 12px; background:#faf9ff; border-bottom:1px solid #f1f0f9; }
    .st-pop-head .ico { width:28px; height:28px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
    .st-pop-head .nm { font-size:12.5px; font-weight:800; color:#1f2328; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; min-width:0; }
    .st-pop-head .tp { font-size:10px; font-weight:800; border:1px solid; border-radius:10px; padding:1px 7px; flex-shrink:0; }
    .st-pop-body { overflow-y:auto; padding:6px; overscroll-behavior:contain; -webkit-overflow-scrolling:touch; }
    .st-pop-lbl { font-size:10px; font-weight:800; color:#a8a3b8; text-transform:uppercase; letter-spacing:.4px; padding:7px 8px 3px; }
    .st-pop-it { display:flex; align-items:center; gap:8px; width:100%; border:none; background:none; text-align:start;
        font-size:12.5px; font-weight:600; color:#3d4149; padding:7px 9px; border-radius:9px; cursor:pointer; text-decoration:none; }
    .st-pop-it:hover { background:#f5f3ff; color:#6d28d9; }
    .st-pop-it .t { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; min-width:0; }
    .st-pop-it .e { font-size:14px; flex-shrink:0; }
    .st-pop-it.danger { color:#c4303f; }
    .st-pop-it.danger:hover { background:#fef2f2; color:#c4303f; }
    .st-pop-sep { border-top:1px solid #f1f0f9; margin:5px 2px; }
    .st-pop-conv { display:flex; gap:6px; padding:2px 8px 8px; }
    .st-pop-conv button { flex:1; font-size:18px; padding:7px 0; border:1px solid #eceaf4; border-radius:10px; background:#fafbfe; cursor:pointer; transition:all .12s; }
    .st-pop-conv button:hover, .st-pop-conv button.cur { border-color:#7c3aed; background:#f5f3ff; transform:translateY(-1px); }
    /* ── task chips: hover actions + drag ── */
    .st-task { position:relative; }
    .st-task.drag-src { opacity:.35; transform:scale(.95); }
    .st-cact { display:none; align-items:center; gap:0; margin-inline-start:2px; }
    .st-task:hover .st-cact, .st-task:focus-within .st-cact { display:inline-flex; }
    .st-cact button { border:none; background:none; color:#a2a5ae; cursor:pointer; border-radius:6px; padding:2px 4px; font-size:10.5px; line-height:1; }
    .st-cact button:hover { color:#6d28d9; background:#ede9fe; }
    .st-cact button[data-ctdel]:hover { color:#c4303f; background:#fdebec; }
    /* ── inline rename: big, clear editing state ── */
    .inline-rename-wrap { display:inline-flex; align-items:center; gap:7px; background:white;
        border:2px solid #7c3aed; border-radius:12px; padding:5px 8px 5px 11px;
        box-shadow:0 0 0 4px rgba(124,58,237,.15), 0 6px 18px -4px rgba(124,58,237,.3); }
    [dir="rtl"] .inline-rename-wrap { padding:5px 11px 5px 8px; }
    .st-task input.inline-rename { border:none; outline:none; background:transparent;
        font-size:13.5px; font-weight:700; color:#1f2328; width:200px; max-width:38vw; padding:1px 2px; }
    .rename-hint { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:700;
        color:#6d28d9; background:#f5f3ff; border-radius:7px; padding:3px 8px; white-space:nowrap; }
    .rename-hint kbd { font-family:inherit; background:white; border:1px solid #ddd0fa;
        border-bottom-width:2px; border-radius:5px; padding:0 6px; font-size:10px; color:#6d28d9; }
    .st-task.removing { transition:all .25s; opacity:0; transform:scale(.92); }
    /* ── drop-to-root strip (visible while dragging a task) ── */
    #st-toroot { display:none; margin:0 14px 10px; border:1.5px dashed #7c3aed; background:#f5f3ff; color:#6d28d9;
        font-size:12px; font-weight:800; border-radius:10px; padding:9px 12px; text-align:center; }
    .st-wrap.task-dragging #st-toroot { display:block; animation:st-pulse 1s ease-in-out infinite; }
    .st-wrap.task-dragging .st-row { cursor:copy; }
    .st-drop { margin:6px 4px; border:1.5px dashed #7c3aed; background:#f5f3ff; color:#6d28d9; font-size:12px; font-weight:700;
        border-radius:10px; padding:8px 12px; animation:st-pulse 1s ease-in-out infinite; }
    @keyframes st-pulse { 0%,100% { opacity:1; } 50% { opacity:.65; } }
    .st-kids { margin-inline-start:22px; border-inline-start:2px solid #eceaf6; border-radius:2px; padding-inline-start:8px; margin-top:6px; }
    .st-tasks { display:flex; flex-wrap:wrap; gap:5px; padding:7px 4px 3px 44px; }
    [dir="rtl"] .st-tasks { padding:7px 44px 3px 4px; }
    .st-task { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:#4b5563;
        background:#f7f8fa; border:1px solid #ececf1; border-radius:20px; padding:3px 8px 3px 8px; cursor:grab; max-width:100%; transition:all .12s; }
    .st-task:active { cursor:grabbing; }
    .st-task-title { cursor:pointer; }
    .st-task-title:hover { color:#6d28d9; }
    [dir="rtl"] .st-task { padding:3px 8px 3px 11px; }
    .st-task:hover { border-color:#c4b5fd; }
    .st-task.done { background:#f0fdf6; border-color:#bbe7cd; }
    .st-task.done .st-task-title { text-decoration:line-through; color:#8aa392; }
    .st-task input { accent-color:#30a46c; width:13px; height:13px; }
    .st-task-title { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:220px; }
    .st-more { font-size:11px; font-weight:700; color:#7c3aed; text-decoration:none; align-self:center; }
    .st-more:hover { text-decoration:underline; }
    .st-quick input { border:1px solid #c4b5fd; border-radius:20px; padding:4px 11px; font-size:12px; outline:none; width:230px; background:white; }
    #st-toroot.dropped { border-color:#30a46c; background:#f0fdf6; color:#1c7a4d; }
    @media (hover:none) {
        .st-cact { display:inline-flex; opacity:.9; }
    }
    @media(max-width:640px) {
        .st-drag { display:none; }
        .st-pb, .st-counts { display:none; }
        .st-hero-actions { width:100%; }
        .st-hero-actions .st-btn { flex:1; justify-content:center; }
        .st-kids { margin-inline-start:10px; }
        .st-tasks { padding-inline-start:8px; }
        .st-type .st-type-txt { display:none; }
    }
</style>
@endpush

<div class="st-wrap" id="st-manager"
     data-root-id="{{ $project->id }}"
     data-store-url="{{ route('projects.nodes.store', $project) }}"
     data-move-url="{{ route('projects.nodes.move', $project) }}"
     data-structure-url="{{ route('projects.structure', $project) }}"
     data-rows-url="{{ route('projects.rows.html', $project) }}">
    <div class="st-hero">
        <span class="st-hero-ico">📚</span>
        <div class="st-hero-txt">
            <p class="st-hero-title">{{ __('Structure') }}</p>
            <p class="st-hero-sub">{{ __('chapters → lessons · drag, move, create') }}</p>
        </div>
        @if(!$direct->isEmpty())
            <span class="st-hero-stats">
                @if($statChapters > 0)<span class="st-stat" id="st-stat-chapters">📁 {{ app_num($statChapters) }} {{ __('Chapter') }}</span>@endif
                @if($statLessons > 0)<span class="st-stat" id="st-stat-lessons">📄 {{ app_num($statLessons) }} {{ __('Lesson') }}</span>@endif
            </span>
        @endif
        <span class="st-hero-actions">
            <button type="button" class="st-btn solid" data-st-add="chapter" data-st-parent="{{ $project->id }}"><i class="bi bi-folder-plus"></i> {{ __('New chapter') }}</button>
            <button type="button" class="st-btn ghost" data-st-add="lesson" data-st-parent="{{ $project->id }}"><i class="bi bi-file-earmark-plus"></i> {{ __('New lesson') }}</button>
        </span>
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
        <button type="button" class="st-btn plain" id="st-bulkclear">{{ __('Clear') }}</button>
    </div>

    <div id="st-body">
    @if($direct->isEmpty())
        <div class="st-empty" id="st-empty">
            <div class="st-empty-ico">📂</div>
            <div class="st-empty-t">{{ __('No chapters or lessons yet. Start with a chapter, then add lessons into it.') }}</div>
            <div class="st-empty-cta">
                <button type="button" class="st-btn primary" data-st-add="chapter" data-st-parent="{{ $project->id }}"><i class="bi bi-folder-plus"></i> {{ __('New chapter') }}</button>
                <button type="button" class="st-btn plain" data-st-add="lesson" data-st-parent="{{ $project->id }}"><i class="bi bi-file-earmark-plus"></i> {{ __('New lesson') }}</button>
            </div>
        </div>
    @else
        @if($containers->count() > 0)
            <div class="st-zone" id="st-zone-containers">
                <div class="st-zone-head">
                    <span class="st-zone-pill">📁 {{ __('Chapters & groups') }} <span class="n">{{ app_num($containers->count()) }}</span></span>
                </div>
                <div id="st-blocks-containers" data-blocks="containers">
                @foreach($containers as $node)
                    @include('projects._structure-node', ['node' => $node, 'depth' => 0, 'rootId' => (int) $project->id, 'destOptions' => $destOptions])
                @endforeach
                </div>
            </div>
        @endif
        @if($ungrouped->count() > 0)
            <div class="st-zone" id="st-zone-ungrouped">
                <div class="st-zone-head">
                    <span class="st-zone-pill amber">📄 {{ __('Ungrouped lessons') }} <span class="n">{{ app_num($ungrouped->count()) }}</span></span>
                    <span class="st-zone-hint">{{ __('These lessons sit directly under the book. Move them into a chapter with drag & drop or the ⋯ menu.') }}</span>
                </div>
                <div class="st-zone-box">
                    <div id="st-blocks-ungrouped" data-blocks="ungrouped">
                    @foreach($ungrouped as $node)
                        @include('projects._structure-node', ['node' => $node, 'depth' => 0, 'rootId' => (int) $project->id, 'destOptions' => $destOptions])
                    @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endif
    </div>
    <div id="st-toroot" data-toroot="{{ $project->id }}">📥 {{ __('Move to root (no chapter)') }}</div>
</div>

{{-- Floating action popover for the ⋯ button (position:fixed → never clipped) --}}
<div id="st-pop" hidden>
    <div class="st-pop-head">
        <span class="ico" id="st-pop-ico">📁</span>
        <span class="nm" id="st-pop-name"></span>
        <span class="tp" id="st-pop-type"></span>
    </div>
    <div class="st-pop-body">
        <div class="st-pop-lbl">{{ __('Move to…') }}</div>
        <div id="st-pop-dests"></div>
        <div class="st-pop-sep"></div>
        <div class="st-pop-lbl">{{ __('Convert to') }}</div>
        <div class="st-pop-conv" id="st-pop-conv"></div>
        <div class="st-pop-sep"></div>
        <a class="st-pop-it" id="st-pop-edit" href="#"><span class="e">✏️</span><span class="t">{{ __('Full edit') }}</span></a>
        <button type="button" class="st-pop-it danger" id="st-pop-del"><span class="e">🗑</span><span class="t">{{ __('Delete') }}</span></button>
    </div>
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
    const FA = document.documentElement.lang === 'fa';
    const num = (n) => FA ? Number(n).toLocaleString('fa-IR') : String(n);

    const toast = (msg, icon) => {
        if (window.alertSwal) alertSwal(msg, null, icon || 'success');
        else alert(msg);
    };

    /* ── island bridge: refresh in place when the Vue island is mounted,
       legacy full reload otherwise (progressive enhancement) ── */
    const refreshPage = () => {
        if (window.ProjectStructure && window.ProjectStructure.mounted) {
            window.ProjectStructure.sync().catch(() => {});
        } else {
            refreshPage();
        }
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
    /* Exposed so the Vue island can re-apply persisted collapse after renders. */
    window.__stApplyCollapsed = applyCollapsed;
    mgr.addEventListener('click', (e) => {
        const t = e.target.closest('[data-st-toggle]');
        if (!t) return;
        const id = String(t.dataset.stToggle);
        collapsed.has(id) ? collapsed.delete(id) : collapsed.add(id);
        localStorage.setItem(ck, JSON.stringify([...collapsed]));
        applyCollapsed();
    });

    /* ── floating ⋯ popover (fixed → never clipped, bidi-safe rows) ── */
    const pop = $('#st-pop');
    let popNode = null, treeCache = null;
    const TYPE_META = {
        project: ['📦', @json(__('Project'))],
        subproject: ['🗂', @json(__('Sub-project'))],
        chapter: ['📁', @json(__('Chapter'))],
        lesson: ['📄', @json(__('Lesson'))],
        section: ['📑', @json(__('Section'))],
    };
    const TYPE_COLOR = { project:'#6366f1', subproject:'#7c3aed', chapter:'#f59e0b', lesson:'#06b6d4', section:'#ec4899' };
    const closePop = () => { if (pop) pop.hidden = true; popNode = null; };
    let popOpenedAt = 0;
    const placePop = (btn) => {
        const r = btn.getBoundingClientRect();
        const W = 264, M = 8;
        pop.style.visibility = 'hidden'; pop.hidden = false;
        const H = pop.offsetHeight || 300;
        let left = r.right - W;
        left = Math.max(M, Math.min(left, window.innerWidth - W - M));
        let top = r.bottom + 6;
        if (top + H > window.innerHeight - M) top = Math.max(M, r.top - H - 6);
        pop.style.left = left + 'px'; pop.style.top = top + 'px';
        pop.style.visibility = '';
        popOpenedAt = Date.now();
    };
    const fetchTree = async () => {
        if (treeCache) return treeCache;
        const res = await fetch(mgr.dataset.structureUrl, { headers });
        const j = await res.json();
        treeCache = (j && j.ok && j.tree) ? j.tree : null;
        return treeCache;
    };
    const flattenTree = (node, depth, out) => {
        (node.children || []).forEach(c => { out.push({ id: c.id, name: c.name, type: c.type, depth }); flattenTree(c, depth + 1, out); });
    };
    const deleteNode = async (id, name) => {
        const ok = await confirmSwal(@json(__('Delete this item and all its tasks/sub-items? This cannot be undone.')), { isDelete: true });
        if (!ok) return;
        closePop();
        try {
            const res = await fetch('{{ url('projects') }}/' + id, { method: 'DELETE', headers });
            const j = await res.json().catch(() => ({}));
            if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
            refreshPage();
        } catch (err) { toast(err.message, 'error'); }
    };
    const convertNode = async (id, tv) => {
        try {
            const res = await fetch('{{ url('projects') }}/' + id + '/convert',
                { method: 'PATCH', headers, body: JSON.stringify({ type: tv }) });
            const j = await res.json().catch(() => ({}));
            if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
            closePop();
            refreshPage();
        } catch (err) { toast(err.message, 'error'); }
    };
    const openPop = async (btn) => {
        const id = btn.dataset.stMenu;
        if (popNode === id && pop && !pop.hidden) { closePop(); return; }
        closePop();
        const meta = TYPE_META[btn.dataset.stType] || ['📄', btn.dataset.stType];
        const col = TYPE_COLOR[btn.dataset.stType] || '#6b7280';
        $('#st-pop-ico').textContent = meta[0];
        $('#st-pop-name').textContent = btn.dataset.stName || '';
        const tp = $('#st-pop-type');
        tp.textContent = meta[1];
        tp.style.color = col; tp.style.borderColor = col + '40'; tp.style.background = col + '14';
        $('#st-pop-edit').href = (btn.dataset.stEdit || '#') + '?return_to=' + encodeURIComponent(window.location.pathname + window.location.search);
        $('#st-pop-del').onclick = () => deleteNode(id, btn.dataset.stName);
        const box = $('#st-pop-dests');
        box.innerHTML = '';
        const loading = document.createElement('div');
        loading.style.cssText = 'padding:8px;font-size:12px;color:#8b8d98;';
        loading.textContent = '…';
        box.appendChild(loading);
        placePop(btn);
        popNode = id;
        try {
            const tree = await fetchTree();
            if (popNode !== id) return; // superseded
            const list = [];
            if (tree) flattenTree(tree, 0, list);
            box.innerHTML = '';
            const mkBtn = (destId, icon, text, depthPx) => {
                const b = document.createElement('button');
                b.type = 'button'; b.className = 'st-pop-it';
                b.style.paddingInlineStart = (9 + depthPx) + 'px';
                const e = document.createElement('span'); e.className = 'e'; e.textContent = icon;
                const t = document.createElement('span'); t.className = 't'; t.textContent = text;
                b.appendChild(e); b.appendChild(t);
                b.addEventListener('click', () => { closePop(); doMove([parseInt(id)], destId); });
                return b;
            };
            box.appendChild(mkBtn(null, '📥', @json(__('Ungrouped (root)')), 0));
            list.forEach(d => {
                if (String(d.id) === String(id) || String(d.id) === String(rootId)) return;
                box.appendChild(mkBtn(d.id, (TYPE_META[d.type] || ['📄'])[0], d.name, Math.min(4, d.depth) * 14));
            });
        } catch (err) { box.innerHTML = ''; }
        const conv = $('#st-pop-conv');
        conv.innerHTML = '';
        ['chapter', 'lesson', 'section'].forEach(tv => {
            const b = document.createElement('button');
            b.type = 'button'; b.textContent = TYPE_META[tv][0]; b.title = TYPE_META[tv][1];
            if (tv === btn.dataset.stType) b.classList.add('cur');
            b.addEventListener('click', () => convertNode(id, tv));
            conv.appendChild(b);
        });
        placePop(btn);
    };
    mgr.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-st-menu]');
        if (!btn) return;
        e.stopPropagation();
        openPop(btn);
    });
    document.addEventListener('pointerdown', (e) => {
        if (!pop || pop.hidden) return;
        const t = e.target;
        if (pop.contains(t)) return;
        if (t && typeof t.closest === 'function' && t.closest('[data-st-menu]')) return;
        closePop();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && pop && !pop.hidden) closePop(); });
    window.addEventListener('scroll', (e) => {
        if (!pop || pop.hidden) return;
        /* Scrolling the destination list itself must NOT close the popover. */
        if (e.target && pop.contains(e.target)) return;
        /* Ignore scroll momentum from the opening gesture (touch devices). */
        if (Date.now() - popOpenedAt < 250) return;
        closePop();
    }, { capture: true, passive: true });
    window.addEventListener('resize', () => closePop());

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
            refreshPage();
        } catch (err) { toast(err.message, 'error'); btn.disabled = false; }
    };
    $('#stModalSave')?.addEventListener('click', doCreate);
    $('#stModalName')?.addEventListener('keydown', (e) => { if (e.key === 'Enter') doCreate(); });

    /* ── move helper (fetch only; callers refresh so bulk can clear first) ── */
    const doMove = async (ids, dest) => {
        const res = await fetch(moveUrl, { method: 'PATCH', headers,
            body: JSON.stringify({ ids, destination_id: dest || null }) });
        const j = await res.json().catch(() => ({}));
        if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
    };
    mgr.addEventListener('click', async (e) => {
        const b = e.target.closest('[data-st-move]');
        if (!b) return;
        b.disabled = true;
        try { await doMove([parseInt(b.dataset.stMove)], b.dataset.stDest || null); refreshPage(); }
        catch (err) { toast(err.message, 'error'); b.disabled = false; }
    });

    /* ── bulk select ── */
    const bulkbar = $('#st-bulkbar'), bulkcount = $('#st-bulkcount');
    const selected = () => $$('.st-check:checked', mgr).map(c => parseInt(c.dataset.checkId));
    const refreshBulk = () => {
        const n = selected().length;
        bulkbar.classList.toggle('on', n > 0);
        bulkcount.textContent = num(n);
    };
    mgr.addEventListener('change', (e) => { if (e.target.matches('.st-check')) refreshBulk(); });
    $('#st-bulkclear')?.addEventListener('click', () => { $$('.st-check:checked', mgr).forEach(c => c.checked = false); refreshBulk(); });
    $('#st-bulkmove')?.addEventListener('click', async (e) => {
        const ids = selected();
        if (!ids.length) return;
        e.target.disabled = true;
        try {
            await doMove(ids, $('#st-bulkdest').value || null);
            $$('.st-check:checked', mgr).forEach(c => c.checked = false);
            refreshBulk();
            refreshPage();
        } catch (err) { toast(err.message, 'error'); e.target.disabled = false; }
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
            refreshPage();
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
            refreshPage();
        } catch (err) { toast(err.message, 'error'); c.checked = !c.checked; }
    });

    /* ── task chips: open / inline rename / quick delete ── */
    const chipRename = (id) => {
        const titleEl = mgr.querySelector('[data-cttitle="' + id + '"]');
        if (!titleEl || titleEl.querySelector('input')) return;
        const chip = mgr.querySelector('[data-ctid="' + id + '"]');
        const old = titleEl.textContent;
        titleEl.innerHTML = '';
        const wrap = document.createElement('span');
        wrap.className = 'inline-rename-wrap';
        const inp = document.createElement('input');
        inp.className = 'inline-rename'; inp.value = old; inp.maxLength = 255;
        inp.title = @json(__('Quick rename — Enter to save, Esc to cancel'));
        inp.setAttribute('aria-label', @json(__('Rename')));
        const hint = document.createElement('span');
        hint.className = 'rename-hint';
        const k1 = document.createElement('kbd'); k1.textContent = 'Enter';
        const k2 = document.createElement('kbd'); k2.textContent = 'Esc';
        hint.appendChild(k1); hint.appendChild(k2);
        wrap.appendChild(inp); wrap.appendChild(hint);
        titleEl.appendChild(wrap); inp.focus(); inp.select();
        let done = false;
        const commit = async (save) => {
            if (done) return; done = true;
            const v = inp.value.trim();
            if (!save || !v || v === old) { titleEl.textContent = old; return; }
            titleEl.textContent = v;
            try {
                const res = await fetch('{{ url('tasks') }}/' + id, { method: 'PUT', headers,
                    body: JSON.stringify({ title: v,
                        priority: (chip && chip.dataset.ctprior) || 'medium',
                        status: (chip && chip.dataset.ctstatus) || 'to_do' }) });
                const j = await res.json().catch(() => ({}));
                if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
            } catch (err) { titleEl.textContent = old; toast(err.message, 'error'); }
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
    const chipDelete = async (id) => {
        const ok = await confirmSwal(@json(__('Delete this task?')), { isDelete: true });
        if (!ok) return;
        try {
            const res = await fetch('{{ url('tasks') }}/' + id, { method: 'DELETE', headers });
            const j = await res.json().catch(() => ({}));
            if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
            const chip = mgr.querySelector('[data-ctid="' + id + '"]');
            if (chip) { chip.classList.add('removing'); setTimeout(() => refreshPage(), 260); }
            else refreshPage();
        } catch (err) { toast(err.message, 'error'); }
    };
    mgr.addEventListener('click', (e) => {
        const rn = e.target.closest('[data-ctrename]');
        if (rn) { e.stopPropagation(); chipRename(rn.dataset.ctrename); return; }
        const del = e.target.closest('[data-ctdel]');
        if (del) { e.stopPropagation(); chipDelete(del.dataset.ctdel); return; }
        const t = e.target.closest('.st-task-title');
        if (t && !e.target.closest('button') && !e.target.closest('input')) {
            const wrap = t.closest('[data-ctid]');
            if (wrap && wrap.dataset.cturl && !t.querySelector('input')) window.location.href = wrap.dataset.cturl;
        }
    });

    /* ── task drag & drop across chapters (chips + task-list rows → nodes) ── */
    let taskDragId = null;
    document.addEventListener('dragstart', (e) => {
        const c = e.target.closest && e.target.closest('[data-ctid],[data-ptdrag]');
        if (!c) return;
        taskDragId = c.dataset.ctid || c.dataset.ptdrag;
        mgr.classList.add('task-dragging');
        c.classList.add('drag-src');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/task-id', String(taskDragId)); } catch (_) {}
    });
    document.addEventListener('dragend', () => {
        if (!taskDragId) return;
        taskDragId = null;
        mgr.classList.remove('task-dragging');
        $$('.drag-src').forEach(x => x.classList.remove('drag-src'));
        $$('.st-node.dragover', mgr).forEach(n => n.classList.remove('dragover'));
        $$('.st-drop', mgr).forEach(d => d.hidden = true);
    });
    mgr.addEventListener('dragover', (e) => {
        if (!taskDragId) return;
        const row = e.target.closest('.st-node[data-node-id]');
        const toRoot = e.target.closest('#st-toroot');
        if (!row && !toRoot) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (row) {
            row.classList.add('dragover');
            const dz = $('[data-drop-id="' + row.dataset.nodeId + '"]', row);
            if (dz) dz.hidden = false;
        }
    });
    mgr.addEventListener('drop', async (e) => {
        if (!taskDragId) return;
        const row = e.target.closest('.st-node[data-node-id]');
        const toRoot = e.target.closest('#st-toroot');
        if (!row && !toRoot) return;
        e.preventDefault();
        e.stopPropagation();
        const dest = toRoot ? rootId : row.dataset.nodeId;
        const tid = taskDragId;
        taskDragId = null;
        mgr.classList.remove('task-dragging');
        const flashEl = toRoot || row.querySelector('.st-row');
        if (flashEl) flashEl.classList.add('dropped');
        try {
            const res = await fetch('{{ url('tasks') }}/' + tid + '/move', { method: 'PATCH', headers,
                body: JSON.stringify({ project_id: parseInt(dest), parent_id: null }) });
            const j = await res.json().catch(() => ({}));
            if (!res.ok || !j.ok) throw new Error(j.message || ('HTTP ' + res.status));
            refreshPage();
        } catch (err) { if (flashEl) flashEl.classList.remove('dropped'); toast(err.message, 'error'); }
    });

    /* ── smooth auto-scroll while dragging (wired once per page) ── */
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
        // Dropping onto a group = move INTO it; dropping on root handled by bulk/menu.
        row.classList.add('dropped');
        try { await doMove([parseInt(dragId)], parseInt(dest)); refreshPage(); }
        catch (err) { row.classList.remove('dropped'); toast(err.message, 'error'); }
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

{{-- Structure Vue island (planner pattern): vendored build, no npm step.
     Mounts on #st-body only; hero/bulkbar/popover/modal stay vanilla.
     Without window.Vue the Blade rows + vanilla handlers above keep working
     with the legacy reload behaviour (refreshPage fallback). --}}
@push('scripts')
<script src="{{ asset('assets/vendor/vue/vue.global.prod.js') }}?v=3.4.38"></script>
<script src="{{ asset('assets/js/project-structure-app.js') }}?v={{ filemtime(public_path('assets/js/project-structure-app.js')) }}"></script>
@endpush
