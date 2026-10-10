@extends('layouts.app')

@section('title', $note->title)

@include('notes._styles')

@push('styles')
<style>
    .nt-show-pill {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 10px; border-radius: 999px; font-size: .72rem; font-weight: 700;
        text-decoration: none;
    }
    .nt-show-pillrow { display: flex; flex-wrap: wrap; gap: 5px; padding: 14px 22px 0; }
    .nt-meta {
        display: flex; align-items: flex-start; gap: 8px;
        padding: 7px 0; font-size: .78rem; color: var(--gray-600);
    }
    .nt-meta + .nt-meta { border-top: 1px solid var(--gray-100); }
    .nt-meta i { color: var(--gray-400); margin-top: 2px; }
    .nt-meta strong { color: var(--gray-900); font-weight: 700; }
    .nt-subject-summary { font-size: .76rem; line-height: 1.85; color: var(--gray-700); }
    .cu-action-link {
        display: flex; align-items: center; gap: 7px; padding: 7px 10px;
        border-radius: 8px; font-size: .78rem; font-weight: 600; text-decoration: none;
        border: 1px solid transparent; cursor: pointer; transition: all .15s; background: #fff;
    }
    .cu-action-link.copy { border-color: var(--gray-200); color: var(--gray-700); }
    .cu-action-link.copy:hover { border-color: var(--primary-500); color: var(--primary-700); }
    .cu-action-link.del { border-color: #fecaca; color: #dc2626; background: #fef2f2; }
    .cu-action-link.del:hover { border-color: #dc2626; background: #fee2e2; }
    .cu-fav-btn {
        display: flex; align-items: center; gap: 7px; padding: 7px 10px;
        border-radius: 8px; font-size: .78rem; font-weight: 600;
        border: 1px solid var(--gray-200); color: var(--gray-600); background: #fff;
        cursor: pointer; transition: all .15s; width: 100%;
    }
    .cu-fav-btn.active { border-color: #f59e0b; color: #b45309; background: #fef3c7; }
    .cu-fav-btn.active i { color: #f59e0b; }
    .cu-fav-btn i { color: var(--gray-400); }
</style>
@endpush

@section('content')
<div class="nt-page-head">
    <a href="{{ route('notes.index') }}" class="nt-page-back" title="{{ __('Back to notes') }}">
        <i class="bi bi-arrow-right"></i>
    </a>
    <span class="nt-head-ico"><i class="bi {{ note_kind_icon($note->kind) }}"></i></span>
    <div>
        <h1 class="nt-head-title">{{ $note->title }}</h1>
        <p class="nt-head-sub">
            {{ note_kind_label($note->kind) }} · {{ __('created') }} {{ $note->created_at->diffForHumans() }}
            @if ($note->updated_at->ne($note->created_at))
                · {{ __('edited') }} {{ $note->updated_at->diffForHumans() }}
            @endif
        </p>
    </div>
    <a href="{{ route('notes.edit', $note) }}" class="btn btn-brand ms-auto">
        <i class="bi bi-pencil"></i>{{ __('Edit') }}
    </a>
</div>

@if (session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2" style="font-size:.82rem;">
        <i class="bi bi-check-circle"></i>{{ session('success') }}
    </div>
@endif

<div class="nt-editor-grid">
    <div>
        <div class="nt-panel">
            @if ($note->labels->count())
                <div class="nt-show-pillrow">
                    @foreach ($note->labels as $label)
                        <a class="nt-show-pill" href="{{ route('notes.index', ['label' => $label->name]) }}"
                           style="background:{{ $label->color }}1a;color:{{ $label->color }};">
                            <i class="bi bi-tag"></i>{{ $label->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Rendered Markdown (escaped, never raw) with @/# tokens linked --}}
            <div class="nt-prose" id="note-content" style="padding:16px 22px;">
                {!! $note->renderedBody() !!}
            </div>

            <div class="nt-stats-bar">
                <span><i class="bi bi-file-text"></i> {{ app_num($note->word_count) }} {{ __('words') }}</span>
                <span><i class="bi bi-hourglass-split"></i> {{ app_num($note->reading_minutes) }} {{ __('min read') }}</span>
                <span><i class="bi bi-clock"></i> {{ $note->created_at->format('M d, Y g:i A') }}</span>
                @if ($note->effectiveDate())
                    <span><i class="bi bi-calendar-event"></i> {{ $note->formatted_date }}</span>
                @endif
            </div>
        </div>

        {{-- AI extraction --}}
        <div class="nt-panel" id="ntAiCard">
            <div class="nt-panel-head">
                <i class="bi bi-stars"></i>{{ __('AI extraction') }}
                <button type="button" class="btn btn-sm btn-brand ms-auto" id="ntAiExtractBtn" style="font-size:.7rem;">
                    <i class="bi bi-magic"></i>{{ __('Extract tasks & decisions') }}
                </button>
            </div>
            <div class="nt-panel-body" id="ntAiResult">
                <p class="mb-0 nt-form-help">{{ __('Let AI read this note and propose tasks, decisions and a summary. You confirm before anything is created.') }}</p>
            </div>
        </div>
    </div>

    <aside class="nt-editor-side">
        <div class="nt-panel">
            <div class="nt-panel-head"><i class="bi bi-sliders"></i>{{ __('Details') }}</div>
            <div class="nt-panel-body">
                <div class="nt-meta">
                    <i class="bi bi-file-text"></i>
                    <span><strong>{{ app_num($note->word_count) }}</strong> {{ __('words') }}</span>
                </div>
                <div class="nt-meta">
                    <i class="bi bi-calendar3"></i>
                    <span>{{ __('Created') }} <strong>{{ $note->created_at->format('M d, Y') }}</strong></span>
                </div>
                @if ($note->updated_at->ne($note->created_at))
                    <div class="nt-meta">
                        <i class="bi bi-pencil"></i>
                        <span>{{ __('Modified') }} <strong>{{ $note->updated_at->format('M d, Y') }}</strong></span>
                    </div>
                @endif
                @if ($note->effectiveDate())
                    <div class="nt-meta">
                        <i class="bi bi-calendar-event"></i>
                        <span>{{ $note->formatted_date }}@if ($note->formatted_time) · {{ $note->formatted_time }}@endif</span>
                    </div>
                @endif
                @if ($note->mood || $note->energy)
                    <div class="nt-meta">
                        <i class="bi bi-emoji-smile"></i>
                        <span>
                            {{ __('Mood') }} {{ $note->mood ? note_mood_icon($note->mood)['emoji'] : '—' }} ·
                            {{ __('Energy') }} {{ $note->energy ? note_mood_icon($note->energy)['emoji'] : '—' }}
                        </span>
                    </div>
                @endif
                @if ($note->notebook)
                    <div class="nt-meta">
                        <i class="bi bi-folder"></i>
                        <span><a href="{{ route('notes.index', ['notebook' => $note->notebook_id]) }}"
                                  style="color:var(--primary-700);font-weight:700;text-decoration:none;">{{ $note->notebook->title }}</a></span>
                    </div>
                @endif
                @if ($note->tags)
                    <div class="nt-meta">
                        <i class="bi bi-tags"></i>
                        <span>{{ implode('، ', $note->tags) }}</span>
                    </div>
                @endif
            </div>
        </div>

        @if (($subjects ?? collect())->count())
            <div class="nt-panel">
                <div class="nt-panel-head"><i class="bi bi-people"></i>{{ __('People & topics') }}</div>
                <div class="nt-panel-body">
                    @foreach ($subjects as $subject)
                        <div class="mb-3" id="ntSubject{{ $subject->id }}">
                            <div class="d-flex align-items-center gap-2">
                                <strong style="font-size:.8rem;">{{ $subject->name }}</strong>
                                <span class="nt-badge nt-badge-kind">{{ $subject->type }}</span>
                                <button type="button" class="btn btn-sm btn-outline ms-auto nt-subject-refresh"
                                        data-id="{{ $subject->id }}" style="font-size:.65rem;padding:2px 7px;">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </div>
                            <div class="nt-subject-summary mt-1">
                                @if (! empty($subject->meta['ai_summary']))
                                    {!! nl2br(e($subject->meta['ai_summary'])) !!}
                                    <div style="font-size:.65rem;color:var(--gray-400);">
                                        {{ app_num($subject->meta['ai_summary_note_count'] ?? 0) }} {{ __('notes') }}
                                    </div>
                                @else
                                    <span class="nt-form-help">{{ __('No AI summary yet — refresh to generate one.') }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @include('notes.partials._linked')

        @if (! empty($backlinks) && count($backlinks))
            @include('notes.partials._backlinks', ['notes' => $backlinks])
        @endif

        <div class="nt-panel">
            <div class="nt-panel-head"><i class="bi bi-lightning"></i>{{ __('Actions') }}</div>
            <div class="nt-panel-body d-grid gap-2">
                <button type="button" class="cu-fav-btn {{ $note->is_favorite ? 'active' : '' }}" id="fav-btn" data-note-id="{{ $note->id }}">
                    <i class="bi bi-star-fill"></i>
                    <span id="fav-label">{{ $note->is_favorite ? __('Unfavourite') : __('Favourite') }}</span>
                </button>
                <button type="button" class="cu-action-link copy" onclick="duplicateNote({{ $note->id }})">
                    <i class="bi bi-files"></i>{{ __('Duplicate') }}
                </button>
                <button type="button" class="cu-action-link copy" onclick="copyContent()" style="border-color:#bfdbfe;color:#2563eb;background:#eff6ff;">
                    <i class="bi bi-clipboard"></i>{{ __('Copy as text') }}
                </button>
                <form action="{{ route('notes.destroy', $note->id) }}" method="POST" id="deleteForm">
                    @csrf @method('DELETE')
                    <button type="button" class="cu-action-link del w-100" onclick="confirmDelete()">
                        <i class="bi bi-trash"></i>{{ __('Delete') }}
                    </button>
                </form>
            </div>
        </div>
    </aside>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const favBtn   = document.getElementById('fav-btn');
    const favLabel = document.getElementById('fav-label');

    favBtn.addEventListener('click', function () {
        const noteId = this.dataset.noteId;
        fetch(`/notes/${noteId}/toggle-favorite`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                favBtn.classList.toggle('active', data.is_favorite);
                favLabel.textContent = data.is_favorite ? '{{ __('Unfavourite') }}' : '{{ __('Favourite') }}';
            }
        });
    });
});

function duplicateNote(noteId) {
    fetch(`/notes/${noteId}/duplicate`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    })
    .then(r => {
        window.location.href = '{{ route("notes.index") }}';
    })
    .catch(err => console.error('Duplicate failed:', err));
}

function copyContent() {
    const text = document.getElementById('note-content').innerText;
    const btn = document.querySelector('[onclick="copyContent()"]');
    const orig = btn.innerHTML;

    function showCopied() {
        btn.innerHTML = '<i class="bi bi-check-lg"></i> {{ __('Copied!') }}';
        setTimeout(() => btn.innerHTML = orig, 2000);
    }

    // Modern clipboard API (HTTPS / localhost)
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(showCopied).catch(() => fallbackCopy(text, showCopied));
    } else {
        fallbackCopy(text, showCopied);
    }
}

function fallbackCopy(text, callback) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try { document.execCommand('copy'); callback(); } catch(e) { alertSwal('{{ __('Copy not supported in this browser.') }}', null, 'error'); }
    document.body.removeChild(ta);
}

function confirmDelete() {
    confirmSwal(document.getElementById('deleteForm'), '{{ __('Delete this note? This cannot be undone.') }}', { isDelete: true });
}

/* ── AI extraction ── */
(function () {
    const btn = document.getElementById('ntAiExtractBtn');
    const box = document.getElementById('ntAiResult');
    if (!btn || !box) return;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const noteId = {{ (int) $note->id }};
    let draft = null;

    btn.addEventListener('click', async function () {
        btn.disabled = true;
        box.innerHTML = '<p style="font-size:.78rem;color:var(--gray-500);">⏳ {{ __('AI is reading this note…') }}</p>';
        try {
            const r = await fetch(`/notes/${noteId}/extract`, { headers: { 'Accept': 'application/json' } });
            draft = await r.json();
            renderDraft();
        } catch (e) {
            box.innerHTML = '<p class="text-danger" style="font-size:.78rem;">{{ __('AI request failed.') }}</p>';
        } finally {
            btn.disabled = false;
        }
    });

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function renderDraft() {
        let h = '';
        if (!draft.ai) h += `<p style="font-size:.75rem;color:#b45309;">⚠️ {{ __('AI is not configured — showing checklist items found in the text.') }}</p>`;
        if (draft.summary) h += `<div class="mb-2" style="font-size:.8rem;"><strong>{{ __('Summary') }}</strong><br>${esc(draft.summary)}</div>`;
        if ((draft.tasks || []).length) {
            h += `<div class="mb-2" style="font-size:.8rem;"><strong>{{ __('Tasks') }} (${draft.tasks.length})</strong>`;
            draft.tasks.forEach((t, i) => {
                h += `<label class="d-flex align-items-center gap-2 mt-1" style="font-weight:400;">
                    <input type="checkbox" class="form-check-input mt-0 nt-task-check" data-i="${i}" checked>
                    <span>${esc(t.title)}</span>
                    <span class="nt-badge nt-badge-kind">${esc(t.priority || '')}${t.due_date ? ' · ' + esc(t.due_date) : ''}</span>
                </label>`;
            });
            h += `</div>`;
        }
        if ((draft.decisions || []).length) {
            h += `<div class="mb-2" style="font-size:.8rem;"><strong>{{ __('Decisions') }}</strong><ul class="mb-0">`
                + draft.decisions.map(d => `<li><strong>${esc(d.title)}</strong>${d.detail ? ' — ' + esc(d.detail) : ''}</li>`).join('')
                + `</ul></div>`;
        }
        if ((draft.questions || []).length) {
            h += `<div class="mb-2" style="font-size:.8rem;"><strong>{{ __('Open questions') }}</strong><ul class="mb-0">`
                + draft.questions.map(q => `<li>${esc(q)}</li>`).join('')
                + `</ul></div>`;
        }
        if (!((draft.tasks || []).length || (draft.decisions || []).length)) {
            h += `<p style="font-size:.76rem;color:var(--gray-500);">{{ __('Nothing actionable found in this note.') }}</p>`;
        }
        h += `<div class="d-flex gap-2 mt-2">
            <button class="btn btn-sm btn-brand" id="ntAiApply" style="font-size:.72rem;">{{ __('Create selected tasks & save summary') }}</button>
        </div>`;
        box.innerHTML = h;
        document.getElementById('ntAiApply').addEventListener('click', applyDraft);
    }

    async function applyDraft() {
        const picked = [];
        document.querySelectorAll('.nt-task-check:checked').forEach(c => {
            const t = draft.tasks[+c.dataset.i];
            if (t) picked.push(t);
        });
        const r = await fetch(`/notes/${noteId}/extract`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ tasks: picked, save_summary: true, summary: draft.summary || '' }),
        });
        const d = await r.json();
        if (d.success) {
            box.innerHTML = `<p style="font-size:.8rem;color:#059669;">✅ {{ __('Done') }} — ${d.created.length} {{ __('tasks created and linked to this note.') }}</p>`;
        } else {
            box.innerHTML = `<p class="text-danger" style="font-size:.78rem;">{{ __('Could not save.') }}</p>`;
        }
    }

    /* ── Subject living summaries ── */
    document.querySelectorAll('.nt-subject-refresh').forEach(b => {
        b.addEventListener('click', async function () {
            const id = this.dataset.id;
            const wrap = document.querySelector(`#ntSubject${id} .nt-subject-summary`);
            this.disabled = true;
            if (wrap) wrap.innerHTML = '<span class="nt-form-help">⏳ …</span>';
            try {
                const r = await fetch(`/note-subjects/${id}/summarize`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                const d = await r.json();
                if (d.success && wrap) wrap.innerHTML = esc(d.summary).replace(/\n/g, '<br>');
                else if (wrap) wrap.innerHTML = `<span class="text-danger">${esc(d.message || '')}</span>`;
            } catch (e) {
                if (wrap) wrap.innerHTML = '<span class="text-danger">{{ __('AI request failed.') }}</span>';
            } finally {
                this.disabled = false;
            }
        });
    });
})();
</script>
@endpush