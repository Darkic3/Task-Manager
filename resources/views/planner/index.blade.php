@extends('layouts.app')

@section('title', 'My Day')

@push('styles')
<style>
    .main-content { padding:14px 16px; background:#f7f8fa; min-height:100vh; }

    /* Header */
    .pl-header {
        background:linear-gradient(135deg,#7c3aed 0%,#5b21b6 100%);
        border-radius:10px; padding:12px 18px; color:white; margin-bottom:14px;
        position:relative; overflow:hidden; border:1px solid #6d28d9;
        box-shadow:0 2px 8px rgba(124,58,237,.3);
    }
    .pl-header::before {
        content:''; position:absolute; top:0; right:0; width:90px; height:90px;
        background:rgba(255,255,255,.08); border-radius:50%; transform:translate(24px,-24px);
    }
    .pl-header-title{font-weight:700;font-size:18px;margin:0;position:relative;z-index:1;}
    .pl-header-sub  {font-size:12.5px;opacity:.85;margin:2px 0 0;position:relative;z-index:1;}

    /* Toolbar */
    .pl-toolbar {
        display:flex; align-items:center; justify-content:space-between; gap:10px;
        background:white; border:1px solid #e3e4e8; border-radius:9px;
        padding:8px 12px; margin-bottom:14px; flex-wrap:wrap;
    }
    .pl-toggle{display:flex;background:#f0f1f3;border-radius:7px;padding:3px;gap:2px;}
    .pl-toggle-btn{
        padding:5px 14px;border-radius:5px;font-size:12px;font-weight:600;
        color:#8a8f98;text-decoration:none;display:flex;align-items:center;gap:5px;transition:all .15s;
    }
    .pl-toggle-btn.active{background:white;color:#1a1d23;box-shadow:0 1px 3px rgba(0,0,0,.1);}
    .pl-nav{display:flex;align-items:center;gap:6px;}
    .pl-nav-btn{
        width:30px;height:30px;display:flex;align-items:center;justify-content:center;
        border:1px solid #e3e4e8;border-radius:7px;background:white;color:#6b7385;
        text-decoration:none;transition:all .15s;
    }
    .pl-nav-btn:hover{border-color:#c4b5fd;color:#7c3aed;background:#faf5ff;}
    .pl-today-btn{
        padding:5px 14px;border:1px solid #e3e4e8;border-radius:7px;background:white;
        color:#3d4149;font-size:12px;font-weight:600;text-decoration:none;transition:all .15s;
    }
    .pl-today-btn:hover{border-color:#c4b5fd;color:#7c3aed;background:#faf5ff;}

    /* Stat chips */
    .pl-stats{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
    .pl-stat{
        display:flex;align-items:center;gap:8px;background:white;border:1px solid #e3e4e8;
        border-radius:9px;padding:8px 14px;font-size:12.5px;color:#6b7385;font-weight:600;
    }
    .pl-stat strong{font-size:15px;color:#1a1d23;}
    .pl-stat.overdue strong{color:#dc2626;}

    /* Section */
    .pl-section{background:white;border:1px solid #e3e4e8;border-radius:10px;overflow:hidden;margin-bottom:14px;}
    .pl-section-head{
        display:flex;align-items:center;gap:8px;padding:10px 16px;
        background:#fafbfc;border-bottom:1px solid #e3e4e8;
    }
    .pl-section-head i{font-size:14px;}
    .pl-section-title{font-size:13px;font-weight:700;color:#1a1d23;}
    .pl-section-count{
        margin-left:auto;background:#f0f1f3;border-radius:20px;padding:1px 9px;
        font-size:11px;font-weight:700;color:#8a8f98;
    }
    .pl-section-body{padding:8px;display:flex;flex-direction:column;gap:6px;}
    .pl-empty{padding:22px;text-align:center;color:#adb0b8;font-size:12.5px;}
    .pl-empty i{display:block;font-size:24px;margin-bottom:6px;color:#c4c9d4;}

    /* Task row */
    .pl-task{
        display:flex;align-items:flex-start;gap:10px;background:white;border:1px solid #eceef1;
        border-radius:8px;padding:9px 11px;transition:all .15s;
    }
    .pl-task:hover{box-shadow:0 3px 10px rgba(0,0,0,.07);border-color:#d8dae0;}
    .pl-check{position:relative;flex-shrink:0;margin-top:1px;cursor:pointer;}
    .pl-check input{position:absolute;opacity:0;width:0;height:0;}
    .pl-check-box{
        width:19px;height:19px;border:2px solid #c4c9d4;border-radius:6px;
        display:flex;align-items:center;justify-content:center;color:transparent;
        font-size:11px;transition:all .15s;
    }
    .pl-check:hover .pl-check-box{border-color:#7c3aed;}
    .pl-check input:checked + .pl-check-box{background:#16a34a;border-color:#16a34a;color:white;}
    .pl-task-body{flex:1;min-width:0;}
    .pl-task-title{font-size:13px;font-weight:600;color:#1a1d23;line-height:1.35;word-break:break-word;}
    .pl-task.is-done .pl-task-title{text-decoration:line-through;color:#adb0b8;}
    .pl-expand{
        margin-left:auto;flex-shrink:0;width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;
        border:none;background:transparent;color:#adb0b8;cursor:pointer;border-radius:6px;font-size:12px;
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
    .pl-modal-backdrop{position:absolute;inset:0;background:rgba(17,20,26,.5);}
    .pl-modal-dialog{
        position:relative;background:#fff;border-radius:14px;width:min(560px,96vw);max-height:88vh;
        display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;
    }
    .pl-modal-head{display:flex;align-items:flex-start;gap:10px;padding:16px 18px 12px;border-bottom:1px solid #eef0f3;}
    .pl-modal-title{font-size:15px;font-weight:800;color:#1a1d23;}
    .pl-modal-sub{font-size:12px;color:#8a8f98;margin-top:2px;}
    .pl-modal-x{
        margin-left:auto;border:none;background:#f2f3f5;color:#6b7385;width:30px;height:30px;
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
    .pl-modal-body .pl-time-field{width:100%;justify-content:flex-start;}
    .pl-modal-body .pl-time-field input[type="time"]{flex:1;width:auto;font-size:15px;padding:8px 0;}
    body.pl-modal-open{overflow:hidden;}
    .pl-details{display:none;}
    .pl-details.open{display:block;}
    .pl-task-title{display:flex;align-items:center;gap:4px;}
    .pl-task-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:4px;}
    .pl-priority{font-size:10.5px;font-weight:700;padding:1px 8px;border-radius:20px;text-transform:uppercase;letter-spacing:.3px;}
    .pl-proj,.pl-due{font-size:11px;color:#8a8f98;display:inline-flex;align-items:center;gap:4px;}
    .pl-due.overdue{color:#dc2626;font-weight:600;}
    .pl-task-open{
        flex-shrink:0;width:26px;height:26px;display:flex;align-items:center;justify-content:center;
        color:#c4c9d4;text-decoration:none;border-radius:6px;transition:all .15s;
    }
    .pl-task-open:hover{color:#7c3aed;background:#faf5ff;}

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

    /* ── Routine metric logging (value + sets) ── */
    .pl-log{display:flex;align-items:center;gap:6px;margin-top:7px;flex-wrap:wrap;}
    .pl-log input{width:110px;padding:4px 9px;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;outline:none;}
    .pl-log input:focus{border-color:#c4b5fd;}
    /* clock-time field: alarm icon + native time picker fused into one pill */
    .pl-time-field{
        display:inline-flex;align-items:center;gap:7px;padding:0 11px;
        border:1.5px solid #ddd6fe;border-radius:11px;
        background:linear-gradient(180deg,#fdfcff 0%,#f6f3ff 100%);
        transition:border-color .15s,box-shadow .15s;
    }
    .pl-time-field > i{color:#7c3aed;font-size:13px;}
    .pl-time-field:hover{border-color:#c4b5fd;}
    .pl-time-field:focus-within{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.14);}
    .pl-time-field input[type="time"]{
        border:none;background:transparent;outline:none;box-shadow:none;
        width:90px;padding:6px 0;font-size:13.5px;font-weight:700;color:#1a1d23;
        letter-spacing:.6px;font-variant-numeric:tabular-nums;color-scheme:light;
    }
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

    /* ── Next Up card ── */
    .pl-next-wrap{margin-bottom:14px;}
    .pl-next-wrap[hidden]{display:none;}
    .pl-next-card{
        display:flex;align-items:center;gap:12px;flex-wrap:wrap;
        background:linear-gradient(135deg,#faf5ff 0%,#fff 60%);
        border:1px solid #ddd6fe;border-radius:11px;padding:11px 14px;
        box-shadow:0 2px 8px rgba(124,58,237,.08);
    }
    .pl-next-label{
        display:inline-flex;align-items:center;gap:5px;flex-shrink:0;
        font-size:10.5px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;
        color:#7c3aed;background:#ede9fe;border-radius:20px;padding:3px 10px;
    }
    .pl-next-body{flex:1;min-width:180px;}
    .pl-next-title{font-size:14px;font-weight:700;color:#1a1d23;display:flex;align-items:center;gap:7px;}
    .pl-next-title i{color:#7c3aed;font-size:15px;}
    .pl-next-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:4px;}
    .pl-next-actions{display:flex;align-items:center;gap:7px;margin-left:auto;}
    .pl-next-actions button{
        display:inline-flex;align-items:center;gap:5px;border-radius:8px;
        font-size:12.5px;font-weight:700;cursor:pointer;padding:6px 13px;transition:all .15s;
    }
    .pl-next-step{
        display:flex;align-items:center;gap:8px;flex-wrap:wrap;
        width:100%;background:#fafbfc;border:1px dashed #ddd6fe;border-radius:9px;
        padding:7px 11px;margin-top:1px;
    }
    .pl-next-step > i{color:#7c3aed;font-size:13px;flex-shrink:0;}
    .pl-next-step-name{font-size:12.5px;font-weight:700;color:#3d4149;}
    .pl-next-set-inputs{display:inline-flex;gap:5px;margin-left:auto;}
    .pl-next-set-inputs input{
        width:74px;padding:4px 8px;border:1px solid #e5e7eb;border-radius:7px;
        font-size:12px;outline:none;font-variant-numeric:tabular-nums;
    }
    .pl-next-set-inputs input:focus{border-color:#c4b5fd;}
    .pl-start-hint{font-size:11px;font-weight:600;color:#8a8f98;}
    .pl-next-start{background:#7c3aed;color:#fff;border:1px solid #7c3aed;}
    .pl-next-start:hover{background:#6d28d9;}
    .pl-next-done{background:#fff;color:#16a34a;border:1px solid #bbf7d0;}
    .pl-next-done:hover{background:#f0fdf4;}

    /* ── Quick add ── */
    .pl-toolbar-right{display:flex;align-items:center;gap:8px;}
    .pl-add-btn{
        display:inline-flex;align-items:center;gap:6px;
        background:#7c3aed;color:#fff;border:1px solid #7c3aed;border-radius:7px;
        padding:6px 14px;font-size:12.5px;font-weight:700;cursor:pointer;transition:all .15s;
    }
    .pl-add-btn:hover{background:#6d28d9;}
    .pl-fab{
        display:none;position:fixed;right:18px;bottom:18px;z-index:1000;
        width:52px;height:52px;border-radius:50%;border:none;
        background:#7c3aed;color:#fff;font-size:22px;cursor:pointer;
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
        $rangeLabel = $view === 'week'
            ? $start->format('M j') . ' – ' . $end->format('M j, Y')
            : $date->format('l, F j, Y');
        $prevDate = $view === 'week' ? $date->copy()->subWeek() : $date->copy()->subDay();
        $nextDate = $view === 'week' ? $date->copy()->addWeek() : $date->copy()->addDay();
    @endphp

    {{-- Header --}}
    <div class="pl-header">
        <h1 class="pl-header-title">{{ $view === 'week' ? 'My Week' : 'My Day' }}</h1>
        <p class="pl-header-sub">{{ $rangeLabel }}{{ $isToday ? ' · Today' : '' }}</p>
    </div>

    {{-- Toolbar --}}
    <div class="pl-toolbar">
        <div class="pl-toggle">
            <a href="{{ route('planner.index', ['view' => 'day', 'date' => $date->toDateString()]) }}"
               class="pl-toggle-btn {{ $view === 'day' ? 'active' : '' }}">
                <i class="bi bi-sun"></i> Day
            </a>
            <a href="{{ route('planner.index', ['view' => 'week', 'date' => $date->toDateString()]) }}"
               class="pl-toggle-btn {{ $view === 'week' ? 'active' : '' }}">
                <i class="bi bi-calendar-week"></i> Week
            </a>
        </div>
        <div class="pl-toolbar-right">
            <div class="pl-nav">
                <a href="{{ route('planner.index', ['view' => $view, 'date' => $prevDate->toDateString()]) }}"
                   class="pl-nav-btn" title="Previous"><i class="bi bi-chevron-left"></i></a>
                <a href="{{ route('planner.index', ['view' => $view]) }}" class="pl-today-btn">Today</a>
                <a href="{{ route('planner.index', ['view' => $view, 'date' => $nextDate->toDateString()]) }}"
                   class="pl-nav-btn" title="Next"><i class="bi bi-chevron-right"></i></a>
            </div>
            @if($view === 'day')
                <button type="button" class="pl-add-btn" onclick="openQuickAdd('task')">
                    <i class="bi bi-plus-lg"></i> Add
                </button>
            @endif
        </div>
    </div>

    @if($view === 'day')
        {{-- Stats --}}
        <div class="pl-stats">
            <div class="pl-stat"><i class="bi bi-arrow-repeat"></i> Routines <strong><span id="plRoutineDone">{{ $routineDone }}</span><span style="color:#adb0b8;font-weight:600;">/{{ $routineTotal }}</span></strong></div>
            <div class="pl-stat"><i class="bi bi-list-check"></i> Pending <strong id="plPendingCount">{{ $pending->count() }}</strong></div>
            <div class="pl-stat"><i class="bi bi-check-circle"></i> Done <strong id="plDoneCount">{{ $done->count() }}</strong></div>
            @if($overdue->count())
                <div class="pl-stat overdue"><i class="bi bi-exclamation-triangle"></i> Overdue <strong>{{ $overdue->count() }}</strong></div>
            @endif
        </div>

        {{-- Next Up --}}
        @include('planner._next-up', ['nextUp' => $nextUp ?? null, 'date' => $date])

        {{-- Routines for the selected day --}}
        <div class="pl-section">
            <div class="pl-section-head">
                <i class="bi bi-arrow-repeat" style="color:#7c3aed;"></i>
                <span class="pl-section-title">{{ $isToday ? "Today's Routines" : 'Routines' }}</span>
                <span class="pl-section-count" id="plRoutineSectionCount">{{ $routineTotal }}</span>
            </div>
            <div class="pl-section-body" id="plRoutinesBody">
                @forelse($routines as $routine)
                    @include('planner._routine-row', ['routine' => $routine, 'routineDate' => $date, 'count' => true])
                @empty
                    <div class="pl-empty"><i class="bi bi-arrow-repeat"></i>No routines scheduled for this day.</div>
                @endforelse
            </div>
        </div>

        {{-- Overdue --}}
        @if($overdue->count())
            <div class="pl-section">
                <div class="pl-section-head">
                    <i class="bi bi-exclamation-triangle-fill" style="color:#dc2626;"></i>
                    <span class="pl-section-title">Overdue</span>
                    <span class="pl-section-count">{{ $overdue->count() }}</span>
                </div>
                <div class="pl-section-body">
                    @foreach($overdue as $task)
                        @include('planner._task-row', ['task' => $task, 'count' => false])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Today's tasks --}}
        <div class="pl-section">
            <div class="pl-section-head">
                <i class="bi bi-check2-square" style="color:#7c3aed;"></i>
                <span class="pl-section-title">{{ $isToday ? "Today's Tasks" : 'Tasks' }}</span>
                <span class="pl-section-count" id="plTodaySectionCount">{{ $pending->count() }}</span>
            </div>
            <div class="pl-section-body" id="plPendingBody">
                @forelse($pending as $task)
                    @include('planner._task-row', ['task' => $task, 'count' => true])
                @empty
                    <div class="pl-empty"><i class="bi bi-cup-hot"></i>Nothing scheduled for this day. Enjoy!</div>
                @endforelse
            </div>
        </div>

        {{-- Done --}}
        @if($done->count())
            <div class="pl-section">
                <div class="pl-section-head">
                    <i class="bi bi-check-circle-fill" style="color:#16a34a;"></i>
                    <span class="pl-section-title">Completed</span>
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
                    <span class="pl-qa-title" id="plQaTitle">Quick add</span>
                    <button type="button" class="pl-qa-x" data-qa-close aria-label="Close">&times;</button>
                </div>
                <div class="pl-qa-tabs" role="tablist">
                    <button type="button" class="pl-qa-tab active" data-qa-tab="task">Task</button>
                    <button type="button" class="pl-qa-tab" data-qa-tab="routine">Routine</button>
                </div>

                <form class="pl-qa-form" data-qa-panel="task"
                      data-url="{{ route('planner.quick-add.task') }}"
                      onsubmit="return submitQuickTask(this)">
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <input type="text" name="title" class="pl-qa-input" placeholder="What needs to be done?" autocomplete="off">
                    <div class="pl-qa-row">
                        <select name="priority" aria-label="Priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                        <select name="project_id" aria-label="Project">
                            <option value="">Inbox</option>
                            @foreach(($quickProjects ?? []) as $proj)
                                <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pl-qa-row">
                        <select name="time_period" aria-label="Time of day">
                            <option value="">Anytime</option>
                            @foreach(config('routines.periods', []) as $key => $period)
                                <option value="{{ $key }}">{{ $period['label'] }}</option>
                            @endforeach
                        </select>
                        <input type="time" name="due_time" aria-label="Exact time">
                    </div>
                    <div class="pl-qa-chips" data-qa-minutes>
                        <button type="button" data-min="15">15m</button>
                        <button type="button" data-min="30">30m</button>
                        <button type="button" data-min="60">1h</button>
                        <button type="button" data-min="90">1h 30m</button>
                    </div>
                    <input type="hidden" name="estimated_minutes" value="">
                    <div class="pl-qa-actions">
                        <button type="submit">Add task</button>
                    </div>
                </form>

                <form class="pl-qa-form" data-qa-panel="routine" hidden
                      data-url="{{ route('planner.quick-add.routine') }}"
                      onsubmit="return submitQuickRoutine(this)">
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <input type="text" name="title" class="pl-qa-input" placeholder="Routine name" autocomplete="off">
                    <div class="pl-qa-row">
                        <select name="frequency" aria-label="Frequency">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="every_n_days">Every other day</option>
                        </select>
                        <select name="time_period" aria-label="Time of day">
                            <option value="">Anytime</option>
                            @foreach(config('routines.periods', []) as $key => $period)
                                <option value="{{ $key }}">{{ $period['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pl-qa-actions">
                        <button type="submit">Add routine</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
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

    async function toggleTask(cb) {
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
    }

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
        const input = box.querySelector('input');
        const isTime = input.type === 'time';
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
        } catch (e) {
            if (e.message !== 'GONE') console.error('[Planner] log sets failed', e);
        } finally {
            btn.disabled = false;
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
            plInsertRow('plPendingBody', json.html);
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

    async function plCompleteNext() {
        const wrap = document.getElementById('plNextUp');
        const card = wrap ? wrap.querySelector('.pl-next-card') : null;
        if (!card) return;
        const type = card.dataset.nextType;
        const id = card.dataset.nextId;
        const url = card.dataset.nextUrl;
        const date = wrap.dataset.nextDate;
        const stepBox = card.querySelector('[data-next-step]');
        const allDone = card.dataset.nextAllDone === '1';

        try {
            if (type === 'task') {
                /* With open steps: tick just the first unfinished step (+ note it). */
                if (stepBox && stepBox.dataset.stepId) {
                    const res = await plFetch(stepBox.dataset.stepUrl, { method: 'POST', headers: { 'Accept': 'application/json' } });
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
            } else if (stepBox && stepBox.dataset.logvalue === '1') {
                /* Value-tracked routine: log the guided input — the log itself completes the routine. */
                const input = stepBox.querySelector('[data-next-value-input]');
                let value = null;
                if (input && input.value) {
                    value = input.type === 'time'
                        ? (input.value.split(':').reduce((a, p) => a * 60 + Number(p), 0))
                        : parseFloat(input.value);
                }
                if (value === null || isNaN(value)) {
                    if (input) {
                        input.classList.remove('pl-flash');
                        void input.offsetWidth;
                        input.classList.add('pl-flash');
                        input.focus();
                    }
                    return;
                }
                const logRes = await plFetch(stepBox.dataset.logUrl + '?date=' + encodeURIComponent(date), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ value: value }),
                });
                if (!logRes.ok) throw new Error('HTTP ' + logRes.status);
                const logJson = await logRes.json();
                if (logJson.routine_completed) {
                    applyRoutineToggle(id, date, true);
                    if (logJson.streak != null) setStreak(id, logJson.streak);
                    refreshRoutineCounters();
                    maybeCelebrate();
                }
            } else if (stepBox && stepBox.dataset.logsets === '1' && stepBox.dataset.stepId) {
                /* Tracked sets step: log the typed numbers — the server auto-ticks the step, no extra toggle. */
                const sets = {};
                stepBox.querySelectorAll('[data-set]').forEach(inp => {
                    if (inp.value !== '' && !isNaN(parseFloat(inp.value))) sets[inp.dataset.set] = parseFloat(inp.value);
                });
                if (!Object.keys(sets).length && stepBox.dataset.logged !== '1') {
                    /* No numbers typed — flash the inputs and stop. */
                    stepBox.querySelectorAll('[data-set]').forEach(inp => {
                        inp.classList.remove('pl-flash');
                        void inp.offsetWidth;
                        inp.classList.add('pl-flash');
                    });
                    const first = stepBox.querySelector('[data-set]');
                    if (first) first.focus();
                    return;
                }
                if (!Object.keys(sets).length) {
                    /* Numbers already logged before (unticked from elsewhere) — the plain tick is allowed. */
                    const res = await plFetch(stepBox.dataset.stepUrl + '?date=' + encodeURIComponent(date), {
                        method: 'POST', headers: { 'Accept': 'application/json' },
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const tickJson = await res.json();
                    setStepChip(tickJson.item_id, date, tickJson.completed);
                    refreshStepCounts();
                    if (tickJson.routine_completed !== undefined) {
                        applyRoutineToggle(tickJson.routine_id, date, !!tickJson.routine_completed);
                        if (tickJson.routine_completed && tickJson.streak != null) setStreak(tickJson.routine_id, tickJson.streak);
                        refreshRoutineCounters();
                        if (tickJson.routine_completed) maybeCelebrate();
                    }
                    await refreshNextUp();
                    return;
                }
                const logRes = await plFetch(stepBox.dataset.logUrl + '?date=' + encodeURIComponent(date), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ item_id: stepBox.dataset.stepId, sets: sets }),
                });
                if (!logRes.ok) throw new Error('HTTP ' + logRes.status);
                const logJson = await logRes.json();
                /* Sync the list-row chips with what the log actually did. */
                if (logJson.steps_done) {
                    const ids = Object.keys(logJson.steps_done);
                    const doneCount = ids.filter(k => logJson.steps_done[k]).length;
                    ids.forEach(itemId => setStepChip(itemId, date, logJson.steps_done[itemId], true));
                    setTaskRowStepProgress(id, doneCount, ids.length, logJson.routine_completed, 'routine');
                } else {
                    setStepChip(stepBox.dataset.stepId, date, true, true);
                }
                if (logJson.routine_completed) {
                    applyRoutineToggle(id, date, true);
                    refreshRoutineCounters();
                    maybeCelebrate();
                }
            } else if (stepBox && stepBox.dataset.stepId && !allDone) {
                /* Tick only the first unfinished step; full toggle only when every step is done. */
                const res = await plFetch(stepBox.dataset.stepUrl + '?date=' + encodeURIComponent(date), {
                    method: 'POST', headers: { 'Accept': 'application/json' },
                });
                if (res.status === 422) {
                    /* Server needs a logged number first (tracked step). */
                    stepBox.querySelectorAll('[data-set]').forEach(inp => {
                        inp.classList.remove('pl-flash');
                        void inp.offsetWidth;
                        inp.classList.add('pl-flash');
                    });
                    return;
                }
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

    /* Enter inside the Next Up card inputs submits like "Complete step". */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.matches('#plNextUp [data-set], #plNextUp [data-next-value-input]')) {
            e.preventDefault();
            plCompleteNext();
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
</script>
@endpush
