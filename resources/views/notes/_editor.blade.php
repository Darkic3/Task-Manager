{{--
    Shared note form. Expects:
      $note (Note), $notebooks (tree Collection), $labels (Collection),
      $selectedLabels (array), $selectedFiles (array), $action (url), $method
--}}
@php
    $isEdit = $note->exists;

    // Flatten the notebook tree into indented options.
    $nbOptions = [];
    $walk = function ($nodes, $depth) use (&$walk, &$nbOptions) {
        foreach ($nodes as $nb) {
            $nbOptions[] = ['id' => $nb->id, 'label' => str_repeat('— ', $depth).$nb->title];
            $walk($nb->children ?? collect(), $depth + 1);
        }
    };
    $walk($notebooks, 0);
@endphp

<form method="POST" action="{{ $action }}" id="ntNoteForm">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="row g-3">
{{--  ── Body ── }}--}}
        <div class="col-lg-8">
            <div class="panel">
                <div class="card-body">
                    <div class="nt-form-row">
                        <label class="nt-form-label" for="ntTitle">{{ __('Title') }}</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror"
                               id="ntTitle" name="title" maxlength="255" required
                               value="{{ old('title', $note->title) }}"
                               placeholder="{{ __('What is this about?') }}">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="nt-form-row">
                        <div class="d-flex align-items-center gap-2">
                            <label class="nt-form-label mb-0" for="ntBody">{{ __('Content') }}</label>
                            <button type="button" class="btn btn-outline ms-auto" id="ntTemplateBtn"
                                    style="padding:2px 10px;font-size:.72rem;" title="{{ __('Insert template for this type') }}">
                                <i class="bi bi-magic"></i> {{ __('Template') }}
                            </button>
                        </div>
                        <div class="nt-form-help" id="ntTemplateHint" style="margin-top:4px;"></div>
                        <textarea id="ntBody" name="content" rows="14" required
                                  class="form-control @error('content') is-invalid @enderror"
                                  placeholder="{{ __('Write in Markdown. Use @name and #label to connect this note to people and topics.') }}">{{ old('content', $note->content) }}</textarea>
                        @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="nt-form-help">
                            <i class="bi bi-at"></i> {{ __('connects to a person or topic') }} ·
                            <i class="bi bi-hash"></i> {{ __('creates a label') }} ·
                            {{ __('both are resolved automatically when you save') }}
                        </div>
                    </div>

                    <div id="ntMentionChips" class="nt-chipbar"></div>
                </div>
            </div>
        </div>

