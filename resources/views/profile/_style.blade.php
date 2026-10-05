{{-- Shared design system for the profile pages (show / edit / password) --}}
<style>
/* ============================================================
   Tokens
   ============================================================ */
.pf-page {
    --pf-brand-50:  #eef2ff;
    --pf-brand-100: #e0e7ff;
    --pf-brand-200: #c7d2fe;
    --pf-brand-500: #6366f1;
    --pf-brand-600: #4f46e5;
    --pf-brand-700: #4338ca;
    --pf-violet-500: #8b5cf6;

    --pf-ink-900: #0f172a;
    --pf-ink-800: #1e293b;
    --pf-ink-700: #334155;
    --pf-ink-600: #475569;
    --pf-ink-500: #64748b;

    --pf-line:      #e6e9f0;
    --pf-line-soft: #f1f4f9;

    --pf-ok-50:    #ecfdf5;
    --pf-ok-600:   #059669;
    --pf-amber-50: #fffbeb;
    --pf-amber-600: #d97706;
    --pf-rose-50:  #fff1f2;
    --pf-rose-600: #e11d48;
    --pf-sky-50:   #f0f9ff;

    --pf-r-sm: 10px;
    --pf-r-md: 14px;
    --pf-r-lg: 20px;
    --pf-r-xl: 26px;

    --pf-shadow-sm: 0 1px 2px rgba(15, 23, 42, .05);
    --pf-shadow-md: 0 2px 4px rgba(15, 23, 42, .04), 0 10px 24px -10px rgba(15, 23, 42, .12);
    --pf-shadow-lg: 0 4px 8px rgba(15, 23, 42, .04), 0 24px 48px -18px rgba(79, 70, 229, .28);

    width: min(1180px, 100%);
    margin: 0 auto;
    padding: clamp(6px, 1vw, 14px) 0 clamp(28px, 4vw, 48px);
    font-size: 15px;
    color: var(--pf-ink-700);
}

html[dir="rtl"] .pf-page { line-height: 1.95; }
html[dir="rtl"] .pf-page h1,
html[dir="rtl"] .pf-page h2 { line-height: 1.7; }

.pf-page .pf-num { font-variant-numeric: tabular-nums; }

/* ============================================================
   Hero
   ============================================================ */
