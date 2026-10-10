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

    // WYSIWYG: the editor surface is HTML, the stored value stays Markdown.
    // Render the Markdown once on the server so the rich surface opens with
    // exactly what the reader view shows.
    $initialMarkdown = old('content', $note->content ?? '');
    try {
        $initialHtml = \App\Support\MarkdownRenderer::toHtml($initialMarkdown);
    } catch (\Throwable $e) {
        $initialHtml = '<p>'.e($initialMarkdown).'</p>';
    }
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
        {{-- ── Main column: minimal WYSIWYG surface (stores Markdown) ── --}}
        <div>
            <div class="nt-panel nt-write-panel">
                <div class="card-body nt-write-body">
                    <div class="mb-1">
                        <label class="nt-form-label visually-hidden" for="ntTitle">{{ __('Title') }}</label>
                        <input type="text" class="nt-title-input @error('title') is-invalid @enderror"
                               id="ntTitle" name="title" maxlength="255" required
                               value="{{ old('title', $note->title) }}"
                               placeholder="{{ __('Title — what is this note about?') }}">
                        @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="nt-ed-shell is-rich" id="ntEdShell">
                        <div class="nt-ed-toolbar" id="ntToolbar" role="toolbar" aria-label="{{ __('Formatting') }}">
                            <div class="nt-ed-group">
                                <button type="button" class="nt-ed-btn" data-cmd="undo" title="{{ __('Undo') }} (Ctrl+Z)"><i class="bi bi-arrow-counterclockwise"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="redo" title="{{ __('Redo') }} (Ctrl+Y)"><i class="bi bi-arrow-clockwise"></i></button>
                            </div>
                            <span class="nt-ed-sep"></span>
                            <div class="nt-ed-group" role="group" aria-label="{{ __('Style') }}">
                                <button type="button" class="nt-ed-btn nt-ed-heading" data-cmd="p" title="{{ __('Normal text') }}">T</button>
                                <button type="button" class="nt-ed-btn nt-ed-heading" data-cmd="h1" title="{{ __('Heading 1') }}">H1</button>
                                <button type="button" class="nt-ed-btn nt-ed-heading" data-cmd="h2" title="{{ __('Heading 2') }}">H2</button>
                                <button type="button" class="nt-ed-btn nt-ed-heading" data-cmd="h3" title="{{ __('Heading 3') }}">H3</button>
                            </div>
                            <span class="nt-ed-sep"></span>
                            <div class="nt-ed-group">
                                <button type="button" class="nt-ed-btn" data-cmd="bold" title="{{ __('Bold') }} (Ctrl+B)"><i class="bi bi-type-bold"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="italic" title="{{ __('Italic') }} (Ctrl+I)"><i class="bi bi-type-italic"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="strike" title="{{ __('Strikethrough') }}"><i class="bi bi-type-strikethrough"></i></button>
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
                                <button type="button" class="nt-ed-btn" data-cmd="quote" title="{{ __('Quote') }}"><i class="bi bi-quote"></i></button>
                            </div>
                            <span class="nt-ed-sep"></span>
                            <div class="nt-ed-group">
                                <button type="button" class="nt-ed-btn" data-cmd="codeblock" title="{{ __('Code block') }}"><i class="bi bi-terminal"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="hr" title="{{ __('Divider') }}"><i class="bi bi-dash-lg"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="table" title="{{ __('Table') }}"><i class="bi bi-table"></i></button>
                                <button type="button" class="nt-ed-btn" data-cmd="clear" title="{{ __('Clear formatting') }}"><i class="bi bi-eraser"></i></button>
                            </div>

                            <span class="nt-ed-spacer"></span>
                            <button type="button" class="nt-ed-btn" data-cmd="zen" id="ntEdZen" title="{{ __('Zen mode') }} (Esc)"><i class="bi bi-arrows-fullscreen"></i></button>
                        </div>

                        {{-- Floating mini-toolbar on text selection (Medium-style) --}}
                        <div class="nt-bubble" id="ntBubble" hidden>
                            <button type="button" data-cmd="bold" title="{{ __('Bold') }}"><i class="bi bi-type-bold"></i></button>
                            <button type="button" data-cmd="italic" title="{{ __('Italic') }}"><i class="bi bi-type-italic"></i></button>
                            <button type="button" data-cmd="strike" title="{{ __('Strikethrough') }}"><i class="bi bi-type-strikethrough"></i></button>
                            <button type="button" data-cmd="code" title="{{ __('Code') }}"><i class="bi bi-code"></i></button>
                            <button type="button" data-cmd="h2" title="{{ __('Heading') }}">H</button>
                            <button type="button" data-cmd="link" title="{{ __('Link') }}"><i class="bi bi-link-45deg"></i></button>
                        </div>

                        <div class="nt-rich-wrap" id="ntRichWrap">
                            <div id="ntRich" class="nt-rich"
                                 contenteditable="true" dir="auto" spellcheck="true"
                                 data-placeholder="{{ __('Start writing… select text to format, type / for blocks, @ to mention, # to label') }}"
                                 aria-label="{{ __('Note body') }}"></div>
                            <div id="ntInitialHtml" hidden>{!! $initialHtml !!}</div>
                        </div>

                        {{-- Stored value: always Markdown (converted from the rich surface on submit).
                             No-JS fallback: shown via noscript style below. --}}
                        <textarea id="ntBody" name="content" hidden
                                  data-mentions-url="{{ route('notes.mentions') }}"
                                  class="@error('content') is-invalid @enderror">{{ old('content', $note->content) }}</textarea>
                        <noscript><style>#ntBody{display:block!important;width:100%;min-height:280px;visibility:visible!important}#ntRichWrap,#ntBubble{display:none!important}</style></noscript>

                        <div class="nt-ed-status">
                            <span><i class="bi bi-text-paragraph"></i> <span id="ntEdWords">0</span> {{ __('words') }}</span>
                            <span class="nt-ed-hide-sm"><i class="bi bi-hourglass-split"></i> <span id="ntEdRead">1</span> {{ __('min') }}</span>
                            <span class="nt-ed-spacer"></span>
                            <span class="nt-ed-draft" id="ntEdDraft"></span>
                            <button type="button" class="nt-ed-mini" id="ntMdCopy" title="{{ __('Copy as Markdown (for export)') }}">
                                <i class="bi bi-markdown"></i><span>{{ __('Markdown') }}</span>
                            </button>
                        </div>

                        <div class="nt-ed-mention" id="ntEdMention" role="listbox"></div>
                        <div class="nt-slash" id="ntSlash" role="listbox" hidden></div>
                    </div>
                    @error('content')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    <div id="ntMentionChips" class="nt-chipbar"></div>
                    <div class="nt-form-help mt-1">{{ __('Formatting is visual — everything is saved and exported as clean Markdown.') }}</div>
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
    // LEGACY GUARD: the form now uses the rich (#ntRich) surface — the old
    // Markdown-textarea engine below is neutered to avoid double-binding.
    if (document.getElementById('ntRich')) return;
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

