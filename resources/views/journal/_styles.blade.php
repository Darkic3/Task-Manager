<style>
.jn-page { max-width: 980px; margin: 0 auto; }
.jn-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px; }
.jn-head h1 { font-size:1.6rem; font-weight:800; margin:0; letter-spacing:-.02em; }
.jn-sub { color:#64748b; font-size:.9rem; }

.jn-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:20px; box-shadow:0 4px 18px rgba(15,23,42,.04); margin-bottom:16px; }
.jn-card-title { font-size:1rem; font-weight:700; margin:0 0 12px; display:flex; align-items:center; gap:8px; color:#0f172a; }
.jn-card-title i { color:#7c3aed; }

.jn-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
@media (max-width:720px){ .jn-grid { grid-template-columns:1fr; } }

.jn-field { display:flex; flex-direction:column; gap:6px; }
.jn-field label { font-size:.85rem; font-weight:600; color:#334155; }
.jn-input, .jn-select, .jn-textarea {
    border:1px solid #cbd5e1; border-radius:10px; padding:10px 12px; font-size:.95rem;
    transition:border-color .15s, box-shadow .15s; background:#fff;
}
.jn-input:focus, .jn-select:focus, .jn-textarea:focus {
    outline:none; border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.12);
}
.jn-textarea { min-height:260px; resize:vertical; line-height:1.6; }

.jn-chips { display:flex; flex-wrap:wrap; gap:8px; }
.jn-chip {
    display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px;
    border:1px solid #cbd5e1; background:#f8fafc; cursor:pointer; font-size:.85rem; user-select:none;
    transition:all .15s;
}
.jn-chip:hover { border-color:#7c3aed; }
.jn-chip.is-on { background:#ede9fe; border-color:#7c3aed; color:#5b21b6; }

.jn-mood-row { display:flex; gap:8px; flex-wrap:wrap; }
.jn-mood-btn {
    width:42px; height:42px; border-radius:10px; border:1px solid #cbd5e1; background:#fff;
    display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.1rem;
    transition:all .15s;
}
.jn-mood-btn:hover { border-color:#7c3aed; }
.jn-mood-btn.is-on { background:#ede9fe; border-color:#7c3aed; transform:scale(1.05); }

.jn-score { display:flex; gap:4px; }
.jn-score button {
    width:30px; height:30px; border-radius:8px; border:1px solid #cbd5e1; background:#fff;
    font-size:.8rem; font-weight:600; cursor:pointer;
}
.jn-score button.is-on { background:#7c3aed; color:#fff; border-color:#7c3aed; }

.jn-prompt {
    background:linear-gradient(135deg,#faf5ff 0%,#ede9fe 100%);
    border:1px solid #ddd6fe; border-radius:14px; padding:14px 16px; margin-bottom:16px;
    display:flex; gap:10px; align-items:flex-start;
}
.jn-prompt i { color:#7c3aed; font-size:1.2rem; margin-top:2px; }
.jn-prompt strong { color:#5b21b6; }

.jn-savebar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:8px; }
.jn-savebar .btn { border-radius:10px; padding:10px 18px; font-weight:600; }
.jn-autosave { margin-left:auto; font-size:.85rem; color:#64748b; }

.jn-section-divider { height:1px; background:#e2e8f0; margin:6px 0 14px; }
.journal-content { line-height:1.7; color:#1e293b; }
.journal-content h1,.journal-content h2,.journal-content h3 { margin-top:1rem; font-weight:700; }
.journal-content p { margin:.6rem 0; }
.journal-content ul,.journal-content ol { padding-inline-start:1.4rem; }
.journal-content code { background:#f1f5f9; padding:2px 6px; border-radius:6px; }
.journal-content pre { background:#0f172a; color:#e2e8f0; padding:12px; border-radius:10px; overflow:auto; }
.journal-content blockquote { border-inline-start:3px solid #7c3aed; padding-inline-start:12px; color:#475569; }
</style>
