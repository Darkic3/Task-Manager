@extends('layouts.app')

@section('title', 'My Day')

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
    /* Clicking the routine row expands its details */
    .pl-routine[data-routine-item] .pl-task-title,
    .pl-routine[data-routine-item] .pl-task-meta{cursor:pointer;}

    /* ── Routine detail modal (big / tracked routines) ── */
    .pl-modal{position:fixed;inset:0;z-index:1090;display:flex;align-items:center;justify-content:center;padding:16px;}
    .pl-modal[hidden]{display:none;}
    .pl-modal-backdrop{position:absolute;inset:0;background:rgba(17,20,26,.5);backdrop-filter:blur(4px);}
    .pl-modal-dialog{
        position:relative;background:#fff;border-radius:16px;width:min(560px,96vw);max-height:88vh;
        display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;
    }
    .pl-modal-head{display:flex;align-items:flex-start;gap:10px;padding:16px 18px 12px;border-bottom:1px solid #eef0f3;}
    .pl-modal-title{font-size:15px;font-weight:800;color:#1a1d23;}
    .pl-modal-sub{font-size:12px;color:#8a8f98;margin-top:2px;}
    .pl-modal-x{
        margin-inline-start:auto;border:none;background:#f2f3f5;color:#6b7385;width:30px;height:30px;
        border-radius:8px;font-size:17px;line-height:1;cursor:pointer;flex-shrink:0;
    }
    .pl-modal-x:hover{background:#e6e8ec;color:#1a1d23;}
    .pl-modal-body{padding:14px 18px;overflow-y:auto;}
    .pl-modal-body .pl-details{display:block;}
    .pl-modal-body .pl-logsets{gap:9px;}
    .pl-modal-body .pl-logset{padding:9px 11px;}
    .pl-modal-body .pl-logset-name{font-size:12.5px;}
    .pl-modal-body .pl-logset input{width:76px;padding:5px 9px;font-size:12.5px;}
    .pl-modal-body .pl-steps{gap:7px;}
    .pl-modal-body .pl-step{font-size:12px;padding:4px 12px;}
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
    .pl-modal-body .pl-step-schedule{font-size:9.5px;}
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

    /* Week grid */
    .pl-week{display:grid;grid-template-columns:repeat(7,minmax(150px,1fr));gap:10px;overflow-x:auto;padding-bottom:4px;}
    @media(max-width:1100px){ .pl-week{grid-template-columns:repeat(7,minmax(160px,1fr));} }
    .pl-day{background:white;border:1px solid #e3e4e8;border-radius:10px;overflow:hidden;min-height:140px;}
    .pl-day.is-today{border-color:#c4b5fd;box-shadow:0 0 0 2px rgba(124,58,237,.12);}
    .pl-day-head{
        display:flex;align-items:center;justify-content:space-between;
        padding:9px 12px;background:#fafbfc;border-bottom:1px solid #e3e4e8;
    }
    .pl-day.is-today .pl-day-head{background:#faf5ff;}
    .pl-day-name{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#8a8f98;}
    .pl-day-date{font-size:14px;font-weight:700;color:#1a1d23;}
    .pl-day.is-today .pl-day-date{color:#7c3aed;}
    .pl-day-count{font-size:11px;font-weight:700;color:#adb0b8;background:#f0f1f3;border-radius:20px;padding:1px 8px;}
    .pl-day-body{padding:7px;display:flex;flex-direction:column;gap:6px;}
    .pl-day .pl-task{padding:7px 9px;}
    .pl-day .pl-task-title{font-size:12px;}
    .pl-day-empty{padding:14px 8px;text-align:center;color:#c4c9d4;font-size:11px;}
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
        } else {
            $navDateLabel = $date->isCurrentWeek() ? __('This Week') : __('Week');
        }
        $rangeLabel = $view === 'week'
            ? ($isFa ? app_date($start, 'd F') . ' – ' . app_date($end, 'd F Y') : $start->format('M j') . ' – ' . $end->format('M j, Y'))
            : app_human_date($date);
        $prevDate = $view === 'week' ? $date->copy()->subWeek() : $date->copy()->subDay();
        $nextDate = $view === 'week' ? $date->copy()->addWeek() : $date->copy()->addDay();
    @endphp

    {{-- Header --}}
    <div class="pl-header">
        <h1 class="pl-header-title">{{ $view === 'week' ? __('My Week') : __('My Day') }}</h1>
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
        </div>
        <div class="pl-toolbar-right">
            @if($view === 'day')
                <div class="d-flex align-items-center gap-2">
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
            <div class="pl-stat"><i class="bi bi-arrow-repeat"></i> {{ __('Routines') }} <strong><span id="plRoutineDone">{{ $routineDone }}</span><span style="color:#adb0b8;font-weight:600;">/{{ $routineTotal }}</span></strong></div>
            <div class="pl-stat"><i class="bi bi-list-check"></i> {{ __('Pending') }} <strong id="plPendingCount">{{ $pending->count() }}</strong></div>
            <div class="pl-stat"><i class="bi bi-check-circle"></i> {{ __('Completed') }} <strong id="plDoneCount">{{ $done->count() }}</strong></div>
            @if($overdue->count())
                <div class="pl-stat overdue"><i class="bi bi-exclamation-triangle"></i> {{ __('Overdue') }} <strong id="plOverdueStat">{{ $overdue->count() }}</strong></div>
            @endif
        </div>


        {{-- Day progress (tasks + routines combined) --}}
        <div class="pl-daybar">
            <div class="pl-daybar-track"><div class="pl-daybar-fill" id="plDayProgressFill"></div></div>
            <span class="pl-daybar-label" id="plDayProgressLabel"></span>
        </div>

        {{-- Next Up --}}
        @include('planner._next-up', ['nextUp' => $nextUp ?? null, 'date' => $date])

        {{-- Today's Workout Session Block --}}
        @include('planner._workout-card')

        {{-- Routines for the selected day --}}
        <div class="pl-section">
            <div class="pl-section-head">
                <i class="bi bi-arrow-repeat" style="color:#7c3aed;"></i>
                <span class="pl-section-title">{{ $isToday ? __("Today's Routines") : __('Routines') }}</span>
                <span class="pl-section-count" id="plRoutineSectionCount">{{ $routineTotal }}</span>
            </div>
            <div class="pl-section-body" id="plRoutinesBody">
                @forelse($routines as $routine)
                    @include('planner._routine-row', ['routine' => $routine, 'routineDate' => $date, 'count' => true])
                @empty
                    <div class="pl-empty"><i class="bi bi-arrow-repeat"></i>{{ __('No routines scheduled for this day.') }}</div>
                @endforelse
            </div>
        </div>

        {{-- Overdue --}}
        @if($overdue->count())
            <div class="pl-section" style="border-color: #fecaca; box-shadow: 0 2px 10px rgba(220, 38, 38, 0.05);">
                <div class="pl-section-head" style="background: linear-gradient(135deg, #fffafb 0%, #fff1f2 100%); border-bottom: 1px solid #fee2e2;">
                    <i class="bi bi-exclamation-triangle-fill" style="color:#dc2626;"></i>
                    <span class="pl-section-title" style="color:#991b1b;">{{ __('Overdue') }}</span>
                    <span class="pl-section-count" id="plOverdueCount" style="background:#fee2e2;color:#b91c1c;">{{ $overdue->count() }}</span>
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

        {{-- Today's tasks --}}
        <div class="pl-section">
            <div class="pl-section-head">
                <i class="bi bi-check2-square" style="color:#7c3aed;"></i>
                <span class="pl-section-title">{{ $isToday ? __("Today's Tasks") : __('Tasks') }}</span>
                <span class="pl-section-count" id="plTodaySectionCount">{{ $pending->count() }}</span>
            </div>
            <div class="pl-section-body" id="plPendingBody">
                @php $groups = $pending->groupBy(fn ($t) => $t->time_period ?: 'anytime'); @endphp
                @forelse($pending as $task)@empty
                    <div class="pl-empty"><i class="bi bi-cup-hot"></i>{{ __('Nothing scheduled for this day. Enjoy!') }}</div>
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

        {{-- Done --}}
        @if($done->count())
            <div class="pl-section">
                <div class="pl-section-head">
                    <i class="bi bi-check-circle-fill" style="color:#16a34a;"></i>
                    <span class="pl-section-title">{{ __('Completed') }}</span>
                    <span class="pl-section-count">{{ $done->count() }}</span>
                </div>
                <div class="pl-section-body">
                    @foreach($done as $task)
                        @include('planner._task-row', ['task' => $task, 'count' => true])
                    @endforeach
                </div>
            </div>
        @endif
    @else
        {{-- Week view --}}
        <div class="pl-week">
            @foreach($days as $day)
                @php $dayDate = $day['date']; $isDayToday = $dayDate->isToday(); @endphp
                <div class="pl-day {{ $isDayToday ? 'is-today' : '' }}">
                    <div class="pl-day-head">
                        <div>
                            <div class="pl-day-name">{{ $dayDate->format('D') }}</div>
                            <div class="pl-day-date">{{ $dayDate->format('M j') }}</div>
                        </div>
                        <span class="pl-day-count" title="Routines done">{{ $day['routineDone'] }}/{{ $day['routineTotal'] }}</span>
                    </div>
                    <div class="pl-day-body">
                        @if(count($day['routines']))
                            @foreach($day['routines'] as $routine)
                                @include('planner._routine-row', ['routine' => $routine, 'routineDate' => $dayDate, 'count' => false])
                            @endforeach
                        @endif
                        @forelse($day['tasks'] as $task)
                            @include('planner._task-row', ['task' => $task, 'hideDue' => true, 'count' => false])
                        @empty
                            @if(!count($day['routines']))
                                <div class="pl-day-empty">—</div>
                            @endif
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    </div>

    <div id="plConfetti" aria-hidden="true"></div>
    <div id="plToast" role="status"></div>

    {{-- Routine detail modal: opened for big or tracked routines --}}
    <div class="pl-modal" id="plRoutineModal" hidden>
        <div class="pl-modal-backdrop" data-modal-close></div>
        <div class="pl-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="plModalTitle">
            <div class="pl-modal-head">
                <div>
                    <div class="pl-modal-title" id="plModalTitle" data-modal-title></div>
                    <div class="pl-modal-sub" data-modal-sub></div>
                </div>
                <button type="button" class="pl-modal-x" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="pl-modal-body" data-modal-body></div>
        </div>
    </div>

    @if($view === 'day')
        <button type="button" class="pl-fab" onclick="openQuickAdd('task')" aria-label="Quick add">
            <i class="bi bi-plus-lg"></i>
        </button>

        <div class="pl-qa" id="plQuickAdd" hidden>
            <div class="pl-qa-backdrop" data-qa-close></div>
            <div class="pl-qa-dialog" role="dialog" aria-modal="true" aria-labelledby="plQaTitle">
                <div class="pl-qa-head">
                    <span class="pl-qa-title" id="plQaTitle">{{ __('Quick Add') }}</span>
                    <button type="button" class="pl-qa-x" data-qa-close aria-label="{{ __('Close') }}">&times;</button>
                </div>
                <div class="pl-qa-tabs" role="tablist">
                    <button type="button" class="pl-qa-tab active" data-qa-tab="task">{{ __('Task') }}</button>
                    <button type="button" class="pl-qa-tab" data-qa-tab="routine">{{ __('Routine') }}</button>
                </div>

                <form class="pl-qa-form" data-qa-panel="task"
                      data-url="{{ route('planner.quick-add.task') }}"
                      onsubmit="return submitQuickTask(this)">
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <input type="text" name="title" class="pl-qa-input" placeholder="{{ __('What needs to be done?') }}" autocomplete="off">
                    <div class="pl-qa-row">
                        <select name="priority" aria-label="{{ __('Priority') }}">
                            <option value="low">{{ __('Low') }}</option>
                            <option value="medium" selected>{{ __('Medium') }}</option>
                            <option value="high">{{ __('High') }}</option>
                        </select>
                        <select name="project_id" aria-label="{{ __('Project') }}">
                            <option value="">{{ __('No Project') }}</option>
                            @foreach(($quickProjects ?? []) as $proj)
                                <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pl-qa-row">
                        <select name="time_period" aria-label="{{ __('Time of day') }}">
                            <option value="">{{ __('Anytime') }}</option>
                            @foreach(config('routines.periods', []) as $key => $period)
                                <option value="{{ $key }}">{{ __($period['label']) }}</option>
                            @endforeach
                        </select>
                        <input type="time" name="due_time" aria-label="{{ __('Exact time') }}">
                    </div>
                    <div class="pl-qa-chips" data-qa-minutes>
                        <button type="button" data-min="15">15m</button>
                        <button type="button" data-min="30">30m</button>
                        <button type="button" data-min="60">1h</button>
                        <button type="button" data-min="90">1h 30m</button>
                    </div>
                    <input type="hidden" name="estimated_minutes" value="">
                    <div class="pl-qa-actions">
                        <button type="submit">{{ __('Add task') }}</button>
                    </div>
                </form>

                <form class="pl-qa-form" data-qa-panel="routine" hidden
                      data-url="{{ route('planner.quick-add.routine') }}"
                      onsubmit="return submitQuickRoutine(this)">
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <input type="text" name="title" class="pl-qa-input" placeholder="{{ __('Routine name') }}" autocomplete="off">
                    <div class="pl-qa-row">
                        <select name="frequency" aria-label="{{ __('Frequency') }}">
                            <option value="daily">{{ __('Daily') }}</option>
                            <option value="weekly">{{ __('Weekly') }}</option>
                            <option value="monthly">{{ __('Monthly') }}</option>
                            <option value="every_n_days">{{ __('Every other day') }}</option>
                        </select>
                        <select name="time_period" aria-label="{{ __('Time of day') }}">
                            <option value="">{{ __('Anytime') }}</option>
                            @foreach(config('routines.periods', []) as $key => $period)
                                <option value="{{ $key }}">{{ __($period['label']) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pl-qa-actions">
                        <button type="submit">{{ __('Add routine') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @include('planner._focus-modal')
    @include('planner._kickoff-modal')
    @include('planner._ai-schedule-modal')
    @include('planner._next-up-modal')
    @include('planner._floating-focus-bar')
@endsection

@push('scripts')
<script>
    let PL_CSRF = '{{ csrf_token() }}';
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
            if (!silent) {
                plShowToast(json.completed ? 'Done ✓' : 'Reopened', () => {
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
        if (p) p.textContent = pending;
        if (d) d.textContent = done;
        if (t) t.textContent = pending;
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
                console.error('[Planner] routine toggle failed', e);
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
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        });
    }

    function applyRoutineToggle(id, date, completed) {
        document.querySelectorAll('[data-routine-item][data-id="' + id + '"][data-date="' + date + '"]').forEach(row => {
            row.classList.toggle('is-done', completed);
            row.dataset.completed = completed ? '1' : '0';
            const box = row.querySelector('input[type="checkbox"]');
            if (box) box.checked = completed;
        });
    }

    /* Reflect whole-routine toggles on the step chips too */
    function syncStepButtons(id, date, completed) {
        document.querySelectorAll('[data-step-item][data-routine="' + id + '"][data-date="' + date + '"]').forEach(btn => {
            btn.classList.toggle('done', completed);
            const i = btn.querySelector('i');
            if (i) i.className = 'bi ' + (completed ? 'bi-check-circle-fill' : 'bi-circle');
        });
        refreshStepCounts();
    }

    function refreshStepCounts() {
        document.querySelectorAll('[data-routine-item]').forEach(row => {
            const steps = row.querySelectorAll('[data-step-item]');
            if (!steps.length) return;
            const done = [...steps].filter(s => s.classList.contains('done')).length;
            const badge = row.querySelector('.steps-count');
            if (badge) {
                badge.textContent = done + '/' + steps.length;
                badge.classList.toggle('all', done === steps.length);
            }
        });
    }

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
                plShowToast('Saved ✓');
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
                plShowToast('Saved ✓');
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

    async function submitRoutineSlip(form) {
        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        try {
            const res = await plFetch(form.dataset.slipUrl + '?date=' + encodeURIComponent(form.dataset.date), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(avoidPayload(form)),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            await res.json();
            plShowToast('Slip logged');
            /* Slips move the card to another slot — reload for correct order. */
            location.reload();
        } catch (e) {
            console.error('[Planner] slip failed', e);
            plShowToast('Could not log the slip');
        } finally {
            btn.disabled = false;
        }
        return false;
    }

    async function submitStepSlip(form) {
        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        try {
            const res = await plFetch(form.dataset.slipUrl + '?date=' + encodeURIComponent(form.dataset.date), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(avoidPayload(form)),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            await res.json();
            plShowToast('Slip logged');
            location.reload();
        } catch (e) {
            console.error('[Planner] step slip failed', e);
            plShowToast('Could not log the slip');
        } finally {
            btn.disabled = false;
        }
        return false;
    }

    async function submitRoutineNote(form) {
        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        try {
            const res = await plFetch(form.dataset.noteUrl + '?date=' + encodeURIComponent(form.dataset.date), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(avoidPayload(form)),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            await res.json();
            plShowToast('Saved ✓');
            const panel = form.closest('[data-note-panel]');
            if (panel) panel.hidden = true;
            form.querySelector('input[name=note]').value = '';
        } catch (e) {
            console.error('[Planner] note failed', e);
            plShowToast('Could not save');
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
        try {
            const res = await plFetch(url + '?date=' + encodeURIComponent(wrap.dataset.nextDate), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PL_CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({}),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            plShowToast('Slip logged');
            location.reload();
        } catch (e) {
            console.error('[Planner] next-up slip failed', e);
            plShowToast('Could not log the slip');
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
            chev.title = open ? 'Hide details' : 'Show details';
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
        plModalState = { details, placeholder };

        const firstInput = plModalBody.querySelector('input');
        if (firstInput) setTimeout(() => firstInput.focus(), 60);
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
            if (chev) { chev.classList.add('open'); chev.title = 'Hide details'; }
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
            fl.textContent = '🔥' + streak;
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
        span.textContent = title + ' done ✓';
        toast.appendChild(span);
        const undo = document.createElement('button');
        undo.type = 'button';
        undo.textContent = 'Undo';
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
        if (rd) rd.textContent = done;
        if (rs) rs.textContent = total;
        if (window.plRefreshDayProgress) window.plRefreshDayProgress();

        // per-day counters in week view
        document.querySelectorAll('.pl-day').forEach(dayEl => {
            const routines = dayEl.querySelectorAll('[data-routine-item]');
            if (!routines.length) return;
            let d = 0;
            routines.forEach(r => { if (r.dataset.completed === '1') d++; });
            const countEl = dayEl.querySelector('.pl-day-count');
            if (countEl) countEl.textContent = d + '/' + routines.length;
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
            undo.textContent = 'Undo';
            undo.onclick = () => { hideRoutineToast(); undoFn(); };
            toast.appendChild(undo);
        }
        toast.classList.add('show');
        clearTimeout(plToastTimer);
        plToastTimer = setTimeout(hideRoutineToast, 4500);
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

    /* ── Quick add ── */
    const plQa = document.getElementById('plQuickAdd');

    function openQuickAdd(tab) {
        if (!plQa) return;
        plQa.hidden = false;
        document.body.classList.add('pl-modal-open');
        switchQuickTab(tab || 'task');
        const panel = plQa.querySelector('[data-qa-panel="' + (tab || 'task') + '"]');
        if (panel) setTimeout(() => { const inp = panel.querySelector('[name="title"]'); if (inp) inp.focus(); }, 40);
    }

    function closeQuickAdd() {
        if (!plQa) return;
        plQa.hidden = true;
        document.body.classList.remove('pl-modal-open');
    }

    function switchQuickTab(tab) {
        if (!plQa) return;
        plQa.querySelectorAll('[data-qa-tab]').forEach(b => b.classList.toggle('active', b.dataset.qaTab === tab));
        plQa.querySelectorAll('[data-qa-panel]').forEach(p => { p.hidden = p.dataset.qaPanel !== tab; });
    }

    if (plQa) {
        plQa.querySelectorAll('[data-qa-close]').forEach(b => b.addEventListener('click', closeQuickAdd));
        plQa.querySelectorAll('[data-qa-tab]').forEach(b => b.addEventListener('click', () => switchQuickTab(b.dataset.qaTab)));
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

    async function submitQuickTask(form) {
        const titleInput = form.querySelector('[name="title"]');
        const title = titleInput.value.trim();
        if (!title) { titleInput.focus(); return false; }
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
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
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            const gb = window.plEnsureGroup ? window.plEnsureGroup(json.group || 'anytime') : null;
            if (gb) {
                const wrap = document.createElement('div');
                wrap.innerHTML = json.html.trim();
                gb.appendChild(wrap.firstElementChild);
            } else {
                plInsertRow('plPendingBody', json.html);
            }
            refreshCounters();
            closeQuickAdd();
            form.reset();
            plQa.querySelectorAll('[data-qa-minutes] button').forEach(x => x.classList.remove('active'));
            plShowToast('Task added', () => plUndoDelete('task', json.task.id));
            refreshNextUp();
        } catch (e) {
            console.error('[Planner] quick add task failed', e);
        } finally {
            btn.disabled = false;
        }
        return false;
    }

    async function submitQuickRoutine(form) {
        const titleInput = form.querySelector('[name="title"]');
        const title = titleInput.value.trim();
        if (!title) { titleInput.focus(); return false; }
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
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
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            plInsertRow('plRoutinesBody', json.html);
            refreshRoutineCounters();
            closeQuickAdd();
            form.reset();
            plShowToast('Routine added', () => plUndoDelete('routine', json.routine.id));
            refreshNextUp();
        } catch (e) {
            console.error('[Planner] quick add routine failed', e);
        } finally {
            btn.disabled = false;
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
                badge.textContent = done + '/' + total;
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
            await plFetch('{{ route('time.start') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ task_id: taskId }),
            });
            await plFetch('{{ url('tasks') }}/' + taskId + '/update-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ status: 'in_progress' }),
            });
        } catch (e) {
            console.error('[Planner] next up start failed', e);
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
            ? { label: 'Anytime', icon: 'bi-inbox', color: '#64748b' }
            : (PL_PERIODS[key] || { label: key, icon: 'bi-clock', color: '#64748b' });
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
        chip.innerHTML = `<i class="bi ${def.icon || 'bi-clock'}"></i> ${def.label || key}`;
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
                overdueCount.textContent = overdueLeft;
                if (overdueLeft === 0) {
                    document.getElementById('plOverdueBody')?.closest('.pl-section')?.remove();
                    document.querySelector('.pl-stat.overdue')?.remove();
                } else {
                    const stat = document.getElementById('plOverdueStat');
                    if (stat) stat.textContent = overdueLeft;
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
        if (lbl) { lbl.textContent = total ? pct + '%' : ''; lbl.title = done + ' of ' + total + ' done'; }
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
                    plShowToast('Title updated');
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

    /* ── Live Floating Focus Bar & Timer Logic ── */
    let plActiveTimeEntry = null;
    let plTimerTicker = null;
    let plElapsedSeconds = 0;
    let plBreakMode = false;

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

    function updateFloatingClockDisplay() {
        const clockEl = document.getElementById('plFfbClock');
        if (clockEl) {
            clockEl.textContent = formatTimeDisplay(plElapsedSeconds);
        }
    }

    function renderFloatingBarState() {
        const bar = document.getElementById('plFloatingFocusBar');
        if (!bar) return;

        if (!plActiveTimeEntry && !plBreakMode) {
            bar.style.display = 'none';
            if (plTimerTicker) clearInterval(plTimerTicker);
            return;
        }

        bar.style.display = 'block';

        const titleEl = document.getElementById('plFfbTaskTitle');
        const projEl = document.getElementById('plFfbProject');
        const badgeEl = document.getElementById('plFfbStatusBadge');
        const pauseIcon = document.getElementById('plFfbPauseIcon');

        if (plBreakMode) {
            if (titleEl) titleEl.textContent = '☕ {{ __("Pomodoro Break") }}';
            if (projEl) projEl.textContent = '{{ __("Rest & Recharge") }}';
            if (badgeEl) {
                badgeEl.textContent = '{{ __("Break") }}';
                badgeEl.style.background = '#0284c7';
            }
            return;
        }

        if (titleEl) titleEl.textContent = plActiveTimeEntry.task?.title || plActiveTimeEntry.description || '{{ __("Active Focus Session") }}';
        if (projEl) projEl.textContent = plActiveTimeEntry.project?.name || '{{ __("General") }}';
        
        if (badgeEl) {
            if (plActiveTimeEntry.status === 'paused') {
                badgeEl.textContent = '{{ __("Paused") }}';
                badgeEl.style.background = '#64748b';
            } else {
                badgeEl.textContent = '{{ __("Focusing") }}';
                badgeEl.style.background = '#6366f1';
            }
        }

        if (pauseIcon) {
            pauseIcon.className = plActiveTimeEntry.status === 'paused' ? 'bi bi-play-fill' : 'bi bi-pause-fill';
        }
    }

    window.plStartTaskTimer = async function(taskId, title, project) {
        try {
            const res = await plFetch('/time/start', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ task_id: taskId })
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            
            plBreakMode = false;
            plActiveTimeEntry = data.active;
            plElapsedSeconds = data.active.elapsed || 0;
            renderFloatingBarState();
            updateFloatingClockDisplay();

            if (plTimerTicker) clearInterval(plTimerTicker);
            plTimerTicker = setInterval(() => {
                if (plActiveTimeEntry && plActiveTimeEntry.status === 'running') {
                    plElapsedSeconds++;
                    updateFloatingClockDisplay();
                }
            }, 1000);

            plShowToast('{{ __("Timer started") }}: ' + (title || 'Task'));
        } catch (err) {
            console.error('[Planner] start timer failed', err);
            plShowToast('{{ __("Error starting timer") }}');
        }
    };

    window.plToggleFloatingPause = async function() {
        if (!plActiveTimeEntry) return;
        const isPaused = plActiveTimeEntry.status === 'paused';
        const url = isPaused ? `/time/entries/${plActiveTimeEntry.id}/resume` : `/time/entries/${plActiveTimeEntry.id}/pause`;
        
        try {
            const res = await plFetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            plActiveTimeEntry = data.active;
            renderFloatingBarState();
        } catch (err) {
            console.error('[Planner] pause/resume failed', err);
        }
    };

    window.plStopFloatingTimer = async function() {
        if (!plActiveTimeEntry) return;
        try {
            const res = await plFetch(`/time/entries/${plActiveTimeEntry.id}/stop`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            
            if (plTimerTicker) clearInterval(plTimerTicker);
            plActiveTimeEntry = null;
            plBreakMode = false;
            renderFloatingBarState();
            plShowToast('{{ __("Timer stopped and time recorded") }}');
        } catch (err) {
            console.error('[Planner] stop timer failed', err);
        }
    };

    window.plStartBreak = function(minutes = 5) {
        if (plTimerTicker) clearInterval(plTimerTicker);
        plBreakMode = true;
        plElapsedSeconds = minutes * 60;
        renderFloatingBarState();
        updateFloatingClockDisplay();

        plTimerTicker = setInterval(() => {
            if (plElapsedSeconds > 0) {
                plElapsedSeconds--;
                updateFloatingClockDisplay();
            } else {
                clearInterval(plTimerTicker);
                plBreakMode = false;
                renderFloatingBarState();
                plShowToast('{{ __("Break finished! Ready to focus?") }}');
            }
        }, 1000);
    };

    window.plExpandToFocusWorkstation = function() {
        if (typeof openFocusWorkstation === 'function') {
            openFocusWorkstation(
                plActiveTimeEntry?.task?.id,
                plActiveTimeEntry?.task?.title,
                plActiveTimeEntry?.project?.name
            );
        }
    };

    window.plCloseFloatingBar = function() {
        const bar = document.getElementById('plFloatingFocusBar');
        if (bar) bar.style.display = 'none';
    };

    async function plInitActiveTimer() {
        try {
            const res = await plFetch('/time/active', {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data.active) {
                plActiveTimeEntry = data.active;
                plElapsedSeconds = data.active.elapsed || 0;
                renderFloatingBarState();
                updateFloatingClockDisplay();

                if (plTimerTicker) clearInterval(plTimerTicker);
                if (plActiveTimeEntry.status === 'running') {
                    plTimerTicker = setInterval(() => {
                        plElapsedSeconds++;
                        updateFloatingClockDisplay();
                    }, 1000);
                }
            }
        } catch (e) {
            /* silent */
        }
    }

    plInitActiveTimer();
})();
</script>
@endpush
