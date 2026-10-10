{{--
    Linked-notes section for a task / project / subtask page.
    Expects:
      $entityKind  'task' | 'project'
      $attachUrl   POST {note_id}  -> {success, note:{id,title,url}}
      $detachBase  URL prefix for DELETE, link id appended (e.g. route('tasks.notes.detach', [$task, '__ID__']) with placeholder)
      $createUrl   notes.create with ?linked_type=&linked_id=
      $indexUrl    notes.index with ?linked_type=<Class>&linked_id=
      $linkedNotes Collection<Note>, $noteLinks Collection<NoteLink>, $recentNotes Collection<Note{id,title}>
--}}
@php
    $linkIdByNote = ($noteLinks ?? collect())->keyBy('note_id')->map(fn ($l) => $l->id);
    $uid = $entityKind.'Notes';
@endphp

<div id="{{ $uid }}" style="margin-top:20px;border-top:1px solid #eef0f2;padding-top:20px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
        <h2 style="font-size:12px;font-weight:700;color:#8b8d98;text-transform:uppercase;letter-spacing:.5px;margin:0;">
            {{ __('Notes') }}
            <span style="font-weight:400;text-transform:none;letter-spacing:0;">· {{ ($linkedNotes ?? collect())->count() }}</span>
        </h2>
        <span style="flex:1;"></span>
        <a href="{{ $indexUrl }}" style="border:1px solid #e5e7eb;background:#fff;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:600;color:#6b6f78;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <i class="bi bi-journals"></i> {{ __('View all') }}
        </a>
        <a href="{{ $createUrl }}" style="border:1px solid #e5e7eb;background:#fff;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:600;color:#6b6f78;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            <i class="bi bi-plus-lg"></i> {{ __('New note') }}
        </a>
        <button type="button" data-role="open-link" style="border:1px solid #7c3aed;background:#7c3aed;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:600;color:#fff;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
            <i class="bi bi-link-45deg"></i> {{ __('Link note') }}
        </button>
    </div>

    <div data-role="list">
        @forelse(($linkedNotes ?? collect()) as $n)
            <div data-note-row="{{ $n->id }}" style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:6px;">
                <span style="width:30px;height:30px;border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:#ede9fe;color:#7c3aed;"><i class="bi bi-journal-text" style="font-size:12px;"></i></span>
                <div style="flex:1;min-width:0;">
                    <a href="{{ route('notes.show', $n) }}" style="font-size:13px;font-weight:600;color:#1f2328;text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $n->title }}</a>
                    <div style="font-size:11px;color:#8b8d98;">{{ $n->updated_at?->diffForHumans() }}</div>
                </div>
                @if($linkIdByNote->has($n->id))
                    <button type="button" data-detach="{{ $linkIdByNote->get($n->id) }}" title="{{ __('Unlink') }}" style="background:none;border:none;cursor:pointer;color:#c1c4cc;padding:4px;font-size:12px;">
                        <i class="bi bi-x-lg"></i>
                    </button>
                @endif
            </div>
        @empty
            <p data-role="empty" style="color:#c1c4cc;font-size:13px;font-style:italic;margin:0;">{{ __('No notes linked yet. Create one or link an existing note.') }}</p>
        @endforelse
    </div>

    {{-- Link-existing modal --}}
    <div data-role="modal" style="display:none;position:fixed;inset:0;background:rgba(20,22,28,.45);z-index:9000;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:10px;width:420px;max-width:94vw;overflow:hidden;">
            <div style="padding:14px 18px;border-bottom:1px solid #eef0f2;display:flex;align-items:center;justify-content:space-between;">
                <h5 style="margin:0;font-size:14px;font-weight:700;">{{ __('Link an existing note') }}</h5>
                <button type="button" data-role="close" style="background:none;border:none;color:#8b8d98;cursor:pointer;font-size:15px;"><i class="bi bi-x"></i></button>
            </div>
            <div style="padding:14px 18px;">
                <input type="text" data-role="filter" class="form-control" placeholder="{{ __('Search your notes…') }}" style="font-size:13px;" autocomplete="off">
                <div data-role="options" style="max-height:260px;overflow-y:auto;margin-top:8px;display:flex;flex-direction:column;gap:4px;">
                    @forelse(($recentNotes ?? collect()) as $rn)
                        <button type="button" data-attach="{{ $rn->id }}" data-name="{{ mb_strtolower($rn->title) }}"
                                style="display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;cursor:pointer;font-size:13px;text-align:start;">
                            <i class="bi bi-journal-text" style="color:#7c3aed;"></i>
                            <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $rn->title }}</span>
                        </button>
                    @empty
                        <p style="font-size:12.5px;color:#8b8d98;font-style:italic;">{{ __('No notes yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const root = document.getElementById(@json($uid));
    if (!root || root.dataset.bound) return;
    root.dataset.bound = '1';

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const attachUrl = @json($attachUrl);
    const detachBase = @json($detachBase);
    const list = root.querySelector('[data-role="list"]');
    const modal = root.querySelector('[data-role="modal"]');

    root.querySelector('[data-role="open-link"]').addEventListener('click', () => {
        modal.style.display = 'flex';
        const f = root.querySelector('[data-role="filter"]');
        if (f) { f.value = ''; filterOptions(''); f.focus(); }
    });
    root.querySelector('[data-role="close"]').addEventListener('click', () => { modal.style.display = 'none'; });
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });

    function filterOptions(q) {
        q = (q || '').trim().toLowerCase();
        root.querySelectorAll('[data-attach]').forEach(btn => {
            btn.style.display = (!q || (btn.dataset.name || '').includes(q)) ? '' : 'none';
        });
    }
    root.querySelector('[data-role="filter"]').addEventListener('input', function () { filterOptions(this.value); });

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    root.querySelectorAll('[data-attach]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const noteId = btn.dataset.attach;
            btn.disabled = true;
            try {
                const r = await fetch(attachUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ note_id: +noteId }),
                });
                const d = await r.json();
                if (d.success) {
                    modal.style.display = 'none';
                    window.location.reload();
                } else {
                    alert(d.message || @json(__('Could not attach.')));
                }
            } catch (e) {
                alert(@json(__('Could not attach.')));
            } finally {
                btn.disabled = false;
            }
        });
    });

    list.querySelectorAll('[data-detach]').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!await confirmSwal(@json(__('Unlink this note? The note itself is kept.')), { isDelete: false })) return;
            try {
                const r = await fetch(detachBase.replace('__ID__', btn.dataset.detach), {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                const d = await r.json();
                if (d.success) {
                    const row = list.querySelector(`[data-note-row="${btn.closest('[data-note-row]').dataset.noteRow}"]`);
                    if (row) row.remove();
                    if (!list.querySelector('[data-note-row]')) window.location.reload();
                }
            } catch (e) { /* noop */ }
        });
    });
})();
</script>
