{{--
    Shared note editor. Self-hosted (no CDN): a Markdown editor with toolbar,
    live server-rendered preview, @/# autocomplete, local drafts, a template
    gallery and Zen mode.

    Expects:
      $note (Note), $notebooks (tree Collection), $labels (Collection),
      $selectedLabels (array), $kindMeta, $templates (array), $action (url)
--}}
@php
    $isEdit = $note->exists;
    $templates = $templates ?? [];

    // Flatten the notebook tree into indented options.
    $nbOptions = [];
    $walk = function ($nodes, $depth) use (&$walk, &$nbOptions) {
        foreach ($nodes as $nb) {
            $nbOptions[] = ['id' => $nb->id, 'label' => str_repeat('— ', $depth).$nb->title];
            $walk($nb->children ?? collect(), $depth + 1);
        }
    };
    $walk($notebooks, 0);

    // Mood/energy only make sense for daily notes — but the block always
    // exists in the DOM now and is toggled, so switching the type at runtime
    // works instead of silently hiding the control.
    $showMood = old('kind', $note->kind) === \App\Models\Note::KIND_DAILY;

    $tplIcons = [];
    foreach (array_keys($templates) as $k) {
        $tplIcons[$k] = $kindMeta[$k]['icon'] ?? 'bi-journal';
    }

    // "New note for this task/project" flow: the chip is shown above the editor
    // and the pair is submitted as hidden inputs so the link survives even when
    // the composer picks are stripped (e.g. JS disabled).
    $prefillLink = $prefillLink ?? null;
    $prefillIco = $prefillLink && $prefillLink['type'] === 'project' ? 'bi-folder' : 'bi-check2-square';
    $prefillText = $prefillLink ? ($prefillLink['type'] === 'project' ? __('Project') : __('Task')) : '';
@endphp