.pf-hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    border-radius: var(--pf-r-xl);
    padding: clamp(22px, 3vw, 34px);
    margin-bottom: 18px;
    color: #fff;
    background: linear-gradient(152deg, var(--pf-brand-700) 0%, var(--pf-brand-600) 32%, var(--pf-brand-500) 62%, var(--pf-violet-500) 100%);
    box-shadow: var(--pf-shadow-lg);
    animation: pf-rise .55s cubic-bezier(.22, .85, .3, 1) both;
}
.pf-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    background-image:
        linear-gradient(rgba(255, 255, 255, .07) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, .07) 1px, transparent 1px);
    background-size: 40px 40px;
    -webkit-mask-image: radial-gradient(120% 100% at 78% 0%, #000 8%, transparent 70%);
    mask-image: radial-gradient(120% 100% at 78% 0%, #000 8%, transparent 70%);
}
.pf-orb { position: absolute; z-index: -1; border-radius: 50%; background: rgba(255, 255, 255, .12); pointer-events: none; }
.pf-orb-1 { width: 320px; height: 320px; top: -130px; inset-inline-end: -90px; animation: pf-drift 17s ease-in-out infinite alternate; }
.pf-orb-2 { width: 220px; height: 220px; bottom: -110px; inset-inline-start: -60px; background: rgba(255, 255, 255, .09); animation: pf-drift 21s ease-in-out infinite alternate-reverse; }
@keyframes pf-drift {
    from { transform: translate3d(0, 0, 0) scale(1); }
    to   { transform: translate3d(-20px, 24px, 0) scale(1.06); }
}

.pf-hero-main { display: flex; align-items: center; gap: clamp(16px, 2.4vw, 26px); }

.pf-avatar-ring {
    position: relative;
    width: clamp(84px, 11vw, 108px);
    height: clamp(84px, 11vw, 108px);
    flex: none;
    border-radius: 50%;
    padding: 4px;
    background: linear-gradient(150deg, rgba(255, 255, 255, .95), rgba(255, 255, 255, .35));
    box-shadow: 0 18px 40px -14px rgba(30, 27, 75, .55);
}
.pf-avatar-ring > * { width: 100%; height: 100%; border-radius: 50%; display: block; }
.pf-avatar-img { object-fit: cover; background: #fff; }
.pf-avatar-init {
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(150deg, var(--pf-brand-600), var(--pf-violet-500));
    font-size: clamp(1.7rem, 3vw, 2.2rem);
    font-weight: 800;
    text-transform: uppercase;
}
.pf-avatar-dot {
    position: absolute;
    bottom: 4px; inset-inline-end: 4px;
    width: 22px; height: 22px;
    border-radius: 50%;
    background: #10b981;
    border: 3px solid #fff;
    box-shadow: 0 2px 6px rgba(15, 23, 42, .2);
}

.pf-hero-id { min-width: 0; flex: 1; }
.pf-eyebrow {
    display: inline-flex; align-items: center; gap: 6px;
    margin: 0 0 6px;
    padding: 4px 11px;
    border-radius: 999px;
    background: rgba(255, 255, 255, .16);
    border: 1px solid rgba(255, 255, 255, .3);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    font-size: .74rem;
    font-weight: 700;
    color: rgba(255, 255, 255, .96);
}
.pf-name {
    margin: 0;
    font-size: clamp(1.4rem, 2.8vw, 1.95rem);
    font-weight: 800;
    line-height: 1.35;
    color: #fff;
    text-shadow: 0 2px 12px rgba(30, 27, 75, .2);
    overflow-wrap: anywhere;
}
.pf-mail {
    margin: 4px 0 0;
    display: flex; align-items: center; gap: 7px;
    font-size: .9rem;
    color: rgba(255, 255, 255, .9);
    overflow-wrap: anywhere;
}
.pf-chips { list-style: none; margin: 14px 0 0; padding: 0; display: flex; flex-wrap: wrap; gap: 8px; }
.pf-chip {
    display: inline-flex; align-items: center; gap: 7px;
    max-width: 100%;
    padding: 6px 13px;
    border-radius: 999px;
    background: rgba(255, 255, 255, .14);
    border: 1px solid rgba(255, 255, 255, .26);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    font-size: .8rem;
    font-weight: 600;
    color: rgba(255, 255, 255, .97);
    text-decoration: none;
    transition: background .18s ease, transform .18s ease;
}
a.pf-chip:hover { background: rgba(255, 255, 255, .28); color: #fff; transform: translateY(-1px); }
.pf-chip i { font-size: .92rem; opacity: .92; flex: none; }
.pf-chip > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 24ch; }
.pf-chip.is-empty { background: rgba(255, 255, 255, .07); border-style: dashed; color: rgba(255, 255, 255, .88); }

.pf-hero-actions { flex: none; align-self: flex-start; }
.pf-hero-slim { padding: clamp(20px, 2.6vw, 28px); }
.pf-hero-slim .pf-hero-title { font-size: clamp(1.25rem, 2.4vw, 1.6rem); }
.pf-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px;
    flex: none;
    border-radius: 12px;
    background: rgba(255, 255, 255, .16);
    border: 1px solid rgba(255, 255, 255, .32);
    color: #fff;
    text-decoration: none;
    transition: background .18s ease, transform .18s ease;
}
.pf-back:hover { background: rgba(255, 255, 255, .3); color: #fff; transform: translateX(-2px); }
html[dir="rtl"] .pf-back:hover { transform: translateX(2px); }

.pf-meter {
    margin-top: clamp(18px, 2.4vw, 24px);
    padding: 16px 18px 13px;
    border-radius: 18px;
    background: rgba(255, 255, 255, .1);
    border: 1px solid rgba(255, 255, 255, .24);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    box-shadow: 0 10px 28px -12px rgba(30, 27, 75, .45);
}
.pf-meter-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
.pf-meter-label { display: inline-flex; align-items: center; gap: 8px; min-width: 0; font-size: .88rem; font-weight: 800; color: #fff; }
.pf-meter-label > i {
    display: grid; place-items: center;
    width: 28px; height: 28px; flex: none;
    border-radius: 9px;
    background: rgba(255, 255, 255, .18);
    border: 1px solid rgba(255, 255, 255, .3);
    font-size: .9rem;
}
.pf-meter-label small { font-size: .74rem; font-weight: 600; color: rgba(255, 255, 255, .78); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pf-meter-value {
    flex: none;
    padding: 3px 13px;
    border-radius: 999px;
    background: rgba(255, 255, 255, .18);
    border: 1px solid rgba(255, 255, 255, .32);
    font-size: .95rem;
    font-weight: 800;
    color: #fff;
    text-shadow: 0 1px 6px rgba(30, 27, 75, .25);
}
.pf-meter-track {
    height: 12px;
    border-radius: 999px;
    background: rgba(15, 23, 42, .32);
    border: 1px solid rgba(255, 255, 255, .16);
    overflow: hidden;
    box-shadow: inset 0 2px 4px rgba(15, 23, 42, .3);
}
.pf-meter-fill {
    position: relative;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #34d399, #a3e635 58%, #fde047);
    box-shadow: 0 0 14px rgba(163, 230, 53, .65);
    transition: width 1s cubic-bezier(.22, .85, .3, 1);
    overflow: hidden;
}
html[dir="rtl"] .pf-meter-fill { background: linear-gradient(-90deg, #34d399, #a3e635 58%, #fde047); }
.pf-meter-fill::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(105deg, transparent 25%, rgba(255, 255, 255, .6) 50%, transparent 75%);
    transform: translateX(-110%);
    animation: pf-shine 2.8s ease-in-out infinite;
}
html[dir="rtl"] .pf-meter-fill::after {
    background: linear-gradient(-105deg, transparent 25%, rgba(255, 255, 255, .6) 50%, transparent 75%);
    transform: translateX(110%);
    animation-name: pf-shine-rtl;
}
@keyframes pf-shine { 60% { transform: translateX(-110%); } 100% { transform: translateX(110%); } }
@keyframes pf-shine-rtl { 60% { transform: translateX(110%); } 100% { transform: translateX(-110%); } }
.pf-meter-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px 12px; margin-top: 11px; flex-wrap: wrap; }
.pf-meter-missing { display: inline-flex; align-items: center; gap: 7px; min-width: 0; font-size: .78rem; font-weight: 600; color: rgba(255, 255, 255, .9); }
.pf-meter-missing > i { flex: none; opacity: .9; }
.pf-meter-cta {
    flex: none;
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 17px;
    border-radius: 999px;
    background: #fff;
    color: var(--pf-brand-700);
    font-size: .8rem;
    font-weight: 800;
    text-decoration: none;
    box-shadow: 0 6px 16px -6px rgba(15, 23, 42, .4);
    transition: transform .16s ease, box-shadow .16s ease;
}
.pf-meter-cta:hover { color: var(--pf-brand-700); transform: translateY(-2px); box-shadow: 0 10px 22px -6px rgba(15, 23, 42, .45); }

/* ============================================================
   Buttons
   ============================================================ */
.pf-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 10px 20px;
    border-radius: 999px;
    border: 1px solid transparent;
    font-family: inherit;
    font-size: .875rem;
    font-weight: 700;
    line-height: 1.4;
    text-decoration: none;
    cursor: pointer;
    transition: transform .16s ease, box-shadow .16s ease, background .16s ease, border-color .16s ease, color .16s ease;
}
.pf-btn-sm { padding: 7px 15px; font-size: .8rem; }
.pf-btn-block { width: 100%; }
.pf-btn-glass {
    background: rgba(255, 255, 255, .18);
    border-color: rgba(255, 255, 255, .42);
    color: #fff;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}
.pf-btn-glass:hover { background: rgba(255, 255, 255, .3); color: #fff; transform: translateY(-2px); box-shadow: 0 10px 22px -8px rgba(30, 27, 75, .5); }
.pf-btn-solid {
    background: var(--pf-brand-600);
    border-color: var(--pf-brand-600);
    color: #fff;
    box-shadow: 0 8px 18px -8px rgba(79, 70, 229, .6);
}
.pf-btn-solid:hover { background: var(--pf-brand-700); border-color: var(--pf-brand-700); color: #fff; transform: translateY(-2px); box-shadow: 0 12px 24px -8px rgba(79, 70, 229, .65); }
.pf-btn-soft { background: #fff; border-color: var(--pf-line); color: var(--pf-ink-600); box-shadow: var(--pf-shadow-sm); }
.pf-btn-soft:hover { background: var(--pf-brand-50); border-color: var(--pf-brand-200); color: var(--pf-brand-700); transform: translateY(-1px); }
.pf-btn-danger { background: #dc2626; border-color: #dc2626; color: #fff; box-shadow: 0 8px 18px -8px rgba(220, 38, 38, .55); }
.pf-btn-danger:hover { background: #b91c1c; border-color: #b91c1c; color: #fff; transform: translateY(-2px); box-shadow: 0 12px 24px -8px rgba(220, 38, 38, .6); }
.pf-btn:disabled { opacity: .55; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

.pf-btn:focus-visible,
.pf-tab:focus-visible,
.pf-stat:focus-visible,
.pf-chip:focus-visible,
.pf-drop:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible { outline: 3px solid rgba(99, 102, 241, .4); outline-offset: 2px; }

/* ============================================================
   Tabs
   ============================================================ */
.pf-tabs {
    display: flex;
    gap: 6px;
    padding: 6px;
    margin-bottom: 18px;
    background: #fff;
    border: 1px solid var(--pf-line);
    border-radius: var(--pf-r-lg);
    box-shadow: var(--pf-shadow-sm);
    animation: pf-rise .55s cubic-bezier(.22, .85, .3, 1) .06s both;
}
.pf-tab {
    flex: 1;
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 10px 14px;
    border-radius: var(--pf-r-md);
    font-size: .875rem;
    font-weight: 700;
    color: var(--pf-ink-500);
    text-decoration: none;
    white-space: nowrap;
    transition: background .16s ease, color .16s ease, box-shadow .16s ease;
}
.pf-tab:hover { background: var(--pf-line-soft); color: var(--pf-ink-800); }
.pf-tab.is-active { background: var(--pf-brand-50); color: var(--pf-brand-700); box-shadow: inset 0 0 0 1px var(--pf-brand-100); }
.pf-tab i { font-size: 1rem; }

/* ============================================================
   Stats
   ============================================================ */
.pf-stats {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 18px;
    animation: pf-rise .55s cubic-bezier(.22, .85, .3, 1) .12s both;
}
.pf-stat {
    position: relative;
    overflow: hidden;
    display: block;
    padding: 16px 14px;
    background: #fff;
    border: 1px solid var(--pf-line);
    border-radius: var(--pf-r-lg);
    box-shadow: var(--pf-shadow-sm);
    text-decoration: none;
    text-align: center;
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.pf-stat::after {
    content: '';
    position: absolute; inset: auto 0 0 0; height: 3px;
    background: var(--pf-stat-color, var(--pf-brand-500));
    opacity: 0;
    transition: opacity .18s ease;
}
.pf-stat:hover { transform: translateY(-3px); border-color: var(--pf-brand-200); box-shadow: var(--pf-shadow-md); }
.pf-stat:hover::after { opacity: 1; }
.pf-stat-ico {
    width: 40px; height: 40px;
    margin: 0 auto 10px;
    border-radius: 12px;
    display: grid; place-items: center;
    font-size: 1.1rem;
    background: var(--pf-stat-soft, var(--pf-brand-50));
    color: var(--pf-stat-color, var(--pf-brand-600));
}
.pf-stat-num { display: block; font-size: 1.6rem; font-weight: 800; line-height: 1.15; color: var(--pf-ink-900); }
.pf-stat-lbl { display: block; margin-top: 3px; font-size: .78rem; font-weight: 600; color: var(--pf-ink-500); }
.pf-stat-c.violet { --pf-stat-color: var(--pf-brand-600); --pf-stat-soft: var(--pf-brand-50); }
.pf-stat-c.green  { --pf-stat-color: var(--pf-ok-600);    --pf-stat-soft: var(--pf-ok-50); }
.pf-stat-c.blue   { --pf-stat-color: #2563eb;             --pf-stat-soft: #eff6ff; }
.pf-stat-c.amber  { --pf-stat-color: var(--pf-amber-600); --pf-stat-soft: var(--pf-amber-50); }
.pf-stat-c.rose   { --pf-stat-color: var(--pf-rose-600);  --pf-stat-soft: var(--pf-rose-50); }
.pf-stat-c.sky    { --pf-stat-color: #0284c7;             --pf-stat-soft: var(--pf-sky-50); }

/* ============================================================
   Grid & cards
   ============================================================ */
.pf-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr); gap: 16px; align-items: start; }
.pf-grid-even { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
.pf-col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.pf-col-side { position: sticky; top: 0; }

.pf-card {
    background: #fff;
    border: 1px solid var(--pf-line);
    border-radius: var(--pf-r-lg);
    box-shadow: var(--pf-shadow-sm);
    overflow: hidden;
    animation: pf-rise .55s cubic-bezier(.22, .85, .3, 1) both;
}
.pf-card-hd {
    display: flex; align-items: center; gap: 11px;
    padding: 15px 18px;
    border-bottom: 1px solid var(--pf-line-soft);
    background: linear-gradient(180deg, #fbfcfe, #fff);
}
.pf-card-ico {
    width: 34px; height: 34px; flex: none;
    border-radius: 11px;
    display: grid; place-items: center;
    font-size: 1rem;
}
.pf-card-ico.violet { background: var(--pf-brand-50); color: var(--pf-brand-600); }
.pf-card-ico.blue   { background: #eff6ff; color: #2563eb; }
.pf-card-ico.green  { background: var(--pf-ok-50); color: var(--pf-ok-600); }
.pf-card-ico.amber  { background: var(--pf-amber-50); color: var(--pf-amber-600); }
.pf-card-ico.rose   { background: var(--pf-rose-50); color: var(--pf-rose-600); }
.pf-card-ico.sky    { background: var(--pf-sky-50); color: #0284c7; }
.pf-card-title { margin: 0; font-size: 1rem; font-weight: 800; color: var(--pf-ink-900); }
.pf-card-sub { margin: 1px 0 0; font-size: .78rem; color: var(--pf-ink-500); }
.pf-card-link { margin-inline-start: auto; flex: none; font-size: .8rem; font-weight: 700; color: var(--pf-brand-600); text-decoration: none; }
.pf-card-link:hover { color: var(--pf-brand-700); text-decoration: underline; }
.pf-card-bd { padding: 18px; }
.pf-card-bd.tight { padding: 10px 18px 16px; }

/* Detail rows */
.pf-rows { display: flex; flex-direction: column; gap: 2px; margin: 0; }
.pf-row {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 12px;
    border-radius: var(--pf-r-md);
    transition: background .16s ease;
}
.pf-row + .pf-row { border-top: 1px solid var(--pf-line-soft); }
.pf-row:hover { background: var(--pf-line-soft); }
.pf-row dt, .pf-row dd { margin: 0; }
.pf-row-ico {
    width: 34px; height: 34px; flex: none;
    border-radius: 10px;
    display: grid; place-items: center;
    font-size: .95rem;
    background: var(--pf-brand-50);
    color: var(--pf-brand-600);
}
.pf-row-ico.muted { background: var(--pf-line-soft); color: var(--pf-ink-500); }
.pf-row-txt { min-width: 0; flex: 1; }
.pf-row-lbl { display: block; font-size: .76rem; font-weight: 600; color: var(--pf-ink-500); }
.pf-row-val { display: block; font-size: .92rem; font-weight: 700; color: var(--pf-ink-800); overflow-wrap: anywhere; }
.pf-row-val a { color: var(--pf-brand-600); text-decoration: none; }
.pf-row-val a:hover { text-decoration: underline; }
.pf-row-val small { font-weight: 500; color: var(--pf-ink-500); }
.pf-row-val.is-empty { color: var(--pf-ink-500); font-weight: 500; }
.pf-row-badge { flex: none; padding: 4px 10px; border-radius: 999px; font-size: .72rem; font-weight: 700; }
.pf-row-badge.on  { background: var(--pf-ok-50); color: var(--pf-ok-600); }
.pf-row-badge.off { background: var(--pf-line-soft); color: var(--pf-ink-500); }

/* ============================================================
   Forms
   ============================================================ */
.pf-field { margin-bottom: 16px; }
.pf-field:last-child { margin-bottom: 0; }
.pf-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.pf-label {
    display: flex; align-items: center; gap: 5px;
    margin-bottom: 6px;
    font-size: .82rem;
    font-weight: 700;
    color: var(--pf-ink-700);
}
.pf-label .pf-req { color: #dc2626; font-weight: 800; }
.pf-input, .pf-textarea, .pf-select {
    width: 100%;
    padding: 10px 13px;
    border: 1px solid #dbe0ea;
    border-radius: var(--pf-r-md);
    background: #fff;
    font-family: inherit;
    font-size: .9rem;
    line-height: 1.6;
    color: var(--pf-ink-900);
    transition: border-color .16s ease, box-shadow .16s ease, background .16s ease;
}
.pf-select {
    appearance: none;
    padding-inline-end: 38px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%2364748b'%3E%3Cpath d='M3.2 5.8 8 10.6l4.8-4.8'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 13px center;
    background-size: 15px;
    cursor: pointer;
}
html[dir="rtl"] .pf-select { background-position: left 13px center; }
.pf-textarea { resize: vertical; min-height: 104px; }
.pf-input::placeholder, .pf-textarea::placeholder { color: #9aa4b5; }
.pf-input:hover, .pf-textarea:hover, .pf-select:hover { border-color: #c7d2fe; }
.pf-input:focus, .pf-textarea:focus, .pf-select:focus {
    outline: none;
    border-color: var(--pf-brand-500);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, .14);
}
.pf-input.is-invalid, .pf-textarea.is-invalid, .pf-select.is-invalid { border-color: #f87171; background: #fff7f7; }
.pf-input.is-invalid:focus, .pf-textarea.is-invalid:focus, .pf-select.is-invalid:focus { box-shadow: 0 0 0 4px rgba(220, 38, 38, .13); }
.pf-hint { margin: 6px 0 0; font-size: .76rem; color: var(--pf-ink-500); }
.pf-err { display: flex; align-items: center; gap: 5px; margin: 6px 0 0; font-size: .78rem; font-weight: 600; color: #dc2626; }
.pf-err::before { content: '\F33A'; font-family: 'bootstrap-icons'; font-size: .9em; }
.pf-counter { margin-inline-start: auto; font-weight: 600; color: var(--pf-ink-500); }
.pf-label-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.pf-label-row .pf-label { margin-bottom: 0; }

/* Input with trailing button (password reveal) */
.pf-input-wrap { position: relative; }
.pf-input-wrap .pf-input { padding-inline-end: 44px; }
.pf-reveal {
    position: absolute; inset-inline-end: 6px; top: 50%; transform: translateY(-50%);
    display: grid; place-items: center;
    width: 32px; height: 32px;
    border: none; border-radius: 9px;
    background: transparent;
    color: var(--pf-ink-500);
    cursor: pointer;
    transition: background .16s ease, color .16s ease;
}
.pf-reveal:hover { background: var(--pf-brand-50); color: var(--pf-brand-600); }
.pf-reveal i { font-size: 1rem; }

/* Switch */
.pf-switch { display: inline-flex; align-items: center; gap: 12px; cursor: pointer; user-select: none; }
.pf-switch input { position: absolute; opacity: 0; width: 1px; height: 1px; margin: 0; }
.pf-switch-track {
    width: 44px; height: 25px; flex: none;
    border-radius: 999px;
    background: #d3d9e4;
    position: relative;
    transition: background .18s ease;
}
.pf-switch-thumb {
    position: absolute; top: 3px; inset-inline-start: 3px;
    width: 19px; height: 19px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, .25);
    transition: transform .18s ease;
}
.pf-switch input:checked + .pf-switch-track { background: linear-gradient(135deg, var(--pf-brand-500), var(--pf-violet-500)); }
.pf-switch input:checked + .pf-switch-track .pf-switch-thumb { transform: translateX(19px); }
html[dir="rtl"] .pf-switch input:checked + .pf-switch-track .pf-switch-thumb { transform: translateX(-19px); }
.pf-switch input:focus-visible + .pf-switch-track { box-shadow: 0 0 0 4px rgba(99, 102, 241, .22); }
.pf-switch-label { font-size: .92rem; font-weight: 700; color: var(--pf-ink-800); }

.pf-switch-panel {
    margin-top: 16px;
    padding: 16px;
    border-radius: var(--pf-r-md);
    background: var(--pf-brand-50);
    border: 1px solid var(--pf-brand-100);
    transition: opacity .2s ease;
}
.pf-switch-panel.is-off { opacity: .45; }
.pf-switch-panel.is-off .pf-input,
.pf-switch-panel.is-off .pf-select { pointer-events: none; }

/* Avatar dropzone */
.pf-drop {
    position: relative;
    display: block;
    padding: 18px 14px;
    border: 2px dashed var(--pf-brand-200);
    border-radius: var(--pf-r-md);
    background: var(--pf-brand-50);
    text-align: center;
    cursor: pointer;
    transition: background .16s ease, border-color .16s ease, transform .16s ease;
}
.pf-drop:hover { background: #e8ecff; border-color: var(--pf-brand-500); transform: translateY(-1px); }
.pf-drop i { display: block; font-size: 1.3rem; color: var(--pf-brand-600); margin-bottom: 5px; }
.pf-drop strong { display: block; font-size: .87rem; color: var(--pf-ink-800); }
.pf-drop span { font-size: .76rem; color: var(--pf-ink-500); }
.pf-drop input { position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; }

.pf-filechip {
    display: none;
    align-items: center; gap: 9px;
    margin-top: 10px;
    padding: 9px 12px;
    border-radius: var(--pf-r-sm);
    background: var(--pf-brand-50);
    border: 1px solid var(--pf-brand-200);
    font-size: .82rem;
    font-weight: 600;
    color: var(--pf-brand-700);
}
.pf-filechip.show { display: flex; }
.pf-filechip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pf-filechip button { margin-inline-start: auto; border: none; background: none; color: var(--pf-brand-600); cursor: pointer; font-size: 1rem; line-height: 1; padding: 0; }
.pf-filechip button:hover { color: var(--pf-rose-600); }

.pf-btn-ghost-danger {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    width: 100%;
    margin-top: 10px;
    padding: 9px 12px;
    border: 1px solid #fecdd3;
    border-radius: var(--pf-r-sm);
    background: var(--pf-rose-50);
    color: #be123c;
    font-family: inherit;
    font-size: .8rem;
    font-weight: 700;
    cursor: pointer;
    transition: background .16s ease, color .16s ease, border-color .16s ease;
}
.pf-btn-ghost-danger:hover { background: #e11d48; border-color: #e11d48; color: #fff; }

/* Sticky action bar */
.pf-actions {
    position: sticky;
    bottom: 12px;
    z-index: 5;
    display: flex; align-items: center; gap: 10px;
    margin-top: 16px;
    padding: 12px 16px;
    background: rgba(255, 255, 255, .92);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid var(--pf-line);
    border-radius: var(--pf-r-lg);
    box-shadow: 0 12px 32px -12px rgba(15, 23, 42, .25);
    animation: pf-rise .55s cubic-bezier(.22, .85, .3, 1) .3s both;
}
.pf-actions .pf-btn { flex: none; }
.pf-actions-note { margin: 0; font-size: .78rem; color: var(--pf-ink-500); }
.pf-actions-spacer { margin-inline-start: auto; }

/* ============================================================
   Password strength
   ============================================================ */
.pf-strength { margin-top: 12px; }
.pf-strength-track { height: 7px; border-radius: 999px; background: var(--pf-line-soft); overflow: hidden; }
.pf-strength-fill { height: 100%; width: 0; border-radius: 999px; transition: width .3s ease, background .3s ease; }
.pf-strength-text { margin-top: 6px; font-size: .78rem; font-weight: 700; color: var(--pf-ink-500); }
.pf-req { list-style: none; margin: 10px 0 0; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 14px; }
.pf-req li { display: flex; align-items: center; gap: 7px; font-size: .79rem; color: var(--pf-ink-500); transition: color .18s ease; }
.pf-req li i { font-size: .78rem; color: #cbd5e1; transition: color .18s ease; }
.pf-req li.met { color: var(--pf-ok-600); }
.pf-req li.met i { color: var(--pf-ok-600); }
.pf-match { margin-top: 9px; font-size: .79rem; font-weight: 700; }
.pf-match.ok  { color: var(--pf-ok-600); }
.pf-match.bad { color: #dc2626; }

/* ============================================================
   Content blocks
   ============================================================ */
.pf-bio { margin: 0; font-size: .95rem; line-height: 2; color: var(--pf-ink-700); white-space: pre-line; }
.pf-bio-quote { padding-inline-start: 18px; border-inline-start: 3px solid var(--pf-brand-200); }
.pf-empty {
    display: flex; align-items: flex-start; gap: 13px;
    padding: 16px;
    border-radius: var(--pf-r-md);
    border: 1px dashed var(--pf-brand-200);
    background: var(--pf-brand-50);
    color: var(--pf-ink-600);
    font-size: .87rem;
}
.pf-empty > i { font-size: 1.15rem; color: var(--pf-brand-500); flex: none; }
.pf-empty strong { display: block; color: var(--pf-ink-800); font-weight: 700; }
.pf-empty .pf-btn { margin-top: 9px; }
.pf-note {
    display: flex; align-items: flex-start; gap: 11px;
    padding: 14px 16px;
    border-radius: var(--pf-r-md);
    font-size: .84rem;
    border: 1px solid transparent;
}
.pf-note > i { font-size: 1.05rem; flex: none; margin-top: 2px; }
.pf-note strong { display: block; margin-bottom: 2px; }
.pf-note-amber { background: var(--pf-amber-50); border-color: #fde68a; color: #92400e; }
.pf-note-amber strong { color: #78350f; }
.pf-note-green { background: var(--pf-ok-50); border-color: #a7f3d0; color: #065f46; }
.pf-note-green strong { color: #064e3b; }
.pf-note-sky { background: var(--pf-sky-50); border-color: #bae6fd; color: #075985; }
.pf-note-sky strong { color: #0c4a6e; }

.pf-security { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.pf-security-txt { flex: 1; min-width: 190px; }
.pf-security-txt strong { display: block; font-size: .95rem; color: var(--pf-ink-800); }
.pf-security-txt span { font-size: .8rem; color: var(--pf-ink-500); }

.pf-tips { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
.pf-tips li { display: flex; align-items: flex-start; gap: 11px; font-size: .86rem; color: var(--pf-ink-600); }
.pf-tips-ico {
    width: 30px; height: 30px; flex: none;
    border-radius: 9px;
    display: grid; place-items: center;
    font-size: .9rem;
    background: var(--pf-brand-50);
    color: var(--pf-brand-600);
}
.pf-tips strong { display: block; color: var(--pf-ink-800); font-weight: 700; }

@keyframes pf-rise {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: none; }
}

/* ============================================================
   Responsive
   ============================================================ */
@media (max-width: 1080px) {
    .pf-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 900px) {
    .pf-grid, .pf-grid-even { grid-template-columns: minmax(0, 1fr); }
    .pf-col-side { position: static; }
}
@media (max-width: 640px) {
    .pf-meter { padding: 14px 15px 12px; }
    .pf-meter-head { flex-wrap: wrap; }
    .pf-meter-foot { flex-direction: column; align-items: stretch; }
    .pf-meter-cta { justify-content: center; }
    .pf-page { font-size: 14.5px; }
    .pf-hero { border-radius: var(--pf-r-lg); }
    .pf-hero-main { flex-direction: column; align-items: flex-start; gap: 16px; }
    .pf-avatar-ring { align-self: center; }
    .pf-hero-actions, .pf-hero-actions .pf-btn { width: 100%; }
    .pf-tabs { overflow-x: auto; }
    .pf-tab { flex: 0 0 auto; }
    .pf-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .pf-stat { padding: 14px 10px; }
    .pf-stat-num { font-size: 1.4rem; }
    .pf-card-bd { padding: 15px; }
    .pf-field-row { grid-template-columns: 1fr; }
    .pf-row { gap: 10px; }
    .pf-chip > span { max-width: 15ch; }
    .pf-req { grid-template-columns: minmax(0, 1fr); }
    .pf-actions { flex-direction: column-reverse; bottom: 8px; }
    .pf-actions .pf-btn { width: 100%; }
    .pf-actions-note { display: none; }
}
@media (max-width: 380px) {
    .pf-stats { grid-template-columns: minmax(0, 1fr); }
}

@media (prefers-reduced-motion: reduce) {
    .pf-page *, .pf-page *::before, .pf-page *::after {
        animation-duration: .001ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .001ms !important;
    }
}
</style>
