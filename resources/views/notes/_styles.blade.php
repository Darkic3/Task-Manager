{{-- Shared stylesheet for the notes module. Included by every notes view. --}}
<style>
    /* ── Shell ───────────────────────────────────────────────── */
    .nt-shell {
        display: grid;
        grid-template-columns: 258px minmax(0, 1fr);
        gap: 14px;
        align-items: start;
    }

    .nt-shell.is-single {
        grid-template-columns: minmax(0, 1fr);
    }

    /* ── Filter sidebar ──────────────────────────────────────── */
    .nt-filters {
        position: sticky;
        top: 0;
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        max-height: calc(100vh - 120px);
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-width: thin;
    }

    .nt-filters::-webkit-scrollbar { width: 6px; }
    .nt-filters::-webkit-scrollbar-thumb { background: #dfe3ec; border-radius: 99px; }

    .nt-fgroup { border-bottom: 1px solid var(--gray-100); padding: 11px 12px; }
    .nt-fgroup:last-child { border-bottom: none; }

    .nt-fgroup-title {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: .66rem;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--gray-400);
        margin-bottom: 8px;
    }

    .nt-flink {
        display: flex;
        align-items: center;
        gap: 9px;
        width: 100%;
        padding: 6px 9px;
        margin-bottom: 2px;
        border: none;
        background: none;
        border-radius: 9px;
        font-size: .82rem;
        font-weight: 600;
        color: var(--gray-600);
        text-align: start;
        text-decoration: none;
        cursor: pointer;
        transition: background-color .14s, color .14s;
    }

    .nt-flink:hover { background: var(--gray-50); color: var(--gray-900); }

    .nt-flink.is-active {
        background: linear-gradient(135deg, #eef2ff, #f6f3ff);
        color: var(--primary-700);
        box-shadow: inset 0 0 0 1px #e2e8ff;
    }

    .nt-flink-ico {
        width: 24px; height: 24px; flex: none;
        border-radius: 7px;
        display: grid; place-items: center;
        font-size: .8rem;
        background: var(--gray-100);
        color: var(--gray-500);
    }

    .nt-flink.is-active .nt-flink-ico {
        background: linear-gradient(135deg, var(--primary-500), #8b5cf6);
        color: #fff;
    }

    .nt-flink-text {
        flex: 1; min-width: 0;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }

    .nt-flink-n {
        flex: none;
        font-size: .7rem;
        font-weight: 700;
        color: var(--gray-400);
        font-variant-numeric: tabular-nums;
    }

    .nt-flink.is-active .nt-flink-n { color: var(--primary-600); }

    /* Notebook tree */
    .nt-nb-row { display: flex; align-items: center; }
    .nt-nb-row .nt-flink { flex: 1; min-width: 0; }
    .nt-nb-kids { padding-inline-start: 15px; border-inline-start: 1px dashed var(--gray-200); margin-inline-start: 12px; }
    .nt-nb-dot { width: 9px; height: 9px; border-radius: 3px; flex: none; }

    /* ── Kind chips ──────────────────────────────────────────── */
    .nt-kind-row {
        display: flex; flex-wrap: wrap; gap: 5px;
    }

    .nt-kind-chip {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 4px 9px;
        border-radius: 999px;
        border: 1px solid var(--gray-200);
        background: #fff;
        font-size: .74rem;
        font-weight: 700;
        color: var(--gray-600);
        text-decoration: none;
        cursor: pointer;
        transition: all .14s;
    }

    .nt-kind-chip:hover { border-color: var(--primary-500); color: var(--primary-700); }
    .nt-kind-chip.is-active { background: var(--primary-600); border-color: var(--primary-600); color: #fff; }
    .nt-kind-chip-n { font-variant-numeric: tabular-nums; opacity: .65; font-weight: 600; }

    /* Label list */
    .nt-lbl-row { display: flex; align-items: center; gap: 7px; padding: 4px 9px; border-radius: 8px; }
    .nt-lbl-row:hover { background: var(--gray-50); }
    .nt-lbl-dot { width: 9px; height: 9px; border-radius: 50%; flex: none; }
    .nt-lbl-name { flex: 1; min-width: 0; font-size: .8rem; font-weight: 600; color: var(--gray-700); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .nt-lbl-n { font-size: .7rem; font-weight: 700; color: var(--gray-400); font-variant-numeric: tabular-nums; }

    /* ── Quick capture ───────────────────────────────────────── */
    .nt-capture {
        position: relative;
        background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
        border-radius: var(--radius-lg);
        border: 1px solid #6d28d9;
        box-shadow: 0 2px 10px rgba(124, 58, 237, .28);
        margin-bottom: 12px;
    }

    .nt-capture-row { display: flex; align-items: flex-start; gap: 9px; padding: 11px 13px; }

    .nt-capture-ico { color: rgba(255,255,255,.75); font-size: 1.05rem; margin-top: 5px; flex: none; }

    .nt-capture-input {
        flex: 1;
        background: transparent;
        border: none;
        outline: none;
        resize: none;
        color: #fff;
        font-family: inherit;
        font-size: .95rem;
        line-height: 1.6;
        min-height: 46px;
        max-height: 190px;
        padding: 4px 0;
    }

    .nt-capture-input::placeholder { color: rgba(255,255,255,.6); }

    .nt-capture-actions { display: flex; align-items: center; gap: 6px; flex: none; padding-top: 3px; }

    .nt-capture-btn {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 8px;
        background: rgba(255,255,255,.2);
        border: 1px solid rgba(255,255,255,.28);
        color: #fff; font-size: .78rem; font-weight: 700;
        cursor: pointer; transition: background .14s;
    }

    .nt-capture-btn:hover { background: rgba(255,255,255,.32); }
    .nt-capture-btn[disabled] { opacity: .55; cursor: not-allowed; }

    .nt-capture-hint {
        padding: 0 13px 9px; display: flex; flex-wrap: wrap; gap: 10px;
        font-size: .7rem; color: rgba(255,255,255,.72);
    }

    .nt-capture-hint kbd {
        background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.22);
        border-radius: 5px; padding: 1px 5px; font-size: .68rem; font-weight: 700;
    }

    /* mention autocomplete */
    .nt-mention-menu {
        position: absolute; z-index: 60;
        inset-inline: 13px; top: calc(100% - 4px);
        background: #fff; border: 1px solid var(--gray-200);
        border-radius: 12px; box-shadow: 0 18px 40px -14px rgba(15,23,42,.32);
        overflow: hidden; display: none;
    }

    .nt-mention-menu.is-open { display: block; }

    .nt-mention-item {
        display: flex; align-items: center; gap: 9px;
        padding: 7px 11px; cursor: pointer;
        border-bottom: 1px solid var(--gray-100);
    }

    .nt-mention-item:last-child { border-bottom: none; }
    .nt-mention-item.is-active, .nt-mention-item:hover { background: var(--primary-50); }

    .nt-mention-ico {
        width: 24px; height: 24px; flex: none; border-radius: 7px;
        display: grid; place-items: center; font-size: .78rem;
        background: var(--gray-100); color: var(--gray-600);
    }

    .nt-mention-name { flex: 1; min-width: 0; font-size: .82rem; font-weight: 700; color: var(--gray-800); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .nt-mention-hint { font-size: .68rem; color: var(--gray-400); font-weight: 700; text-transform: uppercase; }

    .nt-mention-new { background: #fef3c7; color: #92400e; font-size: .64rem; font-weight: 800; padding: 1px 6px; border-radius: 5px; }

    /* ── Toolbar ─────────────────────────────────────────────── */
    .nt-toolbar {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        background: #fff; border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
        padding: 9px 11px; margin-bottom: 12px;
    }

    .nt-search-wrap { position: relative; flex: 1; min-width: 180px; }
    .nt-search-wrap i { position: absolute; inset-inline-start: 10px; top: 50%; transform: translateY(-50%); font-size: .82rem; color: var(--gray-400); }

    .nt-search {
        width: 100%; height: 34px;
        padding: 0 32px;
        border: 1px solid var(--gray-300); border-radius: var(--radius-md);
        font-size: .84rem; color: var(--gray-800); outline: none; box-sizing: border-box;
        transition: border-color .14s, box-shadow .14s;
    }

    .nt-search:focus { border-color: var(--primary-500); box-shadow: 0 0 0 3px rgb(99 102 241 / .12); }

    .nt-search-clear {
        position: absolute; inset-inline-end: 7px; top: 50%; transform: translateY(-50%);
        border: none; background: none; color: var(--gray-400); cursor: pointer;
        font-size: .9rem; padding: 2px 4px; display: none;
    }

    .nt-search-clear.is-on { display: block; }

    .nt-seg { display: inline-flex; background: var(--gray-100); border-radius: 9px; padding: 3px; gap: 2px; }

    .nt-seg-btn {
        border: none; background: none; cursor: pointer;
        padding: 5px 10px; border-radius: 7px;
        font-size: .76rem; font-weight: 700; color: var(--gray-500);
        display: inline-flex; align-items: center; gap: 5px;
        transition: background .14s, color .14s;
    }

    .nt-seg-btn:hover { color: var(--gray-800); }
    .nt-seg-btn.is-active { background: #fff; color: var(--primary-700); box-shadow: var(--shadow-sm); }

    .nt-count { font-size: .74rem; font-weight: 700; color: var(--gray-500); font-variant-numeric: tabular-nums; }

    .nt-chipbar { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; align-items: center; }
    .nt-chip {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 8px; border-radius: 999px;
        background: var(--primary-50); border: 1px solid var(--primary-100);
        color: var(--primary-700); font-size: .72rem; font-weight: 700;
    }
    .nt-chip a { color: inherit; text-decoration: none; opacity: .65; }
    .nt-chip a:hover { opacity: 1; }

    /* ── Results list ────────────────────────────────────────── */
    .nt-list { display: flex; flex-direction: column; gap: 8px; }

    .nt-card {
        position: relative;
        display: block;
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        padding: 12px 14px;
        text-decoration: none;
        color: inherit;
        transition: box-shadow .15s, border-color .15s, transform .15s;
    }

    .nt-card:hover {
        box-shadow: var(--shadow-md);
        border-color: #ddd6fe;
        transform: translateY(-1px);
    }

    .nt-card.is-pinned { border-inline-start: 3px solid var(--warning-500); }
    .nt-card.is-archived { opacity: .62; }

    .nt-card-top { display: flex; align-items: flex-start; gap: 9px; margin-bottom: 5px; }

    .nt-card-kind {
        flex: none;
        width: 30px; height: 30px; border-radius: 9px;
        display: grid; place-items: center; font-size: .85rem;
        background: var(--primary-50); color: var(--primary-600);
    }

    .nt-card-title {
        flex: 1; min-width: 0; margin: 0;
        font-size: .92rem; font-weight: 800; color: var(--gray-900);
        line-height: 1.45;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }

    .nt-star {
        flex: none; border: none; background: none; cursor: pointer;
        color: var(--gray-300); font-size: 1rem; padding: 2px; transition: color .14s;
    }
    .nt-star.is-on { color: var(--warning-500); }
    .nt-star:hover { color: var(--warning-500); }

    .nt-card-excerpt {
        font-size: .8rem; color: var(--gray-500); line-height: 1.6; margin: 0 0 8px;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }

    .nt-card-foot {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        font-size: .71rem; color: var(--gray-400); font-weight: 600;
    }

    .nt-meta { display: inline-flex; align-items: center; gap: 4px; }
    .nt-sep { color: var(--gray-300); }

    .nt-badge {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 1px 7px; border-radius: 999px;
        font-size: .68rem; font-weight: 700;
    }

    .nt-badge-kind { background: var(--primary-50); color: var(--primary-700); }
    .nt-badge-nb { background: #ecfdf5; color: #047857; }
    .nt-badge-legacy { background: #f1f5f9; color: var(--gray-500); font-style: italic; }
    .nt-badge-lbl { background: var(--gray-100); color: var(--gray-600); }
    .nt-mood { font-size: .8rem; }

    /* ── Timeline ────────────────────────────────────────────── */
    .nt-timeline { position: relative; padding-inline-start: 22px; }

    .nt-timeline::before {
        content: ''; position: absolute; inset-block: 6px; inset-inline-start: 6px;
        width: 2px; background: var(--gray-200); border-radius: 2px;
    }

    .nt-tl-month {
        position: sticky; top: 0; z-index: 2;
        margin: 0 0 10px -22px; padding: 6px 10px;
        display: inline-flex; align-items: center; gap: 7px;
        background: rgba(255,255,255,.94);
        backdrop-filter: blur(6px);
        border: 1px solid var(--gray-200); border-radius: 999px;
        font-size: .76rem; font-weight: 800; color: var(--gray-700);
        box-shadow: var(--shadow-sm);
    }

    .nt-tl-month i { color: var(--primary-600); }

    .nt-tl-item { position: relative; margin-bottom: 9px; }

    .nt-tl-dot {
        position: absolute; inset-inline-start: -22px; top: 15px;
        width: 14px; height: 14px; border-radius: 50%;
        background: #fff; border: 2px solid var(--primary-500);
        box-shadow: 0 0 0 3px #fff;
    }

    .nt-tl-day {
        font-size: .68rem; font-weight: 800; color: var(--primary-600);
        margin-bottom: 3px; display: flex; align-items: center; gap: 5px;
    }

    /* ── Density strip ───────────────────────────────────────── */
    .nt-density {
        background: #fff; border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
        padding: 10px 12px; margin-bottom: 12px;
    }

    .nt-density-head {
        display: flex; align-items: center; justify-content: space-between;
        font-size: .68rem; font-weight: 800; letter-spacing: .06em;
        text-transform: uppercase; color: var(--gray-400); margin-bottom: 8px;
    }

    .nt-density-grid { display: flex; gap: 3px; overflow-x: auto; padding-bottom: 3px; scrollbar-width: thin; }
    .nt-density-col { display: flex; flex-direction: column; gap: 3px; }

    .nt-density-cell {
        width: 11px; height: 11px; border-radius: 3px;
        background: var(--gray-100); cursor: pointer;
        transition: transform .12s, outline-color .12s;
        outline: 2px solid transparent;
    }

    .nt-density-cell:hover { transform: scale(1.25); outline-color: var(--primary-500); }
    .nt-density-cell.lv1 { background: #ddd6fe; }
    .nt-density-cell.lv2 { background: #a78bfa; }
    .nt-density-cell.lv3 { background: #7c3aed; }
    .nt-density-cell.lv4 { background: #5b21b6; }
    .nt-density-cell.lv0 { background: var(--gray-100); }

    /* ── Empty ───────────────────────────────────────────────── */
    .nt-empty {
        text-align: center; padding: 44px 20px;
        background: #fff; border: 1px dashed var(--gray-300);
        border-radius: var(--radius-lg);
    }

    .nt-empty i { font-size: 2.4rem; color: var(--gray-300); display: block; margin-bottom: 10px; }
    .nt-empty h3 { font-size: .98rem; font-weight: 800; color: var(--gray-700); margin: 0 0 5px; }
    .nt-empty p { font-size: .82rem; color: var(--gray-500); margin: 0 0 14px; }

    /* ── Reader ──────────────────────────────────────────────── */
    .nt-reader { background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden; }

    .nt-reader-head { padding: 16px 18px 13px; border-bottom: 1px solid var(--gray-100); }

    .nt-reader-kind {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 9px; border-radius: 999px;
        background: var(--primary-50); color: var(--primary-700);
        font-size: .7rem; font-weight: 800; margin-bottom: 7px;
    }

    .nt-reader-title { margin: 0 0 7px; font-size: 1.32rem; font-weight: 800; color: var(--gray-900); line-height: 1.4; }

    .nt-reader-meta { display: flex; flex-wrap: wrap; gap: 9px; font-size: .74rem; color: var(--gray-500); font-weight: 600; align-items: center; }

    .nt-reader-body { padding: 16px 18px; font-size: .92rem; line-height: 1.85; color: var(--gray-700); }
    .nt-reader-body > *:first-child { margin-top: 0; }
    .nt-reader-body > *:last-child { margin-bottom: 0; }
    .nt-reader-body h1, .nt-reader-body h2, .nt-reader-body h3 { font-weight: 800; color: var(--gray-900); margin: 1.3em 0 .5em; line-height: 1.45; }
    .nt-reader-body h1 { font-size: 1.2rem; } .nt-reader-body h2 { font-size: 1.08rem; } .nt-reader-body h3 { font-size: .98rem; }
    .nt-reader-body p { margin: 0 0 .95em; }
    .nt-reader-body ul, .nt-reader-body ol { margin: 0 0 .95em; padding-inline-start: 1.4em; }
    .nt-reader-body li { margin-bottom: .3em; }
    .nt-reader-body code { background: var(--gray-100); padding: .12em .38em; border-radius: 5px; font-size: .86em; font-family: ui-monospace, monospace; }
    .nt-reader-body pre { background: var(--gray-900); color: #e2e8f0; padding: 12px 14px; border-radius: 10px; overflow-x: auto; font-size: .82rem; line-height: 1.6; }
    .nt-reader-body pre code { background: none; color: inherit; padding: 0; }
    .nt-reader-body blockquote { margin: 0 0 .95em; padding: .5em .9em; border-inline-start: 3px solid var(--primary-500); background: var(--primary-50); border-radius: 0 8px 8px 0; color: var(--gray-600); }
    .nt-reader-body img { max-width: 100%; border-radius: 10px; }
    .nt-reader-body table { width: 100%; border-collapse: collapse; margin-bottom: .95em; font-size: .86rem; }
    .nt-reader-body th, .nt-reader-body td { border: 1px solid var(--gray-200); padding: 6px 9px; text-align: start; }
    .nt-reader-body th { background: var(--gray-50); font-weight: 700; }
    .nt-reader-body hr { border: none; border-top: 1px solid var(--gray-200); margin: 1.2em 0; }

    .nt-reader-foot { padding: 11px 18px; border-top: 1px solid var(--gray-100); background: var(--gray-25); display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

    /* mention highlight inside the body */
    .nt-tok-subject, .nt-tok-label {
        padding: 1px 5px; border-radius: 5px; font-weight: 700;
        text-decoration: none; cursor: pointer;
    }

    .nt-tok-subject { background: #ede9fe; color: #6d28d9; }
    .nt-tok-label { background: #dbeafe; color: #1d4ed8; }
    .nt-tok-subject:hover, .nt-tok-label:hover { filter: brightness(.95); }

    /* ── Side panels ─────────────────────────────────────────── */
    .nt-panel { background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); margin-bottom: 12px; }

    .nt-panel-head {
        padding: 9px 13px; border-bottom: 1px solid var(--gray-100);
        display: flex; align-items: center; gap: 7px;
        font-size: .76rem; font-weight: 800; color: var(--gray-700);
    }

    .nt-panel-head i { color: var(--primary-600); }
    .nt-panel-body { padding: 11px 13px; }

    .nt-linkgroup + .nt-linkgroup { margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--gray-200); }
    .nt-linkgroup-label { font-size: .66rem; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; color: var(--gray-400); margin-bottom: 6px; }

    .nt-linkitem {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 3px 9px; margin: 0 4px 4px 0;
        border-radius: 999px; border: 1px solid var(--gray-200);
        background: #fff; font-size: .76rem; font-weight: 700; color: var(--gray-700);
        text-decoration: none;
    }

    .nt-linkitem:hover { border-color: var(--primary-500); color: var(--primary-700); }
    .nt-linkitem-x { border: none; background: none; color: var(--gray-400); cursor: pointer; padding: 0 0 0 2px; font-size: .7rem; }
    .nt-linkitem-x:hover { color: var(--error-500); }

    .nt-rev-item { display: flex; align-items: flex-start; gap: 9px; padding: 7px 0; border-bottom: 1px dashed var(--gray-200); }
    .nt-rev-item:last-child { border-bottom: none; }
    .nt-rev-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary-500); flex: none; margin-top: 6px; }
    .nt-rev-body { flex: 1; min-width: 0; }
    .nt-rev-title { font-size: .79rem; font-weight: 700; color: var(--gray-800); }
    .nt-rev-meta { font-size: .68rem; color: var(--gray-400); font-weight: 600; }

    .nt-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    .nt-form-row { margin-bottom: 14px; }
    .nt-form-label { display: block; font-size: .78rem; font-weight: 700; color: var(--gray-700); margin-bottom: 5px; }
    .nt-form-help { font-size: .7rem; color: var(--gray-400); margin-top: 4px; font-weight: 600; }

    .nt-select {
        width: 100%; height: 36px; padding: 0 10px;
        border: 1px solid var(--gray-300); border-radius: var(--radius-md);
        font-size: .85rem; color: var(--gray-800); outline: none; box-sizing: border-box;
        transition: border-color .14s, box-shadow .14s;
    }

    .nt-select:focus { border-color: var(--primary-500); box-shadow: 0 0 0 3px rgb(99 102 241 / .12); }

    .nt-checks { display: flex; flex-wrap: wrap; gap: 6px; }
    .nt-check {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 10px; border-radius: 999px;
        border: 1px solid var(--gray-200); background: #fff;
        font-size: .77rem; font-weight: 700; color: var(--gray-600); cursor: pointer;
        transition: all .14s;
    }
    .nt-check:hover { border-color: var(--primary-500); }
    .nt-check input { accent-color: var(--primary-600); margin: 0; }
    .nt-check:has(input:checked) { background: var(--primary-50); border-color: var(--primary-500); color: var(--primary-700); }

    .nt-scale { display: flex; gap: 4px; }
    .nt-scale-btn {
        width: 34px; height: 32px; border-radius: 9px;
        border: 1px solid var(--gray-200); background: #fff; cursor: pointer;
        font-size: 1rem; transition: all .14s;
    }
    .nt-scale-btn:hover { border-color: var(--primary-500); transform: translateY(-1px); }
    .nt-scale-btn.is-on { border-color: var(--primary-600); background: var(--primary-50); box-shadow: inset 0 0 0 1px var(--primary-500); }

    /* ── Note editor (self-hosted, no external assets) ─────────
       The textarea IS the editing surface. Everything else is a layer on
       top of it: toolbar, preview, mentions, drafts. */
    .nt-ed-shell {
        position: relative;
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-md);
        background: #fff;
        transition: border-color .15s, box-shadow .15s;
    }
    .nt-ed-shell:focus-within {
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px rgb(79 70 229 / .12);
    }
    .nt-ed-shell.is-zen {
        position: fixed; inset: 0; z-index: 2000;
        border-radius: 0; border: none;
        display: flex; flex-direction: column;
        padding: 12px 16px;
    }
    .nt-ed-shell.is-zen .nt-ed-body { min-height: 0; flex: 1; }

    .nt-ed-toolbar {
        display: flex; align-items: center; flex-wrap: wrap; gap: 2px;
        padding: 5px 7px;
        background: var(--gray-25);
        border-bottom: 1px solid var(--gray-200);
        border-radius: var(--radius-md) var(--radius-md) 0 0;
        position: sticky; top: 0; z-index: 5;
    }
    .nt-ed-group { display: flex; align-items: center; gap: 1px; }
    .nt-ed-sep { width: 1px; height: 20px; margin: 0 4px; background: var(--gray-200); flex: none; }

    .nt-ed-btn {
        width: 30px; height: 30px; padding: 0; border: 1px solid transparent;
        border-radius: 8px; background: transparent; color: var(--gray-600);
        font-size: .85rem; display: inline-flex; align-items: center; justify-content: center;
        cursor: pointer; transition: all .13s;
    }
    .nt-ed-btn:hover { background: var(--primary-50); border-color: var(--primary-200, #c7d2fe); color: var(--primary-700); }
    .nt-ed-btn:active { transform: scale(.92); }
    .nt-ed-btn.is-on { background: var(--primary-100, #e0e7ff); border-color: var(--primary-500); color: var(--primary-700); }
    .nt-ed-btn[disabled] { opacity: .4; pointer-events: none; }
    .nt-ed-btn-wide { width: auto; padding: 0 9px; gap: 5px; font-size: .74rem; font-weight: 700; }

    .nt-ed-heading { width: auto; padding: 0 7px; font-size: .74rem; font-weight: 800; }
    .nt-ed-spacer { flex: 1; }
    .nt-ed-modes {
        display: inline-flex; padding: 2px; gap: 2px; flex: none;
        background: var(--gray-100); border-radius: 9px;
    }
    .nt-ed-mode {
        border: none; background: transparent; color: var(--gray-500);
        font-size: .7rem; font-weight: 700; padding: 4px 10px; border-radius: 7px;
        cursor: pointer; transition: all .13s; display: inline-flex; align-items: center; gap: 4px;
    }
    .nt-ed-mode:hover { color: var(--gray-800); }
    .nt-ed-mode.is-on { background: #fff; color: var(--primary-700); box-shadow: var(--shadow-sm); }

    .nt-ed-body { display: grid; min-height: 0; }
    .nt-ed-shell[data-mode="write"] .nt-ed-body { grid-template-columns: 1fr; }
    .nt-ed-shell[data-mode="split"] .nt-ed-body { grid-template-columns: 1fr 1fr; }
    .nt-ed-shell[data-mode="preview"] .nt-ed-body { grid-template-columns: 1fr; }

    .nt-ed-text {
        width: 100%; min-height: 430px; max-height: 72vh; overflow-y: auto;
        border: none; outline: none; resize: vertical;
        padding: 14px 16px; background: #fff; color: var(--gray-800);
        font-family: inherit; font-size: .92rem; line-height: 2;
        tab-size: 2; field-sizing: content;
    }
    .nt-ed-text::placeholder { color: var(--gray-400); }
    .nt-ed-shell[data-mode="preview"] .nt-ed-side-write,
    .nt-ed-shell[data-mode="write"] .nt-ed-side-preview,
    .nt-ed-shell:not([data-mode="split"]) .nt-ed-divider { display: none; }
    .nt-ed-shell[data-mode="preview"] .nt-ed-text { display: none; }

    .nt-ed-divider { width: 1px; background: var(--gray-200); }
    .nt-ed-preview {
        padding: 14px 18px; overflow-y: auto; max-height: 72vh;
        font-size: .92rem; line-height: 2; color: var(--gray-700);
        background: #fff;
    }
    .nt-ed-preview.is-loading { opacity: .45; transition: opacity .12s; }
    .nt-ed-preview-empty { color: var(--gray-400); font-size: .82rem; }

    /* Mention menu (floats at the caret inside the textarea) */
    .nt-ed-mention {
        position: absolute; z-index: 2100; min-width: 210px; max-width: 300px;
        background: #fff; border: 1px solid var(--gray-200); border-radius: 10px;
        box-shadow: var(--shadow-lg); overflow: hidden; display: none;
    }
    .nt-ed-mention.is-open { display: block; }
    .nt-ed-mention-item {
        display: flex; align-items: center; gap: 8px; padding: 7px 11px;
        font-size: .8rem; cursor: pointer; color: var(--gray-800);
    }
    .nt-ed-mention-item.is-active,
    .nt-ed-mention-item:hover { background: var(--primary-50); }
    .nt-ed-mention-item i { color: var(--gray-400); }
    .nt-ed-mention-hint {
        margin-inline-start: auto; font-size: .62rem; font-weight: 800;
        text-transform: uppercase; letter-spacing: .4px; color: var(--gray-400);
    }

    .nt-ed-status {
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        padding: 5px 11px; font-size: .68rem; font-weight: 700;
        color: var(--gray-500); background: var(--gray-25);
        border-top: 1px solid var(--gray-200);
        border-radius: 0 0 var(--radius-md) var(--radius-md);
    }
    .nt-ed-status .nt-ed-draft { color: var(--primary-600); }
    .nt-ed-status .nt-ed-draft.is-saved { color: #059669; }
    .nt-ed-kbd {
        display: inline-flex; align-items: center; padding: 1px 5px;
        border: 1px solid var(--gray-200); border-bottom-width: 2px; border-radius: 5px;
        background: #fff; font-size: .64rem; font-weight: 700; color: var(--gray-500);
    }

    .nt-prefill {
        display: inline-flex; align-items: center; gap: 7px; margin-bottom: 12px;
        padding: 6px 12px; border-radius: 999px;
        background: var(--primary-50); border: 1px solid var(--primary-100, #e0e7ff);
        font-size: .78rem; font-weight: 700; color: var(--primary-700);
    }
    .nt-prefill-label { color: var(--gray-500); font-weight: 600; }
    .nt-prefill a { color: var(--gray-400); text-decoration: none; display: inline-flex; }
    .nt-prefill a:hover { color: var(--primary-700); }

    /* Draft restore banner */
    .nt-draft-bar {
        display: flex; align-items: center; gap: 8px;
        padding: 8px 12px; margin-bottom: 10px;
        background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px;
        font-size: .78rem; font-weight: 600; color: #92400e;
    }
    .nt-draft-bar[hidden] { display: none; }

    /* Chip bar */
    .nt-chipbar { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
    .nt-chipbar:empty { display: none; }
    .nt-chip {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 9px; border-radius: 999px; font-size: .7rem; font-weight: 700;
        border: 1px solid transparent;
    }
    .nt-chip i { font-size: .62rem; opacity: .75; }

    /* ── Create / edit page chrome ─────────────────────────────── */
    .nt-head {
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        margin-bottom: 13px;
    }
    .nt-head-ico {
        width: 42px; height: 42px; flex: none; border-radius: 13px;
        display: grid; place-items: center; font-size: 1.15rem; color: #fff;
        background: linear-gradient(135deg, #7c3aed, #5b21b6);
        box-shadow: 0 10px 20px -10px rgba(91,33,182,.7);
    }
    .nt-head-title { margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--gray-900); line-height: 1.35; }
    .nt-head-sub { font-size: .78rem; color: var(--gray-500); font-weight: 600; margin: 1px 0 0; }

    .nt-page-head {
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        margin-bottom: 14px;
    }
    .nt-page-back {
        width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center;
        border: 1px solid var(--gray-200); background: #fff; color: var(--gray-500);
        text-decoration: none; transition: all .14s;
    }
    .nt-page-back:hover { border-color: var(--primary-500); color: var(--primary-700); background: var(--primary-50); }

    .nt-editor-grid { display: grid; grid-template-columns: 1fr 300px; gap: 14px; align-items: start; }
    @media (max-width: 992px) { .nt-editor-grid { grid-template-columns: 1fr; } }
    .nt-editor-side > .nt-panel { position: sticky; top: 12px; }
    @media (max-width: 992px) { .nt-editor-side > .nt-panel { position: static; } }

    .nt-title-input {
        width: 100%; border: none; outline: none; background: transparent;
        font-size: 1.28rem; font-weight: 800; color: var(--gray-900);
        padding: 2px 0 6px; letter-spacing: -.01em;
    }
    .nt-title-input::placeholder { color: var(--gray-300); }

    /* Template gallery in the sidebar */
    .nt-tpl-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
    .nt-tpl {
        display: flex; flex-direction: column; align-items: flex-start; gap: 3px;
        padding: 8px 9px; border: 1px solid var(--gray-200); border-radius: 10px;
        background: #fff; cursor: pointer; text-align: start; transition: all .14s;
    }
    .nt-tpl:hover { border-color: var(--primary-500); background: var(--primary-50); transform: translateY(-1px); }
    .nt-tpl.is-on { border-color: var(--primary-600); background: var(--primary-50); box-shadow: inset 0 0 0 1px var(--primary-500); }
    .nt-tpl i { font-size: .95rem; color: var(--primary-600); }
    .nt-tpl-name { font-size: .74rem; font-weight: 700; color: var(--gray-800); line-height: 1.3; }
    .nt-tpl-hint { font-size: .62rem; font-weight: 600; color: var(--gray-400); line-height: 1.35; }

    /* Sticky save bar */
    .nt-savebar {
        position: sticky; bottom: 0; z-index: 20;
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        margin-top: 12px; padding: 10px 12px;
        background: rgba(255,255,255,.92); backdrop-filter: blur(8px);
        border: 1px solid var(--gray-200); border-radius: var(--radius-md);
        box-shadow: 0 -6px 24px -12px rgb(15 23 42 / .18);
    }
    .nt-savebar-hint { font-size: .68rem; font-weight: 600; color: var(--gray-400); }
    @media (max-width: 576px) { .nt-savebar-hint { display: none; } }

    /* ── "New note" primary action ─────────────────────────────── */
    .nt-newbtn {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 8px 15px; border-radius: 11px; border: 1px solid #6d28d9;
        background: linear-gradient(135deg, #7c3aed, #5b21b6); color: #fff;
        font-size: .8rem; font-weight: 700; text-decoration: none;
        box-shadow: 0 8px 18px -8px rgba(91,33,182,.65);
        transition: transform .13s ease, box-shadow .13s ease, filter .13s ease;
    }
    .nt-newbtn:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 11px 22px -8px rgba(91,33,182,.7); filter: brightness(1.06); }
    .nt-newbtn:active { transform: translateY(0) scale(.98); }
    .nt-newbtn i { font-size: .95rem; }
    .nt-newbtn .nt-newbtn-kbd {
        font-size: .62rem; font-weight: 800; padding: 1px 5px; border-radius: 5px;
        background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.25);
    }

    .nt-stats-bar {
        display: flex; flex-wrap: wrap; gap: 14px;
        padding: 9px 22px; border-top: 1px solid var(--gray-100);
        background: var(--gray-25); font-size: .7rem; font-weight: 600; color: var(--gray-500);
    }
    .nt-stats-bar span { display: inline-flex; align-items: center; gap: 5px; }

    /* ── Rendered note content (reader + editor preview) ───────── */
    .nt-prose { font-size: .93rem; line-height: 2; color: var(--gray-700); overflow-wrap: break-word; }
    .nt-prose > :first-child { margin-top: 0; }
    .nt-prose h1, .nt-prose h2, .nt-prose h3, .nt-prose h4 {
        color: var(--gray-900); font-weight: 800; line-height: 1.5;
        margin: 1.5em 0 .6em; scroll-margin-top: 60px;
    }
    .nt-prose h1 { font-size: 1.35rem; padding-bottom: .3em; border-bottom: 1px solid var(--gray-200); }
    .nt-prose h2 { font-size: 1.15rem; padding-bottom: .25em; border-bottom: 1px solid var(--gray-100); }
    .nt-prose h3 { font-size: 1.02rem; }
    .nt-prose h4 { font-size: .92rem; color: var(--gray-600); }
    .nt-prose p { margin: 0 0 .9em; }
    .nt-prose strong { color: var(--gray-900); font-weight: 700; }
    .nt-prose a { color: var(--primary-700); text-decoration: underline; text-underline-offset: 3px; text-decoration-thickness: 1px; }
    .nt-prose a:hover { color: var(--primary-600); }
    .nt-prose ul, .nt-prose ol { margin: 0 0 1em; padding-inline-start: 1.5em; }
    .nt-prose li { margin: .25em 0; }
    .nt-prose li > ul, .nt-prose li > ol { margin: .25em 0; }
    .nt-prose blockquote {
        margin: 1em 0; padding: .6em 1em;
        border-inline-start: 3px solid var(--primary-500);
        background: var(--primary-50, #f0f4ff);
        border-radius: 0 var(--radius-md) var(--radius-md) 0;
        color: var(--gray-600);
    }
    .nt-prose blockquote > :last-child { margin-bottom: 0; }
    .nt-prose code {
        padding: .12em .38em; border-radius: 6px;
        background: var(--gray-100); border: 1px solid var(--gray-200);
        font-size: .85em; color: #be185d; font-family: inherit;
    }
    .nt-prose pre {
        margin: 1em 0; padding: 13px 15px; overflow-x: auto;
        background: #0f172a; color: #e2e8f0;
        border-radius: var(--radius-md); font-size: .82rem; line-height: 1.85;
    }
    .nt-prose pre code { background: none; border: none; color: inherit; padding: 0; font-size: inherit; }
    .nt-prose hr { margin: 1.6em 0; border: none; border-top: 1px solid var(--gray-200); }
    .nt-prose img { max-width: 100%; height: auto; border-radius: var(--radius-md); }
    .nt-prose table {
        width: 100%; margin: 1em 0; border-collapse: collapse;
        font-size: .84rem; display: block; overflow-x: auto;
    }
    .nt-prose th, .nt-prose td { padding: 7px 11px; border: 1px solid var(--gray-200); text-align: start; }
    .nt-prose th { background: var(--gray-25); font-weight: 700; color: var(--gray-900); }
    .nt-prose tbody tr:nth-child(even) { background: var(--gray-25); }
    .nt-prose .task-list-item { list-style: none; margin-inline-start: -1.4em; display: flex; gap: 8px; align-items: flex-start; }
    .nt-prose .task-list-item input { margin-top: .55em; accent-color: var(--primary-600); }
    .nt-prose .nt-token {
        display: inline; padding: .05em .35em; border-radius: 6px;
        font-weight: 700; text-decoration: none;
    }
    .nt-prose a.nt-token-lbl { background: #dbeafe; color: #1d4ed8; }
    .nt-prose a.nt-token-lbl:hover { background: #bfdbfe; color: #1e40af; }
    .nt-prose a.nt-token-psn { background: #ede9fe; color: #6d28d9; }
    .nt-prose a.nt-token-psn:hover { background: #ddd6fe; color: #5b21b6; }
    [dir="rtl"] .nt-prose { direction: rtl; text-align: right; }

    /* ── Rich writing surface (WYSIWYG, stores Markdown) ─────── */
    .nt-write-panel { overflow: visible; }
    .nt-write-body { padding: 16px 18px 12px; }
    .nt-title-input {
        font-size: 1.42rem;
        padding: 4px 2px 10px;
    }
    .nt-ed-shell.is-rich { border-radius: 14px; overflow: visible; }
    .nt-ed-shell.is-empty-error { border-color: var(--error-500); animation: nt-shake .3s ease 2; }
    @keyframes nt-shake {
        25% { transform: translateX(-3px); }
        75% { transform: translateX(3px); }
    }
    .nt-ed-toolbar { border-radius: 14px 14px 0 0; background: #f8fafc; padding: 7px 9px; gap: 3px; }
    .nt-rich-wrap { position: relative; background: #fff; border-radius: 0 0 14px 14px; }
    .nt-rich {
        min-height: 340px;
        max-height: 68vh;
        overflow-y: auto;
        padding: 18px 20px;
        outline: none;
        font-size: 1rem;
        line-height: 2;
        color: var(--gray-800);
        caret-color: var(--primary-600);
        overflow-wrap: break-word;
    }
    .nt-rich:empty::before {
        content: attr(data-placeholder);
        color: var(--gray-300);
        font-size: .92rem;
        pointer-events: none;
        display: block;
    }
    .nt-rich > :first-child { margin-top: 0; }
    .nt-rich > :last-child { margin-bottom: 0; }
    .nt-rich h1, .nt-rich h2, .nt-rich h3, .nt-rich h4 {
        font-weight: 800; color: var(--gray-900); line-height: 1.6;
        margin: 1.1em 0 .45em;
    }
    .nt-rich h1 { font-size: 1.5rem; letter-spacing: -.01em; }
    .nt-rich h2 { font-size: 1.28rem; }
    .nt-rich h3 { font-size: 1.1rem; }
    .nt-rich h4 { font-size: .95rem; color: var(--gray-600); }
    .nt-rich p, .nt-rich div[data-ph] { margin: 0 0 .7em; }
    .nt-rich a { color: var(--primary-700); text-decoration: underline; text-underline-offset: 3px; }
    .nt-rich strong, .nt-rich b { font-weight: 800; color: var(--gray-900); }
    .nt-rich code {
        background: #fdf2f8; border: 1px solid #fbcfe8; color: #be185d;
        padding: .1em .4em; border-radius: 6px; font-size: .86em;
        font-family: ui-monospace, SFMono-Regular, monospace;
    }
    .nt-rich pre {
        background: #0f172a; color: #e2e8f0; border-radius: 12px;
        padding: 13px 15px; margin: .9em 0; overflow-x: auto;
        font-size: .84rem; line-height: 1.8;
    }
    .nt-rich pre code { background: none; border: none; color: inherit; padding: 0; font-size: inherit; }
    .nt-rich blockquote {
        margin: .9em 0; padding: .55em 1em;
        border-inline-start: 3px solid var(--primary-500);
        background: var(--primary-50); border-radius: 0 10px 10px 0; color: var(--gray-600);
    }
    .nt-rich ul, .nt-rich ol { margin: 0 0 .8em; padding-inline-start: 1.5em; }
    .nt-rich li { margin: .28em 0; }
    .nt-rich ul.nt-todo { list-style: none; padding-inline-start: .2em; }
    .nt-rich ul.nt-todo li { display: flex; align-items: flex-start; gap: 9px; }
    .nt-rich ul.nt-todo input[type="checkbox"] {
        width: 17px; height: 17px; margin-top: .5em; flex: none;
        accent-color: var(--primary-600); cursor: pointer;
    }
    .nt-rich hr { border: none; border-top: 2px solid var(--gray-200); margin: 1.4em auto; max-width: 220px; border-radius: 2px; }
    .nt-rich table { width: 100%; border-collapse: collapse; margin: .9em 0; font-size: .86rem; display: block; overflow-x: auto; }
    .nt-rich th, .nt-rich td { border: 1px solid var(--gray-200); padding: 7px 11px; text-align: start; }
    .nt-rich th { background: var(--gray-50); font-weight: 800; }
    .nt-rich img { max-width: 100%; border-radius: 12px; }
    .nt-rich:focus { box-shadow: inset 0 0 0 2px rgb(99 102 241 / .08); border-radius: 0 0 12px 12px; }
    [dir="rtl"] .nt-rich { text-align: right; }

    /* Floating selection bubble */
    .nt-bubble {
        position: absolute; z-index: 60;
        display: flex; align-items: center; gap: 2px;
        background: #0f172a; border-radius: 12px; padding: 5px 7px;
        box-shadow: 0 14px 30px -10px rgba(15,23,42,.5);
    }
    .nt-bubble[hidden] { display: none; }
    .nt-bubble button {
        border: none; background: transparent; color: #cbd5e1;
        width: 30px; height: 30px; border-radius: 8px; cursor: pointer;
        font-size: .85rem; display: grid; place-items: center; font-weight: 800;
    }
    .nt-bubble button:hover { background: rgba(255,255,255,.12); color: #fff; }

    /* Slash block menu */
    .nt-slash {
        position: absolute; z-index: 70; width: 230px; max-height: 280px; overflow-y: auto;
        background: #fff; border: 1px solid var(--gray-200); border-radius: 12px;
        box-shadow: 0 18px 40px -14px rgba(15,23,42,.3); padding: 5px;
    }
    .nt-slash[hidden] { display: none; }
    .nt-slash-item {
        display: flex; align-items: center; gap: 9px;
        padding: 8px 10px; border-radius: 8px; cursor: pointer;
        font-size: .82rem; font-weight: 700; color: var(--gray-700);
    }
    .nt-slash-item i { color: var(--primary-600); font-size: .9rem; }
    .nt-slash-item.is-active, .nt-slash-item:hover { background: var(--primary-50); }

    /* Mini markdown-export button in the status bar */
    .nt-ed-mini {
        display: inline-flex; align-items: center; gap: 5px;
        border: 1px solid var(--gray-200); background: #fff; border-radius: 8px;
        padding: 3px 10px; font-size: .7rem; font-weight: 800; color: var(--gray-600);
        cursor: pointer; transition: all .14s;
    }
    .nt-ed-mini:hover { border-color: var(--primary-500); color: var(--primary-700); background: var(--primary-50); }
    .nt-ed-mini.is-ok { border-color: #10b981; color: #059669; background: #ecfdf5; }
    .nt-ed-hide-sm { display: inline-flex; align-items: center; gap: 4px; }
    @media (max-width: 576px) { .nt-ed-hide-sm { display: none; } }
    .nt-ed-shell.is-zen .nt-rich { min-height: 70vh; max-height: none; font-size: 1.06rem; }
    .nt-ed-shell.is-zen .nt-ed-toolbar { border-radius: 0; }

    /* ── Pagination ──────────────────────────────────────────── */
    .nt-pager { display: flex; justify-content: center; margin-top: 14px; }
    .nt-pager .pagination { margin: 0; }

    /* ── Responsive ──────────────────────────────────────────── */
    @media (max-width: 1024px) {
        .nt-shell { grid-template-columns: 1fr; }
        .nt-filters { position: static; max-height: none; }
        .nt-filters[data-collapsed="1"] .nt-fgroup { display: none; }
    }

    @media (max-width: 640px) {
        .nt-card-top { gap: 7px; }
        .nt-capture-row { flex-wrap: wrap; }
        .nt-capture-actions { width: 100%; justify-content: flex-end; }
        .nt-reader-title { font-size: 1.12rem; }
        .nt-seg-btn span { display: none; }
    }
</style>