<form method="POST" action="{{ $action }}" id="ntNoteForm">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    @if ($prefillLink)
        <input type="hidden" name="linked_type" value="{{ $prefillLink['type'] }}">
        <input type="hidden" name="linked_id" value="{{ $prefillLink['id'] }}">
        <div class="nt-prefill" id="ntPrefill">
            <i class="bi {{ $prefillIco }}"></i>
            <span class="nt-prefill-label">{{ $prefillText }}</span>
            <strong>{{ $prefillLink['name'] }}</strong>
            <a href="{{ route('notes.index') }}" class="ms-auto" title="{{ __('Discard link') }}"><i class="bi bi-x-lg"></i></a>
        </div>
    @endif

    {{-- Unsaved draft found in this browser --}}
    <div class="nt-draft-bar" id="ntDraftBar" hidden>
        <i class="bi bi-cloud-arrow-down"></i>
        <span id="ntDraftText">{{ __('A newer unsaved draft was found in this browser.') }}</span>
        <span class="ms-auto d-flex gap-1">
            <button type="button" class="btn btn-sm btn-brand" id="ntDraftRestore">{{ __('Restore') }}</button>
            <button type="button" class="btn btn-sm btn-outline" id="ntDraftDiscard">{{ __('Discard') }}</button>
        </span>
    </div>

    <div class="nt-editor-grid">
        {{-- ── Main column ── --}}
        <div>
            <div class="nt-panel">
                <div class="card-body">
                    <div class="mb-2">
                        <label class="nt-form-label" for="ntTitle">{{ __('Title') }}</label>
                        <input type="text" class="nt-title-input @error('title') is-invalid @enderror"
                               id="ntTitle" name="title" maxlength="255" required
                               value="{{ old('title', $note->title) }}"
                               placeholder="{{ __('What is this note about?') }}">
                        @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="nt-ed-shell" id="ntEdShell" data-mode="write">
                        <div class="nt-ed-toolbar">
                            <div class="nt-ed-group">
                                <button type="button" class="nt-ed-btn nt-ed-heading" data-cmd="h1" title="{{ __('Heading 1') }}">H1</button>
                                <button type="button" class="nt-ed-btn nt-ed-heading" data-cmd="h2" title="{{ __('Heading 2') }}">H2</button>
                                <button type="button" class="nt-ed-btn nt-ed-heading" data-cmd="h3" title="{{ __('Heading 3') }}">H3</button>
                            </div>
                            <span class="nt-ed-sep"></span>
                            <div class="nt-ed-group">
                                <button type="button" class="nt-ed-btn" data-cmd="bold" title="{{ __('Bold') }} (Ctrl+B)"><i class="bi bi-type-bold"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="italic" title="{{ __('Italic') }} (Ctrl+I)"><i class="bi bi-type-italic"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="strike" title="{{ __('Strikethrough') }} (Ctrl+Shift+X)"><i class="bi bi-type-strikethrough"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="code" title="{{ __('Inline code') }}"><i class="bi bi-code"></i></button>
                            </div>
                            <span class="nt-ed-sep"></span>
                            <div class="nt-ed-group">
                                <button type="button" class="nt-ed-btn" data-cmd="link" title="{{ __('Link') }} (Ctrl+K)"><i class="bi bi-link-45deg"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="image" title="{{ __('Image') }}"><i class="bi bi-image"></i></button>
                            </div>
                            <span class="nt-ed-sep"></span>
                            <div class="nt-ed-group">
                                <button type="button" class="nt-ed-btn" data-cmd="ul" title="{{ __('Bulleted list') }}"><i class="bi bi-list-ul"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="ol" title="{{ __('Numbered list') }}"><i class="bi bi-list-ol"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="task" title="{{ __('To-do list') }}"><i class="bi bi-check2-square"></i></button>
                            </div>
                            <span class="nt-ed-sep"></span>
                            <div class="nt-ed-group">
                                <button type="button" class="nt-ed-btn" data-cmd="quote" title="{{ __('Quote') }}"><i class="bi bi-quote"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="codeblock" title="{{ __('Code block') }}"><i class="bi bi-terminal"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="hr" title="{{ __('Divider') }}"><i class="bi bi-dash-lg"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="table" title="{{ __('Table') }}"><i class="bi bi-table"></i></button>
                            </div>

                            <span class="nt-ed-spacer"></span>

                            <div class="nt-ed-modes" role="group" aria-label="{{ __('Editor view') }}">
                                <button type="button" class="nt-ed-mode is-on" data-mode="write" title="{{ __('Write') }}">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="nt-ed-mode" data-mode="split" title="{{ __('Side by side') }}">
                                    <i class="bi bi-layout-split"></i>
                                </button>
                                <button type="button" class="nt-ed-mode" data-mode="preview" title="{{ __('Preview') }}">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <span class="nt-ed-sep"></span>
                            <button type="button" class="nt-ed-btn" data-cmd="zen" id="ntEdZen" title="{{ __('Zen mode') }} (Esc)"><i class="bi bi-arrows-fullscreen"></i></button>
                        </div>

                        <div class="nt-ed-body">
                            <div class="nt-ed-side-write">
                                <textarea id="ntBody" name="content" rows="16" required
                                          class="nt-ed-text @error('content') is-invalid @enderror"
                                          data-mentions-url="{{ route('notes.mentions') }}"
                                          placeholder="{{ __('Write here… Markdown works: **bold**, # heading, - list, > quote, ``` code, | table |') }}">{{ old('content', $note->content) }}</textarea>
                            </div>
                            <div class="nt-ed-divider"></div>
                            <div class="nt-ed-preview nt-prose" id="ntEdPreview"></div>
                        </div>

                        <div class="nt-ed-status">
                            <span><i class="bi bi-text-paragraph"></i> <span id="ntEdWords">0</span> {{ __('words') }}</span>
                            <span><i class="bi bi-ascii"></i> <span id="ntEdChars">0</span> {{ __('chars') }}</span>
                            <span><i class="bi bi-hourglass-split"></i> <span id="ntEdRead">1</span> {{ __('min read') }}</span>
                            <span class="nt-ed-spacer"></span>
                            <span class="nt-ed-draft" id="ntEdDraft"></span>
                            <span id="ntEdCaret">1:1</span>
                        </div>

                        <div class="nt-ed-mention" id="ntEdMention" role="listbox"></div>
                    </div>
                    @error('content')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    <div id="ntMentionChips" class="nt-chipbar"></div>
                </div>
            </div>
        </div>

        {{-- ── Sidebar ── --}}
        <aside class="nt-editor-side">
            @if ($templates)
                <div class="nt-panel">
                    <div class="nt-panel-head"><i class="bi bi-magic"></i>{{ __('Templates') }}</div>
                    <div class="nt-panel-body">
                        <div class="nt-tpl-grid">
                            @foreach ($templates as $tk => $tpl)
                                <button type="button" class="nt-tpl {{ old('kind', $note->kind) === $tk ? 'is-on' : '' }}" data-tpl="{{ $tk }}" title="{{ $tpl['hint'] ?? '' }}">
                                    <i class="bi {{ $tplIcons[$tk] ?? 'bi-journal' }}"></i>
                                    <span class="nt-tpl-name">{{ $kindMeta[$tk]['label'] ?? $tk }}</span>
                                </button>
                            @endforeach
                        </div>
                        <div class="nt-form-help mt-2" id="ntTemplateHint">{{ $templates[$note->kind]['hint'] ?? '' }}</div>
                    </div>
                </div>
            @endif

            <div class="nt-panel">
                <div class="nt-panel-head"><i class="bi bi-sliders"></i>{{ __('Details') }}</div>
                <div class="nt-panel-body">
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

                    <div class="nt-form-row mb-0">
                        <label class="nt-form-label" for="ntOccurred">{{ __('When did it happen?') }}</label>
                        <x-jalali-date name="occurred_at" id="ntOccurred" type="datetime" class="form-control"
                               :value="old('occurred_at', $note->occurred_at ? $note->occurred_at->format('Y-m-d H:i') : '')" />
                        <div class="nt-form-help">{{ __('Leave empty for today') }}</div>
                    </div>
                </div>
            </div>

            <div class="nt-panel" id="ntMoodBlock" {{ $showMood ? '' : 'hidden' }}>
                <div class="nt-panel-head"><i class="bi bi-emoji-smile"></i>{{ __('Mood & energy') }}</div>
                <div class="nt-panel-body">
                    <div class="nt-form-row">
                        <div class="nt-form-label">{{ __('Mood') }}</div>
                        <div class="nt-scale" data-scale="mood">
                            @for ($i = 1; $i <= 5; $i++)
                                @php $m = note_mood_icon($i); @endphp
                                <button type="button" class="nt-scale-btn {{ (int) old('mood', $note->mood) === $i ? 'is-on' : '' }}"
                                        data-value="{{ $i }}" title="{{ $i }}/5">{{ $m['emoji'] }}</button>
                            @endfor
                        </div>
                        <input type="hidden" name="mood" id="ntMood" value="{{ old('mood', $note->mood) }}">
                    </div>
                    <div class="nt-form-row mb-0">
                        <div class="nt-form-label">{{ __('Energy') }}</div>
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

            <div class="nt-panel">
                <div class="nt-panel-head"><i class="bi bi-bookmark"></i>{{ __('Labels') }}</div>
                <div class="nt-panel-body">
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

            <div class="nt-panel">
                <div class="nt-panel-head"><i class="bi bi-pin-angle"></i>{{ __('Options') }}</div>
                <div class="nt-panel-body">
                    <label class="nt-check mb-0">
                        <input type="checkbox" name="is_pinned" value="1"
                               {{ old('is_pinned', $note->is_pinned) ? 'checked' : '' }}>
                        <i class="bi bi-pin-angle"></i>{{ __('Pin to top') }}
                    </label>
                </div>
            </div>
        </aside>
    </div>

    <div class="nt-savebar">
        <button type="submit" class="btn btn-brand" id="ntSave">
            <i class="bi bi-check-lg"></i>{{ $isEdit ? __('Save changes') : __('Create note') }}
        </button>
        <a href="{{ $isEdit ? route('notes.show', $note) : route('notes.index') }}" class="btn btn-outline">
            {{ __('Cancel') }}
        </a>
        @if ($isEdit)
            <button type="button" class="btn btn-outline text-danger ms-auto" id="ntDelete">
                <i class="bi bi-trash"></i>{{ __('Delete') }}
            </button>
        @else
            <span class="ms-auto nt-savebar-hint"></span>
        @endif
        <span class="nt-savebar-hint"><span class="nt-ed-kbd">Ctrl</span>+<span class="nt-ed-kbd">S</span> {{ __('to save') }}</span>
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
    const shell = document.getElementById('ntEdShell');
    const ta = document.getElementById('ntBody');
    const previewEl = document.getElementById('ntEdPreview');
    const mentionEl = document.getElementById('ntEdMention');
    const form = document.getElementById('ntNoteForm');
    const titleInput = document.getElementById('ntTitle');
    const kindSel = document.getElementById('ntKind');
    const moodBlock = document.getElementById('ntMoodBlock');
    if (!shell || !ta || !form) return;

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const PREVIEW_URL = @json(route('notes.preview'));
    const MENTIONS_URL = ta.dataset.mentionsUrl || '';
    const TEMPLATES = @json($templates);
    const KIND_ICONS = @json($tplIcons);
    const DRAFT_KEY = 'nt:draft:{{ $isEdit ? $note->id : 'new' }}';
    const SERVER_TS = {{ (int) ($isEdit && $note->updated_at ? $note->updated_at->getTimestampMs() : microtime(true) * 1000) }};

    const T = {
        bold: '{{ __('Bold') }}', link: '{{ __('link text') }}', url: '{{ __('https://…') }}',
        alt: '{{ __('image description') }}', code: '{{ __('code') }}',
        empty: '{{ __('Nothing to preview yet.') }}',
        draftSaved: '{{ __('Draft saved') }}', restorable: '{{ __('An unsaved draft from') }}',
        modeSaved: '{{ __('View') }}', hdr: '{{ __('Column') }}', val: '{{ __('Value') }}',
        linkSaved: '{{ __('Saved.') }}', bodyLost: '{{ __('Your text could not be read back — the draft was not restored.') }}',
        imageOK: '{{ __('Image inserted.') }}',
    };

    const isRtl = document.documentElement.getAttribute('dir') === 'rtl';
    const esc = (s) => String(s == null ? '' : s).replace(/[<>&"]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c]));

    /* ── Text primitives ─────────────────────────────────────── */

    // execCommand keeps the browser's native undo stack intact; fall back to a
    // manual splice where it is unavailable.
    function replaceRange(start, end, text, selStart, selEnd) {
        ta.focus();
        ta.setSelectionRange(start, end);
        let ok = false;
        try { ok = document.execCommand('insertText', false, text); } catch (e) { ok = false; }
        if (!ok) {
            ta.value = ta.value.slice(0, start) + text + ta.value.slice(end);
        }
        if (selStart !== undefined) ta.setSelectionRange(selStart, selEnd === undefined ? selStart : selEnd);
        onChange();
    }

    function selection() { return [ta.selectionStart, ta.selectionEnd]; }

    function lineRange(pos) {
        const from = ta.value.lastIndexOf('\n', Math.max(0, pos - 1)) + 1;
        let to = ta.value.indexOf('\n', pos);
        if (to === -1) to = ta.value.length;
        return [from, to];
    }

    function selectedLines() {
        const [start, end] = selection();
        const [from, to] = lineRange(end === start ? start : end);
        return { from, to, lines: ta.value.slice(from, to).split('\n') };
    }

    // Inline wrap with toggle-off when the selection is already wrapped.
    function wrap(before, after, placeholder) {
        const [start, end] = selection();
        const picked = ta.value.slice(start, end);
        const alreadyBefore = ta.value.slice(Math.max(0, start - before.length), start) === before;
        const alreadyAfter = ta.value.slice(end, end + after.length) === after;

        if (picked && alreadyBefore && alreadyAfter) {
            replaceRange(start - before.length, end + after.length, picked, start - before.length);
            return;
        }
        const text = picked || placeholder || '';
        const inner = text;
        const body = before + inner + after;
        replaceRange(start, end, body, picked ? start + before.length : start + before.length,
            picked ? start + before.length + inner.length : start + before.length + inner.length);
    }

    const RE_LIST = /^(\s*)([-*+]\s\[[ xX]\]\s|[-*+]\s|\d+[.)]\s)/;

    function mapLines(fn) {
        const { from, to, lines } = selectedLines();
        const next = fn(lines);
        const text = next.join('\n');
        replaceRange(from, to, text, from, from + text.length);
    }

    function toggleMarker(marker, re) {
        mapLines(lines => {
            const solid = lines.filter(l => l.trim() !== '' && !re.test(l)).length === 0;
            return lines.map(l => {
                if (l.trim() === '') return l;
                if (solid) return l.replace(re, '$1');
                return l.replace(/^(\s*)/, '$1' + marker);
            });
        });
    }

    const reBullet = /^\s*[-*+]\s/;
    const reTask = /^\s*[-*+]\s\[[ xX]\]\s/;
    const reOrdered = /^\s*\d+[.)]\s/;
    const reQuote = /^\s*>\s?/;

    function orderedList() {
        mapLines(lines => {
            const solid = lines.filter(l => l.trim() !== '' && !reOrdered.test(l)).length === 0;
            let n = 0;
            const first = lines.find(l => reOrdered.test(l));
            if (first) n = parseInt(first.trim().match(/^(\d+)/)[1], 10) - 1;
            return lines.map(l => {
                if (l.trim() === '') return l;
                if (solid) return l.replace(reOrdered, '');
                n += 1;
                return l.replace(/^(\s*)/, '$1' + n + '. ');
            });
        });
    }

    function heading(level) {
        const re = new RegExp('^\\s*#{1,6}\\s+');
        mapLines(lines => {
            const solid = lines.filter(l => l.trim() !== '' && new RegExp('^\\s*#{' + level + '}\\s').test(l)).length === 0;
            return lines.map(l => {
                if (l.trim() === '') return l;
                return solid ? l.replace(re, '') : '#'.repeat(level) + ' ' + l.replace(re, '');
            });
        });
    }

    function insertSnippet(text) {
        const [start, end] = selection();
        const value = ta.value;
        const before = value.slice(0, start);
        const after = value.slice(end);
        const lead = before && !before.endsWith('\n') ? '\n' : '';
        const body = lead + text + (after.startsWith('\n') || after === '' ? '\n' : '\n');
        replaceRange(start, end, body, start + lead.length, start + lead.length + text.length);
    }

    function insertLink(image) {
        const [start, end] = selection();
        const picked = ta.value.slice(start, end);
        const open = image ? '![' : '[';
        const label = picked || (image ? T.alt : T.link);
        const body = open + label + '](' + T.url + ')';
        const urlStart = start + open.length + label.length + 3;
        replaceRange(start, end, body, urlStart, urlStart + T.url.length);
    }

    /* ── Toolbar ─────────────────────────────────────────────── */

    const CMDS = {
        bold: () => wrap('**', '**', T.bold),
        italic: () => wrap('_', '_', T.bold),
        strike: () => wrap('~~', '~~', T.bold),
        code: () => wrap('`', '`', T.code),
        codeblock: () => wrap('\n```\n', '\n```\n', T.code),
        link: () => insertLink(false),
        image: () => insertLink(true),
        ul: () => toggleMarker('- ', reBullet),
        task: () => toggleMarker('- [ ] ', reBullet),
        ol: () => orderedList(),
        quote: () => toggleMarker('> ', reQuote),
        hr: () => insertSnippet('---'),
        table: () => insertSnippet('| ' + T.hdr + ' | ' + T.hdr + ' |\n| --- | --- |\n| ' + T.val + ' | ' + T.val + ' |'),
        h1: () => heading(1),
        h2: () => heading(2),
        h3: () => heading(3),
        zen: () => toggleZen(),
    };

    shell.querySelectorAll('[data-cmd]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const fn = CMDS[this.dataset.cmd];
            if (fn) fn();
            ta.focus();
        });
    });

    /* ── Smart Enter / Tab ───────────────────────────────────── */

    ta.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && shell.classList.contains('is-zen')) { toggleZen(false); return; }
        if (mentionEl.classList.contains('is-open') && handleMentionKey(e)) return;

        const mod = e.ctrlKey || e.metaKey;

        // Ctrl/Cmd + shortcuts
        if (mod && !e.shiftKey && e.key.toLowerCase() === 'b') { e.preventDefault(); wrap('**', '**', T.bold); return; }
        if (mod && !e.shiftKey && e.key.toLowerCase() === 'i') { e.preventDefault(); wrap('_', '_', T.bold); return; }
        if (mod && !e.shiftKey && e.key.toLowerCase() === 'k') { e.preventDefault(); insertLink(false); return; }
        if (mod && e.shiftKey && e.key.toLowerCase() === 'x') { e.preventDefault(); wrap('~~', '~~', T.bold); return; }
        if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); saveNote(); return; }

        if (e.key === 'Tab' && !mod) { e.preventDefault(); indent(e.shiftKey ? -1 : 1); return; }

        if (e.key === 'Enter' && !mod && !e.shiftKey) {
            const pos = ta.selectionStart;
            const [from, to] = lineRange(pos);
            const line = ta.value.slice(from, pos);
            const m = line.match(RE_LIST);
            if (m) {
                const rest = line.slice(m[0].length);
                if (rest.trim() === '') {
                    // Empty item → leave the list.
                    replaceRange(from, pos, '', from, from);
                } else {
                    let marker = m[2];
                    if (/^\d+[.)]\s/.test(marker)) marker = (parseInt(marker, 10) + 1) + marker.replace(/^\d+/, '');
                    replaceRange(pos, pos, '\n' + m[1] + marker, pos + 1 + m[1].length + marker.length);
                }
                return;
            }
        }
    });

    function indent(dir) {
        mapLines(lines => lines.map(l => {
            if (dir > 0) return l.trim() === '' ? l : '  ' + l;
            return l.replace(/^ {1,2}/, '');
        }));
    }

    /* ── Preview (rendered by the server = what the reader sees) ── */

    let previewTimer = null;
    let previewAbort = null;

    function schedulePreview() {
        if (shell.dataset.mode === 'write') return;
        clearTimeout(previewTimer);
        previewTimer = setTimeout(renderPreview, 320);
    }

    function renderPreview() {
        previewEl.classList.add('is-loading');
        if (previewAbort) previewAbort.abort();
        previewAbort = new AbortController();

        fetch(PREVIEW_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ content: ta.value }),
            signal: previewAbort.signal
        })
            .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
            .then(j => {
                previewEl.innerHTML = (j.html && j.html.trim()) ? j.html : '<p class="nt-ed-preview-empty">' + esc(T.empty) + '</p>';
            })
            .catch(e => {
                if (e.name === 'AbortError') return;
                previewEl.innerHTML = '<p class="nt-ed-preview-empty">' + esc(T.empty) + '</p>';
            })
            .finally(() => previewEl.classList.remove('is-loading'));
    }

    shell.querySelectorAll('.nt-ed-mode').forEach(btn => {
        btn.addEventListener('click', function () {
            shell.dataset.mode = this.dataset.mode;
            shell.querySelectorAll('.nt-ed-mode').forEach(b => b.classList.toggle('is-on', b === this));
            if (this.dataset.mode !== 'write') renderPreview();
            ta.focus();
        });
    });

    // Keep the preview roughly in step with the writing pane.
    ta.addEventListener('scroll', function () {
        if (shell.dataset.mode !== 'split') return;
        const span = ta.scrollHeight - ta.clientHeight;
        if (span <= 0) return;
        const ratio = ta.scrollTop / span;
        previewEl.scrollTop = ratio * (previewEl.scrollHeight - previewEl.clientHeight);
    });

    function toggleZen(force) {
        const on = typeof force === 'boolean' ? force : !shell.classList.contains('is-zen');
        shell.classList.toggle('is-zen', on);
        document.body.style.overflow = on ? 'hidden' : '';
        ta.focus();
    }
    document.getElementById('ntEdZen').addEventListener('click', () => toggleZen());

    /* ── @person / #label autocomplete ───────────────────────── */

    let mentionResults = [];
    let mentionActive = -1;
    let mentionToken = null;
    let mentionTimer = null;

    function activeToken() {
        const pos = ta.selectionStart;
        const upto = ta.value.slice(0, pos);
        const m = upto.match(/([@#])([\p{L}\p{N}_][\p{L}\p{N}_\-]*)$/u);
        if (!m) return null;
        return { sign: m[1], query: m[2], start: pos - m[0].length, end: pos };
    }

    function closeMention() {
        mentionEl.classList.remove('is-open');
        mentionEl.innerHTML = '';
        mentionResults = [];
        mentionActive = -1;
        mentionToken = null;
    }

    function caretPoint() {
        // Mirror the textarea's text up to the caret to find its coordinates.
        const mirror = document.createElement('div');
        const cs = getComputedStyle(ta);
        ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'paddingTop', 'paddingBottom',
         'paddingLeft', 'paddingRight', 'borderTopWidth', 'borderBottomWidth', 'borderLeftWidth',
         'borderRightWidth', 'boxSizing', 'width', 'direction', 'textAlign'].forEach(p => { mirror.style[p] = cs[p]; });
        mirror.style.position = 'absolute';
        mirror.style.visibility = 'hidden';
        mirror.style.whiteSpace = 'pre-wrap';
        mirror.style.wordWrap = 'break-word';
        mirror.style.overflow = 'hidden';
        mirror.style.top = '0';
        mirror.style.left = '-9999px';
        mirror.textContent = ta.value.slice(0, ta.selectionStart);
        const marker = document.createElement('span');
        marker.textContent = ta.value.slice(ta.selectionStart) || '.';
        mirror.appendChild(marker);
        shell.appendChild(mirror);
        const top = marker.offsetTop - ta.scrollTop + parseFloat(cs.lineHeight || 24) + 6;
        const left = marker.offsetLeft - ta.scrollLeft;
        mirror.remove();
        return { top: Math.max(6, top), left: Math.max(6, left) };
    }

    function openMention(list, token) {
        mentionResults = list;
        mentionToken = token;
        if (!list.length) return closeMention();
        mentionEl.innerHTML = list.map((r, i) =>
            '<div class="nt-ed-mention-item' + (i === 0 ? ' is-active' : '') + '" role="option" data-i="' + i + '">' +
            '<i class="bi ' + esc(r.icon || (token.sign === '#' ? 'bi-tag' : 'bi-dot')) + '"></i>' +
            '<span>' + esc(r.name) + '</span>' +
            '<span class="nt-ed-mention-hint">' + esc(r.hint || r.type || '') + '</span></div>'
        ).join('');
        const pt = caretPoint();
        mentionEl.classList.add('is-open');
        const shellRect = shell.getBoundingClientRect();
        let left = pt.left;
        const width = Math.min(300, Math.max(210, mentionEl.offsetWidth || 210));
        if (left + width > shellRect.width) left = Math.max(6, shellRect.width - width - 6);
        mentionEl.style.left = left + 'px';
        mentionEl.style.top = pt.top + 'px';
        mentionActive = 0;
        mentionEl.querySelectorAll('.nt-ed-mention-item').forEach(el => {
            el.addEventListener('mousedown', e => { e.preventDefault(); chooseMention(+el.dataset.i); });
        });
    }

    function moveMention(delta) {
        const items = mentionEl.querySelectorAll('.nt-ed-mention-item');
        if (!items.length) return;
        mentionActive = (mentionActive + delta + items.length) % items.length;
        items.forEach((el, i) => el.classList.toggle('is-active', i === mentionActive));
        items[mentionActive].scrollIntoView({ block: 'nearest' });
    }

    function chooseMention(i) {
        const r = mentionResults[i];
        if (!r || !mentionToken) return closeMention();
        const token = mentionToken;
        replaceRange(token.start, token.end, token.sign + r.name, token.start + token.sign.length + r.name.length);
        closeMention();
    }

    function handleMentionKey(e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); moveMention(1); return true; }
        if (e.key === 'ArrowUp') { e.preventDefault(); moveMention(-1); return true; }
        if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); chooseMention(mentionActive); return true; }
        if (e.key === 'Escape') { e.preventDefault(); closeMention(); return true; }
        return false;
    }

    function refreshMentions() {
        if (!MENTIONS_URL) return;
        const token = activeToken();
        if (!token || token.query.length < 1) return closeMention();
        clearTimeout(mentionTimer);
        mentionTimer = setTimeout(() => {
            fetch(MENTIONS_URL + '?q=' + encodeURIComponent(token.sign + token.query), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
                .then(r => r.json())
                .then(data => {
                    let list = data.results || [];
                    const exact = list.find(r => (r.name || '').toLowerCase() === token.query.toLowerCase());
                    if (!exact && token.query.trim()) {
                        list = [{
                            name: token.query,
                            icon: token.sign === '#' ? 'bi-plus-circle' : 'bi-person-plus',
                            hint: 'new'
                        }].concat(list);
                    }
                    openMention(list, token);
                })
                .catch(() => closeMention());
        }, 160);
    }

    ta.addEventListener('input', refreshMentions);
    ta.addEventListener('click', () => { if (mentionEl.classList.contains('is-open')) refreshMentions(); });
    ta.addEventListener('blur', () => setTimeout(closeMention, 140));

    /* ── Chips preview of @/# tokens ──────────────────────────── */

    const chips = document.getElementById('ntMentionChips');
    function renderChips() {
        if (!chips) return;
        const found = [];
        (ta.value.match(/[@#][\p{L}\p{N}_][\p{L}\p{N}_\-]*/gu) || []).forEach(tok => {
            if (!found.some(f => f.toLowerCase() === tok.toLowerCase())) found.push(tok);
        });
        chips.innerHTML = found.slice(0, 24).map(tok => {
            const label = tok.replace(/[<>&]/g, '');
            return tok[0] === '#'
                ? '<span class="nt-chip" style="background:#dbeafe;border-color:#bfdbfe;color:#1d4ed8"><i class="bi bi-tag"></i>' + label + '</span>'
                : '<span class="nt-chip" style="background:#ede9fe;border-color:#ddd6fe;color:#6d28d9"><i class="bi bi-at"></i>' + label + '</span>';
        }).join('');
    }

    /* ── Counts, caret, draft ─────────────────────────────────── */

    const wordsEl = document.getElementById('ntEdWords');
    const charsEl = document.getElementById('ntEdChars');
    const readEl = document.getElementById('ntEdRead');
    const caretEl = document.getElementById('ntEdCaret');
    const draftEl = document.getElementById('ntEdDraft');
    const draftBar = document.getElementById('ntDraftBar');
    const draftText = document.getElementById('ntDraftText');

    let draftTimer = null;
    let draftLoaded = null;

    function updateCounters() {
        const text = ta.value;
        const words = text.trim() ? (text.trim().match(/\S+/g) || []).length : 0;
        wordsEl.textContent = words;
        charsEl.textContent = text.length;
        readEl.textContent = Math.max(1, Math.ceil(words / 200));
        const pos = ta.selectionStart;
        const upto = ta.value.slice(0, pos);
        const line = upto.split('\n');
        caretEl.textContent = line.length + ':' + (line[line.length - 1].length + 1);
    }

    function readDraft() {
        try { return JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) { return null; }
    }

    function writeDraft() {
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify({
                content: ta.value,
                title: titleInput ? titleInput.value : '',
                ts: Date.now()
            }));
            draftEl.textContent = T.draftSaved + ' · ' + new Date().toLocaleTimeString();
        } catch (e) { /* private mode */ }
    }

    function checkDraft() {
        const d = readDraft();
        if (!d || typeof d.content !== 'string') return;
        const newer = Number(d.ts) > Number(SERVER_TS);
        const differs = d.content !== ta.value;
        if (!newer || !differs) return;
        draftLoaded = d;
        if (draftText) {
            draftText.textContent = T.restorable + ' ' + new Date(Number(d.ts)).toLocaleString() + '.';
        }
        draftBar.hidden = false;
    }

    function scheduleDraft() {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(writeDraft, 1100);
    }

    document.getElementById('ntDraftRestore').addEventListener('click', function () {
        const d = draftLoaded || readDraft();
        if (!d) return;
        if (titleInput && typeof d.title === 'string') titleInput.value = d.title;
        ta.value = d.content;
        draftBar.hidden = true;
        onChange();
        ta.focus();
    });

    document.getElementById('ntDraftDiscard').addEventListener('click', function () {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
        draftBar.hidden = true;
    });

    function onChange() {
        updateCounters();
        renderChips();
        schedulePreview();
        scheduleDraft();
    }

    /* ── Type / templates ─────────────────────────────────────── */

    const hintEl = document.getElementById('ntTemplateHint');

    function applyTemplate(kind, force) {
        const tpl = TEMPLATES[kind];
        if (!tpl) return;
        const body = tpl.body || '';
        const current = ta.value;
        const untouched = current.trim() === '' || Object.keys(TEMPLATES).some(k => (TEMPLATES[k].body || '') === current);

        const run = () => {
            ta.value = body;
            if (hintEl) hintEl.textContent = tpl.hint || '';
            shell.querySelectorAll('[data-tpl]').forEach(b => b.classList.toggle('is-on', b.dataset.tpl === kind));
            onChange();
            ta.focus();
        };

        if (!untouched && !force) {
            confirmSwal('{{ __('Replace the current text with this template?') }}').then(ok => { if (ok) run(); });
            return;
        }
        run();
    }

    shell.closest('body') && document.querySelectorAll('[data-tpl]').forEach(btn => {
        btn.addEventListener('click', function () {
            const kind = this.dataset.tpl;
            if (kindSel) kindSel.value = kind;
            applyTemplate(kind);
            toggleMood();
        });
    });

    if (kindSel) {
        kindSel.addEventListener('change', function () {
            toggleMood();
            applyTemplate(this.value);
        });
    }

    function toggleMood() {
        if (!moodBlock) return;
        moodBlock.hidden = !kindSel || kindSel.value !== 'daily';
    }
    toggleMood();

    /* ── Mood / energy scales ─────────────────────────────────── */

    document.querySelectorAll('[data-scale]').forEach(scale => {
        const field = scale.dataset.scale === 'mood' ? document.getElementById('ntMood') : document.getElementById('ntEnergy');
        scale.querySelectorAll('.nt-scale-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const already = this.classList.contains('is-on');
                scale.querySelectorAll('.nt-scale-btn').forEach(b => b.classList.remove('is-on'));
                if (already) { field.value = ''; return; }
                this.classList.add('is-on');
                field.value = this.dataset.value;
            });
        });
    });

    /* ── Save, delete, dirty guard ────────────────────────────── */

    function saveNote() {
        writeDraft();
        form.requestSubmit ? form.requestSubmit() : form.submit();
    }

    const saveBtn = document.getElementById('ntSave');
    form.addEventListener('submit', function () {
        if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + saveBtn.textContent.trim(); }
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
        toggleZen(false);
    });

    const del = document.getElementById('ntDelete');
    if (del) {
        del.addEventListener('click', function () {
            confirmSwal(document.getElementById('ntDeleteForm'), '{{ __('Delete this note permanently?') }}', { isDelete: true });
        });
    }

    let dirty = false;
    const initialContent = ta.value;
    const initialTitle = titleInput ? titleInput.value : '';
    function resetDirty() { dirty = false; }
    ta.addEventListener('input', () => { dirty = true; });
    if (titleInput) titleInput.addEventListener('input', () => { dirty = true; });

    window.addEventListener('beforeunload', function (e) {
        if (!dirty) return;
        e.preventDefault();
        e.returnValue = '';
    });

    /* ── Boot ─────────────────────────────────────────────────── */

    updateCounters();
    renderChips();
    checkDraft();
    if (shell.dataset.mode !== 'write') renderPreview();
});
</script>
@endpush