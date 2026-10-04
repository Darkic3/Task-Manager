{{-- Quick capture: type freely, use @subject / #label tokens. --}}
<div class="nt-capture">
    <form method="POST" action="{{ route('notes.quick-capture') }}" id="ntCaptureForm">
        @csrf

        <div class="nt-capture-row">
            <i class="bi bi-lightning-charge-fill nt-capture-ico" aria-hidden="true"></i>

            <textarea name="body" id="ntCaptureInput" class="nt-capture-input" rows="2"
                      data-mentions-url="{{ route('notes.mentions') }}"
                      placeholder="{{ __('Capture a thought… use @person, #label and it files itself') }}"
                      maxlength="8000" required></textarea>

            <div class="nt-capture-actions">
                <button type="button" class="nt-capture-btn" id="ntCaptureKind"
                        title="{{ __('Type: auto') }}" data-kind="">
                    <i class="bi bi-magic"></i><span id="ntCaptureKindLabel">{{ __('Auto') }}</span>
                </button>

                <button type="submit" class="nt-capture-btn" id="ntCaptureSubmit">
                    <i class="bi bi-plus-lg"></i>{{ __('Add') }}
                </button>
            </div>
        </div>

        <div class="nt-capture-hint">
            <span><kbd>@</kbd> {{ __('person / topic') }}</span>
            <span><kbd>#</kbd> {{ __('label') }}</span>
            <span><kbd>Ctrl</kbd>+<kbd>Enter</kbd> {{ __('save') }}</span>
            <span class="ms-auto" id="ntCaptureStatus"></span>
        </div>
    </form>

    <div class="nt-mention-menu" id="ntMentionMenu" role="listbox"></div>
</div>

{{-- Type picker --}}
<div class="dropdown mt-2 d-inline-block">
    <button type="button" class="btn btn-outline" style="font-size:.75rem;" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-tag"></i>{{ __('Save as') }}
    </button>
    <ul class="dropdown-menu dropdown-menu-end" style="max-height:320px;overflow-y:auto;">
        @foreach ($kindMeta as $k => $info)
            <li>
                <button type="button" class="dropdown-item" data-pick-kind="{{ $k }}">
                    <i class="bi {{ $info['icon'] }} text-primary"></i>{{ $info['label'] }}
                </button>
            </li>
        @endforeach
    </ul>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form    = document.getElementById('ntCaptureForm');
    const input   = document.getElementById('ntCaptureInput');
    const menu    = document.getElementById('ntMentionMenu');
    const status  = document.getElementById('ntCaptureStatus');
    const submit  = document.getElementById('ntCaptureSubmit');
    const kindBtn = document.getElementById('ntCaptureKind');
    const kindLbl = document.getElementById('ntCaptureKindLabel');
    if (!form || !input || !menu) return;

    const csrf    = document.querySelector('meta[name="csrf-token"]').content;
    const url     = input.dataset.mentionsUrl;
    const kindMeta = @json($kindMeta);
    const captureErrorText = @json(__('Could not save the note.'));

    let forcedKind = '';
    let results    = [];
    let active     = -1;
    let tokenStart = null;
    let timer      = null;

    /* ── Type picker ── */
    const kindName = (k) => (kindMeta[k] && kindMeta[k].label) || k;
    document.querySelectorAll('[data-pick-kind]').forEach(btn => {
        btn.addEventListener('click', function () {
            forcedKind = this.dataset.pickKind;
            kindLbl.textContent = kindName(forcedKind);
            input.focus();
        });
    });

    /* ── Detect the active @ / # token under the caret ── */
    function activeToken() {
        const pos = input.selectionStart;
        const upto = input.value.slice(0, pos);
        const m = upto.match(/([@#])([\p{L}\p{N}_][\p{L}\p{N}_\-]*)$/u);
        if (!m) return null;
        return { sign: m[1], query: m[2], start: pos - m[0].length };
    }

    /* ── Menu ── */
    function closeMenu() { menu.classList.remove('is-open'); menu.innerHTML = ''; results = []; active = -1; tokenStart = null; }

    function openMenu(list, token, sign) {
        results = list;
        if (!list.length) return closeMenu();
        menu.innerHTML = list.map((r, i) => `
            <div class="nt-mention-item ${i === 0 ? 'is-active' : ''}" role="option" data-i="${i}">
                <span class="nt-mention-ico"><i class="bi ${r.icon || 'bi-dot'}"></i></span>
                <span class="nt-mention-name">${(r.name || '').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]))}</span>
                ${r.isNew ? '<span class="nt-mention-new">' + (sign === '#' ? 'NEW LABEL' : 'NEW') + '</span>' : ''}
                <span class="nt-mention-hint">${(r.hint || r.type || '')}</span>
            </div>`).join('');
        menu.classList.add('is-open');
        active = 0;
        bindItems();
    }

    function bindItems() {
        menu.querySelectorAll('.nt-mention-item').forEach(el => {
            el.addEventListener('mousedown', (e) => { e.preventDefault(); choose(+el.dataset.i); });
        });
    }

    function choose(i) {
        const r = results[i];
        if (!r || tokenStart === null) return;
        const pos = input.selectionStart;
        const after = input.value.slice(pos);
        const insert = r.sign + r.name;
        input.value = input.value.slice(0, tokenStart) + insert + after;
        const caret = tokenStart + insert.length;
        input.focus();
        input.setSelectionRange(caret, caret);
        closeMenu();
    }

    function move(delta) {
        const items = menu.querySelectorAll('.nt-mention-item');
        if (!items.length) return;
        active = (active + delta + items.length) % items.length;
        items.forEach((el, i) => el.classList.toggle('is-active', i === active));
        items[active].scrollIntoView({ block: 'nearest' });
    }

    /* ── Fetch suggestions ── */
    function refresh() {
        const t = activeToken();
        if (!t) return closeMenu();
        tokenStart = t.start;
        const qs = t.sign + t.query;
        clearTimeout(timer);
        timer = setTimeout(() => {
            fetch(`${url}?q=${encodeURIComponent(qs)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    let list = data.results || [];
                    const exact = list.find(r => r.name.toLowerCase() === t.query.toLowerCase());
                    if (!exact && t.query.trim() !== '') {
                        list = [{
                            type: t.sign === '#' ? 'label' : 'subject',
                            name: t.query, icon: t.sign === '#' ? 'bi-tag' : 'bi-plus-circle',
                            hint: 'create', isNew: true
                        }, ...list];
                    }
                    openMenu(list, t, t.sign);
                })
                .catch(() => closeMenu());
        }, 160);
    }

    /* ── Events ── */
    input.addEventListener('input', refresh);
    input.addEventListener('click', () => { if (menu.classList.contains('is-open')) refresh(); });
    input.addEventListener('blur', () => setTimeout(closeMenu, 140));
    input.addEventListener('keydown', (e) => {
        if (menu.classList.contains('is-open')) {
            if (e.key === 'ArrowDown') { e.preventDefault(); move(1); return; }
            if (e.key === 'ArrowUp')   { e.preventDefault(); move(-1); return; }
            if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); choose(active); return; }
            if (e.key === 'Escape')    { e.preventDefault(); closeMenu(); return; }
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            form.requestSubmit ? form.requestSubmit() : form.submit();
        }
    });

    /* Ctrl/Cmd+K focuses the capture box */
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            input.focus();
            input.select();
        }
    });

    /* ── Submit ── */
    form.addEventListener('submit', function (e) {
        const body = input.value.trim();
        if (!body) { e.preventDefault(); return; }

        e.preventDefault();
        submit.disabled = true;
        status.textContent = '…';

        const fd = new FormData();
        fd.append('_token', csrf);
        fd.append('body', body);
        if (forcedKind) fd.append('kind', forcedKind);

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            submit.disabled = false;
            status.textContent = '';
            if (!data.success) { status.textContent = data.message || captureErrorText; return; }

            input.value = '';
            closeMenu();
            refreshResults(data.note);
        })
        .catch(() => { submit.disabled = false; status.textContent = captureErrorText; });
    });

    /* ── Prepend the new card without a full reload ── */
    function refreshResults(note) {
        const host = document.getElementById('nt-results');
        if (!host) return;
        host.style.opacity = '.45';
        fetch(window.location.pathname + window.location.search + (window.location.search ? '&' : '?') + 'partial=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            host.innerHTML = data.html;
            const sidebar = document.getElementById('nt-filters');
            if (sidebar && data.sidebar) sidebar.outerHTML = data.sidebar;
            host.style.opacity = '1';
        })
        .catch(() => { window.location.reload(); });
    }
});
</script>
@endpush