{{-- ══════════════════════════════════════════════════════════════
     Rich (WYSIWYG) note editor.
     Surface = contenteditable HTML (what you see is what you get).
     Storage = clean Markdown in #ntBody (converted on every submit).
     No CDN, no build step — execCommand + a small HTML→Markdown
     converter. Bold/italic/lists/headings render live, exactly like
     the reader view, and export stays Markdown.
     ════════════════════════════════════════════════════════════ --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const shell = document.getElementById('ntEdShell');
    const rich = document.getElementById('ntRich');
    const hidden = document.getElementById('ntBody');
    const initBox = document.getElementById('ntInitialHtml');
    const mentionEl = document.getElementById('ntEdMention');
    const slashEl = document.getElementById('ntSlash');
    const bubble = document.getElementById('ntBubble');
    const form = document.getElementById('ntNoteForm');
    const titleInput = document.getElementById('ntTitle');
    const kindSel = document.getElementById('ntKind');
    const moodBlock = document.getElementById('ntMoodBlock');
    if (!shell || !rich || !hidden || !form) return;

    const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const PREVIEW_URL = @json(route('notes.preview'));
    const MENTIONS_URL = hidden.dataset.mentionsUrl || '';
    const TEMPLATES = @json($templates ?? []);
    const DRAFT_KEY = 'nt:rich:{{ $isEdit ? $note->id : 'new' }}';
    const SERVER_TS = {{ (int) ($isEdit && $note->updated_at ? $note->updated_at->getTimestampMs() : microtime(true) * 1000) }};

    const T = {
        url: '{{ __('https://…') }}',
        urlPrompt: '{{ __('Link URL:') }}',
        imgPrompt: '{{ __('Image URL:') }}',
        linkText: '{{ __('link text') }}',
        codeText: '{{ __('code') }}',
        emptyMd: '{{ __('Write something first — the note is empty.') }}',
        draftSaved: '{{ __('Draft saved') }}',
        restorable: '{{ __('An unsaved draft from') }}',
        copied: '{{ __('Copied!') }}',
        mdCopied: '{{ __('Markdown copied to clipboard.') }}',
        hdr: '{{ __('Column') }}',
    };

    const esc = (s) => String(s == null ? '' : s).replace(/[<>&"]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c]));

    /* ══ Boot: Markdown (server) → rich HTML ═══════════════════ */
    try {
        const seed = (initBox ? initBox.innerHTML : '').trim();
        rich.innerHTML = seed;
        if (initBox) initBox.remove();
    } catch (e) { /* keep empty */ }
    if (!rich.innerHTML.trim()) rich.innerHTML = '';

    function placeCaretAtEnd(el) {
        const r = document.createRange();
        r.selectNodeContents(el);
        r.collapse(false);
        const s = window.getSelection();
        s.removeAllRanges();
        s.addRange(r);
    }

    /* ══ HTML → Markdown (storage / export format) ══════════════ */
    function inlineMd(node) {
        let out = '';
        node.childNodes.forEach(ch => { out += nodeMd(ch); });
        return out;
    }
    function liMd(li) {
        const box = li.querySelector(':scope > input[type="checkbox"]');
        let text = '';
        li.childNodes.forEach(ch => {
            if (ch.nodeType === 1 && ch.tagName === 'INPUT') return;
            if (ch.nodeType === 1 && /^(UL|OL)$/.test(ch.tagName)) return;
            text += nodeMd(ch);
        });
        text = text.trim();
        let prefix;
        if (box) {
            prefix = '- [' + (box.checked ? 'x' : ' ') + '] ';
        } else if (li.parentNode && li.parentNode.tagName === 'OL') {
            prefix = '1. ';
        } else {
            prefix = '- ';
        }
        let out = prefix + text;
        li.childNodes.forEach(ch => {
            if (ch.nodeType === 1 && /^(UL|OL)$/.test(ch.tagName)) {
                out += '\n' + blockMd(ch).replace(/^/gm, '  ');
            }
        });
        return out;
    }
    function tableMd(table) {
        const rows = Array.from(table.querySelectorAll('tr'));
        if (!rows.length) return '';
        const cells = (tr) => Array.from(tr.querySelectorAll('th,td')).map(c => (c.innerText || '').replace(/\|/g, '\\|').trim().replace(/\s+/g, ' ') || ' ');
        const head = cells(rows[0]);
        let out = '| ' + head.join(' | ') + ' |\n';
        out += '| ' + head.map(() => '---').join(' | ') + ' |\n';
        rows.slice(1).forEach(tr => { out += '| ' + cells(tr).join(' | ') + ' |\n'; });
        return out;
    }
    function blockMd(node) {
        const tag = node.tagName;
        if (tag === 'H1') return '# ' + inlineMd(node).trim() + '\n\n';
        if (tag === 'H2') return '## ' + inlineMd(node).trim() + '\n\n';
        if (tag === 'H3') return '### ' + inlineMd(node).trim() + '\n\n';
        if (tag === 'H4') return '#### ' + inlineMd(node).trim() + '\n\n';
        if (tag === 'BLOCKQUOTE') {
            const t = node.innerText || '';
            return t.split('\n').map(l => '> ' + l).join('\n') + '\n\n';
        }
        if (tag === 'PRE') return '```\n' + (node.textContent || '').replace(/^\n+|\n+$/g, '') + '\n```\n\n';
        if (tag === 'HR') return '---\n\n';
        if (tag === 'UL' || tag === 'OL') {
            return Array.from(node.children).filter(c => c.tagName === 'LI').map(liMd).join('\n') + '\n\n';
        }
        if (tag === 'TABLE') return tableMd(node) + '\n';
        if (tag === 'P' || tag === 'DIV') {
            const t = inlineMd(node).trim();
            return t ? t + '\n\n' : '';
        }
        if (tag === 'LI') return liMd(node);
        if (tag === 'BR') return '\n';
        return nodeMd(node);
    }
    function nodeMd(node) {
        if (node.nodeType === 3) return (node.nodeValue || '').replace(/ /g, ' ');
        if (node.nodeType !== 1) return '';
        const tag = node.tagName;
        if (tag === 'STRONG' || tag === 'B') {
            const t = inlineMd(node);
            return t.trim() ? '**' + t + '**' : t;
        }
        if (tag === 'EM' || tag === 'I') {
            const t = inlineMd(node);
            return t.trim() ? '*' + t + '*' : t;
        }
        if (tag === 'S' || tag === 'DEL' || tag === 'STRIKE') {
            const t = inlineMd(node);
            return t.trim() ? '~~' + t + '~~' : t;
        }
        if (tag === 'U') return inlineMd(node);
        if (tag === 'CODE' && !(node.parentNode && node.parentNode.tagName === 'PRE')) {
            return '`' + (node.textContent || '') + '`';
        }
        if (tag === 'A') {
            const t = inlineMd(node).trim() || node.getAttribute('href') || '';
            const href = node.getAttribute('href') || '';
            return href ? '[' + t + '](' + href + ')' : t;
        }
        if (tag === 'IMG') {
            return '![' + (node.getAttribute('alt') || '') + '](' + (node.getAttribute('src') || '') + ')';
        }
        if (tag === 'BR') return '\n';
        if (/^(H1|H2|H3|H4|P|DIV|UL|OL|BLOCKQUOTE|PRE|HR|TABLE|LI)$/.test(tag)) return blockMd(node);
        return inlineMd(node);
    }
    function htmlToMarkdown(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html || '';
        let out = '';
        tmp.childNodes.forEach(n => {
            out += (n.nodeType === 1 && /^(H1|H2|H3|H4|P|DIV|UL|OL|BLOCKQUOTE|PRE|HR|TABLE)$/.test(n.tagName)) ? blockMd(n) : nodeMd(n);
        });
        return out.replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
    }
    function syncHidden() {
        hidden.value = htmlToMarkdown(rich.innerHTML);
        return hidden.value;
    }

    /* ══ Commands (live visual formatting) ═════════════════════ */
    function currentBlock() {
        const sel = window.getSelection();
        if (!sel.rangeCount) return null;
        let n = sel.getRangeAt(0).startContainer;
        if (n.nodeType === 3) n = n.parentNode;
        if (n === rich) return rich;
        while (n && n !== rich && !/^(P|DIV|H1|H2|H3|H4|LI|BLOCKQUOTE|PRE)$/.test(n.tagName)) n = n.parentNode;
        return n && n !== rich ? n : null;
    }
    function formatBlock(tag) {
        rich.focus();
        try {
            document.execCommand('formatBlock', false, tag);
        } catch (e) {
            try { document.execCommand('formatBlock', false, '<' + tag.toLowerCase() + '>'); } catch (e2) {}
        }
        onChange();
    }
    function insertHtml(html) {
        rich.focus();
        try { document.execCommand('insertHTML', false, html); }
        catch (e) {
            const r = window.getSelection().getRangeAt(0);
            const t = document.createElement('template');
            t.innerHTML = html.trim();
            r.deleteContents();
            r.insertNode(t.content.cloneNode(true));
        }
        onChange();
    }
    function makeTodo() {
        const blk = currentBlock();
        if (blk && blk.tagName === 'LI' && blk.parentNode.classList.contains('nt-todo')) return;
        insertHtml('<ul class="nt-todo"><li><input type="checkbox"> </li></ul><p><br></p>');
    }
    function makeCodeBlock() {
        const blk = currentBlock();
        if (blk && blk.tagName === 'PRE') { formatBlock('P'); return; }
        const sel = window.getSelection();
        const text = (!sel.isCollapsed && rich.contains(sel.anchorNode)) ? sel.toString() : T.codeText;
        insertHtml('<pre><code>' + esc(text) + '</code></pre><p><br></p>');
    }
    function makeLink() {
        const sel = window.getSelection();
        const hasSel = !sel.isCollapsed && rich.contains(sel.anchorNode) && sel.toString().trim() !== '';
        let url = prompt(T.urlPrompt, 'https://');
        if (url === null) return;
        url = url.trim();
        if (!url) return;
        if (!/^(https?:\/\/|mailto:|tel:|#)/i.test(url)) url = 'https://' + url;
        rich.focus();
        if (hasSel) {
            try { document.execCommand('createLink', false, url); } catch (e) {}
        } else {
            insertHtml('<a href="' + esc(url) + '">' + esc(url) + '</a>&nbsp;');
        }
        onChange();
    }
    function makeImage() {
        let url = prompt(T.imgPrompt, 'https://');
        if (url === null) return;
        url = url.trim();
        if (!url) return;
        insertHtml('<p><img src="' + esc(url) + '" alt=""></p><p><br></p>');
    }

    const CMDS = {
        bold: () => document.execCommand('bold'),
        italic: () => document.execCommand('italic'),
        strike: () => document.execCommand('strikeThrough'),
        code: () => {
            const sel = window.getSelection();
            if (!sel.isCollapsed && rich.contains(sel.anchorNode)) {
                insertHtml('<code>' + esc(sel.toString()) + '</code>');
            } else {
                insertHtml('<code>' + esc(T.codeText) + '</code>&nbsp;');
            }
        },
        p: () => formatBlock('P'),
        h1: () => formatBlock('H1'),
        h2: () => formatBlock('H2'),
        h3: () => formatBlock('H3'),
        ul: () => document.execCommand('insertUnorderedList'),
        ol: () => document.execCommand('insertOrderedList'),
        task: () => makeTodo(),
        quote: () => {
            const blk = currentBlock();
            formatBlock(blk && blk.tagName === 'BLOCKQUOTE' ? 'P' : 'BLOCKQUOTE');
        },
        codeblock: () => makeCodeBlock(),
        link: () => makeLink(),
        image: () => makeImage(),
        hr: () => insertHtml('<hr><p><br></p>'),
        table: () => insertHtml('<table><tbody><tr><th>' + esc(T.hdr) + ' 1</th><th>' + esc(T.hdr) + ' 2</th></tr><tr><td><br></td><td><br></td></tr></tbody></table><p><br></p>'),
        clear: () => { document.execCommand('removeFormat'); formatBlock('P'); },
        undo: () => document.execCommand('undo'),
        redo: () => document.execCommand('redo'),
        zen: () => toggleZen(),
    };

    document.querySelectorAll('#ntToolbar [data-cmd], #ntBubble [data-cmd]').forEach(btn => {
        btn.addEventListener('mousedown', e => e.preventDefault());
        btn.addEventListener('click', e => {
            e.preventDefault();
            const fn = CMDS[btn.dataset.cmd];
            if (fn) { rich.focus(); fn(); refreshToolbar(); }
            rich.focus();
        });
    });

    function refreshToolbar() {
        let block = '';
        try { block = (document.queryCommandValue('formatBlock') || '').toLowerCase().replace(/[<>]/g, ''); } catch (e) {}
        document.querySelectorAll('#ntToolbar [data-cmd]').forEach(b => {
            const c = b.dataset.cmd;
            let on = false;
            try {
                if (c === 'bold') on = document.queryCommandState('bold');
                else if (c === 'italic') on = document.queryCommandState('italic');
                else if (c === 'strike') on = document.queryCommandState('strikeThrough');
                else if (c === 'ul') on = document.queryCommandState('insertUnorderedList');
                else if (c === 'ol') on = document.queryCommandState('insertOrderedList');
                else if (['p', 'h1', 'h2', 'h3'].includes(c)) on = (block === c);
            } catch (e) {}
            b.classList.toggle('is-on', !!on);
        });
    }
    document.addEventListener('selectionchange', () => {
        if (document.activeElement === rich || (rich.contains(document.activeElement))) refreshToolbar();
        positionBubble();
    });

    /* ══ Floating bubble on selection ═══════════════════════════ */
    let bubbleTimer = null;
    function caretRect() {
        const sel = window.getSelection();
        if (!sel.rangeCount) return null;
        const r = sel.getRangeAt(0).cloneRange();
        const rects = r.getClientRects();
        if (rects.length) return rects[0];
        return r.getBoundingClientRect();
    }
    function positionBubble() {
        clearTimeout(bubbleTimer);
        bubbleTimer = setTimeout(() => {
            const sel = window.getSelection();
            if (!sel.rangeCount || sel.isCollapsed || !rich.contains(sel.anchorNode)) {
                bubble.hidden = true;
                return;
            }
            const rc = caretRect();
            const sr = shell.getBoundingClientRect();
            if (!rc) { bubble.hidden = true; return; }
            bubble.hidden = false;
            let top = rc.top - sr.top - bubble.offsetHeight - 10 + shell.scrollTop;
            let left = rc.left - sr.left + (rc.width / 2) - (bubble.offsetWidth / 2);
            left = Math.max(8, Math.min(left, sr.width - bubble.offsetWidth - 8));
            if (top < 46) top = rc.bottom - sr.top + 10;
            bubble.style.top = top + 'px';
            bubble.style.left = left + 'px';
        }, 60);
    }
    rich.addEventListener('mouseup', positionBubble);
    rich.addEventListener('keyup', positionBubble);

    /* ══ Slash (/) block menu ════════════════════════════════════ */
    const SLASH_ITEMS = [
        { k: 'h1', icon: 'bi-type-h1', label: @json(__('Heading 1')) },
        { k: 'h2', icon: 'bi-type-h2', label: @json(__('Heading 2')) },
        { k: 'h3', icon: 'bi-type-h3', label: @json(__('Heading 3')) },
        { k: 'ul', icon: 'bi-list-ul', label: @json(__('Bulleted list')) },
        { k: 'ol', icon: 'bi-list-ol', label: @json(__('Numbered list')) },
        { k: 'task', icon: 'bi-check2-square', label: @json(__('To-do')) },
        { k: 'quote', icon: 'bi-quote', label: @json(__('Quote')) },
        { k: 'codeblock', icon: 'bi-terminal', label: @json(__('Code block')) },
        { k: 'hr', icon: 'bi-dash-lg', label: @json(__('Divider')) },
        { k: 'table', icon: 'bi-table', label: @json(__('Table')) },
    ];
    let slashActive = 0, slashList = [];
    function slashToken() {
        const sel = window.getSelection();
        if (!sel.rangeCount || !sel.isCollapsed) return null;
        const blk = currentBlock();
        if (!blk) return null;
        if (/^(UL|OL)$/.test(blk.tagName)) return null;
        const text = (blk.textContent || '');
        const m = text.match(/^\/([\p{L}\p{N}]*)$/u);
        if (!m) return null;
        return { blk, query: m[1] };
    }
    function renderSlash() {
        const tok = slashToken();
        if (!tok) { slashEl.hidden = true; return; }
        slashList = SLASH_ITEMS.filter(i => !tok.query || i.label.toLowerCase().includes(tok.query.toLowerCase()) || i.k.includes(tok.query.toLowerCase()));
        if (!slashList.length) { slashEl.hidden = true; return; }
        slashActive = Math.min(slashActive, slashList.length - 1);
        slashEl.innerHTML = slashList.map((i, x) =>
            '<div class="nt-slash-item' + (x === slashActive ? ' is-active' : '') + '" data-i="' + x + '"><i class="bi ' + i.icon + '"></i><span>' + esc(i.label) + '</span></div>'
        ).join('');
        const rc = caretRect(), sr = shell.getBoundingClientRect();
        slashEl.hidden = false;
        if (rc) {
            slashEl.style.top = (rc.bottom - sr.top + 6) + 'px';
            slashEl.style.left = Math.max(8, Math.min(rc.left - sr.left, sr.width - 240)) + 'px';
        }
        slashEl.querySelectorAll('.nt-slash-item').forEach(el => {
            el.addEventListener('mousedown', e => { e.preventDefault(); pickSlash(+el.dataset.i); });
        });
    }
    function pickSlash(i) {
        const item = slashList[i];
        const tok = slashToken();
        slashEl.hidden = true;
        if (!item || !tok) return;
        tok.blk.textContent = '';
        placeCaretAtEnd(tok.blk);
        const fn = CMDS[item.k];
        if (fn) fn();
        refreshToolbar();
    }

    /* ══ @person / #label autocomplete (contenteditable) ═════════ */
    let mentionResults = [], mentionActive = 0, mentionToken = null, mentionTimer = null;
    function activeToken() {
        const sel = window.getSelection();
        if (!sel.rangeCount || !sel.isCollapsed) return null;
        const node = sel.getRangeAt(0).startContainer;
        if (!node || node.nodeType !== 3) return null;
        const before = node.textContent.slice(0, sel.getRangeAt(0).startOffset);
        const m = before.match(/([@#])([\p{L}\p{N}_][\p{L}\p{N}_\-]*)$/u);
        if (!m) return null;
        return { sign: m[1], query: m[2], node, start: sel.getRangeAt(0).startOffset - m[0].length, end: sel.getRangeAt(0).startOffset };
    }
    function closeMention() {
        mentionEl.classList.remove('is-open');
        mentionEl.innerHTML = '';
        mentionResults = [];
        mentionToken = null;
    }
    function openMention(list, token) {
        mentionResults = list;
        mentionToken = token;
        if (!list.length) return closeMention();
        mentionEl.innerHTML = list.map((r, i) =>
            '<div class="nt-ed-mention-item' + (i === 0 ? ' is-active' : '') + '" data-i="' + i + '"><i class="bi ' + esc(r.icon || (token.sign === '#' ? 'bi-tag' : 'bi-dot')) + '"></i><span>' + esc(r.name) + '</span><span class="nt-ed-mention-hint">' + esc(r.hint || r.type || '') + '</span></div>'
        ).join('');
        const rc = caretRect(), sr = shell.getBoundingClientRect();
        mentionEl.classList.add('is-open');
        if (rc) {
            mentionEl.style.top = (rc.bottom - sr.top + 6) + 'px';
            mentionEl.style.left = Math.max(8, Math.min(rc.left - sr.left, sr.width - 260)) + 'px';
        }
        mentionActive = 0;
        mentionEl.querySelectorAll('.nt-ed-mention-item').forEach(el => {
            el.addEventListener('mousedown', e => { e.preventDefault(); chooseMention(+el.dataset.i); });
        });
    }
    function chooseMention(i) {
        const r = mentionResults[i];
        const tok = mentionToken;
        if (!r || !tok) return closeMention();
        const range = document.createRange();
        range.setStart(tok.node, tok.start);
        range.setEnd(tok.node, tok.end);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        document.execCommand('insertText', false, tok.sign + r.name + ' ');
        closeMention();
        onChange();
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
                    const exact = list.find(x => (x.name || '').toLowerCase() === token.query.toLowerCase());
                    if (!exact && token.query.trim()) {
                        list = [{ name: token.query, icon: token.sign === '#' ? 'bi-plus-circle' : 'bi-person-plus', hint: 'new' }].concat(list);
                    }
                    openMention(list, token);
                })
                .catch(() => closeMention());
        }, 160);
    }
    function mentionKey(e) {
        if (!mentionEl.classList.contains('is-open')) return false;
        if (e.key === 'ArrowDown') { e.preventDefault(); mentionActive = (mentionActive + 1) % mentionResults.length; openMention(mentionResults, mentionToken); mentionActive = Math.min(mentionActive, mentionResults.length - 1); return true; }
        if (e.key === 'ArrowUp') { e.preventDefault(); mentionActive = (mentionActive - 1 + mentionResults.length) % mentionResults.length; return true; }
        if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); chooseMention(mentionActive); return true; }
        if (e.key === 'Escape') { e.preventDefault(); closeMention(); return true; }
        return false;
    }

    /* ══ Paste: keep formatting, drop junk ═══════════════════════ */
    rich.addEventListener('paste', function (e) {
        const html = (e.clipboardData || {}).getData ? e.clipboardData.getData('text/html') : '';
        const text = (e.clipboardData || {}).getData ? e.clipboardData.getData('text/plain') : '';
        if (!html || /urn:schemas-microsoft-com|w:word/i.test(html)) return; // plain-text path below
        e.preventDefault();
        try {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            doc.querySelectorAll('script,style,meta,link').forEach(n => n.remove());
            doc.querySelectorAll('*').forEach(n => {
                Array.from(n.attributes || []).forEach(a => {
                    if (/^on/i.test(a.name) || a.name === 'style' || a.name === 'class' && /Mso/i.test(a.value)) n.removeAttribute(a.name);
                });
            });
            insertHtml(doc.body.innerHTML);
        } catch (err) {
            document.execCommand('insertText', false, text);
            onChange();
        }
    });

    /* ══ Keys: shortcuts, todo continuation, code Enter ══════════ */
    rich.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (!slashEl.hidden) { slashEl.hidden = true; return; }
            if (mentionEl.classList.contains('is-open')) { closeMention(); return; }
            if (shell.classList.contains('is-zen')) { toggleZen(false); return; }
        }
        if (mentionKey(e)) return;
        if (!slashEl.hidden && (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === 'Tab')) {
            e.preventDefault();
            if (e.key === 'ArrowDown') { slashActive = (slashActive + 1) % slashList.length; renderSlash(); }
            else if (e.key === 'ArrowUp') { slashActive = (slashActive - 1 + slashList.length) % slashList.length; renderSlash(); }
            else pickSlash(slashActive);
            return;
        }
        const mod = e.ctrlKey || e.metaKey;
        if (mod && !e.shiftKey && e.key.toLowerCase() === 'b') { e.preventDefault(); document.execCommand('bold'); refreshToolbar(); return; }
        if (mod && !e.shiftKey && e.key.toLowerCase() === 'i') { e.preventDefault(); document.execCommand('italic'); refreshToolbar(); return; }
        if (mod && !e.shiftKey && e.key.toLowerCase() === 'k') { e.preventDefault(); makeLink(); return; }
        if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); saveNote(); return; }

        if (e.key === 'Tab' && !mod) {
            const blk = currentBlock();
            if (blk && blk.tagName === 'LI') {
                e.preventDefault();
                document.execCommand(e.shiftKey ? 'outdent' : 'indent');
                return;
            }
        }
        // PRE: plain newline instead of nested divs
        const inPre = (function () {
            let n = window.getSelection().rangeCount ? window.getSelection().getRangeAt(0).startContainer : null;
            if (n && n.nodeType === 3) n = n.parentNode;
            while (n && n !== rich) { if (n.tagName === 'PRE') return true; n = n.parentNode; }
            return false;
        })();
        if (e.key === 'Enter' && inPre && !mod) {
            e.preventDefault();
            document.execCommand('insertText', false, '\n');
            return;
        }
        // Todo: Enter continues, empty item exits to paragraph
        if (e.key === 'Enter' && !mod && !e.shiftKey) {
            const blk = currentBlock();
            if (blk && blk.tagName === 'LI' && blk.parentNode.classList.contains('nt-todo')) {
                const txt = blk.textContent.trim();
                if (txt === '') {
                    e.preventDefault();
                    const p = document.createElement('p');
                    p.innerHTML = '<br>';
                    blk.parentNode.after(p);
                    blk.remove();
                    placeCaretAtEnd(p);
                    onChange();
                } else {
                    e.preventDefault();
                    const li = document.createElement('li');
                    li.innerHTML = '<input type="checkbox"> ';
                    blk.after(li);
                    placeCaretAtEnd(li);
                    onChange();
                }
                return;
            }
        }
    });

    /* ══ Counters, chips, drafts ═════════════════════════════════ */
    const wordsEl = document.getElementById('ntEdWords');
    const readEl = document.getElementById('ntEdRead');
    const draftEl = document.getElementById('ntEdDraft');
    const draftBar = document.getElementById('ntDraftBar');
    const draftText = document.getElementById('ntDraftText');
    const chips = document.getElementById('ntMentionChips');
    let draftTimer = null, draftLoaded = null;

    function plainText() { return rich.innerText || rich.textContent || ''; }
    function updateCounters() {
        const text = plainText();
        const words = text.trim() ? (text.trim().match(/\S+/g) || []).length : 0;
        if (wordsEl) wordsEl.textContent = words;
        if (readEl) readEl.textContent = Math.max(1, Math.ceil(words / 200));
    }
    function renderChips() {
        if (!chips) return;
        const found = [];
        (plainText().match(/[@#][\p{L}\p{N}_][\p{L}\p{N}_\-]*/gu) || []).forEach(tok => {
            if (!found.some(f => f.toLowerCase() === tok.toLowerCase())) found.push(tok);
        });
        chips.innerHTML = found.slice(0, 24).map(tok => {
            const label = tok.replace(/[<>&]/g, '');
            return tok[0] === '#'
                ? '<span class="nt-chip" style="background:#dbeafe;border-color:#bfdbfe;color:#1d4ed8"><i class="bi bi-tag"></i>' + esc(label) + '</span>'
                : '<span class="nt-chip" style="background:#ede9fe;border-color:#ddd6fe;color:#6d28d9"><i class="bi bi-at"></i>' + esc(label) + '</span>';
        }).join('');
    }
    function readDraft() {
        try { return JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) { return null; }
    }
    function writeDraft() {
        try {
            syncHidden();
            localStorage.setItem(DRAFT_KEY, JSON.stringify({ html: rich.innerHTML, title: titleInput ? titleInput.value : '', ts: Date.now() }));
            if (draftEl) draftEl.textContent = T.draftSaved + ' · ' + new Date().toLocaleTimeString();
        } catch (e) {}
    }
    function checkDraft() {
        const d = readDraft();
        if (!d || typeof d.html !== 'string') return;
        if (Number(d.ts) <= Number(SERVER_TS)) return;
        if ((d.html || '').trim() === (rich.innerHTML || '').trim()) return;
        draftLoaded = d;
        if (draftText) draftText.textContent = T.restorable + ' ' + new Date(Number(d.ts)).toLocaleString() + '.';
        if (draftBar) draftBar.hidden = false;
    }
    function scheduleDraft() { clearTimeout(draftTimer); draftTimer = setTimeout(writeDraft, 1100); }

    const drRestore = document.getElementById('ntDraftRestore');
    if (drRestore) drRestore.addEventListener('click', function () {
        const d = draftLoaded || readDraft();
        if (!d) return;
        if (titleInput && typeof d.title === 'string') titleInput.value = d.title;
        rich.innerHTML = d.html || '';
        if (draftBar) draftBar.hidden = true;
        onChange();
        rich.focus();
    });
    const drDiscard = document.getElementById('ntDraftDiscard');
    if (drDiscard) drDiscard.addEventListener('click', function () {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
        if (draftBar) draftBar.hidden = true;
    });

    rich.addEventListener('input', () => {
        dirty = true;
        renderSlash();
        refreshMentions();
        onChange();
    });
    rich.addEventListener('keyup', refreshToolbar);
    rich.addEventListener('mouseup', refreshToolbar);
    rich.addEventListener('blur', () => setTimeout(() => { closeMention(); slashEl.hidden = true; }, 140));

    function onChange() {
        updateCounters();
        renderChips();
        scheduleDraft();
        positionBubble();
    }

    /* ══ Copy as Markdown (export) ═══════════════════════════════ */
    const mdCopy = document.getElementById('ntMdCopy');
    if (mdCopy) mdCopy.addEventListener('click', async function () {
        const md = syncHidden();
        const done = () => {
            mdCopy.classList.add('is-ok');
            const s = mdCopy.querySelector('span');
            const old = s ? s.textContent : '';
            if (s) s.textContent = T.copied;
            setTimeout(() => { mdCopy.classList.remove('is-ok'); if (s) s.textContent = old; }, 1600);
        };
        try { await navigator.clipboard.writeText(md); done(); }
        catch (e) {
            const ta = document.createElement('textarea');
            ta.value = md;
            ta.style.cssText = 'position:fixed;top:-9999px;opacity:0;';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (e2) {}
            document.body.removeChild(ta);
        }
    });

    /* ══ Templates (Markdown on server → rich HTML) ══════════════ */
    const hintEl = document.getElementById('ntTemplateHint');
    function mdFallback(md) {
        return esc(md).split(/\n{2,}/).map(chunk => {
            const lines = chunk.split('\n');
            if (/^#{1,3}\s/.test(lines[0])) {
                const lvl = lines[0].match(/^(#{1,3})/)[1].length;
                return '<h' + lvl + '>' + esc(lines[0].replace(/^#{1,3}\s*/, '')) + '</h' + lvl + '>';
            }
            if (lines.every(l => /^\s*([-*+]|\d+[.)])\s/.test(l) || l.trim() === '')) {
                return '<ul><li>' + lines.filter(l => l.trim()).map(l => esc(l.replace(/^\s*([-*+]|\d+[.)])\s*/, '')) || '<br>').join('</li><li>') + '</li></ul>';
            }
            return '<p>' + lines.map(esc).join('<br>') + '</p>';
        }).join('');
    }
    function applyTemplate(kind, force) {
        const tpl = TEMPLATES[kind];
        if (!tpl) return;
        const body = tpl.body || '';
        const currentMd = htmlToMarkdown(rich.innerHTML);
        const untouched = currentMd.trim() === '' || Object.keys(TEMPLATES).some(k => ((TEMPLATES[k].body || '').trim() === currentMd.trim()));
        const run = () => {
            fetch(PREVIEW_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ content: body }),
            })
                .then(r => r.ok ? r.json() : Promise.reject())
                .then(j => { rich.innerHTML = (j.html || '').trim() ? j.html : mdFallback(body); })
                .catch(() => { rich.innerHTML = mdFallback(body); })
                .finally(() => {
                    if (hintEl) hintEl.textContent = tpl.hint || '';
                    document.querySelectorAll('[data-tpl]').forEach(b => b.classList.toggle('is-on', b.dataset.tpl === kind));
                    onChange();
                    rich.focus();
                });
        };
        if (!untouched && !force) {
            confirmSwal(@json(__('Replace the current text with this template?'))).then(ok => { if (ok) run(); });
            return;
        }
        run();
    }
    document.querySelectorAll('[data-tpl]').forEach(btn => {
        btn.addEventListener('click', function () {
            if (kindSel) kindSel.value = this.dataset.tpl;
            applyTemplate(this.dataset.tpl);
            toggleMood();
        });
    });
    if (kindSel) kindSel.addEventListener('change', function () {
        toggleMood();
        applyTemplate(this.value);
    });
    function toggleMood() {
        if (!moodBlock) return;
        moodBlock.hidden = !kindSel || kindSel.value !== 'daily';
    }
    toggleMood();

    /* ══ Mood / energy ═══════════════════════════════════════════ */
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

    /* ══ Zen ═════════════════════════════════════════════════════ */
    function toggleZen(force) {
        const on = typeof force === 'boolean' ? force : !shell.classList.contains('is-zen');
        shell.classList.toggle('is-zen', on);
        document.body.style.overflow = on ? 'hidden' : '';
        rich.focus();
    }

    /* ══ Save / delete / dirty guard ════════════════════════════ */
    function saveNote() {
        syncHidden();
        writeDraft();
        form.requestSubmit ? form.requestSubmit() : form.submit();
    }
    const saveBtn = document.getElementById('ntSave');
    form.addEventListener('submit', function (e) {
        const md = syncHidden();
        if (md.trim() === '') {
            e.preventDefault();
            shell.classList.add('is-empty-error');
            setTimeout(() => shell.classList.remove('is-empty-error'), 1200);
            rich.focus();
            if (typeof alertSwal === 'function') alertSwal(T.emptyMd, null, 'warning');
            return;
        }
        if (saveBtn) { saveBtn.disabled = true; }
        try { localStorage.removeItem(DRAFT_KEY); } catch (err) {}
        toggleZen(false);
    });
    const del = document.getElementById('ntDelete');
    if (del) del.addEventListener('click', function () {
        confirmSwal(document.getElementById('ntDeleteForm'), @json(__('Delete this note permanently?')), { isDelete: true });
    });

    let dirty = false;
    const initialHtml = rich.innerHTML;
    const initialTitle = titleInput ? titleInput.value : '';
    if (titleInput) titleInput.addEventListener('input', () => { dirty = true; });
    window.addEventListener('beforeunload', function (e) {
        if (!dirty) return;
        e.preventDefault();
        e.returnValue = '';
    });

    /* ══ Boot ════════════════════════════════════════════════════ */
    updateCounters();
    renderChips();
    checkDraft();
    refreshToolbar();
});
</script>
@endpush