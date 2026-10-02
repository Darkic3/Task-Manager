@extends('layouts.app')

@section('title', __('Lina AI'))

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>if(typeof marked!=='undefined') marked.setOptions({ breaks: true, gfm: true });</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<style>
/* ══ Lina — minimal, flat, professional ══ */
:root {
    --lina-accent: #4f46e5;
    --lina-accent-hover: #4338ca;
    --lina-accent-soft: #f5f5ff;
    --lina-accent-line: #e4e4f7;
    --lina-ink: #0f172a;
    --lina-body: #334155;
    --lina-muted: #64748b;
    --lina-faint: #94a3b8;
    --lina-line: #e8eaed;
    --lina-line-soft: #f1f3f5;
    --lina-surface: #ffffff;
    --lina-bg: #fafafa;
    --lina-r-lg: 14px;
    --lina-r-md: 12px;
    --lina-r-sm: 9px;
    --lina-shadow: 0 1px 2px rgba(15,23,42,.05);
    --lina-ring: 0 0 0 3px rgba(79,70,229,.14);
    --lina-col: 760px;
    --lina-sidebar-w: 244px;
}
/* ── Full-height chat shell ── */
main {
    padding: 0 !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
}
footer, .topnav { display: none !important; }

/* ── Page ── */
.lina-page {
    display: flex; flex: 1; overflow: hidden; height: 100%;
    background: var(--lina-bg);
}

