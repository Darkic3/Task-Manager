@extends('layouts.app')

@section('title', $view === 'month' ? __('My Month') : ($view === 'week' ? __('My Week') : __('My Day')))

@push('styles')
<style>
    .main-content { padding: 18px 20px 60px; background: #f8fafc; min-height: 100vh; }

    /* Header */
    .pl-header {
        background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
        border-radius: 16px; padding: 16px 22px; color: white; margin-bottom: 16px;
        position: relative; overflow: hidden; border: 1px solid rgba(255,255,255,0.1);
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.25);
    }
    .pl-header::before {
        content: ''; position: absolute; top: -30px; right: -30px; width: 140px; height: 140px;
        background: rgba(255, 255, 255, 0.12); border-radius: 50%;
    }
    .pl-header::after {
        content: ''; position: absolute; bottom: -40px; right: 80px; width: 100px; height: 100px;
        background: rgba(255, 255, 255, 0.08); border-radius: 50%;
    }
    .pl-header-title { font-weight: 800; font-size: 22px; margin: 0; position: relative; z-index: 1; letter-spacing: -0.02em; }
    .pl-header-sub   { font-size: 13px; opacity: 0.9; margin: 3px 0 0; position: relative; z-index: 1; font-weight: 500; }

    /* Toolbar */
    .pl-toolbar {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        background: white; border: 1px solid #e2e8f0; border-radius: 14px;
        padding: 10px 14px; margin-bottom: 16px; flex-wrap: wrap; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    }
    .pl-toggle { display: flex; background: #f1f5f9; border-radius: 10px; padding: 3px; gap: 2px; }
    .pl-toggle-btn {
        padding: 6px 16px; border-radius: 8px; font-size: 12.5px; font-weight: 700;
        color: #64748b; text-decoration: none; display: flex; align-items: center; gap: 6px; transition: all 0.15s;
    }
    .pl-toggle-btn.active { background: white; color: #0f172a; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06); }
    .pl-nav { display: flex; align-items: center; gap: 6px; }
    .pl-nav-btn {
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        border: 1px solid #e2e8f0; border-radius: 8px; background: white; color: #64748b;
        text-decoration: none; transition: all 0.15s;
    }
    .pl-nav-btn:hover { border-color: #c7d2fe; color: #4338ca; background: #f8faff; }
    .pl-today-btn {
        padding: 6px 14px; border: 1px solid #e2e8f0; border-radius: 8px; background: white;
        color: #334155; font-size: 12px; font-weight: 700; text-decoration: none; transition: all 0.15s;
    }
    .pl-today-btn:hover { border-color: #c7d2fe; color: #4338ca; background: #f8faff; }

    /* Stat chips */
    .pl-stats { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
    .pl-stat {
        display: flex; align-items: center; gap: 8px; background: white; border: 1px solid #e2e8f0;
        border-radius: 12px; padding: 8px 16px; font-size: 12.5px; color: #64748b; font-weight: 600;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    }
    .pl-stat strong { font-size: 15px; color: #0f172a; font-weight: 800; }
    .pl-stat.overdue strong { color: #dc2626; }

    /* Section */
    .pl-section { background: white; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; margin-bottom: 16px; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04); }
    .pl-section-head {
        display: flex; align-items: center; gap: 10px; padding: 12px 18px;
        background: #f8fafc; border-bottom: 1px solid #f1f5f9;
    }
    .pl-section-head i{font-size:14px;}
    .pl-section-title{font-size:13.5px;font-weight:700;color:#1a1d23;}
    .pl-section-count{
        margin-inline-start:auto;background:#f0f1f3;border-radius:20px;padding:2px 10px;
        font-size:11px;font-weight:700;color:#8a8f98;
    }
    .pl-section-body{padding:8px;display:flex;flex-direction:column;gap:6px;}
    .pl-empty{padding:26px 16px;text-align:center;color:#94a3b8;font-size:12.5px;font-weight:500;}
    .pl-empty i{display:block;font-size:26px;margin-bottom:8px;color:#cbd5e1;}

    /* ── Empty-day CTA: pull an existing (unscheduled) task into this day ── */
    .pl-qadd{position:relative;}
    .pl-qadd-btn{
        position:relative;display:flex;align-items:center;gap:13px;width:100%;overflow:hidden;
        padding:14px 16px;border-radius:15px;cursor:pointer;text-align:start;
        border:1.5px dashed #ddd6fe;background:linear-gradient(135deg,#fbfaff 0%,#f5f3ff 100%);
        font-family:inherit;transition:border-color .18s ease,transform .18s ease,box-shadow .18s ease,background .18s ease;}
    .pl-qadd-btn:hover{border-color:#c4b5fd;border-style:solid;transform:translateY(-1px);
        box-shadow:0 14px 28px -18px rgba(76,29,149,.6);}
    .pl-qadd-btn:focus-visible{outline:2px solid #7c3aed;outline-offset:2px;}
    .pl-qadd-glow{position:absolute;inset-inline-start:-40%;top:-60%;width:45%;height:220%;pointer-events:none;
        background:linear-gradient(90deg,transparent,rgba(255,255,255,.85),transparent);transform:skewX(-18deg);
        transition:inset-inline-start .55s ease;}
    .pl-qadd-btn:hover .pl-qadd-glow{inset-inline-start:130%;}
    .pl-qadd-ico{flex-shrink:0;width:40px;height:40px;display:grid;place-items:center;border-radius:13px;color:#fff;font-size:17px;
        background:linear-gradient(135deg,#7c3aed,#6366f1);
        box-shadow:0 10px 20px -10px rgba(109,40,217,.95),inset 0 1px 0 rgba(255,255,255,.3);}
    .pl-qadd-txt{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px;}
    .pl-qadd-txt b{font-size:13px;font-weight:800;color:#241d4b;letter-spacing:-.01em;}
    .pl-qadd-txt small{font-size:11px;font-weight:600;color:#7d76a0;line-height:1.5;}
    .pl-qadd-arrow{flex-shrink:0;color:#a79ccb;font-size:12px;transition:transform .18s ease;}
    .pl-qadd-btn[aria-expanded="true"] .pl-qadd-arrow{transform:rotate(180deg);}
    .pl-qadd-btn[aria-expanded="true"]{border-style:solid;border-color:#c4b5fd;background:linear-gradient(135deg,#fff,#f7f3ff);
        box-shadow:0 10px 24px -16px rgba(76,29,149,.55);}
    .pl-qadd-panel{margin-top:8px;border:1px solid #e7e1fb;border-radius:15px;overflow:hidden;background:#fff;
        box-shadow:0 18px 38px -22px rgba(23,20,48,.5);animation:plQaddIn .18s cubic-bezier(.16,1,.3,1);}
    .pl-qadd-panel[hidden]{display:none;}
    @keyframes plQaddIn{from{opacity:0;transform:translateY(-6px);}to{opacity:1;transform:none;}}
    .pl-qadd-search{display:flex;align-items:center;gap:9px;padding:9px 12px;border-bottom:1px solid #f1eefb;background:#faf8ff;}
    .pl-qadd-search>i{color:#a79ccb;font-size:13px;}
    .pl-qadd-search input{flex:1;min-width:0;border:0;background:transparent;outline:none;font-family:inherit;
        font-size:13px;font-weight:600;color:#1e293b;}
    .pl-qadd-search input::placeholder{color:#a9a2c4;font-weight:500;}
    .pl-qadd-clear{border:0;background:#f1edfd;color:#6b6390;width:26px;height:26px;border-radius:8px;cursor:pointer;
        display:grid;place-items:center;font-size:11px;transition:all .14s ease;}
    .pl-qadd-clear:hover{background:#e5dcfb;color:#4c1d95;}
    .pl-qadd-clear[hidden]{display:none;}
    .pl-qadd-list{max-height:264px;overflow-y:auto;padding:6px;display:flex;flex-direction:column;gap:3px;}
    .pl-qadd-item{display:flex;align-items:center;gap:10px;width:100%;padding:9px 11px;border:1px solid transparent;
        border-radius:11px;background:transparent;cursor:pointer;text-align:start;font-family:inherit;transition:all .13s ease;}
    .pl-qadd-item:hover:not(:disabled){background:#faf8ff;border-color:#ece5fc;}
    .pl-qadd-item:focus-visible{outline:2px solid #7c3aed;outline-offset:-2px;}
    .pl-qadd-item:disabled{opacity:.55;cursor:wait;}
    .pl-qadd-dot{flex-shrink:0;width:8px;height:8px;border-radius:50%;}
    .pl-qadd-name{flex:1;min-width:0;font-size:12.5px;font-weight:700;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .pl-qadd-proj{flex-shrink:0;max-width:110px;font-size:10.5px;font-weight:700;color:#8b84a8;background:#f5f2ff;
        border:1px solid #ece5fc;border-radius:20px;padding:2px 9px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .pl-qadd-go{flex-shrink:0;width:24px;height:24px;display:grid;place-items:center;border-radius:8px;color:#7c3aed;
        background:#f2edff;font-size:11px;opacity:0;transform:translateX(-4px);transition:all .14s ease;}
    .pl-qadd-item:hover:not(:disabled) .pl-qadd-go{opacity:1;transform:none;}
    .pl-qadd-note{padding:14px 12px;text-align:center;font-size:11.5px;font-weight:600;color:#9b94b8;line-height:1.7;}
    .pl-qadd-note i{display:block;font-size:20px;margin-bottom:6px;color:#c9c2e4;}
    /* Once the day has tasks, the empty-state CTA steps aside. */
    #plPendingBody:has([data-task-item]) .pl-qadd,
    #plPendingBody:has(.pl-period-group) .pl-qadd{display:none;}
    @media (max-width:576px){
        .pl-qadd-btn{padding:12px 13px;gap:10px;}
        .pl-qadd-ico{width:36px;height:36px;font-size:15px;}
        .pl-qadd-txt small{display:none;}
    }

    /* ── Desktop duo: side-by-side pairs, day view only (≥992px). Below that, normal stack. ── */
    @media (min-width: 992px) {
        .pl-duo{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;margin-bottom:16px;}
        .pl-duo-single{grid-template-columns:1fr;}
        .pl-duo > *{margin-bottom:0 !important;min-width:0;}
        /* Priority grids: tasks slightly wider than routines, day-log wider than side rail */
        .pl-main-grid{grid-template-columns:1.12fr .88fr;}
        .pl-lower-grid{grid-template-columns:1.55fr .85fr;}
    }

    /* ══ Planner hierarchy v2 — priority: NextUp hero > Overdue urgent > Tasks/Routines > DayLog + Workout rail ══ */
    /* Top command zone: slimmer banner + sticky toolbar + compact KPIs */
    .pl-header{padding:14px 20px;margin-bottom:12px;border-radius:14px;}
    .pl-header-title{font-size:20px;}
    .pl-header-sub{font-size:12.5px;}
    .pl-toolbar{position:sticky;top:0;z-index:60;backdrop-filter:blur(10px);background:rgba(255,255,255,.92);margin-bottom:12px;}
    .pl-stats{margin-bottom:12px;gap:8px;}
    .pl-stat{padding:7px 13px;border-radius:11px;font-size:12px;}
    .pl-stat i{width:26px;height:26px;display:grid;place-items:center;border-radius:8px;background:#f1f5f9;color:#64748b;font-size:13px;}
    .pl-stat.overdue{border-color:#fecaca;background:#fff5f5;}
    .pl-stat.overdue i{background:#fee2e2;color:#dc2626;}
    .pl-daybar{margin:0 0 16px;}
    .pl-daybar-track{height:6px;}
    .pl-daybar-label{font-size:11px;min-width:44px;}
    /* Hero Next-Up: full-width, unmissable, owns the first viewport */
    .pl-hero{margin-bottom:14px;}
    .pl-hero .pl-next-wrap{margin-bottom:0;}
    .pl-hero .pl-next-card{
        padding:18px 22px;border-width:2px;border-color:#c4b5fd;
        background:linear-gradient(135deg,#ffffff 0%,#faf5ff 40%,#f0eaff 100%);
        box-shadow:0 12px 32px -10px rgba(124,58,237,.28),0 2px 8px rgba(15,23,42,.05);
    }
    .pl-hero .pl-next-card::before{
        content:'';position:absolute;inset-inline-start:0;top:0;bottom:0;width:5px;border-radius:18px 0 0 18px;
        background:linear-gradient(180deg,#7c3aed,#a78bfa);
    }
    html[dir="rtl"] .pl-hero .pl-next-card::before{border-radius:0 18px 18px 0;}
    .pl-hero .pl-next-title{font-size:18px;}
    .pl-hero .pl-action-btn{padding:10px 22px;font-size:13px;}
    /* Urgent overdue: full-width alert strip directly under hero */
    .pl-urgent{margin-bottom:16px !important;border:1.5px solid #fecaca !important;border-inline-start:5px solid #dc2626 !important;
        box-shadow:0 8px 24px -12px rgba(220,38,38,.35) !important;}
    .pl-urgent .pl-section-head{padding:10px 16px;}
    .pl-urgent-dot{width:8px;height:8px;border-radius:50%;background:#dc2626;flex-shrink:0;animation:plUrgentPulse 1.6s ease infinite;}
    @keyframes plUrgentPulse{0%,100%{box-shadow:0 0 0 0 rgba(220,38,38,.4);}50%{box-shadow:0 0 0 6px rgba(220,38,38,0);}}
    /* Section priority tints: tasks = primary work, routines = habits */
    .pl-section-primary{border-color:#c7d2fe;box-shadow:0 4px 16px -8px rgba(79,70,229,.25);}
    .pl-section-primary > .pl-section-head{background:linear-gradient(135deg,#eef2ff 0%,#f5f3ff 100%);border-bottom-color:#e0e7ff;}
    .pl-section-habit > .pl-section-head{background:linear-gradient(135deg,#faf5ff 0%,#f5f3ff 100%);}
    .pl-section-head .pl-head-sub{font-size:11px;font-weight:600;color:#8a8f98;}
    /* Workout as secondary rail card — never competes with hero */
    .pl-workout-mini{border:1px solid #e9e4fb !important;background:linear-gradient(180deg,#fff 0%,#faf8ff 100%) !important;}
    .pl-workout-mini > .pl-section-head{background:transparent !important;border-bottom:1px dashed #e9e4fb !important;padding:10px 14px !important;}
    .pl-workout-mini .pl-section-body{padding:12px 14px !important;}
    .pl-workout-mini h6{font-size:13.5px !important;}
    .pl-workout-chip{font-size:10.5px !important;padding:3px 8px !important;}
    .pl-side-stack{display:flex;flex-direction:column;gap:16px;min-width:0;}
    .pl-side-stack > *{margin-bottom:0 !important;}
    @media (max-width:991.98px){
        .pl-toolbar{position:static;}
        .pl-hero .pl-next-card{padding:14px 16px;}
        .pl-hero .pl-next-title{font-size:16px;}
        .pl-hero .pl-next-actions{width:100%;}
        .pl-hero .pl-action-btn{flex:1;justify-content:center;}
    }

    /* ── Done as a collapsed archive + ultra-wide container cap ── */
    .pl-done > summary{list-style:none;cursor:pointer;}
    .pl-done > summary::-webkit-details-marker{display:none;}
    .pl-done > summary::marker{content:'';}
    .pl-done > summary:focus-visible{outline:2px solid #16a34a;outline-offset:-2px;}
    .pl-done-chev{color:#9aa1b5;font-size:12px;transition:transform .15s ease;}
    .pl-done[open] .pl-done-chev{transform:rotate(180deg);}
    @media (min-width: 1600px) {
        .main-content{max-width:1500px;margin-inline:auto;}
    }

    /* Task row */
    .pl-task{
        display:flex;align-items:flex-start;gap:10px;background:white;border:1px solid #eceef1;
        border-radius:10px;padding:10px 12px;transition:all .15s ease;
    }
    .pl-task:hover{box-shadow:0 3px 12px rgba(0,0,0,.06);border-color:#d8dae0;}
    .pl-check{position:relative;flex-shrink:0;margin-top:2px;cursor:pointer;}
    .pl-check input{position:absolute;opacity:0;width:0;height:0;}
    .pl-check-box{
        width:20px;height:20px;border:2px solid #cbd5e1;border-radius:7px;
        display:flex;align-items:center;justify-content:center;color:transparent;
        font-size:11px;transition:all .15s;
    }
    .pl-check:hover .pl-check-box{border-color:#7c3aed;}
    .pl-check input:checked + .pl-check-box{background:#16a34a;border-color:#16a34a;color:white;}
    .pl-task-body{flex:1;min-width:0;}
    .pl-task-title{font-size:13.5px;font-weight:600;color:#1e293b;line-height:1.45;word-break:break-word;}
    .pl-task.is-done .pl-task-title{text-decoration:line-through;color:#94a3b8;}
    .pl-expand{
        margin-inline-start:auto;flex-shrink:0;width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;
        border:none;background:transparent;color:#94a3b8;cursor:pointer;border-radius:6px;font-size:12px;
    }
    .pl-expand:hover{color:#7c3aed;background:#faf5ff;}
    .pl-expand i{transition:transform .15s;}
    .pl-expand.open i{transform:rotate(180deg);}

    /* ✗ "did not do it" button + skipped-day state */
    .pl-routine-skip{
        width:21px;height:21px;flex-shrink:0;display:inline-grid;place-items:center;
        border:1px solid #e5e7eb;background:#fafbfc;color:#c1c4cc;border-radius:50%;
        font-size:9px;line-height:1;cursor:pointer;padding:0;transition:all .12s;
    }
    .pl-routine-skip:hover{border-color:#fca5a5;background:#fef2f2;color:#dc2626;}
    .pl-routine-skip.active{background:#ef4444;border-color:#ef4444;color:#fff;}
    .pl-routine-skip:disabled{opacity:.5;cursor:wait;}
    .pl-routine-skip:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(239,68,68,.25);}
    /* ↔ "move to tomorrow" button for interval/weekly routines */
    .pl-routine-postpone{
        width:auto;min-height:21px;flex-shrink:0;display:inline-flex;align-items:center;gap:4px;
        border:1px solid #dbeafe;background:#eff6ff;color:#2563eb;border-radius:999px;
        font-size:11px;line-height:1;cursor:pointer;padding:0 6px;transition:all .12s;
    }
    .pl-routine-postpone:hover{border-color:#93c5fd;background:#dbeafe;color:#1d4ed8;}
    .pl-routine-postpone:disabled{opacity:.5;cursor:wait;}
    .pl-routine-postpone:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(37,99,235,.25);}
    .pl-skip-tag{
        font-size:10.5px;font-weight:700;color:#b91c1c;background:#fee2e2;
        border-radius:20px;padding:1px 8px;display:inline-flex;align-items:center;gap:4px;
        text-transform:none;white-space:nowrap;
    }
    .pl-task.is-skipped .pl-task-title{text-decoration:line-through;color:#94a3b8;}
    .pl-task.is-skipped .pl-task-meta{opacity:.8;}
    /* Fully failed: the habit ring itself turns red with an ✗ core. */
    .pl-task.is-skipped .habit-ring .ring-fg{stroke:#ef4444;}
    .pl-task.is-skipped .habit-ring .ring-bg{stroke:#fee2e2;}
    .pl-task.is-skipped .routine-check-box{border-color:#fca5a5;color:#dc2626;}
    /* Partially settled (every step resolved, mix of done + failed):
       closed look, locked checked box, green settled tag. */
    .pl-task.is-settled{background:#fafbfc;}
    .pl-task.is-settled .pl-task-title{color:#475569;}
    .pl-settled-tag{
        font-size:10.5px;font-weight:800;color:#15803d;background:#e9f9f0;
        border:1px solid #bbf7d0;border-radius:20px;padding:1px 9px;margin-left:6px;
        display:inline-flex;align-items:center;gap:4px;white-space:nowrap;vertical-align:1px;
    }
    /* Locked bulk checkbox: reopening happens step by step, never by re-tick. */
    .pl-habit.is-locked{cursor:not-allowed;}
    .pl-habit.is-locked input:disabled + .habit-ring,
    .pl-habit.is-locked input:disabled + .routine-check-box{cursor:not-allowed;}
    /* Failed tasks: explicit "won't do it" — red failed state with note. */
    .pl-task.is-failed{background:#fff7f7;border-color:#f3c2c2;}
    .pl-task.is-failed:hover{border-color:#f0a8a8;box-shadow:0 3px 12px rgba(220,38,38,.08);}
    .pl-task.is-failed .pl-task-title{color:#b91c1c;}
    .pl-task.is-failed .pl-check-box{border-color:#f0a8a8;}
    .pl-fail-tag{
        font-size:10.5px;font-weight:800;color:#fff;background:#dc2626;
        border-radius:20px;padding:1px 9px;display:inline-flex;align-items:center;gap:4px;
        text-transform:none;white-space:nowrap;letter-spacing:0;
    }
    .pl-fail-note{
        font-size:11px;color:#991b1b;background:#fef2f2;border:1px solid #fbd5d5;
        border-radius:8px;padding:4px 9px;margin-top:6px;line-height:1.5;word-break:break-word;
        display:flex;align-items:flex-start;gap:6px;
    }
    .pl-fail-note i{flex-shrink:0;margin-top:2px;}
    .pl-task-act-fail:hover{background:#dc2626 !important;border-color:#dc2626 !important;color:#fff !important;
        transform:translateY(-1px);box-shadow:0 4px 10px rgba(220,38,38,.28);}
    .pl-task-act-fail.active{background:#dc2626 !important;border-color:#dc2626 !important;color:#fff !important;}
    /* Clicking the routine row expands its details */
    .pl-routine[data-routine-item] .pl-task-title,
    .pl-routine[data-routine-item] .pl-task-meta{cursor:pointer;}

    /* ── Routine detail modal (big / tracked routines) ── */
    .pl-modal{
        position:fixed;inset:0;z-index:1090;display:flex;
        align-items:center;justify-content:center;padding:20px 16px;
    }
    .pl-modal[hidden]{display:none;}
    .pl-modal-backdrop{
        position:absolute;inset:0;background:rgba(23,20,32,.42);
        backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);
    }
    .pl-modal-dialog{
        position:relative;background:#fff;border-radius:20px;width:min(540px,100%);
        max-height:min(88vh,760px);
        display:flex;flex-direction:column;overflow:hidden;
        border:1px solid #eceaf2;
        box-shadow:0 1px 2px rgba(15,23,42,.06),
                   0 12px 28px -8px rgba(15,23,42,.16),
                   0 32px 64px -16px rgba(15,23,42,.22);
        animation:plModalIn .2s cubic-bezier(.16,1,.3,1);
    }
    @keyframes plModalIn {
        from{opacity:0;transform:translateY(10px) scale(.985);}
        to{opacity:1;transform:none;}
    }
    .pl-modal-head{
        display:flex;align-items:flex-start;gap:10px;
        padding:16px 16px 14px 18px;
        background:#fcfcfd;border-bottom:1px solid #eef0f4;
        flex-shrink:0;
    }
    .pl-modal-title{
        font-size:15.5px;font-weight:800;color:#1a1d23;line-height:1.35;
        display:flex;align-items:center;gap:8px;
    }
    /* Accent rule that ties the header to the accent used inside the body */
    .pl-modal-title::before{
        content:'';width:3px;height:16px;border-radius:99px;
        background:#7c3aed;flex-shrink:0;
    }
    .pl-modal-sub{font-size:12px;color:#8a8f98;margin-top:3px;line-height:1.5;}
    .pl-modal-x{
        margin-inline-start:auto;border:none;background:#f2f3f5;color:#6b7385;
        width:30px;height:30px;border-radius:9px;font-size:17px;line-height:1;
        cursor:pointer;flex-shrink:0;display:grid;place-items:center;
        transition:background .15s,color .15s;
    }
    .pl-modal-x:hover{background:#e9eaee;color:#1a1d23;}
    .pl-modal-x:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(124,58,237,.25);}
    .pl-modal-body{
        padding:16px 18px 18px;overflow-y:auto;flex:1;
        display:flex;flex-direction:column;gap:12px;
    }
    .pl-modal-body::-webkit-scrollbar{width:8px;}
    .pl-modal-body::-webkit-scrollbar-track{background:transparent;}
    .pl-modal-body::-webkit-scrollbar-thumb{background:#dcdee3;border-radius:99px;border:2px solid #fff;}
    .pl-modal-body::-webkit-scrollbar-thumb:hover{background:#c8ccd3;}

    /* ── Modal body: the moved .pl-details panel, rebuilt for reading ──
       Everything in here had inline-row sizing. Inside a 540px modal the
       same chips and inputs read as a loose pile, so each group is
       reconstructed as a full-width card stack in the modal only. */
    .pl-modal-body .pl-details{display:flex;flex-direction:column;gap:16px;}

    /* Section caps: tiny translated labels, scoped to the modal only so the
       inline accordion keeps its compact chip layout. Only selectors that
       receive real content are given the ::before box, so no phantom
       spacers appear on avoid steps or value logs. */
    .pl-modal-body .pl-steps:not(.pl-avoid-steps)::before,
    .pl-modal-body .pl-logsets::before{
        content:'';display:block;width:100%;
        font-size:10.5px;font-weight:800;letter-spacing:.06em;
        color:#9599a3;
    }
    .pl-modal-body .pl-steps:not(.pl-avoid-steps)::before{content:'{{ __('Steps') }}';}
    .pl-modal-body .pl-logsets::before{content:'{{ __('Sets') }}';}

    /* Steps: chips become full-width rows */
    .pl-modal-body .pl-steps{flex-direction:column;flex-wrap:nowrap;gap:7px;margin-top:0;}
    .pl-modal-body .pl-step{
        width:100%;padding:10px 13px;border-radius:12px;
        border:1px solid #e9ebef;background:#fff;
        font-size:12.5px;color:#3d4149;gap:8px;
        transition:background .12s,border-color .15s;
    }
    .pl-modal-body .pl-step i{font-size:15px;flex-shrink:0;}
    .pl-modal-body .pl-step:hover{border-color:#ddd6fe;background:#fcfcfd;color:#1a1d23;}
    .pl-modal-body .pl-step.done{background:#f4fbf7;border-color:#bcdfc9;color:#29774b;}
    .pl-modal-body .pl-step.done i{color:#30a46c;}
    .pl-modal-body .pl-step-schedule{
        margin-inline-start:auto;font-size:10.5px;gap:4px;
        padding:2px 8px;border-radius:99px;background:#fafbfc;
        border:1px solid #eef0f3;
    }
    .pl-modal-body .pl-step.done .pl-step-schedule{background:#fff;border-color:#d3ecd9;}

    /* Sets: name on its own line, inputs + Log beneath */
    .pl-modal-body .pl-logsets{gap:8px;margin-top:0;}
    .pl-modal-body .pl-logset{
        background:#fcfcfd;border:1px solid #e9ebef;border-radius:12px;padding:11px 13px;
    }
    .pl-modal-body .pl-logset:hover{border-color:#ddd9ea;}
    .pl-modal-body .pl-logset-name{
        flex:1 1 100%;display:flex;align-items:center;gap:6px;
        font-weight:700;color:#1a1d23;margin-bottom:2px;
    }
    .pl-modal-body .pl-logset-name .pl-step-schedule{margin-inline-start:auto;}
    .pl-modal-body .pl-logset input{
        width:62px;height:32px;padding:0;text-align:center;
        border:1px solid #e3e4e8;border-radius:9px;font-size:12.5px;
        font-variant-numeric:tabular-nums;font-weight:600;color:#1a1d23;
        outline:none;background:#fff;
        transition:border-color .15s,box-shadow .15s;
    }
    .pl-modal-body .pl-logset input:hover{border-color:#d6d9de;}
    .pl-modal-body .pl-logset input:focus{
        border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);
    }
    .pl-modal-body .pl-logset input.has-val{border-color:#a9dfbf;background:#f3fbf6;}

    /* Value log: single well with the input, action and saved chip aligned */
    .pl-modal-body .pl-log{
        background:#ffffff;border:1px solid #e9ebef;border-radius:12px;
        padding:12px 13px;gap:9px;margin-top:0;flex-wrap:nowrap;
    }
    /* Time logs carry the compact picker + button + saved chip; let them
       breathe onto a second line instead of clipping. Inside the modal the
       compact picker is scaled up — the inline row sizing is too small for
       a 540px dialog and hurts tap targets. */
    .pl-modal-body .pl-log.is-time{flex-wrap:wrap;}
    .pl-modal-body .pl-log.is-time .tp{flex:1 1 100%;}
    .pl-modal-body .tp-compact .tp-display{padding:10px 12px;gap:7px;border-radius:13px;}
    .pl-modal-body .tp-compact .tp-digits{font-size:1.45rem;}
    .pl-modal-body .tp-compact .tp-period{font-size:.72rem;padding:3px 10px;}
    .pl-modal-body .tp-compact .tp-step-sm{width:32px;height:32px;font-size:1.05rem;}
    .pl-modal-body .tp-compact .tp-chip{padding:6px 12px;font-size:.78rem;}
    .pl-modal-body .pl-log > input[type="number"]{
        flex:1;min-width:0;width:auto;height:36px;
        border:1px solid #e5e7eb;border-radius:9px;text-align:center;
        font-size:14px;font-weight:700;font-variant-numeric:tabular-nums;color:#1a1d23;
        outline:none;transition:border-color .15s,box-shadow .15s;
    }
    .pl-modal-body .pl-log > input[type="number"]:focus{
        border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);
    }

    /* Action + inputs inside the modal share one button style */
    .pl-modal-body .pl-logset button,
    .pl-modal-body .pl-log button,
    .pl-modal-body .pl-avoid-panel button{
        display:inline-flex;align-items:center;justify-content:center;gap:5px;
        height:32px;padding:0 15px;border-radius:9px;
        border:1px solid #ddd6fe;background:#faf5ff;color:#7c3aed;
        font-size:12px;font-weight:700;cursor:pointer;
        transition:background .15s,transform .1s;flex-shrink:0;
    }
    .pl-modal-body .pl-logset button:hover,
    .pl-modal-body .pl-log button:hover,
    .pl-modal-body .pl-avoid-panel button:hover{background:#ede9fe;}
    .pl-modal-body .pl-logset button:active,
    .pl-modal-body .pl-log button:active,
    .pl-modal-body .pl-avoid-panel button:active{transform:scale(.97);}
    .pl-modal-body .pl-logset button:disabled,
    .pl-modal-body .pl-log button:disabled,
    .pl-modal-body .pl-avoid-panel button:disabled{opacity:.5;cursor:wait;transform:none;}

    /* Avoid logging panels: soft red card, fields stacked by width */
    .pl-modal-body .pl-avoid-actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:0;}
    .pl-modal-body .pl-avoid-panel{
        margin-top:0;padding:11px 13px;
        background:#fffafa;border:1px solid #f2dede;border-radius:12px;
    }
    .pl-modal-body .pl-avoid-panel form{gap:7px;}
    .pl-modal-body .pl-avoid-panel input,
    .pl-modal-body .pl-avoid-panel select{
        height:32px;padding:0 10px;font-size:12.5px;border-radius:9px;
        transition:border-color .15s,box-shadow .15s;
    }
    .pl-modal-body .pl-avoid-panel input:focus,
    .pl-modal-body .pl-avoid-panel select:focus{
        border-color:#c4b5fd;box-shadow:0 0 0 3px rgba(124,58,237,.1);
    }
    .pl-modal-body .pl-avoid-panel button{
        background:#b91c1c;border-color:#b91c1c;color:#fff;height:32px;
        padding:0 16px;border-radius:9px;font-size:12px;font-weight:700;
        cursor:pointer;display:inline-flex;align-items:center;gap:5px;
        transition:background .15s;
    }
    .pl-modal-body .pl-avoid-panel button:hover{background:#991b1b;}

    /* Avoid step rows keep their shield icon and slip action */
    .pl-modal-body .pl-step.avoid{
        background:#fff;border-color:#f3e2e2;padding-inline-end:10px;
    }
    .pl-modal-body .pl-step.avoid:hover{border-color:#f3e2e2;color:#3d4149;}
    .pl-modal-body .pl-step.avoid.violated{background:#fdf3f3;border-color:#fca5a5;color:#b91c1c;}
    .pl-modal-body .pl-step.avoid.violated i{color:#dc2626;}
    .pl-modal-body .pl-step-slipbtn{
        height:26px;padding:0 11px;border-radius:99px;
        border:1px solid #fca5a5;background:#fff;color:#b91c1c;
        font-size:10.5px;font-weight:700;cursor:pointer;flex-shrink:0;
        transition:background .15s;
    }
    .pl-modal-body .pl-step-slipbtn:hover{background:#fee2e2;}

    /* Logged state chip */
    .pl-modal-body .pl-log-saved{
        display:inline-flex;align-items:center;gap:4px;flex-shrink:0;
        height:26px;padding:0 11px;border-radius:99px;
        font-size:11px;font-weight:700;font-variant-numeric:tabular-nums;
    }

    /* Routine modal footer: quick Done / Not-done without hunting the row. */
    .pl-modal-foot{
        display:flex;align-items:center;gap:10px;
        padding:12px 18px;border-top:1px solid #eef0f4;background:#fafbfc;flex-shrink:0;
    }
    .pl-modal-foot[hidden]{display:none;}
    .pl-modal-status{
        font-size:11.5px;font-weight:700;color:#8a8f98;
        display:inline-flex;align-items:center;gap:6px;min-width:0;
    }
    .pl-modal-status.is-done{color:#15803d;}
    .pl-modal-status.is-skipped{color:#b91c1c;}
    .pl-modal-foot-actions{margin-inline-start:auto;display:flex;gap:8px;flex-shrink:0;}
    .pl-modal-foot-btn{
        display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 16px;
        border-radius:10px;font-size:12.5px;font-weight:800;cursor:pointer;border:1px solid transparent;
        transition:all .15s;
    }
    .pl-modal-foot-btn.done{background:linear-gradient(135deg,#16a34a 0%,#15803d 100%);color:#fff;border-color:#15803d;}
    .pl-modal-foot-btn.done:hover{filter:brightness(.95);transform:translateY(-1px);}
    .pl-modal-foot-btn.skip{background:#fff;border-color:#fca5a5;color:#b91c1c;}
    .pl-modal-foot-btn.skip:hover{background:#fef2f2;}
    .pl-modal-foot-btn.skip.active{background:#ef4444;border-color:#ef4444;color:#fff;}
    .pl-modal-foot-btn:disabled{opacity:.5;cursor:wait;transform:none;}

    /* Inline (non-modal) routine log inputs: same tap targets as the modal. */
    .pl-details .pl-log input[type="number"]{height:32px;border-radius:9px;font-weight:600;}
    .pl-details .pl-log input[type="number"]:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
    .pl-details .pl-logset input{height:30px;border-radius:8px;font-weight:600;}
    .pl-details .pl-logset input:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
    .pl-details .pl-log button,.pl-details .pl-logset button{height:30px;border-radius:8px;}

    /* Task fail modal specifics (reuses .pl-qa dialog chrome). */
    .pl-fail-taskname{
        font-size:13px;font-weight:700;color:#1a1d23;background:#fef2f2;
        border:1px solid #fbd5d5;border-radius:9px;padding:8px 12px;line-height:1.45;
        word-break:break-word;
    }
    .pl-fail-label{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#8a8f98;}
    .pl-fail-note-input{min-height:56px;resize:vertical;line-height:1.5;}
    .pl-qa-chips button.picked{background:#fee2e2;border-color:#fca5a5;color:#b91c1c;}
    .pl-fail-cancel{
        background:#fff;color:#6b7385;border:1px solid #e3e4e8;border-radius:9px;
        padding:8px 18px;font-size:12.5px;font-weight:700;cursor:pointer;
    }
    .pl-fail-cancel:hover{background:#f5f6f8;}
    .pl-fail-submit{
        background:linear-gradient(135deg,#dc2626 0%,#b91c1c 100%) !important;
        border-color:#b91c1c !important;
    }
    .pl-fail-submit:hover{background:linear-gradient(135deg,#b91c1c 0%,#991b1b 100%) !important;}

    @keyframes plSheetIn{from{transform:translateY(100%);}to{transform:none;}}
    @media (max-width: 560px) {
        .pl-modal{padding:0;align-items:flex-end;}
        .pl-modal-dialog{
            width:100%;max-height:92vh;
            border-radius:20px 20px 0 0;border-bottom:none;
            animation:plSheetIn .24s cubic-bezier(.16,1,.3,1);
        }
        .pl-modal-body{padding:14px 16px calc(16px + env(safe-area-inset-bottom));}
        .pl-modal-body .pl-avoid-panel form{flex-direction:column;align-items:stretch;}
        .pl-modal-body .pl-avoid-panel input,
        .pl-modal-body .pl-avoid-panel select{width:100%;}
    }
    @media (prefers-reduced-motion: reduce) {
        .pl-modal-dialog{animation:none;}
    }
    body.pl-modal-open{overflow:hidden;}
    .pl-details{display:none;}
    .pl-details.open{display:block;}
    .pl-task-title{display:flex;align-items:center;gap:4px;}
    .pl-task-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:4px;}
    .pl-priority{font-size:10.5px;font-weight:700;padding:1px 8px;border-radius:20px;text-transform:uppercase;letter-spacing:.3px;}
    .pl-proj,.pl-due{font-size:11px;color:#8a8f98;display:inline-flex;align-items:center;gap:4px;}
    .pl-due.overdue{color:#dc2626;font-weight:600;}

    /* Period groups + postpone actions */
    .pl-period-group{display:flex;flex-direction:column;gap:2px;}
    .pl-period-head{
        display:flex;align-items:center;gap:6px;font-size:10.5px;font-weight:800;
        letter-spacing:.05em;text-transform:uppercase;color:#64748b;padding:7px 2px 2px;
    }
    .pl-period-head i{font-size:12px;}
    .pl-period-body{
        display:flex;flex-direction:column;gap:6px;padding:2px;border-radius:8px;
        min-height:10px;transition:background .15s;
    }
    .pl-period-body.drop-active{background:#f5f3ff;box-shadow:inset 0 0 0 2px #ddd6fe;}
    .pl-task[draggable="true"]{cursor:grab;}
    .pl-task[draggable="true"]:active{cursor:grabbing;}
    .pl-task.dragging{opacity:.4;}
    .pl-task.drop-before{box-shadow:inset 0 3px 0 0 #7c3aed;}
    .pl-task.drop-after{box-shadow:inset 0 -3px 0 0 #7c3aed;}
    .pl-task-actions{display:flex;align-items:center;gap:6px;flex-shrink:0;align-self:center;opacity:.4;transition:opacity .18s;}
    .pl-task:hover .pl-task-actions,.pl-task:focus-within .pl-task-actions{opacity:1;}
    .pl-task-act{
        width:27px;height:27px;display:grid;place-items:center;padding:0;
        border:1px solid #e3e4e8;background:#fff;color:#5b6472;border-radius:8px;
        font-size:13px;cursor:pointer;transition:all .15s;
    }
    .pl-task-act-move:hover{background:#4f46e5;border-color:#4f46e5;color:#fff;transform:translateY(-1px);box-shadow:0 4px 10px rgba(79,70,229,.28);}
    .pl-task-act-pull:hover{background:#16a34a;border-color:#16a34a;color:#fff;transform:translateY(-1px);box-shadow:0 4px 10px rgba(22,163,74,.28);}
    .pl-task-act-danger:hover{background:#e11d48;border-color:#e11d48;color:#fff;transform:translateY(-1px);box-shadow:0 4px 10px rgba(225,29,72,.28);}
    .pl-task-act:active{transform:translateY(0) scale(.96);}
    .pl-task-act:disabled{opacity:.35;cursor:default;transform:none;box-shadow:none;}
    .pl-task-open{
        display:inline-flex;align-items:center;gap:4px;height:27px;padding:0 10px;
        border:1px solid #e3e4e8;background:#fff;border-radius:8px;
        color:#5b6472;font-size:11px;font-weight:800;letter-spacing:.02em;text-decoration:none;
        transition:all .15s;
    }
    .pl-task-open:hover{background:#0f172a;border-color:#0f172a;color:#fff;transform:translateY(-1px);box-shadow:0 5px 12px rgba(15,23,42,.25);}
    .pl-task-open i{font-size:12px;}

    /* Day progress bar */
    .pl-daybar{display:flex;align-items:center;gap:10px;margin:2px 0 14px;}
    .pl-daybar-track{flex:1;height:8px;background:#eceef2;border-radius:20px;overflow:hidden;}
    .pl-daybar-fill{height:100%;width:0%;border-radius:20px;background:linear-gradient(90deg,#7c3aed,#a78bfa);transition:width .45s ease;}
    .pl-daybar-fill.is-full{background:linear-gradient(90deg,#16a34a,#4ade80);}
    .pl-daybar-label{font-size:11.5px;font-weight:800;color:#7c3aed;min-width:36px;text-align:right;}
    .pl-daybar-fill.is-full + .pl-daybar-label,.pl-daybar.done .pl-daybar-label{color:#16a34a;}

    /* Postpone-all button on the Overdue head */
    .pl-overdue-all{
        display:inline-flex;align-items:center;gap:6px;margin-inline-start:auto;
        border:1px solid #fecaca;background:linear-gradient(135deg, #fff 0%, #fff5f5 100%);
        color:#dc2626;border-radius:20px;
        font-size:11.5px;font-weight:700;padding:4px 12px;cursor:pointer;
        box-shadow:0 1px 3px rgba(220,38,38,.08);transition:all .18s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .pl-overdue-all i{font-size:12px;transition:transform .18s ease;}
    .pl-overdue-all:hover{
        background:linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        border-color:#b91c1c;color:#fff;
        box-shadow:0 4px 12px rgba(220,38,38,.25);transform:translateY(-1px);
    }
    .pl-overdue-all:hover i{transform:translateY(1px);}
    .pl-overdue-all:active{transform:scale(0.97);}
    .pl-overdue-all:disabled{opacity:.5;cursor:default;transform:none;}

    /* Inline title editing */
    .pl-task-title{cursor:text;border-radius:6px;padding:1px 4px;margin:-1px -4px;transition:background .15s;}
    .pl-task-title:hover{background:#f5f3ff;}
    .pl-task-title:hover::after{
        content:'\F4C6';font-family:'bootstrap-icons';font-size:10px;color:#a78bfa;margin-left:6px;vertical-align:middle;
    }
    .pl-task.pl-routine .pl-task-title{cursor:pointer;}
    .pl-task.pl-routine .pl-task-title:hover{background:transparent;}
    .pl-task.pl-routine .pl-task-title:hover::after{content:none;}
    .pl-title-input{
        width:100%;font:inherit;font-size:13px;font-weight:600;color:#1a1d23;
        border:1px solid #7c3aed;border-radius:7px;padding:2px 8px;outline:none;
        box-shadow:0 0 0 3px rgba(124,58,237,.13);background:#fff;
    }

    @media(max-width:768px){
        .pl-task-actions{opacity:1;}
        .pl-task-open span{display:none;}
        .pl-task-open{padding:0 8px;}
    }

    /* Routine row accent */
    .pl-routine .pl-check input:checked + .pl-check-box{background:#7c3aed;border-color:#7c3aed;}
    .pl-routine-static{
        flex-shrink:0;margin-top:1px;width:19px;height:19px;display:flex;
        align-items:center;justify-content:center;color:#c4c9d4;font-size:12px;
    }

    /* ── Habit Ring (package A) ── */
    .pl-habit{position:relative;flex-shrink:0;margin-top:1px;cursor:pointer;display:inline-flex;}
    .pl-habit input{position:absolute;opacity:0;width:0;height:0;}
    .routine-check-box{
        width:19px;height:19px;border:2px solid #c4c9d4;border-radius:50%;
        display:flex;align-items:center;justify-content:center;color:transparent;
        font-size:10px;transition:all .15s;background:white;position:absolute;
        top:50%;left:50%;transform:translate(-50%,-50%);
    }
    .pl-habit:hover .routine-check-box{border-color:#7c3aed;}
    .pl-habit input:checked + .habit-ring .routine-check-box,
    .pl-habit input:checked + .routine-check-box{background:#16a34a;border-color:#16a34a;color:white;}
    .habit-ring{position:relative;width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;}
    .habit-ring svg{width:30px;height:30px;transform:rotate(-90deg);}
    .habit-ring .ring-bg{fill:none;stroke:#eef0f2;stroke-width:3.5;}
    .habit-ring .ring-fg{fill:none;stroke:#b9a5f5;stroke-width:3.5;stroke-linecap:round;transition:stroke-dashoffset .4s;}
    .pl-task.is-done .habit-ring .ring-fg{stroke:#16a34a;}
    .pl-habit input:checked + .habit-ring .ring-fg{stroke:#16a34a;}
    .flame{
        font-size:11px;font-weight:700;color:#d97706;background:#fdf4de;
        border-radius:20px;padding:0 7px;margin-left:6px;white-space:nowrap;
        vertical-align:1px;
    }
    .last7{display:inline-flex;gap:3px;align-items:center;}
    .last7 .sq{width:7px;height:7px;border-radius:2.5px;display:inline-block;}
    .sq-done{background:#30a46c;}
    .sq-missed{background:#e3e5e9;}
    .sq-na{background:#f2f3f5;}
    .sq-future,.sq-today{background:transparent;box-shadow:inset 0 0 0 1px #e8eaef;}
    .sq-violated{background:#ef4444;}

    /* ── Routine steps (package: sub-items) ── */
    .pl-steps{display:flex;flex-wrap:wrap;gap:6px;margin-top:7px;}
    .pl-step{
        display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;
        border:1px solid #e5e7eb;background:#fafbfc;color:#6b6f78;font-size:11.5px;font-weight:600;
        cursor:pointer;transition:all .12s;
    }
    .pl-step:hover{border-color:#c4b5fd;color:#7c3aed;}
    .pl-step i{font-size:13px;color:#c1c4cc;transition:color .12s;}
    .pl-step.done{background:#e3f5ec;border-color:#a9dfbf;color:#29774b;}
    .pl-step.done i{color:#30a46c;}
    .pl-step-schedule{
        display:inline-flex;align-items:center;gap:3px;margin-left:2px;
        font-size:10px;font-weight:700;text-transform:none;white-space:nowrap;
    }
    .pl-step-schedule i{font-size:11px;color:inherit;}
    /* Per-step ✗ "did not do it": tiny circle attached to the step chip. */
    .pl-step-wrap{display:inline-flex;align-items:center;}
    .pl-step-wrap .pl-step-skip{
        width:18px;height:18px;flex-shrink:0;display:inline-grid;place-items:center;
        border:1px solid #e5e7eb;background:#fff;color:#c1c4cc;border-radius:50%;
        font-size:8px;line-height:1;cursor:pointer;padding:0;margin-inline-start:-7px;
        opacity:0;transition:opacity .12s,all .12s;position:relative;z-index:1;
    }
    .pl-step-wrap:hover .pl-step-skip,.pl-step-wrap:focus-within .pl-step-skip,
    .pl-step-wrap .pl-step-skip.active{opacity:1;}
    .pl-step-wrap .pl-step-skip:hover{border-color:#fca5a5;background:#fef2f2;color:#dc2626;}
    .pl-step-wrap .pl-step-skip.active{background:#ef4444;border-color:#ef4444;color:#fff;opacity:1;}
    .pl-step-wrap .pl-step-skip:disabled{opacity:.5;cursor:wait;}
    .pl-step-wrap.is-skipped .pl-step,
    .pl-step.is-skipped{background:#fef2f2;border-color:#fca5a5;color:#b91c1c;}
    .pl-step-wrap.is-skipped .pl-step i,
    .pl-step.is-skipped i{color:#dc2626;}
    /* Partial-fail badge on the routine title (some steps skipped). */
    .steps-skipped{
        font-size:10.5px;font-weight:800;color:#b91c1c;background:#fee2e2;
        border-radius:20px;padding:1px 7px;margin-left:6px;vertical-align:1px;white-space:nowrap;
    }
    /* "Skip remaining" helper under partially skipped steps. */
    .pl-skip-rest{
        display:inline-flex;align-items:center;gap:5px;margin-top:7px;
        padding:3px 12px;border-radius:20px;border:1px dashed #fca5a5;
        background:transparent;color:#b91c1c;font-size:11px;font-weight:700;cursor:pointer;
        transition:all .12s;
    }
    .pl-skip-rest:hover{background:#fef2f2;border-style:solid;}
    .pl-skip-rest:disabled{opacity:.5;cursor:wait;}
    /* Skipped steps read as full-width rows inside the routine modal. */
    .pl-modal-body .pl-step-wrap{width:100%;display:flex;align-items:stretch;gap:8px;}
    .pl-modal-body .pl-step-wrap .pl-step{flex:1;min-width:0;}
    .pl-modal-body .pl-step-wrap .pl-step-skip{
        opacity:1;margin-inline-start:0;width:32px;height:auto;min-height:32px;
        border-radius:10px;font-size:11px;align-self:stretch;
    }
    .steps-count{
        font-size:10.5px;font-weight:700;color:#8a8f98;background:#f2f3f5;
        border-radius:20px;padding:1px 7px;margin-left:6px;vertical-align:1px;
    }
    .steps-count.all{color:#29774b;background:#e3f5ec;}
    .steps-count.bad{color:#b91c1c;background:#fee2e2;}

    /* ── Avoid habits (forbidden): shield, slip buttons, inline forms ── */
    .pl-avoid-shield{
        width:30px;height:30px;border-radius:50%;flex-shrink:0;
        display:flex;align-items:center;justify-content:center;font-size:15px;
    }
    .pl-avoid-shield.ok{background:#dcfce7;color:#15803d;}
    .pl-avoid-shield.bad{background:#fee2e2;color:#b91c1c;}
    .pl-avoid-tag{
        font-size:10.5px;font-weight:700;color:#b91c1c;background:#fee2e2;
        border-radius:20px;padding:1px 7px;margin-left:6px;vertical-align:1px;white-space:nowrap;
    }
    .pl-avoid-actions{display:flex;gap:6px;margin-top:7px;flex-wrap:wrap;}
    .pl-avoid-btn{
        display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;
        font-size:11.5px;font-weight:700;cursor:pointer;transition:all .12s;border:1px solid #e5e7eb;
        background:#fafbfc;color:#6b6f78;
    }
    .pl-avoid-btn.slip{border-color:#fca5a5;background:#fef2f2;color:#b91c1c;}
    .pl-avoid-btn.slip:hover{background:#fee2e2;}
    .pl-avoid-btn.note:hover{border-color:#c4b5fd;color:#7c3aed;}
    .pl-avoid-panel{margin-top:7px;}
    .pl-avoid-panel form{display:flex;gap:6px;flex-wrap:wrap;align-items:center;}
    .pl-avoid-panel input,.pl-avoid-panel select{
        padding:4px 9px;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;outline:none;
        background:white;color:#1f2328;max-width:100%;
    }
    .pl-avoid-panel input:focus,.pl-avoid-panel select:focus{border-color:#c4b5fd;}
    .pl-avoid-panel input[name=quantity]{width:64px;}
    .pl-avoid-panel input[name=note]{flex:1;min-width:140px;}
    .pl-avoid-panel button{
        padding:4px 14px;border-radius:8px;border:none;background:#b91c1c;color:white;
        font-size:12px;font-weight:700;cursor:pointer;
    }
    .pl-avoid-panel button:hover{background:#991b1b;}
    .pl-step.avoid{cursor:default;}
    .pl-step.avoid:hover{border-color:#e5e7eb;color:#6b6f78;}
    .pl-step.avoid.violated{background:#fee2e2;border-color:#fca5a5;color:#b91c1c;}
    .pl-step.avoid.violated i{color:#dc2626;}
    .pl-step-slipcount{font-size:10px;font-weight:800;color:#b91c1c;}
    .pl-step-slipbtn{
        margin-left:2px;padding:2px 10px;border-radius:14px;border:1px solid #fca5a5;
        background:white;color:#b91c1c;font-size:10.5px;font-weight:700;cursor:pointer;
    }
    .pl-step-slipbtn:hover{background:#fee2e2;}

    /* ── Routine metric logging (value + sets) ── */
    .pl-log{display:flex;align-items:center;gap:6px;margin-top:7px;flex-wrap:wrap;}
    .pl-log input{width:110px;padding:4px 9px;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;outline:none;}
    .pl-log input:focus{border-color:#c4b5fd;}
    .pl-log button,.pl-logset button{
        display:inline-flex;align-items:center;gap:4px;
        padding:4px 12px;border-radius:8px;border:1px solid #c4b5fd;background:#faf5ff;
        color:#7c3aed;font-size:11.5px;font-weight:700;cursor:pointer;
    }
    .pl-log button:hover,.pl-logset button:hover{background:#ede9fe;}
    .pl-log button:disabled,.pl-logset button:disabled{opacity:.5;cursor:wait;}
    .pl-log-saved{
        font-size:11px;font-weight:700;color:#15803d;background:#e9f9f0;
        border:1px solid #bbf7d0;border-radius:20px;padding:2px 10px;
        font-variant-numeric:tabular-nums;
    }
    .pl-logsets{display:flex;flex-direction:column;gap:6px;margin-top:7px;}
    .pl-logset{display:flex;align-items:center;gap:5px;flex-wrap:wrap;background:#fafbfc;border:1px solid #eef0f3;border-radius:8px;padding:5px 8px;}
    .pl-logset-name{font-size:11.5px;font-weight:700;color:#3d4149;flex:1;min-width:90px;}
    .pl-logset input{width:64px;padding:3px 7px;border:1px solid #e5e7eb;border-radius:7px;font-size:11.5px;outline:none;}
    .pl-logset input:focus{border-color:#c4b5fd;}
    .pl-logset input.has-val{border-color:#a9dfbf;background:#f3fbf6;}
    @keyframes plFlash{0%,100%{box-shadow:none;}50%{box-shadow:0 0 0 3px rgba(124,58,237,.45);}}
    .pl-flash{animation:plFlash .8s ease-in-out 2;border-color:#7c3aed !important;}

    /* ── Package B: confetti + toast ── */
    #plConfetti{position:fixed;inset:0;pointer-events:none;z-index:1080;overflow:hidden;}
    #plConfetti i{position:absolute;top:-12px;width:8px;height:14px;border-radius:2px;opacity:0;animation:plFall 1.4s ease-in forwards;}
    @keyframes plFall{
        0%{opacity:1;transform:translateY(0) rotate(0);}
        100%{opacity:0;transform:translateY(70vh) rotate(540deg);}
    }
    #plToast{
        position:fixed;bottom:22px;left:50%;transform:translateX(-50%);z-index:1070;
        background:#1f2328;color:white;font-size:13px;padding:9px 14px 9px 18px;border-radius:8px;
        display:none;align-items:center;gap:12px;white-space:nowrap;
        box-shadow:0 6px 20px rgba(0,0,0,.22);
    }
    #plToast.show{display:flex;}
    #plToast button{
        background:none;border:none;color:#a78bfa;font-size:12.5px;font-weight:700;
        cursor:pointer;padding:2px 4px;
    }
    #plToast button:hover{color:white;}

    /* ── Minimalist Hero Next Up Card ── */
    .pl-next-wrap{margin-bottom:20px;}
    .pl-next-wrap[hidden]{display:none;}
    .pl-next-card{
        display:flex;align-items:center;gap:16px;flex-wrap:wrap;
        background:linear-gradient(135deg, #ffffff 0%, #fbfaff 45%, #f5f3ff 100%);
        border:1.5px solid #e0e7ff;border-radius:18px;padding:16px 20px;
        box-shadow:0 8px 24px -4px rgba(124,58,237,.08), 0 2px 8px -2px rgba(0,0,0,.04);
        position:relative;overflow:hidden;transition:all .22s cubic-bezier(0.4, 0, 0.2, 1);
        cursor:pointer;
    }
    .pl-next-card:hover{
        border-color:#c4b5fd;transform:translateY(-2px);
        box-shadow:0 12px 30px -4px rgba(124,58,237,.16), 0 4px 12px -2px rgba(0,0,0,.06);
    }
    .pl-next-card-avoid{
        background:linear-gradient(135deg, #ffffff 0%, #fff5f5 45%, #fef2f2 100%);
        border-color:#fecaca;
    }
    .pl-next-card-avoid:hover{
        border-color:#f87171;box-shadow:0 12px 30px -4px rgba(220,38,38,.14);
    }
    .pl-next-top-badge{
        display:flex;align-items:center;gap:8px;width:100%;margin-bottom:2px;
    }
    .pl-next-label{
        display:inline-flex;align-items:center;gap:6px;
        font-size:11px;font-weight:800;letter-spacing:normal;
        color:#ffffff;background:linear-gradient(135deg,#7c3aed 0%,#8b5cf6 100%);
        border-radius:20px;padding:3.5px 11px;box-shadow:0 2px 8px rgba(124,58,237,.3);
    }
    .pl-next-label i{font-size:11px;color:#fde047;filter:drop-shadow(0 1px 2px rgba(0,0,0,0.2));}
    .pl-next-step-indicator{
        font-size:11px;font-weight:700;color:#6d28d9;background:#ede9fe;
        border-radius:20px;padding:3.5px 10px;display:inline-flex;align-items:center;gap:4px;
    }
    .pl-next-step-indicator.all-done{background:#dcfce7;color:#16a34a;}
    
    .pl-next-body{flex:1;min-width:240px;}
    .pl-next-title-row{display:flex;align-items:flex-start;gap:12px;}
    .pl-next-icon-wrap{
        width:38px;height:38px;border-radius:12px;display:grid;place-items:center;
        font-size:18px;flex-shrink:0;transition:transform .2s ease;
    }
    .pl-next-card:hover .pl-next-icon-wrap{transform:scale(1.08);}
    .pl-next-icon-wrap.is-task{background:#eff6ff;color:#2563eb;}
    .pl-next-icon-wrap.is-routine{background:#f5f3ff;color:#7c3aed;}
    .pl-next-icon-wrap.is-avoid{background:#fee2e2;color:#dc2626;}
    .pl-next-title-content{flex:1;}
    .pl-next-title{font-size:16px;font-weight:800;color:#0f172a;line-height:1.35;margin-bottom:4px;}
    .pl-next-meta{display:flex;align-items:center;gap:7px;flex-wrap:wrap;}
    .pl-priority-pill{
        font-size:11.5px;font-weight:700;border-radius:8px;padding:2px 8px;border:1px solid transparent;
    }
    .pl-period-pill{
        font-size:11.5px;font-weight:700;border-radius:8px;padding:2px 8px;border:1px solid transparent;
        display:inline-flex;align-items:center;gap:4px;
    }
    .pl-meta-tag{
        font-size:11.5px;color:#64748b;font-weight:600;display:inline-flex;align-items:center;gap:4px;
        background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;padding:2px 7px;
    }
    .pl-streak-tag{
        font-size:11.5px;font-weight:700;color:#d97706;background:#fef3c7;border:1px solid #fde68a;
        border-radius:7px;padding:2px 7px;display:inline-flex;align-items:center;gap:3px;
    }
    
    .pl-next-prompt-pill{
        display:inline-flex;align-items:center;gap:8px;margin-top:9px;
        background:#ffffff;border:1.5px solid #ede9fe;border-radius:10px;
        padding:5px 12px;font-size:12.5px;color:#334155;box-shadow:0 1px 3px rgba(124,58,237,.05);
    }
    .pl-next-prompt-pill.value{border-color:#ddd6fe;background:#faf5ff;color:#6b21a8;}
    .pl-next-prompt-pill.step{border-color:#e0e7ff;background:#f8faff;color:#1e3a8a;}
    .pl-next-prompt-pill.avoid{border-color:#fecaca;background:#fff5f5;color:#991b1b;}
    .pl-step-schedule-mini{font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:3px;}

    .pl-next-actions{display:flex;align-items:center;gap:8px;margin-inline-start:auto;}
    .pl-action-btn{
        display:inline-flex;align-items:center;gap:6px;border-radius:11px;
        font-size:12.5px;font-weight:700;cursor:pointer;padding:8px 18px;
        transition:all .18s cubic-bezier(0.4, 0, 0.2, 1);border:1px solid transparent;
    }
    .pl-action-btn:active{transform:scale(0.96);}
    .pl-action-btn-primary{
        background:linear-gradient(135deg,#7c3aed 0%,#6d28d9 100%);color:#fff;
        border-color:#6d28d9;box-shadow:0 3px 10px rgba(124,58,237,.28);
    }
    .pl-action-btn-primary:hover{
        background:linear-gradient(135deg,#6d28d9 0%,#5b21b6 100%);
        transform:translateY(-1px);box-shadow:0 5px 14px rgba(124,58,237,.38);color:#fff;
    }
    .pl-action-btn-start{
        background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe;
    }
    .pl-action-btn-start:hover{
        background:#ede9fe;color:#5b21b6;transform:translateY(-1px);
    }
    .pl-action-btn-focus{
        background:#f8fafc;color:#334155;border-color:#cbd5e1;
    }
    .pl-action-btn-focus:hover{
        background:#f1f5f9;color:#0f172a;transform:translateY(-1px);
    }
    .pl-action-btn-slip{
        background:linear-gradient(135deg,#dc2626 0%,#b91c1c 100%);color:#fff;
        border-color:#b91c1c;box-shadow:0 3px 10px rgba(220,38,38,.25);
    }
    .pl-action-btn-slip:hover{
        background:linear-gradient(135deg,#b91c1c 0%,#991b1b 100%);color:#fff;
        transform:translateY(-1px);box-shadow:0 5px 14px rgba(220,38,38,.35);
    }

    /* ── Quick Action Mini-Modal (#plNextUpModal) ── */
    .pl-qa-modal{position:fixed;inset:0;z-index:1099;display:flex;align-items:center;justify-content:center;padding:16px;}
    .pl-qa-modal[hidden]{display:none;}
    .pl-qa-modal-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);animation:plFadeIn .2s ease-out;}
    .pl-qa-modal-dialog{
        position:relative;background:#ffffff;border-radius:20px;width:min(460px,94vw);
        box-shadow:0 24px 60px -12px rgba(15,23,42,.35), 0 0 0 1px rgba(226,232,240,.8);
        display:flex;flex-direction:column;overflow:hidden;animation:plScaleUp .22s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes plFadeIn{from{opacity:0;}to{opacity:1;}}
    @keyframes plScaleUp{from{opacity:0;transform:scale(.92) translateY(10px);}to{opacity:1;transform:scale(1) translateY(0);}}
    
    .pl-qa-modal-head{
        display:flex;align-items:center;justify-content:space-between;
        padding:16px 20px 14px;border-bottom:1px solid #f1f5f9;background:#faf5ff;
    }
    .pl-qa-modal-icon{
        width:36px;height:36px;border-radius:10px;background:#7c3aed;color:#fff;
        display:grid;place-items:center;font-size:16px;box-shadow:0 2px 8px rgba(124,58,237,.3);
    }
    .pl-qa-modal-title{font-size:15px;font-weight:800;color:#0f172a;}
    .pl-qa-modal-sub{font-size:11.5px;color:#64748b;font-weight:600;}
    .pl-qa-modal-close{
        border:none;background:#f1f5f9;color:#64748b;width:30px;height:30px;
        border-radius:50%;display:grid;place-items:center;cursor:pointer;transition:all .15s;
    }
    .pl-qa-modal-close:hover{background:#e2e8f0;color:#0f172a;}
    .pl-qa-modal-body{padding:18px 20px;max-height:calc(85vh - 140px);overflow-y:auto;}
    .pl-qa-modal-foot{
        display:flex;align-items:center;justify-content:space-between;gap:12px;
        padding:14px 20px;border-top:1px solid #f1f5f9;background:#f8fafc;
    }
    .pl-qa-hero-chip{
        background:linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border:1.5px solid #e2e8f0;border-radius:13px;padding:12px 14px;
    }
    .pl-preset-chip{
        border:1.5px solid #e2e8f0;background:#ffffff;color:#334155;border-radius:18px;
        padding:4px 10px;font-size:12px;font-weight:700;cursor:pointer;transition:all .15s;
    }
    .pl-preset-chip:hover{border-color:#a855f7;background:#faf5ff;color:#7c3aed;transform:translateY(-1px);}
    .pl-stepper-wrap input{border-color:#cbd5e1;color:#0f172a;}
    .pl-stepper-wrap input:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.15);}
    .pl-qa-submit-btn{
        background:linear-gradient(135deg,#7c3aed 0%,#6d28d9 100%);border-color:#6d28d9;
        box-shadow:0 3px 10px rgba(124,58,237,.28);
    }
    .pl-qa-submit-btn:hover{
        background:linear-gradient(135deg,#6d28d9 0%,#5b21b6 100%);
        transform:translateY(-1px);box-shadow:0 5px 14px rgba(124,58,237,.38);
    }

    /* ── Live Floating Focus Bar ── */
    .pl-floating-focus-bar{
        position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:1090;
        width:min(720px,94vw);animation:plFloatUp .3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes plFloatUp{from{opacity:0;transform:translate(-50%, 20px);}to{opacity:1;transform:translate(-50%, 0);}}
    .pl-ffb-container{
        display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
        background:linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
        border:1.5px solid rgba(139,92,246,.35);border-radius:20px;padding:12px 18px;
        box-shadow:0 16px 40px -8px rgba(15,23,42,.6), 0 0 24px rgba(124,58,237,.25);
        backdrop-filter:blur(10px);color:#fff;
    }
    .pl-ffb-info{display:flex;align-items:center;gap:10px;min-width:180px;}
    .pl-ffb-pulse{
        width:12px;height:12px;border-radius:50%;background:#22c55e;flex-shrink:0;
        box-shadow:0 0 0 0 rgba(34,197,94,.7);animation:plPulse 1.8s infinite;
    }
    @keyframes plPulse{0%{transform:scale(0.95);box-shadow:0 0 0 0 rgba(34,197,94,.7);}70%{transform:scale(1);box-shadow:0 0 0 8px rgba(34,197,94,0);}100%{transform:scale(0.95);box-shadow:0 0 0 0 rgba(34,197,94,0);}}
    .pl-ffb-title{font-size:13.5px;font-weight:700;color:#f8fafc;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .pl-ffb-sub{font-size:11px;color:#94a3b8;font-weight:600;}
    .pl-ffb-clock-wrap{display:flex;align-items:center;gap:8px;}
    .pl-ffb-clock{font-size:20px;font-weight:800;color:#38bdf8;letter-spacing:1px;}
    .pl-ffb-mode-badge{font-size:10.5px;font-weight:700;color:#c084fc;background:rgba(192,132,252,.15);border-radius:12px;padding:2px 8px;}
    .pl-ffb-actions{display:flex;align-items:center;gap:6px;}
    .pl-ffb-btn{
        background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:#f1f5f9;
        border-radius:10px;padding:6px 10px;font-size:12px;font-weight:700;cursor:pointer;
        display:inline-flex;align-items:center;justify-content:center;transition:all .15s;
    }
    .pl-ffb-btn:hover{background:rgba(255,255,255,.2);color:#fff;transform:translateY(-1px);}
    .pl-ffb-btn-pause{background:#6366f1;border-color:#4f46e5;}
    .pl-ffb-btn-pause:hover{background:#4f46e5;}
    .pl-ffb-btn-stop{background:#ef4444;border-color:#dc2626;}
    .pl-ffb-btn-stop:hover{background:#dc2626;}
    .pl-ffb-btn-break{background:rgba(245,158,11,.2);color:#fbbf24;border-color:rgba(245,158,11,.4);}
    .pl-ffb-btn-break:hover{background:rgba(245,158,11,.3);color:#fde68a;}
    .pl-ffb-btn-fullscreen{background:rgba(124,58,237,.25);color:#c4b5fd;border-color:rgba(124,58,237,.5);}
    .pl-ffb-btn-fullscreen:hover{background:rgba(124,58,237,.4);color:#fff;}
    .pl-ffb-btn-close{background:transparent;border:none;color:#94a3b8;}
    .pl-ffb-btn-close:hover{color:#f8fafc;background:rgba(255,255,255,.1);}
    .pl-ffb-btn:disabled{opacity:.5;pointer-events:none;}
    .pl-ffb-btn-pause{transition:background .15s ease,transform .1s ease;}
    .pl-ffb-btn-pause:active{transform:scale(.92);}
    .pl-ffb-paused .pl-ffb-pulse{background:#f59e0b;animation:none;box-shadow:0 0 0 4px rgba(245,158,11,.25);}
    .pl-ffb-paused .pl-ffb-clock{color:#94a3b8;}

    /* ── Task Row Quick Action Buttons ── */
    .pl-task-act-play{background:#eff6ff !important;color:#2563eb !important;border-color:#bfdbfe !important;}
    .pl-task-act-play:hover{background:#2563eb !important;color:#fff !important;border-color:#2563eb !important;}
    .pl-task-act-focus{background:#f5f3ff !important;color:#7c3aed !important;border-color:#ddd6fe !important;}
    .pl-task-act-focus:hover{background:#7c3aed !important;color:#fff !important;border-color:#7c3aed !important;}

    /* ── Quick Estimate Popover ── */
    .pl-task-estimate-wrapper{position:relative;display:inline-block;}
    .pl-due-clickable{cursor:pointer;transition:all .15s;}
    .pl-due-clickable:hover{background:#ede9fe;color:#6d28d9;border-color:#c4b5fd;}
    .pl-estimate-popover{
        position:absolute;top:calc(100% + 4px);right:0;z-index:100;
        background:#ffffff;border:1.5px solid #e2e8f0;border-radius:12px;padding:8px 10px;
        box-shadow:0 10px 25px -4px rgba(0,0,0,.15);min-width:160px;
    }
    .pl-est-btn{
        border:1px solid #e2e8f0;background:#f8fafc;color:#334155;border-radius:6px;
        padding:2px 7px;font-size:11px;font-weight:700;cursor:pointer;transition:all .12s;
    }
    .pl-est-btn:hover{background:#7c3aed;color:#fff;border-color:#7c3aed;}

    /* ── Quick add ── */
    .pl-toolbar-right{display:flex;align-items:center;gap:8px;}
    .pl-add-btn{
        display:inline-flex;align-items:center;gap:6px;
        background:linear-gradient(135deg,#7c3aed 0%,#6d28d9 100%);
        color:#fff;border:1px solid #6d28d9;border-radius:9px;
        padding:7px 16px;font-size:12.5px;font-weight:700;cursor:pointer;
        box-shadow:0 2px 6px rgba(124,58,237,.22);transition:all .15s;
    }
    .pl-add-btn:hover{
        background:linear-gradient(135deg,#6d28d9 0%,#5b21b6 100%);
        transform:translateY(-1px);box-shadow:0 4px 10px rgba(124,58,237,.32);
    }
    .pl-fab{
        display:none;position:fixed;right:18px;bottom:18px;z-index:1000;
        width:52px;height:52px;border-radius:50%;border:none;
        background:linear-gradient(135deg,#7c3aed 0%,#6d28d9 100%);
        color:#fff;font-size:22px;cursor:pointer;
        box-shadow:0 6px 18px rgba(124,58,237,.4);
        align-items:center;justify-content:center;
    }
    .pl-fab:hover{background:#6d28d9;}
    @media(max-width:640px){
        .pl-add-btn{display:none;}
        .pl-fab{display:flex;}
    }
    .pl-qa{position:fixed;inset:0;z-index:1095;display:flex;align-items:flex-start;justify-content:center;padding:70px 16px 16px;}
    .pl-qa[hidden]{display:none;}
    .pl-qa-backdrop{position:absolute;inset:0;background:rgba(17,20,26,.45);}
    .pl-qa-dialog{
        position:relative;background:#fff;border-radius:14px;width:min(440px,96vw);
        display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;
    }
    .pl-qa-head{display:flex;align-items:center;padding:14px 16px 10px;}
    .pl-qa-title{font-size:14px;font-weight:800;color:#1a1d23;}
    .pl-qa-x{margin-left:auto;border:none;background:#f2f3f5;color:#6b7385;width:28px;height:28px;border-radius:8px;font-size:16px;line-height:1;cursor:pointer;}
    .pl-qa-x:hover{background:#e6e8ec;color:#1a1d23;}
    .pl-qa-tabs{display:flex;gap:4px;padding:0 16px 10px;border-bottom:1px solid #eef0f3;}
    .pl-qa-tab{
        border:1px solid transparent;background:transparent;color:#8a8f98;
        font-size:12.5px;font-weight:700;padding:6px 12px;border-radius:8px;cursor:pointer;
    }
    .pl-qa-tab.active{background:#faf5ff;color:#7c3aed;border-color:#ddd6fe;}
    .pl-qa-form{display:flex;flex-direction:column;gap:10px;padding:14px 16px 16px;}
    .pl-qa-form[hidden]{display:none;}
    .pl-qa-input{
        width:100%;padding:10px 12px;border:1.5px solid #e3e4e8;border-radius:9px;
        font-size:13.5px;outline:none;transition:border-color .15s;
    }
    .pl-qa-input:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
    .pl-qa-row{display:flex;gap:8px;}
    .pl-qa-row select,.pl-qa-row input[type="time"]{
        flex:1;padding:7px 9px;border:1px solid #e3e4e8;border-radius:9px;
        font-size:12.5px;color:#3d4149;outline:none;background:#fff;
    }
    .pl-qa-row select:focus,.pl-qa-row input:focus{border-color:#c4b5fd;}
    .pl-qa-chips{display:flex;gap:6px;flex-wrap:wrap;}
    .pl-qa-chips button{
        border:1px solid #e5e7eb;background:#fafbfc;color:#6b6f78;
        border-radius:20px;padding:4px 12px;font-size:11.5px;font-weight:700;cursor:pointer;transition:all .12s;
    }
    .pl-qa-chips button:hover{border-color:#c4b5fd;color:#7c3aed;}
    .pl-qa-chips button.active{background:#ede9fe;border-color:#c4b5fd;color:#7c3aed;}
    .pl-qa-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:2px;}
    .pl-qa-actions button[type="submit"]{
        background:#7c3aed;color:#fff;border:1px solid #7c3aed;border-radius:9px;
        padding:8px 18px;font-size:12.5px;font-weight:700;cursor:pointer;
    }
    .pl-qa-actions button[type="submit"]:hover{background:#6d28d9;}

    /* ── Week view redesign: clean, readable day columns ── */
    .pw-grid{display:grid;grid-template-columns:repeat(7,minmax(215px,1fr));gap:12px;align-items:start;}
    @media(max-width:1500px){ .pw-grid{grid-template-columns:repeat(7,minmax(200px,1fr));} }
    @media(max-width:1100px){
        .pw-grid{display:flex;overflow-x:auto;padding-bottom:12px;scroll-snap-type:x proximity;scrollbar-width:thin;}
        .pw-grid .pw-day{flex:0 0 300px;scroll-snap-align:start;}
    }
    @media(max-width:640px){ .pw-grid{display:flex;flex-direction:column;overflow:visible;} .pw-grid .pw-day{flex:none;} }
    .pw-day{position:relative;background:#fff;border:1px solid #e6e8ee;border-radius:16px;display:flex;flex-direction:column;min-height:230px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
    .pw-day.is-today{border-color:#c4b5fd;box-shadow:0 0 0 2px rgba(124,58,237,.14),0 8px 24px -12px rgba(124,58,237,.35);}
    .pw-day-head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:12px 14px;background:#fafbfc;border-bottom:1px solid #eef0f3;border-radius:16px 16px 0 0;}
    .pw-day.is-today .pw-day-head{background:linear-gradient(135deg,#faf5ff,#f3edff);}
    .pw-day-id{display:flex;align-items:center;gap:10px;min-width:0;}
    .pw-day-num{display:grid;place-items:center;width:38px;height:38px;flex:none;border-radius:12px;background:#eef0f3;color:#3d4149;font-size:16px;font-weight:800;font-variant-numeric:tabular-nums;}
    .pw-day.is-today .pw-day-num{background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;box-shadow:0 4px 12px rgba(124,58,237,.35);}
    .pw-day-txt{display:flex;flex-direction:column;min-width:0;line-height:1.35;}
    .pw-day-txt strong{font-size:13.5px;color:#1a1d23;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .pw-day-txt small{font-size:11px;color:#8a8f98;}
    .pw-day-side{display:flex;align-items:center;gap:6px;flex:none;}
    .pw-today-pill{font-size:10px;font-weight:800;color:#7c3aed;background:#ede9fe;border-radius:20px;padding:2px 9px;text-transform:uppercase;letter-spacing:.4px;}
    .pw-day-count{font-size:11px;font-weight:700;color:#8a8f98;background:#eef0f3;border-radius:20px;padding:2px 9px;font-variant-numeric:tabular-nums;}
    .pw-day-count.has-open{color:#7c3aed;background:#ede9fe;}
    /* routines strip */
    .pw-routines{padding:10px 14px 2px;border-bottom:1px dashed #eef0f3;}
    .pw-routines-head{display:flex;align-items:center;gap:6px;font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:#8a8f98;margin-bottom:6px;}
    .pw-routines-head i{color:#7c3aed;}
    .pw-routines-count{margin-inline-start:auto;background:#eef0f3;border-radius:12px;padding:1px 8px;font-variant-numeric:tabular-nums;}
    .pw-routine-list{display:flex;flex-direction:column;gap:4px;margin-bottom:10px;}
    .pw-rchip{display:flex;align-items:center;gap:7px;min-width:0;font-size:12px;color:#4b5059;background:#fafbfc;border:1px solid #f0f1f3;border-radius:8px;padding:5px 8px;}
    .pw-rchip i{font-size:12px;flex:none;}
    .pw-rchip-title{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .pw-rchip.is-done{color:#9aa0ab;background:transparent;border-color:transparent;}
    .pw-rchip.is-done .pw-rchip-title{text-decoration:line-through;}
    /* period sections */
    .pw-groups{display:flex;flex-direction:column;gap:12px;padding:12px 14px 4px;}
    .pw-group-head{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:800;color:#6b7385;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;}
    .pw-group-head i{font-size:12px;}
    .pw-group-count{background:#eef0f3;border-radius:12px;font-size:10px;padding:1px 7px;color:#8a8f98;font-variant-numeric:tabular-nums;}
    .pw-group-add{margin-inline-start:auto;width:22px;height:22px;border-radius:7px;border:1px solid transparent;background:transparent;color:#b6bbc4;display:grid;place-items:center;cursor:pointer;opacity:0;transition:all .13s;font-size:11px;padding:0;}
    .pw-group:hover .pw-group-add,.pw-group-add:focus-visible{opacity:1;}
    .pw-group-add:hover{background:#ede9fe;color:#7c3aed;border-color:#ddd0fb;}
    @media(hover:none){ .pw-group-add{opacity:1;} }
    .pw-group-body{display:flex;flex-direction:column;gap:6px;min-height:2px;}
    /* task card */
    .pw-task{position:relative;display:flex;gap:9px;align-items:flex-start;background:#fff;border:1px solid #e8eaf0;border-inline-start:3px solid var(--pw-p,#94a3b8);border-radius:12px;padding:10px;transition:border-color .13s,box-shadow .13s;}
    .pw-task:hover{border-color:#d9dce4;box-shadow:0 2px 8px rgba(15,23,42,.06);}
    .pw-task.is-done .pw-task-title{text-decoration:line-through;color:#9aa0ab;}
    @keyframes pwFlash{0%{background:#ede9fe;border-color:#c4b5fd;}100%{background:#fff;}}
    .pw-task.pw-flash{animation:pwFlash 1.6s ease-out;}
    .pw-check{flex:none;margin-top:1px;cursor:pointer;position:relative;}
    .pw-check input{position:absolute;opacity:0;width:1px;height:1px;margin:0;}
    .pw-check-box{display:grid;place-items:center;width:19px;height:19px;border-radius:50%;border:1.5px solid #c9ccd3;color:transparent;font-size:11px;transition:all .13s;background:#fff;}
    .pw-check:hover .pw-check-box{border-color:#30a46c;}
    .pw-check input:checked + .pw-check-box{background:#30a46c;border-color:#30a46c;color:#fff;}
    .pw-check input:focus-visible + .pw-check-box{box-shadow:0 0 0 3px rgba(48,164,108,.25);}
    .pw-task-main{flex:1;min-width:0;}
    .pw-task-title{font-size:13.5px;font-weight:600;color:#1f2328;line-height:1.45;overflow-wrap:anywhere;}
    .pw-task-meta{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:5px;font-size:10.5px;color:#8a8f98;}
    .pw-prio{font-weight:800;text-transform:uppercase;letter-spacing:.3px;background:#f2f3f5;border-radius:6px;padding:1px 7px;}
    .pw-proj{display:inline-flex;align-items:center;gap:4px;min-width:0;}
    .pw-proj i{font-size:10px;flex:none;}
    .pw-est{display:inline-flex;align-items:center;gap:4px;}
    .pw-est i{font-size:10px;}
    .pw-task-acts{position:absolute;top:6px;inset-inline-end:6px;display:flex;gap:2px;background:rgba(255,255,255,.96);border:1px solid #eef0f3;border-radius:9px;padding:2px;opacity:0;transition:opacity .13s;box-shadow:0 2px 8px rgba(15,23,42,.08);}
    .pw-task:hover .pw-task-acts,.pw-task-acts:focus-within{opacity:1;}
    @media(hover:none){ .pw-task-acts{opacity:1;} }
    .pw-act{width:24px;height:24px;border:none;border-radius:7px;background:transparent;color:#8a8f98;display:grid;place-items:center;cursor:pointer;font-size:12px;text-decoration:none;padding:0;font-family:inherit;}
    .pw-act:hover{background:#f0f1f3;color:#1f2328;}
    .pw-act-danger:hover{background:#fdebec;color:#e5484d;}
    /* empty + footer add */
    .pw-day-empty{display:flex;align-items:center;justify-content:center;gap:7px;margin:10px 14px 0;padding:16px 8px;border:1.5px dashed #e3e4e8;border-radius:12px;color:#b9bec7;font-size:12px;}
    .pw-day-empty[hidden]{display:none;}
    .pw-day-empty i{font-size:14px;}
    .pw-day-foot{margin-top:auto;padding:10px 14px 14px;}
    .pw-add{width:100%;display:flex;align-items:center;justify-content:center;gap:6px;padding:8px;border-radius:10px;border:1.5px dashed #d9d4ec;background:#fcfbff;color:#7c3aed;font-size:12.5px;font-weight:700;cursor:pointer;transition:all .13s;font-family:inherit;}
    .pw-add:hover{background:#f5f0ff;border-color:#7c3aed;border-style:solid;box-shadow:0 0 0 3px rgba(124,58,237,.1);}
    /* add popover */
    .pw-pop{position:absolute;z-index:30;inset-inline:8px;bottom:8px;background:#fff;border:1px solid #e3e4e8;border-radius:14px;box-shadow:0 20px 50px rgba(15,23,42,.22);padding:12px;animation:pwIn .15s ease-out;}
    .pw-pop[hidden]{display:none;}
    @keyframes pwIn{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:none;}}
    .pw-pop-chips{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:8px;}
    .pw-pop-chips button{display:inline-flex;align-items:center;gap:4px;border:1px solid #e5e7eb;background:#fafbfc;color:#6b7385;border-radius:16px;padding:3px 10px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;}
    .pw-pop-chips button i{font-size:11px;}
    .pw-pop-chips button.active{background:#ede9fe;border-color:#c4b5fd;color:#7c3aed;}
    .pw-pop-new{display:flex;gap:6px;}
    .pw-pop-new input{flex:1;min-width:0;border:1px solid #e3e4e8;border-radius:9px;padding:7px 10px;font-size:12.5px;outline:none;font-family:inherit;color:#1f2328;}
    .pw-pop-new input:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
    .pw-pop-new button{flex:none;background:#7c3aed;color:#fff;border:none;border-radius:9px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;}
    .pw-pop-new button:hover{background:#6d28d9;}
    .pw-pop-div{display:flex;align-items:center;gap:8px;margin:10px 0 8px;color:#b9bec7;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;}
    .pw-pop-div::before,.pw-pop-div::after{content:'';flex:1;height:1px;background:#eef0f3;}
    .pw-pop-sec input[data-week-search]{width:100%;border:1px solid #e3e4e8;border-radius:9px;padding:7px 10px;font-size:12.5px;outline:none;font-family:inherit;color:#1f2328;box-sizing:border-box;}
    .pw-pop-sec input[data-week-search]:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
    .pw-pop-sec select[data-week-project]{width:100%;border:1px solid #e3e4e8;border-radius:9px;padding:7px 10px;font-size:12.5px;outline:none;font-family:inherit;color:#1f2328;box-sizing:border-box;margin-top:6px;background:#fff;}
    .pw-pop-sec select[data-week-project]:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
    .pw-pop-list{display:flex;flex-direction:column;gap:4px;margin-top:6px;max-height:180px;overflow-y:auto;}
    .pw-backlog{display:flex;align-items:center;gap:7px;width:100%;text-align:start;border:1px solid #eef0f3;background:#fff;border-radius:9px;padding:7px 9px;cursor:pointer;font-family:inherit;transition:all .12s;box-sizing:border-box;}
    .pw-backlog:hover{border-color:#c4b5fd;background:#faf5ff;}
    .pw-backlog .dot{width:8px;height:8px;border-radius:50%;flex:none;}
    .pw-backlog .t{flex:1;min-width:0;font-size:12.5px;font-weight:600;color:#1f2328;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .pw-backlog .p{font-size:10px;color:#8a8f98;flex:none;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .pw-pop-hint{font-size:11px;color:#b9bec7;text-align:center;padding:8px 4px 2px;}
    /* ── Month view: compact calendar overview ── */
    .pm-grid{background:#fff;border:1px solid #e3e4e8;border-radius:14px;overflow:hidden;}
    .pm-weekdays{display:grid;grid-template-columns:repeat(7,1fr);background:#fafbfc;border-bottom:1px solid #eef0f3;}
    .pm-weekdays span{padding:9px 4px;text-align:center;font-size:11px;font-weight:800;color:#8a8f98;text-transform:uppercase;letter-spacing:.4px;}
    .pm-days{display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:#eef0f3;}
    .pm-day{background:#fff;min-height:96px;padding:7px 8px;display:flex;flex-direction:column;gap:4px;text-decoration:none;color:inherit;transition:background .12s;}
    .pm-day:hover{background:#f5f0ff;}
    .pm-day.is-out{opacity:.42;}
    .pm-num{width:26px;height:26px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:800;color:#3d4149;font-variant-numeric:tabular-nums;}
    .pm-day.is-today .pm-num{background:#7c3aed;color:#fff;box-shadow:0 2px 8px rgba(124,58,237,.35);}
    .pm-tasks{display:flex;flex-direction:column;gap:3px;min-height:2px;}
    .pm-t{font-size:11px;font-weight:600;color:#4c1d95;background:#f5f0ff;border-radius:6px;padding:2px 7px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .pm-day.is-out .pm-t{background:#f4f4f5;color:#8a8f98;}
    .pm-more{font-size:10.5px;font-weight:700;color:#8a8f98;padding-inline-start:7px;}
    .pm-meta{margin-top:auto;display:flex;align-items:center;gap:8px;padding-top:3px;}
    .pm-m{display:inline-flex;align-items:center;gap:3px;font-size:10.5px;font-weight:700;color:#6b7385;font-variant-numeric:tabular-nums;}
    .pm-dot{width:7px;height:7px;border-radius:50%;display:inline-block;flex:none;}
    .pm-dot.t{background:#3b82f6;}
    .pm-dot.d{background:#16a34a;}
    .pm-dot.r{background:#7c3aed;}
    .pm-legend{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:10px;font-size:11.5px;color:#8a8f98;font-weight:600;}
    .pm-legend span{display:inline-flex;align-items:center;gap:5px;}
    .pm-legend .pm-hint{margin-inline-start:auto;color:#b9bec7;font-weight:500;}
    @media(max-width:640px){
        .pm-day{min-height:64px;padding:5px 6px;}
        .pm-t,.pm-more{display:none;}
        .pm-weekdays span{font-size:10px;padding:7px 2px;}
    }
    /* floating move menu */
    .pw-move{position:fixed;z-index:1200;min-width:200px;background:#fff;border:1px solid #e3e4e8;border-radius:12px;box-shadow:0 16px 40px rgba(15,23,42,.2);padding:6px;animation:pwIn .13s ease-out;}
    .pw-move[hidden]{display:none;}
    .pw-move-title{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:#8a8f98;padding:6px 10px 4px;}
    .pw-move button{display:flex;align-items:center;gap:8px;width:100%;border:none;background:transparent;border-radius:8px;padding:8px 10px;font-size:12.5px;font-weight:600;color:#1f2328;cursor:pointer;font-family:inherit;text-align:start;box-sizing:border-box;}
    .pw-move button:hover{background:#f5f0ff;color:#7c3aed;}
    .pw-move button.current{color:#7c3aed;background:#f5f0ff;}
    .pw-move button .d{margin-inline-start:auto;font-size:11px;color:#8a8f98;font-variant-numeric:tabular-nums;}
    /* ── Avoid confirm modal + persistent undo stack ── */
    #plAvoidUndoStack{position:fixed;bottom:22px;left:50%;transform:translateX(-50%);z-index:1075;display:flex;flex-direction:column;gap:8px;align-items:center;}
    .pl-undo-bar{background:#1f2328;color:#fff;font-size:13px;padding:9px 14px;border-radius:10px;display:flex;align-items:center;gap:12px;box-shadow:0 6px 20px rgba(0,0,0,.25);white-space:nowrap;}
    .pl-undo-bar .pl-undo-count{color:#fca5a5;font-weight:800;font-variant-numeric:tabular-nums;}
    .pl-undo-bar button{background:none;border:none;color:#a78bfa;font-size:12.5px;font-weight:800;cursor:pointer;padding:2px 4px;}
    .pl-undo-bar button:hover{color:#fff;}
    .pl-avoid-confirm{position:fixed;inset:0;z-index:1090;display:flex;align-items:center;justify-content:center;padding:16px;}
    .pl-avoid-confirm[hidden]{display:none;}
    .pl-avoid-confirm-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.5);backdrop-filter:blur(2px);}
    .pl-avoid-confirm-dialog{position:relative;background:#fff;border-radius:14px;max-width:420px;width:100%;padding:18px;box-shadow:0 24px 60px rgba(0,0,0,.3);}
    .pl-avoid-confirm-title{font-weight:800;font-size:15px;color:#1f2328;margin-bottom:4px;}
    .pl-avoid-confirm-sub{font-size:12px;color:#8a8f98;margin-bottom:12px;}
    .pl-avoid-confirm-rows{display:flex;flex-direction:column;gap:6px;margin-bottom:14px;font-size:13px;color:#3d4149;}
    .pl-avoid-confirm-rows .r{display:flex;justify-content:space-between;gap:10px;padding:6px 10px;background:#fafbfc;border:1px solid #eef0f3;border-radius:8px;}
    .pl-avoid-confirm-rows .k{color:#8a8f98;font-weight:600;}
    .pl-avoid-confirm-rows .v{font-weight:700;color:#1f2328;text-align:end;overflow:hidden;text-overflow:ellipsis;max-width:220px;}
    /* ── Phase 1+2: clean avoid form grid ── */
    .pl-avoid-panel form{display:flex;flex-direction:column;gap:8px;padding:10px;background:#fafbfc;border:1px solid #eef0f3;border-radius:10px;}
    .pl-avoid-grid{display:flex;gap:6px;flex-wrap:wrap;}
    .pl-avoid-grid input,.pl-avoid-grid select{flex:1;min-width:110px;padding:7px 9px;border:1px solid #d3d5db;border-radius:8px;font-size:12.5px;background:#fff;color:#1f2328;outline:none;}
    .pl-avoid-grid input:focus,.pl-avoid-grid select:focus{border-color:#7c3aed;box-shadow:0 0 0 2px rgba(124,58,237,.12);}
    .pl-avoid-grid input[name=quantity]{flex:0 0 76px;min-width:76px;}
    .pl-avoid-grid select[name=mood]{flex:0 0 112px;min-width:112px;}
    .pl-avoid-now{flex:0 0 auto;padding:7px 12px;border-radius:8px;border:1px solid #e2e8f0;background:#fff;color:#475569;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;}
    .pl-avoid-now:hover{border-color:#c4b5fd;color:#7c3aed;background:#faf5ff;}
    .pl-avoid-submit{padding:8px 16px;border-radius:8px;border:1px solid #b91c1c;background:#b91c1c;color:#fff;font-weight:800;font-size:13px;cursor:pointer;}
    .pl-avoid-submit:hover{background:#991b1b;}
    [data-note-panel] .pl-avoid-submit{background:#0369a1;border-color:#0369a1;}
    [data-note-panel] .pl-avoid-submit:hover{background:#075985;}
    .pl-avoid-confirm-actions{display:flex;gap:8px;justify-content:flex-end;}
    .pl-avoid-confirm-actions .cancel{padding:8px 16px;border-radius:8px;border:1px solid #d3d5db;background:#fff;color:#6b7385;font-weight:700;cursor:pointer;}
    .pl-avoid-confirm-actions .confirm{padding:8px 18px;border-radius:8px;border:1px solid #b91c1c;background:#b91c1c;color:#fff;font-weight:800;cursor:pointer;}
    .pl-avoid-confirm-actions .confirm.note{background:#0369a1;border-color:#0369a1;}
    /* ── Phase 3: avoid history modal ── */
    .pl-history-dialog{max-width:560px;}
    .pl-history-filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #eef0f3;}
    .pl-history-chips,.pl-history-ranges{display:flex;gap:6px;flex-wrap:wrap;}
    .pl-history-chips button,.pl-history-ranges button{padding:5px 12px;border-radius:20px;border:1px solid #e5e7eb;background:#fafbfc;color:#6b7385;font-size:12px;font-weight:700;cursor:pointer;}
    .pl-history-chips button.active,.pl-history-ranges button.active{background:#1f2328;border-color:#1f2328;color:#fff;}
    .pl-history-list{display:flex;flex-direction:column;gap:8px;max-height:52vh;overflow-y:auto;padding:4px 2px;}
    .pl-history-item{padding:10px 12px;border:1px solid #eef0f3;border-radius:10px;background:#fff;}
    .pl-history-item.slip{border-inline-start:3px solid #ef4444;}
    .pl-history-item.craving{border-inline-start:3px solid #f59e0b;}
    .pl-history-item.note{border-inline-start:3px solid #0ea5e9;}
    .pl-history-top{display:flex;align-items:center;gap:8px;font-size:12px;color:#8a8f98;margin-bottom:4px;}
    .pl-history-kind{font-weight:800;font-size:11px;text-transform:uppercase;letter-spacing:.4px;padding:2px 8px;border-radius:12px;}
    .pl-history-kind.slip{background:#fee2e2;color:#b91c1c;}
    .pl-history-kind.craving{background:#fef3c7;color:#b45309;}
    .pl-history-kind.note{background:#e0f2fe;color:#0369a1;}
    .pl-history-time{margin-inline-start:auto;font-variant-numeric:tabular-nums;}
    .pl-history-main{font-size:13px;color:#1f2328;font-weight:600;}
    .pl-history-meta{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;font-size:11.5px;color:#6b7385;}
    .pl-history-meta span{background:#f8fafc;border:1px solid #eef0f3;border-radius:12px;padding:2px 8px;}
    .pl-history-more{width:100%;margin-top:10px;padding:8px;border-radius:8px;border:1px solid #e2e8f0;background:#fff;font-weight:700;cursor:pointer;}
    .pl-history-loading,.pl-history-empty{text-align:center;color:#8a8f98;padding:18px;font-size:13px;}
    .pl-avoid-history-btn{padding:4px 10px;border-radius:16px;border:1px solid #e2e8f0;background:#fff;color:#475569;font-size:11px;font-weight:700;cursor:pointer;}
    .pl-avoid-history-btn:hover{border-color:#c4b5fd;color:#7c3aed;}
    .pl-avoid-last{font-size:11px;color:#8a8f98;}

    /* ~ Day Log (دفتر روز) — journal-style capture card ~
       Visual only: every class, id and data-attribute below is consumed by the
       existing Day Log script, so the markup contract stays untouched. */
    .lg-section{position:relative;border-color:#e9e4fb;box-shadow:0 1px 3px rgba(124,58,237,.05),0 16px 36px -24px rgba(124,58,237,.6);}
    .lg-section::before{content:'';position:absolute;top:0;inset-inline:0;height:3px;z-index:3;background:linear-gradient(90deg,#8b5cf6 0%,#6366f1 45%,#e879f9 100%);}
    .lg-section .pl-section-head{position:relative;background:linear-gradient(135deg,#fcfaff 0%,#f5f2ff 55%,#fdf6ff 100%);border-bottom:1px solid #ede9fe;padding:13px 18px;}
    .lg-section .pl-section-head>i{width:30px;height:30px;flex-shrink:0;display:grid;place-items:center;border-radius:10px;background:linear-gradient(135deg,#7c3aed,#9333ea);color:#fff;font-size:15px;box-shadow:0 6px 14px -8px rgba(109,40,217,.9),inset 0 1px 0 rgba(255,255,255,.35);}
    .lg-section .pl-section-title{font-size:14.5px;font-weight:800;color:#191426;letter-spacing:-.01em;}
    .lg-beta{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:800;background:#fff;color:#6d28d9;border:1px solid #ddd6fe;border-radius:20px;padding:3px 9px;box-shadow:0 1px 2px rgba(124,58,237,.12);}
    .lg-beta::before{content:'';width:5px;height:5px;border-radius:50%;background:#8b5cf6;box-shadow:0 0 0 3px rgba(139,92,246,.22);}
    .lg-section .pl-section-count{background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;font-size:11.5px;font-weight:800;padding:3px 11px;border-radius:20px;min-width:26px;text-align:center;box-shadow:0 6px 14px -9px rgba(109,40,217,.95);}
    .lg-body{position:relative;padding:14px 16px 16px;display:flex;flex-direction:column;gap:12px;
        background-image:radial-gradient(rgba(124,58,237,.075) 1px,transparent 1.2px);background-size:17px 17px;background-position:6px 6px;}

    /* Hero — one input, one send; the sentence and its action read as one control */
    .lg-hero{position:relative;z-index:40;}
    .lg-composer{display:flex;align-items:center;gap:9px;min-height:52px;border:1.5px solid #e7e3f7;border-radius:15px;background:#fff;padding-inline:14px 7px;
        box-shadow:0 1px 2px rgba(15,23,42,.04),inset 0 1px 0 #fff;transition:border-color .18s ease,box-shadow .18s ease;}
    .lg-composer:hover{border-color:#d9d0fa;}
    .lg-composer:focus-within{border-color:#8b5cf6;box-shadow:0 0 0 4px rgba(139,92,246,.14),0 12px 28px -20px rgba(109,40,217,.9);}
    .lg-composer .lg-composer-ico{flex-shrink:0;font-size:16px;color:#a78bfa;transition:color .18s ease;}
    .lg-composer:focus-within .lg-composer-ico{color:#7c3aed;}
    .lg-composer .lg-input{flex:1;min-width:0;height:100%;border:0;padding:0;background:transparent;font-family:inherit;font-size:14.5px;font-weight:600;color:#0f172a;outline:none;}
    .lg-composer .lg-input::placeholder{color:#a4a9b7;font-weight:500;}
    .lg-send{flex-shrink:0;display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 17px;border:0;border-radius:11px;
        background:linear-gradient(135deg,#7c3aed 0%,#6366f1 100%);color:#fff;font-family:inherit;font-size:12.5px;font-weight:800;cursor:pointer;touch-action:manipulation;
        box-shadow:0 10px 20px -12px rgba(109,40,217,.95),inset 0 1px 0 rgba(255,255,255,.28);
        transition:transform .12s ease,box-shadow .18s ease,filter .18s ease,background .18s ease;}
    .lg-send:hover:not(:disabled){filter:saturate(1.08) brightness(1.05);transform:translateY(-1px);box-shadow:0 14px 26px -12px rgba(109,40,217,1),inset 0 1px 0 rgba(255,255,255,.3);}
    .lg-send:active:not(:disabled){transform:translateY(0);}
    .lg-send:disabled{background:linear-gradient(135deg,#f1f1f6,#ebeef4);color:#a7adbd;box-shadow:none;cursor:default;}
    .lg-send:focus-visible{outline:2px solid #312e81;outline-offset:2px;}

    /* Suggestions */
    .lg-suggest{position:absolute;top:calc(100% + 8px);inset-inline:0;max-height:264px;overflow-x:hidden;overflow-y:auto;background:#fff;border:1px solid #ece9f8;
        border-radius:14px;box-shadow:0 24px 50px -24px rgba(15,23,42,.42),0 3px 10px rgba(15,23,42,.05);z-index:60;display:none;}
    .lg-suggest.show{display:block;animation:lgPop .16s ease both;}
    @keyframes lgPop{from{opacity:0;transform:translateY(-6px) scale(.985);}to{opacity:1;transform:none;}}
    .lg-suggest-item{display:flex;align-items:center;gap:10px;padding:9px 12px;font-size:12.5px;font-weight:700;color:#334155;cursor:pointer;touch-action:manipulation;transition:background .12s ease;}
    .lg-suggest-item+.lg-suggest-item{border-top:1px solid #f4f4f8;}
    .lg-suggest-item>i{width:26px;height:26px;flex-shrink:0;display:grid;place-items:center;border-radius:9px;background:#f2eeff;color:#7c3aed;font-size:12.5px;}
    .lg-suggest-item:hover,.lg-suggest-item.active{background:#faf8ff;color:#4c1d95;}
    .lg-suggest-item.active>i{background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;}
    .lg-suggest-item small{margin-inline-start:auto;font-size:10.5px;font-weight:800;color:#9aa1ac;background:#f4f5f8;border-radius:20px;padding:2px 8px;}
    .lg-suggest-empty{padding:12px 14px;font-size:12px;font-weight:600;color:#98a1b3;}

    /* On-demand pills: each shows its own state */
    .lg-pills{display:flex;align-items:center;gap:7px;flex-wrap:wrap;}
    .lg-pill{display:inline-flex;align-items:center;gap:7px;height:31px;padding:0 13px;border:1px solid #e9e9f2;background:#fff;border-radius:20px;
        font-family:inherit;font-size:11.5px;font-weight:700;color:#767c8f;cursor:pointer;touch-action:manipulation;box-shadow:0 1px 2px rgba(15,23,42,.04);transition:all .15s ease;}
    .lg-pill:hover{border-color:#ddd6fe;background:#fdfcff;color:#6d28d9;transform:translateY(-1px);box-shadow:0 6px 14px -10px rgba(124,58,237,.9);}
    .lg-pill.is-on{border-color:#c4b5fd;background:linear-gradient(135deg,#f7f4ff,#f2ecff);color:#6d28d9;box-shadow:0 6px 16px -10px rgba(124,58,237,.95);}
    .lg-pill:focus-visible{outline:2px solid #8b5cf6;outline-offset:2px;}
    .lg-pill:disabled{opacity:.45;cursor:default;transform:none;box-shadow:none;}
    .lg-pill:disabled:hover{border-color:#e9e9f2;background:#fff;color:#767c8f;transform:none;box-shadow:0 1px 2px rgba(15,23,42,.04);}
    .lg-pill i{font-size:12px;}

    /* Panel — the optional details live here */
    .lg-panel{position:relative;border:1px solid #ecebf6;background:linear-gradient(135deg,#fdfcff 0%,#faf9ff 100%);border-radius:14px;padding:12px 14px;padding-inline-start:17px;
        display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 2px rgba(15,23,42,.03);animation:lgPanelIn .18s ease both;}
    .lg-panel[hidden]{display:none;}
    .lg-panel::before{content:'';position:absolute;inset-inline-start:0;top:14px;bottom:14px;width:3.5px;border-radius:4px;background:linear-gradient(180deg,#a78bfa,#7c3aed);}
    @keyframes lgPanelIn{from{opacity:0;transform:translateY(-6px);}to{opacity:1;transform:none;}}
    .lg-panel .lg-input{flex:1;min-width:180px;height:36px;border:1px solid #e3e4ec;border-radius:10px;padding:0 12px;font-family:inherit;font-size:12.5px;font-weight:600;color:#1e293b;background:#fff;outline:none;transition:border-color .15s ease,box-shadow .15s ease;}
    .lg-panel .lg-input:focus{border-color:#8b5cf6;box-shadow:0 0 0 3px rgba(139,92,246,.14);}
    .lg-num{width:64px;height:36px;border:1px solid #e3e4ec;border-radius:10px;font-family:inherit;font-size:13px;font-weight:800;color:#1e293b;background:#fff;text-align:center;font-variant-numeric:tabular-nums;outline:none;transition:border-color .15s ease,box-shadow .15s ease;}
    .lg-sel{height:36px;border:1px solid #e3e4ec;border-radius:10px;padding:0 10px;font-family:inherit;font-size:12px;font-weight:700;color:#334155;background:#fff;outline:none;transition:border-color .15s ease,box-shadow .15s ease;}
    .lg-time{height:36px;width:96px;border:1px solid #e3e4ec;border-radius:10px;padding:0 10px;font-family:inherit;font-size:12px;font-weight:700;color:#334155;background:#fff;outline:none;transition:border-color .15s ease,box-shadow .15s ease;}
    .lg-num:focus,.lg-sel:focus,.lg-time:focus{border-color:#8b5cf6;box-shadow:0 0 0 3px rgba(139,92,246,.14);}
    .lg-sep{color:#b7bcc8;font-weight:800;font-size:11.5px;}
    .lg-quick{display:inline-flex;gap:3px;padding:3px;background:#f1f0f8;border-radius:20px;}
    .lg-quick button{border:1px solid transparent;background:transparent;border-radius:20px;height:26px;padding:0 11px;font-family:inherit;font-size:11px;font-weight:800;color:#797f92;cursor:pointer;touch-action:manipulation;transition:all .14s ease;}
    .lg-quick button:hover{color:#6d28d9;background:#fff;box-shadow:0 1px 3px rgba(15,23,42,.08);}
    .lg-quick button.active{background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;border-color:transparent;box-shadow:0 6px 14px -8px rgba(109,40,217,.95);}
    .lg-chip{display:inline-flex;align-items:center;gap:6px;height:28px;padding:0 12px;border:1px solid #e9e9f2;background:#fff;border-radius:20px;font-family:inherit;font-size:11px;font-weight:800;color:#767c8f;cursor:pointer;touch-action:manipulation;transition:all .14s ease;}
    .lg-chip:hover{border-color:#ddd6fe;color:#6d28d9;background:#fdfcff;}
    .lg-chip.active{border-color:#c4b5fd;background:linear-gradient(135deg,#f6f2ff,#f1eaff);color:#6d28d9;box-shadow:0 4px 12px -8px rgba(124,58,237,.9);}
    .lg-chip i{font-size:11.5px;}
    .lg-label{font-size:11px;font-weight:800;color:#8b90a1;}

    /* Quiet summary + one suggestion at a time */
    .lg-meta{margin:0;min-height:16px;font-size:11.5px;font-weight:700;color:#8b90a1;font-variant-numeric:tabular-nums;line-height:1.7;}
    .lg-meta:empty{display:none;}
    .lg-meta:not(:empty)::before{content:'';display:inline-block;width:6px;height:6px;border-radius:50%;background:linear-gradient(135deg,#a78bfa,#7c3aed);margin-inline-end:7px;vertical-align:1px;box-shadow:0 0 0 3px rgba(139,92,246,.14);}
    .lg-meta b{color:#1f2937;font-weight:800;}
    .lg-meta .lg-dot{display:inline-block;width:4px;height:4px;border-radius:50%;background:#cfd4dd;vertical-align:2px;margin:0 8px;}
    .lg-ask{position:relative;display:flex;align-items:center;gap:11px;border:1px solid #ece8fb;background:linear-gradient(135deg,#fbfaff 0%,#f5f1ff 100%);border-radius:14px;padding:11px 13px;
        font-size:12px;font-weight:600;color:#5a6274;line-height:1.7;overflow:hidden;box-shadow:0 1px 2px rgba(15,23,42,.03);animation:lgPanelIn .2s ease both;}
    .lg-ask::before{content:'';position:absolute;inset-inline-start:0;top:0;bottom:0;width:4px;background:linear-gradient(180deg,#a78bfa,#7c3aed);}
    .lg-ask>i{width:32px;height:32px;flex-shrink:0;display:grid;place-items:center;border-radius:11px;background:#ede9fe;color:#6d28d9;font-size:15px;box-shadow:inset 0 1px 0 rgba(255,255,255,.75);}
    .lg-ask.quit{border-color:#fbe9e9;background:linear-gradient(135deg,#fffbfb 0%,#fff4f4 100%);}
    .lg-ask.quit::before{background:linear-gradient(180deg,#fca5a5,#ef4444);}
    .lg-ask.quit>i{background:#fee2e2;color:#b91c1c;}
    .lg-ask b{color:#171426;font-weight:800;}
    .lg-ask-acts{margin-inline-start:auto;display:flex;gap:6px;flex-shrink:0;align-items:center;}
    .lg-ask-acts button{border:1px solid transparent;background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;font-family:inherit;font-size:11.5px;font-weight:800;padding:7px 13px;border-radius:10px;
        cursor:pointer;touch-action:manipulation;white-space:nowrap;box-shadow:0 8px 16px -10px rgba(109,40,217,.95);transition:all .14s ease;}
    .lg-ask-acts button:hover{filter:brightness(1.07);transform:translateY(-1px);}
    .lg-ask-acts button.later{background:#fff;border-color:#e3e5ec;color:#7a8194;box-shadow:0 1px 2px rgba(15,23,42,.05);font-weight:700;}
    .lg-ask-acts button.later:hover{background:#f7f8fa;color:#4b5568;filter:none;}
    .lg-ask.quit .lg-ask-acts button{background:linear-gradient(135deg,#dc2626,#b91c1c);box-shadow:0 8px 16px -10px rgba(185,28,28,.95);}
    .lg-ask.quit .lg-ask-acts button:hover{background:linear-gradient(135deg,#dc2626,#b91c1c);filter:brightness(1.06);}
    .lg-ask.quit .lg-ask-acts button.later{background:#fff;border-color:#e3e5ec;color:#7a8194;box-shadow:0 1px 2px rgba(15,23,42,.05);filter:none;}
    .lg-ask-acts button:focus-visible{outline:2px solid #8b5cf6;outline-offset:2px;}

    /* Period groups + entry cards */
    .lg-groups{display:flex;flex-direction:column;gap:6px;}
    .lg-group-head{display:flex;align-items:center;gap:8px;padding:13px 2px 5px;font-size:11.5px;font-weight:800;color:#4b5568;}
    .lg-group-head>i{width:24px;height:24px;flex-shrink:0;display:grid;place-items:center;border-radius:8px;font-size:12px;
        background:#f3f0ff;background:color-mix(in srgb,currentColor 15%,#fff);box-shadow:0 1px 2px rgba(15,23,42,.06);}
    .lg-group-head::after{content:'';order:0;flex:1;min-width:16px;height:1px;background-image:repeating-linear-gradient(to right,#e2e5ec 0 5px,transparent 5px 11px);}
    .lg-group-head .lg-group-count{order:1;margin-inline-start:0;font-size:10.5px;font-weight:800;color:#8b90a1;background:#f3f4f8;border:1px solid #ecedf3;border-radius:20px;padding:1px 9px;font-variant-numeric:tabular-nums;}
    .lg-row{position:relative;display:flex;align-items:stretch;gap:11px;padding:9px 11px;border-radius:13px;background:#fff;border:1px solid #eef0f5;
        transition:background .15s ease,border-color .15s ease,box-shadow .18s ease,transform .18s ease;}
    .lg-row:hover,.lg-row:focus-within{background:#fdfdff;border-color:#e4e0f6;box-shadow:0 12px 28px -20px rgba(15,23,42,.6);transform:translateY(-1px);}
    .lg-row.is-new{animation:lgRowIn .9s ease;}
    @keyframes lgRowIn{0%{background:#f5f1ff;border-color:#c4b5fd;box-shadow:0 0 0 4px rgba(139,92,246,.16);}100%{background:#fff;border-color:#eef0f5;box-shadow:none;}}
    .lg-row-dot{align-self:stretch;width:3.5px;min-height:26px;flex-shrink:0;border-radius:4px;background:var(--c,#c4b5fd);opacity:.88;transition:opacity .15s ease;}
    .lg-row:hover .lg-row-dot,.lg-row:focus-within .lg-row-dot{opacity:1;}
    /* One flowing line: the title opens it, meta/note close it, actions end it.
       When the title is long the meta block wraps onto its own line. */
    .lg-row-main{flex:1;min-width:0;display:flex;flex-direction:row;flex-wrap:wrap;align-items:center;gap:5px 10px;}
    .lg-row-title{flex:1 1 auto;font-size:13.5px;font-weight:700;color:#1b2432;line-height:1.5;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .lg-row-sub{display:flex;align-items:center;gap:7px;min-width:0;flex:0 1 auto;}
    .lg-row-meta{font-size:10.5px;font-weight:800;color:#6b7280;background:#f5f6f9;border:1px solid #edeff3;border-radius:20px;padding:1.5px 9px;white-space:nowrap;flex-shrink:0;font-variant-numeric:tabular-nums;}
    .lg-row-note{font-size:11.5px;font-weight:600;color:#9aa1ae;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .lg-row.is-journal{background:linear-gradient(135deg,#fffdf6 0%,#fffbee 100%);border-color:#f3e7c9;}
    .lg-row.is-journal:hover{background:linear-gradient(135deg,#fffcf3 0%,#fff8e4 100%);border-color:#ecd9b0;}
    html[dir="ltr"] .lg-row.is-journal .lg-row-title{font-style:italic;font-weight:600;color:#7c6038;}
    html[dir="rtl"] .lg-row.is-journal .lg-row-title{font-weight:700;color:#7c6038;white-space:normal;overflow:visible;overflow-wrap:anywhere;line-height:1.65;}
    .lg-row.is-journal .lg-row-meta{background:#fdf1d8;border-color:#f6e5bf;color:#98690a;}
    .lg-row.is-journal .lg-row-note{color:#b39a6d;}
    .lg-row-acts{display:flex;gap:2px;flex-shrink:0;align-self:center;opacity:0;transform:scale(.94);transition:opacity .15s ease,transform .15s ease;}
    .lg-row:hover .lg-row-acts,.lg-row:focus-within .lg-row-acts{opacity:1;transform:none;}
    .lg-icon{width:28px;height:28px;display:grid;place-items:center;border:0;background:transparent;border-radius:9px;color:#9aa1ae;cursor:pointer;font-size:13px;touch-action:manipulation;transition:all .14s ease;}
    .lg-icon:hover{background:#f2eeff;color:#6d28d9;}
    .lg-icon.danger:hover{background:#fef2f2;color:#dc2626;}
    .lg-icon:focus-visible{outline:2px solid #8b5cf6;outline-offset:1px;}
    .lg-edit{display:block;width:100%;box-sizing:border-box;min-width:0;height:30px;border:1.5px solid #8b5cf6;border-radius:9px;padding:0 10px;font-family:inherit;font-size:13px;font-weight:700;color:#1e293b;background:#fff;outline:none;box-shadow:0 0 0 3px rgba(139,92,246,.14);}
    .lg-row-title:has(.lg-edit){overflow:visible;}

    .lg-empty{display:flex;align-items:center;justify-content:center;gap:10px;padding:24px 14px;font-size:12.5px;font-weight:700;color:#98a0af;
        background:linear-gradient(135deg,#fbfbfe,#f7f8fc);border:1px dashed #e5e7ef;border-radius:14px;}
    .lg-empty>i{width:34px;height:34px;display:grid;place-items:center;border-radius:12px;background:#f0ecff;color:#8b5cf6;font-size:15px;}
    .lg-foot{margin:0;padding-top:10px;border-top:1px dashed #edeff4;font-size:10.5px;font-weight:600;color:#adb2bf;text-align:center;}
    @media (prefers-reduced-motion:reduce){
        .lg-row-acts,.lg-row,.lg-send,.lg-pill,.lg-composer,.lg-chip,.lg-quick button,.lg-ask-acts button{transition:none;}
        .lg-panel,.lg-ask,.lg-suggest.show{animation:none;}
        .lg-row.is-new{animation:none;background:#f5f1ff;border-color:#c4b5fd;}
        .lg-row:hover,.lg-pill:hover,.lg-send:hover:not(:disabled),.lg-ask-acts button:hover{transform:none;}
    }
    @media (hover:none){
        .lg-row-acts{opacity:1;transform:none;}
    }
    @media (max-width:576px){
        .lg-body{padding:12px 12px 14px;}
        .lg-section .pl-section-head{padding:12px 14px;}
        .lg-row-title{flex:1 1 100%;}
        .lg-row-sub{flex-wrap:wrap;gap:4px 7px;}
        .lg-row-acts{opacity:1;transform:none;}
        .lg-send{padding:0 13px;}
        .lg-panel{padding:11px 13px;padding-inline-start:16px;}
        .lg-panel .lg-label{min-width:100%;}
        .lg-ask{flex-wrap:wrap;align-items:flex-start;}
        .lg-ask-acts{margin-inline-start:0;width:100%;justify-content:flex-start;}
        .lg-foot{text-align:start;}
    }
    .lg-fields{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .lg-panel .lg-fields{flex:1 1 100%;}
    .lg-fields[hidden]{display:none;}
    .lg-panel .lg-label{min-width:60px;}
    .lg-chips{display:flex;gap:5px;flex-wrap:wrap;}
    .lg-clear{margin-inline-start:auto;color:#8b90a1;}
    .lg-clear:hover{border-color:#dfe1e9;color:#64748b;background:#fff;}

    /* ══ Day Log — Pro redesign layer (overrides prototype look, same markup/JS contract) ══ */
    .lg-section{--lg-ink:#181430;--lg-violet:#7c3aed;--lg-indigo:#4f46e5;--lg-gold:#f59e0b;
        border:1px solid #e4defc;border-radius:20px;overflow:hidden;background:#fff;
        box-shadow:0 1px 2px rgba(23,20,48,.05),0 12px 32px -18px rgba(76,29,149,.28);}
    .lg-section::before{display:none;}
    #lgSection .pl-section-head{position:relative;gap:13px;padding:16px 20px 15px;border-bottom:0;color:#fff;
        background:linear-gradient(135deg,#171231 0%,#2d1b69 42%,#5b21b6 78%,#7c3aed 100%);overflow:hidden;}
    #lgSection .pl-section-head::after{content:'';position:absolute;inset:0;pointer-events:none;opacity:.9;
        background:
            radial-gradient(220px 130px at 88% -30%,rgba(232,121,249,.45),transparent 65%),
            radial-gradient(260px 150px at 8% 130%,rgba(99,102,241,.5),transparent 62%),
            radial-gradient(rgba(255,255,255,.16) 1px,transparent 1.4px);
        background-size:auto,auto,18px 18px;}
    #lgSection .pl-section-head>*{position:relative;z-index:1;}
    .lg-head-icon{width:42px;height:42px;flex-shrink:0;display:grid;place-items:center;border-radius:14px;color:#fff;font-size:19px;
        background:linear-gradient(135deg,rgba(255,255,255,.22),rgba(255,255,255,.06));
        border:1px solid rgba(255,255,255,.28);box-shadow:inset 0 1px 0 rgba(255,255,255,.35),0 8px 18px -8px rgba(0,0,0,.5);}
    .lg-head-text{display:flex;flex-direction:column;gap:2px;min-width:0;}
    #lgSection .pl-section-title{color:#fff;font-size:15.5px;font-weight:800;letter-spacing:-.01em;line-height:1.3;}
    .lg-head-sub{font-size:11.5px;font-weight:600;color:rgba(255,255,255,.72);line-height:1.5;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .lg-beta{background:rgba(245,158,11,.16);color:#fde68a;border:1px solid rgba(253,230,138,.4);box-shadow:none;backdrop-filter:blur(4px);}
    .lg-beta::before{background:#fbbf24;box-shadow:0 0 0 3px rgba(251,191,36,.25);}
    #lgSection .pl-section-count{margin-inline-start:auto;background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.25);
        font-size:12px;font-weight:800;padding:5px 13px;box-shadow:none;backdrop-filter:blur(6px);font-variant-numeric:tabular-nums;}
    .lg-body{gap:13px !important;padding:16px 18px 15px !important;background:linear-gradient(180deg,#fff 0%,#faf8ff 60%,#f6f1ff 100%) !important;}
    .lg-intro{display:flex;align-items:center;gap:10px;font-size:12px;font-weight:600;color:#5b5470;line-height:1.8;
        background:linear-gradient(135deg,#f6f2ff,#fdf4ff);border:1px solid #e9e2fb;border-radius:13px;padding:9px 13px;}
    .lg-intro>i{width:30px;height:30px;flex-shrink:0;display:grid;place-items:center;border-radius:10px;background:#fff;color:#7c3aed;
        border:1px solid #e6defc;font-size:14px;box-shadow:0 2px 6px rgba(124,58,237,.12);}
    .lg-intro b{color:#3b2d6e;font-weight:800;}
    .lg-intro code{font-family:inherit;font-size:11px;font-weight:800;color:#6d28d9;background:#fff;border:1px solid #e2d9fb;border-radius:7px;padding:1px 7px;white-space:nowrap;}
    .lg-hero{z-index:40;}
    .lg-composer{min-height:58px !important;border-radius:18px !important;border:1.5px solid #e2d9fb !important;
        background:linear-gradient(180deg,#fff,#fcfbff) !important;padding-inline:16px 8px !important;
        box-shadow:0 1px 2px rgba(23,20,48,.05),0 10px 26px -18px rgba(76,29,149,.35) !important;}
    .lg-composer:hover{border-color:#c9b8fa !important;}
    .lg-composer:focus-within{border-color:#7c3aed !important;box-shadow:0 0 0 4px rgba(124,58,237,.13),0 18px 36px -20px rgba(76,29,149,.55) !important;}
    .lg-composer .lg-input{font-size:15px !important;}
    .lg-send{height:42px !important;padding:0 20px !important;border-radius:13px !important;font-size:13px !important;position:relative;overflow:hidden;}
    .lg-send::after{content:'';position:absolute;top:0;bottom:0;width:36px;left:-46px;transform:skewX(-18deg);
        background:linear-gradient(90deg,transparent,rgba(255,255,255,.45),transparent);transition:left .5s ease;}
    .lg-send:hover:not(:disabled)::after{left:130%;}
    .lg-kbd{flex-shrink:0;font-size:10px;font-weight:800;color:#a5adbe;background:#f3f4f7;border:1px solid #e5e7ee;border-bottom-width:2px;
        border-radius:7px;padding:3px 8px;font-variant-numeric:tabular-nums;}
    .lg-suggest{border-radius:16px !important;overflow:hidden;}
    .lg-suggest-item{padding:11px 14px !important;}
    .lg-pills{background:#f5f2ff;border:1px solid #e9e2fb;border-radius:14px;padding:7px 9px;}
    .lg-pills-bar-label{font-size:10.5px;font-weight:800;color:#8b84a8;margin-inline:6px 2px;letter-spacing:.02em;}
    .lg-pill{border-radius:13px !important;height:34px !important;background:#fff !important;}
    .lg-pill.is-on{background:linear-gradient(135deg,#fff,#f3edff) !important;box-shadow:0 4px 12px -8px rgba(109,40,217,.7) !important;}
    .lg-pill .lg-chev{font-size:9px;color:#b6b0cc;transition:transform .15s ease;}
    .lg-pill[aria-expanded="true"] .lg-chev{transform:rotate(180deg);}
    .lg-panel{border-radius:16px !important;border:1px solid #e9e2fb !important;
        background:linear-gradient(135deg,#fff 0%,#faf8ff 100%) !important;box-shadow:0 6px 18px -14px rgba(76,29,149,.4) !important;
        padding:13px 15px !important;}
    .lg-panel-head{display:flex;align-items:center;gap:8px;flex:1 1 100%;margin-bottom:2px;}
    .lg-panel-head>i{width:26px;height:26px;display:grid;place-items:center;border-radius:9px;background:#f1eafe;color:#6d28d9;font-size:13px;}
    .lg-panel-head b{font-size:12px;font-weight:800;color:#2d2450;}
    .lg-panel-head small{font-size:11px;font-weight:600;color:#9893ad;}
    .lg-panel .lg-input,.lg-num,.lg-sel,.lg-time{border-radius:11px !important;box-shadow:0 1px 2px rgba(23,20,48,.04);}
    .lg-quick{background:#efeafd !important;border:1px solid #e2d7fb;}
    .lg-chip{border-radius:12px !important;}
    #lgMeta{background:#f7f5ff;border:1px solid #eee9fc;border-radius:12px;padding:8px 13px;font-size:12px !important;}
    #lgMeta:empty{display:none;}
    .lg-groups{gap:8px !important;}
    .lg-group-head{position:sticky;top:0;z-index:2;background:linear-gradient(180deg,#faf8ff 70%,rgba(250,248,255,0));padding:14px 4px 7px !important;}
    .lg-group-head>span:nth-of-type(1){font-size:12.5px;color:#2d2450;}
    .lg-row{border-radius:15px !important;padding:11px 13px !important;box-shadow:0 1px 2px rgba(23,20,48,.04);}
    .lg-row:hover{box-shadow:0 14px 30px -20px rgba(35,20,90,.45) !important;transform:translateY(-1px);}
    .lg-row-dot{width:4px !important;}
    .lg-row.is-journal{background:linear-gradient(135deg,#fffdf4 0%,#fff7df 100%) !important;border:1px dashed #e8cf96 !important;}
    .lg-empty{border-radius:16px !important;padding:30px 16px !important;background:radial-gradient(400px 120px at 50% 0%,#f3edff,transparent),linear-gradient(135deg,#fbfbfe,#f5f3ff) !important;}
    .lg-empty>i{width:46px;height:46px !important;font-size:20px !important;border-radius:15px !important;
        background:linear-gradient(135deg,#7c3aed,#6366f1) !important;color:#fff !important;box-shadow:0 10px 20px -10px rgba(109,40,217,.8);}
    .lg-foot{display:flex;align-items:center;justify-content:center;gap:7px;font-size:11px !important;color:#a3a8b8 !important;}
    .lg-ask{border-radius:16px !important;padding:13px 15px !important;box-shadow:0 10px 26px -18px rgba(76,29,149,.5) !important;}
    @media (max-width:576px){
        #lgSection .pl-section-head{padding:14px 15px 13px !important;}
        .lg-head-sub{display:none;}
        .lg-body{padding:13px 13px 12px !important;}
        .lg-kbd{display:none;}
        .lg-intro{font-size:11.5px;}
        .lg-pills{padding:6px;}
    }

    /* ══ Day Log v2 — three calm layers: capture / timeline / insights ══ */
    #lgSection .lg-body{gap:14px !important;}
    .lg-card{background:#fff;border:1px solid #e9e4fa;border-radius:18px;padding:14px 15px;
        box-shadow:0 1px 2px rgba(23,20,48,.04);position:relative;}
    .lg-card-head{display:flex;align-items:center;gap:10px;margin-bottom:11px;}
    .lg-card-head>i{width:30px;height:30px;flex-shrink:0;display:grid;place-items:center;border-radius:10px;font-size:14px;
        background:#f2edff;color:#6d28d9;border:1px solid #e6dcfb;}
    .lg-card-head b{font-size:13px;font-weight:800;color:#241d4b;}
    .lg-card-head small{display:block;font-size:11px;font-weight:600;color:#9893ad;}
    .lg-card-head .lg-card-side{margin-inline-start:auto;font-size:11px;font-weight:800;color:#8b84a8;
        background:#f5f2ff;border:1px solid #e9e2fb;border-radius:20px;padding:3px 11px;font-variant-numeric:tabular-nums;white-space:nowrap;}
    .lg-capture{background:linear-gradient(180deg,#fff 0%,#fbf9ff 100%);}
    .lg-capture .lg-intro{margin-bottom:11px;padding:8px 12px;font-size:11.5px;}
    /* Type segment — mirrors state.kind (wired in JS, visual only) */
    .lg-type-seg{display:flex;background:#f1edfd;border:1px solid #e4dbfa;border-radius:14px;padding:4px;gap:4px;margin-bottom:11px;}
    .lg-type-seg button{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:7px;height:36px;border:0;border-radius:10px;
        background:transparent;font-family:inherit;font-size:12.5px;font-weight:800;color:#7a7396;cursor:pointer;transition:all .15s ease;white-space:nowrap;}
    .lg-type-seg button i{font-size:13px;}
    .lg-type-seg button:hover{color:#5b21b6;background:rgba(255,255,255,.6);}
    .lg-type-seg button.active{background:#fff;color:#4c1d95;box-shadow:0 2px 8px rgba(76,29,149,.18),inset 0 0 0 1px #e2d6fb;}
    .lg-type-seg button:focus-visible{outline:2px solid #8b5cf6;outline-offset:2px;}
    /* Timeline */
    .lg-timeline{background:#fff;padding-bottom:8px !important;}
    #lgSection .lg-groups{position:relative;gap:0 !important;}
    #lgSection .lg-groups::before{content:'';position:absolute;top:12px;bottom:12px;inset-inline-start:23px;width:2px;border-radius:2px;
        background:linear-gradient(180deg,#ddd0fa,#f0eafa 60%,#e9e2fb);}
    html[dir="rtl"] #lgSection .lg-groups::before{inset-inline-start:auto;}
    #lgSection .lg-group-head{position:sticky;top:0;z-index:3;background:rgba(255,255,255,.92);backdrop-filter:blur(6px);
        border-radius:12px;margin:0;padding:12px 4px 8px 0 !important;}
    #lgSection .lg-group-head>i{width:30px;height:30px;font-size:13px;border-radius:10px;background:#fff !important;
        border:1px solid #e9e2fb;box-shadow:0 2px 6px rgba(76,29,149,.12);position:relative;z-index:1;}
    #lgSection .lg-group-head>span:nth-of-type(1){font-size:13px;color:#241d4b;font-weight:800;}
    #lgSection .lg-group-head .lg-group-count{background:#241d4b;color:#fff;border:0;font-size:10.5px;padding:2px 10px;}
    #lgSection .lg-row{margin-inline-start:42px !important;margin-bottom:8px;position:relative;}
    #lgSection .lg-row::before{content:'';position:absolute;inset-inline-start:-26px;top:50%;width:9px;height:9px;border-radius:50%;
        background:#fff;border:2.5px solid var(--c,#c4b5fd);transform:translateY(-50%);box-shadow:0 0 0 3px #f4f0ff;}
    #lgSection .lg-row.is-journal::before{border-color:#d9a441;box-shadow:0 0 0 3px #fdf3d9;}
    #lgSection .lg-row.is-mood{background:linear-gradient(135deg,#faf5ff 0%,#f3edff 100%) !important;border-color:#ddd0fa !important;}
    #lgSection .lg-row.is-mood::before{border-color:#8b5cf6;box-shadow:0 0 0 3px #ede8ff;}
    .lg-mood-badge{font-size:10px;font-weight:800;color:#6d28d9;background:#ede8ff;border:1px solid #d9ccfb;border-radius:20px;
        padding:1.5px 9px;white-space:nowrap;font-variant-numeric:tabular-nums;}
    /* Row clock — logged hour + part of day, scannable at a glance */
    .lg-row-time{flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;min-width:58px;
        padding-inline-end:10px;border-inline-end:1px dashed #e7e2f4;cursor:pointer;background:transparent;
        border-top:0;border-bottom:0;border-inline-start:0;font-family:inherit;text-align:center;}
    .lg-row-time b{font-size:13.5px;font-weight:800;color:#241d4b;font-variant-numeric:tabular-nums;line-height:1.2;white-space:nowrap;}
    .lg-row-time:hover b{color:#6d28d9;text-decoration:underline dotted;text-underline-offset:3px;}
    .lg-row-time.is-flat b{color:#9aa1b5;}
    .lg-row-time small{display:inline-flex;align-items:center;gap:3px;font-size:9.5px;font-weight:800;white-space:nowrap;line-height:1.4;}
    .lg-row-time small i{font-size:9px;}
    .lg-time-edit{width:78px;height:30px;border:1.5px solid #8b5cf6;border-radius:9px;font-family:inherit;font-size:12.5px;
        font-weight:800;color:#1e293b;text-align:center;outline:none;box-shadow:0 0 0 3px rgba(139,92,246,.14);font-variant-numeric:tabular-nums;}
    /* Timeline tools — type filter + merge */
    .lg-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;}
    .lg-filter{display:flex;background:#f1edfd;border:1px solid #e4dbfa;border-radius:12px;padding:3px;gap:3px;flex-wrap:wrap;}
    .lg-filter button{border:0;background:transparent;border-radius:8px;height:30px;padding:0 12px;font-family:inherit;font-size:11.5px;
        font-weight:800;color:#7a7396;cursor:pointer;white-space:nowrap;transition:all .14s ease;display:inline-flex;align-items:center;}
    .lg-filter button:hover{color:#5b21b6;}
    .lg-filter button.active{background:#fff;color:#4c1d95;box-shadow:0 2px 8px rgba(76,29,149,.16),inset 0 0 0 1px #e2d6fb;}
    .lg-fcount{font-size:10px;font-weight:800;background:#e7e0fb;color:#5b21b6;border-radius:12px;padding:0 7px;margin-inline-start:5px;font-variant-numeric:tabular-nums;}
    .lg-filter button.active .lg-fcount{background:#5b21b6;color:#fff;}
    .lg-merge{margin-inline-start:auto;display:inline-flex;align-items:center;gap:7px;height:32px;padding:0 13px;cursor:pointer;
        border:1px dashed #d9a441;background:#fffdf4;border-radius:11px;font-family:inherit;font-size:11.5px;font-weight:800;color:#92600a;transition:all .14s ease;}
    .lg-merge:hover{background:#fdf3d9;border-style:solid;}
    .lg-merge[hidden]{display:none;}
    .lg-merge-count{background:#92600a;color:#fff;border-radius:20px;font-size:10px;font-weight:800;padding:1px 8px;font-variant-numeric:tabular-nums;}
    /* Live timer pill inside the row clock */
    .lg-live{display:inline-block;font-size:10px;font-weight:800;color:#15803d;background:#e9f9f0;border:1px solid #bbf7d0;
        border-radius:20px;padding:1px 9px;font-variant-numeric:tabular-nums;animation:lgPulse 1.2s ease infinite;}
    @keyframes lgPulse{0%,100%{box-shadow:0 0 0 0 rgba(22,163,74,.35);}50%{box-shadow:0 0 0 4px rgba(22,163,74,0);}}
    .lg-icon.is-running{background:#e9f9f0;color:#16a34a;animation:lgPulse 1.2s ease infinite;}
    .lg-icon.is-quit-on{background:#fef3c7;color:#b45309;}
    .lg-icon.is-quit-on:hover{background:#fde68a;color:#92400e;}
    .lg-loading>i{animation:lgSpin 1s linear infinite;}
    @keyframes lgSpin{to{transform:rotate(360deg);}}
    /* Nightly nudge inside the shutdown modal */
    .lg-shutdown-nudge{display:flex;align-items:center;gap:10px;border:1px solid #e6dcfb;border-radius:13px;padding:10px 13px;margin-bottom:14px;
        background:linear-gradient(135deg,#faf7ff,#f5f0ff);font-size:12.5px;font-weight:600;color:#4b4566;line-height:1.7;}
    .lg-shutdown-nudge>i{width:32px;height:32px;flex-shrink:0;display:grid;place-items:center;border-radius:10px;font-size:15px;color:#fff;
        background:linear-gradient(135deg,#7c3aed,#6366f1);box-shadow:0 6px 14px -8px rgba(109,40,217,.9);}
    .lg-shutdown-nudge b{color:#2d2450;font-weight:800;}
    .lg-shutdown-nudge button{margin-inline-start:auto;flex-shrink:0;border:0;border-radius:10px;padding:8px 15px;cursor:pointer;white-space:nowrap;
        background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;font-family:inherit;font-size:11.5px;font-weight:800;
        box-shadow:0 8px 16px -10px rgba(109,40,217,.95);}
    .lg-shutdown-nudge button:hover{filter:brightness(1.07);}
    /* Insights card — hidden when no suggestion (JS renders empty) */
    .lg-insights{background:linear-gradient(135deg,#fffdf4 0%,#fffaf0 55%,#fdf6ff 100%);border:1px solid #f0e3b8;}
    .lg-insights .lg-card-head>i{background:#fef3c7;color:#b45309;border-color:#f9e2a8;}
    .lg-insights:has(#lgAsk:empty){display:none;}
    #lgSection .lg-ask{margin:0;}
    #lgSection .lg-meta{margin-top:11px !important;}
    @media (max-width:576px){
        .lg-card{padding:12px 12px;}
        #lgSection .lg-row{margin-inline-start:34px !important;}
        #lgSection .lg-groups::before{inset-inline-start:18px;}
        #lgSection .lg-row::before{inset-inline-start:-22px;}
        .lg-type-seg button{font-size:11.5px;height:34px;}
        .lg-row-time{min-width:48px;padding-inline-end:8px;}
        .lg-row-time b{font-size:12.5px;}
    }

    /* ══ Mobile responsive layer (≤768px): single column, thumb-friendly, no overflow ══ */
    @media (max-width:767.98px){
        .main-content{padding:12px 12px 96px;}
        /* Header slimmer */
        .pl-header{padding:12px 14px;margin-bottom:10px;border-radius:12px;}
        .pl-header-title{font-size:17px;}
        .pl-header-sub{font-size:12px;}
        /* Toolbar stacks: full-width Day/Week segment, scrollable actions, spread nav */
        .pl-toolbar{flex-direction:column;align-items:stretch;padding:10px;gap:8px;position:static;}
        .pl-toggle{width:100%;}
        .pl-toggle-btn{flex:1;justify-content:center;padding:8px 10px;}
        .pl-toolbar-right{width:100%;flex-wrap:wrap;}
        .pl-toolbar-actions{max-width:100%;overflow-x:auto;scrollbar-width:none;padding-bottom:2px;}
        .pl-toolbar-actions::-webkit-scrollbar{display:none;}
        .pl-toolbar-actions .btn{flex-shrink:0;white-space:nowrap;}
        .pl-nav{width:100%;justify-content:space-between;}
        .pl-today-btn{flex:1;text-align:center;}
        /* Stats become a 2×2 grid; overdue spans full width */
        .pl-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;}
        .pl-stat{padding:8px 10px;font-size:11.5px;}
        .pl-stat strong{font-size:14px;}
        .pl-stat.overdue{grid-column:1 / -1;justify-content:center;}
        .pl-daybar{margin:0 0 12px;}
        /* Hero Next-Up stacks vertically with big tap targets */
        .pl-hero{margin-bottom:12px;}
        .pl-hero .pl-next-card{flex-direction:column;align-items:stretch;gap:12px;padding:14px;}
        .pl-next-body{min-width:0;width:100%;}
        .pl-next-title-row{gap:10px;}
        .pl-hero .pl-next-title{font-size:15px;}
        .pl-next-prompt-pill{display:flex;flex-wrap:wrap;width:100%;font-size:12px;}
        .pl-next-top-badge{flex-wrap:wrap;}
        .pl-next-actions{width:100%;margin:0;display:flex;flex-wrap:wrap;gap:8px;}
        .pl-next-actions .pl-action-btn{flex:1 1 40%;justify-content:center;min-height:44px;padding:10px 12px;font-size:13px;}
        .pl-next-actions .pl-action-btn-primary{flex:1 1 100%;}
        /* Sections: wrap heads, full-width overdue action */
        .pl-section{border-radius:12px;margin-bottom:12px;}
        .pl-section-head{flex-wrap:wrap;gap:8px;padding:10px 12px;row-gap:6px;}
        .pl-section-title{font-size:13px;}
        .pl-overdue-all{flex:1 1 100%;justify-content:center;padding:8px;}
        .pl-section-body{padding:6px;}
        /* Task rows: actions drop to their own full-width row, 34px+ targets */
        .pl-task{flex-wrap:wrap;padding:10px;}
        .pl-task-title{font-size:13px;}
        .pl-task-actions{flex:1 1 100%;opacity:1;justify-content:flex-start;flex-wrap:wrap;gap:6px;margin-top:4px;}
        .pl-task-act{width:34px;height:34px;font-size:14px;border-radius:9px;}
        .pl-task-open{height:34px;flex:1;justify-content:center;}
        .pl-task-meta{row-gap:6px;}
        /* Routine steps: always-visible skip, taller chips for thumbs */
        .pl-steps{gap:5px;}
        .pl-step{font-size:11px;min-height:32px;}
        .pl-step-wrap .pl-step-skip{opacity:1;width:24px;height:24px;font-size:9px;}
        .pl-log{flex-direction:column;align-items:stretch;}
        .pl-log input{width:100%;}
        .pl-logset input{height:34px;}
        .pl-avoid-panel input[name=note]{flex:1 1 100%;min-width:0;}
        /* Day Log: 16px input kills iOS zoom, tighter timeline indent */
        .lg-composer{min-height:52px !important;}
        .lg-composer .lg-input{font-size:16px !important;}
        .lg-type-seg button{font-size:11px;height:40px;}
        .lg-card{padding:12px;}
        .lg-tools{gap:6px;}
        .lg-filter{max-width:100%;flex-wrap:nowrap;overflow-x:auto;}
        .lg-filter button{flex-shrink:0;}
        .lg-merge{width:100%;justify-content:center;margin:0;}
        #lgSection .lg-row{margin-inline-start:30px !important;}
        #lgSection .lg-groups::before{inset-inline-start:15px;}
        #lgSection .lg-row::before{inset-inline-start:-19px;}
        .lg-row-time{min-width:44px;}
        /* Floating bar + FAB respect safe-area and stay compact */
        .pl-floating-focus-bar{bottom:calc(12px + env(safe-area-inset-bottom));width:calc(100vw - 24px);}
        .pl-ffb-container{padding:10px 12px;gap:8px;}
        .pl-ffb-info{min-width:0;flex:1;}
        .pl-ffb-title{max-width:38vw;}
        .pl-ffb-clock{font-size:17px;}
        .pl-ffb-btn{min-height:36px;}
        .pl-fab{bottom:calc(12px + env(safe-area-inset-bottom));right:12px;width:48px;height:48px;}
        #plToast{bottom:calc(70px + env(safe-area-inset-bottom));max-width:calc(100vw - 24px);white-space:normal;text-align:center;}
        /* Modals become bottom sheets with breathing room */
        .pl-qa{padding:12px;align-items:flex-end;}
        .pl-qa-dialog{width:100%;}
        .pl-qa-modal{padding:12px;align-items:flex-end;}
        .pl-qa-modal-dialog{width:100%;}
        .pl-modal-foot{flex-wrap:wrap;}
        .pl-modal-foot-btn{flex:1;justify-content:center;min-height:42px;}
    }
    @media (max-width:575.98px){
        .pl-stat{font-size:11px;}
        .pl-head-sub{display:none;}
        .pl-section-head{padding:10px 12px;}
        .pl-next-meta{gap:5px;}
        .pl-meta-tag,.pl-priority-pill,.pl-period-pill{font-size:10.5px;}
        .pl-qa-row{flex-direction:column;}
        .pl-qa-row select,.pl-qa-row input[type="time"]{width:100%;min-height:38px;}
        .pw-day{min-height:0;}
    }

    /* ══ Planner beauty layer v3 — cohesive, calm, premium ── */
    .main-content{background:linear-gradient(180deg,#f8fafc 0%,#f4f3fb 100%);}
    /* Header: deep gradient, glass date pill */
    .pl-header{background:linear-gradient(135deg,#4f46e5 0%,#6d28d9 55%,#7c3aed 100%);box-shadow:0 12px 28px -10px rgba(99,102,241,.5),inset 0 1px 0 rgba(255,255,255,.22);}
    .pl-header-title{text-shadow:0 1px 8px rgba(0,0,0,.25);}
    .pl-header-sub{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.25);border-radius:99px;padding:3px 12px;margin-top:8px;font-size:12px;backdrop-filter:blur(4px);}
    /* Section heads: tinted icon tile + refined count */
    .pl-section{border-radius:16px;}
    .pl-section-head{padding:11px 16px;}
    .pl-section-head > i:first-child{width:28px;height:28px;flex-shrink:0;display:grid;place-items:center;border-radius:9px;background:#f1edff;color:#6d28d9;font-size:14px;box-shadow:inset 0 0 0 1px #e4dafa;}
    .pl-section-primary > .pl-section-head > i:first-child{background:#e7edff;color:#4f46e5;box-shadow:inset 0 0 0 1px #ccd8ff;}
    .pl-urgent .pl-section-head > i:first-child,.pl-section-head > i.bi-exclamation-triangle-fill{background:#fee2e2;color:#dc2626;box-shadow:inset 0 0 0 1px #fecaca;}
    .pl-section-count{box-shadow:inset 0 0 0 1px rgba(15,23,42,.05);font-variant-numeric:tabular-nums;}
    /* Task rows: softer, lift on hover, tinted done state */
    .pl-task{border-radius:12px;border-color:#edf0f4;}
    .pl-task:hover{transform:translateY(-1px);}
    .pl-task.is-done{background:#fafdf9;}
    .pl-check-box{width:22px;height:22px;}
    .pl-check:hover .pl-check-box{box-shadow:0 0 0 4px rgba(124,58,237,.12);}
    .pl-check input:checked + .pl-check-box{box-shadow:0 2px 8px rgba(22,163,74,.4);}
    .pl-task:focus-within{border-color:#c4b5fd;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
    /* Hero: glow + slow shine sweep */
    .pl-hero .pl-next-card{overflow:hidden;}
    .pl-hero .pl-next-card::after{content:'';position:absolute;top:-60%;bottom:-60%;width:44px;inset-inline-start:-70px;transform:skewX(-18deg);background:linear-gradient(90deg,transparent,rgba(255,255,255,.7),transparent);animation:plHeroShine 5.5s ease-in-out infinite;pointer-events:none;}
    @keyframes plHeroShine{0%,55%{inset-inline-start:-70px;}90%,100%{inset-inline-start:130%;}}
    .pl-next-icon-wrap{box-shadow:0 4px 12px -4px rgba(15,23,42,.25);}
    .pl-action-btn-primary{box-shadow:0 6px 16px -6px rgba(124,58,237,.6),inset 0 1px 0 rgba(255,255,255,.25);}
    .pl-action-btn:active{transform:scale(.97);}
    /* Stats: clean cards, tabular numbers */
    .pl-stat{font-variant-numeric:tabular-nums;}
    .pl-stat strong{font-variant-numeric:tabular-nums;}
    /* Toolbar: refined segment + glow add */
    .pl-toggle{box-shadow:inset 0 1px 3px rgba(15,23,42,.08);}
    .pl-add-btn{box-shadow:0 4px 12px -4px rgba(124,58,237,.55),inset 0 1px 0 rgba(255,255,255,.25);}
    /* Daybar: glowing fill */
    .pl-daybar-fill{box-shadow:0 0 12px rgba(124,58,237,.55);}
    .pl-daybar-fill.is-full{box-shadow:0 0 12px rgba(22,163,74,.55);}
    /* Overdue: alert presence without noise */
    .pl-urgent .pl-section-count{box-shadow:none;}
    /* Routine steps: pill polish */
    .pl-step{box-shadow:0 1px 2px rgba(15,23,42,.04);}
    .pl-step.done{box-shadow:none;}
    /* Empty states: friendlier */
    .pl-empty{border:1.5px dashed #e5e8ef;border-radius:12px;margin:4px;background:#fbfcfe;}
    .pl-empty i{color:#b9c0cf;}
    /* Persian: no latin letter-spacing, roomier line-height */
    html[dir="rtl"] .pl-priority,html[dir="rtl"] .pw-today-pill,html[dir="rtl"] .pw-group-head,html[dir="rtl"] .pl-period-head{letter-spacing:0;}
    html[dir="rtl"] .pl-task-title{line-height:1.7;}
    html[dir="rtl"] .pl-next-title{line-height:1.6;}
    @media (prefers-reduced-motion:reduce){
        .pl-hero .pl-next-card::after{animation:none;display:none;}
        .pl-task:hover{transform:none;}
    }

    /* ══ Pro Quick-Add modal: add a task to the day in seconds ── */
    .pl-qa-dialog-pro{width:min(540px,96vw);border-radius:18px;}
    .pl-qa-pro-head{display:flex;align-items:center;gap:12px;padding:16px 18px 14px;color:#fff;
        background:linear-gradient(135deg,#4f46e5 0%,#6d28d9 60%,#7c3aed 100%);position:relative;overflow:hidden;}
    .pl-qa-pro-head::after{content:'';position:absolute;top:-40px;inset-inline-end:-30px;width:130px;height:130px;
        background:rgba(255,255,255,.12);border-radius:50%;pointer-events:none;}
    .pl-qa-pro-icon{width:38px;height:38px;flex-shrink:0;display:grid;place-items:center;border-radius:12px;font-size:17px;
        background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);box-shadow:inset 0 1px 0 rgba(255,255,255,.3);position:relative;z-index:1;}
    .pl-qa-pro-titles{display:flex;flex-direction:column;gap:1px;min-width:0;position:relative;z-index:1;}
    .pl-qa-pro-head .pl-qa-title{font-size:15px;font-weight:800;color:#fff;}
    .pl-qa-sub{font-size:11.5px;font-weight:600;color:rgba(255,255,255,.75);}
    .pl-qa-pro-head .pl-qa-x{background:rgba(255,255,255,.15);color:#fff;position:relative;z-index:1;}
    .pl-qa-pro-head .pl-qa-x:hover{background:rgba(255,255,255,.3);color:#fff;}
    .pl-qa-dialog-pro .pl-qa-tabs{background:#faf8ff;}
    .pl-qa-dialog-pro .pl-qa-tab{display:inline-flex;align-items:center;gap:6px;}
    .pl-qa-dialog-pro .pl-qa-form{gap:9px;padding:16px 18px 18px;}
    .pl-qa-title-input{font-size:15px !important;font-weight:700;padding:12px 14px !important;border-radius:12px !important;}
    .pl-qa-title-input::placeholder{font-weight:500;}
    .pl-qa-label{font-size:11px;font-weight:800;color:#8a8f98;text-transform:uppercase;letter-spacing:.05em;margin-top:4px;}
    html[dir="rtl"] .pl-qa-label{letter-spacing:0;}
    .pl-qa-error{display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:700;color:#b91c1c;
        background:#fef2f2;border:1px solid #fbd5d5;border-radius:10px;padding:8px 12px;}
    .pl-qa-error[hidden]{display:none;}
    .pl-qa-error::before{content:'\F33B';font-family:'bootstrap-icons';font-size:14px;}
    /* Priority segmented control */
    .pl-qa-seg{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;background:#f4f5f8;border-radius:12px;padding:4px;}
    .pl-qa-seg button{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:1px solid transparent;
        background:transparent;border-radius:9px;min-height:36px;font-size:12.5px;font-weight:800;color:#6b7385;cursor:pointer;transition:all .14s;}
    .pl-qa-seg button:hover{background:#fff;}
    .pl-qa-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;}
    .pl-qa-seg-low .pl-qa-dot{background:#16a34a;}
    .pl-qa-seg-medium .pl-qa-dot{background:#d97706;}
    .pl-qa-seg-high .pl-qa-dot{background:#dc2626;}
    .pl-qa-seg button.active{background:#fff;box-shadow:0 2px 8px rgba(15,23,42,.1);color:#1a1d23;}
    .pl-qa-seg-low.active{box-shadow:inset 0 0 0 1.5px #86efac,0 2px 8px rgba(22,163,74,.18);}
    .pl-qa-seg-medium.active{box-shadow:inset 0 0 0 1.5px #fcd34d,0 2px 8px rgba(217,119,6,.18);}
    .pl-qa-seg-high.active{box-shadow:inset 0 0 0 1.5px #fca5a5,0 2px 8px rgba(220,38,38,.18);}
    /* Period + estimate chips */
    .pl-qa-dialog-pro .pl-qa-chips button{display:inline-flex;align-items:center;gap:6px;min-height:34px;padding:5px 13px;font-size:12px;}
    .pl-qa-dialog-pro .pl-qa-chips button.active{background:linear-gradient(135deg,#ede9fe,#e0e7ff);border-color:#a78bfa;color:#5b21b6;
        box-shadow:0 2px 8px rgba(124,58,237,.2);}
    /* Labeled fields */
    .pl-qa-field{flex:1;display:flex;flex-direction:column;gap:4px;min-width:0;}
    .pl-qa-field .pl-qa-label{margin-top:0;}
    .pl-qa-field select,.pl-qa-field input[type="time"]{min-height:40px;}
    /* Session-added list */
    .pl-qa-added{border:1px solid #bbf7d0;background:linear-gradient(135deg,#f4fdf7,#ecfdf5);border-radius:12px;padding:9px 12px;}
    .pl-qa-added[hidden]{display:none;}
    .pl-qa-added-head{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:800;color:#15803d;margin-bottom:5px;}
    .pl-qa-added ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:3px;max-height:118px;overflow-y:auto;}
    .pl-qa-added li{display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:700;color:#334155;
        background:#fff;border:1px solid #d9f2e3;border-radius:9px;padding:5px 10px;animation:plQaddIn .18s ease;}
    .pl-qa-added li i{color:#16a34a;font-size:13px;}
    /* Footer: ghost + primary */
    .pl-qa-actions-split{justify-content:space-between;margin-top:4px;}
    .pl-qa-secondary{display:inline-flex;align-items:center;gap:6px;background:#fff;color:#6d28d9;border:1.5px solid #ddd6fe;
        border-radius:10px;padding:9px 16px;font-size:12.5px;font-weight:800;cursor:pointer;transition:all .15s;}
    .pl-qa-secondary:hover:not(:disabled){background:#f5f3ff;border-color:#c4b5fd;transform:translateY(-1px);}
    .pl-qa-secondary:disabled{opacity:.5;cursor:wait;}
    .pl-qa-dialog-pro .pl-qa-actions button[type="submit"]{display:inline-flex;align-items:center;gap:7px;border-radius:10px;
        padding:10px 22px;font-size:13px;box-shadow:0 4px 12px -4px rgba(124,58,237,.5);}
    .pl-qa-dialog-pro .pl-qa-actions button[type="submit"]:disabled{opacity:.45;cursor:not-allowed;box-shadow:none;}
    .pl-qa-submit.is-loading{position:relative;color:transparent !important;pointer-events:none;}
    .pl-qa-submit.is-loading::after{content:'';position:absolute;top:50%;left:50%;width:16px;height:16px;margin:-8px 0 0 -8px;
        border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:plQaSpin .7s linear infinite;}
    @keyframes plQaSpin{to{transform:rotate(360deg);}}
    @media (max-width:575.98px){
        .pl-qa-dialog-pro{border-radius:18px 18px 0 0;}
        .pl-qa-actions-split{flex-direction:column-reverse;align-items:stretch;}
        .pl-qa-secondary,.pl-qa-dialog-pro .pl-qa-actions button[type="submit"]{justify-content:center;min-height:46px;}
        .pl-qa-seg button{min-height:42px;}
    }

    /* ══ Backlog picker modal: pull existing tasks into the day ── */
    .pl-head-add-existing{
        display:inline-flex;align-items:center;justify-content:center;gap:6px;
        width:30px;height:30px;border-radius:9px;border:1px solid #e2e8f0;background:#fff;color:#6d28d9;
        font-size:14px;cursor:pointer;transition:all .15s;flex-shrink:0;
    }
    .pl-head-add-existing:hover{border-color:#c4b5fd;background:#faf5ff;transform:translateY(-1px);
        box-shadow:0 4px 10px rgba(124,58,237,.18);}
    .pl-head-add-existing:active{transform:scale(.95);}
    .pl-bl-search{border-bottom:1px solid #f1eefb;}
    .pl-qadd-filters{display:flex;gap:6px;flex-wrap:wrap;padding:10px 14px 2px;}
    .pl-qadd-filters button{display:inline-flex;align-items:center;gap:6px;border:1px solid #e5e7eb;background:#fafbfc;
        color:#6b6f78;border-radius:20px;padding:5px 13px;font-size:11.5px;font-weight:800;cursor:pointer;transition:all .13s;}
    .pl-qadd-filters button:hover{border-color:#c4b5fd;color:#7c3aed;}
    .pl-qadd-filters button.active{background:linear-gradient(135deg,#ede9fe,#e0e7ff);border-color:#a78bfa;color:#5b21b6;
        box-shadow:0 2px 8px rgba(124,58,237,.2);}
    .pl-qadd-filters button:disabled{opacity:.5;cursor:default;}
    .pl-bl-list{max-height:320px;min-height:120px;}
    .pl-bl-list .pl-qadd-item{cursor:pointer;align-items:center;}
    .pl-bl-list .pl-qadd-item.selected{background:#faf5ff;border-color:#c4b5fd;box-shadow:0 0 0 3px rgba(124,58,237,.08);}
    .pl-qadd-check{appearance:none;-webkit-appearance:none;width:20px;height:20px;flex-shrink:0;margin:0;cursor:pointer;
        border:2px solid #cbd5e1;border-radius:7px;background:#fff;display:grid;place-items:center;transition:all .13s;}
    .pl-qadd-check:hover{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
    .pl-qadd-check:checked{background:linear-gradient(135deg,#7c3aed,#6d28d9);border-color:#6d28d9;}
    .pl-qadd-check:checked::after{content:'✓';color:#fff;font-size:12px;font-weight:900;line-height:1;}
    .pl-qadd-check:focus-visible{outline:2px solid #7c3aed;outline-offset:2px;}
    .pl-bl-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;
        padding:12px 16px;border-top:1px solid #f1f5f9;background:#f8fafc;border-radius:0 0 18px 18px;}
    .pl-qa-submit-bl{display:inline-flex;align-items:center;gap:7px;border:1px solid #6d28d9;border-radius:10px;
        background:linear-gradient(135deg,#7c3aed 0%,#6d28d9 100%);color:#fff;padding:10px 20px;font-size:13px;font-weight:800;
        cursor:pointer;box-shadow:0 4px 12px -4px rgba(124,58,237,.5);transition:all .15s;position:relative;}
    .pl-qa-submit-bl:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 6px 16px -4px rgba(124,58,237,.6);}
    .pl-qa-submit-bl:disabled{opacity:.45;cursor:not-allowed;box-shadow:none;}
    .pl-qa-submit-bl.is-loading{color:transparent !important;pointer-events:none;}
    .pl-qa-submit-bl.is-loading::after{content:'';position:absolute;top:50%;left:50%;width:16px;height:16px;margin:-8px 0 0 -8px;
        border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:plQaSpin .7s linear infinite;}
    @media (max-width:575.98px){
        .pl-bl-foot{flex-direction:column-reverse;align-items:stretch;}
        .pl-qa-submit-bl,.pl-bl-foot .pl-qa-secondary{justify-content:center;min-height:46px;}
        .pl-bl-list{max-height:46vh;}
    }
</style>
@endpush

@section('content')
<div class="main-content">

    @php
        $isFa = app()->getLocale() === 'fa';
        $relativeLabel = '';
        $navDateLabel = __('Today');
        if ($view === 'day') {
            if ($date->isToday()) {
                $relativeLabel = ' · ' . __('Today');
                $navDateLabel = __('Today');
            } elseif ($date->isTomorrow()) {
                $relativeLabel = ' · ' . __('Tomorrow');
                $navDateLabel = __('Tomorrow');
            } elseif ($date->isYesterday()) {
                $relativeLabel = ' · ' . __('Yesterday');
                $navDateLabel = __('Yesterday');
            } else {
                $navDateLabel = app_date($date, 'j F');
            }
        } elseif ($view === 'month') {
            $monthStartLB = $monthStart ?? $date->copy()->startOfMonth();
            $navDateLabel = $monthStartLB->isCurrentMonth() ? __('This Month') : __('Month');
            $rangeLabel = $isFa
                ? app_num(\Morilog\Jalali\Jalalian::fromCarbon($monthStartLB)->format('F Y'))
                : $monthStartLB->format('F Y');
        } else {
            $navDateLabel = $date->isCurrentWeek() ? __('This Week') : __('Week');
        }
        $rangeLabel = $view === 'week'
            ? ($isFa ? app_date($start, 'd F') . ' – ' . app_date($end, 'd F Y') : $start->format('M j') . ' – ' . $end->format('M j, Y'))
            : ($rangeLabel ?? app_human_date($date));
        $prevDate = $view === 'week' ? $date->copy()->subWeek()
            : ($view === 'month' ? $date->copy()->subMonthNoOverflow()->startOfMonth() : $date->copy()->subDay());
        $nextDate = $view === 'week' ? $date->copy()->addWeek()
            : ($view === 'month' ? $date->copy()->addMonthNoOverflow()->startOfMonth() : $date->copy()->addDay());
    @endphp

    {{-- Header --}}
    <div class="pl-header">
        <h1 class="pl-header-title">{{ $view === 'week' ? __('My Week') : ($view === 'month' ? __('My Month') : __('My Day')) }}</h1>
        <p class="pl-header-sub">{{ $rangeLabel }}{{ $relativeLabel }}</p>
    </div>

    {{-- Toolbar --}}
    <div class="pl-toolbar">
        <div class="pl-toggle">
            <a href="{{ route('planner.index', ['view' => 'day', 'date' => $date->toDateString()]) }}"
               class="pl-toggle-btn {{ $view === 'day' ? 'active' : '' }}">
                <i class="bi bi-sun"></i> {{ __('Day') }}
            </a>
            <a href="{{ route('planner.index', ['view' => 'week', 'date' => $date->toDateString()]) }}"
               class="pl-toggle-btn {{ $view === 'week' ? 'active' : '' }}">
                <i class="bi bi-calendar-week"></i> {{ __('Week') }}
            </a>
            <a href="{{ route('planner.index', ['view' => 'month', 'date' => $date->toDateString()]) }}"
               class="pl-toggle-btn {{ $view === 'month' ? 'active' : '' }}">
                <i class="bi bi-calendar-month"></i> {{ __('Month') }}
            </a>
        </div>
        <div class="pl-toolbar-right">
            @if($view === 'day')
                <div class="d-flex align-items-center gap-2 pl-toolbar-actions">
                    <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-semibold d-inline-flex align-items-center gap-1" onclick="openMorningKickoff()" title="{{ __('Morning kickoff ritual') }}">
                        <i class="bi bi-sunrise-fill text-warning"></i> {{ __('Kickoff') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-primary fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" onclick="openFocusWorkstation()" title="{{ __('Enter full-screen focus mode') }}">
                        <i class="bi bi-bullseye"></i> {{ __('Focus') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="openEveningShutdown()" title="{{ __('Evening shutdown ritual') }}">
                        <i class="bi bi-moon-stars-fill text-primary"></i> {{ __('Shutdown') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 fw-semibold" onclick="openLinaScheduleModal()" title="{{ __('Optimize schedule with Lina AI') }}">
                        <i class="bi bi-stars"></i> {{ __('Lina AI') }}
                    </button>
                </div>
            @endif
            {{-- Navigation: arrows adapt to RTL/LTR naturally --}}
            <div class="pl-nav">
                <a href="{{ route('planner.index', ['view' => $view, 'date' => $prevDate->toDateString()]) }}"
                   class="pl-nav-btn" title="{{ __('Previous') }}">
                    <i class="bi {{ $isFa ? 'bi-chevron-right' : 'bi-chevron-left' }}"></i>
                </a>
                <a href="{{ route('planner.index', ['view' => $view]) }}"
                   class="pl-today-btn {{ $date->isToday() ? 'is-today' : '' }}"
                   title="{{ __('Go to Today') }}">
                    {{ $navDateLabel }}
                </a>
                <a href="{{ route('planner.index', ['view' => $view, 'date' => $nextDate->toDateString()]) }}"
                   class="pl-nav-btn" title="{{ __('Next') }}">
                    <i class="bi {{ $isFa ? 'bi-chevron-left' : 'bi-chevron-right' }}"></i>
                </a>
            </div>
            @if($view === 'day')
                <button type="button" class="pl-add-btn" onclick="openQuickAdd('task')">
                    <i class="bi bi-plus-lg"></i> {{ __('Add') }}
                </button>
            @endif
        </div>
    </div>

    @if($view === 'day')
        {{-- Stats --}}
        <div class="pl-stats">
            <div class="pl-stat"><i class="bi bi-arrow-repeat"></i> {{ __('Routines') }} <strong><span id="plRoutineDone">{{ app_num($routineDone) }}</span><span style="color:#adb0b8;font-weight:600;">/{{ app_num($routineTotal) }}</span></strong></div>
            <div class="pl-stat"><i class="bi bi-list-check"></i> {{ __('Pending') }} <strong id="plPendingCount">{{ app_num($pending->count()) }}</strong></div>
            <div class="pl-stat"><i class="bi bi-check-circle"></i> {{ __('Completed') }} <strong id="plDoneCount">{{ app_num($done->count()) }}</strong></div>
            @if($overdue->count())
                <div class="pl-stat overdue"><i class="bi bi-exclamation-triangle"></i> {{ __('Overdue') }} <strong id="plOverdueStat">{{ app_num($overdue->count()) }}</strong></div>
            @endif
        </div>


        {{-- Day progress (tasks + routines combined) --}}
        <div class="pl-daybar">
            <div class="pl-daybar-track"><div class="pl-daybar-fill" id="plDayProgressFill"></div></div>
            <span class="pl-daybar-label" id="plDayProgressLabel"></span>
        </div>

        {{-- HERO: Next Up owns the full width — single most important box on the page --}}
        @if(! empty($nextUp ?? null))
        <div class="pl-hero">
            @include('planner._next-up', ['nextUp' => $nextUp ?? null, 'date' => $date])
        </div>
        @endif

        {{-- URGENT: Overdue sits directly under the hero, full-width alert --}}
        @if($overdue->count())
            <div class="pl-section pl-urgent">
                <div class="pl-section-head" style="background: linear-gradient(135deg, #fffafb 0%, #fff1f2 100%); border-bottom: 1px solid #fee2e2;">
                    <span class="pl-urgent-dot" aria-hidden="true"></span>
                    <i class="bi bi-exclamation-triangle-fill" style="color:#dc2626;"></i>
                    <span class="pl-section-title" style="color:#991b1b;">{{ __('Overdue — clear first') }}</span>
                    <span class="pl-head-sub">{{ __('From previous days') }}</span>
                    <span class="pl-section-count" id="plOverdueCount" style="background:#fee2e2;color:#b91c1c;">{{ app_num($overdue->count()) }}</span>
                    <button type="button" class="pl-overdue-all" id="plPostponeAll" title="{{ __('Move every overdue task to tomorrow') }}">
                        <i class="bi bi-arrow-90deg-down"></i> {{ __('Postpone all to tomorrow') }}
                    </button>
                </div>
                <div class="pl-section-body" id="plOverdueBody">
                    @foreach($overdue as $task)
                        @include('planner._task-row', ['task' => $task, 'count' => false, 'postpone' => 'today'])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- MAIN WORK: Today's Tasks (primary) + Routines (habits) side by side --}}
        <div class="pl-duo pl-main-grid">
        {{-- Today's tasks first: the day's real workload --}}
        <div class="pl-section pl-section-primary">
            <div class="pl-section-head">
                <i class="bi bi-check2-square" style="color:#4f46e5;"></i>
                <span class="pl-section-title">{{ $isToday ? __("Today's Tasks") : __('Tasks') }}</span>
                <span class="pl-head-sub">{{ __('Do the work') }}</span>
                <button type="button" class="pl-head-add-existing" onclick="openBacklogModal()"
                        title="{{ __('Add existing task to this day') }}" aria-label="{{ __('Add existing task to this day') }}">
                    <i class="bi bi-inboxes"></i>
                </button>
                <span class="pl-section-count" id="plTodaySectionCount">{{ app_num($pending->count()) }}</span>
            </div>
            <div class="pl-section-body" id="plPendingBody">
                @php $groups = $pending->groupBy(fn ($t) => $t->time_period ?: 'anytime'); @endphp
                @forelse($pending as $task)@empty
                    <div class="pl-empty"><i class="bi bi-cup-hot"></i>{{ __('Nothing scheduled for this day. Enjoy!') }}</div>
                    <div class="pl-qadd">
                        <button type="button" class="pl-qadd-btn" onclick="openBacklogModal()">
                            <span class="pl-qadd-glow" aria-hidden="true"></span>
                            <span class="pl-qadd-ico"><i class="bi bi-inboxes" aria-hidden="true"></i></span>
                            <span class="pl-qadd-txt">
                                <b>{{ __('Pull an existing task into today') }}</b>
                                <small>{{ __('Pick from your unscheduled tasks — nothing gets created') }}</small>
                            </span>
                            <span class="pl-qadd-arrow"><i class="bi bi-plus-lg" aria-hidden="true"></i></span>
                        </button>
                    </div>
                @endforelse
                @foreach(config('routines.periods', []) as $key => $period)
                    @if($groups->has($key))
                        @include('planner._period-group', ['periodKey' => $key, 'period' => $period, 'rows' => $groups->get($key)])
                    @endif
                @endforeach
                @if($groups->has('anytime'))
                    @include('planner._period-group', [
                        'periodKey' => 'anytime',
                        'period' => ['label' => 'Anytime', 'icon' => 'bi-inbox', 'color' => '#64748b'],
                        'rows' => $groups->get('anytime'),
                    ])
                @endif
            </div>
        </div>

        {{-- Routines second: repeating habits, visually calmer than tasks --}}
        <div class="pl-section pl-section-habit">
            <div class="pl-section-head">
                <i class="bi bi-arrow-repeat" style="color:#7c3aed;"></i>
                <span class="pl-section-title">{{ $isToday ? __("Today's Routines") : __('Routines') }}</span>
                <span class="pl-head-sub">{{ __('Habits & repeats') }}</span>
                <span class="pl-section-count" id="plRoutineSectionCount">{{ app_num($routineTotal) }}</span>
            </div>
            <div class="pl-section-body" id="plRoutinesBody">
                @forelse($routines as $routine)
                    @include('planner._routine-row', ['routine' => $routine, 'routineDate' => $date, 'count' => true])
                @empty
                    <div class="pl-empty"><i class="bi bi-arrow-repeat"></i>{{ __('No routines scheduled for this day.') }}</div>
                @endforelse
            </div>
        </div>
        </div>{{-- /Main work grid --}}

        {{-- LOWER: Day Log (wide) + side rail (workout, secondary) --}}
        <div class="pl-duo pl-lower-grid">
        {{-- ── Day Log (دفتر روز) — persisted via the day-logs API. --}}
        <div class="pl-section lg-section" id="lgSection" data-date="{{ $date->toDateString() }}">
            <div class="pl-section-head">
                <span class="lg-head-icon"><i class="bi bi-journal-text" aria-hidden="true"></i></span>
                <span class="lg-head-text">
                    <span class="d-inline-flex align-items-center gap-2">
                        <span class="pl-section-title">{{ __('Day Log') }}</span>
                    </span>
                    <span class="lg-head-sub">{{ __('One line per moment — time, amount and note are detected automatically') }}</span>
                </span>
                <span class="pl-section-count" id="lgCount"></span>
            </div>
            <div class="lg-body">
                {{-- LAYER 1 — capture: one input + details + live preview --}}
                <section class="lg-card lg-capture" aria-label="{{ __('Log a moment') }}">
                <div class="lg-intro">
                    <i class="bi bi-stars" aria-hidden="true"></i>
                    <span>{!! __('Try: <code>:sample</code> — then press Enter', ['sample' => __('played piano 45m evening')]) !!}</span>
                </div>

                <div class="lg-type-seg" role="tablist" aria-label="{{ __('Entry type') }}">
                    <button type="button" data-lg-kind="activity" role="tab" aria-selected="true">
                        <i class="bi bi-lightning-charge" aria-hidden="true"></i>{{ __('Activity') }}
                    </button>
                    <button type="button" data-lg-kind="journal" role="tab" aria-selected="false">
                        <i class="bi bi-pen" aria-hidden="true"></i>{{ __('Sentence') }}
                    </button>
                    <button type="button" data-lg-kind="mood" role="tab" aria-selected="false">
                        <i class="bi bi-emoji-smile" aria-hidden="true"></i>{{ __('Mood') }}
                    </button>
                </div>

                {{-- One input does the work: "played piano 45m evening" --}}
                <div class="lg-hero">
                    <div class="lg-composer">
                        <i class="bi bi-feather lg-composer-ico" aria-hidden="true"></i>
                        <input type="text" class="lg-input" id="lgInput" name="day_log_entry" autocomplete="off"
                               spellcheck="false" aria-label="{{ __('Log what you did') }}"
                               title="{{ __('e.g. played piano 45m…') }}"
                               placeholder="{{ __('e.g. played piano 45m…') }}">
                        <kbd class="lg-kbd" title="Enter">⏎</kbd>
                        <button type="button" class="lg-send" id="lgSave" disabled>
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>{{ __('Log') }}
                        </button>
                    </div>
                    <div class="lg-suggest" id="lgSuggest" role="listbox" aria-label="{{ __('Suggestions') }}"></div>
                </div>

                {{-- Optional details, closed by default; each pill shows its own state --}}
                <div class="lg-pills" role="group" aria-label="{{ __('Optional details') }}">
                    <span class="lg-pills-bar-label">{{ __('Details') }}</span>
                    <button type="button" class="lg-pill" id="lgPillTime" aria-expanded="false" aria-controls="lgPanelTime">
                        <i class="bi bi-stopwatch" aria-hidden="true"></i><span id="lgTimeLabel">{{ __('Set time') }}</span><i class="bi bi-chevron-down lg-chev" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="lg-pill" id="lgPillNote" aria-expanded="false" aria-controls="lgPanelNote">
                        <i class="bi bi-pencil" aria-hidden="true"></i>{{ __('Note') }}<i class="bi bi-chevron-down lg-chev" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="lg-pill" id="lgPillMood" aria-expanded="false" aria-controls="lgPanelMood">
                        <i class="bi bi-emoji-smile" aria-hidden="true"></i>{{ __('Mood') }}<i class="bi bi-chevron-down lg-chev" aria-hidden="true"></i>
                    </button>
                </div>

                {{-- No mode switch: the panel shows exactly what the day needs —
                     time of day, then duration, and a clock range only once one
                     has been typed in the composer. --}}
                <div class="lg-panel" id="lgPanelTime" hidden>
                    <div class="lg-panel-head">
                        <i class="bi bi-stopwatch" aria-hidden="true"></i>
                        <b>{{ __('When & how long') }}</b>
                        <small>{{ __('Pick a part of day, a duration, or both') }}</small>
                    </div>
                    <div class="lg-fields">
                        <span class="lg-label">{{ __('Time of day') }}</span>
                        <div class="lg-chips" id="lgPeriodChips"></div>
                    </div>
                    <div class="lg-fields">
                        <span class="lg-label">{{ __('How long') }}</span>
                        <input type="number" class="lg-num" id="lgAmount" name="day_log_amount" min="1" step="1" value="45"
                               inputmode="numeric" aria-label="{{ __('Amount') }}">
                        <select class="lg-sel" id="lgUnit" name="day_log_unit" aria-label="{{ __('Unit') }}">
                            <option data-lg-unit="minute">{{ __('minute') }}</option>
                            <option data-lg-unit="hour">{{ __('hour') }}</option>
                            <option data-lg-unit="page">{{ __('page') }}</option>
                            <option data-lg-unit="verse">{{ __('verse') }}</option>
                            <option data-lg-unit="count">{{ __('time(s)') }}</option>
                            <option data-lg-unit="km">{{ __('km') }}</option>
                        </select>
                        <span class="lg-quick">
                            @foreach([15, 30, 45, 60, 90] as $lgQuickMinutes)
                                @php
                                    $lgIsHours = $lgQuickMinutes >= 60;
                                    $lgQuickLabel = $lgIsHours
                                        ? rtrim(rtrim(number_format($lgQuickMinutes / 60, 1, '.', ''), '0'), '.')
                                        : $lgQuickMinutes;
                                @endphp
                                <button type="button" data-lg-val="{{ $lgQuickMinutes }}">{{ app_num($lgQuickLabel) }}{{ $lgIsHours ? __('h') : __('m') }}</button>
                            @endforeach
                        </span>
                        <button type="button" class="lg-chip lg-clear" id="lgClearTime">
                            <i class="bi bi-slash-circle" aria-hidden="true"></i>{{ __('Untimed') }}
                        </button>
                    </div>
                    <div class="lg-fields" id="lgFieldsRange" hidden>
                        <span class="lg-label">{{ __('Clock to clock') }}</span>
                        <input type="time" class="lg-time" id="lgStart" name="day_log_start" value="19:30" aria-label="{{ __('From') }}">
                        <span class="lg-sep">{{ __('to') }}</span>
                        <input type="time" class="lg-time" id="lgEnd" name="day_log_end" value="20:15" aria-label="{{ __('To') }}">
                    </div>
                </div>

                <div class="lg-panel" id="lgPanelNote" hidden>
                    <div class="lg-panel-head">
                        <i class="bi bi-pencil" aria-hidden="true"></i>
                        <b>{{ __('Add a note') }}</b>
                        <small>{{ __('One short sentence, saved with this entry') }}</small>
                    </div>
                    <input type="text" class="lg-input" id="lgNote" name="day_log_note" autocomplete="off" maxlength="2000"
                           aria-label="{{ __('Short note') }}" placeholder="{{ __('Short note (optional)') }}…">
                </div>

                <div class="lg-panel" id="lgPanelMood" hidden>
                    <div class="lg-panel-head">
                        <i class="bi bi-emoji-smile" aria-hidden="true"></i>
                        <b>{{ __('Mood note') }}</b>
                        <small>{{ __('A mood sentence — no time, kept out of the day totals.') }}</small>
                    </div>
                    <span class="lg-label">{{ __('Mood') }}</span>
                    <select class="lg-sel" id="lgMood" name="day_log_mood" aria-label="{{ __('Mood') }}">
                        <option value="">{{ __('Not set') }}</option>
                        @for($lgMood = 1; $lgMood <= 10; $lgMood++)
                            <option value="{{ $lgMood }}">{{ app_num($lgMood) }}/{{ app_num(10) }}</option>
                        @endfor
                    </select>
                </div>

                <p class="lg-meta" id="lgMeta" aria-live="polite"></p>
                </section>

                {{-- LAYER 2 — timeline: today sorted by time, no grouping --}}
                <section class="lg-card lg-timeline" aria-label="{{ __("Today's timeline") }}">
                    <div class="lg-card-head">
                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                        <span><b>{{ __("Today's timeline") }}</b><small>{{ __('Sorted by time') }}</small></span>
                    </div>
                    <div class="lg-tools">
                        <div class="lg-filter" role="group" aria-label="{{ __('Filter') }}">
                            <button type="button" class="active" data-lg-filter="all">{{ __('All') }}</button>
                            <button type="button" data-lg-filter="activity">{{ __('Activity') }}</button>
                            <button type="button" data-lg-filter="journal">{{ __('Sentence') }}</button>
                            <button type="button" data-lg-filter="mood">{{ __('Mood') }}</button>
                        </div>
                        <button type="button" class="lg-merge" id="lgMerge" hidden>
                            <i class="bi bi-union" aria-hidden="true"></i>{{ __('Merge duplicates') }}<span class="lg-merge-count" id="lgMergeCount"></span>
                        </button>
                    </div>
                    <div class="lg-groups" id="lgRows" role="list"></div>
                </section>

                {{-- LAYER 3 — insights: habit / quit suggestions live here, never inline --}}
                <section class="lg-card lg-insights" id="lgInsights" aria-label="{{ __('Smart suggestions') }}">
                    <div class="lg-card-head">
                        <i class="bi bi-stars" aria-hidden="true"></i>
                        <span><b>{{ __('Smart suggestions') }}</b><small>{{ __('Repeated logs can become habits — reviewed here') }}</small></span>
                    </div>
                    <div id="lgAsk"></div>
                </section>
                <p class="lg-foot"><i class="bi bi-check" aria-hidden="true"></i>{{ __('Saved to your day log.') }}</p>
            </div>
        </div>

        {{-- Side rail: workout is secondary context, compact next to the day log --}}
        <div class="pl-side-stack">
            @if(isset($todayWorkoutDay))
                <div class="pl-workout-mini-wrap">
                    @include('planner._workout-card')
                </div>
            @endif
        </div>
        </div>{{-- /Lower grid --}}

        {{-- Done: archival reference, collapsed at the very bottom to keep the work area tight --}}
        @if($done->count())
            <details class="pl-section pl-done">
                <summary class="pl-section-head">
                    <i class="bi bi-check-circle-fill" style="color:#16a34a;"></i>
                    <span class="pl-section-title">{{ __('Completed') }}</span>
                    <span class="pl-section-count">{{ $done->count() }}</span>
                    <i class="bi bi-chevron-down pl-done-chev" aria-hidden="true"></i>
                </summary>
                <div class="pl-section-body">
                    @foreach($done as $task)
                        @include('planner._task-row', ['task' => $task, 'count' => true])
                    @endforeach
                </div>
            </details>
        @endif
    @elseif($view === 'month')
        {{-- Month view: compact calendar overview, cells link to the day view --}}
        @php
            $pmWeekdays = $isFa
                ? ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه']
                : ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
            $pmDayNum = fn ($d) => $isFa
                ? app_num(\Morilog\Jalali\Jalalian::fromCarbon($d['date'])->format('j'))
                : $d['date']->day;
        @endphp
        <div class="pl-stats">
            <div class="pl-stat"><i class="bi bi-list-check"></i> {{ __('Open tasks') }} <strong>{{ app_num($monthStats['tasksOpen']) }}</strong></div>
            <div class="pl-stat"><i class="bi bi-check-circle"></i> {{ __('Done tasks') }} <strong>{{ app_num($monthStats['tasksDone']) }}</strong></div>
            <div class="pl-stat"><i class="bi bi-arrow-repeat"></i> {{ __('Routines') }} <strong>{{ app_num($monthStats['routinesDone']) }}<span style="color:#adb0b8;font-weight:600;">/{{ app_num($monthStats['routinesTotal']) }}</span></strong></div>
        </div>
        <div class="pm-grid">
            <div class="pm-weekdays">
                @foreach($pmWeekdays as $wd)
                    <span>{{ $wd }}</span>
                @endforeach
            </div>
            <div class="pm-days">
                @foreach($monthDays as $d)
                    <a href="{{ route('planner.index', ['view' => 'day', 'date' => $d['key']]) }}"
                       class="pm-day {{ $d['inMonth'] ? '' : 'is-out' }} {{ $d['isToday'] ? 'is-today' : '' }}">
                        <span class="pm-num">{{ $pmDayNum($d) }}</span>
                        <span class="pm-tasks">
                            @foreach($d['taskTitles'] as $t)
                                <span class="pm-t">{{ $t }}</span>
                            @endforeach
                            @if($d['taskExtra'] > 0)
                                <span class="pm-more">+{{ app_num($d['taskExtra']) }}</span>
                            @endif
                        </span>
                        @if($d['tasksOpen'] > 0 || $d['tasksDone'] > 0 || $d['routinesTotal'] > 0)
                            <span class="pm-meta">
                                @if($d['tasksOpen'] > 0)
                                    <span class="pm-m"><i class="pm-dot t"></i>{{ app_num($d['tasksOpen']) }}</span>
                                @endif
                                @if($d['tasksDone'] > 0)
                                    <span class="pm-m"><i class="pm-dot d"></i>{{ app_num($d['tasksDone']) }}</span>
                                @endif
                                @if($d['routinesTotal'] > 0)
                                    <span class="pm-m"><i class="pm-dot r"></i>{{ app_num($d['routinesDone']) }}/{{ app_num($d['routinesTotal']) }}</span>
                                @endif
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
        <div class="pm-legend">
            <span><i class="pm-dot t"></i> {{ __('Open tasks') }}</span>
            <span><i class="pm-dot d"></i> {{ __('Done') }}</span>
            <span><i class="pm-dot r"></i> {{ __('Routines done/planned') }}</span>
            <span class="pm-hint">{{ __('Select a day to plan it') }}</span>
        </div>
    @else
        {{-- Week view: clean day columns with period sections + inline add --}}
        @php
            $weekDaysMeta = collect($days)->map(fn ($d) => [
                'date' => $d['date']->toDateString(),
                'label' => $isFa
                    ? \Morilog\Jalali\Jalalian::fromCarbon($d['date'])->format('l')
                    : $d['date']->format('D'),
            ])->values();
            $weekPeriodsMeta = collect(config('routines.periods', []))->map(fn ($p, $k) => [
                'key' => $k, 'label' => __($p['label']), 'icon' => $p['icon'], 'color' => $p['color'],
            ])->values();
        @endphp
        <div class="pw-grid" id="plWeek" data-week-days='@json($weekDaysMeta)' data-periods='@json($weekPeriodsMeta)'>
            @foreach($days as $day)
                @include('planner._week-day', [
                    'day' => $day,
                    'isFa' => $isFa,
                    'isDayToday' => $day['date']->isToday(),
                ])
            @endforeach
        </div>
        {{-- Floating move-to-day menu (one instance for the whole week) --}}
        <div class="pw-move" id="pwMoveMenu" hidden>
            <div class="pw-move-title">{{ __('Move to') }}</div>
            <div data-move-days></div>
        </div>
    @endif

    </div>

    <div id="plConfetti" aria-hidden="true"></div>
    <div id="plToast" role="status"></div>
    <div id="plAvoidUndoStack" aria-live="polite"></div>
    {{-- Avoid confirm-before-save modal (Phase 0) --}}
    <div class="pl-avoid-confirm" id="plAvoidConfirm" hidden>
        <div class="pl-avoid-confirm-backdrop" data-avoid-confirm-cancel></div>
        <div class="pl-avoid-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="plAvoidConfirmTitle">
            <div class="pl-avoid-confirm-title" id="plAvoidConfirmTitle"></div>
            <div class="pl-avoid-confirm-sub" data-avoid-confirm-sub></div>
            <div class="pl-avoid-confirm-rows" data-avoid-confirm-rows></div>
            <div class="pl-avoid-confirm-actions">
                <button type="button" class="cancel" data-avoid-confirm-cancel>{{ __('Cancel') }}</button>
                <button type="button" class="confirm" data-avoid-confirm-ok></button>
            </div>
        </div>
    </div>

    {{-- Routine detail modal: opened for big or tracked routines --}}
    <div class="pl-modal" id="plRoutineModal" hidden>
        <div class="pl-modal-backdrop" data-modal-close></div>
        <div class="pl-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="plModalTitle">
            <div class="pl-modal-head">
                <div>
                    <div class="pl-modal-title" id="plModalTitle" data-modal-title></div>
                    <div class="pl-modal-sub" data-modal-sub></div>
                </div>
                <button type="button" class="pl-modal-x" data-modal-close aria-label="{{ __('Close') }}">&times;</button>
            </div>
            <div class="pl-modal-body" data-modal-body></div>
            <div class="pl-modal-foot" data-modal-foot hidden>
                <span class="pl-modal-status" data-modal-status></span>
                <span class="pl-modal-foot-actions">
                    <button type="button" class="pl-modal-foot-btn skip" data-modal-skip>
                        <i class="bi bi-x-lg"></i> {{ __('Not done') }}
                    </button>
                    <button type="button" class="pl-modal-foot-btn done" data-modal-done>
                        <i class="bi bi-check-lg"></i> {{ __('Done') }}
                    </button>
                </span>
            </div>
        </div>
    </div>

    {{-- Task fail modal: note + optional reschedule in one step --}}
    <div class="pl-qa" id="plFailModal" hidden>
        <div class="pl-qa-backdrop" data-fail-close></div>
        <div class="pl-qa-dialog" role="dialog" aria-modal="true" aria-labelledby="plFailTitle">
            <div class="pl-qa-head">
                <span class="pl-qa-title" id="plFailTitle">{{ __('Mark as failed') }}</span>
                <button type="button" class="pl-qa-x" data-fail-close aria-label="{{ __('Close') }}">&times;</button>
            </div>
            <form class="pl-qa-form" data-fail-form onsubmit="return submitFailModal(this)">
                <div class="pl-fail-taskname" data-fail-taskname></div>
                <label class="pl-fail-label" for="plFailNote">{{ __('Fail note') }}</label>
                <textarea name="note" id="plFailNote" class="pl-qa-input pl-fail-note-input"
                          rows="2" maxlength="2000"
                          placeholder="{{ __('Why did this fail? (optional)') }}"></textarea>
                <label class="pl-fail-label">{{ __('Reschedule to') }}</label>
                <div class="pl-qa-chips" data-fail-chips>
                    <button type="button" data-fail-day="">{{ __('No reschedule') }}</button>
                    <button type="button" data-fail-day="+1">{{ __('Tomorrow') }}</button>
                    <button type="button" data-fail-day="+3">{{ __('In 3 days') }}</button>
                    <button type="button" data-fail-day="+7">{{ __('Next week') }}</button>
                </div>
                <div class="pl-qa-row">
                    <x-jalali-date name="reschedule_date" data-fail-date aria-label="{{ __('Reschedule to') }}" />
                </div>
                <div class="pl-qa-actions">
                    <button type="button" class="pl-fail-cancel" data-fail-close>{{ __('Cancel') }}</button>
                    <button type="submit" class="pl-fail-submit">
                        <i class="bi bi-x-octagon"></i> {{ __('Mark failed') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if($view === 'day')
        <button type="button" class="pl-fab" onclick="openQuickAdd('task')" aria-label="Quick add">
            <i class="bi bi-plus-lg"></i>
        </button>

        <div class="pl-qa" id="plQuickAdd" hidden>
            <div class="pl-qa-backdrop" data-qa-close></div>
            <div class="pl-qa-dialog pl-qa-dialog-pro" role="dialog" aria-modal="true" aria-labelledby="plQaTitle">
                <div class="pl-qa-pro-head">
                    <span class="pl-qa-pro-icon"><i class="bi bi-plus-lg"></i></span>
                    <span class="pl-qa-pro-titles">
                        <span class="pl-qa-title" id="plQaTitle">{{ __('Add task to this day') }}</span>
                        <span class="pl-qa-sub">{{ app_human_date($date) }}</span>
                    </span>
                    <button type="button" class="pl-qa-x" data-qa-close aria-label="{{ __('Close') }}">&times;</button>
                </div>
                <div class="pl-qa-tabs" role="tablist">
                    <button type="button" class="pl-qa-tab active" data-qa-tab="task" data-qa-title="{{ __('Add task to this day') }}"><i class="bi bi-check2-square"></i> {{ __('Task') }}</button>
                    <button type="button" class="pl-qa-tab" data-qa-tab="routine" data-qa-title="{{ __('Add routine to this day') }}"><i class="bi bi-arrow-repeat"></i> {{ __('Routine') }}</button>
                </div>

                <form class="pl-qa-form" data-qa-panel="task"
                      data-url="{{ route('planner.quick-add.task') }}"
                      onsubmit="return submitQuickTask(this)">
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <input type="text" name="title" class="pl-qa-input pl-qa-title-input" placeholder="{{ __('What needs to be done?') }}" autocomplete="off" maxlength="255">
                    <div class="pl-qa-error" data-qa-error hidden></div>

                    <span class="pl-qa-label">{{ __('Priority') }}</span>
                    <div class="pl-qa-seg" data-qa-seg="priority" role="radiogroup" aria-label="{{ __('Priority') }}">
                        <button type="button" data-val="low" class="pl-qa-seg-low"><span class="pl-qa-dot"></span>{{ __('Low') }}</button>
                        <button type="button" data-val="medium" class="active pl-qa-seg-medium"><span class="pl-qa-dot"></span>{{ __('Medium') }}</button>
                        <button type="button" data-val="high" class="pl-qa-seg-high"><span class="pl-qa-dot"></span>{{ __('High') }}</button>
                    </div>
                    <input type="hidden" name="priority" value="medium">

                    <span class="pl-qa-label">{{ __('When') }}</span>
                    <div class="pl-qa-chips pl-qa-chips-period" data-qa-period>
                        <button type="button" data-val="" class="active"><i class="bi bi-inbox"></i>{{ __('Anytime') }}</button>
                        @foreach(config('routines.periods', []) as $key => $period)
                            <button type="button" data-val="{{ $key }}"><i class="bi {{ $period['icon'] }}" style="color:{{ $period['color'] }};"></i>{{ __($period['label']) }}</button>
                        @endforeach
                    </div>
                    <input type="hidden" name="time_period" value="">

                    <div class="pl-qa-row">
                        <div class="pl-qa-field">
                            <span class="pl-qa-label">{{ __('Project') }}</span>
                            <select name="project_id" aria-label="{{ __('Project') }}">
                                <option value="">{{ __('No Project') }}</option>
                                @foreach(($quickProjects ?? []) as $proj)
                                    <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pl-qa-field">
                            <span class="pl-qa-label">{{ __('Exact time') }}</span>
                            <input type="time" name="due_time" aria-label="{{ __('Exact time') }}">
                        </div>
                    </div>

                    <span class="pl-qa-label">{{ __('Estimate') }}</span>
                    <div class="pl-qa-chips" data-qa-minutes>
                        <button type="button" data-min="15">15m</button>
                        <button type="button" data-min="30">30m</button>
                        <button type="button" data-min="45">45m</button>
                        <button type="button" data-min="60">1h</button>
                        <button type="button" data-min="90">1h 30m</button>
                        <button type="button" data-min="120">2h</button>
                    </div>
                    <input type="hidden" name="estimated_minutes" value="">

                    <div class="pl-qa-added" data-qa-added hidden>
                        <div class="pl-qa-added-head"><i class="bi bi-check-circle-fill"></i><span data-qa-added-count></span></div>
                        <ul data-qa-added-list></ul>
                    </div>

                    <div class="pl-qa-actions pl-qa-actions-split">
                        <button type="button" class="pl-qa-secondary" data-qa-add-new="task"><i class="bi bi-plus-lg"></i> {{ __('Add & new') }}</button>
                        <button type="submit" class="pl-qa-submit" data-qa-submit disabled><i class="bi bi-check-lg"></i> {{ __('Add task') }}</button>
                    </div>
                </form>

                <form class="pl-qa-form" data-qa-panel="routine" hidden
                      data-url="{{ route('planner.quick-add.routine') }}"
                      onsubmit="return submitQuickRoutine(this)">
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <input type="text" name="title" class="pl-qa-input pl-qa-title-input" placeholder="{{ __('Routine name') }}" autocomplete="off" maxlength="255">
                    <div class="pl-qa-error" data-qa-error hidden></div>
                    <div class="pl-qa-row">
                        <div class="pl-qa-field">
                            <span class="pl-qa-label">{{ __('Frequency') }}</span>
                            <select name="frequency" aria-label="{{ __('Frequency') }}">
                                <option value="daily">{{ __('Daily') }}</option>
                                <option value="weekly">{{ __('Weekly') }}</option>
                                <option value="monthly">{{ __('Monthly') }}</option>
                                <option value="every_n_days">{{ __('Every other day') }}</option>
                            </select>
                        </div>
                        <div class="pl-qa-field">
                            <span class="pl-qa-label">{{ __('Time of day') }}</span>
                            <select name="time_period" aria-label="{{ __('Time of day') }}">
                                <option value="">{{ __('Anytime') }}</option>
                                @foreach(config('routines.periods', []) as $key => $period)
                                    <option value="{{ $key }}">{{ __($period['label']) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="pl-qa-added" data-qa-added hidden>
                        <div class="pl-qa-added-head"><i class="bi bi-check-circle-fill"></i><span data-qa-added-count></span></div>
                        <ul data-qa-added-list></ul>
                    </div>

                    <div class="pl-qa-actions pl-qa-actions-split">
                        <button type="button" class="pl-qa-secondary" data-qa-add-new="routine"><i class="bi bi-plus-lg"></i> {{ __('Add & new') }}</button>
                        <button type="submit" class="pl-qa-submit" data-qa-submit disabled><i class="bi bi-check-lg"></i> {{ __('Add routine') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Backlog picker modal: pull existing unscheduled tasks into this day --}}
        <div class="pl-qa" id="plBacklogModal" hidden data-date="{{ $date->toDateString() }}">
            <div class="pl-qa-backdrop" data-bl-close></div>
            <div class="pl-qa-dialog pl-qa-dialog-pro" role="dialog" aria-modal="true" aria-labelledby="plBlTitle">
                <div class="pl-qa-pro-head">
                    <span class="pl-qa-pro-icon"><i class="bi bi-inboxes"></i></span>
                    <span class="pl-qa-pro-titles">
                        <span class="pl-qa-title" id="plBlTitle">{{ __('Add existing tasks') }}</span>
                        <span class="pl-qa-sub">{{ app_human_date($date) }}</span>
                    </span>
                    <button type="button" class="pl-qa-x" data-bl-close aria-label="{{ __('Close') }}">&times;</button>
                </div>
                <div class="pl-qadd-search pl-bl-search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="plBacklogSearch" autocomplete="off"
                           placeholder="{{ __('Search tasks… (unscheduled, overdue, no time)') }}"
                           aria-label="{{ __('Search tasks') }}">
                    <button type="button" class="pl-qadd-clear" id="plBacklogClear" hidden
                            aria-label="{{ __('Close') }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <div style="padding:8px 14px 0;">
                    <select id="plBacklogProject" class="form-select form-select-sm" aria-label="{{ __('Filter by project') }}">
                        @include('planner._backlog-project-options')
                    </select>
                </div>
                <div class="pl-qadd-filters" data-bl-filters role="group" aria-label="{{ __('Priority') }}">
                    <button type="button" data-prio="" class="active">{{ __('All') }}</button>
                    <button type="button" data-prio="high"><span class="pl-qa-dot" style="background:#dc2626;"></span>{{ __('High') }}</button>
                    <button type="button" data-prio="medium"><span class="pl-qa-dot" style="background:#d97706;"></span>{{ __('Medium') }}</button>
                    <button type="button" data-prio="low"><span class="pl-qa-dot" style="background:#16a34a;"></span>{{ __('Low') }}</button>
                </div>
                <div class="pl-qadd-list pl-bl-list" id="plBacklogList" role="listbox"
                     aria-label="{{ __('Unscheduled tasks') }}" aria-multiselectable="true"></div>
                <div class="pl-bl-foot">
                    <button type="button" class="pl-qa-secondary" data-bl-select-all>
                        <i class="bi bi-check2-square"></i> <span data-bl-select-all-label>{{ __('Select all') }}</span>
                    </button>
                    <button type="button" class="pl-qa-submit-bl" data-bl-add disabled>
                        <i class="bi bi-arrow-down-circle"></i> <span data-bl-add-label></span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @include('planner._focus-modal')
    @include('planner._kickoff-modal')
    @include('planner._ai-schedule-modal')
    @include('planner._next-up-modal')
    @include('planner._floating-focus-bar')
    @include('planner._avoid-history-modal')
@endsection

@push('scripts')
<script>
    let PL_CSRF = '{{ csrf_token() }}';
    const PL_SKIP_I18N = {
        notDone: @json(__('Not done')),
        mark: @json(__('Mark as not done')),
        undo: @json(__('Undo skip')),
        markStep: @json(__('Mark step as not done')),
        settled: @json(__('Settled')),
        postponed: @json(__('Postponed')),
        movedTomorrow: @json(__('Moved to tomorrow — the schedule restarts from tomorrow.')),
        postponeFail: @json(__('Could not move the routine.')),
    };
    const PL_FAIL_I18N = {
        failed: @json(__('Failed')),
        mark: @json(__('Mark as failed')),
        undo: @json(__('Undo fail')),
    };
    /* Locale-aware UI numbers + strings (Persian digits when <html lang="fa">). */
    const PL_IS_FA = (document.documentElement.lang || '').toLowerCase().startsWith('fa');
    function plNum(v) {
        if (v === null || v === undefined) return '';
        const s = String(v);
        return PL_IS_FA ? s.replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]) : s;
    }
    const PL_I18N_EXTRA = {
        showDetails: @json(__('Show details')),
        hideDetails: @json(__('Hide details')),
        anytime: @json(__('Anytime')),
        progressOf: @json(__('{done} of {total} done')),
        pctSuffix: PL_IS_FA ? '٪' : '%',
    };
    const plRawFetch = window.fetch.bind(window);

    /* One POST wrapper for the whole page: sends the live CSRF token and, when
       the server answers 419 (token rotated / session expired since the page
       was rendered), refreshes the token and retries once — only reloading
       when the session is truly gone so the user lands back on the toggle. */
    async function plFetch(url, init = {}) {
        init.headers = Object.assign({ 'X-CSRF-TOKEN': PL_CSRF }, init.headers || {});
        let res = await plRawFetch(url, init);
        if (res.status === 419) {
            const fresh = await plRefreshCsrf();
            if (fresh) {
                init.headers['X-CSRF-TOKEN'] = fresh;
                res = await plRawFetch(url, init);
            }
            if (res.status === 419 || res.status === 401) {
                window.location.reload();
                return new Response(null, { status: 419 });
            }
        }
        return res;
    }

    async function plRefreshCsrf() {
        try {
            const page = await plRawFetch(window.location.href, {
                headers: { 'Accept': 'text/html' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const m = (await page.text()).match(/<meta\s+name=["']csrf-token["']\s+content=["']([^"']+)["']/i);
            if (m) {
                PL_CSRF = m[1];
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.content = m[1];
                return m[1];
            }
        } catch (e) { /* network error — caller reloads below */ }
        return null;
    }

    /* A 404 on a toggle means the bound record no longer exists (deleted or
       archived in another tab). The current view is stale, so resync once
       instead of leaving a broken checkbox and a console error. */
    let plGoneReloading = false;
    function plHandleGone() {
        if (plGoneReloading) return;
        plGoneReloading = true;
        window.location.reload();
    }

    async function toggleTask(cb, silent) {
        const url = cb.dataset.url;
        const id  = cb.dataset.id;
        cb.disabled = true;
        try {
            const res = await plFetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json' },
            });
            if (res.status === 404) { plHandleGone(); throw new Error('GONE'); }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            document.querySelectorAll('[data-task-item][data-id="' + id + '"]').forEach(row => {
                row.classList.toggle('is-done', !!json.completed);
                row.dataset.completed = json.completed ? '1' : '0';
            });
            refreshCounters();
            if (window.pwWeekRefresh) { try { window.pwWeekRefresh(id); } catch (e) {} }
            if (!silent) {
                plShowToast(json.completed ? @json(__('Done ✓')) : @json(__('Reopened')), () => {
                    document.querySelectorAll('[data-task-item][data-id="' + id + '"] .pl-check input')
                        .forEach(c => toggleTask(c, true));
                });
            }
        } catch (e) {
            if (e.message !== 'GONE') {
                cb.checked = !cb.checked;
                console.error('[Planner] toggle failed', e);
            }
        } finally {
            cb.disabled = false;
        }
    }

    function refreshCounters() {
        let pending = 0, done = 0;
        document.querySelectorAll('[data-task-item][data-count="1"]').forEach(el => {
            if (el.dataset.completed === '1') done++; else pending++;
        });
        const p = document.getElementById('plPendingCount');
        const d = document.getElementById('plDoneCount');
        const t = document.getElementById('plTodaySectionCount');
        if (p) p.textContent = plNum(pending);
        if (d) d.textContent = plNum(done);
        if (t) t.textContent = plNum(pending);
        if (window.plRefreshDayProgress) window.plRefreshDayProgress();
    }
    window.refreshCounters = refreshCounters;

    async function toggleRoutine(cb) {
        const url = cb.dataset.url;
        const id  = cb.dataset.id;
        const date = cb.dataset.date;
        cb.disabled = true;
        try {
            const json = await routineToggleRequest(url, date);
            applyRoutineToggle(id, date, !!json.completed);
            if (json.items && json.items.length) syncStepButtons(id, date, !!json.completed);
            /* Package B: accurate flame from server + minimal undo toast */
            if (json.completed) {
                setStreak(id, json.streak ?? null);
                showRoutineToast(id, date);
                maybeCelebrate();
            } else {
                setStreak(id, json.streak ?? 0, true);
                hideRoutineToast();
            }
            refreshRoutineCounters();
        } catch (e) {
            if (e.message !== 'GONE') {
                cb.checked = !cb.checked;
                /* Tracked routines refuse a bare tick with no logged numbers:
                   surface the reason instead of failing silently. */
                if (e.status === 422 && e.response) {
                    e.response.json().then(
                        j => plShowToast(j.message || @json(__('Log the numbers first.'))),
                        () => plShowToast(@json(__('Log the numbers first.')))
                    );
                } else {
                    console.error('[Planner] routine toggle failed', e);
                }
            }
        } finally {
            cb.disabled = false;
        }
    }

    function routineToggleRequest(url, date) {
        return plFetch(url + (url.includes('?') ? '&' : '?') + 'date=' + encodeURIComponent(date), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json' },
        }).then(res => {
            if (res.status === 404) { plHandleGone(); throw new Error('GONE'); }
            if (!res.ok) {
                const err = new Error('HTTP ' + res.status);
                err.status = res.status;
                err.response = res;
                throw err;
            }
            return res.json();
        });
    }

    function applyRoutineToggle(id, date, completed) {
        document.querySelectorAll('[data-routine-item][data-id="' + id + '"][data-date="' + date + '"]').forEach(row => {
            row.classList.toggle('is-done', completed);
            row.dataset.completed = completed ? '1' : '0';
            const box = row.querySelector('input[type="checkbox"]');
            if (box) box.checked = completed;
            /* Ticking off a previously skipped day converts it server-side,
               so clear the skip visuals here as well. */
            if (completed) applyRoutineSkip(id, date, false);
            row.dataset.status = completed ? 'done'
                : (row.classList.contains('is-skipped') ? 'skipped' : 'open');
        });
        if (typeof refreshRoutineModalFoot === 'function') refreshRoutineModalFoot();
    }

    /* ── ✗ mark / unmark a routine as "did not do it" ── */
    async function skipRoutine(btn) {
        const url = btn.dataset.skipUrl;
        const id = btn.dataset.id;
        const date = btn.dataset.date;
        btn.disabled = true;
        try {
            const json = await plFetch(url + (url.includes('?') ? '&' : '?') + 'date=' + encodeURIComponent(date), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json' },
            }).then(res => {
                if (res.status === 404) { plHandleGone(); throw new Error('GONE'); }
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            });
            applyRoutineSkip(id, date, !!json.skipped);
            /* Undoing an auto-closed day reopens its step skips too. */
            if (json.steps_reopened) {
                document.querySelectorAll('[data-step-wrap][data-routine="' + id + '"][data-date="' + date + '"].is-skipped')
                    .forEach(w => applyStepSkip(w.dataset.id, id, date, false));
            }
            refreshStepCounts();
            if (json.skipped) {
                hideRoutineToast();
                refreshRoutineCounters();
            } else {
                refreshRoutineCounters();
            }
            setStreak(id, json.streak ?? 0, !json.skipped);
        } catch (e) {
            if (e.message !== 'GONE') console.error('[Planner] routine skip failed', e);
        } finally {
            btn.disabled = false;
        }
    }

    /* ── ↔ move an interval/weekly routine to tomorrow and re-anchor ── */
    async function postponeRoutine(btn) {
        const url = btn.dataset.postponeUrl;
        const id = btn.dataset.id;
        const date = btn.dataset.date;
        btn.disabled = true;
        try {
            const json = await plFetch(url + (url.includes('?') ? '&' : '?') + 'date=' + encodeURIComponent(date), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json' },
            }).then(res => {
                if (res.status === 404) { plHandleGone(); throw new Error('GONE'); }
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            });
            /* Today is now skipped; the schedule re-anchors from tomorrow. */
            applyRoutineSkip(id, date, true);
            refreshStepCounts();
            refreshRoutineCounters();
            setStreak(id, json.streak ?? 0, true);
            plShowToast(PL_SKIP_I18N.movedTomorrow);
        } catch (e) {
            if (e.message !== 'GONE') {
                console.error('[Planner] routine postpone failed', e);
                plShowToast(PL_SKIP_I18N.postponeFail);
            }
        } finally {
            btn.disabled = false;
        }
    }

    function applyRoutineSkip(id, date, skipped) {
        document.querySelectorAll('[data-routine-item][data-id="' + id + '"][data-date="' + date + '"]').forEach(row => {
            row.classList.toggle('is-skipped', skipped);
            if (skipped) {
                row.classList.remove('is-done');
                row.dataset.completed = '0';
                const box = row.querySelector('input[type="checkbox"]');
                if (box) box.checked = false;
            }
            row.dataset.status = skipped ? 'skipped'
                : (row.classList.contains('is-done') ? 'done' : 'open');

            const sb = row.querySelector('.pl-routine-skip');
            if (sb) {
                sb.classList.toggle('active', skipped);
                sb.title = skipped ? PL_SKIP_I18N.undo : PL_SKIP_I18N.mark;
                sb.setAttribute('aria-pressed', skipped ? 'true' : 'false');
            }

            const meta = row.querySelector('.pl-task-meta');
            const tag = row.querySelector('.pl-skip-tag');
            if (skipped && !tag && meta) {
                const el = document.createElement('span');
                el.className = 'pl-skip-tag';
                el.innerHTML = '<i class="bi bi-x-circle-fill"></i> ' + PL_SKIP_I18N.notDone;
                meta.prepend(el);
            } else if (!skipped && tag) {
                tag.remove();
            }
        });
        if (typeof refreshRoutineModalFoot === 'function') refreshRoutineModalFoot();
    }

    /* Reflect whole-routine toggles on the step chips too */
    function syncStepButtons(id, date, completed) {
        document.querySelectorAll('[data-step-item][data-routine="' + id + '"][data-date="' + date + '"]').forEach(btn => {
            /* Unchecking never touches explicitly skipped steps (server-side
               they are left alone too). */
            if (!completed && btn.closest('[data-step-wrap]')?.classList.contains('is-skipped')) return;
            btn.classList.toggle('done', completed);
            const i = btn.querySelector('i');
            if (i) i.className = 'bi ' + (completed ? 'bi-check-circle-fill' : 'bi-circle');
        });
        /* A whole-routine check converts step skips to done server-side. */
        if (completed) {
            document.querySelectorAll('[data-step-wrap][data-routine="' + id + '"][data-date="' + date + '"]').forEach(w => {
                w.classList.remove('is-skipped');
                w.querySelectorAll('.pl-step').forEach(b => b.classList.remove('is-skipped'));
                const sb = w.querySelector('[data-step-skip]');
                if (sb) {
                    sb.classList.remove('active');
                    sb.title = PL_SKIP_I18N.markStep;
                    sb.setAttribute('aria-pressed', 'false');
                }
            });
        }
        refreshStepCounts();
    }

    /* ── ✗ mark / unmark a single routine step as "did not do it" ── */
    async function skipStep(btn) {
        btn.disabled = true;
        try {
            await skipStepRequest(btn);
        } catch (e) {
            if (e.message !== 'GONE') console.error('[Planner] step skip failed', e);
        } finally {
            btn.disabled = false;
        }
    }

    function applyStepSkip(itemId, routineId, date, skipped) {
        const sel = '[data-step-wrap][data-id="' + itemId + '"]'
            + (routineId ? '[data-routine="' + routineId + '"]' : '')
            + '[data-date="' + date + '"]';
        document.querySelectorAll(sel).forEach(w => {
            w.classList.toggle('is-skipped', skipped);
            const main = w.querySelector('[data-step-item]');
            if (main) {
                main.classList.toggle('is-skipped', skipped);
                if (skipped) {
                    main.classList.remove('done');
                    const i = main.querySelector('i');
                    if (i) i.className = 'bi bi-x-circle';
                } else {
                    const i = main.querySelector('i');
                    if (i && !main.classList.contains('done')) i.className = 'bi bi-circle';
                }
            }
            const sb = w.querySelector('[data-step-skip]');
            if (sb) {
                sb.classList.toggle('active', skipped);
                sb.title = skipped ? PL_SKIP_I18N.undo : PL_SKIP_I18N.markStep;
                sb.setAttribute('aria-pressed', skipped ? 'true' : 'false');
            }
        });
        /* A skipped-then-ticked step clears its skip server-side. */
        if (!skipped && routineId) {
            document.querySelectorAll('[data-step-item][data-id="' + itemId + '"][data-routine="' + routineId + '"][data-date="' + date + '"]').forEach(b => {
                b.classList.remove('is-skipped');
            });
        }
    }

    /* ── Task fail flow: note + optional reschedule, red failed state ── */
    const plFailModal = document.getElementById('plFailModal');
    let plFailTask = null;

    document.addEventListener('click', function (e) {
        const failBtn = e.target.closest('[data-fail-task]');
        if (failBtn) {
            e.stopPropagation();
            const id = failBtn.dataset.id;
            if (failBtn.classList.contains('active')) {
                unfailTask(id, failBtn);
            } else {
                openFailModal(id, failBtn);
            }
            return;
        }
        if (e.target.closest('[data-fail-close]')) {
            if (plFailModal) plFailModal.hidden = true;
        }
    });

    function openFailModal(id, btn) {
        if (!plFailModal) return;
        const row = btn.closest('[data-task-item]');
        const title = row ? (row.querySelector('.pl-task-title')?.textContent || '').trim() : '';
        plFailTask = {
            id,
            failUrl: btn.dataset.failUrl,
            row,
        };
        plFailModal.querySelector('[data-fail-taskname]').textContent = title;
        plFailModal.querySelector('[data-fail-form]').reset();
        if (typeof window.jalaliSyncVisible === 'function') window.jalaliSyncVisible('reschedule_date');
        plFailModal.querySelectorAll('[data-fail-chips] button').forEach(b => b.classList.remove('picked', 'active'));
        const noRes = plFailModal.querySelector('[data-fail-day=""]');
        if (noRes) noRes.classList.add('picked');
        plFailModal.hidden = false;
        setTimeout(() => plFailModal.querySelector('#plFailNote')?.focus(), 60);
    }

    document.addEventListener('click', function (e) {
        const chip = e.target.closest('[data-fail-day]');
        if (!chip || !plFailModal || chip.closest('#plFailModal') === null) return;
        e.preventDefault();
        plFailModal.querySelectorAll('[data-fail-chips] button').forEach(b => b.classList.remove('picked', 'active'));
        chip.classList.add('picked');
        const dateInput = plFailModal.querySelector('[data-fail-date]');
        const off = chip.dataset.failDay;
        if (!off) {
            if (dateInput) dateInput.value = '';
            return;
        }
        const d = new Date();
        d.setDate(d.getDate() + parseInt(off, 10));
        if (dateInput) dateInput.value = d.toISOString().slice(0, 10);
        if (typeof window.jalaliSyncVisible === 'function') window.jalaliSyncVisible('reschedule_date');
    });

    async function submitFailModal(form) {
        if (!plFailTask) return false;
        const btn = form.querySelector('[type="submit"]');
        const note = form.querySelector('#plFailNote')?.value || '';
        const reschedule = form.querySelector('[data-fail-date]')?.value || '';
        btn.disabled = true;
        try {
            const json = await plFetch(plFailTask.failUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ note, reschedule_date: reschedule || null }),
            }).then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            });
            plFailModal.hidden = true;
            if (json.rescheduled) {
                /* The task moved to another day — reload so groups stay truthful. */
                location.reload();
                return false;
            }
            applyTaskFail(plFailTask.id, true, json.fail_note || note || '');
            refreshNextUp();
        } catch (e) {
            console.error('[Planner] task fail failed', e);
        } finally {
            btn.disabled = false;
        }
        return false;
    }

    async function unfailTask(id, btn) {
        const url = btn.dataset.unfailUrl;
        btn.disabled = true;
        try {
            await plFetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json' },
            }).then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            });
            applyTaskFail(id, false, '');
            refreshNextUp();
        } catch (e) {
            console.error('[Planner] task unfail failed', e);
        } finally {
            btn.disabled = false;
        }
    }

    function applyTaskFail(id, failed, note) {
        document.querySelectorAll('[data-task-item][data-id="' + id + '"]').forEach(row => {
            row.classList.toggle('is-failed', failed);
            row.dataset.failed = failed ? '1' : '0';
            const fb = row.querySelector('[data-fail-task]');
            if (fb) {
                fb.classList.toggle('active', failed);
                fb.title = failed ? PL_FAIL_I18N.undo : PL_FAIL_I18N.mark;
                fb.setAttribute('aria-label', failed ? PL_FAIL_I18N.undo : PL_FAIL_I18N.mark);
                const ic = fb.querySelector('i');
                if (ic) ic.className = 'bi ' + (failed ? 'bi-arrow-counterclockwise' : 'bi-x-octagon');
            }
            let tag = row.querySelector('.pl-fail-tag');
            const meta = row.querySelector('.pl-task-meta');
            if (failed && !tag && meta) {
                tag = document.createElement('span');
                tag.className = 'pl-fail-tag';
                tag.innerHTML = '<i class="bi bi-x-circle-fill"></i> ' + PL_FAIL_I18N.failed;
                meta.prepend(tag);
            } else if (!failed && tag) {
                tag.remove();
            }
            let noteEl = row.querySelector('.pl-fail-note');
            if (failed && note) {
                if (!noteEl && row.querySelector('.pl-task-body')) {
                    noteEl = document.createElement('div');
                    noteEl.className = 'pl-fail-note';
                    row.querySelector('.pl-task-body').appendChild(noteEl);
                }
                if (noteEl) {
                    noteEl.title = note;
                    noteEl.innerHTML = '';
                    const ic = document.createElement('i');
                    ic.className = 'bi bi-chat-left-text';
                    noteEl.appendChild(ic);
                    noteEl.appendChild(document.createTextNode(' ' + (note.length > 120 ? note.slice(0, 120) + '…' : note)));
                }
            } else if (!failed && noteEl) {
                noteEl.remove();
            }
        });
    }

    function refreshStepCounts() {
        document.querySelectorAll('[data-routine-item]').forEach(row => {
            const steps = row.querySelectorAll('[data-step-item]');
            if (!steps.length) return;
            const done = [...steps].filter(s => s.classList.contains('done')).length;
            const badge = row.querySelector('.steps-count');
            if (badge) {
                badge.textContent = plNum(done) + '/' + plNum(steps.length);
                badge.classList.toggle('all', done === steps.length);
            }
            /* Partial-fail badge: how many steps are marked "not done". */
            const skipped = row.querySelectorAll('[data-step-wrap].is-skipped').length;
            let skipBadge = row.querySelector('[data-skip-count]');
            if (skipped > 0) {
                if (!skipBadge && badge) {
                    skipBadge = document.createElement('span');
                    skipBadge.className = 'steps-skipped';
                    skipBadge.setAttribute('data-skip-count', '');
                    badge.after(skipBadge);
                }
                if (skipBadge) skipBadge.textContent = '✗' + skipped;
            } else if (skipBadge) {
                skipBadge.remove();
            }
            /* "Skip remaining" helper shows only on partial skips. */
            const open = [...steps].filter(s =>
                !s.classList.contains('done')
                && !s.closest('[data-step-wrap]')?.classList.contains('is-skipped')).length;
            const restBtn = row.querySelector('[data-skip-rest]');
            if (restBtn) {
                const show = open > 0 && skipped > 0;
                restBtn.style.display = show ? '' : 'none';
                const n = restBtn.querySelector('[data-skip-rest-n]');
                if (n) n.textContent = open;
            }
            /* Resolved-state sync (no reload): lock the bulk checkbox when the
               day is failed or fully settled; settled renders checked. */
            const box = row.querySelector('.pl-habit input[type="checkbox"]');
            if (!box) return;
            const isDone = row.classList.contains('is-done');
            let failed = row.classList.contains('is-skipped');
            if (!isDone && !failed && steps.length > 0 && skipped === steps.length) {
                failed = true;
                row.classList.add('is-skipped');
                row.dataset.status = 'skipped';
            }
            const settled = !isDone && !failed && steps.length > 0 && open === 0 && done > 0;
            row.classList.toggle('is-settled', settled);
            if (settled) {
                row.dataset.status = 'settled';
            } else if (!isDone && !failed && row.dataset.status === 'settled') {
                row.dataset.status = 'open';
            }
            box.disabled = failed || settled;
            if (!isDone) box.checked = settled;
            box.closest('.pl-habit')?.classList.toggle('is-locked', failed || settled);
            const core = row.querySelector('.routine-check-box i');
            if (core) core.className = 'bi ' + (failed ? 'bi-x-lg' : 'bi-check-lg');
            const sb = row.querySelector('.pl-routine-skip');
            if (sb && failed !== sb.classList.contains('active')) {
                sb.classList.toggle('active', failed);
                sb.setAttribute('aria-pressed', failed ? 'true' : 'false');
            }
            let settledTag = row.querySelector('[data-settled-tag]');
            if (settled && !settledTag) {
                const title = row.querySelector('.pl-task-title');
                if (title) {
                    settledTag = document.createElement('span');
                    settledTag.className = 'pl-settled-tag';
                    settledTag.setAttribute('data-settled-tag', '');
                    settledTag.textContent = '✓ ' + (PL_SKIP_I18N.settled || 'Settled');
                    title.appendChild(settledTag);
                }
            } else if (!settled && settledTag) {
                settledTag.remove();
            }
        });
    }

    /* Skip every still-open step of a routine, one call per step. */
    async function skipRemainingSteps(btn) {
        const routineId = btn.dataset.routine;
        const date = btn.dataset.date;
        const row = btn.closest('[data-routine-item]');
        btn.disabled = true;
        try {
            const targets = [...(row ? row.querySelectorAll('[data-step-wrap]') : [])]
                .filter(w => !w.classList.contains('is-skipped'))
                .map(w => w.querySelector('[data-step-skip]'))
                .filter(sb => sb && !sb.disabled);
            for (const sb of targets) {
                /* Re-check: an earlier call may have closed the set. */
                if (sb.closest('[data-step-wrap]')?.classList.contains('is-skipped')) continue;
                sb.disabled = true;
                try { await skipStepRequest(sb); } finally { sb.disabled = false; }
            }
        } finally {
            btn.disabled = false;
        }
    }

    function skipStepRequest(sb) {
        const url = sb.dataset.skipUrl;
        const date = sb.dataset.date;
        return plFetch(url + (url.includes('?') ? '&' : '?') + 'date=' + encodeURIComponent(date), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json' },
        }).then(res => {
            if (res.status === 404) { plHandleGone(); throw new Error('GONE'); }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        }).then(json => {
            const wrap = sb.closest('[data-step-wrap]');
            applyStepSkip(sb.dataset.id, wrap ? wrap.dataset.routine : null, date, !!json.skipped);
            if (json.routine_id && json.date) applyRoutineSkip(json.routine_id, json.date, !!json.routine_skipped);
            refreshStepCounts();
            refreshRoutineCounters();
            refreshNextUp();
        });
    }

    document.addEventListener('click', function (e) {
        const rest = e.target.closest('[data-skip-rest]');
        if (rest) {
            e.stopPropagation();
            skipRemainingSteps(rest);
        }
    });

    async function toggleCheckItem(btn) {
        /* Tracked sets-mode steps need a logged number first — ticking alone is not allowed */
        if (btn.dataset.tracked === 'sets' && btn.dataset.logged !== '1' && !btn.classList.contains('done')) {
            expandRoutineDetails(btn);
            const input = findFirstEmptySetInput(btn);
            if (input) {
                input.focus();
                input.classList.remove('pl-flash');
                void input.offsetWidth;
                input.classList.add('pl-flash');
            }
            return;
        }
        btn.disabled = true;
        try {
            const res = await plFetch(btn.dataset.url + '?date=' + encodeURIComponent(btn.dataset.date), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json' },
            });
            if (res.status === 422) {
                /* Server refused (e.g. tracked step without a logged number) */
                expandRoutineDetails(btn);
                const input = findFirstEmptySetInput(btn);
                if (input) {
                    input.focus();
                    input.classList.remove('pl-flash');
                    void input.offsetWidth;
                    input.classList.add('pl-flash');
                }
                return;
            }
            if (res.status === 404) { plHandleGone(); throw new Error('GONE'); }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();

            /* Ticking a skipped step converts it to done server-side. */
            if (json.completed) applyStepSkip(btn.dataset.id, btn.dataset.routine, btn.dataset.date, false);
            btn.classList.toggle('done', !!json.completed);
            const i = btn.querySelector('i');
            if (i) i.className = 'bi ' + (json.completed ? 'bi-check-circle-fill' : 'bi-circle');
            refreshStepCounts();

            /* routine auto-completes/un-completes with its steps */
            if (json.routine_completed !== undefined) {
                applyRoutineToggle(json.routine_id, btn.dataset.date, !!json.routine_completed);
                if (json.routine_completed && json.streak != null) setStreak(json.routine_id, json.streak);
                refreshRoutineCounters();
                if (json.routine_completed) maybeCelebrate();
            }
            /* completing a step reopens a skipped day server-side — mirror it */
            if (json.routine_skipped !== undefined) {
                applyRoutineSkip(json.routine_id, btn.dataset.date, !!json.routine_skipped);
            }
        } catch (e) {
            if (e.message !== 'GONE') console.error('[Planner] step toggle failed', e);
        } finally {
            btn.disabled = false;
        }
    }

    /* ── Metric logging: single value + per-step sets ── */
    function fmtLogValue(kind, v) {
        if (v === null || v === undefined || v === '') return '';
        if (kind !== 'time') return v;
        const m = ((Math.round(Number(v)) % 1440) + 1440) % 1440;
        return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
    }

    async function logRoutineValue(btn) {
        const box = btn.closest('[data-log-value]');
        const input = box.querySelector('input[data-tp-value]') || box.querySelector('input');
        const isTime = box.dataset.kind === 'time';
        let value;
        if (isTime) {
            if (!input.value) { input.focus(); return; }
            const [h, m] = input.value.split(':').map(Number);
            value = h * 60 + (m || 0);   /* stored as minutes from midnight */
        } else {
            value = parseFloat(input.value);
            if (isNaN(value)) { input.focus(); return; }
        }
        btn.disabled = true;
        try {
            const res = await plFetch(box.dataset.url + '?date=' + encodeURIComponent(box.dataset.date), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ value }),
            });
            if (res.status === 404) { plHandleGone(); throw new Error('GONE'); }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            let saved = box.querySelector('.pl-log-saved');
            if (!saved) { saved = document.createElement('span'); saved.className = 'pl-log-saved'; box.appendChild(saved); }
            saved.textContent = '✓ ' + fmtLogValue(box.dataset.kind, json.values?.value ?? value);
            /* A value routine has one input per day: logging it completes it. */
            if (json.routine_completed) {
                applyRoutineToggle(box.dataset.routine, box.dataset.date, true);
                if (json.streak != null) setStreak(box.dataset.routine, json.streak);
                refreshRoutineCounters();
                maybeCelebrate();
            }
            refreshNextUp();
            /* Saving from the routine modal closes it automatically. */
            if (box.closest('#plRoutineModal')) {
                closeRoutineModal();
                plShowToast(@json(__('Saved ✓')));
            }
        } catch (e) {
            if (e.message !== 'GONE') console.error('[Planner] log value failed', e);
        } finally {
            btn.disabled = false;
        }
    }

    async function logRoutineSets(btn) {
        const box = btn.closest('[data-log-sets]');
        const sets = {};
        box.querySelectorAll('input[data-set]').forEach(inp => {
            if (inp.value !== '' && !isNaN(parseFloat(inp.value))) sets[inp.dataset.set] = parseFloat(inp.value);
        });
        if (!Object.keys(sets).length) { box.querySelector('input[data-set]')?.focus(); return; }
        btn.disabled = true;
        try {
            const res = await plFetch(box.dataset.url + '?date=' + encodeURIComponent(box.dataset.date), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ item_id: box.dataset.item, sets }),
            });
            if (res.status === 404) { plHandleGone(); throw new Error('GONE'); }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            const saved = json.values?.[box.dataset.item] || {};
            box.querySelectorAll('input[data-set]').forEach(inp => {
                inp.classList.toggle('has-val', saved[inp.dataset.set] !== undefined);
            });
            /* A logged set auto-ticks its step chip */
            if (json.steps_done) {
                Object.entries(json.steps_done).forEach(([itemId, done]) => {
                    setStepChip(itemId, box.dataset.date, done, true);
                });
            } else {
                setStepChip(box.dataset.item, box.dataset.date, true, true);
            }
            if (json.routine_completed) {
                applyRoutineToggle(box.dataset.routine, box.dataset.date, true);
                refreshRoutineCounters();
                maybeCelebrate();
            }
            refreshNextUp();
            /* Saving from the routine modal closes it automatically. */
            if (box.closest('#plRoutineModal')) {
                closeRoutineModal();
                plShowToast(@json(__('Saved ✓')));
            }
        } catch (e) {
            if (e.message !== 'GONE') console.error('[Planner] log sets failed', e);
        } finally {
            btn.disabled = false;
        }
    }

    /* Enter in a routine log input submits that row like clicking "ثبت". */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const inp = e.target instanceof Element ? e.target.closest('.pl-log input, .pl-logset input') : null;
        if (!inp) return;
        e.preventDefault();
        const box = inp.closest('[data-log-value], [data-log-sets]');
        const btn = box ? box.querySelector('button') : null;
        if (btn) btn.click();
    });

    /* ── Avoid habits: slip / craving / note ── */
    document.addEventListener('click', function (e) {
        const slipBtn = e.target.closest('[data-avoid-slip]');
        if (slipBtn) {
            const row = slipBtn.closest('[data-routine-item]');
            const panel = row ? row.querySelector('[data-slip-panel]') : null;
            if (panel) panel.hidden = !panel.hidden;
            return;
        }
        const noteBtn = e.target.closest('[data-avoid-note]');
        if (noteBtn) {
            const row = noteBtn.closest('[data-routine-item]');
            const panel = row ? row.querySelector('[data-note-panel]') : null;
            if (panel) panel.hidden = !panel.hidden;
            return;
        }
        const stepSlipBtn = e.target.closest('[data-avoid-step-slip]');
        if (stepSlipBtn) {
            const wrap = stepSlipBtn.closest('.pl-avoid-steps') || stepSlipBtn.closest('[data-routine-item]');
            const panels = wrap ? [...wrap.querySelectorAll('[data-step-slip-panel]')] : [];
            const stepRow = stepSlipBtn.closest('[data-step-item]');
            const idx = stepRow ? [...wrap.querySelectorAll('[data-step-item]')].indexOf(stepRow) : -1;
            const panel = idx >= 0 ? panels[idx] : null;
            if (panel) panel.hidden = !panel.hidden;
            return;
        }
    });

    function avoidPayload(form) {
        const data = { date: form.dataset.date };
        form.querySelectorAll('input, select').forEach(el => {
            if (!el.name || el.value === '') return;
            data[el.name] = el.type === 'number' ? Number(el.value) : el.value;
        });
        return data;
    }

    /* ── Phase 0: confirm-before-save + persistent 10-min undo, no reload ── */
    const PL_UNDO_KEY = 'pl_avoid_undo_v1';
    const PL_UNDO_MS = 600000;
    let plUndoTimers = {};

    function plGetUndos() {
        try { return JSON.parse(localStorage.getItem(PL_UNDO_KEY) || '[]'); }
        catch (e) { return []; }
    }
    function plSaveUndos(list) {
        try { localStorage.setItem(PL_UNDO_KEY, JSON.stringify(list)); } catch (e) {}
    }
    function plAddUndo(entry) {
        const list = plGetUndos().filter(u => u.expiresAt > Date.now());
        list.push(entry);
        plSaveUndos(list);
        plRenderUndoStack();
    }
    function plRemoveUndo(id, type) {
        plSaveUndos(plGetUndos().filter(u => !(String(u.id) === String(id) && u.type === type)));
        plRenderUndoStack();
    }
    function plFormatCountdown(ms) {
        const s = Math.max(0, Math.ceil(ms / 1000));
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    }
    function plRenderUndoStack() {
        const stack = document.getElementById('plAvoidUndoStack');
        if (!stack) return;
        stack.replaceChildren();
        clearTimeout(plUndoTimers.tick);
        const now = Date.now();
        const actives = plGetUndos().filter(u => u.expiresAt > now);
        if (!actives.length) { plSaveUndos([]); return; }
        plSaveUndos(actives);
        actives.forEach(u => {
            const bar = document.createElement('div');
            bar.className = 'pl-undo-bar';
            bar.dataset.undoId = u.id;
            bar.dataset.undoType = u.type;
            const label = document.createElement('span');
            label.textContent = (u.type === 'note' ? @json(__('Saved')) + ' ✓' : @json(__('Slip logged'))) + ' — ' + (u.routineTitle || '');
            const count = document.createElement('span');
            count.className = 'pl-undo-count';
            count.dataset.countdown = u.expiresAt;
            count.textContent = plFormatCountdown(u.expiresAt - now);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = @json(__('Undo'));
            btn.onclick = () => { u.type === 'note' ? undoNote(u.id) : undoViolation(u.id); };
            bar.append(label, count, btn);
            stack.appendChild(bar);
        });
        plUndoTimers.tick = setTimeout(plRenderUndoStack, 1000);
        // auto-prune expired
        const minExp = Math.min(...actives.map(u => u.expiresAt));
        clearTimeout(plUndoTimers.prune);
        plUndoTimers.prune = setTimeout(plRenderUndoStack, Math.max(1000, minExp - Date.now() + 500));
    }
    document.addEventListener('DOMContentLoaded', plRenderUndoStack);

    function plRoutineTitleFromForm(form) {
        const row = form.closest('[data-routine-item]');
        if (!row) return '';
        const t = row.querySelector('.pl-task-title');
        return t ? t.textContent.trim().split('\n')[0].trim().slice(0, 40) : '';
    }

    /* Confirm modal */
    let plConfirmResolve = null;
    function plOpenAvoidConfirm({ title, sub, rows, okLabel, okClass }) {
        return new Promise(resolve => {
            plConfirmResolve = resolve;
            const m = document.getElementById('plAvoidConfirm');
            m.querySelector('#plAvoidConfirmTitle').textContent = title;
            m.querySelector('[data-avoid-confirm-sub]').textContent = sub || '';
            const box = m.querySelector('[data-avoid-confirm-rows]');
            box.replaceChildren();
            (rows || []).forEach(([k, v]) => {
                if (!v) return;
                const r = document.createElement('div');
                r.className = 'r';
                const kk = document.createElement('span'); kk.className = 'k'; kk.textContent = k;
                const vv = document.createElement('span'); vv.className = 'v'; vv.textContent = v;
                r.append(kk, vv);
                box.appendChild(r);
            });
            const ok = m.querySelector('[data-avoid-confirm-ok]');
            ok.textContent = okLabel;
            ok.className = 'confirm' + (okClass === 'note' ? ' note' : '');
            m.hidden = false;
        });
    }
    function plCloseAvoidConfirm(val) {
        document.getElementById('plAvoidConfirm').hidden = true;
        if (plConfirmResolve) { plConfirmResolve(val); plConfirmResolve = null; }
    }
    document.addEventListener('click', e => {
        if (e.target.closest('[data-avoid-confirm-cancel]')) plCloseAvoidConfirm(false);
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !document.getElementById('plAvoidConfirm').hidden) plCloseAvoidConfirm(false);
    });
    document.querySelector('[data-avoid-confirm-ok]')?.addEventListener('click', () => plCloseAvoidConfirm(true));

    /* "Now" button: reset occurred_at to current minute */
    document.addEventListener('click', function (e) {
        const nowBtn = e.target.closest('[data-avoid-now]');
        if (!nowBtn) return;
        const form = nowBtn.closest('form');
        if (!form) return;
        const dt = form.querySelector('input[name="occurred_at"], input[type="datetime-local"]');
        if (dt) {
            const n = new Date();
            const pad = v => String(v).padStart(2, '0');
            dt.value = n.getFullYear() + '-' + pad(n.getMonth() + 1) + '-' + pad(n.getDate()) + 'T' + pad(n.getHours()) + ':' + pad(n.getMinutes());
            dt.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    /* Trigger/location suggestions (Phase 2): lazy-load per routine */
    const plSuggestCache = {};
    async function plLoadSuggestions(form) {
        const url = form.dataset.suggestUrl;
        if (!url) return;
        const routineId = form.dataset.routineId;
        if (plSuggestCache[routineId]) { plFillDatalists(form, plSuggestCache[routineId]); return; }
        try {
            const res = await plFetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const json = await res.json();
            plSuggestCache[routineId] = json;
            plFillDatalists(form, json);
        } catch (e) { /* silent */ }
    }
    function plFillDatalists(form, data) {
        const row = form.closest('[data-routine-item]');
        const scope = row || document;
        (data.triggers || []).forEach(t => {
            scope.querySelectorAll('datalist[id^="trig-list-"]').forEach(dl => {
                if (![...dl.options].some(o => o.value === t)) {
                    const o = document.createElement('option'); o.value = t; dl.appendChild(o);
                }
            });
        });
        (data.locations || []).forEach(t => {
            scope.querySelectorAll('datalist[id^="loc-list-"]').forEach(dl => {
                if (![...dl.options].some(o => o.value === t)) {
                    const o = document.createElement('option'); o.value = t; dl.appendChild(o);
                }
            });
        });
    }
    document.addEventListener('focusin', function (e) {
        if (e.target.matches('input[name="trigger"], input[name="location"]')) {
            const form = e.target.closest('form[data-suggest-url]');
            if (form) plLoadSuggestions(form);
        }
    });

    function plConfirmSlip(form, isNote) {
        const d = avoidPayload(form);
        const rows = [
            [@json(__('Routine')), plRoutineTitleFromForm(form)],
            d.quantity ? [@json(__('Quantity')), String(d.quantity)] : null,
            d.trigger ? [@json(__('Trigger')), d.trigger] : null,
            d.location ? [@json(__('Location')), d.location] : null,
            d.mood ? [@json(__('Mood')), String(d.mood) + ' / 10'] : null,
            d.occurred_at ? [@json(__('Time')), String(d.occurred_at).slice(0, 16).replace('T', ' ')] : null,
            d.note ? [@json(__('Note')), String(d.note).slice(0, 80)] : null,
            isNote && d.kind ? [@json(__('Kind')), d.kind === 'craving' ? @json(__('Craving')) : @json(__('Note'))] : null,
        ].filter(Boolean);
        return plOpenAvoidConfirm({
            title: isNote ? @json(__('Save this note?')) : @json(__('Log this slip?')),
            sub: @json(__('Please review before saving — you have 10 minutes to undo.')),
            rows,
            okLabel: isNote ? @json(__('Save')) : @json(__('Log Slip')),
            okClass: isNote ? 'note' : 'slip',
        });
    }

    /* In-place row update after slip (no reload) */
    function plMarkRowViolated(form, dayQty) {
        const row = form.closest('[data-routine-item]');
        if (!row) return;
        const shield = row.querySelector('.pl-avoid-shield');
        if (shield) {
            shield.classList.remove('ok'); shield.classList.add('bad');
            shield.querySelector('i')?.classList.replace('bi-shield-fill-check', 'bi-shield-fill-exclamation');
        }
        const meta = row.querySelector('.pl-task-meta .pl-priority');
        if (meta && typeof dayQty !== 'undefined') {
            meta.style.color = '#b91c1c'; meta.style.background = '#fee2e2';
            meta.innerHTML = '<i class="bi bi-exclamation-triangle"></i> ' + @json(__('Slip logged')) + (dayQty > 1 ? ' · ×' + dayQty : '');
        }
        const panel = form.closest('[data-slip-panel], [data-step-slip-panel]');
        if (panel) panel.hidden = true;
        form.reset?.();
    }
    function plUnmarkRowIfClean(routineId, date, dayQty) {
        if (dayQty > 0) return;
        document.querySelectorAll('[data-routine-item][data-id="' + routineId + '"][data-date="' + date + '"]').forEach(row => {
            const shield = row.querySelector('.pl-avoid-shield');
            if (shield) {
                shield.classList.remove('bad'); shield.classList.add('ok');
                shield.querySelector('i')?.classList.replace('bi-shield-fill-exclamation', 'bi-shield-fill-check');
            }
            const meta = row.querySelector('.pl-task-meta .pl-priority');
            if (meta) {
                meta.style.color = '#15803d'; meta.style.background = '#dcfce7';
                meta.innerHTML = '<i class="bi bi-shield-check"></i> ' + @json(__('Clean so far'));
            }
        });
    }

    async function doPostSlip(url, payload) {
        const res = await plFetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    async function submitRoutineSlip(form, e) {
        if (e) e.preventDefault();
        const btn = form.querySelector('button[type=submit]');
        const ok = await plConfirmSlip(form, false);
        if (!ok) return false;
        btn.disabled = true;
        try {
            const json = await doPostSlip(form.dataset.slipUrl + '?date=' + encodeURIComponent(form.dataset.date), avoidPayload(form));
            if (json.violation_id) {
                plAddUndo({ id: json.violation_id, type: 'violation', routineId: json.routine_id, date: json.date, routineTitle: plRoutineTitleFromForm(form), expiresAt: Date.now() + PL_UNDO_MS });
                plMarkRowViolated(form, json.day_qty);
                refreshNextUp?.();
            } else {
                plShowToast(json.already_violated ? @json(__('Already logged today')) : @json(__('Slip logged')));
            }
        } catch (e) {
            console.error('[Planner] slip failed', e);
            plShowToast(@json(__('Could not log the slip')));
        } finally {
            btn.disabled = false;
        }
        return false;
    }

    async function submitStepSlip(form, e) {
        if (e) e.preventDefault();
        const btn = form.querySelector('button[type=submit]');
        const ok = await plConfirmSlip(form, false);
        if (!ok) return false;
        btn.disabled = true;
        try {
            const json = await doPostSlip(form.dataset.slipUrl + '?date=' + encodeURIComponent(form.dataset.date), avoidPayload(form));
            if (json.violation_id) {
                plAddUndo({ id: json.violation_id, type: 'violation', routineId: json.routine_id, date: json.date, routineTitle: plRoutineTitleFromForm(form), expiresAt: Date.now() + PL_UNDO_MS });
                plMarkRowViolated(form, json.day_qty);
                refreshNextUp?.();
            } else {
                plShowToast(json.already_violated ? @json(__('Already logged today')) : @json(__('Slip logged')));
            }
        } catch (e) {
            console.error('[Planner] step slip failed', e);
            plShowToast(@json(__('Could not log the slip')));
        } finally {
            btn.disabled = false;
        }
        return false;
    }

    async function submitRoutineNote(form, e) {
        if (e) e.preventDefault();
        const btn = form.querySelector('button[type=submit]');
        const ok = await plConfirmSlip(form, true);
        if (!ok) return false;
        btn.disabled = true;
        try {
            const json = await doPostSlip(form.dataset.noteUrl + '?date=' + encodeURIComponent(form.dataset.date), avoidPayload(form));
            if (json.note_id) {
                plAddUndo({ id: json.note_id, type: 'note', routineId: json.routine_id, date: json.date, routineTitle: plRoutineTitleFromForm(form), expiresAt: Date.now() + PL_UNDO_MS });
            } else {
                plShowToast(@json(__('Saved')) + ' ✓');
            }
            const panel = form.closest('[data-note-panel]');
            if (panel) panel.hidden = true;
            form.querySelector('input[name=note]').value = '';
        } catch (e) {
            console.error('[Planner] note failed', e);
            plShowToast(@json(__('Could not save')));
        } finally {
            btn.disabled = false;
        }
        return false;
    }

    /* Next-up card for an avoid habit logs a slip on the guided step/routine. */
    async function plSlipNext() {
        const wrap = document.getElementById('plNextUp');
        const card = wrap ? wrap.querySelector('.pl-next-card') : null;
        if (!card) return;
        const stepBox = card.querySelector('[data-next-step]');
        const url = (stepBox && stepBox.dataset.slipUrl) || card.dataset.slipUrl;
        if (!url) return;
        const ok = await plOpenAvoidConfirm({
            title: @json(__('Log this slip?')),
            sub: @json(__('Please review before saving — you have 10 minutes to undo.')),
            rows: [[@json(__('Routine')), card.dataset.routineTitle || '']],
            okLabel: @json(__('Log Slip')),
            okClass: 'slip',
        });
        if (!ok) return;
        try {
            const res = await plFetch(url + '?date=' + encodeURIComponent(wrap.dataset.nextDate), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({}),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            if (json.violation_id) {
                plAddUndo({ id: json.violation_id, type: 'violation', routineId: json.routine_id, date: json.date, routineTitle: card.dataset.routineTitle || '', expiresAt: Date.now() + PL_UNDO_MS });
                refreshNextUp?.();
            }
            plShowToast(@json(__('Slip logged')));
        } catch (e) {
            console.error('[Planner] next-up slip failed', e);
            plShowToast(@json(__('Could not log the slip')));
        }
    }

    /* ── Routine details accordion (collapsed by default) ── */
    function toggleRoutineDetails(btn) {
        const row = btn.closest('[data-routine-item]');
        if (row) toggleRoutineDetailsRow(row);
    }

    function toggleRoutineDetailsRow(row) {
        const details = row.querySelector('[data-details]');
        if (!details) return;
        const open = details.classList.toggle('open');
        const chev = row.querySelector('.pl-expand');
        if (chev) {
            chev.classList.toggle('open', open);
            chev.title = open ? PL_I18N_EXTRA.hideDetails : PL_I18N_EXTRA.showDetails;
        }
    }

    /* Clicking anywhere on a routine row opens its panel. Big/tracked routines
       open the modal; the rest expand inline. Interactive controls and the open
       panel keep handling their own clicks. */
    document.addEventListener('click', function (e) {
        const row = e.target.closest('[data-routine-item]');
        if (!row) return;
        if (e.target.closest('button, input, select, textarea, a, label, [data-details]')) return;
        if (row.dataset.modal === '1') { openRoutineModal(row); return; }
        toggleRoutineDetailsRow(row);
    });

    /* ── Routine detail modal ── */
    const plModal = document.getElementById('plRoutineModal');
    const plModalBody = plModal ? plModal.querySelector('[data-modal-body]') : null;
    let plModalState = null;

    function openRoutineModal(el) {
        const row = el.closest && el.closest('[data-routine-item]') ? el.closest('[data-routine-item]') : el;
        const details = row.querySelector('[data-details]');
        if (!details || !plModal) return;
        if (plModalState) closeRoutineModal();

        /* Move the row's details panel into the modal, leaving a marker behind. */
        const placeholder = document.createComment('pl-details');
        details.parentNode.insertBefore(placeholder, details);
        details.classList.add('open');
        plModalBody.appendChild(details);

        plModal.querySelector('[data-modal-title]').textContent = row.dataset.modalTitle || '';
        plModal.querySelector('[data-modal-sub]').textContent = row.dataset.modalSub || '';
        plModal.hidden = false;
        document.body.classList.add('pl-modal-open');
        plModalState = { details, placeholder, id: row.dataset.id, date: row.dataset.date };
        refreshRoutineModalFoot();

        const firstInput = plModalBody.querySelector('input');
        if (firstInput) setTimeout(() => firstInput.focus(), 60);
    }

    /* Modal footer: quick Done / Not-done driving the underlying row. */
    function refreshRoutineModalFoot() {
        const foot = plModal ? plModal.querySelector('[data-modal-foot]') : null;
        if (!foot || !plModalState) return;
        const row = document.querySelector('[data-routine-item][data-id="' + plModalState.id + '"][data-date="' + plModalState.date + '"]');
        foot.hidden = false;
        const status = foot.querySelector('[data-modal-status]');
        const doneBtn = foot.querySelector('[data-modal-done]');
        const skipBtn = foot.querySelector('[data-modal-skip]');
        const st = row ? (row.dataset.status || (row.dataset.completed === '1' ? 'done' : 'open')) : 'open';
        if (status) {
            status.className = 'pl-modal-status' + (st === 'done' ? ' is-done' : st === 'skipped' ? ' is-skipped' : '');
            status.textContent = st === 'done' ? '✓ ' + (row?.querySelector('.flame')?.textContent || '') : st === 'skipped' ? '✗ ' + PL_SKIP_I18N.notDone : '';
        }
        if (skipBtn) skipBtn.classList.toggle('active', st === 'skipped');
        /* A locked day (failed / settled) has no bulk action: the footer
           Done mirrors the row checkbox lock. */
        if (doneBtn) {
            const locked = !row || row.querySelector('.pl-habit input[type="checkbox"]')?.disabled;
            doneBtn.disabled = !!locked;
        }
    }

    if (plModal) {
        const doneBtn = plModal.querySelector('[data-modal-done]');
        if (doneBtn) doneBtn.addEventListener('click', function () {
            if (!plModalState) return;
            const row = document.querySelector('[data-routine-item][data-id="' + plModalState.id + '"][data-date="' + plModalState.date + '"]');
            const box = row ? row.querySelector('input[type="checkbox"]') : null;
            if (box && !box.disabled) box.click();
            setTimeout(refreshRoutineModalFoot, 350);
        });
        const skipBtn = plModal.querySelector('[data-modal-skip]');
        if (skipBtn) skipBtn.addEventListener('click', function () {
            if (!plModalState) return;
            const row = document.querySelector('[data-routine-item][data-id="' + plModalState.id + '"][data-date="' + plModalState.date + '"]');
            const sb = row ? row.querySelector('.pl-routine-skip') : null;
            if (sb && !sb.disabled) sb.click();
            setTimeout(refreshRoutineModalFoot, 350);
        });
    }

    function closeRoutineModal() {
        if (!plModalState) return;
        const { details, placeholder } = plModalState;
        details.classList.remove('open');
        if (placeholder.parentNode) placeholder.parentNode.insertBefore(details, placeholder);
        placeholder.remove();
        plModal.hidden = true;
        document.body.classList.remove('pl-modal-open');
        plModalState = null;
        refreshStepCounts();
    }

    if (plModal) {
        plModal.querySelectorAll('[data-modal-close]').forEach(b => b.addEventListener('click', closeRoutineModal));
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeRoutineModal();
        });
    }

    function expandRoutineDetails(el) {
        const row = el.closest('[data-routine-item]');
        const details = row ? row.querySelector('[data-details]') : null;
        if (details && !details.classList.contains('open')) {
            details.classList.add('open');
            const chev = row.querySelector('.pl-expand');
            if (chev) { chev.classList.add('open'); chev.title = PL_I18N_EXTRA.hideDetails; }
        }
    }

    function findFirstEmptySetInput(stepBtn) {
        /* Works whether the steps live in the row (accordion) or the modal. */
        const scope = stepBtn.closest('[data-routine-item], [data-modal-body]');
        if (!scope) return null;
        const box = scope.querySelector('[data-log-sets][data-item="' + stepBtn.dataset.id + '"]');
        if (!box) return null;
        return box.querySelector('input[data-set]:not(.has-val)') || box.querySelector('input[data-set]');
    }

    function setStepChip(itemId, date, done, logged) {
        document.querySelectorAll('[data-step-item][data-id="' + itemId + '"][data-date="' + date + '"]').forEach(chip => {
            chip.classList.toggle('done', !!done);
            const i = chip.querySelector('i');
            if (i) i.className = 'bi ' + (done ? 'bi-check-circle-fill' : 'bi-circle');
            if (logged !== undefined) chip.dataset.logged = logged ? '1' : '0';
        });
        refreshStepCounts();
    }

    /* Flame reflects the server-computed streak (never inflated client-side) */
    function setStreak(id, streak, hideIfZero) {        document.querySelectorAll('[data-routine-item][data-id="' + id + '"] .flame').forEach(fl => {
            if (!streak) {
                if (hideIfZero) fl.remove();
                return;
            }
            fl.textContent = '🔥' + plNum(streak);
        });
    }

    /* Minimal toast with undo (package B) */
    let plToastTimer = null;
    function showRoutineToast(id, date) {
        const toast = document.getElementById('plToast');
        if (!toast) return;
        const row = document.querySelector('[data-routine-item][data-id="' + id + '"][data-date="' + date + '"]');
        const title = row ? (row.querySelector('.pl-task-title')?.textContent || '').trim() : 'Routine';
        toast.replaceChildren();
        const span = document.createElement('span');
        span.textContent = title + ' ' + @json(__('done ✓'));
        toast.appendChild(span);
        const undo = document.createElement('button');
        undo.type = 'button';
        undo.textContent = @json(__('Undo'));
        undo.onclick = () => {
            hideRoutineToast();
            try {
                routineToggleRequest(document.querySelector('[data-routine-item][data-id="' + id + '"][data-date="' + date + '"] input[type="checkbox"]')?.dataset.url || '', date)
                    .then(() => { applyRoutineToggle(id, date, false); syncStepButtons(id, date, false); refreshRoutineCounters(); });
            } catch (e) { /* keep UI state */ }
        };
        toast.appendChild(undo);
        toast.classList.add('show');
        clearTimeout(plToastTimer);
        plToastTimer = setTimeout(hideRoutineToast, 4500);
    }
    function hideRoutineToast() {
        const t = document.getElementById('plToast');
        if (t) { t.classList.remove('show'); t.replaceChildren(); }
        clearTimeout(plToastTimer);
    }

    /* One small confetti burst when ALL today's (counted) routines are done */
    function maybeCelebrate() {
        const items = document.querySelectorAll('[data-routine-item][data-count="1"]');
        if (!items.length) return;
        let done = 0;
        items.forEach(el => { if (el.dataset.completed === '1') done++; });
        if (done !== items.length) return;
        const host = document.getElementById('plConfetti');
        if (!host || host.childElementCount) return;
        const colors = ['#7c3aed','#a78bfa','#30a46c','#f59e0b','#e5484d','#0b6bcb'];
        for (let i = 0; i < 16; i++) {
            const p = document.createElement('i');
            p.style.left = (5 + Math.random() * 90) + '%';
            p.style.background = colors[i % colors.length];
            p.style.animationDelay = (Math.random() * .25) + 's';
            p.style.animationDuration = (1.1 + Math.random() * .7) + 's';
            host.appendChild(p);
        }
        setTimeout(() => host.replaceChildren(), 2400);
    }

    function refreshRoutineCounters() {
        let done = 0, total = 0;
        document.querySelectorAll('[data-routine-item][data-count="1"]').forEach(el => {
            total++;
            if (el.dataset.completed === '1') done++;
        });
        const rd = document.getElementById('plRoutineDone');
        const rs = document.getElementById('plRoutineSectionCount');
        if (rd) rd.textContent = plNum(done);
        if (rs) rs.textContent = plNum(total);
        if (window.plRefreshDayProgress) window.plRefreshDayProgress();

        // per-day counters in week view
        document.querySelectorAll('.pl-day').forEach(dayEl => {
            const routines = dayEl.querySelectorAll('[data-routine-item]');
            if (!routines.length) return;
            let d = 0;
            routines.forEach(r => { if (r.dataset.completed === '1') d++; });
            const countEl = dayEl.querySelector('.pl-day-count');
            if (countEl) countEl.textContent = plNum(d) + '/' + plNum(routines.length);
        });
    }

    /* ── Generic undo toast (reuses the routine toast shell) ── */
    function plShowToast(message, undoFn) {
        const toast = document.getElementById('plToast');
        if (!toast) return;
        toast.replaceChildren();
        const span = document.createElement('span');
        span.textContent = message;
        toast.appendChild(span);
        if (undoFn) {
            const undo = document.createElement('button');
            undo.type = 'button';
        undo.textContent = @json(__('Undo'));
            undo.onclick = () => { hideRoutineToast(); undoFn(); };
            toast.appendChild(undo);
        }
        toast.classList.add('show');
        clearTimeout(plToastTimer);
        plToastTimer = setTimeout(hideRoutineToast, 4500);
    }

    /* Undo-only (10 min): no permanent delete/edit UI */
    async function undoViolation(violationId) {
        const entry = plGetUndos().find(u => String(u.id) === String(violationId) && u.type === 'violation');
        try {
            const res = await plFetch('{{ url('planner/violations') }}/' + violationId, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json' },
            });
            if (res.status === 410) throw new Error('HTTP 410');
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json().catch(() => ({}));
            plRemoveUndo(violationId, 'violation');
            if (entry) plUnmarkRowIfClean(entry.routineId, entry.date, json.day_qty ?? 0);
            plShowToast(@json(__('Undone — back to clean')));
            refreshNextUp?.();
        } catch (e) {
            console.error('[Planner] undo violation failed', e);
            plRemoveUndo(violationId, 'violation');
            plShowToast(e.message.includes('410') ? @json(__('Undo window expired.')) : @json(__('Could not undo')));
        }
    }
    async function undoNote(noteId) {
        try {
            const res = await plFetch('{{ url('planner/notes') }}/' + noteId, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json' },
            });
            if (res.status === 410) throw new Error('HTTP 410');
            if (!res.ok) throw new Error('HTTP ' + res.status);
            plRemoveUndo(noteId, 'note');
            plShowToast(@json(__('Undone')));
        } catch (e) {
            console.error('[Planner] undo note failed', e);
            plRemoveUndo(noteId, 'note');
            plShowToast(e.message.includes('410') ? @json(__('Undo window expired.')) : @json(__('Could not undo')));
        }
    }
    /* ── Phase 3: avoid history modal ── */
    const plHistoryState = { routineId: null, routineTitle: '', type: 'all', range: 30, page: 1, hasMore: false, loading: false };
    function openAvoidHistory(routineId, routineTitle) {
        plHistoryState.routineId = routineId;
        plHistoryState.routineTitle = routineTitle || '';
        plHistoryState.page = 1;
        const m = document.getElementById('plAvoidHistoryModal');
        m.querySelector('[data-history-title]').textContent = routineTitle || @json(__('History'));
        m.hidden = false;
        document.body.classList.add('pl-modal-open');
        loadAvoidHistory(true);
    }
    function closeAvoidHistory() {
        document.getElementById('plAvoidHistoryModal').hidden = true;
        document.body.classList.remove('pl-modal-open');
    }
    document.addEventListener('click', e => {
        if (e.target.closest('[data-history-close]')) closeAvoidHistory();
        const hBtn = e.target.closest('[data-avoid-history]');
        if (hBtn) { openAvoidHistory(hBtn.dataset.avoidHistory, hBtn.dataset.routineTitle || ''); return; }
        const tBtn = e.target.closest('[data-history-type]');
        if (tBtn) {
            document.querySelectorAll('[data-history-type]').forEach(b => b.classList.toggle('active', b === tBtn));
            plHistoryState.type = tBtn.dataset.historyType;
            plHistoryState.page = 1;
            loadAvoidHistory(true);
            return;
        }
        const rBtn = e.target.closest('[data-history-range]');
        if (rBtn) {
            document.querySelectorAll('[data-history-range]').forEach(b => b.classList.toggle('active', b === rBtn));
            plHistoryState.range = parseInt(rBtn.dataset.historyRange, 10) || 30;
            plHistoryState.page = 1;
            loadAvoidHistory(true);
            return;
        }
        if (e.target.closest('[data-history-more]')) {
            plHistoryState.page += 1;
            loadAvoidHistory(false);
        }
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !document.getElementById('plAvoidHistoryModal')?.hidden) closeAvoidHistory();
    });
    async function loadAvoidHistory(reset) {
        const { routineId, type, range, page } = plHistoryState;
        if (!routineId || plHistoryState.loading) return;
        plHistoryState.loading = true;
        const list = document.querySelector('[data-history-list]');
        const emptyEl = document.querySelector('[data-history-empty]');
        const loadEl = document.querySelector('[data-history-loading]');
        const moreBtn = document.querySelector('[data-history-more]');
        const sub = document.querySelector('[data-history-sub]');
        if (reset) { list.replaceChildren(); emptyEl.hidden = true; }
        loadEl.hidden = false;
        moreBtn.hidden = true;
        try {
            const to = new Date().toISOString().slice(0, 10);
            const fromD = new Date(Date.now() - (range - 1) * 864e5).toISOString().slice(0, 10);
            const url = '{{ url('planner/routines') }}/' + routineId + '/history?type=' + encodeURIComponent(type) + '&from=' + fromD + '&to=' + to + '&page=' + page + '&per_page=20';
            const res = await plFetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            if (sub) sub.textContent = json.total + ' ' + @json(__('entries')) + ' · ' + fromD + ' → ' + to;
            (json.items || []).forEach(it => list.appendChild(renderHistoryItem(it)));
            plHistoryState.hasMore = !!json.has_more;
            moreBtn.hidden = !json.has_more;
            emptyEl.hidden = list.childElementCount > 0;
        } catch (err) {
            console.error('[Planner] history failed', err);
            emptyEl.hidden = list.childElementCount > 0;
        } finally {
            loadEl.hidden = true;
            plHistoryState.loading = false;
        }
    }
    function renderHistoryItem(it) {
        const div = document.createElement('div');
        div.className = 'pl-history-item ' + (it.entry_type || 'note');
        const kindLabel = it.entry_type === 'slip' ? @json(__('Slip')) : (it.entry_type === 'craving' ? @json(__('Craving')) : @json(__('Note')));
        const top = document.createElement('div');
        top.className = 'pl-history-top';
        const k = document.createElement('span');
        k.className = 'pl-history-kind ' + (it.entry_type || 'note');
        k.textContent = kindLabel + (it.quantity && it.quantity > 1 ? ' ×' + it.quantity : '');
        const t = document.createElement('span');
        t.className = 'pl-history-time';
        t.textContent = it.occurred_label || '';
        top.append(k, t);
        div.appendChild(top);
        if (it.note) {
            const main = document.createElement('div');
            main.className = 'pl-history-main';
            main.textContent = it.note;
            div.appendChild(main);
        }
        const meta = document.createElement('div');
        meta.className = 'pl-history-meta';
        [['trigger', it.trigger], ['location', it.location], ['mood', it.mood ? it.mood + '/10' : null], ['step', it.step]].forEach(([label, val]) => {
            if (!val) return;
            const s = document.createElement('span');
            s.textContent = val;
            s.title = label;
            meta.appendChild(s);
        });
        if (meta.childElementCount) div.appendChild(meta);
        return div;
    }

    async function plUndoDelete(type, id) {
        const url = type === 'task'
            ? '{{ url('tasks') }}/' + id
            : '{{ url('routines') }}/' + id;
        try {
            await plFetch(url, { method: 'DELETE', headers: { 'Accept': 'application/json' } });
        } catch (e) { /* best effort */ }
        document.querySelectorAll('[data-' + type + '-item][data-id="' + id + '"]').forEach(row => row.remove());
        if (type === 'task') refreshCounters(); else refreshRoutineCounters();
    }

    /* Take an existing task back out of this day (keeps the task itself). */
    async function plUnscheduleTask(id) {
        try {
            const res = await plFetch(POSTPONE_URL(id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ action: 'clear' }),
            });
            if (!res.ok) { throw new Error('HTTP ' + res.status); }
        } catch (e) {
            console.error('[Planner] unschedule failed', e);
            plShowToast(@json(__('Could not undo.')));
            return;
        }
        document.querySelectorAll('[data-task-item][data-id="' + id + '"]').forEach(row => row.remove());
        refreshCounters();
    }

    /* ── Quick add ── */
    const plQa = document.getElementById('plQuickAdd');

    const QA_I18N = {
        needTitle: @json(__('Please write a title first')),
        added: @json(__('Added (:count)')),
        genericError: @json(__('Something went wrong. Please try again.')),
    };

    function openQuickAdd(tab) {
        if (!plQa) return;
        plQa.hidden = false;
        document.body.classList.add('pl-modal-open');
        switchQuickTab(tab || 'task');
        const panel = plQa.querySelector('[data-qa-panel="' + (tab || 'task') + '"]');
        if (panel) {
            qaHideError(panel);
            qaSyncSubmit(panel);
            setTimeout(() => { const inp = panel.querySelector('[name="title"]'); if (inp) inp.focus(); }, 40);
        }
    }

    function closeQuickAdd() {
        if (!plQa) return;
        plQa.hidden = true;
        document.body.classList.remove('pl-modal-open');
        /* Fresh start next time: clear session lists + errors. */
        plQa.querySelectorAll('[data-qa-added]').forEach(box => {
            box.hidden = true;
            const list = box.querySelector('[data-qa-added-list]');
            if (list) list.replaceChildren();
        });
        plQa.querySelectorAll('[data-qa-error]').forEach(e => { e.hidden = true; });
    }

    function switchQuickTab(tab) {
        if (!plQa) return;
        plQa.querySelectorAll('[data-qa-tab]').forEach(b => {
            const on = b.dataset.qaTab === tab;
            b.classList.toggle('active', on);
            if (on && b.dataset.qaTitle) {
                const title = document.getElementById('plQaTitle');
                if (title) title.textContent = b.dataset.qaTitle;
            }
        });
        plQa.querySelectorAll('[data-qa-panel]').forEach(p => { p.hidden = p.dataset.qaPanel !== tab; });
    }

    /* Live validation: submit stays disabled until a title is typed. */
    function qaSyncSubmit(panel) {
        const title = panel.querySelector('[name="title"]');
        const submit = panel.querySelector('[data-qa-submit]');
        if (submit) submit.disabled = !title || !title.value.trim();
    }

    function qaShowError(panel, message) {
        const box = panel.querySelector('[data-qa-error]');
        if (!box) return;
        box.textContent = message || QA_I18N.genericError;
        box.hidden = false;
    }

    function qaHideError(panel) {
        const box = panel.querySelector('[data-qa-error]');
        if (box) box.hidden = true;
    }

    /* Restore pills/chips to match hidden inputs (after a full form.reset()). */
    function qaSyncControls(form) {
        const seg = form.querySelector('[data-qa-seg]');
        if (seg) {
            const input = form.querySelector('[name="priority"]');
            seg.querySelectorAll('button').forEach(b => b.classList.toggle('active', !!input && b.dataset.val === input.value));
        }
        const period = form.querySelector('[data-qa-period]');
        if (period) {
            const input = form.querySelector('[name="time_period"]');
            period.querySelectorAll('button').forEach(b => b.classList.toggle('active', !!input && (b.dataset.val || '') === (input.value || '')));
        }
        const mins = form.querySelector('[data-qa-minutes]');
        if (mins) {
            const input = form.querySelector('[name="estimated_minutes"]');
            mins.querySelectorAll('button').forEach(b => b.classList.toggle('active', !!input && input.value !== '' && b.dataset.min === input.value));
        }
        qaSyncSubmit(form);
    }

    /* "Added in this session" list inside the modal. */
    function qaTrackAdded(panel, title) {
        const box = panel.querySelector('[data-qa-added]');
        if (!box) return;
        const list = box.querySelector('[data-qa-added-list]');
        const head = box.querySelector('[data-qa-added-count]');
        const li = document.createElement('li');
        const icon = document.createElement('i');
        icon.className = 'bi bi-check-lg';
        li.appendChild(icon);
        li.appendChild(document.createTextNode(title));
        list.prepend(li);
        while (list.children.length > 5) list.lastElementChild.remove();
        box.hidden = false;
        if (head) head.textContent = QA_I18N.added.replace(':count', plNum(list.children.length));
    }

    function qaSetLoading(form, loading) {
        form.querySelectorAll('[data-qa-submit], [data-qa-add-new]').forEach(b => { b.disabled = loading; });
        const submit = form.querySelector('[data-qa-submit]');
        if (submit) submit.classList.toggle('is-loading', loading);
    }

    if (plQa) {
        plQa.querySelectorAll('[data-qa-close]').forEach(b => b.addEventListener('click', closeQuickAdd));
        plQa.querySelectorAll('[data-qa-tab]').forEach(b => b.addEventListener('click', () => switchQuickTab(b.dataset.qaTab)));
        plQa.addEventListener('input', e => {
            const panel = e.target.closest('[data-qa-panel]');
            if (panel && e.target.name === 'title') { qaSyncSubmit(panel); qaHideError(panel); }
        });
        /* Priority segmented pills. */
        plQa.querySelectorAll('[data-qa-seg]').forEach(seg => {
            seg.addEventListener('click', e => {
                const b = e.target.closest('button');
                if (!b) return;
                const form = seg.closest('[data-qa-panel]');
                const input = form.querySelector('[name="priority"]');
                if (input) input.value = b.dataset.val;
                seg.querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
            });
        });
        /* Time-of-day chips: single select, clicking the active one resets to Anytime. */
        plQa.querySelectorAll('[data-qa-period]').forEach(wrap => {
            wrap.addEventListener('click', e => {
                const b = e.target.closest('button');
                if (!b) return;
                const form = wrap.closest('[data-qa-panel]');
                const input = form.querySelector('[name="time_period"]');
                const wasActive = b.classList.contains('active');
                wrap.querySelectorAll('button').forEach(x => x.classList.remove('active'));
                if (wasActive && (b.dataset.val || '') !== '') {
                    if (input) input.value = '';
                    const anytime = wrap.querySelector('button[data-val=""]');
                    if (anytime) anytime.classList.add('active');
                } else {
                    if (input) input.value = b.dataset.val || '';
                    b.classList.add('active');
                }
            });
        });
        plQa.querySelectorAll('[data-qa-minutes] button').forEach(b => {
            b.addEventListener('click', () => {
                const form = b.closest('[data-qa-panel]');
                const input = form.querySelector('[name="estimated_minutes"]');
                const wasActive = b.classList.contains('active');
                plQa.querySelectorAll('[data-qa-minutes] button').forEach(x => x.classList.remove('active'));
                input.value = wasActive ? '' : b.dataset.min;
                if (!wasActive) b.classList.add('active');
            });
        });
        plQa.querySelectorAll('[data-qa-add-new]').forEach(b => {
            b.addEventListener('click', () => {
                const form = b.closest('[data-qa-panel]');
                if (!form) return;
                if (b.dataset.qaAddNew === 'routine') submitQuickRoutine(form, true);
                else submitQuickTask(form, true);
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !plQa.hidden) closeQuickAdd();
        });
    }

    function plInsertRow(containerId, html) {
        const body = document.getElementById(containerId);
        if (!body) return;
        const empty = body.querySelector('.pl-empty');
        if (empty) empty.remove();
        body.insertAdjacentHTML('afterbegin', (html || '').trim());
    }

    async function submitQuickTask(form, keepOpen) {
        const titleInput = form.querySelector('[name="title"]');
        const title = titleInput.value.trim();
        if (!title) { qaShowError(form, QA_I18N.needTitle); titleInput.focus(); return false; }
        qaHideError(form);
        qaSetLoading(form, true);
        try {
            const res = await plFetch(form.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    title: title,
                    date: form.querySelector('[name="date"]').value,
                    priority: form.querySelector('[name="priority"]').value,
                    project_id: form.querySelector('[name="project_id"]').value || null,
                    time_period: form.querySelector('[name="time_period"]').value || null,
                    due_time: form.querySelector('[name="due_time"]').value || null,
                    estimated_minutes: form.querySelector('[name="estimated_minutes"]').value || null,
                }),
            });
            if (!res.ok) {
                let message = QA_I18N.genericError;
                try { const err = await res.json(); if (err && err.message) message = err.message; } catch (_) { /* keep generic */ }
                throw new Error(message);
            }
            const json = await res.json();
            const gb = window.plEnsureGroup ? window.plEnsureGroup(json.group || 'anytime') : null;
            let addedEl = null;
            if (gb) {
                const wrap = document.createElement('div');
                wrap.innerHTML = json.html.trim();
                addedEl = wrap.firstElementChild;
                gb.appendChild(addedEl);
            } else {
                plInsertRow('plPendingBody', json.html);
            }
            refreshCounters();
            if (window.plRefreshDayProgress) window.plRefreshDayProgress();
            if (keepOpen) {
                /* Stack mode: clear only the title + estimate, keep prefs, track + spotlight. */
                titleInput.value = '';
                const est = form.querySelector('[name="estimated_minutes"]');
                if (est) est.value = '';
                form.querySelectorAll('[data-qa-minutes] button').forEach(x => x.classList.remove('active'));
                qaTrackAdded(form, title);
                qaSyncSubmit(form);
                titleInput.focus();
                if (addedEl) {
                    addedEl.classList.add('pl-flash');
                    setTimeout(() => addedEl.classList.remove('pl-flash'), 1700);
                }
            } else {
                closeQuickAdd();
                form.reset();
                qaSyncControls(form);
                plShowToast(@json(__('Task added')), () => plUndoDelete('task', json.task.id));
            }
            refreshNextUp();
        } catch (e) {
            console.error('[Planner] quick add task failed', e);
            qaShowError(form, e.message && !String(e.message).startsWith('HTTP ') ? e.message : QA_I18N.genericError);
        } finally {
            qaSetLoading(form, false);
            qaSyncSubmit(form);
        }
        return false;
    }

    /* ── Backlog picker modal: pull existing unscheduled tasks into this day.
       Same endpoints the week view uses (backlog + schedule), multi-select. ── */
    const plBacklogModal = document.getElementById('plBacklogModal');
    const BL_I18N = {
        selectAll: @json(__('Select all')),
        deselectAll: @json(__('Deselect all')),
        addToDay: @json(__('Add (:count) to day')),
        moved: @json(__(':count tasks moved to this day')),
        noMatch: @json(__('No matching task in your inbox.')),
        inboxEmpty: @json(__('Your inbox is empty — every task has a date.')),
        loadFail: @json(__('Could not load your inbox.')),
        scheduleFail: @json(__('Could not schedule the task.')),
        loading: @json(__('Loading…')),
    };

    function openBacklogModal() {
        if (!plBacklogModal) return;
        blSelected.clear();
        const search = document.getElementById('plBacklogSearch');
        if (search) search.value = '';
        blSetPrio('');
        plBacklogModal.hidden = false;
        document.body.classList.add('pl-modal-open');
        blLoad('');
        setTimeout(() => { if (search) search.focus(); }, 40);
    }

    function closeBacklogModal() {
        if (!plBacklogModal) return;
        plBacklogModal.hidden = true;
        document.body.classList.remove('pl-modal-open');
    }

    const blSelected = new Set();
    let blTasks = [];
    let blBusy = false;
    const BL_PRIO = { high: '#dc2626', medium: '#d97706', low: '#16a34a' };

    function blDay() {
        return (plBacklogModal && plBacklogModal.dataset.date) || '';
    }

    function blNote(text, icon) {
        const list = document.getElementById('plBacklogList');
        if (!list) return;
        list.replaceChildren();
        const div = document.createElement('div');
        div.className = 'pl-qadd-note';
        const i = document.createElement('i');
        i.className = 'bi ' + (icon || 'bi-inbox');
        div.appendChild(i);
        div.appendChild(document.createTextNode(text));
        list.appendChild(div);
    }

    function blVisibleTasks() {
        const active = document.querySelector('[data-bl-filters] button.active');
        const prio = active ? (active.dataset.prio || '') : '';
        return prio ? blTasks.filter(t => (t.priority || '') === prio) : blTasks.slice();
    }

    function blRender() {
        const list = document.getElementById('plBacklogList');
        if (!list) return;
        const search = document.getElementById('plBacklogSearch');
        const q = search ? search.value.trim() : '';
        const tasks = blVisibleTasks();
        /* Drop selections for tasks that are no longer listed. */
        const ids = new Set(tasks.map(t => t.id));
        [...blSelected].forEach(id => { if (!ids.has(id)) blSelected.delete(id); });
        list.replaceChildren();
        if (!tasks.length) {
            blNote(q ? BL_I18N.noMatch : BL_I18N.inboxEmpty, 'bi-check2-circle');
        } else {
            tasks.forEach(t => {
                const row = document.createElement('label');
                row.className = 'pl-qadd-item' + (blSelected.has(t.id) ? ' selected' : '');
                row.setAttribute('role', 'option');
                row.setAttribute('aria-selected', blSelected.has(t.id) ? 'true' : 'false');
                const check = document.createElement('input');
                check.type = 'checkbox';
                check.className = 'pl-qadd-check';
                check.checked = blSelected.has(t.id);
                check.setAttribute('aria-label', t.title);
                check.addEventListener('change', () => {
                    if (check.checked) blSelected.add(t.id); else blSelected.delete(t.id);
                    row.classList.toggle('selected', check.checked);
                    row.setAttribute('aria-selected', check.checked ? 'true' : 'false');
                    blSyncFoot();
                });
                const dot = document.createElement('span');
                dot.className = 'pl-qadd-dot';
                dot.style.background = BL_PRIO[t.priority] || '#94a3b8';
                const name = document.createElement('span');
                name.className = 'pl-qadd-name';
                name.textContent = t.title;
                row.appendChild(check);
                row.appendChild(dot);
                row.appendChild(name);
                const meta = [t.project, t.due_label].filter(Boolean).join(' · ');
                if (meta) {
                    const proj = document.createElement('span');
                    proj.className = 'pl-qadd-proj';
                    proj.textContent = meta;
                    if (t.is_overdue) proj.style.color = '#dc2626';
                    row.appendChild(proj);
                }
                list.appendChild(row);
            });
        }
        blSyncFoot();
    }

    function blSyncFoot() {
        const addBtn = document.querySelector('[data-bl-add]');
        const addLabel = document.querySelector('[data-bl-add-label]');
        const selectBtn = document.querySelector('[data-bl-select-all]');
        const selectLabel = document.querySelector('[data-bl-select-all-label]');
        const n = blSelected.size;
        if (addLabel) addLabel.textContent = BL_I18N.addToDay.replace(':count', plNum(n));
        if (addBtn) {
            addBtn.disabled = blBusy || n === 0;
            addBtn.classList.toggle('is-loading', blBusy);
        }
        const tasks = blVisibleTasks();
        const allOn = tasks.length > 0 && tasks.every(t => blSelected.has(t.id));
        if (selectLabel) selectLabel.textContent = allOn ? BL_I18N.deselectAll : BL_I18N.selectAll;
        if (selectBtn) selectBtn.disabled = blBusy || tasks.length === 0;
    }

    function blSetPrio(prio) {
        document.querySelectorAll('[data-bl-filters] button').forEach(b =>
            b.classList.toggle('active', (b.dataset.prio || '') === (prio || '')));
    }

    function blParams(q) {
        const p = new URLSearchParams();
        if (q) p.set('q', q);
        const proj = document.getElementById('plBacklogProject');
        if (proj && proj.value) p.set('project_id', proj.value);
        if (blDay()) p.set('date', blDay());
        const s = p.toString();

        return s ? '?' + s : '';
    }

    async function blLoad(q) {
        const clear = document.getElementById('plBacklogClear');
        if (clear) clear.hidden = !q;
        blNote(BL_I18N.loading, 'bi-arrow-repeat');
        try {
            const res = await plFetch(@json(route('planner.backlog')) + blParams(q), {
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            blTasks = json.tasks || [];
            blRender();
        } catch (e) {
            console.error('[Planner] backlog load failed', e);
            blNote(BL_I18N.loadFail, 'bi-exclamation-triangle');
        }
    }

    async function blAddSelected() {
        const ids = [...blSelected];
        if (!ids.length || blBusy) return;
        blBusy = true;
        blSyncFoot();
        const SCHEDULE_URL = id => `{{ route('planner.tasks.schedule', ['task' => '__ID__']) }}`.replace('__ID__', id);
        let ok = 0;
        for (const id of ids) {
            try {
                const res = await plFetch(SCHEDULE_URL(id), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ due_date: blDay(), view: 'day' }),
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                const gb = window.plEnsureGroup ? window.plEnsureGroup(json.group || 'anytime') : null;
                let addedEl = null;
                if (gb) {
                    const wrap = document.createElement('div');
                    wrap.innerHTML = json.html.trim();
                    addedEl = wrap.firstElementChild;
                    gb.appendChild(addedEl);
                } else {
                    plInsertRow('plPendingBody', json.html);
                }
                if (addedEl) {
                    addedEl.classList.add('pl-flash');
                    setTimeout(() => addedEl.classList.remove('pl-flash'), 1700);
                }
                blTasks = blTasks.filter(t => t.id !== id);
                blSelected.delete(id);
                ok++;
                blRender();
            } catch (e) {
                console.error('[Planner] schedule failed', e);
            }
        }
        blBusy = false;
        blRender();
        refreshCounters();
        if (window.plRefreshDayProgress) window.plRefreshDayProgress();
        if (window.refreshNextUp) window.refreshNextUp();
        plShowToast(ok ? BL_I18N.moved.replace(':count', plNum(ok)) : BL_I18N.scheduleFail);
    }

    (function blWire() {
        if (!plBacklogModal) return;
        let searchT = null;
        const search = document.getElementById('plBacklogSearch');
        const clear = document.getElementById('plBacklogClear');
        plBacklogModal.querySelectorAll('[data-bl-close]').forEach(b => b.addEventListener('click', closeBacklogModal));
        if (clear) clear.addEventListener('click', () => { search.value = ''; search.focus(); blLoad(''); });
        if (search) search.addEventListener('input', () => {
            clearTimeout(searchT);
            const q = search.value.trim();
            searchT = setTimeout(() => blLoad(q), 250);
        });
        const projSel = document.getElementById('plBacklogProject');
        if (projSel) projSel.addEventListener('change', () => {
            blLoad(search ? search.value.trim() : '');
            if (search) search.focus();
        });
        document.querySelectorAll('[data-bl-filters] button').forEach(b =>
            b.addEventListener('click', () => { blSetPrio(b.dataset.prio || ''); blRender(); if (search) search.focus(); }));
        const selectAll = document.querySelector('[data-bl-select-all]');
        if (selectAll) selectAll.addEventListener('click', () => {
            const tasks = blVisibleTasks();
            const allOn = tasks.length > 0 && tasks.every(t => blSelected.has(t.id));
            tasks.forEach(t => { if (allOn) blSelected.delete(t.id); else blSelected.add(t.id); });
            blRender();
        });
        const addBtn = document.querySelector('[data-bl-add]');
        if (addBtn) addBtn.addEventListener('click', blAddSelected);
        document.addEventListener('keydown', ev => {
            if (ev.key === 'Escape' && plBacklogModal && !plBacklogModal.hidden) closeBacklogModal();
        });
    })();

    async function submitQuickRoutine(form, keepOpen) {
        const titleInput = form.querySelector('[name="title"]');
        const title = titleInput.value.trim();
        if (!title) { qaShowError(form, QA_I18N.needTitle); titleInput.focus(); return false; }
        qaHideError(form);
        qaSetLoading(form, true);
        try {
            const res = await plFetch(form.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    title: title,
                    date: form.querySelector('[name="date"]').value,
                    frequency: form.querySelector('[name="frequency"]').value,
                    time_period: form.querySelector('[name="time_period"]').value || null,
                }),
            });
            if (!res.ok) {
                let message = QA_I18N.genericError;
                try { const err = await res.json(); if (err && err.message) message = err.message; } catch (_) { /* keep generic */ }
                throw new Error(message);
            }
            const json = await res.json();
            plInsertRow('plRoutinesBody', json.html);
            refreshRoutineCounters();
            if (window.plRefreshDayProgress) window.plRefreshDayProgress();
            if (keepOpen) {
                titleInput.value = '';
                qaTrackAdded(form, title);
                qaSyncSubmit(form);
                titleInput.focus();
            } else {
                closeQuickAdd();
                form.reset();
                qaSyncControls(form);
                plShowToast(@json(__('Routine added')), () => plUndoDelete('routine', json.routine.id));
            }
            refreshNextUp();
        } catch (e) {
            console.error('[Planner] quick add routine failed', e);
            qaShowError(form, e.message && !String(e.message).startsWith('HTTP ') ? e.message : QA_I18N.genericError);
        } finally {
            qaSetLoading(form, false);
            qaSyncSubmit(form);
        }
        return false;
    }

    /* ── Next Up ── */
    function plNextCard() {
        const wrap = document.getElementById('plNextUp');
        return wrap ? wrap.querySelector('.pl-next-card') : null;
    }

    /* Reflect a single step tick on the matching list row (task or routine). */
    function setTaskRowStepProgress(ownerId, done, total, ownerCompleted, kind = 'task') {
        const sel = kind === 'task' ? '[data-task-item][data-id="' + ownerId + '"]' : '[data-routine-item][data-id="' + ownerId + '"]';
        document.querySelectorAll(sel).forEach(row => {
            const badge = row.querySelector('.steps-count');
            if (badge) {
                badge.textContent = plNum(done) + '/' + plNum(total);
                badge.classList.toggle('all', total > 0 && done === total);
            }
            if (kind === 'routine' && ownerCompleted !== undefined) {
                row.classList.toggle('is-done', !!ownerCompleted);
                row.dataset.completed = ownerCompleted ? '1' : '0';
            }
        });
        if (kind === 'task') refreshCounters(); else refreshRoutineCounters();
    }

    /* ── Quick Action Mini-Modal & Next Up Handlers ── */
    const plNextUpModal = document.getElementById('plNextUpModal');
    let plNextUpActiveData = null;

    function handleNextUpCardClick(e) {
        if (e.target.closest('button, a, input, select, label')) return;
        const wrap = document.getElementById('plNextUp');
        const card = wrap ? wrap.querySelector('.pl-next-card') : null;
        if (!card) return;
        if (card.dataset.needsModal === '1') {
            openNextUpModal();
        } else {
            plCompleteNext();
        }
    }

    function handleNextUpClick() {
        const wrap = document.getElementById('plNextUp');
        const card = wrap ? wrap.querySelector('.pl-next-card') : null;
        if (!card) return;
        if (card.dataset.needsModal === '1') {
            openNextUpModal();
        } else {
            plCompleteNext();
        }
    }

    function openNextUpModal() {
        const wrap = document.getElementById('plNextUp');
        const card = wrap ? wrap.querySelector('.pl-next-card') : null;
        if (!card || !plNextUpModal) return;

        const d = card.dataset;
        plNextUpActiveData = {
            type: d.nextType,
            id: d.nextId,
            title: d.nextTitle,
            url: d.nextUrl,
            hasSteps: d.hasSteps === '1',
            stepsDone: parseInt(d.stepsDone || '0'),
            stepsTotal: parseInt(d.stepsTotal || '0'),
            stepId: d.stepId,
            stepName: d.stepName,
            stepUrl: d.stepUrl,
            slipUrl: d.slipUrl,
            isAvoid: d.isAvoid === '1',
            trackMode: d.trackMode,
            logUrl: d.logUrl,
            valueKind: d.valueKind,
            valueUnit: d.valueUnit,
            valueLabel: d.valueLabel,
            targetSets: parseInt(d.targetSets || '1'),
            loggedSets: d.loggedSets ? JSON.parse(d.loggedSets) : {},
            allSteps: d.allSteps ? JSON.parse(d.allSteps) : [],
            date: wrap.dataset.nextDate
        };

        // Header & Hero Chip setup
        document.getElementById('plNextUpHeroTitle').textContent = plNextUpActiveData.title;
        const heroIcon = document.getElementById('plNextUpHeroIcon');
        heroIcon.className = 'bi fs-5 ' + (plNextUpActiveData.isAvoid ? 'bi-shield-exclamation text-danger' : (plNextUpActiveData.type === 'task' ? 'bi-check2-square text-primary' : 'bi-arrow-repeat text-purple'));
        
        // Modal sub text
        document.getElementById('plNextUpModalSub').textContent = plNextUpActiveData.type === 'task' ? '{{ __('Task') }}' : '{{ __('Routine') }}';

        // Reset panels
        ['plModalPanelTime', 'plModalPanelValue', 'plModalPanelSets', 'plModalPanelAvoid', 'plModalPanelSteps'].forEach(id => {
            const p = document.getElementById(id);
            if (p) p.style.display = 'none';
        });

        const btnText = document.getElementById('plModalBtnText');
        const btnIcon = document.getElementById('plModalBtnIcon');

        // Panel activation
        if (plNextUpActiveData.trackMode === 'value') {
            if (plNextUpActiveData.valueKind === 'time') {
                const panel = document.getElementById('plModalPanelTime');
                panel.style.display = 'block';
                document.getElementById('plModalTimeLabel').textContent = plNextUpActiveData.valueLabel || '{{ __('Wake-up Time') }}';
                const timeInp = document.getElementById('plModalTimeInput');
                if (window.TimePicker) { window.TimePicker.setNow('plModalTime'); }
                else if (timeInp) { timeInp.value = formatNowTime(); }
                if (btnText) btnText.textContent = '{{ __('Log Time') }}';
            } else {
                const panel = document.getElementById('plModalPanelValue');
                panel.style.display = 'block';
                document.getElementById('plModalValueLabel').textContent = plNextUpActiveData.valueLabel || '{{ __('Value') }}';
                document.getElementById('plModalValueUnit').textContent = plNextUpActiveData.valueUnit || '';
                document.getElementById('plModalValueInput').value = '1';
                if (btnText) btnText.textContent = '{{ __('Log Value') }}';
            }
        } else if (plNextUpActiveData.trackMode === 'sets') {
            const panel = document.getElementById('plModalPanelSets');
            panel.style.display = 'block';
            document.getElementById('plModalSetsStepName').textContent = plNextUpActiveData.stepName ? (plNextUpActiveData.stepName + ' • ' + '{{ __('Sets') }}') : '{{ __('Sets') }}';
            
            const grid = document.getElementById('plModalSetsGrid');
            grid.innerHTML = '';
            for (let s = 1; s <= plNextUpActiveData.targetSets; s++) {
                const loggedVal = plNextUpActiveData.loggedSets[s] || '';
                const row = document.createElement('div');
                row.className = 'd-flex align-items-center gap-2 bg-light p-2 rounded-3 border';
                row.innerHTML = `
                    <span class="badge bg-purple text-white px-2 py-1">${'{{ __('Set') }} ' + s}</span>
                    <input type="number" min="0" step="any" class="form-control form-control-sm text-center fw-bold" 
                           data-modal-set="${s}" value="${loggedVal}" placeholder="${plNextUpActiveData.valueUnit || '0'}">
                    <span class="small text-muted fw-semibold">${plNextUpActiveData.valueUnit || ''}</span>
                `;
                grid.appendChild(row);
            }
            if (btnText) btnText.textContent = '{{ __('Log Sets') }}';
        } else if (plNextUpActiveData.isAvoid) {
            const panel = document.getElementById('plModalPanelAvoid');
            panel.style.display = 'block';
            document.getElementById('plModalAvoidNote').value = '';
            if (btnText) btnText.textContent = '{{ __('Log Slip') }}';
        } else if (plNextUpActiveData.allSteps && plNextUpActiveData.allSteps.length > 0) {
            const panel = document.getElementById('plModalPanelSteps');
            panel.style.display = 'block';
            const list = document.getElementById('plModalStepsList');
            list.innerHTML = '';
            plNextUpActiveData.allSteps.forEach((st, idx) => {
                const stepRow = document.createElement('div');
                stepRow.className = 'd-flex align-items-center justify-content-between p-2 rounded-3 border ' + (st.completed ? 'bg-success-subtle border-success-subtle' : 'bg-white');
                stepRow.innerHTML = `
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi ${st.completed ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted'} fs-6"></i>
                        <span class="small fw-bold ${st.completed ? 'text-decoration-line-through text-muted' : 'text-dark'}">${st.name}</span>
                    </div>
                    ${st.period_label ? `<span class="badge bg-light text-muted border small">${st.period_label}</span>` : ''}
                `;
                list.appendChild(stepRow);
            });
            if (btnText) btnText.textContent = '{{ __('Complete step') }}';
        } else {
            if (btnText) btnText.textContent = '{{ __('Save & Complete') }}';
        }

        plNextUpModal.hidden = false;
        document.body.classList.add('pl-modal-open');

        setTimeout(() => {
            const firstInput = plNextUpModal.querySelector('input:not([type="hidden"]):not([disabled])');
            if (firstInput) firstInput.focus();
        }, 80);
    }

    function closeNextUpModal() {
        if (!plNextUpModal) return;
        plNextUpModal.hidden = true;
        document.body.classList.remove('pl-modal-open');
        plNextUpActiveData = null;
    }

    function formatNowTime() {
        const now = new Date();
        return String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
    }

    function plSetTimePreset(val) {
        if (!window.TimePicker) return;
        if (val === 'now') window.TimePicker.setNow('plModalTime');
        else window.TimePicker.setValue('plModalTime', val);
    }

    function plAdjustTimeMinutes(delta) {
        if (!window.TimePicker) return;
        window.TimePicker.adjust('plModalTime', delta);
    }

    function plAdjustValue(delta) {
        const inp = document.getElementById('plModalValueInput');
        if (!inp) return;
        let cur = parseFloat(inp.value) || 0;
        cur = Math.max(0, cur + delta);
        inp.value = cur;
    }

    function plFillAllSetsFromFirst() {
        const first = plNextUpModal.querySelector('[data-modal-set="1"]');
        if (!first || first.value === '') return;
        const val = first.value;
        plNextUpModal.querySelectorAll('[data-modal-set]').forEach(inp => {
            inp.value = val;
        });
    }

    async function plSubmitNextUpModal() {
        if (!plNextUpActiveData) return;
        const spinner = document.getElementById('plModalSpinner');
        const submitBtn = document.getElementById('plModalSubmitBtn');
        if (spinner) spinner.classList.remove('d-none');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const d = plNextUpActiveData;
            if (d.trackMode === 'value') {
                let val = null;
                if (d.valueKind === 'time') {
                    const timeInp = document.getElementById('plModalTimeInput');
                    if (timeInp && timeInp.value) {
                        val = timeInp.value.split(':').reduce((a, p) => a * 60 + Number(p), 0);
                    }
                } else {
                    const numInp = document.getElementById('plModalValueInput');
                    if (numInp) val = parseFloat(numInp.value);
                }
                if (val !== null && !isNaN(val)) {
                    const res = await plFetch(d.logUrl + '?date=' + encodeURIComponent(d.date), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ value: val }),
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const logJson = await res.json();
                    if (logJson.routine_completed) {
                        applyRoutineToggle(d.id, d.date, true);
                        if (logJson.streak != null) setStreak(d.id, logJson.streak);
                        refreshRoutineCounters();
                        maybeCelebrate();
                    }
                }
            } else if (d.trackMode === 'sets') {
                const sets = {};
                plNextUpModal.querySelectorAll('[data-modal-set]').forEach(inp => {
                    if (inp.value !== '' && !isNaN(parseFloat(inp.value))) sets[inp.dataset.modalSet] = parseFloat(inp.value);
                });
                const res = await plFetch(d.logUrl + '?date=' + encodeURIComponent(d.date), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ item_id: d.stepId, sets: sets }),
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const logJson = await res.json();
                if (logJson.steps_done) {
                    const ids = Object.keys(logJson.steps_done);
                    const doneCount = ids.filter(k => logJson.steps_done[k]).length;
                    ids.forEach(itemId => setStepChip(itemId, d.date, logJson.steps_done[itemId], true));
                    setTaskRowStepProgress(d.id, doneCount, ids.length, logJson.routine_completed, 'routine');
                }
                if (logJson.routine_completed) {
                    applyRoutineToggle(d.id, d.date, true);
                    refreshRoutineCounters();
                    maybeCelebrate();
                }
            } else if (d.isAvoid) {
                const noteVal = document.getElementById('plModalAvoidNote')?.value || '';
                const slipUrl = d.slipUrl;
                if (slipUrl) {
                    const res = await plFetch(slipUrl + '?date=' + encodeURIComponent(d.date), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ note: noteVal }),
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                }
            } else if (d.hasSteps && d.stepId) {
                const res = await plFetch(d.stepUrl + '?date=' + encodeURIComponent(d.date), {
                    method: 'POST', headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                if (d.type === 'task') {
                    setTaskRowStepProgress(json.task_id, json.steps_done, json.steps_total, json.completed);
                } else {
                    setStepChip(json.item_id, d.date, json.completed);
                    refreshStepCounts();
                    if (json.routine_completed !== undefined) {
                        applyRoutineToggle(json.routine_id, d.date, !!json.routine_completed);
                        if (json.routine_completed && json.streak != null) setStreak(json.routine_id, json.streak);
                        refreshRoutineCounters();
                        if (json.routine_completed) maybeCelebrate();
                    }
                }
            } else {
                await plCompleteNext();
            }

            closeNextUpModal();
            await refreshNextUp();
        } catch (err) {
            console.error('[Planner] submit next up modal failed', err);
        } finally {
            if (spinner) spinner.classList.add('d-none');
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    async function plCompleteNext() {
        const wrap = document.getElementById('plNextUp');
        const card = wrap ? wrap.querySelector('.pl-next-card') : null;
        if (!card) return;
        const type = card.dataset.nextType;
        const id = card.dataset.nextId;
        const url = card.dataset.nextUrl;
        const date = wrap.dataset.nextDate;
        const stepId = card.dataset.stepId;
        const stepUrl = card.dataset.stepUrl;

        try {
            if (type === 'task') {
                if (stepId && stepUrl) {
                    const res = await plFetch(stepUrl, { method: 'POST', headers: { 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const json = await res.json();
                    setTaskRowStepProgress(json.task_id, json.steps_done, json.steps_total, json.completed);
                } else {
                    const res = await plFetch(url, { method: 'POST', headers: { 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const json = await res.json();
                    document.querySelectorAll('[data-task-item][data-id="' + id + '"]').forEach(row => {
                        row.classList.toggle('is-done', !!json.completed);
                        row.dataset.completed = json.completed ? '1' : '0';
                    });
                    refreshCounters();
                }
            } else if (stepId && stepUrl) {
                const res = await plFetch(stepUrl + '?date=' + encodeURIComponent(date), {
                    method: 'POST', headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                setStepChip(json.item_id, date, json.completed);
                refreshStepCounts();
                if (json.routine_completed !== undefined) {
                    applyRoutineToggle(json.routine_id, date, !!json.routine_completed);
                    if (json.routine_completed && json.streak != null) setStreak(json.routine_id, json.streak);
                    refreshRoutineCounters();
                    if (json.routine_completed) maybeCelebrate();
                }
            } else {
                const json = await routineToggleRequest(url, date);
                applyRoutineToggle(id, date, !!json.completed);
                if (json.items && json.items.length) syncStepButtons(id, date, !!json.completed);
                refreshRoutineCounters();
            }
            await refreshNextUp();
        } catch (e) {
            console.error('[Planner] next up complete failed', e);
        }
    }

    async function plStartNext() {
        const wrap = document.getElementById('plNextUp');
        const card = wrap ? wrap.querySelector('.pl-next-card') : null;
        if (!card || card.dataset.nextType !== 'task') return;
        const taskId = card.dataset.nextId;
        try {
            await TM.start({ task_id: taskId });
            plBarDismissedId = null;
        } catch (e) {
            console.error('[Planner] next up timer start failed', e);
        }
        try {
            await plFetch('{{ url('tasks') }}/' + taskId + '/update-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ status: 'in_progress' }),
            });
        } catch (e) {
            console.error('[Planner] next up status update failed', e);
        }
    }

    /* Modal Keyboard shortcuts: Enter submits, Escape closes */
    document.addEventListener('keydown', function (e) {
        if (plNextUpModal && !plNextUpModal.hidden) {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeNextUpModal();
            } else if (e.key === 'Enter' && !e.target.matches('textarea')) {
                e.preventDefault();
                plSubmitNextUpModal();
            }
        }
    });

    async function refreshNextUp() {
        const wrap = document.getElementById('plNextUp');
        if (!wrap) return;
        const date = wrap.dataset.nextDate;
        try {
            const res = await plFetch('{{ route('planner.next-up') }}?date=' + encodeURIComponent(date), {
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) return;
            const json = await res.json();
            wrap.outerHTML = json.html;
        } catch (e) {
            console.error('[Planner] next up refresh failed', e);
        }
    }
    window.refreshNextUp = refreshNextUp;
</script>
@endpush

@push('scripts')
<script>
/* ── My Day: period groups, drag & drop reordering, postpone actions ── */
(function () {
    const PL_PERIODS = @json(config('routines.periods', []));
    const PL_PERIOD_LABELS = @json(collect(config('routines.periods', []))->mapWithKeys(fn ($p, $k) => [$k => __($p['label'])])->all());
    const plPeriodLabel = key => key === 'anytime' ? PL_I18N_EXTRA.anytime : (PL_PERIOD_LABELS[key] || (PL_PERIODS[key]?.label || key));
    const POSTPONE_URL = id => `{{ route('planner.tasks.postpone', ['task' => '__ID__']) }}`.replace('__ID__', id);
    const REORDER_URL = '{{ route('planner.tasks.reorder') }}';
    const body = document.getElementById('plPendingBody');
    const periodOrder = key => key === 'anytime' ? 99 : (Number(PL_PERIODS[key]?.order) || 99);

    /* Always available so quick-add can insert into the right group. */
    window.plEnsureGroup = function (key) {
        if (!body) { return null; }
        let gb = body.querySelector(`[data-period-body="${key}"]`);
        if (gb) { return gb; }
        const def = key === 'anytime'
            ? { label: PL_I18N_EXTRA.anytime, icon: 'bi-inbox', color: '#64748b' }
            : (PL_PERIODS[key] ? { ...PL_PERIODS[key], label: plPeriodLabel(key) } : { label: key, icon: 'bi-clock', color: '#64748b' });
        const group = document.createElement('div');
        group.className = 'pl-period-group';
        group.dataset.periodGroup = key;
        group.innerHTML = `<div class="pl-period-head"><i class="bi ${def.icon}" style="color:${def.color};"></i><span>${def.label}</span></div><div class="pl-period-body" data-period-body="${key}"></div>`;
        const empty = body.querySelector(':scope > .pl-empty');
        if (empty) { empty.remove(); }
        const ref = [...body.querySelectorAll('[data-period-group]')].find(g => periodOrder(g.dataset.periodGroup) > periodOrder(key));
        ref ? body.insertBefore(group, ref) : body.appendChild(group);
        return group.querySelector('[data-period-body]');
    };

    function updateRowPeriodChip(row, key) {
        const meta = row.querySelector('.pl-task-meta');
        if (!meta) { return; }
        let chip = row.querySelector('[data-period-chip]');
        if (key === 'anytime') { chip?.remove(); return; }
        const def = PL_PERIODS[key] || {};
        if (!chip) {
            chip = document.createElement('span');
            chip.className = 'pl-priority';
            chip.dataset.periodChip = '';
            meta.appendChild(chip);
        }
        const color = def.color || '#64748b';
        chip.style.color = color;
        chip.style.background = color + '1a';
        chip.style.textTransform = 'none';
        chip.innerHTML = `<i class="bi ${def.icon || 'bi-clock'}"></i> ${plPeriodLabel(key)}`;
    }

    function groupItems(groupBody) {
        if (!groupBody) { return []; }
        return [...groupBody.querySelectorAll('.pl-task')].map((row, i) => ({
            id: Number(row.dataset.id),
            sort_order: i * 10,
            time_period: (row.dataset.period || 'anytime') === 'anytime' ? null : row.dataset.period,
        }));
    }

    async function persist(items) {
        if (!items.length) { return; }
        try {
            const res = await plFetch(REORDER_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ items }),
            });
            if (!res.ok) { throw new Error('HTTP ' + res.status); }
        } catch (e) {
            console.error('[Planner] reorder save failed', e);
        }
    }

    if (body) {
        let dragRow = null, dragSrcKey = 'anytime', dropRef = null;

        const clearMarks = () => {
            body.querySelectorAll('.drop-before,.drop-after,.drop-active')
                .forEach(el => el.classList.remove('drop-before', 'drop-after', 'drop-active'));
            dropRef = null;
        };

        body.addEventListener('dragstart', e => {
            const row = e.target.closest('.pl-task[draggable="true"]');
            if (!row) { return; }
            dragRow = row;
            dragSrcKey = row.dataset.period || 'anytime';
            row.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', String(row.dataset.id)); } catch (_) {}
        });

        body.addEventListener('dragend', () => {
            dragRow?.classList.remove('dragging');
            clearMarks();
            dragRow = null;
        });

        body.addEventListener('dragover', e => {
            if (!dragRow) { return; }
            const groupBody = e.target.closest('[data-period-body]');
            if (!groupBody) { return; }
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            body.querySelectorAll('.drop-before,.drop-after,.drop-active')
                .forEach(el => el.classList.remove('drop-before', 'drop-after', 'drop-active'));
            groupBody.classList.add('drop-active');
            const rows = [...groupBody.querySelectorAll('.pl-task:not(.dragging)')];
            const after = rows.find(r => r.getBoundingClientRect().top + r.getBoundingClientRect().height / 2 > e.clientY);
            dropRef = { groupBody, ref: after || null };
            after ? after.classList.add('drop-before')
                  : (rows.at(-1) ? rows.at(-1).classList.add('drop-after') : null);
        });

        body.addEventListener('drop', async e => {
            if (!dragRow || !dropRef) { return; }
            e.preventDefault();
            const { groupBody, ref } = dropRef;
            const targetKey = groupBody.dataset.periodBody;
            ref ? groupBody.insertBefore(dragRow, ref) : groupBody.appendChild(dragRow);
            updateRowPeriodChip(dragRow, targetKey);
            dragRow.dataset.period = targetKey;
            clearMarks();
            const items = groupItems(groupBody);
            if (targetKey !== dragSrcKey) {
                const srcBody = body.querySelector(`[data-period-body="${dragSrcKey}"]`);
                const src = groupItems(srcBody);
                src.forEach(i => items.push(i));
            }
            await persist(items);
            refreshCounters();
        });
    }

    /* Postpone / pull-to-today / remove-from-day buttons. */
    document.addEventListener('click', async e => {
        const btn = e.target.closest('[data-postpone],[data-clear-day]');
        if (!btn || btn.dataset.busy) { return; }
        btn.dataset.busy = '1';
        const id = Number(btn.dataset.id);
        const action = btn.hasAttribute('data-clear-day') ? 'clear' : btn.dataset.postpone;
        
        const rows = document.querySelectorAll(`[data-task-item][data-id="${id}"]`);
        rows.forEach(r => {
            r.style.transition = 'all 0.25s cubic-bezier(0.4, 0, 0.2, 1)';
            r.style.opacity = '0.4';
            r.style.transform = 'scale(0.98)';
        });

        try {
            const res = await plFetch(POSTPONE_URL(id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ action }),
            });
            if (!res.ok) { throw new Error('HTTP ' + res.status); }
            const json = await res.json();

            // Smoothly remove task rows
            rows.forEach(r => {
                const parentGroupBody = r.closest('[data-period-body]');
                r.remove();
                if (parentGroupBody && parentGroupBody.querySelectorAll('.pl-task').length === 0) {
                    const pGroup = parentGroupBody.closest('.pl-period-group');
                    if (pGroup) pGroup.remove();
                }
            });

            // If all tasks in pending body are gone, display empty message
            if (body && body.querySelectorAll('.pl-task').length === 0 && !body.querySelector('.pl-empty')) {
                const emptyEl = document.createElement('div');
                emptyEl.className = 'pl-empty';
                emptyEl.textContent = '{{ __("Nothing scheduled for this day. Enjoy!") }}';
                body.appendChild(emptyEl);
            }

            if (action === 'today' && json.row_html) {
                const wrap = document.createElement('div');
                wrap.innerHTML = json.row_html.trim();
                const gb = window.plEnsureGroup(json.group || 'anytime');
                gb?.appendChild(wrap.firstElementChild);
            }

            const overdueCount = document.getElementById('plOverdueCount');
            let overdueLeft = -1;
            if (overdueCount) {
                overdueLeft = document.querySelectorAll('#plOverdueBody [data-task-item]').length;
                overdueCount.textContent = plNum(overdueLeft);
                if (overdueLeft === 0) {
                    document.getElementById('plOverdueBody')?.closest('.pl-section')?.remove();
                    document.querySelector('.pl-stat.overdue')?.remove();
                } else {
                    const stat = document.getElementById('plOverdueStat');
                    if (stat) stat.textContent = plNum(overdueLeft);
                }
            }
            
            if (window.refreshCounters) window.refreshCounters();
            if (window.refreshNextUp) window.refreshNextUp();
            if (window.plRefreshDayProgress) window.plRefreshDayProgress();

            const msgs = { 
                tomorrow: '{{ __("Postponed to tomorrow") }}', 
                today: '{{ __("Pulled into today") }}', 
                clear: '{{ __("Removed from My Day") }}' 
            };
            if (msgs[action]) {
                plShowToast(msgs[action], () => {
                    plFetch(POSTPONE_URL(id), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ action: 'restore', due_date: json.previous_due_date ?? null }),
                    }).then(() => location.reload()).catch(() => {});
                });
            }
        } catch (err) {
            console.error('[Planner] postpone failed', err);
            rows.forEach(r => {
                r.style.opacity = '1';
                r.style.transform = 'none';
            });
            delete btn.dataset.busy;
            plShowToast('{{ __("Error performing action") }}');
        }
    });

    /* ── Day progress bar (tasks + routines combined) ── */
    window.plRefreshDayProgress = function () {
        const fill = document.getElementById('plDayProgressFill');
        if (!fill) { return; }
        let done = 0, total = 0;
        document.querySelectorAll('[data-task-item][data-count="1"], [data-routine-item][data-count="1"]').forEach(el => {
            total++;
            if (el.dataset.completed === '1') done++;
        });
        const pct = total > 0 ? Math.round(done / total * 100) : 0;
        fill.style.width = pct + '%';
        fill.classList.toggle('is-full', pct === 100 && total > 0);
        const lbl = document.getElementById('plDayProgressLabel');
        if (lbl) { lbl.textContent = total ? plNum(pct) + PL_I18N_EXTRA.pctSuffix : ''; lbl.title = PL_I18N_EXTRA.progressOf.replace('{done}', plNum(done)).replace('{total}', plNum(total)); }
    };
    window.plRefreshDayProgress();

    /* ── Postpone every overdue task in one click ── */
    const postponeAllBtn = document.getElementById('plPostponeAll');
    if (postponeAllBtn) {
        postponeAllBtn.addEventListener('click', async () => {
            postponeAllBtn.disabled = true;
            try {
                const res = await plFetch('{{ route('planner.tasks.postpone-all') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                });
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                location.reload();
            } catch (e) {
                console.error('[Planner] postpone all failed', e);
                postponeAllBtn.disabled = false;
            }
        });
    }

    /* ── Double-click to rename a task title inline ── */
    const TITLE_URL = id => `{{ route('planner.tasks.title', ['task' => '__ID__']) }}`.replace('__ID__', id);

    document.addEventListener('dblclick', e => {
        const titleEl = e.target.closest('.pl-task[data-task-item] .pl-task-title');
        if (!titleEl || titleEl.querySelector('input')) { return; }
        const row = titleEl.closest('[data-task-item]');
        const old = titleEl.textContent.trim();
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'pl-title-input';
        input.value = old;
        input.maxLength = 255;
        titleEl.textContent = '';
        titleEl.appendChild(input);
        input.focus();
        input.select();

        let settled = false;
        const finish = async (save) => {
            if (settled) { return; }
            settled = true;
            const val = input.value.trim();
            if (save && val && val !== old) {
                try {
                    const res = await plFetch(TITLE_URL(row.dataset.id), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ title: val }),
                    });
                    if (!res.ok) { throw new Error('HTTP ' + res.status); }
                    const json = await res.json();
                    document.querySelectorAll(`[data-task-item][data-id="${row.dataset.id}"] .pl-task-title`)
                        .forEach(t => { t.textContent = json.title; });
                    plShowToast(@json(__('Title updated')));
                    return;
                } catch (err) {
                    console.error('[Planner] rename failed', err);
                }
            }
            titleEl.textContent = old;
        };
        input.addEventListener('keydown', ev => {
            if (ev.key === 'Enter') { ev.preventDefault(); finish(true); }
            if (ev.key === 'Escape') { ev.preventDefault(); finish(false); }
        });
        input.addEventListener('blur', () => finish(true));
    });

    /* ── Quick Estimate Popover & Updater ── */
    window.toggleEstimatePicker = function(taskId, ev) {
        if (ev) { ev.stopPropagation(); ev.preventDefault(); }
        const popover = document.getElementById('plEstimatePopover-' + taskId);
        if (!popover) return;
        const wasShowing = popover.classList.contains('show');
        document.querySelectorAll('.pl-estimate-popover.show').forEach(p => p.classList.remove('show'));
        if (!wasShowing) {
            popover.classList.add('show');
        }
    };

    window.setQuickEstimate = async function(taskId, hours, ev) {
        if (ev) { ev.stopPropagation(); ev.preventDefault(); }
        const popover = document.getElementById('plEstimatePopover-' + taskId);
        if (popover) popover.classList.remove('show');

        try {
            const res = await plFetch(`/planner/tasks/${taskId}/estimate`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ estimated_hours: hours })
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            
            // Update badge text across any instances of this task
            document.querySelectorAll(`[data-task-item][data-id="${taskId}"] .pl-task-est-badge`).forEach(badge => {
                badge.innerHTML = `<i class="bi bi-clock-history"></i> ${data.estimated_label}`;
            });
            plShowToast('{{ __("Estimated time updated") }}');
        } catch (err) {
            console.error('[Planner] update estimate failed', err);
            plShowToast('{{ __("Error updating estimate") }}');
        }
    };

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.pl-task-estimate-wrapper')) {
            document.querySelectorAll('.pl-estimate-popover.show').forEach(p => p.classList.remove('show'));
        }
    });

    /* ── Live Floating Focus Bar — mirrors the global TM timer store ── */
    let plBreakMode = false;
    let plBreakEndAt = 0;
    let plBreakTicker = null;
    let plBarDismissedId = null;

    function formatTimeDisplay(totalSeconds) {
        const h = Math.floor(totalSeconds / 3600);
        const m = Math.floor((totalSeconds % 3600) / 60);
        const s = totalSeconds % 60;
        return [
            h > 0 ? String(h).padStart(2, '0') : null,
            String(m).padStart(2, '0'),
            String(s).padStart(2, '0')
        ].filter(Boolean).join(':');
    }

    function plRenderFloatingBar(snap) {
        const bar = document.getElementById('plFloatingFocusBar');
        if (!bar) return;
        const entry = snap.entry;
        const clockEl = document.getElementById('plFfbClock');
        const titleEl = document.getElementById('plFfbTaskTitle');
        const projEl = document.getElementById('plFfbProject');
        const badgeEl = document.getElementById('plFfbStatusBadge');
        const pauseIcon = document.getElementById('plFfbPauseIcon');
        const pauseBtnEl = document.getElementById('plFfbPauseBtn');

        if (plBreakMode) {
            bar.style.display = 'block';
            bar.classList.remove('pl-ffb-paused');
            const remain = Math.max(0, Math.ceil((plBreakEndAt - Date.now()) / 1000));
            if (titleEl) titleEl.textContent = '☕ {{ __("Pomodoro Break") }}';
            if (projEl) projEl.textContent = '{{ __("Rest & Recharge") }}';
            if (badgeEl) {
                badgeEl.textContent = '{{ __("Break") }}';
                badgeEl.style.background = '#0284c7';
            }
            if (clockEl) clockEl.textContent = formatTimeDisplay(remain);
            return;
        }

        if (!entry || entry.id === plBarDismissedId) {
            bar.style.display = 'none';
            return;
        }

        bar.style.display = 'block';
        bar.classList.toggle('pl-ffb-paused', entry.status === 'paused');

        if (titleEl) titleEl.textContent = entry.task?.title || entry.description || '{{ __("Active Focus Session") }}';
        if (projEl) projEl.textContent = entry.project?.name || '{{ __("General") }}';

        if (badgeEl) {
            if (entry.status === 'paused') {
                badgeEl.textContent = '{{ __("Paused") }}';
                badgeEl.style.background = '#64748b';
            } else {
                badgeEl.textContent = '{{ __("Focusing") }}';
                badgeEl.style.background = '#6366f1';
            }
        }

        if (pauseIcon) {
            pauseIcon.className = entry.status === 'paused' ? 'bi bi-play-fill' : 'bi bi-pause-fill';
        }
        if (pauseBtnEl) pauseBtnEl.disabled = !!snap.busy;
        if (clockEl) clockEl.textContent = formatTimeDisplay(snap.elapsed);
    }

    TM.subscribe(plRenderFloatingBar);

    window.plStartTaskTimer = async function(taskId, title, project) {
        try {
            await TM.start({ task_id: taskId });
            plBarDismissedId = null;
            if (plBreakTicker) { clearInterval(plBreakTicker); plBreakTicker = null; }
            plBreakMode = false;
            plShowToast('{{ __("Timer started") }}: ' + (title || @json(__('Task'))));
        } catch (err) {
            console.error('[Planner] start timer failed', err);
            plShowToast('{{ __("Error starting timer") }}');
        }
    };

    window.plToggleFloatingPause = function() {
        TM.togglePause().catch(() => plShowToast('{{ __("Could not update the timer") }}'));
    };

    window.plStopFloatingTimer = async function() {
        try {
            await TM.stop();
            plShowToast('{{ __("Timer stopped and time recorded") }}');
        } catch (err) {
            console.error('[Planner] stop timer failed', err);
            plShowToast('{{ __("Error stopping timer") }}');
        }
    };

    window.plStartBreak = function(minutes = 5) {
        plBreakMode = true;
        plBreakEndAt = Date.now() + minutes * 60000;
        if (!plBreakTicker) {
            plBreakTicker = setInterval(() => {
                const remain = Math.max(0, Math.ceil((plBreakEndAt - Date.now()) / 1000));
                if (remain <= 0) {
                    clearInterval(plBreakTicker);
                    plBreakTicker = null;
                    plBreakMode = false;
                    plRenderFloatingBar(TM.snapshot());
                    plShowToast('{{ __("Break finished! Ready to focus?") }}');
                    return;
                }
                const clockEl = document.getElementById('plFfbClock');
                if (clockEl) clockEl.textContent = formatTimeDisplay(remain);
            }, 250);
        }
        plRenderFloatingBar(TM.snapshot());
    };

    window.plExpandToFocusWorkstation = function() {
        if (typeof openFocusWorkstation === 'function') {
            const entry = TM.entry;
            openFocusWorkstation(
                entry?.task?.id,
                entry?.task?.title,
                entry?.project?.name
            );
        }
    };

    window.plCloseFloatingBar = function() {
        plBarDismissedId = TM.entry ? TM.entry.id : null;
        const bar = document.getElementById('plFloatingFocusBar');
        if (bar) bar.style.display = 'none';
    };

    /* ── Week view: clean planning (add / move / remove per day) ── */
    (function initWeekView() {
        const grid = document.getElementById('plWeek');
        if (!grid) return;

        const QUICK_ADD_URL = @json(route('planner.quick-add.task'));
        const BACKLOG_URL = @json(route('planner.backlog'));
        const SCHEDULE_URL = id => `{{ route('planner.tasks.schedule', ['task' => '__ID__']) }}`.replace('__ID__', id);
        let PW_PERIODS = [];
        try { PW_PERIODS = JSON.parse(grid.dataset.periods || '[]'); } catch (e) { PW_PERIODS = []; }
        const PW_ANYTIME = { key: 'anytime', label: @json(__('Anytime')), icon: 'bi-inbox', color: '#64748b' };
        const PW_ORDER = PW_PERIODS.map(p => p.key).concat(['anytime']);
        const PW_PRIO = { high: '#dc2626', medium: '#d97706', low: '#16a34a' };
        const PW_TXT = {
            added: @json(__('Task added ✓')),
            scheduled: @json(__('Scheduled ✓')),
            moved: @json(__('Moved ✓')),
            removed: @json(__('Removed from the week')),
            errCreate: @json(__('Could not create task')),
            errMove: @json(__('Could not move task')),
            errLoad: @json(__('Could not load inbox')),
            inboxEmpty: @json(__('Inbox is empty — nice!')),
        };
        const NUMFMT = new Intl.NumberFormat(document.documentElement.lang === 'fa' ? 'fa-IR' : 'en-US');

        function dayEl(dateISO) { return grid.querySelector('[data-week-day="' + dateISO + '"]'); }
        function weekDays() { try { return JSON.parse(grid.dataset.weekDays || '[]'); } catch (e) { return []; } }
        function pwMeta(key) { return key === 'anytime' ? PW_ANYTIME : (PW_PERIODS.find(p => p.key === key) || PW_ANYTIME); }

        function pwRefreshDay(day) {
            if (!day) return;
            const open = day.querySelectorAll('[data-week-task]:not(.is-done)').length;
            const badge = day.querySelector('[data-day-open]');
            if (badge) {
                badge.textContent = NUMFMT.format(open);
                badge.classList.toggle('has-open', open > 0);
            }
            day.querySelectorAll('[data-period-group]').forEach(g => {
                const n = g.querySelectorAll('[data-week-task]').length;
                const c = g.querySelector('.pw-group-count');
                if (c) c.textContent = NUMFMT.format(n);
                if (n === 0) g.remove();
            });
            const empty = day.querySelector('[data-day-empty]');
            if (empty) empty.hidden = day.querySelector('[data-week-task]') !== null;
        }

        function buildGroup(key, dateISO) {
            const m = pwMeta(key);
            const g = document.createElement('div');
            g.className = 'pw-group';
            g.dataset.periodGroup = key;
            g.innerHTML = '<div class="pw-group-head"><i class="bi ' + m.icon + '" style="color:' + m.color + ';"></i>'
                + '<span class="pw-group-name"></span><span class="pw-group-count">0</span>'
                + '<button type="button" class="pw-group-add" data-week-add data-date="' + dateISO + '" data-period="' + key + '"><i class="bi bi-plus-lg"></i></button></div>'
                + '<div class="pw-group-body" data-period-body="' + key + '"></div>';
            g.querySelector('.pw-group-name').textContent = m.label;
            return g;
        }

        function pwInsertTask(dateISO, groupKey, html) {
            const day = dayEl(dateISO);
            if (!day || !html) return null;
            let group = day.querySelector('[data-period-group="' + groupKey + '"]');
            if (!group) {
                group = buildGroup(groupKey, dateISO);
                const groups = day.querySelector('[data-day-groups]');
                const idx = PW_ORDER.indexOf(groupKey);
                let placed = false;
                groups.querySelectorAll(':scope > [data-period-group]').forEach(ex => {
                    if (!placed && PW_ORDER.indexOf(ex.dataset.periodGroup) > idx) {
                        groups.insertBefore(group, ex);
                        placed = true;
                    }
                });
                if (!placed) groups.appendChild(group);
            }
            const tmp = document.createElement('template');
            tmp.innerHTML = html.trim();
            const node = tmp.content.firstElementChild;
            if (!node) return null;
            node.classList.add('pw-flash');
            group.querySelector('[data-period-body]').appendChild(node);
            pwRefreshDay(day);
            return node;
        }

        function pwRemoveTaskEverywhere(id, except) {
            document.querySelectorAll('[data-week-task][data-id="' + id + '"]').forEach(n => {
                if (n === except) return;
                const d = n.closest('[data-week-day]');
                n.remove();
                if (d) pwRefreshDay(d);
            });
        }

        /* ── Add popover ── */
        function closePops() {
            grid.querySelectorAll('[data-week-pop]').forEach(p => { p.hidden = true; });
        }
        function openPop(dayISO, period) {
            closePops();
            closeMove();
            const day = dayEl(dayISO);
            if (!day) return;
            const pop = day.querySelector('[data-week-pop]');
            if (!pop) return;
            pop.dataset.date = dayISO;
            pop.dataset.period = period || '';
            pop.querySelectorAll('[data-wp-period]').forEach(b => {
                b.classList.toggle('active', (b.dataset.wpPeriod || '') === (period || ''));
            });
            pop.hidden = false;
            loadBacklog(pop, '');
            setTimeout(() => pop.querySelector('[data-week-title]')?.focus(), 30);
        }

        async function loadBacklog(pop, q) {
            const list = pop.querySelector('[data-week-backlog]');
            if (!list) return;
            try {
                const proj = pop.querySelector('[data-week-project]');
                const params = new URLSearchParams();
                if (q) params.set('q', q);
                if (proj && proj.value) params.set('project_id', proj.value);
                if (pop.dataset.date) params.set('date', pop.dataset.date);
                const qs = params.toString();
                const res = await plFetch(BACKLOG_URL + (qs ? '?' + qs : ''), {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                list.innerHTML = '';
                if (!json.tasks || !json.tasks.length) {
                    list.innerHTML = '<div class="pw-pop-hint"></div>';
                    list.firstElementChild.textContent = PW_TXT.inboxEmpty;
                    return;
                }
                json.tasks.forEach(t => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'pw-backlog';
                    b.dataset.id = t.id;
                    b.title = t.title;
                    b.innerHTML = '<span class="dot"></span><span class="t"></span><span class="p"></span>';
                    b.querySelector('.dot').style.background = PW_PRIO[t.priority] || '#94a3b8';
                    b.querySelector('.t').textContent = t.title;
                    const meta = [t.project, t.due_label].filter(Boolean).join(' · ');
                    const pEl = b.querySelector('.p');
                    pEl.textContent = meta;
                    if (t.is_overdue) pEl.style.color = '#dc2626';
                    list.appendChild(b);
                });
            } catch (e) {
                console.error('[Week] backlog failed', e);
                list.innerHTML = '<div class="pw-pop-hint"></div>';
                list.firstElementChild.textContent = PW_TXT.errLoad;
            }
        }

        async function createWeekTask(pop) {
            const input = pop.querySelector('[data-week-title]');
            const title = input.value.trim();
            if (!title || pop.dataset.busy) return;
            pop.dataset.busy = '1';
            try {
                const res = await plFetch(QUICK_ADD_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        title,
                        date: pop.dataset.date,
                        time_period: pop.dataset.period || null,
                        view: 'week',
                    }),
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                pwInsertTask(json.due_date || pop.dataset.date, json.group || 'anytime', json.html);
                input.value = '';
                input.focus();
                plShowToast(PW_TXT.added);
            } catch (e) {
                console.error('[Week] quick-add failed', e);
                plShowToast(PW_TXT.errCreate);
            } finally {
                delete pop.dataset.busy;
            }
        }

        async function scheduleExisting(btn, dateISO, period) {
            const id = btn.dataset.id;
            if (!id || btn.dataset.busy) return;
            btn.dataset.busy = '1';
            try {
                const res = await plFetch(SCHEDULE_URL(id), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ due_date: dateISO, time_period: period || null }),
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                const node = pwInsertTask(json.due_date, json.group || 'anytime', json.html);
                pwRemoveTaskEverywhere(id, node);
                btn.remove();
                plShowToast(PW_TXT.scheduled);
            } catch (e) {
                console.error('[Week] schedule failed', e);
                plShowToast(PW_TXT.errMove);
                delete btn.dataset.busy;
            }
        }

        /* ── Move menu ── */
        const moveMenu = document.getElementById('pwMoveMenu');
        let moveId = null;
        function closeMove() {
            if (moveMenu) moveMenu.hidden = true;
            moveId = null;
        }
        function openMove(btn) {
            if (!moveMenu) return;
            closePops();
            moveId = btn.dataset.id;
            const from = btn.dataset.date;
            const box = moveMenu.querySelector('[data-move-days]');
            box.innerHTML = '';
            weekDays().forEach(d => {
                const b = document.createElement('button');
                b.type = 'button';
                if (d.date === from) b.classList.add('current');
                b.innerHTML = '<span class="t"></span><span class="d"></span>';
                b.querySelector('.t').textContent = d.label;
                b.querySelector('.d').textContent = String(d.date).slice(5);
                b.addEventListener('click', () => moveTaskTo(d.date));
                box.appendChild(b);
            });
            const r = btn.getBoundingClientRect();
            moveMenu.hidden = false;
            const w = 220, h = Math.min(320, 60 + weekDays().length * 38);
            moveMenu.style.minWidth = w + 'px';
            moveMenu.style.left = Math.max(12, Math.min(r.left, window.innerWidth - w - 12)) + 'px';
            moveMenu.style.top = Math.max(12, Math.min(r.bottom + 6, window.innerHeight - h - 12)) + 'px';
        }
        async function moveTaskTo(dateISO) {
            const id = moveId;
            closeMove();
            if (!id) return;
            try {
                const res = await plFetch(SCHEDULE_URL(id), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ due_date: dateISO }),
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                const node = pwInsertTask(json.due_date, json.group || 'anytime', json.html);
                pwRemoveTaskEverywhere(id, node);
                plShowToast(PW_TXT.moved);
            } catch (e) {
                console.error('[Week] move failed', e);
                plShowToast(PW_TXT.errMove);
            }
        }

        async function removeWeekTask(btn) {
            const id = btn.dataset.id;
            const node = btn.closest('[data-week-task]');
            if (!id || !node || btn.dataset.busy) return;
            btn.dataset.busy = '1';
            try {
                const res = await plFetch(POSTPONE_URL(id), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ action: 'clear' }),
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const day = node.closest('[data-week-day]');
                node.remove();
                pwRefreshDay(day);
                plShowToast(PW_TXT.removed);
            } catch (e) {
                console.error('[Week] remove failed', e);
                plShowToast(PW_TXT.errMove);
                delete btn.dataset.busy;
            }
        }

        /* ── Wiring ── */
        grid.addEventListener('click', e => {
            const add = e.target.closest('[data-week-add]');
            if (add) {
                e.stopPropagation();
                openPop(add.dataset.date, add.dataset.period || '');
                return;
            }
            const chip = e.target.closest('[data-wp-period]');
            if (chip) {
                const pop = chip.closest('[data-week-pop]');
                pop.dataset.period = chip.dataset.wpPeriod || '';
                pop.querySelectorAll('[data-wp-period]').forEach(b => b.classList.toggle('active', b === chip));
                return;
            }
            const create = e.target.closest('[data-week-create]');
            if (create) {
                createWeekTask(create.closest('[data-week-pop]'));
                return;
            }
            const bl = e.target.closest('[data-week-backlog] .pw-backlog');
            if (bl) {
                const pop = bl.closest('[data-week-pop]');
                scheduleExisting(bl, pop.dataset.date, pop.dataset.period || null);
                return;
            }
            const mv = e.target.closest('[data-week-move]');
            if (mv) {
                e.stopPropagation();
                openMove(mv);
                return;
            }
            const rm = e.target.closest('[data-week-remove]');
            if (rm) {
                removeWeekTask(rm);
                return;
            }
        });
        grid.addEventListener('keydown', e => {
            if (e.key === 'Enter' && e.target.matches?.('[data-week-title]')) {
                e.preventDefault();
                createWeekTask(e.target.closest('[data-week-pop]'));
            } else if (e.key === 'Escape') {
                closePops();
            }
        });
        let searchT = null;
        grid.addEventListener('input', e => {
            if (!e.target.matches?.('[data-week-search]')) return;
            const pop = e.target.closest('[data-week-pop]');
            const q = e.target.value.trim();
            clearTimeout(searchT);
            searchT = setTimeout(() => loadBacklog(pop, q), 250);
        });
        grid.addEventListener('change', e => {
            if (!e.target.matches?.('[data-week-project]')) return;
            const pop = e.target.closest('[data-week-pop]');
            const search = pop.querySelector('[data-week-search]');
            loadBacklog(pop, search ? search.value.trim() : '');
        });
        document.addEventListener('click', e => {
            if (!e.target.closest?.('[data-week-pop]') && !e.target.closest?.('[data-week-add]')) closePops();
            if (!e.target.closest?.('#pwMoveMenu') && !e.target.closest?.('[data-week-move]')) closeMove();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closePops();
                closeMove();
            }
        });

        grid.querySelectorAll('[data-week-day]').forEach(pwRefreshDay);

        /* Refresh a week day's counters after a task toggle (called from toggleTask). */
        window.pwWeekRefresh = function (id) {
            const row = grid.querySelector('[data-week-task][data-id="' + id + '"]');
            if (row) pwRefreshDay(row.closest('[data-week-day]'));
        };
    })();

    /* Floating bar boot is handled by the global TM store (time/_store) which
       auto-syncs /time/active and pushes state into plRenderFloatingBar. */
})();
</script>
@endpush
@push('scripts')
<script>    /* ── Day Log (دفترروز) — daily capture ───────────────────────────────────────
       One hero input does the work; everything else is an on-demand panel.
       Entries are persisted per user per day via the day-logs API
       (DayLogController) with full validation; the catalog and the Insights
       candidates are computed server-side. It reuses plFetch(), plShowToast()
       and the real quick-add habit form.
       ─────────────────────────────────────────────────────────────────────────── */
@php
    $lgPeriods = collect(config('routines.periods', []))->map(fn ($p, $k) => [
        'key' => $k, 'label' => __($p['label']), 'icon' => $p['icon'],
        'color' => $p['color'], 'at' => $p['at'],
    ])->values()->all();
@endphp
(function () {
    const root = document.getElementById('lgSection');
    if (!root) { return; }   // day view only

    const LG_DATE = root.dataset.date || '';
    const LG_ROUTES = {
        index: @json(route('day-logs.index')),
        store: @json(route('day-logs.store')),
        candidates: @json(route('day-logs.candidates')),
        merge: @json(route('day-logs.merge')),
        update: id => @json(url('/planner/day-logs')) + '/' + id,
        destroy: id => @json(url('/planner/day-logs')) + '/' + id,
    };

    const I18N = {
        setTime:    @json(__('Set time')),
        untimed:    @json(__('Untimed')),
        noTitle:    @json(__('No title')),
        logged:     @json(__('Logged')),
        moodSentence: @json(__('mood sentence')),
        removed:    @json(__('Removed from Day Log')),
        empty:      @json(__('Nothing logged for today yet.')),
        noPeriod:   @json(__('No specific time of day')),
        loggedToday: @json(__('Logged today')),
        and:        @json(__('and')),
        minutes:    @json(__('minute')),
        minutesPlural: @json(__('minutes')),
        hoursUnit:  @json(__('hour')),
        hoursPlural: @json(__('hours')),
        activityOne: @json(__('activity')),
        activityMany: @json(__('activities')),
        moodOne:    @json(__('mood note')),
        moodMany:   @json(__('mood notes')),
        moodLabel:  @json(__('Mood')),
        timesBefore: @json(__('times before')),
        newOne:     @json(__('New one — log it as is and it will be suggested from now on.')),
        turned:     @json(__('Turned into an activity — add a time or a time of day')),
        prefilled:  @json(__('Form pre-filled from this entry')),
        habitNudge: @json(__('You logged ":name" :count times in the last 3 weeks. Make it a habit? (If not, just keep logging it.)')),
        habitYes:   @json(__('Yes, make it a habit')),
        quitNudge:  @json(__('You logged ":name" :count times this week. Define it as something you are quitting, so clean days and slips get counted?')),
        quitYes:    @json(__('Define as a quit habit')),
        liveNudge:  @json(__('You logged ":name" three times just now. Shall we make it a habit?')),
        later:      @json(__('Later')),
        beStill:    @json(__('Okay — I will stop suggesting this.')),
        convertHabit: @json(__('Convert to habit')),
        intoActivity: @json(__('Turn into an activity')),
        edit:       @json(__('Edit')),
        delete:     @json(__('Delete')),
        filterEmpty: @json(__('Nothing in this filter.')),
        startTimer: @json(__('Start timer')),
        stopTimer:  @json(__('Stop timer')),
        timerStarted: @json(__('Timer started')),
        timerSaved: @json(__('Timer stopped — time saved')),
        timerFail:  @json(__('Could not start the timer.')),
        merged:     @json(__('Merged')),
        invalidTime: @json(__('Invalid time')),
        logsToday:  @json(__('You have :count logs today.')),
        unloggedQ:  @json(__('Anything left unlogged today?')),
        quickLog:   @json(__('Quick log')),
        loading:    @json(__('Loading…')),
        loadFail:   @json(__('Could not load the day log.')),
        saveFail:   @json(__('Could not save the entry.')),
        updateFail: @json(__('Could not update the entry.')),
        deleteFail: @json(__('Could not delete the entry.')),
        markQuit:   @json(__('Mark as quit-tracked')),
        unmarkQuit: @json(__('Stop tracking quit')),
        quitMarked: @json(__('Marked for quit tracking')),
        quitUnmarked: @json(__('Removed from quit tracking')),
    };

    const PERIODS = @json($lgPeriods);
    const IS_FA = @json(app()->getLocale() === 'fa');
    const NUM = n => IS_FA ? String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]) : String(n);
    const plural = (n, one, many) => n === 1 ? one : many;
    const esc = s => String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const pad = n => String(n).padStart(2, '0');
    const periodOf = key => PERIODS.find(p => p.key === key) || null;
    const periodLabel = key => { const p = periodOf(key); return p ? p.label : ''; };

    /* ── inline parsing: "پیانو ۴۵ دقیقه عصر" → title + time + amount ── */
    const toLatin = s => String(s)
        .replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
        .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
    const normText = s => toLatin(s).replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/\u200c/g, ' ');

    /* Period words, longest first so "بعدازظهر" wins over "ظهر". */
    function periodWords() {
        const aliases = {
            morning: ['صبح', 'بامداد', 'morning'],
            noon: ['نیمروز', 'ظهر', 'noon', 'midday'],
            afternoon: ['بعدازظهر', 'بعد از ظهر', 'بعدازازظهر', 'afternoon'],
            evening: ['عصر', 'غروب', 'evening'],
            night: ['شب', 'night'],
        };
        const words = [];
        PERIODS.forEach(p => {
            (aliases[p.key] || []).forEach(w => words.push([w, p.key]));
            if (p.label) { words.push([p.label, p.key]); }
        });
        return words.sort((a, b) => b[0].length - a[0].length);
    }

    const UNITS = { minute: 0, hour: 1, page: 2, verse: 3, count: 4, km: 5 };

    function unitWordMap() {
        const select = $('lgUnit');
        const map = [];
        const push = (idx, words) => words.forEach(w => w && map.push([w.toLowerCase(), idx]));
        push(UNITS.minute, [select.options[0] && select.options[0].text, 'دقیقه', 'دق', 'دقیقه‌ای', 'min', 'mins', 'minute', 'minutes', 'm']);
        push(UNITS.hour, [select.options[1] && select.options[1].text, 'ساعت', 'ساعتی', 'س', 'h', 'hr', 'hour', 'hours']);
        push(UNITS.page, [select.options[2] && select.options[2].text, 'صفحه', 'صفحه‌ای', 'ص', 'page', 'pages', 'p']);
        push(UNITS.verse, [select.options[3] && select.options[3].text, 'بیت', 'بیتی', 'verse']);
        push(UNITS.count, [select.options[4] && select.options[4].text, 'بار', 'باره', 'مرتبه', 'time', 'times']);
        push(UNITS.km, [select.options[5] && select.options[5].text, 'کیلومتر', 'کیلومتری', 'km']);
        return map.sort((a, b) => b[0].length - a[0].length);
    }

    /**
     * Reads the raw input and splits it into a title plus optional time/amount.
     * Everything is best-effort: whatever it cannot recognise stays in the title,
     * and the panel fields are synced so the user can correct it.
     */
    function parseInline(raw) {
        const out = { title: '', note: null, seconds: null, amount: null, unit: null, start: null, end: null, period: null, mode: 'none' };
        if (!raw || !raw.trim()) { return out; }

        let text = normText(raw);
        let cutFrom = raw.length;
        const cuts = [];

        /* A dash hands everything after it to the note: "پیانو ۴۵ دقیقه — قطعه‌ی جدید" */
        const dash = text.match(/\s+(?:[—–]|--?)\s+/);
        if (dash) {
            const head = text.slice(0, dash.index).trim();
            const tail = text.slice(dash.index + dash[0].length).trim();
            if (head && tail) {
                out.note = tail;
                text = text.slice(0, dash.index);
                cutFrom = dash.index;
            }
        }

        const range = text.match(/(\d{1,2})\s*[:.٫]\s*(\d{2})\s*(?:-|–|—|تا|to)\s*(\d{1,2})\s*[:.٫]\s*(\d{2})/);
        if (range) {
            const [sh, sm, eh, em] = [range[1], range[2], range[3], range[4]].map(Number);
            if (sh < 24 && eh < 24 && sm < 60 && em < 60) {
                out.start = pad(sh) + ':' + pad(sm);
                out.end = pad(eh) + ':' + pad(em);
                out.mode = 'range';
                cuts.push([range.index, range.index + range[0].length]);
            }
        }

        const units = unitWordMap();
        const pattern = units.map(u => u[0].replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|');
        const amount = pattern ? text.match(new RegExp('(\\d+(?:[.,]\\d+)?)\\s*(' + pattern + ')', 'i')) : null;
        if (amount) {
            const value = parseFloat(amount[1].replace(',', '.'));
            const hit = units.find(u => u[0] === amount[2].toLowerCase());
            if (hit && value > 0 && value <= 100000) {
                out.amount = value;
                out.unit = hit[1];
                out.mode = 'duration';
                cuts.push([amount.index, amount.index + amount[0].length]);
            }
        }

        if (out.mode === 'none') {
            const bare = text.match(/(\d{1,3})\s*$/);
            const value = bare ? Number(bare[1]) : 0;
            if (value > 0 && value <= 600) {
                out.amount = value;
                out.unit = UNITS.minute;
                out.mode = 'duration';
                cuts.push([bare.index, bare.index + bare[0].length]);
            }
        }

        for (const [word, key] of periodWords()) {
            const idx = text.toLowerCase().indexOf(word.toLowerCase());
            if (idx >= 0) {
                out.period = key;
                if (out.mode === 'none') { out.mode = 'period'; }
                cuts.push([idx, idx + word.length]);
                break;
            }
        }

        /* Remove the recognised bits from the ORIGINAL string (normalisation is
           1:1 char-wise, so indexes line up) and collapse the leftovers. */
        let title = raw.slice(0, cutFrom);
        cuts.sort((a, b) => b[0] - a[0]).forEach(([start, end]) => {
            title = title.slice(0, start) + ' ' + title.slice(end);
        });
        out.title = title.replace(/\s{2,}/g, ' ').replace(/^[\s\-–—,·]+|[\s\-–—,·]+$/g, '').trim();

        if (out.mode === 'duration' && out.unit !== null) {
            out.seconds = [UNITS.minute, UNITS.hour].indexOf(out.unit) >= 0
                ? out.amount * (out.unit === UNITS.hour ? 3600 : 60)
                : null;
        }

        return out;
    }

    /* ── state ──
       No mode switch: an entry may carry a time of day, a duration and a clock
       range all at once ("پیانو ۴۵ دقیقه عصر"), or none of them. */
    const TIME_UNITS = [UNITS.minute, UNITS.hour];
    const state = { kind: 'activity', amount: null, unit: UNITS.minute,
                    start: null, end: null, period: null, mood: null, actId: null };

    const $ = id => document.getElementById(id);
    const input = $('lgInput');
    const suggestBox = $('lgSuggest');
    const meta = $('lgMeta');

    const CYCLE = ['#7c3aed', '#0ea5e9', '#ec4899', '#f59e0b', '#10b981', '#ef4444', '#0e7490'];
    const ICON_POOL = ['bi-music-note-beamed', 'bi-book', 'bi-soundwave', 'bi-vector-pen', 'bi-person-walking',
                       'bi-fire', 'bi-people', 'bi-brush', 'bi-cup-hot', 'bi-bicycle', 'bi-camera', 'bi-controller'];
    const UNIT_KEYS = ['minute', 'hour', 'page', 'verse', 'count', 'km'];

    /* Deterministic presentation per title so an activity keeps its color/icon across reloads. */
    const hashStr = s => { let h = 0; const t = String(s || ''); for (let i = 0; i < t.length; i++) { h = (h * 31 + t.charCodeAt(i)) >>> 0; } return h; };
    const colorFor = name => CYCLE[hashStr(name) % CYCLE.length];
    const iconFor = name => ICON_POOL[hashStr(name) % ICON_POOL.length];

    /* Entries + repeat catalog live on the server; loaded per viewed day. */
    let catalog = [];   // { id, name, uses, unitKey, quit, icon, color }
    let entries = [];   // client shapes mapped from the API (see mapServerEntry)
    let lgLoading = true;

    const unitKeyOf = idx => (($('lgUnit').options[idx] || {}).dataset || {}).lgUnit || UNIT_KEYS[idx] || 'minute';
    const unitIdxOf = key => { const i = UNIT_KEYS.indexOf(key); return i >= 0 ? i : 0; };
    const unitLabelOf = key => {
        const sel = $('lgUnit');
        for (let i = 0; i < sel.options.length; i++) {
            if (((sel.options[i].dataset || {}).lgUnit || '') === key) { return sel.options[i].text; }
        }
        return (sel.options[unitIdxOf(key)] || { text: key }).text;
    };

    function mapServerEntry(s) {
        return {
            id: s.id, title: s.title, note: s.note, kind: s.kind, mode: s.mode,
            period: s.period || null,
            seconds: s.duration_seconds != null ? Number(s.duration_seconds) : null,
            amount: s.amount != null ? Number(s.amount) : null,
            amountUnit: (s.amount != null && s.amount_unit) ? unitLabelOf(s.amount_unit) : null,
            unitKey: s.amount_unit || null,
            start: s.start_time || null, end: s.end_time || null,
            loggedAt: s.logged_at || null,
            mood: s.mood != null ? Number(s.mood) : null,
            moodPicked: !!s.mood_picked, isQuit: !!s.is_quit,
            timeEntryId: s.time_entry_id || null,
            color: colorFor(s.title),
        };
    }

    /* POST/PATCH/DELETE with JSON; throws { status, messages } on failure. */
    async function lgApi(url, method, body) {
        const res = await plFetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
        let json = null;
        try { json = await res.json(); } catch (e) { /* non-JSON */ }
        if (!res.ok) { throw { status: res.status, messages: json && json.errors ? json.errors : null }; }
        return json;
    }
    function apiErr(err, fallback) {
        if (err && err.messages) {
            const first = Object.values(err.messages).flat()[0];
            if (first) { return first; }
        }
        return fallback;
    }

    /* Plain server-shape snapshot of a client entry (for payloads + undo). */
    function snapshotEntry(e) {
        return { title: e.title, note: e.note, kind: e.kind, period: e.period, seconds: e.seconds,
                 amount: e.amount, unitKey: e.unitKey, start: e.start, end: e.end, loggedAt: e.loggedAt,
                 mood: e.mood, moodPicked: e.moodPicked, isQuit: e.isQuit, timeEntryId: e.timeEntryId };
    }
    function snapshotToPayload(s) {
        const isJ = s.kind === 'journal';
        return { date: LG_DATE, kind: s.kind, title: s.title, note: s.note || null,
            period: isJ ? null : (s.period || null),
            duration_seconds: isJ ? null : (s.seconds || null),
            amount: isJ ? null : (s.amount != null ? s.amount : null),
            amount_unit: (!isJ && s.amount != null) ? (s.unitKey || 'minute') : null,
            start_time: isJ ? null : (s.start || null), end_time: isJ ? null : (s.end || null),
            logged_at: s.loggedAt || null, mood: s.mood != null ? s.mood : null,
            mood_picked: !!s.moodPicked, is_quit: !!s.isQuit,
            time_entry_id: s.timeEntryId || null };
    }
    const entryToPayload = e => snapshotToPayload(snapshotEntry(e));

    async function loadDay() {
        lgLoading = true;
        renderRows();
        try {
            const res = await plFetch(LG_ROUTES.index + '?date=' + encodeURIComponent(LG_DATE), {
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) { throw new Error('HTTP ' + res.status); }
            const j = await res.json();
            entries = (j.entries || []).map(mapServerEntry);
            catalog = (j.catalog || []).map((c, i) => ({
                id: 'srv' + i, name: c.name, uses: Number(c.uses || 0),
                unitKey: c.unit || 'minute', quit: !!c.quit,
                icon: iconFor(c.name), color: colorFor(c.name),
            }));
            refreshAsk();
        } catch (err) {
            console.error('[DayLog] load failed', err);
            plShowToast(I18N.loadFail);
        }
        lgLoading = false;
        render();
    }

    const dismissed = {};
    const sessionAdds = {};
    let activeIdx = -1;

    /* ── helpers ── */
    const unitLabel = idx => ($('lgUnit').options[idx] || { text: '' }).text;

    function humanDuration(sec) {
        const h = Math.floor(sec / 3600), m = Math.round((sec % 3600) / 60);
        const hours = NUM(h) + ' ' + plural(h, I18N.hoursUnit, I18N.hoursPlural);
        const mins = NUM(m) + ' ' + plural(m, I18N.minutes, I18N.minutesPlural);
        if (h && m) { return hours + ' ' + I18N.and + ' ' + mins; }
        return h ? hours : mins;
    }

    const toMinutes = t => { const [h, m] = String(t).split(':').map(Number); return (h || 0) * 60 + (m || 0); };

    /* A range that ends before it starts simply crossed midnight. */
    const secondsBetween = (from, to) => (((toMinutes(to) - toMinutes(from)) + 1440) % 1440 || 0) * 60;

    const secondsOf = (value, unit) => unit === UNITS.hour ? value * 3600
        : (unit === UNITS.minute ? value * 60 : null);

    function groupOf(e) {
        if (e.period) { return e.period; }
        if (e.mode === 'range' && e.start) {
            const h = parseInt(e.start.split(':')[0], 10);
            let best = PERIODS.length ? PERIODS[0].key : 'unscheduled';
            PERIODS.forEach(p => { if (h >= p.at - 3) { best = p.key; } });
            return best;
        }
        return 'unscheduled';
    }

    /* ── rendering ── */
    function rowMeta(e) {
        const bits = [];
        if (e.start && e.end) { bits.push(NUM(e.start) + '–' + NUM(e.end)); }
        else if (e.seconds) { bits.push(humanDuration(e.seconds)); }
        if (e.amount && e.amountUnit) { bits.push(NUM(e.amount) + ' ' + e.amountUnit); }
        if (e.mood != null && !e.moodPicked) { bits.push(I18N.moodLabel + ' ' + NUM(e.mood) + '/' + NUM(10)); }
        return bits.join(' · ');
    }

    function rowHtml(e) {
        const isJ = e.kind === 'journal';
        const isMood = !!e.moodPicked;
        const meta = rowMeta(e);
        /* Clock block: the hour it was logged + which part of day it belongs to.
           Activity ranges stay in the meta chips; this block always answers
           "when was this logged, morning or night?". */
        const p = periodOf(e.period || groupOf(e));
        const clock = e.loggedAt || e.start || null;
        const timing = !!(typeof lgTimer !== 'undefined' && lgTimer && lgTimer.lgId === e.id);
        const timeHtml = clock
            ? '<button type="button" class="lg-row-time' + (p ? '' : ' is-flat') + '" data-lg-act="time"' +
              ' aria-label="' + esc(I18N.edit) + '" title="' + esc(I18N.logged + ' ' + clock) + '">' +
                '<b>' + NUM(clock) + '</b>' +
                (p ? '<small style="color:' + p.color + '"><i class="bi ' + p.icon + '" aria-hidden="true"></i>' + p.label + '</small>' : '') +
                (timing ? '<span class="lg-live" data-lg-live="' + e.id + '">' + NUM(fmtLive(lgTimer.lastElapsed)) + '</span>' : '') +
              '</button>'
            : '';
        /* Meta and note travel together: they sit at the end of the title line,
           or wrap as a block when the title is long. */
        const moodBadge = (e.mood != null && e.moodPicked)
            ? '<span class="lg-mood-badge">' + esc(I18N.moodLabel) + ' ' + NUM(e.mood) + '/' + NUM(10) + '</span>' : '';
        const sub = (meta || e.note || moodBadge)
            ? '<div class="lg-row-sub">' + moodBadge +
                (meta ? '<span class="lg-row-meta">' + esc(meta) + '</span>' : '') +
                (e.note ? '<span class="lg-row-note">' + esc(e.note) + '</span>' : '') +
              '</div>'
            : '';
        return '' +
            '<div class="lg-row ' + (isJ ? 'is-journal' : '') + (isMood ? ' is-mood' : '') + '" data-lg-id="' + e.id + '" role="listitem">' +
                '<span class="lg-row-dot" style="--c:' + e.color + '" aria-hidden="true"></span>' +
                timeHtml +
                '<div class="lg-row-main">' +
                    '<span class="lg-row-title" data-lg-title>' + esc(e.title) + '</span>' +
                    sub +
                '</div>' +
                '<div class="lg-row-acts">' +
                    (!isJ ? '<button type="button" class="lg-icon' + (timing ? ' is-running' : '') + '" data-lg-act="timer" aria-label="' + esc(timing ? I18N.stopTimer : I18N.startTimer) + '" title="' + esc(timing ? I18N.stopTimer : I18N.startTimer) + '"><i class="bi ' + (timing ? 'bi-stop-fill' : 'bi-stopwatch') + '" aria-hidden="true"></i></button>' : '') +
                    (!isJ ? '<button type="button" class="lg-icon' + (e.isQuit ? ' is-quit-on' : '') + '" data-lg-act="quit" aria-label="' + esc(e.isQuit ? I18N.unmarkQuit : I18N.markQuit) + '" title="' + esc(e.isQuit ? I18N.unmarkQuit : I18N.markQuit) + '"><i class="bi ' + (e.isQuit ? 'bi-flag-fill' : 'bi-flag') + '" aria-hidden="true"></i></button>' : '') +
                    (isJ
                        ? '<button type="button" class="lg-icon" data-lg-act="activity" aria-label="' + esc(I18N.intoActivity) + '" title="' + esc(I18N.intoActivity) + '"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></button>'
                        : '<button type="button" class="lg-icon" data-lg-act="habit" aria-label="' + esc(I18N.convertHabit) + '" title="' + esc(I18N.convertHabit) + '"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></button>') +
                    '<button type="button" class="lg-icon" data-lg-act="edit" aria-label="' + esc(I18N.edit) + '" title="' + esc(I18N.edit) + '"><i class="bi bi-pencil" aria-hidden="true"></i></button>' +
                    '<button type="button" class="lg-icon danger" data-lg-act="delete" aria-label="' + esc(I18N.delete) + '" title="' + esc(I18N.delete) + '"><i class="bi bi-trash" aria-hidden="true"></i></button>' +
                '</div>' +
            '</div>';
    }

    /* Flat timeline, sorted by time of day (period order, then clock, then id).
       No group headers — each row carries its own period chip.
       The type filter only hides rows, never deletes them. */
    function renderRows() {
        if (lgLoading) {
            $('lgRows').innerHTML = '<div class="lg-empty lg-loading"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' + esc(I18N.loading) + '</div>';
            return;
        }
        const pIdx = key => { const i = PERIODS.findIndex(p => p.key === key); return i >= 0 ? i : PERIODS.length; };
        const rows = entries.slice().sort((a, b) =>
            (pIdx(a.period || groupOf(a)) - pIdx(b.period || groupOf(b))) ||
            ((a.start ? toMinutes(a.start) : 1e9) - (b.start ? toMinutes(b.start) : 1e9)) ||
            (a.id - b.id)).filter(filterMatch);

        $('lgRows').innerHTML = rows.length
            ? rows.map(rowHtml).join('')
            : (entries.length
                ? '<div class="lg-empty"><i class="bi bi-funnel" aria-hidden="true"></i>' + esc(I18N.filterEmpty) + '</div>'
                : '<div class="lg-empty"><i class="bi bi-journal" aria-hidden="true"></i>' + esc(I18N.empty) + '</div>');
    }

    /* Quiet summary when idle; a live "exactly what will be saved" preview while
       typing — read from the state, so manual edits and text agree. */
    function renderMeta(parsed) {
        if (input.value.trim()) {
            const title = state.kind === 'journal' ? input.value.trim() : (parsed.title || input.value.trim());
            const bits = [title || I18N.noTitle];
            if (state.kind !== 'journal') {
                if (state.start && state.end) { bits.push(NUM(state.start) + '–' + NUM(state.end)); }
                else if (state.amount) { bits.push(NUM(state.amount) + ' ' + unitLabel(state.unit)); }
                if (state.period) { bits.push(periodLabel(state.period)); }
            }
            if (state.mood != null) { bits.push(I18N.moodLabel + ' ' + NUM(state.mood) + '/' + NUM(10)); }
            const note = $('lgNote').value.trim();
            if (note) { bits.push(note); }
            meta.style.color = '#6d28d9';
            meta.innerHTML = '<b>' + esc(bits[0]) + '</b>' + bits.slice(1).map(b => ' · ' + esc(b)).join('');
            return;
        }

        const secs = entries.reduce((s, e) => s + (e.seconds || 0), 0);
        const journals = entries.filter(e => e.kind === 'journal').length;
        const activities = entries.length - journals;
        meta.style.color = '';
        meta.innerHTML = entries.length
            ? esc(I18N.loggedToday) + ' <b>' + esc(secs ? humanDuration(secs) : I18N.untimed) + '</b>' +
              '<span class="lg-dot"></span><b>' + NUM(activities) + '</b> ' + esc(plural(activities, I18N.activityOne, I18N.activityMany)) +
              (journals ? '<span class="lg-dot"></span><b>' + NUM(journals) + '</b> ' + esc(plural(journals, I18N.moodOne, I18N.moodMany)) : '')
            : '';
    }

    const hasTime = () => state.kind !== 'journal' && !!(state.amount || (state.start && state.end) || state.period);

    function timePillText() {
        if (state.kind === 'journal') { return I18N.setTime; }
        if (state.start && state.end) { return NUM(state.start) + '–' + NUM(state.end); }
        if (state.amount) { return NUM(state.amount) + ' ' + unitLabel(state.unit); }
        if (state.period) { return periodLabel(state.period); }
        return I18N.setTime;
    }

    /* The mood field mirrors the state — empty means "not filled in". */
    function syncMoodField() {
        const sel = $('lgMood');
        const value = state.mood == null ? '' : String(state.mood);
        if (sel.value !== value) { sel.value = value; }
    }

    function renderPills() {
        $('lgTimeLabel').textContent = timePillText();
        $('lgPillTime').classList.toggle('is-on', hasTime());
        $('lgPillMood').classList.toggle('is-on', state.mood != null);
        syncMoodField();
        $('lgCount').textContent = NUM(entries.length);
        $('lgSave').disabled = !input.value.trim();
        document.querySelectorAll('[data-lg-val]').forEach(b =>
            b.classList.toggle('active', Number(b.dataset.lgVal) === state.amount));
    }

    /* Insights candidates come from the server (repeat counts); the live
       session nudge stays client-side. One suggestion at a time. */
    let srvAsk = { habit: [], quit: [] };
    const askCache = {};
    async function refreshAsk() {
        try {
            const res = await plFetch(LG_ROUTES.candidates + '?date=' + encodeURIComponent(LG_DATE), {
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) { throw new Error('HTTP ' + res.status); }
            const j = await res.json();
            srvAsk = { habit: j.habit || [], quit: j.quit || [] };
        } catch (err) {
            console.error('[DayLog] candidates failed', err);
            srvAsk = { habit: [], quit: [] };
        }
        renderAsk();
    }

    function renderAsk() {
        const queued = [];
        Object.keys(sessionAdds).forEach(name => {
            if (sessionAdds[name] >= 3 && !dismissed['live:' + name]) { queued.push({ id: 'live:' + name, name: name, live: true }); }
        });
        srvAsk.habit.forEach(h => {
            if (!dismissed['srv:' + h.name]) { queued.push({ id: 'srv:' + h.name, name: h.name, count: h.count }); }
        });
        srvAsk.quit.forEach(q => {
            if (!dismissed['srv:' + q.name]) { queued.push({ id: 'srv:' + q.name, name: q.name, count: q.count, quit: true }); }
        });

        const ask = queued[0];
        const insights = document.getElementById('lgInsights');
        if (!ask) { $('lgAsk').innerHTML = ''; if (insights) { insights.hidden = true; } return; }
        if (insights) { insights.hidden = false; }
        askCache[ask.id] = ask;

        const text = ask.live
            ? esc(I18N.liveNudge).replace(':name', '<b>' + esc(ask.name) + '</b>')
            : (ask.quit
                ? esc(I18N.quitNudge).replace(':name', '<b>' + esc(ask.name) + '</b>').replace(':count', '<b>' + NUM(ask.count || 4) + '</b>')
                : esc(I18N.habitNudge).replace(':name', '<b>' + esc(ask.name) + '</b>').replace(':count', '<b>' + NUM(ask.count || 5) + '</b>'));

        $('lgAsk').innerHTML =
            '<div class="lg-ask ' + (ask.quit ? 'quit' : '') + '">' +
                '<i class="bi ' + (ask.quit ? 'bi-fire' : 'bi-lightbulb') + '" aria-hidden="true"></i>' +
                '<span>' + text + '</span>' +
                '<span class="lg-ask-acts">' +
                    '<button type="button" data-lg-ask-yes="' + ask.id + '">' + esc(ask.quit ? I18N.quitYes : I18N.habitYes) + '</button>' +
                    '<button type="button" class="later" data-lg-ask-later="' + ask.id + '">' + esc(I18N.later) + '</button>' +
                '</span>' +
            '</div>';
    }

    function renderChips() {
        $('lgPeriodChips').innerHTML = PERIODS.map(p =>
            '<button type="button" class="lg-chip ' + (state.period === p.key ? 'active' : '') + '"' +
            ' data-lg-period="' + p.key + '"><i class="bi ' + p.icon + '" aria-hidden="true"></i> ' + p.label + '</button>').join('');
    }

    function render() {
        renderRows();
        renderMeta(parseInline(input.value));
        renderPills();
        renderAsk();
        renderChips();
        renderSeg();
        renderTools();
        hookShutdown();
    }

    /* ── v2: explicit entry-type segment (activity / sentence / mood).
       The segment only changes when the user clicks it — opening the mood
       input must never move the selection under the user's finger. */
    let segKind = 'activity';   // activity | journal | mood

    /* One place decides what the composer is. A mood can ride on any entry,
       so it is a plain detail and never forces a kind of its own. */
    function setComposerKind(kind) {
        segKind = kind === 'activity' ? 'activity' : (kind === 'mood' ? 'mood' : 'journal');
        state.kind = segKind === 'activity' ? 'activity' : 'journal';
        input.placeholder = state.kind === 'journal'
            ? @json(__('What did you feel today? One sentence…'))
            : @json(__('e.g. played piano 45m…'));
        /* Dimmed, never hidden: the row must not jump under the finger. */
        $('lgPillTime').disabled = state.kind === 'journal';
        if (state.kind === 'journal' && $('lgPanelTime').hidden === false) { togglePanel('time'); }
    }

    function renderSeg() {
        document.querySelectorAll('[data-lg-kind]').forEach(b => {
            const on = b.dataset.lgKind === segKind;
            b.classList.toggle('active', on);
            b.setAttribute('aria-selected', String(on));
        });
    }
    document.querySelectorAll('[data-lg-kind]').forEach(b => b.addEventListener('click', () => {
        setComposerKind(b.dataset.lgKind);
        const wantMood = segKind === 'mood';
        if (wantMood && $('lgPanelMood').hidden) { togglePanel('mood'); }
        else if (!wantMood && $('lgPanelMood').hidden === false) { togglePanel('mood'); }
        render();
        input.focus();
    }));

    /* ── v2 features: filter · merge · editable clock · floating-timer link · shutdown nudge ── */
    let lgFilter = 'all';
    let lgTimer = null;   // { lgId, entryId, lastElapsed }

    const fmtLive = s => {
        s = Math.max(0, Math.floor(s || 0));
        return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    };
    const periodForHour = h => {
        let best = null;
        PERIODS.forEach(p => { if (h >= p.at - 3) { best = p.key; } });
        return best || (PERIODS.length ? PERIODS[0].key : null);
    };
    const filterMatch = e => lgFilter === 'all' ? true
        : lgFilter === 'activity' ? e.kind === 'activity'
        : lgFilter === 'mood' ? (e.kind === 'journal' && !!e.moodPicked)
        : (e.kind === 'journal' && !e.moodPicked);

    /* Mergeable = same activity title (case-insensitive) logged 2+ times, all time-based. */
    function dupGroups() {
        const map = {};
        entries.forEach(e => {
            if (e.kind !== 'activity') { return; }
            const k = String(e.title || '').trim().toLowerCase();
            if (!k) { return; }
            (map[k] = map[k] || []).push(e);
        });
        return Object.values(map).filter(g => g.length > 1 && g.every(x => x.seconds));
    }

    function renderTools() {
        const counts = { all: entries.length, activity: 0, journal: 0, mood: 0 };
        entries.forEach(e => { counts[e.kind === 'journal' ? (e.moodPicked ? 'mood' : 'journal') : 'activity']++; });
        document.querySelectorAll('[data-lg-filter]').forEach(b => {
            const k = b.dataset.lgFilter;
            b.classList.toggle('active', k === lgFilter);
            if (!b.dataset.lgBase) { b.dataset.lgBase = b.textContent.trim(); }
            b.innerHTML = esc(b.dataset.lgBase) + ' <span class="lg-fcount">' + NUM(counts[k] || 0) + '</span>';
        });
        const dups = dupGroups();
        const mergeBtn = $('lgMerge');
        if (mergeBtn) {
            mergeBtn.hidden = !dups.length;
            $('lgMergeCount').textContent = NUM(dups.reduce((s, g) => s + g.length, 0));
        }
    }
    document.querySelectorAll('[data-lg-filter]').forEach(b => b.addEventListener('click', () => {
        lgFilter = b.dataset.lgFilter;
        render();
    }));

    $('lgMerge').addEventListener('click', async () => {
        const groups = dupGroups();
        if (!groups.length) { return; }
        try {
            for (const g of groups) {
                const j = await lgApi(LG_ROUTES.merge, 'POST', { ids: g.map(x => x.id) });
                const surv = mapServerEntry(j.survivor);
                (j.merged_ids || []).forEach(id => {
                    const i = entries.findIndex(x => x.id === id);
                    if (i > -1) { entries.splice(i, 1); }
                });
                const local = entries.find(x => x.id === surv.id);
                if (local) { Object.assign(local, surv); }
            }
            render();
            refreshAsk();
            plShowToast(I18N.merged);
        } catch (err) {
            console.error('[DayLog] merge failed', err);
            plShowToast(apiErr(err, I18N.updateFail));
            loadDay();
        }
    });

    /* Editable logged hour: the clock becomes a time field. */
    function timeEdit(row, entry) {
        const cell = row.querySelector('.lg-row-time');
        if (!cell || cell.querySelector('input')) { return; }
        const field = document.createElement('input');
        field.type = 'time';
        field.className = 'lg-time-edit';
        field.value = entry.loggedAt || '';
        field.setAttribute('aria-label', I18N.edit);
        cell.replaceChildren(field);
        field.focus();
        let done = false;
        const commit = async () => {
            if (done) { return; }
            done = true;
            const v = field.value || '';
            const m = v.match(/^(\d{2}):(\d{2})$/);
            if (!m || Number(m[1]) >= 24 || Number(m[2]) >= 60) { render(); plShowToast(I18N.invalidTime); return; }
            if (v === entry.loggedAt) { render(); return; }
            const prev = entry.loggedAt;
            entry.loggedAt = v;
            render();
            try {
                const j = await lgApi(LG_ROUTES.update(entry.id), 'PATCH', { logged_at: v });
                Object.assign(entry, mapServerEntry(j.entry));
                entry.color = colorFor(entry.title);
                render();
            } catch (err) {
                console.error('[DayLog] time update failed', err);
                entry.loggedAt = prev;
                render();
                plShowToast(apiErr(err, I18N.updateFail));
            }
        };
        const cancel = () => { if (done) { return; } done = true; render(); };
        field.addEventListener('keydown', ev => {
            ev.stopPropagation();
            if (ev.key === 'Enter') { ev.preventDefault(); commit(); }
            if (ev.key === 'Escape') { ev.preventDefault(); cancel(); }
        });
        field.addEventListener('change', commit);
        field.addEventListener('blur', commit);
    }

    /* ── Floating-timer link: start from a row, duration lands back on stop. ── */
    function lgTimerTick(snap) {
        if (!lgTimer) { return; }
        if (snap.entry && snap.entry.id === lgTimer.entryId) {
            lgTimer.lastElapsed = snap.elapsed || 0;
            const el = document.querySelector('[data-lg-live="' + lgTimer.lgId + '"]');
            if (el) { el.textContent = NUM(fmtLive(lgTimer.lastElapsed)); }
            return;
        }
        if (!snap.entry) { flushLgTimer(); }
    }
    function snapshotTimeFields(s) {
        return { duration_seconds: s.seconds || null, amount: s.amount != null ? s.amount : null,
                 amount_unit: s.amount != null ? (s.unitKey || 'minute') : null,
                 start_time: s.start || null, end_time: s.end || null, period: s.period || null };
    }
    function flushLgTimer() {
        if (!lgTimer) { return; }
        const t = lgTimer;
        lgTimer = null;
        const e = entries.find(x => x.id === t.lgId);
        const secs = Math.max(0, Math.floor(t.lastElapsed || 0));
        if (e && secs > 0) {
            const prev = snapshotEntry(e);
            delete e.amount; delete e.amountUnit; e.unitKey = null; delete e.start; delete e.end;
            e.seconds = secs;
            e.mode = 'duration';
            if (!e.period) { e.period = periodForHour(new Date().getHours()); }
            render();
            const payload = { duration_seconds: secs, amount: null, amount_unit: null,
                              start_time: null, end_time: null, period: e.period };
            lgApi(LG_ROUTES.update(e.id), 'PATCH', payload).then(j => {
                Object.assign(e, mapServerEntry(j.entry));
                e.color = colorFor(e.title);
                render();
                refreshAsk();
                plShowToast(I18N.timerSaved + ': ' + humanDuration(secs), async () => {
                    try {
                        const r = await lgApi(LG_ROUTES.update(e.id), 'PATCH', snapshotTimeFields(prev));
                        Object.assign(e, mapServerEntry(r.entry));
                        e.color = colorFor(e.title);
                        render();
                        refreshAsk();
                    } catch (err) { plShowToast(apiErr(err, I18N.updateFail)); }
                });
            }).catch(err => {
                console.error('[DayLog] timer save failed', err);
                plShowToast(apiErr(err, I18N.updateFail));
                loadDay();
            });
        } else {
            render();
        }
    }
    function lgTimerToggle(entry) {
        if (!window.TM || !window.TM.start) { return; }
        if (lgTimer && lgTimer.lgId === entry.id) {
            window.TM.stop().catch(() => {});
            return;
        }
        if (lgTimer) { flushLgTimer(); }
        window.TM.start({ description: entry.title || '', category: 'other' }).then(j => {
            if (!j) { return; }
            lgTimer = { lgId: entry.id, entryId: j.id, lastElapsed: 0 };
            render();
            plShowToast(I18N.timerStarted + ': ' + entry.title);
        }).catch(() => plShowToast(I18N.timerFail));
    }
    if (window.TM && window.TM.subscribe) { try { window.TM.subscribe(lgTimerTick); } catch (err) { /* no timer */ } }

    /* ── Nightly nudge: hook the shutdown ritual, offer one-tap quick log. ── */
    function hookShutdown() {
        if (typeof window.openEveningShutdown !== 'function') { return; }
        if (window.openEveningShutdown.__lgHooked) { return; }
        const orig = window.openEveningShutdown;
        window.openEveningShutdown = function () { orig(); setTimeout(lgShutdownNudge, 350); };
        window.openEveningShutdown.__lgHooked = true;
    }
    function lgShutdownNudge() {
        const body = document.querySelector('#eveningShutdownModal .modal-body');
        if (!body) { return; }
        let box = body.querySelector('[data-lg-nudge]');
        if (!box) {
            box = document.createElement('div');
            box.className = 'lg-shutdown-nudge';
            box.setAttribute('data-lg-nudge', '');
            body.prepend(box);
        }
        box.innerHTML = '<i class="bi bi-journal-text" aria-hidden="true"></i>' +
            '<span><b>' + esc(I18N.logsToday.replace(':count', NUM(entries.length))) + '</b> ' + esc(I18N.unloggedQ) + '</span>' +
            '<button type="button" data-lg-nudge-log>' + esc(I18N.quickLog) + '</button>';
    }
    document.addEventListener('click', ev => {
        if (!ev.target.closest('[data-lg-nudge-log]')) { return; }
        const m = document.getElementById('eveningShutdownModal');
        if (m && typeof bootstrap !== 'undefined') { try { bootstrap.Modal.getInstance(m).hide(); } catch (err) { /* noop */ } }
        const sec = document.getElementById('lgSection');
        if (sec) { sec.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        setTimeout(() => { input.focus(); }, 450);
    });

    /* ── panels: one pill open at a time ── */
    const PANELS = {
        time: { panel: 'lgPanelTime', pill: 'lgPillTime' },
        note: { panel: 'lgPanelNote', pill: 'lgPillNote' },
        mood: { panel: 'lgPanelMood', pill: 'lgPillMood' },
    };

    function togglePanel(name) {
        const open = $(PANELS[name].panel).hidden;
        Object.keys(PANELS).forEach(key => {
            const isTarget = key === name && open;
            $(PANELS[key].panel).hidden = !isTarget;
            $(PANELS[key].pill).setAttribute('aria-expanded', String(isTarget));
            $(PANELS[key].pill).classList.toggle('is-on', isTarget ||
                (key === 'time' ? hasTime() : key === 'mood' && state.mood != null));
        });
        if (open) {
            const focusTarget = $(PANELS[name].panel).querySelector('input, select, button');
            if (focusTarget && name !== 'time') { focusTarget.focus(); }
        }
    }

    /* The panel is a view of the state — there is no mode to switch between. */
    function syncTimeFields() {
        const shown = state.amount === null ? '' : String(state.amount);
        if ($('lgAmount').value !== shown) { $('lgAmount').value = shown; }
        $('lgUnit').selectedIndex = state.unit;
        if (state.start && state.end) { $('lgStart').value = state.start; $('lgEnd').value = state.end; }
        $('lgFieldsRange').hidden = !(state.start && state.end);
    }

    function resetTime() {
        state.amount = null;
        state.period = null;
        state.start = null;
        state.end = null;
        syncTimeFields();
    }

    /* ── input + suggestions ── */
    function openSuggest() {
        const q = (parseInline(input.value).title || '').trim();
        let list = catalog.slice().sort((a, b) => b.uses - a.uses);
        if (q) { list = list.filter(c => c.name.indexOf(q) >= 0); }
        suggestBox.innerHTML = list.slice(0, 5).map(c =>
            '<div class="lg-suggest-item" data-lg-pick="' + c.id + '" role="option">' +
                '<i class="bi ' + c.icon + '" aria-hidden="true"></i>' + esc(c.name) +
                '<small>' + NUM(c.uses) + ' ' + esc(I18N.timesBefore) + '</small>' +
            '</div>').join('') ||
            '<div class="lg-suggest-empty">' + esc(I18N.newOne) + '</div>';
        suggestBox.classList.add('show');
        activeIdx = -1;
    }
    const closeSuggest = () => suggestBox.classList.remove('show');

    function pick(id) {
        const c = catalog.find(x => x.id === id);
        if (!c) { return; }
        input.value = c.name;
        state.actId = c.id;
        if (c.unitKey) {
            state.unit = unitIdxOf(c.unitKey);
            syncTimeFields();
        }
        closeSuggest();
        render();
        input.focus();
    }

    input.addEventListener('focus', openSuggest);
    input.addEventListener('input', () => {
        const parsed = parseInline(input.value);
        state.actId = null;
        /* An emptied composer is a fresh entry: nothing may leak from the last
           sentence into the next one. */
        if (!input.value.trim()) { resetTime(); $('lgNote').value = ''; state.mood = null; }
        else if (parsed.note) { $('lgNote').value = parsed.note; }
        applyParsed(parsed);
        render();
        openSuggest();
    });

    input.addEventListener('keydown', ev => {
        const items = Array.from(suggestBox.querySelectorAll('.lg-suggest-item'));
        if (ev.key === 'ArrowDown' && items.length) {
            ev.preventDefault(); activeIdx = Math.min(activeIdx + 1, items.length - 1);
        } else if (ev.key === 'ArrowUp' && items.length) {
            ev.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0);
        } else if (ev.key === 'Enter') {
            ev.preventDefault();
            if (suggestBox.classList.contains('show') && activeIdx >= 0 && items[activeIdx]) {
                pick(items[activeIdx].dataset.lgPick);
            } else {
                save();
            }
            return;
        } else if (ev.key === 'Escape') {
            if (suggestBox.classList.contains('show')) { closeSuggest(); }
            else if (input.value) { resetComposer(); render(); }
            return;
        } else {
            activeIdx = -1;
        }
        items.forEach((el, i) => el.classList.toggle('active', i === activeIdx));
    });

    suggestBox.addEventListener('mousedown', ev => {
        const item = ev.target.closest('[data-lg-pick]');
        if (!item) { return; }
        ev.preventDefault();
        pick(item.dataset.lgPick);
    });

    document.addEventListener('click', ev => {
        if (!ev.target.closest('.lg-hero')) { closeSuggest(); }
    });

    /* Text wins wherever it says something; the panel keeps what it was told. */
    function applyParsed(parsed) {
        if (state.kind === 'journal') { return; }
        if (parsed.period) { state.period = parsed.period; }
        if (parsed.start && parsed.end) { state.start = parsed.start; state.end = parsed.end; }
        if (parsed.amount) { state.amount = parsed.amount; state.unit = parsed.unit; }
        syncTimeFields();
    }

    /* ── pills ── */
    $('lgPillTime').addEventListener('click', () => togglePanel('time'));
    $('lgPillNote').addEventListener('click', () => togglePanel('note'));
    $('lgPillMood').addEventListener('click', () => {
        /* Just opens the mood field — the selected entry type stays put. */
        togglePanel('mood');
        render();
    });

    /* Panel edits are as real as the sentence: both feed the same state. */
    $('lgAmount').addEventListener('input', e => { state.amount = Number(e.target.value) || null; render(); });
    $('lgUnit').addEventListener('change', e => { state.unit = e.target.selectedIndex; render(); });
    document.querySelectorAll('[data-lg-val]').forEach(b => b.addEventListener('click', () => {
        const minutes = Number(b.dataset.lgVal);
        const isHour = minutes >= 60 && minutes % 60 === 0;
        const same = state.amount !== null && state.amount * (state.unit === UNITS.hour ? 60 : 1) === minutes;
        state.amount = same ? null : (isHour ? minutes / 60 : minutes);
        if (!same) { state.unit = isHour ? UNITS.hour : UNITS.minute; }
        syncTimeFields();
        render();
    }));
    $('lgStart').addEventListener('input', e => { state.start = e.target.value; render(); });
    $('lgEnd').addEventListener('input', e => { state.end = e.target.value; render(); });
    $('lgPeriodChips').addEventListener('click', ev => {
        const b = ev.target.closest('[data-lg-period]');
        if (!b) { return; }
        state.period = state.period === b.dataset.lgPeriod ? null : b.dataset.lgPeriod;   // tap again to clear
        render();
    });
    $('lgClearTime').addEventListener('click', () => { resetTime(); render(); });
    $('lgMood').addEventListener('change', e => {
        state.mood = e.target.value === '' ? null : Number(e.target.value);
        render();
    });

    /* One place decides what an entry is: the sentence first, the panel second. */
    function buildEntry(parsed) {
        const isJournal = state.kind === 'journal';
        const title = isJournal ? input.value.trim() : (parsed.title || input.value.trim());
        const entry = { id: 'tmp' + Date.now(), actId: state.actId, title: title, kind: state.kind,
                        note: $('lgNote').value.trim() || null, unitKey: unitKeyOf(state.unit),
                        mood: state.mood, moodPicked: segKind === 'mood' };
        const now = new Date();
        entry.loggedAt = pad(now.getHours()) + ':' + pad(now.getMinutes());

        if (isJournal) {
            return Object.assign(entry, { mode: 'none', period: null, color: '#a16207' });
        }

        const period = parsed.period || state.period || null;

        if (state.start && state.end) {
            Object.assign(entry, { mode: 'range', start: state.start, end: state.end,
                                  seconds: secondsBetween(state.start, state.end) });
        } else if (state.amount) {
            entry.mode = 'duration';
            const seconds = secondsOf(state.amount, state.unit);
            if (seconds) { entry.seconds = seconds; }
            else { entry.amount = state.amount; entry.amountUnit = unitLabel(state.unit); }
        } else {
            entry.mode = period ? 'period' : 'none';
        }

        /* A clock range already implies a time of day — fill it, never overwrite. */
        entry.period = period || (entry.mode === 'range' ? groupOf(entry) : null);
        return entry;
    }

    /* ── save via API — Enter keeps focus for rapid logging ── */
    async function save() {
        const entry = buildEntry(parseInline(input.value));
        if (!entry.title) { return; }
        $('lgSave').disabled = true;
        try {
            const j = await lgApi(LG_ROUTES.store, 'POST', entryToPayload(entry));
            const saved = mapServerEntry(j.entry);
            entries.push(saved);
            if (saved.kind !== 'journal') {
                sessionAdds[saved.title] = (sessionAdds[saved.title] || 0) + 1;
                const hit = catalog.find(x => x.name === saved.title);
                if (hit) { hit.uses += 1; }
            }
            resetComposer();
            render();
            refreshAsk();
            flashRow(saved.id);
            input.focus();
            plShowToast(I18N.logged + ': ' + (saved.kind === 'journal' ? I18N.moodSentence : saved.title));
        } catch (err) {
            console.error('[DayLog] save failed', err);
            plShowToast(apiErr(err, I18N.saveFail));
            render();
        }
    }

    function resetComposer() {
        input.value = '';
        $('lgNote').value = '';
        state.actId = null;
        state.mood = null;
        resetTime();
        closeSuggest();
    }

    /* Land the eye on the new row without moving the page. */
    function flashRow(id) {
        const row = $('lgRows').querySelector('.lg-row[data-lg-id="' + id + '"]');
        if (!row) { return; }
        row.classList.add('is-new');
        setTimeout(() => row.classList.remove('is-new'), 1100);
    }

    $('lgSave').addEventListener('click', save);

    /* ── row actions ── */
    $('lgRows').addEventListener('click', ev => {
        const btn = ev.target.closest('[data-lg-act]');
        if (!btn) { return; }
        const row = btn.closest('.lg-row');
        const entry = entries.find(x => x.id === Number(row.dataset.lgId));
        if (!entry) { return; }

        if (btn.dataset.lgAct === 'delete') {
            const idx = entries.indexOf(entry);
            const snap = snapshotEntry(entry);
            entries.splice(idx, 1);
            render();
            lgApi(LG_ROUTES.destroy(entry.id), 'DELETE').then(() => refreshAsk()).catch(err => {
                console.error('[DayLog] delete failed', err);
                entries.splice(Math.min(idx, entries.length), 0, entry);
                render();
                plShowToast(I18N.deleteFail);
            });
            plShowToast(I18N.removed, async () => {
                try {
                    const j = await lgApi(LG_ROUTES.store, 'POST', snapshotToPayload(snap));
                    entries.splice(Math.min(idx, entries.length), 0, mapServerEntry(j.entry));
                    render();
                    refreshAsk();
                } catch (err) { plShowToast(apiErr(err, I18N.saveFail)); }
            });
        } else if (btn.dataset.lgAct === 'habit') {
            openHabitForm(entry);
        } else if (btn.dataset.lgAct === 'quit') {
            const next = !entry.isQuit;
            entry.isQuit = next;
            render();
            lgApi(LG_ROUTES.update(entry.id), 'PATCH', { is_quit: next }).then(() => {
                refreshAsk();
                plShowToast(next ? I18N.quitMarked : I18N.quitUnmarked);
            }).catch(err => {
                console.error('[DayLog] quit flag failed', err);
                entry.isQuit = !next;
                render();
                plShowToast(apiErr(err, I18N.updateFail));
            });
        } else if (btn.dataset.lgAct === 'activity') {
            const period = state.period || 'evening';
            entry.kind = 'activity';
            entry.mode = 'period';
            entry.period = period;
            entry.color = '#7c3aed';
            /* Keep the mood value — activities may carry one too. */
            entry.moodPicked = false;
            render();
            lgApi(LG_ROUTES.update(entry.id), 'PATCH', { kind: 'activity', period, mood_picked: false }).then(j => {
                Object.assign(entry, mapServerEntry(j.entry));
                entry.color = colorFor(entry.title);
                render();
                refreshAsk();
                plShowToast(I18N.turned);
            }).catch(err => {
                console.error('[DayLog] convert failed', err);
                plShowToast(apiErr(err, I18N.updateFail));
                loadDay();
            });
        } else if (btn.dataset.lgAct === 'edit') {
            inlineEdit(row, entry);
        } else if (btn.dataset.lgAct === 'time') {
            timeEdit(row, entry);
        } else if (btn.dataset.lgAct === 'timer') {
            lgTimerToggle(entry);
        }
    });

    $('lgRows').addEventListener('dblclick', ev => {
        const titleEl = ev.target.closest('[data-lg-title]');
        if (!titleEl) { return; }
        const entry = entries.find(x => x.id === Number(titleEl.closest('.lg-row').dataset.lgId));
        if (entry) { inlineEdit(titleEl.closest('.lg-row'), entry); }
    });

    function inlineEdit(row, entry) {
        const titleEl = row.querySelector('[data-lg-title]');
        const previous = titleEl.textContent;
        const field = document.createElement('input');
        field.type = 'text';
        field.className = 'lg-edit';
        field.value = entry.title;
        field.setAttribute('aria-label', I18N.edit);
        titleEl.replaceChildren(field);
        field.focus();
        field.select();
        let done = false;
        const commit = async () => {
            if (done) { return; }
            done = true;
            const next = field.value.trim();
            if (!next || next === entry.title) { render(); return; }
            const prev = entry.title;
            entry.title = next;
            entry.color = colorFor(next);
            render();
            try {
                const j = await lgApi(LG_ROUTES.update(entry.id), 'PATCH', { title: next });
                Object.assign(entry, mapServerEntry(j.entry));
                entry.color = colorFor(entry.title);
                render();
                refreshAsk();
            } catch (err) {
                console.error('[DayLog] title update failed', err);
                entry.title = prev;
                entry.color = colorFor(prev);
                render();
                plShowToast(apiErr(err, I18N.updateFail));
            }
        };
        field.addEventListener('keydown', e => {
            if (e.key === 'Enter') { commit(); }
            if (e.key === 'Escape') { done = true; titleEl.textContent = previous; }
        });
        field.addEventListener('blur', commit);
    }

    /* Reuses the real quick-add habit form instead of adding a second modal. */
    function openHabitForm(entry) {
        if (typeof openQuickAdd !== 'function') { return; }
        openQuickAdd('routine');
        const panel = document.querySelector('[data-qa-panel="routine"]');
        if (!panel) { return; }
        const title = panel.querySelector('[name="title"]');
        if (title) { title.value = entry.title; }
        const period = panel.querySelector('[name="time_period"]');
        if (period && entry.period) { period.value = entry.period; }
        plShowToast(I18N.prefilled);
    }

    /* ── one suggestion at a time ── */
    $('lgAsk').addEventListener('click', ev => {
        const later = ev.target.closest('[data-lg-ask-later]');
        const yes = ev.target.closest('[data-lg-ask-yes]');

        if (later) {
            dismissed[later.dataset.lgAskLater] = true;
            renderAsk();
            plShowToast(I18N.beStill);
            return;
        }
        if (!yes) { return; }

        const id = yes.dataset.lgAskYes;
        const ask = askCache[id];
        dismissed[id] = true;
        renderAsk();
        openHabitForm({ title: ask ? ask.name : '', period: state.period });
    });

    /* Nothing is set until the user says so: the panel starts empty, not at 45. */
    syncTimeFields();
    render();
    loadDay();
})();
</script>
@endpush