{{--  ── Sidebar ── }}--}}
        <div class="col-lg-4">
            <div class="panel mb-3">
                <div class="card-header"><i class="bi bi-sliders"></i> {{ __('Details') }}</div>
                <div class="card-body">
                    <div class="nt-form-row">
                        <label class="nt-form-label" for="ntKind">{{ __('Type') }}</label>
                        <select class="nt-select @error('kind') is-invalid @enderror" id="ntKind" name="kind">
                            @foreach ($kindMeta as $k => $info)
                                <option value="{{ $k }}" {{ old('kind', $note->kind) === $k ? 'selected' : '' }}>
                                    {{ $info['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('kind')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="nt-form-row">
                        <label class="nt-form-label" for="ntNotebook">{{ __('Notebook') }}</label>
                        <select class="nt-select" id="ntNotebook" name="notebook_id">
                            <option value="">{{ __('— Unfiled —') }}</option>
                            @foreach ($nbOptions as $opt)
                                <option value="{{ $opt['id'] }}" {{ (int) old('notebook_id', $note->notebook_id) === (int) $opt['id'] ? 'selected' : '' }}>
                                    {{ $opt['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="nt-form-row">
                        <label class="nt-form-label" for="ntOccurred">{{ __('When did it happen?') }}</label>
                        <x-jalali-date name="occurred_at" id="ntOccurred" type="datetime" class="form-control"
                               :value="old('occurred_at', $note->occurred_at ? $note->occurred_at->format('Y-m-d H:i') : '')" />
                        <div class="nt-form-help">{{ __('Leave empty for today') }}</div>
                    </div>

                    @if ($note->kind === \App\Models\Note::KIND_DAILY || old('kind') === \App\Models\Note::KIND_DAILY)
                        <div class="nt-form-row">
                            <label class="nt-form-label">{{ __('Mood & energy') }}</label>
                            <div class="d-flex gap-3">
                                <div>
                                    <div class="nt-form-help" style="margin-top:0">{{ __('Mood') }}</div>
                                    <div class="nt-scale" data-scale="mood">
                                        @for ($i = 1; $i <= 5; $i++)
                                            @php $m = note_mood_icon($i); @endphp
                                            <button type="button" class="nt-scale-btn {{ (int) old('mood', $note->mood) === $i ? 'is-on' : '' }}"
                                                    data-value="{{ $i }}" title="{{ $i }}/5">{{ $m['emoji'] }}</button>
                                        @endfor
                                    </div>
                                    <input type="hidden" name="mood" id="ntMood" value="{{ old('mood', $note->mood) }}">
                                </div>
                                <div>
                                    <div class="nt-form-help" style="margin-top:0">{{ __('Energy') }}</div>
                                    <div class="nt-scale" data-scale="energy">
                                        @for ($i = 1; $i <= 5; $i++)
                                            @php $m = note_mood_icon($i); @endphp
                                            <button type="button" class="nt-scale-btn {{ (int) old('energy', $note->energy) === $i ? 'is-on' : '' }}"
                                                    data-value="{{ $i }}" title="{{ $i }}/5">{{ $m['emoji'] }}</button>
                                        @endfor
                                    </div>
                                    <input type="hidden" name="energy" id="ntEnergy" value="{{ old('energy', $note->energy) }}">
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="nt-form-row mb-0">
                        <label class="form-check-label d-flex align-items-center gap-2" style="font-size:.83rem;font-weight:600;">
                            <input type="checkbox" class="form-check-input mt-0" name="is_pinned" value="1"
                                   {{ old('is_pinned', $note->is_pinned) ? 'checked' : '' }}>
                            <i class="bi bi-pin-angle"></i>{{ __('Pin to top') }}
                        </label>
                    </div>
                </div>
            </div>

            <div class="panel mb-3">
                <div class="card-header"><i class="bi bi-bookmark"></i> {{ __('Labels') }}</div>
                <div class="card-body">
                    @if ($labels->count())
                        <div class="nt-checks">
                            @foreach ($labels as $label)
                                <label class="nt-check">
                                    <input type="checkbox" name="label_ids[]" value="{{ $label->id }}"
                                        {{ in_array((int) $label->id, array_map('intval', $selectedLabels), true) ? 'checked' : '' }}>
                                    <span class="nt-lbl-dot" style="background:{{ $label->color }}"></span>{{ $label->name }}
                                </label>
                            @endforeach
                        </div>
                    @else
                        <p class="nt-form-help" style="margin:0">
                            {{ __('No labels yet — write #something in the body to create one.') }}
                        </p>
                    @endif
                </div>
            </div>

            @if (! $isEdit || $note->tags)
                <div class="panel">
                    <div class="card-header"><i class="bi bi-tags"></i> {{ __('Free-text tags') }}</div>
                    <div class="card-body">
                        <input type="text" class="form-control" name="tags"
                               value="{{ old('tags', is_array($note->tags) ? implode(', ', $note->tags) : '') }}"
                               placeholder="{{ __('comma, separated, tags') }}">
                        <div class="nt-form-help">{{ __('Legacy keyword list — kept as-is') }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-brand">
            <i class="bi bi-check-lg"></i>{{ $isEdit ? __('Save changes') : __('Create note') }}
        </button>
        <a href="{{ $isEdit ? route('notes.show', $note) : route('notes.index') }}" class="btn btn-outline">
            {{ __('Cancel') }}
        </a>
        @if ($isEdit)
            <button type="button" class="btn btn-outline ms-auto text-danger" id="ntDelete">
                <i class="bi bi-trash"></i>{{ __('Delete') }}
            </button>
        @endif
    </div>
</form>

@if ($isEdit)
    <form method="POST" action="{{ route('notes.destroy', $note) }}" id="ntDeleteForm" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    /* ── Markdown editor (falls back to the plain textarea if the CDN is blocked) ── */
    const body = document.getElementById('ntBody');
    let mde = null;
    if (body && typeof EasyMDE !== 'undefined') {
        mde = new EasyMDE({
            element: body,
            autofocus: false,
            spellChecker: false,
            autoDownloadFontAwesome: false,
            status: ['words', 'characters'],
            forceSync: true,
            lineWrapping: true,
            minHeight: '400px',
            placeholder: body.getAttribute('placeholder') || '',
            toolbar: ['bold', 'italic', 'heading', '|', 'quote', 'unordered-list', 'ordered-list', '|',
                      'link', 'image', 'code', '|', 'preview', 'side-by-side', 'fullscreen', 'guide', 'undo', 'redo']
        });

        // Let CodeMirror handle RTL natively (avoids the cursor jumping to the
        // wrong side that happens when direction is only forced with CSS).
        if (document.documentElement.getAttribute('dir') === 'rtl') {
            mde.codemirror.setOption('direction', 'rtl');
            mde.codemirror.refresh();
        }
    }

    /* ── Live @/# chips preview (resolved on save) ── */
    const chips = document.getElementById('ntMentionChips');
    function renderChips() {
        if (!chips) return;
        const text = mde ? mde.value() : body.value;
        const found = [];
        (text.match(/[@#][\p{L}\p{N}_][\p{L}\p{N}_\-]*/gu) || []).forEach(function (t) {
            if (!found.some(f => f.token.toLowerCase() === t.toLowerCase())) found.push({ token: t });
        });
        chips.innerHTML = found.slice(0, 24).map(function (f) {
            const isLabel = f.token[0] === '#';
            return `<span class="nt-chip" style="${isLabel
                ? 'background:#dbeafe;border-color:#bfdbfe;color:#1d4ed8'
                : 'background:#ede9fe;border-color:#ddd6fe;color:#6d28d9'}">${f.token.replace(/[<>&]/g, '')}</span>`;
        }).join('');
    }
    if (body) {
        renderChips();
        body.addEventListener('input', renderChips);
        if (mde) mde.codemirror.on('change', renderChips);
    }

    /* ── Mood / energy scales ── */
    document.querySelectorAll('[data-scale]').forEach(scale => {
        const field = scale.dataset.scale === 'mood'
            ? document.getElementById('ntMood') : document.getElementById('ntEnergy');
        scale.querySelectorAll('.nt-scale-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const v = this.dataset.value;
                const already = this.classList.contains('is-on');
                scale.querySelectorAll('.nt-scale-btn').forEach(b => b.classList.remove('is-on'));
                if (already) { field.value = ''; return; }
                this.classList.add('is-on');
                field.value = v;
            });
        });
    });

    /* ── Reveal mood/energy only for daily notes ── */
    const kind = document.getElementById('ntKind');
    const templates = @json($templates ?? []);
    const hintEl = document.getElementById('ntTemplateHint');
    const tplBtn = document.getElementById('ntTemplateBtn');
    function currentText() { return mde ? mde.value() : body.value; }
    function setText(v) {
        if (mde) { mde.value(v); } else { body.value = v; }
        renderChips();
    }
    if (kind && body) {
        const toggle = function () {
            const isDaily = kind.value === 'daily';
            const block = document.getElementById('ntMood')?.closest('.nt-form-row');
            if (block) block.style.display = isDaily ? '' : 'none';
            if (hintEl && templates[kind.value]) hintEl.textContent = templates[kind.value].hint || '';
        };
        kind.addEventListener('change', function () {
            toggle();
            // Auto-fill the skeleton when the body is still empty.
            if (templates[kind.value] && currentText().trim() === '') setText(templates[kind.value].body);
        });
        toggle();
    }
    if (tplBtn) {
        tplBtn.addEventListener('click', function () {
            const k = kind ? kind.value : 'general';
            if (!templates[k]) return;
            if (currentText().trim() !== '') {
                confirmSwal('{{ __('Replace the current text with the template?') }}').then(ok => {
                    if (ok) setText(templates[k].body);
                });
                return;
            }
            setText(templates[k].body);
        });
    }

    /* ── Delete ── */
    const del = document.getElementById('ntDelete');
    if (del) {
        del.addEventListener('click', function () {
            confirmSwal(document.getElementById('ntDeleteForm'), '{{ __('Delete this note permanently?') }}', { isDelete: true });
        });
    }

    /* ── Guard against losing unsaved Markdown ── */
    let dirty = false;
    if (body) {
        body.addEventListener('input', () => { dirty = true; });
        if (mde) mde.codemirror.on('change', () => { dirty = true; });
    }
    window.addEventListener('beforeunload', function (e) {
        if (!dirty) return;
        e.preventDefault();
        e.returnValue = '';
    });
    const form = document.getElementById('ntNoteForm');
    if (form) form.addEventListener('submit', () => { dirty = false; });
});
</script>
@endpush