/* ── Conversations sidebar ── */
.lina-sidebar {
    width: var(--lina-sidebar-w); flex-shrink: 0;
    border-inline-end: 1px solid var(--lina-line);
    background: var(--lina-surface);
    display: flex; flex-direction: column;
    overflow: hidden;
}
.lina-sidebar-head { padding: 14px 12px 12px; border-bottom: 1px solid var(--lina-line-soft); }
.lina-sidebar-head h2 {
    font-size: 11px; font-weight: 700; letter-spacing: .5px;
    color: var(--lina-muted); margin: 0 0 10px; text-transform: uppercase;
}
.lina-new-btn {
    width: 100%; padding: 8px 12px;
    background: var(--lina-accent); border: 1px solid transparent;
    border-radius: var(--lina-r-sm); color: #fff;
    font-size: 13px; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    transition: background .15s; font-family: inherit;
}
.lina-new-btn:hover { background: var(--lina-accent-hover); }
.lina-new-btn:active { transform: scale(.99); }
.lina-conv-list {
    flex: 1; overflow-y: auto; padding: 8px;
    display: flex; flex-direction: column; gap: 1px;
}
.lina-conv-list::-webkit-scrollbar { width: 5px; }
.lina-conv-list::-webkit-scrollbar-thumb { background: #e2e5e9; border-radius: 3px; }
.lina-conv-list::-webkit-scrollbar-thumb:hover { background: #cfd4da; }
.lina-conv-item {
    padding: 8px 9px; border-radius: var(--lina-r-sm);
    cursor: pointer; display: flex; align-items: center; gap: 8px;
    font-size: 12.5px; color: var(--lina-body);
    transition: background .12s; position: relative;
    border: 1px solid transparent; text-align: start;
}
.lina-conv-item:hover { background: var(--lina-bg); }
.lina-conv-item.active {
    background: var(--lina-accent-soft); color: var(--lina-accent-hover);
    font-weight: 600; border-color: var(--lina-accent-line);
}
.lina-conv-item i { font-size: 12px; flex-shrink: 0; color: var(--lina-faint); }
.lina-conv-item.active i { color: var(--lina-accent); }
.lina-conv-label { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lina-conv-del {
    opacity: 0; background: none; border: none; cursor: pointer;
    color: var(--lina-faint); font-size: 12px; padding: 2px 4px; border-radius: 5px;
    transition: all .12s; flex-shrink: 0;
}
.lina-conv-item:hover .lina-conv-del, .lina-conv-item:focus-within .lina-conv-del { opacity: 1; }
.lina-conv-del:hover { background: #fef2f2; color: #dc2626; }
.lina-conv-del:focus-visible { opacity: 1; outline: 2px solid var(--lina-accent); outline-offset: 1px; }

/* ── Main column ── */
.lina-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }

/* ── Header ── */
.lina-head {
    flex-shrink: 0; padding: 0 18px; height: 56px;
    background: var(--lina-surface); border-bottom: 1px solid var(--lina-line);
    display: flex; align-items: center; gap: 10px;
}
.lina-head-avatar {
    width: 32px; height: 32px; border-radius: 9px;
    background: var(--lina-accent);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; color: #fff; flex-shrink: 0;
}
.lina-head-title { font-size: 14.5px; font-weight: 700; color: var(--lina-ink); line-height: 1.25; }
.lina-head-status {
    display: flex; align-items: center; gap: 5px;
    font-size: 11px; color: var(--lina-muted); margin-top: 1px;
}
.lina-status-dot { width: 6px; height: 6px; border-radius: 50%; background: #10b981; flex-shrink: 0; }
.lina-head-right { margin-inline-start: auto; display: flex; align-items: center; gap: 8px; }
.lina-pending-pill {
    display: none; align-items: center; gap: 5px;
    background: #fffbeb; border: 1px solid #fde68a; color: #92400e;
    border-radius: 999px; padding: 4px 11px; font-size: 11.5px; font-weight: 600;
    cursor: pointer; transition: background .15s; font-family: inherit; white-space: nowrap;
}
.lina-pending-pill:hover { background: #fef6df; }
.lina-pending-pill.show { display: inline-flex; }
.lina-icon-btn {
    width: 32px; height: 32px; border-radius: 9px;
    border: 1px solid var(--lina-line); background: var(--lina-surface);
    color: var(--lina-muted); font-size: 13px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; transition: all .15s;
}
.lina-icon-btn:hover { background: var(--lina-bg); color: var(--lina-body); border-color: #d8dce1; }
.lina-icon-btn:focus-visible, .lina-mode-btn:focus-visible, .lina-send-btn:focus-visible,
.lina-new-btn:focus-visible, .lina-cap:focus-visible, .lina-chip:focus-visible,
.lina-conv-item:focus-visible, .lina-copy-btn:focus-visible, .lina-tool-confirm:focus-visible,
.lina-tool-reject:focus-visible, .lina-dock-btn:focus-visible, .lina-code-btn:focus-visible {
    outline: none; box-shadow: var(--lina-ring);
}
#linaModelPill { display: none; }

/* ── Messages ── */
.lina-messages {
    flex: 1; overflow-y: auto; padding: 26px 20px 16px;
    display: flex; flex-direction: column; gap: 20px; scroll-behavior: smooth;
}
.lina-messages::-webkit-scrollbar { width: 7px; }
.lina-messages::-webkit-scrollbar-track { background: transparent; }
.lina-messages::-webkit-scrollbar-thumb { background: #e2e5e9; border-radius: 4px; }
.lina-messages::-webkit-scrollbar-thumb:hover { background: #cfd4da; }
.lina-msg-wrap, .lina-typing-wrap, .lina-error {
    width: 100%; max-width: var(--lina-col); margin-inline: auto;
}

/* Welcome */
.lina-welcome {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    flex: 1; text-align: center; padding: 24px 20px; gap: 10px;
    width: 100%; max-width: 620px; margin-inline: auto;
}
.lina-welcome-icon {
    width: 48px; height: 48px; border-radius: 14px; background: var(--lina-accent);
    display: flex; align-items: center; justify-content: center;
    font-size: 23px; color: #fff; margin-bottom: 2px;
}
.lina-welcome h3 { font-size: 19px; font-weight: 700; color: var(--lina-ink); margin: 0; }
.lina-welcome p { font-size: 13px; color: var(--lina-muted); margin: 0; max-width: 400px; line-height: 1.75; }
.lina-cap-grid {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px; width: 100%; margin-top: 8px; text-align: start;
}
.lina-cap {
    background: var(--lina-surface); border: 1px solid var(--lina-line);
    border-radius: var(--lina-r-md); padding: 11px 13px;
    cursor: pointer; transition: border-color .15s, background .15s;
    display: flex; gap: 10px; align-items: flex-start; text-align: start;
    font-family: inherit; width: 100%;
}
.lina-cap:hover { border-color: #d3d3f0; background: #fcfcff; }
.lina-cap-ico {
    width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; background: var(--lina-bg); color: var(--lina-muted);
}
.lina-cap b { display: block; font-size: 12.5px; color: var(--lina-ink); margin-bottom: 1px; font-weight: 600; }
.lina-cap span { display: block; font-size: 11.5px; color: var(--lina-muted); line-height: 1.6; }
.lina-welcome-chips { display: flex; flex-wrap: wrap; gap: 7px; justify-content: center; margin-top: 4px; }

/* Bubbles */
.lina-msg-wrap { display: flex; flex-direction: column; gap: 4px; }
.lina-msg-wrap.user { align-items: flex-end; }
.lina-msg-wrap.bot { align-items: flex-start; }
.lina-msg-meta { display: flex; align-items: center; gap: 8px; font-size: 10.5px; color: var(--lina-faint); }
.lina-model-tag { background: var(--lina-accent-soft); color: var(--lina-accent-hover); border-radius: 10px; padding: 1px 7px; font-size: 10px; font-weight: 600; }

.lina-msg {
    max-width: 82%; font-size: 14px; line-height: 1.75;
    padding: 10px 14px; border-radius: var(--lina-r-lg); word-break: break-word;
}
.lina-msg.user {
    background: var(--lina-accent); color: #fff;
    border-end-end-radius: 5px;
}
.lina-msg.bot {
    background: var(--lina-surface); color: var(--lina-body);
    border-end-start-radius: 5px; border: 1px solid var(--lina-line);
}
.lina-msg.bot p { margin: 0 0 8px; }
.lina-msg.bot p:last-child { margin: 0; }
.lina-msg.bot ul, .lina-msg.bot ol { margin: 4px 0 8px 20px; padding: 0; }
.lina-msg.bot li { margin-bottom: 3px; }
.lina-msg.bot code {
    background: var(--lina-line-soft); padding: 1px 5px; border-radius: 4px;
    font-size: 12.5px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.lina-msg.bot pre {
    margin: 0; padding: 0; overflow-x: auto; font-size: 12.5px;
    background: #1e2227; border-radius: 0;
}
.lina-msg.bot pre code { padding: 0; font-size: inherit; }
.lina-msg.bot pre code.hljs {
    padding: 13px 15px !important; border-radius: 0 0 var(--lina-r-sm) var(--lina-r-sm) !important;
    font-size: 12.5px; line-height: 1.65; display: block;
}
.lina-msg.bot strong { color: var(--lina-ink); font-weight: 600; }
.lina-msg.bot h1, .lina-msg.bot h2, .lina-msg.bot h3 {
    font-size: 14.5px; font-weight: 700; margin: 10px 0 4px; color: var(--lina-ink);
}
.lina-msg-actions { display: flex; gap: 4px; opacity: 0; transition: opacity .15s; }
.lina-msg-wrap:hover .lina-msg-actions,
.lina-msg-wrap:focus-within .lina-msg-actions { opacity: 1; }
.lina-copy-btn {
    background: var(--lina-surface); border: 1px solid var(--lina-line); border-radius: 7px;
    padding: 2px 8px; font-size: 11px; color: var(--lina-muted); cursor: pointer;
    display: flex; align-items: center; gap: 4px; transition: all .15s;
}
.lina-copy-btn:hover { background: var(--lina-bg); color: var(--lina-body); }

/* Typing */
.lina-typing-wrap { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
.lina-typing {
    background: var(--lina-surface); border: 1px solid var(--lina-line);
    border-radius: 14px; border-end-start-radius: 5px; padding: 13px 16px;
}
.lina-dots span {
    display: inline-block; width: 6px; height: 6px; border-radius: 50%;
    background: var(--lina-faint); margin: 0 2px;
    animation: linaBounce 1.2s infinite ease-in-out;
}
.lina-dots span:nth-child(2) { animation-delay: .16s; }
.lina-dots span:nth-child(3) { animation-delay: .32s; }
@keyframes linaBounce { 0%,80%,100% { transform: translateY(0); opacity: .4 } 40% { transform: translateY(-4px); opacity: 1 } }

.lina-streaming::after { content: '\258C'; animation: linaCursor .8s infinite; }
@keyframes linaCursor { 0%,100% { opacity: 1 } 50% { opacity: 0 } }

/* Error */
.lina-error {
    text-align: center; font-size: 12.5px; color: #b91c1c;
    padding: 9px 14px; background: #fef2f2; border-radius: var(--lina-r-md);
    border: 1px solid #fecaca; max-width: 440px;
}

/* Code block wrapper */
.code-block-wrap { margin: 10px 0; border-radius: var(--lina-r-sm); overflow: hidden; border: 1px solid #2b2f34; }
.code-block-header {
    display: flex; align-items: center; justify-content: space-between;
    background: #171a1e; padding: 6px 11px; border-bottom: 1px solid #2b2f34;
}
.code-lang { font-size: 10.5px; color: #8b949e; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .3px; }
.code-copy-btn, .lina-code-btn {
    background: none; border: 1px solid #333a41; border-radius: 6px;
    color: #8b949e; font-size: 11px; padding: 2px 8px; cursor: pointer;
    display: flex; align-items: center; gap: 4px; transition: all .15s;
    font-family: inherit; line-height: 1.5;
}
.code-copy-btn:hover { border-color: #545d66; color: #e6edf3; }

/* ── Pending dock ── */
.lina-dock {
    display: none; align-items: center; gap: 10px;
    background: #fffbeb; border: 1px solid #fde68a; color: #92400e;
    border-radius: var(--lina-r-md); padding: 7px 12px; margin-bottom: 8px;
    font-size: 12.5px; font-weight: 600;
}
.lina-dock.show { display: flex; }
.lina-dock .grow { flex: 1; }
.lina-dock-btn {
    border: none; border-radius: 8px; padding: 5px 13px;
    font-size: 12.5px; font-weight: 600; cursor: pointer;
    font-family: inherit; transition: filter .15s; white-space: nowrap;
}
.lina-dock-ok { background: var(--lina-accent); color: #fff; }
.lina-dock-ok:hover:not(:disabled) { filter: brightness(1.08); }
.lina-dock-no { background: transparent; border: 1px solid #fcd34d; color: #92400e; }
.lina-dock-no:hover:not(:disabled) { background: #fef3c7; }
.lina-dock-btn:disabled { opacity: .5; cursor: not-allowed; }

/* ── Composer ── */
.lina-foot {
    flex-shrink: 0; padding: 10px 20px 18px;
    background: var(--lina-surface); border-top: 1px solid var(--lina-line);
}
.lina-foot-inner { width: 100%; max-width: var(--lina-col); margin-inline: auto; }
.lina-input-box {
    background: var(--lina-surface); border: 1px solid var(--lina-line);
    border-radius: var(--lina-r-lg); overflow: hidden;
    transition: border-color .16s, box-shadow .16s;
}
.lina-input-box:focus-within { border-color: var(--lina-accent); box-shadow: var(--lina-ring); }
#linaInput {
    display: block; width: 100%; border: none; outline: none; resize: none;
    font-size: 14px; line-height: 1.7; color: var(--lina-body); background: transparent;
    padding: 12px 14px 2px; min-height: 46px; max-height: 180px;
    overflow-y: hidden; font-family: inherit;
}
#linaInput::placeholder { color: var(--lina-faint); }
.lina-input-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 4px 8px 8px; }
.lina-input-hints { display: flex; align-items: center; gap: 10px; font-size: 11px; color: var(--lina-faint); }
.lina-input-hints kbd {
    background: var(--lina-bg); border: 1px solid var(--lina-line); border-radius: 4px;
    padding: 1px 5px; font-size: 10.5px; font-family: inherit; color: var(--lina-muted);
}
.lina-input-right { display: flex; align-items: center; gap: 8px; }
#linaCharCount { font-size: 11px; color: var(--lina-faint); font-variant-numeric: tabular-nums; }
#linaCharCount.warn { color: #d97706; }
#linaCharCount.over { color: #dc2626; }
.lina-send-btn {
    width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0;
    background: var(--lina-accent); border: none; color: #fff; font-size: 13px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; transition: background .15s;
}
.lina-send-btn:hover:not(:disabled) { background: var(--lina-accent-hover); }
.lina-send-btn:disabled { opacity: .35; cursor: not-allowed; }

/* Chips */
.lina-chip {
    background: var(--lina-surface); border: 1px solid var(--lina-line); border-radius: 999px;
    padding: 6px 13px; font-size: 12px; color: var(--lina-body); cursor: pointer;
    transition: all .15s; white-space: nowrap; font-family: inherit;
}
.lina-chip:hover { background: var(--lina-accent-soft); border-color: var(--lina-accent-line); color: var(--lina-accent-hover); }

/* ── Tool proposal card ── */
.lina-tool-card {
    background: var(--lina-surface); border: 1px solid var(--lina-line);
    border-inline-start: 2px solid var(--lina-accent);
    border-radius: var(--lina-r-md); padding: 14px 16px;
    font-size: 13px; color: var(--lina-body);
    animation: linaRise .2s ease;
}
@keyframes linaRise { from { opacity: 0; transform: translateY(6px) } to { opacity: 1; transform: none } }
.lina-tool-card.danger { border-color: #fecaca; border-inline-start-color: #dc2626; background: #fffbfb; }
.lina-tool-card h4 { margin: 0 0 8px; font-size: 13px; font-weight: 700; color: var(--lina-ink); }
.lina-tool-card table { width: 100%; border-collapse: collapse; margin: 8px 0 2px; font-size: 12.5px; }
.lina-tool-card td { padding: 5px 4px; border-top: 1px solid var(--lina-line-soft); vertical-align: top; }
.lina-tool-card td:first-child { color: var(--lina-muted); width: 118px; font-size: 12px; }
.lina-tool-impact { font-size: 12px; color: #b45309; margin-top: 8px; background: #fffbeb; border-radius: 8px; padding: 6px 10px; }
.lina-tool-card.danger .lina-tool-impact { color: #b91c1c; font-weight: 600; background: #fef2f2; }
.lina-tool-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.lina-tool-confirm, .lina-tool-reject {
    border-radius: 9px; padding: 7px 16px; font-size: 12.5px; font-weight: 600;
    cursor: pointer; font-family: inherit; transition: all .15s;
}
.lina-tool-confirm { background: var(--lina-accent); color: #fff; border: none; }
.lina-tool-confirm:hover:not(:disabled) { background: var(--lina-accent-hover); }
.lina-tool-reject { background: var(--lina-surface); border: 1px solid var(--lina-line); color: var(--lina-body); }
.lina-tool-reject:hover:not(:disabled) { background: var(--lina-bg); }
.lina-tool-confirm:disabled, .lina-tool-reject:disabled { opacity: .5; cursor: not-allowed; }
.lina-tool-expiry { font-size: 11px; color: var(--lina-faint); margin-top: 8px; }

/* ── Plan card ── */
.lina-plan-card {
    background: var(--lina-surface); border: 1px solid var(--lina-line);
    border-radius: var(--lina-r-lg); padding: 15px 17px;
    font-size: 13px; color: var(--lina-body);
    animation: linaRise .2s ease;
}
.lina-plan-card h4 { margin: 0 0 2px; font-size: 14px; font-weight: 700; color: var(--lina-ink); }
.lina-plan-progress { height: 4px; border-radius: 99px; background: var(--lina-line-soft); margin: 10px 0 4px; overflow: hidden; }
.lina-plan-progress i { display: block; height: 100%; border-radius: 99px; background: var(--lina-accent); transition: width .35s ease; }
.lina-plan-totals { font-size: 12px; color: var(--lina-muted); margin-bottom: 8px; }
.lina-plan-tree { font-size: 12.5px; line-height: 1.75; }
.lina-plan-tree ul { list-style: none; margin: 2px 0; padding: 0; }
.lina-plan-tree ul ul { margin-inline-start: 12px; padding-inline-start: 10px; border-inline-start: 1px solid var(--lina-line); }
.lina-plan-due { color: var(--lina-faint); font-size: 11.5px; }
.lina-plan-subs { color: var(--lina-muted); font-size: 11.5px; }
.lina-phase {
    display: flex; align-items: center; gap: 9px; padding: 7px 10px;
    border-radius: 9px; font-size: 12.5px; margin-top: 4px;
    background: var(--lina-bg); border: 1px solid transparent;
}
.lina-phase .st { font-weight: 600; display: flex; align-items: center; gap: 8px; }
.lina-phase .st::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--lina-faint); flex-shrink: 0; }
.lina-phase.done { background: #f6fdf8; border-color: #dcfce7; }
.lina-phase.done .st { color: #15803d; }
.lina-phase.done .st::before { background: #22c55e; }
.lina-phase.current { background: var(--lina-accent-soft); border-color: var(--lina-accent-line); }
.lina-phase.current .st { color: var(--lina-accent-hover); }
.lina-phase.current .st::before { background: var(--lina-accent); animation: linaPulse 1.8s infinite; }
.lina-phase.locked { color: var(--lina-faint); background: transparent; border-style: dashed; border-color: var(--lina-line); }
.lina-phase .cnt { margin-inline-start: auto; color: var(--lina-faint); font-size: 11.5px; font-variant-numeric: tabular-nums; }
.lina-plan-note { font-size: 12.5px; margin-top: 8px; }
.lina-plan-note.ok { color: #16a34a; font-weight: 600; }
.lina-plan-note.muted { color: var(--lina-muted); }
@keyframes linaPulse { 0%,100% { opacity: 1 } 50% { opacity: .45 } }

/* ── Mode toggle ── */
.lina-mode-toggle { display: flex; background: var(--lina-bg); border: 1px solid var(--lina-line); border-radius: 999px; padding: 2px; gap: 2px; }
.lina-mode-btn {
    border: none; background: transparent; border-radius: 999px;
    padding: 5px 12px; font-size: 12px; font-weight: 600; cursor: pointer;
    color: var(--lina-muted); transition: all .15s; white-space: nowrap; font-family: inherit;
}
.lina-mode-btn:hover:not(.active) { color: var(--lina-body); }
.lina-mode-btn.active { background: var(--lina-surface); color: var(--lina-accent-hover); box-shadow: var(--lina-shadow); }

/* Agent notice */
.lina-agent-banner { flex-shrink: 0; padding: 0; background: transparent; border: none; display: flex; justify-content: center; }
.lina-agent-banner-inner {
    margin: 10px 20px 0; font-size: 11.5px; font-weight: 500;
    background: var(--lina-accent-soft); color: var(--lina-accent-hover);
    border: 1px solid var(--lina-accent-line); border-radius: 999px; padding: 5px 14px;
    max-width: var(--lina-col);
}
#linaAgentBlocked { border-radius: 0; }

/* Keep the global time-tracker FAB clear of the composer */
#tt-root { bottom: 116px !important; }

/* ── Mobile ── */
@media (max-width: 768px) {
    .lina-msg { max-width: 92%; }
    .lina-cap-grid { grid-template-columns: 1fr; }
    .lina-sidebar {
        position: fixed; top: 0; bottom: 0; inset-inline-start: 0;
        z-index: 1000; width: 272px;
        transform: translateX(-100%); transition: transform .25s ease;
        box-shadow: 0 0 24px rgba(15,23,42,.14);
    }
    [dir="rtl"] .lina-sidebar { transform: translateX(100%); }
    .lina-sidebar.open { transform: translateX(0) !important; }
    .lina-mob-backdrop { display: none; position: fixed; inset: 0; z-index: 999; background: rgba(15,23,42,.4); }
    .lina-mob-backdrop.open { display: block; }
    .lina-sidebar-close { display: flex !important; }
    .lina-mob-menu-btn { display: flex !important; }
    .lina-head { padding: 0 12px; height: 52px; gap: 8px; }
    .lina-head-avatar { width: 28px; height: 28px; font-size: 14px; border-radius: 8px; }
    .lina-head-title { font-size: 13.5px; }
    .lina-head-status { display: none; }
    .lina-mode-btn { padding: 4px 9px; font-size: 11.5px; }
    .lina-messages { padding: 16px 12px 12px; gap: 16px; }
    .lina-foot { padding: 8px 12px 14px; }
    .lina-input-hints { display: none; }
    #tt-root { bottom: 100px !important; right: 12px !important; }
}
.lina-mob-menu-btn, .lina-sidebar-close, .lina-mob-backdrop { display: none; }

/* Motion + contrast preferences */
@media (prefers-reduced-motion: reduce) {
    .lina-dots span, .lina-phase.current .st::before { animation: none; }
    .lina-streaming::after { animation: none; }
    .lina-messages { scroll-behavior: auto; }
    * { animation-duration: .01ms !important; transition-duration: .01ms !important; }
}
</style>
@endpush

@section('content')
{{-- Mobile backdrop --}}
<div class="lina-mob-backdrop" id="linaMobBackdrop" onclick="closeMobSidebar()"></div>

<div class="lina-page">

    {{-- Left conversations sidebar --}}
    <div class="lina-sidebar" id="linaSidebar">
        <div class="lina-sidebar-head">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <h2 style="margin:0;">{{ __('Conversations') }}</h2>
                <button class="lina-sidebar-close" onclick="closeMobSidebar()" title="{{ __('Close') }}" style="background:none;border:none;cursor:pointer;font-size:18px;color:var(--gray-500);padding:2px 6px;border-radius:6px;">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <button class="lina-new-btn" onclick="newConversation()">
                <i class="bi bi-plus-lg"></i> {{ __('New Chat') }}
            </button>
        </div>
        <div class="lina-conv-list" id="linaConvList"></div>
    </div>

    {{-- Main chat area --}}
    <div class="lina-main">

        {{-- Header --}}
        <div class="lina-head">
            {{-- Mobile: hamburger to open sidebar --}}
            <button class="lina-mob-menu-btn lina-icon-btn" onclick="openMobSidebar()" title="{{ __('Conversations') }}" style="flex-shrink:0;">
                <i class="bi bi-layout-sidebar"></i>
            </button>
            <div class="lina-head-avatar"><i class="bi bi-stars"></i></div>
            <div style="flex:1;min-width:0;">
                <div class="lina-head-title">{{ __('Lina') }}</div>
                <div class="lina-head-status">
                    <span class="lina-status-dot"></span>
                    <span>{{ __('Online — knows your workspace data') }}</span>
                </div>
            </div>
            <div class="lina-head-right">
                <button type="button" id="linaPendingPill" class="lina-pending-pill" onclick="scrollToPendingDock()" title="{{ __('Pending confirmations') }}">
                    ⏳ <span id="linaPendingCount">0</span>
                </button>
                <div class="lina-mode-toggle" role="group" aria-label="{{ __('Chat mode') }}">
                    <button type="button" id="linaModeChat" class="lina-mode-btn" onclick="setMode('chat')" title="{{ __('Chat: talk about your workspace, no changes') }}">💬 {{ __('Chat') }}</button>
                    <button type="button" id="linaModeAgent" class="lina-mode-btn" onclick="setMode('agent')" title="{{ __('Agent: create, edit and complete things with your confirmation') }}">🛠 {{ __('Agent') }}</button>
                </div>
                {{-- Mobile: back to app button --}}
                <a href="{{ url()->previous() == url()->current() ? route('dashboard') : url()->previous() }}" class="lina-icon-btn" title="{{ __('Back') }}" style="text-decoration:none;">
                    <i class="bi bi-arrow-left"></i>
                </a>
            </div>
        </div>

        {{-- Agent-mode pill --}}
        <div class="lina-agent-banner" id="linaAgentBanner" style="display:none;">
            <span class="lina-agent-banner-inner">{{ __('🛠 Agent mode — I build with your confirmation') }}</span>
        </div>

        {{-- Agent blocked warning (non-OpenAI provider / no provider) --}}
        <div id="linaAgentBlocked" style="display:none;flex-shrink:0;padding:8px 24px;font-size:12.5px;font-weight:600;background:#fef2f2;color:#b91c1c;border-bottom:1px solid #fecaca;"></div>

        {{-- Messages --}}
        <div class="lina-messages" id="linaMessages">
            <div class="lina-welcome" id="linaWelcome">
                <div class="lina-welcome-icon"><i class="bi bi-stars"></i></div>
                <h3>{{ __("Hi, I'm Lina!") }}</h3>
                <p>{{ __("Ask me anything — or pick what to build. In Agent mode I create projects, tasks, reminders and reports with your confirmation.") }}</p>
                <div class="lina-cap-grid">
                    <button type="button" class="lina-cap" onclick="fillExample(this)" data-text="سه پروژه بساز: برساز، ذخیره انرژی و Task Manager">
                        <span class="lina-cap-ico">🛠</span>
                        <span><b>{{ __('Build projects') }}</b><span>{{ __('Several projects + tasks in one go') }}</span></span>
                    </button>
                    <button type="button" class="lina-cap" onclick="fillExample(this)" data-text="برای امروز ساعت 6 بعد از ظهر یک یادآوری بساز">
                        <span class="lina-cap-ico">⏰</span>
                        <span><b>{{ __('Reminders') }}</b><span>{{ __('Events with date, time and place') }}</span></span>
                    </button>
                    <button type="button" class="lina-cap" onclick="fillExample(this)" data-text="گزارش این هفته من را بده">
                        <span class="lina-cap-ico">📊</span>
                        <span><b>{{ __('Weekly report') }}</b><span>{{ __('Progress, focus time and insights') }}</span></span>
                    </button>
                    <button type="button" class="lina-cap" onclick="fillExample(this)" data-text="تسک‌های امروز من را نشان بده">
                        <span class="lina-cap-ico">📋</span>
                        <span><b>{{ __('Today’s tasks') }}</b><span>{{ __('What is due and overdue') }}</span></span>
                    </button>
                </div>
                <div class="lina-welcome-chips">
                    <span class="lina-chip" onclick="askChip(this)">{{ __('What tasks are due today?') }}</span>
                    <span class="lina-chip" onclick="askChip(this)">{{ __('Show high priority tasks') }}</span>
                    <span class="lina-chip" onclick="askChip(this)">{{ __('Summarize my projects') }}</span>
                </div>
            </div>
        </div>

        {{-- Input --}}
        <div class="lina-foot">
            <div class="lina-foot-inner">
            <div class="lina-dock" id="linaDock">
                <span>⏳</span>
                <span class="grow" id="linaDockLabel"></span>
                <button type="button" class="lina-dock-btn lina-dock-ok" id="linaDockOk" onclick="dockConfirmAll()"></button>
                <button type="button" class="lina-dock-btn lina-dock-no" id="linaDockNo" onclick="dockRejectAll()"></button>
            </div>
            <div class="lina-input-box">
                <textarea id="linaInput"
                    placeholder="{{ __('Message Lina…') }}"
                    rows="1" maxlength="8000"></textarea>
                <div class="lina-input-toolbar">
                    <div class="lina-input-hints">
                        <span><kbd>Enter</kbd> {{ __('send') }}</span>
                        <span><kbd>Shift+Enter</kbd> {{ __('new line') }}</span>
                    </div>
                    <div class="lina-input-right">
                        <span id="linaCharCount"></span>
                        <button class="lina-send-btn" id="linaSend" onclick="window.sendMessage()" title="{{ __('Send') }}">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    /* ── Config ── */
    const STREAM_URL    = "{{ route('ai.stream') }}";
    const CONV_URL      = "{{ url('/ai/conversations') }}";
    const CSRF          = "{{ csrf_token() }}";
    const MAX_HISTORY   = 20;
    const MAX_CHARS     = 8000;

    /* ── State ── */
    let conversations = [];   // [{ id, label, updated_at }]
    let activeConvId  = null;
    let activeMessages= [];   // [{ role, content, model, created_at }]
    let isBusy        = false;
    // Chat is the safe default; agent mode acts on the workspace (with confirm cards).
    let chatMode = (function () {
        try { return localStorage.getItem('linaMode') === 'agent' ? 'agent' : 'chat'; }
        catch { return 'chat'; }
    })();
    // Phase 1: readiness from /ai/status — null = unknown yet.
    let agentReady = null;
    let agentBlockReason = null;
    let agentResolvedLabel = '';

    window.setMode = function (mode) {
        chatMode = mode === 'agent' ? 'agent' : 'chat';
        try { localStorage.setItem('linaMode', chatMode); } catch {}
        paintMode();
    };

    async function refreshAiStatus() {
        try {
            const s = await api('GET', "{{ url('/ai/status') }}");
            agentReady = !!s.agent_ready;
            agentBlockReason = s.agent_block_reason || null;
            agentResolvedLabel = s.resolved ? (s.resolved.provider + '/' + s.resolved.model) : '';
        } catch {
            agentReady = null;
            agentBlockReason = null;
        }
        paintMode();
    }

    function paintMode() {
        const c = document.getElementById('linaModeChat');
        const a = document.getElementById('linaModeAgent');
        const b = document.getElementById('linaAgentBanner');
        const blocked = document.getElementById('linaAgentBlocked');
        if (c) c.classList.toggle('active', chatMode === 'chat');
        if (a) a.classList.toggle('active', chatMode === 'agent');
        if (b) b.style.display = chatMode === 'agent' ? '' : 'none';
        if (!blocked) return;
        if (chatMode === 'agent' && agentReady === false) {
            blocked.style.display = '';
            const reason = agentBlockReason === 'no_provider'
                ? 'هیچ پروایدر فعالی تنظیم نشده — لینا فقط حالت آفلاین خواندنی است و چیزی نمی‌سازد.'
                : 'پروایدر فعلی (' + agentResolvedLabel + ') function-calling ندارد — ایجنت فقط با پروایدر OpenAI-compatible (مثل OpenRouter) کار می‌کند و چیزی ساخته نمی‌شود.';
            blocked.innerHTML = '⚠️ ایجنت آماده نیست: ' + reason + ' <a href="{{ route('ai.settings') }}" style="font-weight:700;text-decoration:underline;">رفتن به AI Settings</a>';
        } else {
            blocked.style.display = 'none';
            blocked.innerHTML = '';
        }
    }

    // Heuristic: does this message ask to CREATE/CHANGE something?
    function looksLikeBuildIntent(text) {
        const t = (text || '').toLowerCase();
        const keywords = ['بساز', 'ایجاد', 'اضافه کن', 'تعریف کن', 'پروژه', 'تسک', 'یادآوری', 'رویداد', 'یاداور', 'برنامه', 'create', 'add ', 'make ', 'build ', 'new project', 'new task', 'remind'];
        return keywords.some(k => t.includes(k));
    }

    function showSwitchToAgentHint() {
        const wrap = document.createElement('div');
        wrap.className = 'lina-msg-wrap bot';
        const card = document.createElement('div');
        card.className = 'lina-tool-card';
        card.style.cssText = 'border-color:#c4b5fd;background:#faf5ff;';
        card.innerHTML = '<h4>🛠 به نظر می‌رسد می‌خواهی چیزی ساخته شود</h4>'
            + '<div style="font-size:12.5px;color:#5b21b6;margin-bottom:8px;">در حالت Chat فقط صحبت می‌کنیم و چیزی ساخته نمی‌شود. برای ساخت واقعی به Agent برو.</div>';
        const btn = document.createElement('button');
        btn.className = 'lina-tool-confirm';
        btn.textContent = 'رفتن به Agent 🛠';
        btn.onclick = () => { window.setMode('agent'); wrap.remove(); input.focus(); };
        card.appendChild(btn);
        wrap.appendChild(card);
        msgsEl.appendChild(wrap);
        scrollBottom();
    }

    /* ── DOM ── */
    const msgsEl    = document.getElementById('linaMessages');
    const welcome   = document.getElementById('linaWelcome');
    const input     = document.getElementById('linaInput');
    const sendBtn   = document.getElementById('linaSend');
    const charCount = document.getElementById('linaCharCount');
    const convList  = document.getElementById('linaConvList');

    /* ── API helpers ── */
    async function api(method, url, body) {
        const opts = {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        };
        if (body) opts.body = JSON.stringify(body);
        const res = await fetch(url, opts);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    /* ── Conversations management ── */
    async function loadConversations() {
        try {
            conversations = await api('GET', CONV_URL);
        } catch { conversations = []; }
        renderConvList();
        // Deep link from notes ("Send to AI"): ?conversation=ID opens it directly.
        const wanted = parseInt(new URLSearchParams(window.location.search).get('conversation') || '0', 10);
        if (wanted && conversations.some(c => c.id === wanted)) {
            await switchConversation(wanted);
        } else if (conversations.length) {
            await switchConversation(conversations[0].id);
        } else {
            await newConversation();
        }
    }

    function renderConvList() {
        convList.innerHTML = '';
        if (!conversations.length) {
            convList.innerHTML = '<div style="font-size:12px;color:var(--gray-400);padding:10px;text-align:center;">No conversations yet</div>';
            return;
        }
        conversations.forEach(conv => {
            const item = document.createElement('div');
            item.className = 'lina-conv-item' + (conv.id === activeConvId ? ' active' : '');
            item.dataset.id = conv.id;
            item.innerHTML = `
                <i class="bi bi-chat-left-text"></i>
                <span class="lina-conv-label">${escHtml(conv.label || 'New Chat')}</span>
                <button class="lina-conv-del" onclick="deleteConv(${conv.id}, event)" title="Delete"><i class="bi bi-x"></i></button>
            `;
            item.addEventListener('click', () => switchConversation(conv.id));
            convList.appendChild(item);
        });
    }

    window.newConversation = async function () {
        // If the active conversation is already empty, just focus it — don't create another
        if (activeConvId && activeMessages.length === 0) {
            if (window.closeMobSidebar) closeMobSidebar();
            return;
        }
        try {
            const conv = await api('POST', CONV_URL, { label: 'New Chat' });
            conversations.unshift(conv);
            renderConvList();
            await switchConversation(conv.id);
        } catch (e) { console.error('[Lina] newConversation', e); }
    };

    window.switchConversation = async function (id) {
        activeConvId = id;
        activeMessages = [];
        renderConvList();
        msgsEl.innerHTML = '';
        msgsEl.appendChild(welcome);
        // Close sidebar on mobile after picking a conversation
        if (window.closeMobSidebar) closeMobSidebar();
        try {
            const data = await api('GET', CONV_URL + '/' + id);
            activeMessages = data.messages || [];
            renderMessages();
        } catch (e) { console.error('[Lina] switchConversation', e); }
    };

    window.deleteConv = async function (id, e) {
        e.stopPropagation();
        if (!confirm('Delete this conversation?')) return;
        try {
            await api('DELETE', CONV_URL + '/' + id);
            conversations = conversations.filter(c => c.id !== id);
            if (conversations.length) {
                await switchConversation(conversations[0].id);
            } else {
                await newConversation();
            }
            renderConvList();
        } catch (e) { console.error('[Lina] deleteConv', e); }
    };

    /* ── Messages rendering ── */
    function renderMessages() {
        msgsEl.innerHTML = '';
        if (!activeMessages.length) {
            msgsEl.appendChild(welcome);
            return;
        }
        activeMessages.forEach(m => renderBubble(m.role, m.content, m.created_at, false, m.model));
        scrollBottom();
    }

    function renderBubble(role, content, time, animate, model) {
        const wrap = document.createElement('div');
        wrap.className = 'lina-msg-wrap ' + (role === 'user' ? 'user' : 'bot');
        if (animate) { wrap.style.opacity = '0'; wrap.style.transform = 'translateY(8px)'; wrap.style.transition = 'all .2s'; }

        // Meta
        const meta = document.createElement('div');
        meta.className = 'lina-msg-meta';
        if (role !== 'user' && model) {
            const tag = document.createElement('span');
            tag.className = 'lina-model-tag';
            tag.textContent = friendlyModel(model);
            meta.appendChild(tag);
        }
        const ts = document.createElement('span');
        ts.textContent = formatTime(time);
        meta.appendChild(ts);
        wrap.appendChild(meta);

        // Bubble
        const bubble = document.createElement('div');
        bubble.className = 'lina-msg ' + (role === 'user' ? 'user' : 'bot');
        if (role !== 'user' && typeof marked !== 'undefined') {
            bubble.innerHTML = marked.parse(content);
            applyCodeEnhancements(bubble);
        } else {
            bubble.textContent = content;
        }
        wrap.appendChild(bubble);

        // Copy action
        const actions = document.createElement('div');
        actions.className = 'lina-msg-actions';
        const copyBtn = document.createElement('button');
        copyBtn.className = 'lina-copy-btn';
        copyBtn.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
        copyBtn.onclick = () => copyText(content, copyBtn);
        actions.appendChild(copyBtn);
        wrap.appendChild(actions);

        msgsEl.appendChild(wrap);
        if (animate) requestAnimationFrame(() => { wrap.style.opacity = '1'; wrap.style.transform = 'translateY(0)'; });
        return wrap;
    }

    function appendTyping() {
        const wrap = document.createElement('div');
        wrap.className = 'lina-typing-wrap';
        const meta = document.createElement('div');
        meta.className = 'lina-msg-meta'; meta.textContent = 'Lina is thinking…';
        wrap.appendChild(meta);
        const t = document.createElement('div');
        t.className = 'lina-typing';
        t.innerHTML = '<div class="lina-dots"><span></span><span></span><span></span></div>';
        wrap.appendChild(t);
        msgsEl.appendChild(wrap);
        scrollBottom();
        return wrap;
    }

    function appendError(msg) {
        const div = document.createElement('div');
        div.className = 'lina-error';
        div.textContent = '⚠ ' + msg;
        msgsEl.appendChild(div);
        scrollBottom();
    }

    /* ── Send ── */
    window.sendMessage = async function () {
        const text = input.value.trim();
        if (!text || isBusy || text.length > MAX_CHARS) return;
        if (!activeConvId) return;

        // Phase 1 guard: agent mode without a capable provider builds nothing.
        if (chatMode === 'agent' && agentReady === false) {
            const reason = agentBlockReason === 'no_provider'
                ? 'هیچ پروایدر فعالی تنظیم نشده — اول در AI Settings کلید اضافه کن.'
                : 'پروایدر فعلی function-calling ندارد — یک پروایدر OpenAI-compatible انتخاب کن.';
            appendError('ایجنت آماده نیست و چیزی ساخته نمی‌شود: ' + reason);
            return;
        }

        // Remove welcome if present
        if (welcome.parentNode) welcome.parentNode.removeChild(welcome);

        // Phase 1 hint: build intent in chat mode never creates anything.
        const buildHint = chatMode === 'chat' && looksLikeBuildIntent(text);

        const timestamp = new Date().toISOString();
        activeMessages.push({ role: 'user', content: text, created_at: timestamp });

        // Optimistically update label if first message
        if (activeMessages.length === 1) {
            const conv = conversations.find(c => c.id === activeConvId);
            if (conv) { conv.label = text.slice(0, 60); renderConvList(); }
        }

        renderBubble('user', text, timestamp, true);
        scrollBottom();

        input.value = ''; autoResize(); updateCharCount();

        const typingEl = appendTyping();
        isBusy = true; sendBtn.disabled = true;

        const historyPayload = activeMessages.slice(0, -1).slice(-MAX_HISTORY)
            .map(m => ({
                role: m.role === 'assistant' ? 'assistant' : 'user',
                content: (m.content ?? '').toString(),
            }))
            // Drop empty/whitespace-only turns (e.g. a tool-only reply) — the
            // server validation rejects a history entry without real content.
            .filter(m => m.content.trim() !== '');

        let accumulatedText = '';
        let selectedModel   = null;
        let streamWrap      = null;
        let streamBubbleEl  = null;
        let streamMetaEl    = null;
        let proposalRendered = false;
        let errorShown       = false;
        let sawToolCall      = false;

        try {
            const res = await fetch(STREAM_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ message: text, conversation_id: activeConvId, history: historyPayload, mode: chatMode }),
            });

            typingEl.remove();

            if (!res.ok) {
                let detail = '';
                try {
                    const errJson = await res.clone().json();
                    detail = errJson.message
                        || Object.values(errJson.errors || {}).flat().join(' ')
                        || (typeof errJson.error === 'string' ? errJson.error : '');
                } catch { /* non-JSON error body */ }
                appendError('Server error ' + res.status + (detail ? ': ' + detail : '') + '. Please try again.');
                activeMessages.pop();
                return;
            }

            // A 302 (usually auth -> login) is followed automatically by fetch.
            // If the final response is not SSE, do not parse it as a stream;
            // otherwise an HTML login page ends up as "No response received."
            const responseType = res.headers.get('content-type') || '';
            if (!responseType.includes('text/event-stream')) {
                const where = res.redirected && res.url ? ' Final URL: ' + res.url : '';
                appendError('AI stream failed: expected SSE but received "' + (responseType || 'unknown content type') + '".'
                    + ' This usually means the session expired or the request was redirected.' + where
                    + ' Please reload the page and log in again if needed.');
                activeMessages.pop();
                return;
            }

            // Create streaming bubble
            const streamTs = new Date().toISOString();
            streamWrap = document.createElement('div');
            streamWrap.className = 'lina-msg-wrap bot';
            streamWrap.style.cssText = 'opacity:0;transform:translateY(8px);transition:all .2s';

            streamMetaEl = document.createElement('div');
            streamMetaEl.className = 'lina-msg-meta';
            const tsMeta = document.createElement('span');
            tsMeta.textContent = formatTime(streamTs);
            streamMetaEl.appendChild(tsMeta);
            streamWrap.appendChild(streamMetaEl);

            streamBubbleEl = document.createElement('div');
            streamBubbleEl.className = 'lina-msg bot lina-streaming';
            streamWrap.appendChild(streamBubbleEl);
            msgsEl.appendChild(streamWrap);
            requestAnimationFrame(() => { streamWrap.style.opacity='1'; streamWrap.style.transform='translateY(0)'; });
            scrollBottom();

            // Read SSE stream
            const reader  = res.body.getReader();
            const decoder = new TextDecoder();
            let sseBuffer  = '';

            outer: while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                sseBuffer += decoder.decode(value, { stream: true });

                const lines = sseBuffer.split('\n');
                sseBuffer   = lines.pop();

                for (const line of lines) {
                    const trimmed = line.trim();
                    if (!trimmed.startsWith('data: ')) continue;
                    const data = trimmed.slice(6);
                    if (data === '[DONE]') break outer;

                    try {
                        const json = JSON.parse(data);
                        if (json.type === 'tool_proposal' && json.action_id) {
                            // Server is authoritative, but never render action cards in chat mode.
                            if (chatMode === 'agent') { proposalRendered = true; renderProposalCard(json); }
                            else console.warn('[Lina] proposal ignored in chat mode');
                        } else if (json.type === 'tool_proposal' && json.error) {
                            errorShown = true;
                            if (json.code === 'too_many_pending' && chatMode === 'agent') renderPendingOverflowCard(json);
                            else appendError(json.error);
                        } else if (json.type === 'plan_proposal' && json.plan) {
                            if (chatMode === 'agent') { proposalRendered = true; renderPlanCard(json.plan); }
                            else console.warn('[Lina] plan ignored in chat mode');
                        } else if (json.type === 'plan_proposal' && json.error) {
                            errorShown = true;
                            appendError(json.error);
                        } else if (json.type === 'workout_import_proposal' && json.import) {
                            if (chatMode === 'agent') { proposalRendered = true; renderWorkoutImportCard(json.import); }
                            else console.warn('[Lina] workout import ignored in chat mode');
                        } else if (json.type === 'workout_import_proposal' && json.error) {
                            errorShown = true;
                            appendError(json.error);
                        } else if (json.conversation_id !== undefined && json.choices === undefined) {
                            // Our metadata packet: { model, conversation_id }
                            if (json.model) {
                                selectedModel = json.model;
                                const tag = document.createElement('span');
                                tag.className = 'lina-model-tag';
                                tag.textContent = friendlyModel(selectedModel);
                                streamMetaEl.prepend(tag);
                            }
                        } else if (json.error) {
                            errorShown = true;
                            streamBubbleEl.classList.remove('lina-streaming');
                            const errMsg = typeof json.error === 'string' ? json.error : (json.error?.message || 'Something went wrong. Please try again.');
                            streamBubbleEl.textContent = '⚠ ' + errMsg;
                        } else {
                            const delta = json.choices?.[0]?.delta;
                            if (delta && Array.isArray(delta.tool_calls) && delta.tool_calls.length) {
                                // Model is making a tool call (no text). The server
                                // will emit the proposal packet; never show
                                // "No response received." for this.
                                sawToolCall = true;
                            }
                            const token = delta?.content || '';
                            if (token) {
                                accumulatedText += token;
                                streamBubbleEl.textContent = accumulatedText;
                                scrollBottom();
                            }
                        }
                    } catch (e) { /* ignore parse errors */ }
                }
            }

            // Final markdown render
            streamBubbleEl.classList.remove('lina-streaming');
            if (accumulatedText.trim()) {
                accumulatedText = accumulatedText.trim();
                streamBubbleEl.innerHTML = typeof marked !== 'undefined'
                    ? marked.parse(accumulatedText)
                    : accumulatedText.replace(/\n/g, '<br>');
                applyCodeEnhancements(streamBubbleEl);
                const actions = document.createElement('div');
                actions.className = 'lina-msg-actions';
                const copyBtn = document.createElement('button');
                copyBtn.className = 'lina-copy-btn';
                copyBtn.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
                const capturedText = accumulatedText;
                copyBtn.onclick = () => copyText(capturedText, copyBtn);
                actions.appendChild(copyBtn);
                streamWrap.appendChild(actions);

                const finalTs = new Date().toISOString();
                activeMessages.push({ role: 'assistant', content: accumulatedText, model: selectedModel, created_at: finalTs });
                // Refresh conv list so updated_at order updates
                api('GET', CONV_URL).then(c => { conversations = c; renderConvList(); }).catch(() => {});
            } else if (!streamBubbleEl.textContent.includes('⚠')) {
                if (proposalRendered || errorShown || sawToolCall) {
                    // The plan/tool card or the error already replaced the reply;
                    // drop the empty streaming bubble instead of a misleading message.
                    if (streamWrap && streamWrap.parentNode) streamWrap.parentNode.removeChild(streamWrap);
                } else {
                    streamBubbleEl.textContent = 'No response received.';
                    activeMessages.pop();
                }
            }

            if (buildHint) showSwitchToAgentHint();
            scrollBottom();
            refreshPendingDock();

        } catch (e) {
            if (typingEl.parentNode) typingEl.remove();
            if (streamWrap && streamWrap.parentNode) streamWrap.remove();
            appendError('Network error. Check your connection.');
            activeMessages.pop();
            console.error('[Lina]', e);
        } finally {
            isBusy = false; sendBtn.disabled = false; input.focus();
        }
    };

    window.askChip = function (el) {
        input.value = el.textContent.trim();
        autoResize(); updateCharCount(); sendMessage();
    };

    /* Capability gallery: fill the input so the user can edit before sending */
    window.fillExample = function (btn) {
        input.value = (btn.dataset.text || '').trim();
        autoResize(); updateCharCount(); input.focus();
        scrollBottom();
    };

    /* ── Pending dock: always-visible strip for unconfirmed actions ── */
    const dockEl    = document.getElementById('linaDock');
    const dockLabel = document.getElementById('linaDockLabel');
    const dockOk    = document.getElementById('linaDockOk');
    const dockNo    = document.getElementById('linaDockNo');
    const pendPill  = document.getElementById('linaPendingPill');
    const pendCount = document.getElementById('linaPendingCount');
    let pendingTotal = 0;

    async function refreshPendingDock() {
        let items = [];
        try {
            const dbg = await api('GET', "{{ url('/ai/debug') }}");
            const now = Date.now();
            items = (dbg.recent_pending_actions || []).filter(a =>
                a.status === 'pending' && (!a.expires_at || new Date(a.expires_at).getTime() > now));
        } catch { items = []; }
        pendingTotal = items.length;
        if (pendingTotal > 0) {
            dockLabel.textContent = pendingTotal + ' کار در انتظار تأیید داری';
            dockOk.textContent = 'تأیید همه ✅';
            dockNo.textContent = 'لغو همه';
            dockEl.classList.add('show');
            pendCount.textContent = pendingTotal;
            pendPill.classList.add('show');
        } else {
            dockEl.classList.remove('show');
            pendPill.classList.remove('show');
        }
    }

    window.scrollToPendingDock = function () {
        dockEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        try { input.focus({ preventScroll: true }); } catch { input.focus(); }
    };

    window.dockConfirmAll = async function () {
        dockOk.disabled = true; dockNo.disabled = true;
        dockOk.textContent = 'در حال اجرا…';
        try {
            const res = await api('POST', '/ai/actions/confirm-all');
            dockLabel.textContent = res.message || 'Done.';
        } catch {
            dockLabel.textContent = 'خطا — لطفاً تکی تأیید کن.';
        }
        dockOk.disabled = false; dockNo.disabled = false;
        await refreshPendingDock();
    };

    window.dockRejectAll = async function () {
        dockOk.disabled = true; dockNo.disabled = true;
        try {
            const res = await api('POST', '/ai/actions/reject-all');
            dockLabel.textContent = res.message || 'Cancelled.';
        } catch {
            dockLabel.textContent = 'خطا در لغو.';
        }
        dockOk.disabled = false; dockNo.disabled = false;
        await refreshPendingDock();
    };

    /* ── Mobile sidebar ── */
    const mobSidebar  = document.getElementById('linaSidebar');
    const mobBackdrop = document.getElementById('linaMobBackdrop');
    window.openMobSidebar  = function () { mobSidebar.classList.add('open'); mobBackdrop.classList.add('open'); document.body.style.overflow='hidden'; };
    window.closeMobSidebar = function () { mobSidebar.classList.remove('open'); mobBackdrop.classList.remove('open'); document.body.style.overflow=''; };

    /* ── Toolbar actions ── */
    window.clearConversation = async function () {
        if (!activeConvId || !activeMessages.length) return;
        if (!confirm('Clear this conversation?')) return;
        try {
            await api('POST', CONV_URL + '/' + activeConvId + '/clear');
            activeMessages = [];
            const conv = conversations.find(c => c.id === activeConvId);
            if (conv) conv.label = 'New Chat';
            renderConvList(); renderMessages();
        } catch (e) { console.error('[Lina] clear', e); }
    };

    window.exportChat = function () {
        if (!activeMessages.length) return;
        const lines = activeMessages.map(m =>
            '[' + formatTime(m.created_at) + '] ' +
            (m.role === 'user' ? 'You' : 'Lina' + (m.model ? ' (' + m.model + ')' : '')) +
            ':\n' + m.content + '\n'
        );
        const blob = new Blob([lines.join('\n')], { type: 'text/plain' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'lina-chat-' + new Date().toISOString().slice(0,10) + '.txt';
        a.click();
    };

    /* ── Pending-overflow card: 5 unconfirmed actions, nothing is lost ── */
    function renderPendingOverflowCard(p) {
        const wrap = document.createElement('div');
        wrap.className = 'lina-msg-wrap bot';
        const card = document.createElement('div');
        card.className = 'lina-tool-card';
        card.style.cssText = 'border-color:#fcd34d;background:#fffbeb;';
        const title = document.createElement('h4');
        title.textContent = '⏳ ۵ تایید باز داری — چیزی از دست نرفته';
        card.appendChild(title);
        const hint = document.createElement('div');
        hint.style.cssText = 'font-size:12.5px;color:#92400e;margin-bottom:8px;';
        hint.textContent = 'برای امنیت، ایجنت بیشتر از ۵ کار تأییدنشده نگه نمی‌دارد. اول این‌ها را تأیید یا لغو کن، بعد ادامه بده:';
        card.appendChild(hint);
        const list = document.createElement('div');
        list.style.cssText = 'font-size:12.5px;line-height:1.9;';
        (p.pending || []).forEach(it => {
            const row = document.createElement('div');
            row.textContent = '• ' + (it.label || it.tool) + ' (' + it.tool + ')';
            list.appendChild(row);
        });
        card.appendChild(list);
        const actions = document.createElement('div');
        actions.className = 'lina-tool-actions';
        actions.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;';
        const allOk = document.createElement('button');
        allOk.className = 'lina-tool-confirm';
        allOk.textContent = 'تأیید همه ✅';
        const allNo = document.createElement('button');
        allNo.className = 'lina-tool-reject';
        allNo.textContent = 'لغو همه';
        allOk.onclick = async () => {
            allOk.disabled = true; allNo.disabled = true; allOk.textContent = 'در حال اجرا…';
            try {
                const res = await api('POST', '/ai/actions/confirm-all');
                actions.remove();
                const done = document.createElement('div');
                done.style.cssText = 'font-size:12.5px;color:#16a34a;font-weight:600;margin-top:8px;white-space:pre-wrap;';
                done.textContent = '✅ ' + (res.message || 'Done.');
                if (res.details && res.details.length) {
                    const ul = document.createElement('div');
                    ul.style.cssText = 'font-weight:400;margin-top:4px;';
                    ul.textContent = res.details.join('\n');
                    done.appendChild(ul);
                }
                card.appendChild(done);
            } catch {
                allOk.disabled = false; allNo.disabled = false; allOk.textContent = 'تأیید همه ✅';
                appendError('Bulk confirm failed — try confirming items one by one.');
            }
            scrollBottom();
        };
        allNo.onclick = async () => {
            allOk.disabled = true; allNo.disabled = true;
            try { await api('POST', '/ai/actions/reject-all'); } catch {}
            actions.remove();
            const done = document.createElement('div');
            done.style.cssText = 'font-size:12.5px;color:var(--gray-500);margin-top:8px;';
            done.textContent = 'لغو شد — چیزی ساخته نشد.';
            card.appendChild(done);
            scrollBottom();
        };
        actions.appendChild(allOk); actions.appendChild(allNo);
        card.appendChild(actions);
        wrap.appendChild(card);
        msgsEl.appendChild(wrap);
        scrollBottom();
    }

    /* ── Tool proposal card ── */
    function renderProposalCard(p) {
        const wrap = document.createElement('div');
        wrap.className = 'lina-msg-wrap bot';
        const prev = p.preview || {};
        const card = document.createElement('div');
        card.className = 'lina-tool-card' + (prev.danger ? ' danger' : '');
        const title = document.createElement('h4');
        title.textContent = (prev.danger ? '⚠ ' : '🛠 ') + (prev.title || p.tool);
        card.appendChild(title);
        if ((prev.rows || []).length) {
            const tbl = document.createElement('table');
            prev.rows.forEach(r => {
                const tr = document.createElement('tr');
                const tdK = document.createElement('td'); tdK.textContent = r.k;
                const tdV = document.createElement('td'); tdV.textContent = r.v;
                tr.appendChild(tdK); tr.appendChild(tdV); tbl.appendChild(tr);
            });
            card.appendChild(tbl);
        }
        if (prev.impact) {
            const imp = document.createElement('div');
            imp.className = 'lina-tool-impact'; imp.textContent = prev.impact;
            card.appendChild(imp);
        }
        const actions = document.createElement('div');
        actions.className = 'lina-tool-actions';
        const okBtn = document.createElement('button');
        okBtn.className = 'lina-tool-confirm'; okBtn.textContent = 'Confirm & run';
        const noBtn = document.createElement('button');
        noBtn.className = 'lina-tool-reject'; noBtn.textContent = 'Cancel';
        okBtn.onclick = async () => {
            okBtn.disabled = true; noBtn.disabled = true; okBtn.textContent = 'Running…';
            try {
                const res = await api('POST', '/ai/actions/' + p.action_id + '/confirm');
                card.querySelector('.lina-tool-actions')?.remove();
                const done = document.createElement('div');
                done.style.cssText = 'font-size:12.5px;color:#16a34a;font-weight:600;margin-top:8px;white-space:pre-wrap;';
                if (p.tool === 'report_generate' && typeof marked !== 'undefined') {
                    done.innerHTML = marked.parse(res.message || 'Done.');
                } else {
                    done.textContent = '✅ ' + (res.message || 'Done.');
                }
                card.appendChild(done);
                scrollBottom();
                refreshPendingDock();
            } catch (e) {
                okBtn.disabled = false; noBtn.disabled = false; okBtn.textContent = 'Confirm & run';
                appendError('Action failed or expired.');
            }
        };
        noBtn.onclick = async () => {
            okBtn.disabled = true; noBtn.disabled = true;
            try { await api('POST', '/ai/actions/' + p.action_id + '/reject'); } catch {}
            card.querySelector('.lina-tool-actions')?.remove();
            const done = document.createElement('div');
            done.style.cssText = 'font-size:12.5px;color:var(--gray-500);margin-top:8px;';
            done.textContent = 'Cancelled — nothing changed.';
            card.appendChild(done);
            refreshPendingDock();
        };
        actions.appendChild(okBtn); actions.appendChild(noBtn);
        card.appendChild(actions);
        if (p.expires_at) {
            const exp = document.createElement('div');
            exp.className = 'lina-tool-expiry';
            exp.textContent = 'Expires ' + formatTime(p.expires_at);
            card.appendChild(exp);
        }
        wrap.appendChild(card);
        msgsEl.appendChild(wrap);
        scrollBottom();
    }

    /* ── Workout import proposal card ── */
    function renderWorkoutImportCard(imp) {
        const wrap = document.createElement('div');
        wrap.className = 'lina-msg-wrap bot';
        const card = document.createElement('div');
        card.className = 'lina-tool-card';
        card.style.cssText = 'border-color:#c4b5fd;background:#faf5ff;';
        
        const title = document.createElement('h4');
        title.innerHTML = '🏋️ <strong>' + escPlan(imp.title || 'Workout Training Plan') + '</strong>'
            + (imp.week_number ? ' <span class="badge bg-primary-subtle text-primary" style="font-size:11px;">Week ' + imp.week_number + '</span>' : '')
            + (imp.start_date ? ' <span class="badge bg-light text-dark border" style="font-size:11px;">Starts: ' + imp.start_date + '</span>' : '');
        card.appendChild(title);

        const rows = (imp.preview && imp.preview.rows) || [];
        if (rows.length) {
            const tbl = document.createElement('table');
            rows.forEach(r => {
                const tr = document.createElement('tr');
                const tdK = document.createElement('td'); tdK.textContent = r.k;
                const tdV = document.createElement('td'); tdV.innerHTML = '<strong>' + escPlan(r.v) + '</strong>';
                tr.appendChild(tdK); tr.appendChild(tdV); tbl.appendChild(tr);
            });
            card.appendChild(tbl);
        }

        const hint = document.createElement('div');
        hint.className = 'lina-tool-impact';
        hint.style.cssText = 'color:#6d28d9;font-size:12px;margin:8px 0;';
        hint.innerHTML = '<i class="bi bi-info-circle me-1"></i> ساختار ۷ روزه تمرین به همراه تمامی حرکات، ست‌ها، تمپوها و استراحت‌ها آماده ثبت است.';
        card.appendChild(hint);

        const actions = document.createElement('div');
        actions.className = 'lina-tool-actions';
        actions.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;';

        const confirmBtn = document.createElement('button');
        confirmBtn.className = 'lina-tool-confirm';
        confirmBtn.style.cssText = 'background:linear-gradient(135deg, #16a34a, #15803d);';
        confirmBtn.innerHTML = '⚡ تأیید و ساخت مستقیم برنامه ورزشی';

        const openBtn = document.createElement('a');
        openBtn.className = 'lina-tool-reject';
        openBtn.style.cssText = 'font-size:12.5px;text-decoration:none;display:inline-flex;align-items:center;';
        openBtn.innerHTML = '<i class="bi bi-eye me-1"></i> بررسی جزئیات';
        openBtn.href = imp.preview_url || '#';
        openBtn.target = '_blank';
        openBtn.rel = 'noopener';

        const editBtn = document.createElement('button');
        editBtn.className = 'lina-tool-reject';
        editBtn.style.cssText = 'font-size:12.5px;';
        editBtn.innerHTML = '<i class="bi bi-pencil me-1"></i> ویرایش / تغییر در برنامه';
        editBtn.onclick = () => {
            input.value = 'روی این برنامه ورزشی این تغییرات رو اعمال کن: ';
            autoResize();
            input.focus();
        };

        confirmBtn.onclick = async () => {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> در حال ساخت برنامه...';
            try {
                const res = await api('POST', '/workouts/imports/' + imp.id + '/confirm');
                actions.remove();
                hint.remove();
                const done = document.createElement('div');
                done.style.cssText = 'font-size:13px;color:#16a34a;font-weight:700;margin-top:10px;background:#f0fdf4;padding:10px 14px;border-radius:10px;border:1px solid #bbf7d0;';
                done.innerHTML = `✅ ${res.message || 'برنامه تمرینی با موفقیت ساخته شد.'} <div class="mt-2"><a href="${res.plan?.url || '/workouts/plans'}" class="btn btn-sm btn-success px-3 rounded-pill fw-bold" style="font-size:12px;"><i class="bi bi-calendar-check me-1"></i> مشاهده برنامه در Workouts</a></div>`;
                card.appendChild(done);
            } catch (e) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = '⚡ تأیید و ساخت مستقیم برنامه ورزشی';
                appendError('خطا در ثبت برنامه. لطفاً دوباره تلاش کنید.');
            }
            scrollBottom();
        };

        actions.appendChild(confirmBtn);
        actions.appendChild(openBtn);
        actions.appendChild(editBtn);
        card.appendChild(actions);

        wrap.appendChild(card);
        msgsEl.appendChild(wrap);
        scrollBottom();
    }

    /* ── Plan structure card + stepper ── */
    function renderPlanCard(plan) {
        const wrap = document.createElement('div');
        wrap.className = 'lina-msg-wrap bot';
        const card = document.createElement('div');
        card.className = 'lina-plan-card';
        wrap.appendChild(card);
        msgsEl.appendChild(wrap);
        paintPlan(card, plan);
        scrollBottom();
    }

    function escPlan(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function paintPlan(card, plan) {
        const prev = plan.preview || {};
        const tree = prev.tree || {};
        const totals = prev.totals || {};
        let totalsLine = '';
        if ((totals.routines || 0) > 0) {
            totalsLine = (totals.routines + ' routine(s) · ' + (totals.steps || 0) + ' step(s)');
        } else {
            const parts = [];
            if ((totals.projects || 0) > 1) parts.push(totals.projects + ' projects');
            if ((totals.subprojects || 0) > 0) parts.push(totals.subprojects + ' sub-project(s)');
            parts.push((totals.tasks || 0) + ' task(s)');
            parts.push((totals.subtasks || 0) + ' subtask(s)');
            if ((totals.reminders || 0) > 0) parts.push(totals.reminders + ' reminder(s)');
            if ((totals.notes || 0) > 0) parts.push(totals.notes + ' note(s)');
            if ((totals.members || 0) > 0) parts.push(totals.members + ' member(s)');
            totalsLine = parts.join(' · ');
        }
        let html = '<h4>📋 ' + escPlan(plan.title) + '</h4>'
            + '<div class="lina-plan-totals">' + totalsLine + '</div>';
        // Thin progress bar from phase states.
        const phaseList = plan.phases || [];
        const actionable = phaseList.filter(ph => (ph.total || 0) > 0);
        if (actionable.length) {
            const doneCount = actionable.filter(ph => ph.status === 'done').length;
            const pct = Math.round(doneCount / actionable.length * 100);
            html += '<div class="lina-plan-progress"><i style="width:' + pct + '%"></i></div>';
        }

        html += '<div class="lina-plan-tree"><ul>';
        if ((tree.routines || []).length) {
            tree.routines.forEach(r => {
                html += '<li>🔁 <strong>' + escPlan(r.title) + '</strong>'
                    + ' <span class="lina-plan-due">' + escPlan(r.frequency || '')
                    + (r.tracking_mode && r.tracking_mode !== 'none' ? ' · ' + escPlan(r.tracking_mode) : '') + '</span>';
                if ((r.steps || []).length) {
                    html += '<ul>' + r.steps.slice(0, 10).map(s =>
                        '<li>• ' + escPlan(s) + '</li>').join('')
                        + (r.steps.length > 10 ? '<li class="lina-plan-subs">+' + (r.steps.length - 10) + ' more…</li>' : '')
                        + '</ul>';
                }
                html += '</li>';
            });
        }
        const taskHtml = (t) => {
            let s = escPlan(t.title);
            if (t.due_date) s += ' <span class="lina-plan-due">' + escPlan(t.due_date) + '</span>';
            const subs = t.subtasks || [];
            if (subs.length) {
                const shown = subs.slice(0, 8).map(x => '<li>• ' + escPlan(x) + '</li>').join('');
                const more = subs.length > 8 ? '<li class="lina-plan-subs">+' + (subs.length - 8) + ' more…</li>' : '';
                s += '<ul>' + shown + more + '</ul>';
            }
            return '<li>☑ ' + s + '</li>';
        };
        if (!((tree.routines || []).length)) {
            // Multi-project plans use tree.projects; legacy single-tree plans
            // use tree.project + tree.subprojects.
            const rootList = (tree.projects && tree.projects.length)
                ? tree.projects
                : (tree.project ? [{ name: tree.project.name, tasks: tree.project.tasks, subprojects: tree.subprojects }] : []);
            rootList.forEach(p => {
                html += '<li>📁 <strong>' + escPlan(p.name) + '</strong>';
                if ((p.members || []).length) {
                    html += ' <span class="lina-plan-subs">👥 ' + p.members.map(escPlan).join(', ') + '</span>';
                }
                html += '<ul>';
                (p.tasks || []).forEach(t => { html += taskHtml(t); });
                (p.subprojects || []).forEach(s => {
                    html += '<li>📂 <strong>' + escPlan(s.name) + '</strong><ul>';
                    (s.tasks || []).forEach(t => { html += taskHtml(t); });
                    html += '</ul></li>';
                });
                html += '</ul></li>';
            });
        }
        (tree.reminders || []).forEach(r => {
            html += '<li>⏰ <strong>' + escPlan(r.title) + '</strong>'
                + ' <span class="lina-plan-due">' + escPlan([r.date, r.time].filter(Boolean).join(' '))
                + (r.location ? ' · ' + escPlan(r.location) : '') + '</span></li>';
        });
        (tree.notes || []).forEach(n => {
            html += '<li>📝 <strong>' + escPlan(n.title) + '</strong>'
                + (n.category ? ' <span class="lina-plan-due">' + escPlan(n.category) + '</span>' : '') + '</li>';
        });
        html += '</ul></div>';
        if (plan.status === 'proposed') {
            html += '<div class="lina-plan-note muted" style="background:#fffbeb;padding:8px 12px;border-radius:10px;border:1px solid #fde68a;margin-top:8px;">⚠️ تایید ساختار = ساخته شدن نیست. بعد از تایید باید مرحله‌ها را اجرا کنی تا پروژه و تسک‌ها واقعاً ساخته شوند.</div>';
        } else if (plan.status === 'confirmed' || plan.status === 'executing') {
            html += '<div class="lina-plan-note muted" style="background:#eff6ff;padding:8px 12px;border-radius:10px;border:1px solid #bfdbfe;margin-top:8px;">📋 ساختار تایید شد ولی هنوز کامل ساخته نشده — مرحله‌های زیر را اجرا کن.</div>';
        }
        html += '<div class="lina-plan-body"></div>';
        card.innerHTML = html;
        const body = card.querySelector('.lina-plan-body');

        if (plan.status === 'proposed') {
            const actions = document.createElement('div');
            actions.className = 'lina-tool-actions';
            actions.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;';

            // 1-Click Build Everything
            const buildAllBtn = document.createElement('button');
            buildAllBtn.className = 'lina-tool-confirm';
            buildAllBtn.style.cssText = 'background:linear-gradient(135deg, #4f46e5, #7c3aed);font-weight:700;';
            buildAllBtn.innerHTML = '🚀 ساخت و اجرای کامل پروژه (1-Click)';

            // Step by Step: approve structure AND run the first real phase,
            // so the user sees actual creation (not just "Structure approved").
            const stepBtn = document.createElement('button');
            stepBtn.className = 'lina-tool-reject';
            stepBtn.textContent = 'تایید + اجرای مرحله اول';

            // Edit / Revision pill
            const editBtn = document.createElement('button');
            editBtn.className = 'lina-tool-reject';
            editBtn.innerHTML = '<i class="bi bi-pencil me-1"></i> درخواست ویرایش';
            editBtn.onclick = () => {
                input.value = 'روی این پروژه این تغییرات رو اعمال کن: ';
                autoResize();
                input.focus();
            };

            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'lina-tool-reject';
            cancelBtn.textContent = 'انصراف';

            buildAllBtn.onclick = async () => {
                buildAllBtn.disabled = true;
                stepBtn.disabled = true;
                buildAllBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> در حال ساخت پروژه...';
                try {
                    const res = await api('POST', '/ai/plans/' + plan.id + '/confirm-structure');
                    const executed = await api('POST', '/ai/plans/' + plan.id + '/confirm-phase', { run_all: true });
                    paintPlan(card, executed.plan);
                } catch {
                    appendError('خطا در اجرای پلن. ممکن است منقضی شده باشد.');
                    paintPlan(card, plan);
                }
                scrollBottom();
            };

            stepBtn.onclick = async () => {
                stepBtn.disabled = true;
                buildAllBtn.disabled = true;
                stepBtn.textContent = 'در حال تایید و اجرای مرحله اول...';
                try {
                    const res = await api('POST', '/ai/plans/' + plan.id + '/confirm-structure');
                    // Immediately run the first pending phase so "approved"
                    // is visibly different from "built".
                    try {
                        const executed = await api('POST', '/ai/plans/' + plan.id + '/confirm-phase', { phase: res.plan.current_phase || 0 });
                        paintPlan(card, executed.plan);
                    } catch {
                        paintPlan(card, res.plan);
                    }
                } catch {
                    appendError('خطا در تایید ساختار پلن.');
                    paintPlan(card, plan);
                }
                scrollBottom();
            };

            cancelBtn.onclick = async () => {
                cancelBtn.disabled = true;
                try { await api('POST', '/ai/plans/' + plan.id + '/cancel'); } catch {}
                plan.status = 'cancelled';
                paintPlan(card, plan);
                scrollBottom();
            };

            actions.appendChild(buildAllBtn);
            actions.appendChild(stepBtn);
            actions.appendChild(editBtn);
            actions.appendChild(cancelBtn);
            body.appendChild(actions);
        } else if (plan.status === 'confirmed' || plan.status === 'executing') {
            (plan.phases || []).forEach((ph, idx) => {
                const row = document.createElement('div');
                const cls = ph.status === 'done' ? 'done' : (idx === plan.current_phase ? 'current' : 'locked');
                row.className = 'lina-phase ' + cls;
                const icon = ph.status === 'done' ? '✅' : (idx === plan.current_phase ? '▶' : '🔒');
                row.innerHTML = '<span class="st">' + icon + ' ' + escPlan(ph.label) + '</span>'
                    + '<span class="cnt">' + (ph.done || 0) + '/' + (ph.total || 0) + '</span>';
                if (idx === plan.current_phase && (ph.total || 0) > 0) {
                    const run = document.createElement('button');
                    run.className = 'lina-tool-confirm'; run.style.cssText = 'padding:4px 10px;font-size:12px;';
                    run.textContent = 'اجرای مرحله';
                    run.onclick = () => planRunPhase(card, plan.id, idx, false, run);
                    row.appendChild(run);
                    if (planRemains(plan) > (ph.total || 0)) {
                        const all = document.createElement('button');
                        all.className = 'lina-tool-reject'; all.style.cssText = 'padding:4px 10px;font-size:12px;';
                        all.textContent = 'اجرای همه باقی‌مانده';
                        all.onclick = () => planRunPhase(card, plan.id, idx, true, all);
                        row.appendChild(all);
                    }
                }
                body.appendChild(row);
            });
            const cancel = document.createElement('button');
            cancel.className = 'lina-tool-reject'; cancel.style.cssText = 'margin-top:8px;font-size:12px;';
            cancel.textContent = 'توقف پلن';
            cancel.onclick = async () => {
                cancel.disabled = true;
                try { await api('POST', '/ai/plans/' + plan.id + '/cancel'); } catch {}
                plan.status = 'cancelled';
                paintPlan(card, plan);
                scrollBottom();
            };
            body.appendChild(cancel);
        } else if (plan.status === 'done') {
            body.innerHTML = '<div class="lina-plan-note ok" style="background:#f0fdf4;padding:10px 14px;border-radius:10px;border:1px solid #bbf7d0;color:#16a34a;font-weight:700;">✅ پروژه و تمامی تسک‌ها با موفقیت ساخته شدند. <div class="mt-2"><a href="/projects" class="btn btn-sm btn-primary px-3 rounded-pill fw-bold" style="font-size:12px;"><i class="bi bi-folder-check me-1"></i> مشاهده در Projects</a></div></div>';
        } else {
            body.innerHTML = '<div class="lina-plan-note muted">پلن ' + escPlan(plan.status) + ' شد — تغییری ایجاد نشد.</div>';
        }
        if (plan.expires_at && (plan.status === 'proposed' || plan.status === 'confirmed' || plan.status === 'executing')) {
            const exp = document.createElement('div');
            exp.className = 'lina-tool-expiry';
            exp.textContent = 'Expires ' + formatTime(plan.expires_at);
            body.appendChild(exp);
        }
    }

    function planRemains(plan) {
        return (plan.phases || []).reduce((n, p) => n + ((p.status === 'done') ? 0 : (p.total || 0)), 0);
    }

    async function planRunPhase(card, planId, phaseIdx, runAll, btn) {
        card.querySelectorAll('button').forEach(b => { b.disabled = true; });
        if (btn) btn.textContent = 'Running…';
        try {
            const res = await api('POST', '/ai/plans/' + planId + '/confirm-phase', { phase: phaseIdx, run_all: !!runAll });
            paintPlan(card, res.plan);
        } catch {
            appendError('Phase failed — plan stopped. Already-created items stay.');
        }
        scrollBottom();
    }

    /* ── Helpers ── */
    function scrollBottom() { setTimeout(() => msgsEl.scrollTop = msgsEl.scrollHeight, 30); }

    function friendlyModel(model) {
        return 'Lina';
    }

    function formatTime(iso) {
        if (!iso) return '';
        try { return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
        catch { return ''; }
    }

    function fallbackCopy(text, btn, ok, def) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
        document.body.appendChild(ta);
        ta.focus(); ta.select();
        try { document.execCommand('copy'); btn.innerHTML = ok; setTimeout(() => btn.innerHTML = def, 1800); } catch {}
        document.body.removeChild(ta);
    }

    function copyText(text, btn) {
        const ok  = '<i class="bi bi-check2"></i> Copied!';
        const def = '<i class="bi bi-clipboard"></i> Copy';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text)
                .then(() => { btn.innerHTML = ok; setTimeout(() => btn.innerHTML = def, 1800); })
                .catch(() => fallbackCopy(text, btn, ok, def));
        } else {
            fallbackCopy(text, btn, ok, def);
        }
    }

    function applyCodeEnhancements(el) {
        el.querySelectorAll('pre code').forEach(codeEl => {
            if (typeof hljs !== 'undefined' && !codeEl.dataset.highlighted) {
                hljs.highlightElement(codeEl);
            }
            const pre = codeEl.parentNode;
            if (pre.dataset.enhanced) return;
            pre.dataset.enhanced = '1';

            const lang = [...codeEl.classList]
                .find(c => c.startsWith('language-'))?.replace('language-', '') || 'plaintext';

            const wrapper = document.createElement('div');
            wrapper.className = 'code-block-wrap';
            pre.parentNode.insertBefore(wrapper, pre);
            wrapper.appendChild(pre);

            const header = document.createElement('div');
            header.className = 'code-block-header';
            const langSpan = document.createElement('span');
            langSpan.className = 'code-lang';
            langSpan.textContent = lang;
            const copyBtn = document.createElement('button');
            copyBtn.className = 'code-copy-btn';
            copyBtn.type = 'button';
            copyBtn.innerHTML = '<i class="bi bi-clipboard"></i> Copy code';
            copyBtn.addEventListener('click', function () {
                const ok  = '<i class="bi bi-check2"></i> Copied!';
                const def = '<i class="bi bi-clipboard"></i> Copy code';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(codeEl.innerText)
                        .then(() => { this.innerHTML = ok; setTimeout(() => { this.innerHTML = def; }, 1800); })
                        .catch(() => fallbackCopy(codeEl.innerText, this, ok, def));
                } else {
                    fallbackCopy(codeEl.innerText, this, ok, def);
                }
            });
            header.appendChild(langSpan);
            header.appendChild(copyBtn);
            wrapper.insertBefore(header, pre);
        });
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function autoResize() {
        input.style.height = 'auto';
        const h = Math.min(input.scrollHeight, 180);
        input.style.height = h + 'px';
        input.style.overflowY = input.scrollHeight > 180 ? 'auto' : 'hidden';
    }

    function updateCharCount() {
        const len = input.value.length;
        if (len === 0) { charCount.textContent = ''; charCount.className = ''; return; }
        charCount.textContent = len + ' / ' + MAX_CHARS;
        charCount.className = len > MAX_CHARS ? 'over' : len > MAX_CHARS * 0.85 ? 'warn' : '';
    }

    input.addEventListener('input', () => { autoResize(); updateCharCount(); });
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); window.sendMessage(); }
    });

    /* ── Boot ── */
    paintMode();
    refreshAiStatus();
    loadConversations();
    autoResize();
    refreshPendingDock();
    // Keep the dock honest while other tabs/devices confirm too.
    setInterval(() => { if (!isBusy) refreshPendingDock(); }, 25000);
})();
</script>
@endpush
