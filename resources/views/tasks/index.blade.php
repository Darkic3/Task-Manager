@extends('layouts.app')

@section('title', isset($project) ? $project->name . ' — ' . __('Tasks') : __('Tasks'))

@push('styles')
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
    .main-content { padding:20px 24px; background:#fafafa; min-height:100vh; }

    /* ─── Header — minimal ─── */
    .cu-header {
        background:transparent; border:none; box-shadow:none; border-radius:0;
        padding:0 0 14px; margin-bottom:14px; color:#1f2328; overflow:visible;
    }
    .cu-header::before{display:none;}
    .cu-header-title{font-weight:700;font-size:19px;margin:0;color:#1f2328;}
    .cu-header-sub  {font-size:13px;color:#8b8d98;margin:3px 0 0;}
    .cu-header a.back-link{color:#8b8d98;}
    .cu-header a.back-link:hover{color:#7c3aed;}

    /* ─── Toolbar ─── */
    .cu-toolbar {
        display:flex; align-items:center; justify-content:space-between; gap:10px;
        background:transparent; border:none; border-radius:0;
        padding:0; margin-bottom:18px; flex-wrap:wrap;
    }
    .cu-toolbar-left  {display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .cu-toolbar-right {display:flex;align-items:center;gap:10px;}
    .cu-view-toggle{display:flex;background:#f0f1f3;border-radius:6px;padding:3px;gap:2px;}
    .cu-view-btn{
        padding:5px 12px;border:none;background:transparent;border-radius:5px;
        font-size:12px;font-weight:600;color:#8b8d98;cursor:pointer;transition:all .15s;
        display:flex;align-items:center;gap:5px;border:none;
    }
    .cu-view-btn.active{background:white;color:#1f2328;box-shadow:0 1px 2px rgba(0,0,0,.06);}
    .cu-filter-select{
        border:1px solid #e5e7eb;border-radius:6px;padding:5px 10px;
        font-size:12px;color:#3d4149;background:white;cursor:pointer;outline:none;
    }
    .cu-filter-select:focus{border-color:#7c3aed;}
    .cu-search-input{
        border:1px solid #e5e7eb;border-radius:6px;padding:5px 10px;
        font-size:12px;color:#3d4149;background:white;width:200px;outline:none;
    }
    .cu-search-input:focus{border-color:#7c3aed;}
    .cu-btn-new{
        display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:6px;
        background:#7c3aed;color:white;border:none;font-size:12px;font-weight:600;
        cursor:pointer;transition:background .15s;text-decoration:none;
    }
    .cu-btn-new:hover{background:#6d28d9;color:white;}

    /* ─── Kanban — minimal ─── */
    .cu-kanban{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;align-items:start;}
    @media(max-width:1200px){.cu-kanban{grid-template-columns:repeat(3,minmax(0,1fr));}}
    @media(max-width:920px){
        .cu-kanban{display:flex;overflow-x:auto;gap:10px;padding-bottom:10px;scroll-snap-type:x proximity;scrollbar-width:thin;}
        .cu-kanban .cu-col{flex:0 0 272px;scroll-snap-align:start;}
    }
    @media(max-width:560px){
        .cu-kanban{display:grid;grid-template-columns:minmax(0,1fr);overflow:visible;padding-bottom:0;}
    }
    .cu-col{background:#f2f3f5;border-radius:8px;display:flex;flex-direction:column;}
    .cu-col-head{
        display:flex;align-items:center;gap:8px;
        padding:10px 12px;cursor:pointer;user-select:none;
    }
    .cu-col-chevron{
        width:18px;height:18px;border:none;background:transparent;color:#8b8d98;
        display:flex;align-items:center;justify-content:center;flex-shrink:0;
        transition:transform .15s;font-size:11px;padding:0;
    }
    .cu-col.collapsed .cu-col-chevron{transform:rotate(-90deg);}
    .cu-col-head-left{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:#4b5059;}
    .cu-col-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
    .cu-col-count{
        background:#e4e6ea;border-radius:20px;
        padding:1px 7px;font-size:11px;font-weight:600;color:#8b8d98;
    }
    .cu-col-actions{margin-left:auto;display:flex;gap:4px;}
    .cu-col-add{
        width:24px;height:24px;border-radius:5px;border:none;
        background:transparent;cursor:pointer;display:flex;align-items:center;
        justify-content:center;color:#8b8d98;font-size:13px;transition:all .15s;padding:0;
    }
    .cu-col-add:hover{background:#e4e6ea;color:#1f2328;}
    .cu-col-chevron-btn{
        width:24px;height:24px;border-radius:5px;border:none;
        background:transparent;cursor:pointer;display:flex;align-items:center;
        justify-content:center;color:#8b8d98;font-size:11px;transition:all .15s;padding:0;
    }
    .cu-col-chevron-btn:hover{background:#e4e6ea;color:#1f2328;}
    .cu-col.collapsed .cu-col-chevron-btn i{transform:rotate(-90deg);}
    .cu-col-chevron-btn i{transition:transform .15s;}
    .cu-col-body{padding:4px 8px 8px;min-height:100px;max-height:calc(100vh - 330px);overflow-y:auto;display:flex;flex-direction:column;gap:8px;scrollbar-width:thin;transition:background .12s, box-shadow .12s;border-radius:8px;}
    .cu-col.collapsed .cu-col-body{display:none;}
    .cu-col-body.drop-target{background:#ece9fd;box-shadow:inset 0 0 0 2px #c4b5fd;}

    /* ─── Task card — minimal ─── */
    .cu-task-card{
        background:white;border:1px solid #e5e7eb;border-radius:6px;
        padding:10px 10px 8px;cursor:grab;transition:border-color .12s, box-shadow .12s, opacity .12s, transform .12s;position:relative;
        -webkit-user-drag:element;user-select:none;-webkit-user-select:none;
    }
    .cu-task-card *{ -webkit-user-drag:none; }
    .cu-task-card:hover{border-color:#d3d7de;box-shadow:0 1px 3px rgba(0,0,0,.06);}
    .cu-task-card:hover .cu-task-menu-btn{opacity:1;}
    .cu-task-card.dragging{opacity:.45;transform:rotate(1.2deg) scale(.99);box-shadow:0 8px 20px rgba(0,0,0,.12);cursor:grabbing;}
    .cu-task-card.drop-before{box-shadow:inset 0 3px 0 0 #7c3aed;}
    .cu-task-card.drop-after{box-shadow:inset 0 -3px 0 0 #7c3aed;}
    .cu-grip{
        cursor:grab;color:#c9ccd3;font-size:13px;flex-shrink:0;
        display:flex;align-items:center;margin-top:1px;padding:2px 0;
    }
    .cu-grip:hover{color:#6b6f78;}
    .cu-grip:active{cursor:grabbing;}
    .cu-task-card.is-done .cu-task-title{color:#9ca0aa;text-decoration:line-through;text-decoration-color:#c7cad1;}
    .cu-task-main{display:flex;align-items:flex-start;gap:7px;}
    .cu-task-title{
        font-size:13.5px;font-weight:500;color:#1f2328;line-height:1.4;
        text-decoration:none;flex:1;min-width:0;word-break:break-word;
    }
    .cu-task-title:hover{color:#7c3aed;}
    .cu-check{
        width:17px;height:17px;border:none;background:transparent;color:#c1c4cc;
        padding:0;flex-shrink:0;display:flex;align-items:center;justify-content:center;
        cursor:pointer;font-size:15px;line-height:1;margin-top:2px;transition:color .12s;
    }
    .cu-check:hover{color:#30a46c;}
    .cu-check.done{color:#30a46c;}
    .cu-task-foot{
        display:flex;align-items:center;gap:8px;margin-top:8px;padding-left:24px;min-height:20px;
    }
    .cu-due{
        display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#8b8d98;
    }
    .cu-due{display:inline-flex;}
    .cu-due.overdue{color:#e5484d;font-weight:600;}
    .cu-overdue-tag{
        background:#fdebec;color:#e5484d;border-radius:4px;padding:0 5px;
        font-size:9px;font-weight:700;letter-spacing:.3px;
    }
    .cu-mini{
        display:inline-flex;align-items:center;gap:3px;font-size:10px;color:#8b8d98;gap:4px;
    }
    .cu-mini i{font-size:10px;}
    .cu-assignee{
        width:19px;height:19px;border-radius:50%;background:#ece9fd;color:#7c3aed;
        font-size:9px;font-weight:700;display:inline-flex;align-items:center;
        justify-content:center;margin-left:auto;flex-shrink:0;
    }
    .cu-card-menu{position:static;}
    .cu-card-menu .dropdown-menu{--bs-dropdown-min-width:130px;}
    .cu-task-menu-btn{
        width:20px;height:20px;border:none;background:transparent;color:#8b8d98;
        display:flex;align-items:center;justify-content:center;cursor:pointer;
        font-size:13px;border-radius:4px;padding:0;opacity:0;transition:opacity .12s;flex-shrink:0;
    }
    .cu-task-menu-btn:hover{background:#f0f1f3;color:#1f2328;}
    .cu-task-title:focus-visible~.cu-task-foot .cu-task-menu-btn{opacity:1;}
    .cu-col-empty{text-align:center;padding:18px 8px;color:#c1c4cc;font-size:12px;}

    /* ─── Quick add ─── */
    .cu-quickadd{
        display:flex;align-items:center;gap:6px;
        margin-top:2px;padding:2px 4px;
    }
    .cu-quickadd i{color:#8b8d98;font-size:13px;flex-shrink:0;}
    .cu-quickadd input{
        border:none;background:transparent;outline:none;width:100%;
        font-size:12.5px;color:#1f2328;padding:4px 0;
    }
    .cu-quickadd input::placeholder{color:#aeb2ba;}
    .cu-quickadd:focus-within i{color:#7c3aed;}

    /* ─── List view ─── */
    .cu-list-view{display:none;}
    .cu-list-head{
        display:grid;grid-template-columns:2fr 1.2fr .8fr .8fr .8fr 80px;
        gap:8px;padding:8px 14px;background:transparent;
        border-radius:6px;margin-bottom:4px;
        font-size:11px;font-weight:600;color:#8b8d98;text-transform:uppercase;letter-spacing:.4px;
    }
    .cu-list-row{
        display:grid;grid-template-columns:2fr 1.2fr .8fr .8fr .8fr 80px;
        gap:8px;padding:10px 14px;background:white;border:1px solid #e5e7eb;
        border-radius:6px;margin-bottom:4px;align-items:center;
        font-size:13px;transition:border-color .12s;
    }
    .cu-list-row:hover{border-color:#d3d7de;}
    .cu-list-title{font-weight:500;color:#1f2328;}
    .cu-list-sub{font-size:10px;color:#8b8d98;margin-top:2px;display:flex;align-items:center;gap:3px;}
    .cu-list-project{font-size:12px;color:#3d4149;display:flex;align-items:center;gap:5px;}
    .cu-list-actions{display:flex;gap:5px;justify-content:flex-end;}
    .cu-status-chip{
        display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;
        font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;
    }
    .cu-status-chip.to_do      {background:#eef0f2;color:#6b6f78;}
    .cu-status-chip.in_progress{background:#ece9fd;color:#7c3aed;}
    .cu-status-chip.on_hold    {background:#fdf4de;color:#ad6800;}
    .cu-status-chip.in_review  {background:#e2f0fd;color:#0b6bcb;}
    .cu-status-chip.completed  {background:#e3f5ec;color:#29774b;}
    .cu-priority{
        display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:20px;
        font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;
    }
    .cu-priority.high  {background:#fdebec;color:#e5484d;}
    .cu-priority.medium{background:#fdf4de;color:#ad6800;}
    .cu-priority.low   {background:#e3f5ec;color:#29774b;}
    .cu-task-btn{
        width:24px;height:24px;border:1px solid #e5e7eb;
        background:white;display:flex;align-items:center;justify-content:center;
        font-size:11px;color:#8b8d98;text-decoration:none;transition:all .12s;border-radius:5px;
    }
    .cu-task-btn:hover{border-color:#7c3aed;background:#f7f5ff;color:#7c3aed;}

    /* ─── Tree view ─── */
    .cu-tree-view{display:none;background:white;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;}
    .cu-ttree-node{border-bottom:1px solid #f2f3f5;}
    .cu-ttree-node:last-child{border-bottom:none;}
    .cu-ttree-row{
        display:flex;align-items:center;gap:8px;
        padding:9px 12px 9px calc(12px + var(--depth, 0) * 22px);
    }
    .cu-ttree-row:hover{background:#fafbfc;}
    .cu-tree-toggle{
        width:22px;height:22px;border:1px solid #e5e7eb;background:white;border-radius:5px;
        color:#8b8d98;font-size:10px;cursor:pointer;display:flex;align-items:center;
        justify-content:center;flex-shrink:0;padding:0;
    }
    .cu-tree-toggle i{transition:transform .15s;}
    .cu-tree-toggle.collapsed i{transform:rotate(-90deg);}
    .cu-tree-spacer{width:22px;flex-shrink:0;}
    .cu-ttree-title{font-size:13px;font-weight:500;color:#1f2328;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;}
    .cu-ttree-title:hover{color:#7c3aed;}
    .cu-ttree-weight{font-size:10px;font-weight:600;background:#ece9fd;color:#7c3aed;border-radius:20px;padding:1px 8px;flex-shrink:0;}
    .cu-ttree-meta{font-size:11px;color:#8b8d98;margin-left:auto;white-space:nowrap;}
    .cu-ttree-progress{display:flex;align-items:center;gap:6px;min-width:110px;font-size:11px;color:#6b7385;}
    .cu-ttree-pb{flex:1;height:4px;background:#f0f1f3;border-radius:4px;overflow:hidden;}
    .cu-ttree-pb-fill{height:100%;background:#7c3aed;border-radius:4px;}
    .cu-ttree-actions{display:flex;gap:4px;}
    .cu-ttree-children{background:#fcfcfd;}

    /* ─── Bulk select — modern, professional ─── */
    .cu-select-box{
        appearance:none;-webkit-appearance:none;
        width:18px;height:18px;min-width:18px;min-height:18px;
        border:1.6px solid #d1d5db;border-radius:5px;background:#fff;
        display:none;place-items:center;cursor:pointer;flex-shrink:0;
        margin:2px 0 0;padding:0;transition:all .15s;position:relative;
    }
    .cu-select-box::after{
        content:'';position:absolute;inset:0;display:grid;place-items:center;
        font-size:11px;font-weight:900;color:#fff;opacity:0;transform:scale(.6);transition:all .13s;
    }
    .cu-select-box:checked{background:#7c3aed;border-color:#7c3aed;}
    .cu-select-box:checked::after{content:'\2713';opacity:1;transform:scale(1);}
    .cu-select-box:focus-visible{outline:2px solid #7c3aed;outline-offset:1px;}
    body.cu-selecting .cu-select-box{display:grid;}
    /* hover reveal even without selecting mode — subtle hint */
    .cu-task-card:hover .cu-select-box,
    .cu-ch-row:hover .cu-select-box,
    .cu-list-row:hover .cu-select-box,
    .cu-ttree-row:hover .cu-select-box{display:grid;}
    body.cu-selecting .cu-task-card, body.cu-selecting .cu-ch-row,
    body.cu-selecting .cu-list-row, body.cu-selecting .cu-ttree-row{cursor:default;}
    /* selected highlight — works with :has() in modern browsers, fallback via .is-selected class */
    .cu-task-card:has(.cu-select-box:checked), .cu-task-card.is-selected,
    .cu-ch-row:has(.cu-select-box:checked), .cu-ch-row.is-selected,
    .cu-list-row:has(.cu-select-box:checked), .cu-list-row.is-selected,
    .cu-ttree-row:has(.cu-select-box:checked){background:#f5f3ff !important;border-color:#c4b5fd !important;box-shadow:0 0 0 2px rgba(124,58,237,.08);}
    .cu-ttree-row.is-selected{background:#f5f3ff !important;}
    #cuSelectMode{
        position:relative;display:inline-flex;align-items:center;gap:6px;
        border:1.5px solid #e5e7eb;background:#fff;color:#4b5059;
        transition:all .15s;
    }
    #cuSelectMode:hover{border-color:#c4b5fd;color:#7c3aed;background:#faf5ff;}
    #cuSelectMode.on{border-color:#7c3aed;color:#fff;background:#7c3aed;box-shadow:0 2px 8px rgba(124,58,237,.25);}
    #cuSelectMode .cu-select-badge{
        display:none;min-width:18px;height:18px;padding:0 5px;border-radius:999px;
        background:#fff;color:#7c3aed;font-size:11px;font-weight:800;align-items:center;justify-content:center;
    }
    #cuSelectMode.on .cu-select-badge{display:inline-flex;}
    /* floating bulk bar — pill, glass, animated */
    #cuBulkBar{
        position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(16px) scale(.98);
        z-index:1070;background:rgba(24,26,31,.96);backdrop-filter:blur(14px) saturate(1.2);
        color:#fff;border:1px solid rgba(255,255,255,.08);border-radius:999px;
        padding:6px 6px 6px 14px;display:flex;align-items:center;gap:6px;font-size:13px;
        box-shadow:0 12px 32px rgba(0,0,0,.28), 0 2px 8px rgba(0,0,0,.18);
        white-space:nowrap;opacity:0;pointer-events:none;transition:all .22s cubic-bezier(.16,1,.3,1);
    }
    #cuBulkBar.show{opacity:1;pointer-events:auto;transform:translateX(-50%) translateY(0) scale(1);}
    .cu-bulk-count{
        display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;
        padding-right:10px;border-right:1px solid rgba(255,255,255,.12);margin-right:2px;
    }
    .cu-bulk-count-num{
        min-width:26px;height:26px;padding:0 7px;border-radius:999px;background:#7c3aed;color:#fff;
        display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;
    }
    .cu-bulk-label{color:#e5e7eb;font-weight:600;font-size:12px;}
    #cuBulkBar .cu-bulk-sep{width:1px;height:22px;background:rgba(255,255,255,.1);margin:0 2px;}
    #cuBulkBar .cu-bulk-actions{display:flex;align-items:center;gap:6px;}
    #cuBulkBar select{
        background:#2e333b;color:#fff;border:1px solid #3f444e;border-radius:999px;
        font-size:12px;font-weight:600;padding:7px 10px;outline:none;cursor:pointer;min-width:110px;
    }
    #cuBulkBar select:focus{border-color:#7c3aed;}
    .cu-bulk-btn{
        border:none;border-radius:999px;padding:7px 14px;font-size:12.5px;font-weight:700;
        cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .15s;white-space:nowrap;
    }
    .cu-bulk-btn.primary{background:#fff;color:#1f2328;}
    .cu-bulk-btn.primary:hover{background:#f3f0ff;color:#6d28d9;transform:translateY(-1px);}
    .cu-bulk-btn.ghost{background:rgba(255,255,255,.08);color:#e5e7eb;}
    .cu-bulk-btn.ghost:hover{background:rgba(255,255,255,.14);color:#fff;}
    .cu-bulk-btn.danger{background:#e5484d;color:#fff;}
    .cu-bulk-btn.danger:hover{background:#c93338;transform:translateY(-1px);}
    .cu-bulk-btn:active{transform:scale(.97);}
    #cuBulkCancel{width:32px;height:32px;border-radius:50%;padding:0;background:rgba(255,255,255,.08);color:#c1c4cc;display:inline-flex;align-items:center;justify-content:center;}
    #cuBulkCancel:hover{background:rgba(255,255,255,.14);color:#fff;}
    #cuBulkSelectAll, #cuBulkClear{
        background:transparent;color:#a1a6b3;border:none;font-size:11px;font-weight:700;cursor:pointer;padding:4px 6px;border-radius:6px;
    }
    #cuBulkSelectAll:hover, #cuBulkClear:hover{color:#fff;background:rgba(255,255,255,.08);}
    @media(max-width:640px){
        #cuBulkBar{left:12px;right:12px;transform:translateY(16px) scale(.98);border-radius:16px;flex-wrap:wrap;justify-content:center;padding:10px 12px;}
        #cuBulkBar.show{transform:translateY(0) scale(1);}
        .cu-bulk-count{border-right:none;padding-right:0;}
        .cu-bulk-sep{display:none;}
    }

    /* ─── Chapters view ─── */    .cu-chapters-view{display:none;flex-direction:column;gap:10px;}
    .cu-chapter{background:white;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;}
    .cu-chapter-head{
        display:flex;align-items:center;gap:8px;padding:10px 12px;cursor:pointer;user-select:none;
    }
    .cu-chapter-head:hover{background:#fafbfc;}
    .cu-chapter.collapsed .cu-chapter-body{display:none;}
    .cu-chapter-head:hover .cu-chapter-title{color:#7c3aed;}
    .cu-ch-heading{display:flex;align-items:center;gap:4px;flex:1;min-width:0;}
    .cu-chapter-title{font-size:13.5px;font-weight:600;color:#1f2328;text-decoration:none;flex:0 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:color .12s;}
    .cu-ch-chevron{
        width:28px;height:28px;flex-shrink:0;padding:0;border:1px solid #e5e7eb;border-radius:7px;
        background:white;color:#6b6f78;font-size:13px;cursor:pointer;
        display:flex;align-items:center;justify-content:center;
        transition:border-color .12s, background .12s, color .12s, transform .08s;
    }
    .cu-chapter-head:hover .cu-ch-chevron{border-color:#d3d7de;}
    .cu-ch-chevron:hover{border-color:#7c3aed;background:#f7f5ff;color:#7c3aed;}
    .cu-ch-chevron:active{transform:scale(.9);}
    .cu-ch-chevron:focus-visible{outline:2px solid #7c3aed;outline-offset:1px;}
    .cu-ch-chevron i{transition:transform .15s;}
    .cu-chapter.collapsed .cu-ch-chevron i{transform:rotate(-90deg);}
    .cu-ch-open{
        width:24px;height:24px;flex-shrink:0;padding:0;border:0;border-radius:6px;
        background:transparent;color:#9ca0aa;font-size:12.5px;
        display:inline-flex;align-items:center;justify-content:center;text-decoration:none;
        transition:background .12s, color .12s, transform .08s;
    }
    .cu-chapter-head:hover .cu-ch-open{color:#6b7280;}
    .cu-ch-open:hover{background:#f7f5ff;color:#7c3aed;}
    .cu-ch-open:active{transform:scale(.88);}
    .cu-chapter-progress{display:flex;align-items:center;gap:8px;min-width:150px;}
    .cu-chapter-pb{flex:1;height:5px;background:#eef0f2;border-radius:4px;overflow:hidden;}
    .cu-chapter-pb-fill{display:block;height:100%;background:#30a46c;border-radius:4px;transition:width .2s;}
    .cu-chapter-count{font-size:11px;font-weight:600;color:#6b6f78;white-space:nowrap;}
    .cu-chapter-open{flex-shrink:0;}
    .cu-chapter-body{border-top:1px solid #f2f3f5;padding:4px;}
    .cu-ch-row{
        display:flex;align-items:center;gap:7px;padding:7px 10px;border-radius:6px;
    }
    .cu-ch-row:hover{background:#fafbfc;}
    .cu-ch-row:hover .cu-task-menu-btn{opacity:1;}
    .cu-ch-row.is-done .cu-task-title{color:#9ca0aa;text-decoration:line-through;text-decoration-color:#c7cad1;}
    .cu-ch-row .cu-due{margin-left:2px;}
    .cu-ch-row .cu-assignee{display:none;}
    .cu-checkline{
        display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#3d4149;
        cursor:pointer;user-select:none;white-space:nowrap;
    }
    .cu-checkline input{accent-color:#7c3aed;cursor:pointer;}
    .cu-mini-btn{
        border:1px solid #e5e7eb;background:white;border-radius:6px;padding:4px 10px;
        font-size:11px;font-weight:600;color:#6b6f78;cursor:pointer;white-space:nowrap;
    }
    .cu-mini-btn:hover{border-color:#7c3aed;color:#7c3aed;}

    .cu-empty{
        text-align:center;padding:60px 20px;background:white;
        border:1px solid #e5e7eb;border-radius:8px;
    }
    .cu-empty-icon{
        width:52px;height:52px;border-radius:12px;background:#f2f3f5;color:#8b8d98;
        font-size:22px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;
    }
    .cu-empty h5{font-weight:700;color:#1f2328;margin-bottom:6px;}
    .cu-empty p {color:#8b8d98;font-size:13px;margin-bottom:16px;}

    /* ─── Toast ─── */
    #cuToast{
        position:fixed;bottom:22px;left:50%;transform:translateX(-50%) translateY(8px);
        background:#1f2328;color:white;font-size:13px;padding:9px 18px;border-radius:8px;
        opacity:0;pointer-events:none;transition:opacity .2s, transform .2s;z-index:1080;
        box-shadow:0 4px 16px rgba(0,0,0,.18);white-space:nowrap;
    }
    #cuToast.show{opacity:1;transform:translateX(-50%) translateY(0);}

    /* ─── Responsive ─── */
    @media (max-width: 768px) {
        .cu-header-title { font-size: 17px; }
        .cu-toolbar { flex-direction: column; align-items: stretch; gap: 8px; }
        .cu-toolbar-left  { flex-wrap: wrap; }
        .cu-toolbar-right { justify-content: space-between; }
        .cu-search-input  { width: 100%; }
        .cu-filter-select { flex: 1; min-width: 0; }
        .cu-btn-new       { width: 100%; justify-content: center; padding: 8px; }
        .cu-list-head,
        .cu-list-row { grid-template-columns: 1fr auto 80px; }
        .cu-list-head > *:nth-child(2),
        .cu-list-head > *:nth-child(4),
        .cu-list-head > *:nth-child(5),
        .cu-list-row  > *:nth-child(2),
        .cu-list-row  > *:nth-child(4),
        .cu-list-row  > *:nth-child(5) { display: none; }
    }

    /* ─── Modal (unchanged behaviour, minimal look) ─── */
    .cu-modal .modal-content{border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 16px 40px rgba(0,0,0,.12);}
    .cu-modal .modal-header{background:white;color:#1f2328;border:none;border-bottom:1px solid #e5e7eb;border-radius:10px 10px 0 0;padding:14px 20px;}
    .cu-modal .modal-title{font-weight:700;font-size:15px;}
    .cu-modal .modal-body {padding:18px 20px;}
    .cu-modal .modal-footer{padding:12px 20px;border-top:1px solid #e5e7eb;background:#fafbfc;border-radius:0 0 10px 10px;}
    .cu-field{margin-bottom:14px;}
    .cu-label{font-size:12px;font-weight:600;color:#3d4149;margin-bottom:5px;display:block;}
    .cu-input{
        width:100%;border:1px solid #e5e7eb;border-radius:6px;
        padding:7px 10px;font-size:13px;color:#1f2328;background:white;
        transition:border .12s;outline:none;
    }
    .cu-input:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.08);}
    .cu-select{appearance:auto;}
    .cu-more{margin:4px 0 14px;}
    .cu-more summary{
        font-size:12px;font-weight:600;color:#7c3aed;cursor:pointer;
        list-style:none;display:flex;align-items:center;gap:5px;user-select:none;
    }
    .cu-more summary::-webkit-details-marker{display:none;}
    .cu-more summary::before{content:'\f282';font-family:'bootstrap-icons';font-size:10px;transition:transform .15s;}
    .cu-more[open] summary::before{transform:rotate(90deg);}
    .cu-more summary:hover{text-decoration:underline;}
    .cu-more .row.mt-0 .cu-field{margin-bottom:10px;}
    .ql-toolbar{border-color:#e5e7eb !important;border-radius:6px 6px 0 0;}
    .ql-container.ql-snow{border-color:#e5e7eb !important;border-radius:0 0 6px 6px;}
    #task-quill-editor{height:120px;}

    /* ── Add-to-day ── */
    @keyframes cudIn{from{opacity:0;transform:translateY(6px) scale(.98);}to{opacity:1;transform:none;}}
    :root{--cud-purple:#7c3aed;}
    .cu-add-day{
        display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;
        width:24px;height:24px;margin-left:2px;border:none;border-radius:6px;
        background:#f4f5f7;color:#8b8d98;cursor:pointer;transition:all .15s;
    }
    .cu-add-day i{font-size:11px;}
    .cu-add-day:hover{background:#ede9fe;color:var(--cud-purple);}
    .cu-add-day.set{background:#e8f7ef;color:#30a46c;}

    /* ── Day shortcut (Projects / Chapters rows) — beautiful quick action ── */
    .cu-day-shortcut{
        position:relative;display:inline-flex;align-items:center;gap:5px;flex-shrink:0;
        height:26px;padding:0 9px 0 8px;margin-left:auto;border-radius:999px;cursor:pointer;
        border:1px solid #e7e2fb;background:linear-gradient(180deg,#faf8ff,#f3efff);
        color:#6d28d9;font-size:11px;font-weight:700;white-space:nowrap;
        opacity:0;transform:translateY(1px);transition:opacity .15s, transform .15s, box-shadow .15s, background .15s;
    }
    .cu-day-shortcut i.bi{font-size:12px;line-height:1;}
    .cu-day-shortcut .cu-day-kbd{
        font-size:9px;font-weight:800;line-height:1;min-width:16px;height:16px;padding:0 4px;
        display:inline-flex;align-items:center;justify-content:center;
        background:#fff;border:1px solid #ddd6fe;border-bottom-width:2px;border-radius:5px;color:#7c3aed;
    }
    .cu-ch-row:hover .cu-day-shortcut,
    .cu-day-shortcut:focus-visible{opacity:1;transform:none;}
    .cu-day-shortcut:hover{
        background:linear-gradient(135deg,#7c3aed,#a855f7);border-color:#7c3aed;color:#fff;
        box-shadow:0 4px 12px rgba(124,58,237,.35);transform:translateY(-1px);
    }
    .cu-day-shortcut:hover .cu-day-kbd{background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.45);color:#fff;}
    .cu-day-shortcut:active{transform:translateY(0) scale(.97);}
    .cu-day-shortcut.is-set{
        opacity:1;transform:none;
        background:linear-gradient(180deg,#eefbf3,#e2f6ea);border-color:#bfe6cd;color:#1d7a44;
    }
    .cu-day-shortcut.is-set .cu-day-kbd{background:#fff;border-color:#bfe6cd;color:#1d7a44;}
    .cu-day-shortcut.is-set:hover{background:linear-gradient(135deg,#16a34a,#22c55e);border-color:#16a34a;color:#fff;box-shadow:0 4px 12px rgba(22,163,74,.3);}
    .cu-day-shortcut.is-set:hover .cu-day-kbd{background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.45);color:#fff;}
    .cu-today-chip{
        display:inline-flex;align-items:center;gap:4px;flex-shrink:0;
        font-size:10px;font-weight:700;color:#6d28d9;background:#f3efff;
        border:1px solid #e2d9fd;border-radius:999px;padding:2px 8px;white-space:nowrap;
    }
    .cu-today-chip i{font-size:10px;}
    @media (hover:none), (max-width:768px){ .cu-day-shortcut{opacity:1;transform:none;} }
    [dir="rtl"] .cu-day-shortcut{margin-left:0;margin-right:auto;}

    /* ── Project quick-add (inline task creation) ── */
    .cu-kbd{
        font-size:9px;font-weight:800;line-height:1;min-width:16px;height:16px;padding:0 4px;
        display:inline-flex;align-items:center;justify-content:center;
        background:#fff;border:1px solid #bfe6cd;border-bottom-width:2px;border-radius:5px;color:#1d7a44;
    }
    .cu-ch-add{
        display:inline-flex;align-items:center;gap:5px;flex-shrink:0;
        height:26px;padding:0 9px 0 8px;border-radius:999px;cursor:pointer;white-space:nowrap;
        border:1px solid #cfe8d6;background:linear-gradient(180deg,#f4fbf6,#e7f6ed);
        color:#1d7a44;font-size:11px;font-weight:700;
        opacity:0;transform:translateY(1px);transition:opacity .15s, transform .15s, box-shadow .15s, background .15s;
    }
    .cu-ch-add i.bi{font-size:12px;line-height:1;}
    .cu-chapter-head:hover .cu-ch-add,
    .cu-ch-add:focus-visible{opacity:1;transform:none;}
    .cu-ch-add:hover{
        background:linear-gradient(135deg,#16a34a,#22c55e);border-color:#16a34a;color:#fff;
        box-shadow:0 4px 12px rgba(22,163,74,.35);transform:translateY(-1px);
    }
    .cu-ch-add:hover .cu-kbd{background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.45);color:#fff;}
    .cu-ch-add:active{transform:translateY(0) scale(.97);}
    @media (hover:none), (max-width:768px){ .cu-ch-add{opacity:1;transform:none;} }
    .cu-ch-quickform{
        display:flex;align-items:center;gap:8px;margin:4px;padding:8px 10px;
        background:#f6fef9;border:1.5px dashed #bfe6cd;border-radius:10px;
        animation:cudIn .15s ease-out;
    }
    .cu-ch-quickform[hidden]{display:none;}
    .cu-ch-quickform > i{color:#16a34a;font-size:13px;flex-shrink:0;}
    .cu-ch-quickform input{
        flex:1;min-width:0;border:none;background:transparent;outline:none;
        font-size:13px;color:#1f2328;padding:2px 0;font-family:inherit;
    }
    .cu-ch-quickform input::placeholder{color:#a8b3a9;}
    .cu-ch-quickform:focus-within{border-style:solid;border-color:#16a34a;background:#fff;box-shadow:0 0 0 3px rgba(22,163,74,.1);}
    .cu-ch-quickhint{font-size:10px;color:#8a8f98;white-space:nowrap;flex-shrink:0;}
    .cu-ch-quickhint kbd{
        font-family:inherit;font-size:9px;font-weight:700;background:#fff;
        border:1px solid #d5dbe0;border-bottom-width:2px;border-radius:4px;padding:1px 5px;color:#6b7385;
    }
    @keyframes cuFlash{0%{background:#ddf3e5;}100%{background:transparent;}}
    .cu-ch-row.row-flash,.cu-task-card.row-flash,.cu-list-row.row-flash{animation:cuFlash 1.8s ease-out;}
    .cu-ttree-node.row-flash{animation:cuFlash 1.8s ease-out;border-radius:8px;}

    .cud-modal{position:fixed;inset:0;z-index:1100;display:flex;align-items:center;justify-content:center;padding:16px;}
    .cud-modal[hidden]{display:none;}
    .cud-backdrop{position:absolute;inset:0;background:rgba(17,20,26,.45);backdrop-filter:blur(2px);}
    .cud-box{
        position:relative;background:#fff;border-radius:16px;width:min(440px,94vw);
        box-shadow:0 20px 60px rgba(0,0,0,.28);overflow:hidden;
        animation:cudIn .16s ease-out;
    }
    .cud-head{display:flex;align-items:flex-start;gap:10px;padding:14px 16px 10px;}
    .cud-eyebrow{font-size:10.5px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;color:var(--cud-purple);display:flex;align-items:center;gap:5px;}
    .cud-title{font-size:14px;font-weight:700;color:#1a1d23;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:300px;}
    .cud-x{
        margin-left:auto;flex-shrink:0;border:none;background:#f2f3f5;color:#6b7385;
        width:28px;height:28px;border-radius:8px;font-size:16px;line-height:1;cursor:pointer;
    }
    .cud-x:hover{background:#e6e8ec;color:#1a1d23;}
    .cud-chips{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:4px 16px 12px;}
    .cud-chip{
        display:inline-flex;align-items:center;justify-content:center;gap:6px;
        border:1px solid #e5e7eb;background:#fafbfc;color:#6b6f78;
        font-size:12px;font-weight:700;border-radius:9px;padding:8px 6px;cursor:pointer;transition:all .12s;
    }
    .cud-chip:hover{border-color:#c4b5fd;color:var(--cud-purple);background:#faf5ff;}
    .cud-chip.active{background:#ede9fe;border-color:#c4b5fd;color:var(--cud-purple);}
    .cud-chip.busy{opacity:.55;pointer-events:none;}
    .cud-foot{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 16px 14px;border-top:1px solid #f2f3f5;}
    .cud-hint{font-size:11px;color:#8a8f98;display:flex;align-items:center;gap:6px;min-width:0;}
    .cud-hint .dot{width:7px;height:7px;border-radius:50%;background:#7c3aed;flex-shrink:0;animation:cudPulse 1.6s infinite;}
    @keyframes cudPulse{0%,100%{opacity:1;}50%{opacity:.35;}}
    .cud-link{font-size:11.5px;font-weight:700;color:var(--cud-purple);text-decoration:none;white-space:nowrap;}
    .cud-link:hover{text-decoration:underline;}
    @media(max-width:480px){ .cud-chips{grid-template-columns:repeat(2,1fr);} }

    /* ── Schedule modal: day strip ── */
    .cud-sec-label{font-size:11px;font-weight:800;color:#6b7385;text-transform:uppercase;letter-spacing:.5px;padding:2px 16px 8px;display:flex;align-items:center;gap:6px;}
    .cud-days{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;padding:0 16px 6px;}
    .cud-day{
        display:flex;flex-direction:column;align-items:center;gap:1px;
        border:1.5px solid #e9e7f0;background:#fafbfc;border-radius:12px;padding:8px 4px 7px;cursor:pointer;
        transition:all .13s;position:relative;min-width:0;
    }
    .cud-day:hover{border-color:#c4b5fd;background:#faf5ff;transform:translateY(-1px);}
    .cud-day .d-top{font-size:10px;font-weight:800;color:#6b7385;white-space:nowrap;}
    .cud-day .d-num{font-size:16px;font-weight:800;color:#1a1d23;line-height:1.25;}
    .cud-day .d-mon{font-size:9.5px;font-weight:600;color:#9aa0ab;white-space:nowrap;}
    .cud-day .d-kbd{
        position:absolute;top:4px;inset-inline-end:5px;font-size:8.5px;font-weight:800;color:#a5aab4;
        border:1px solid #e5e7eb;border-radius:4px;padding:0 3px;line-height:1.4;background:#fff;
    }
    .cud-day.active{
        background:linear-gradient(135deg,#7c3aed,#a855f7);border-color:#7c3aed;color:#fff;
        box-shadow:0 6px 16px rgba(124,58,237,.35);
    }
    .cud-day.active .d-top,.cud-day.active .d-num,.cud-day.active .d-mon{color:#fff;}
    .cud-day.active .d-kbd{background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.4);color:#fff;}
    .cud-day.is-past{opacity:.45;}
    .cud-day.busy{opacity:.55;pointer-events:none;}
    .cud-custom{display:flex;align-items:center;gap:8px;padding:6px 16px 12px;}
    .cud-custom input[type="date"],
    .cud-custom input.cud-jalali{
        flex:1;min-width:0;border:1.5px dashed #d9d4ec;border-radius:10px;padding:7px 10px;font-size:12px;color:#3d4149;
        background:#fcfbff;outline:none;cursor:pointer;font-family:inherit;text-align:center;letter-spacing:.3px;
    }
    .cud-custom input[type="date"]:focus,
    .cud-custom input.cud-jalali:focus{border-color:#7c3aed;border-style:solid;background:#fff;box-shadow:0 0 0 3px rgba(124,58,237,.1);}
    .cud-custom input[type="date"].has-value,
    .cud-custom input.cud-jalali.has-value{border-style:solid;border-color:#7c3aed;background:#f5f0ff;font-weight:700;color:#6d28d9;}
    .cud-sec-divider{height:1px;background:#f2f3f5;margin:2px 16px 10px;}
</style>
@endpush

@section('content')
<div class="main-content">

    <div class="cu-header">
        <div class="d-flex align-items-center gap-2">
            @if(isset($project))
                <a href="{{ route('projects.show', $project) }}" class="back-link text-decoration-none">
                    <i class="bi bi-arrow-left" style="font-size:16px;"></i>
                </a>
            @endif
            <div>
                <div class="cu-header-title">{{ isset($project) ? $project->name . ' — ' . __('Tasks') : __('Tasks') }}</div>
                <div class="cu-header-sub">{{ isset($project) ? __('Manage and track tasks for this project') : __('All active tasks across your projects') }}</div>
            </div>
        </div>
    </div>

    @php
        $todoCnt     = count($tasks['to_do'] ?? []);
        $progressCnt = count($tasks['in_progress'] ?? []);
        $onHoldCnt   = count($tasks['on_hold'] ?? []);
        $inReviewCnt = count($tasks['in_review'] ?? []);
        $completedCnt= count($tasks['completed'] ?? []);
        $totalCnt    = $todoCnt + $progressCnt + $onHoldCnt + $inReviewCnt + $completedCnt;
        $hasAny      = $totalCnt > 0;
    @endphp

    <div class="cu-toolbar">
        <div class="cu-toolbar-left">
            <div class="cu-view-toggle">
                <button class="cu-view-btn active" data-view="kanban"><i class="bi bi-kanban"></i> {{ __('Board') }}</button>
                @if(!isset($project))
                    <button class="cu-view-btn" data-view="projects"><i class="bi bi-folder"></i> {{ __('Projects') }}</button>
                @endif
                <button class="cu-view-btn" data-view="chapters"><i class="bi bi-collection"></i> {{ __('Chapters') }}</button>
                <button class="cu-view-btn" data-view="list"><i class="bi bi-list-ul"></i> {{ __('List') }}</button>
                <button class="cu-view-btn" data-view="tree"><i class="bi bi-diagram-3"></i> {{ __('Tree') }}</button>
            </div>
            <input type="text" class="cu-search-input" id="cuSearch" placeholder="{{ __('Search') }}">
            <select class="cu-filter-select" id="cuPriority">
                <option value="">{{ __('All') }} {{ __('Priority') }}</option>
                <option value="high">{{ __('High') }}</option>
                <option value="medium">{{ __('Medium') }}</option>
                <option value="low">{{ __('Low') }}</option>
            </select>
            <select class="cu-filter-select" id="cuProject">
                <option value="">{{ __('All') }} {{ __('Projects') }}</option>
                @foreach($projects as $proj)
                    <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                @endforeach
            </select>
            <select class="cu-filter-select" id="cuStatus">
                <option value="">{{ __('All') }} {{ __('Status') }}</option>
                <option value="to_do">{{ __('To Do') }}</option>
                <option value="in_progress">{{ __('In Progress') }}</option>
                <option value="on_hold">{{ __('On Hold') }}</option>
                <option value="in_review">{{ __('In Review') }}</option>
                <option value="completed">{{ __('Completed') }}</option>
            </select>
            <select class="cu-filter-select" id="cuSort" title="{{ __('Sort') }}">
                <option value="">{{ __('Sort') }}: {{ __('Manual') }}</option>
                <option value="due">{{ __('Due Date') }}</option>
                <option value="priority">{{ __('Priority') }}</option>
                <option value="title">{{ __('Title') }}</option>
            </select>
            <label class="cu-checkline" id="cuUnfinishedWrap" title="{{ __('Show only unfinished tasks') }}" style="display:none;">
                <input type="checkbox" id="cuUnfinished"> {{ __('Unfinished only') }}
            </label>
            <span id="cuChapterExpandWrap" style="display:none;gap:4px;">
                <button class="cu-mini-btn" id="cuExpandAll" title="{{ __('Expand all chapters') }}">{{ __('Expand all') }}</button>
                <button class="cu-mini-btn" id="cuCollapseAll" title="{{ __('Collapse all chapters') }}">{{ __('Collapse all') }}</button>
            </span>
        </div>
        <div class="cu-toolbar-right">
            <span style="font-size:12px;color:#8b8d98;">{{ $totalCnt }} {{ __('Tasks') }}</span>
            <button class="cu-mini-btn" id="cuSelectMode" title="{{ __('Select multiple tasks') }}">
                <i class="bi bi-check2-square"></i> <span>{{ __('Select') }}</span> <span class="cu-select-badge" id="cuSelectBadge">0</span>
            </button>
            <button class="cu-btn-new" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                <i class="bi bi-plus-lg"></i> {{ __('New Task') }}
            </button>
        </div>
    </div>

    @if(!$hasAny)
        <div class="cu-empty">
            <div class="cu-empty-icon"><i class="bi bi-list-task"></i></div>
            <h5>{{ __('No tasks yet') }}</h5>
            <p>{{ isset($project) ? __('Manage and track tasks for this project') : __('No tasks found. Create one to get started.') }}</p>
            <button class="cu-btn-new" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                <i class="bi bi-plus-lg"></i> {{ __('Create Task') }}
            </button>
        </div>
    @else

    {{-- KANBAN --}}
    <div class="cu-kanban" id="cuKanban">
        @php
            $columns = [
                'to_do'      => ['label' => __('To Do'),        'dot' => '#94a3b8',            'empty' => __('No tasks here'),     'icon' => 'bi-circle',           'collapsed' => false],
                'in_progress'=> ['label' => __('In Progress'),   'dot' => '#7c3aed',            'empty' => __('Nothing active'),    'icon' => 'bi-arrow-clockwise',  'collapsed' => false],
                'on_hold'    => ['label' => __('On Hold'),       'dot' => '#ad6800',            'empty' => __('None on hold'),      'icon' => 'bi-pause-circle',     'collapsed' => true],
                'in_review'  => ['label' => __('In Review'),     'dot' => '#0b6bcb',            'empty' => __('Nothing to review'), 'icon' => 'bi-eye',              'collapsed' => true],
                'completed'  => ['label' => __('Completed'),     'dot' => '#29774b',            'empty' => __('Nothing done yet'),  'icon' => 'bi-check-circle',     'collapsed' => true],
            ];
            $colCounts = [
                'to_do' => $todoCnt, 'in_progress' => $progressCnt, 'on_hold' => $onHoldCnt,
                'in_review' => $inReviewCnt, 'completed' => $completedCnt,
            ];
        @endphp
        @foreach($columns as $statusKey => $col)
            <div class="cu-col {{ $col['collapsed'] ? 'collapsed' : '' }}" data-collapsed="{{ $col['collapsed'] ? '1' : '0' }}">
                <div class="cu-col-head" data-col-toggle="{{ $statusKey }}">
                    <span class="cu-col-dot" style="background:{{ $col['dot'] }};"></span>
                    <span class="cu-col-head-left">
                        <span>{{ $col['label'] }}</span>
                        <span class="cu-col-count" id="cnt-{{ $statusKey }}">{{ $colCounts[$statusKey] }}</span>
                    </span>
                    <div class="cu-col-actions">
                        <button class="cu-col-add" data-bs-toggle="modal" data-bs-target="#createTaskModal" data-status="{{ $statusKey }}" title="{{ __('New task in this column') }}">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                        <button type="button" class="cu-col-chevron-btn" data-chevron="{{ $statusKey }}" title="{{ __('Collapse / expand') }}">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                </div>
                <div class="cu-col-body" id="col-{{ $statusKey }}" data-status="{{ $statusKey }}">
                    @foreach($tasks[$statusKey] ?? [] as $task)
                        @include('tasks._card', ['task' => $task])
                    @endforeach
                    <div class="cu-col-empty" @if(!empty($tasks[$statusKey])) style="display:none;" @endif>
                        <i class="bi {{ $col['icon'] }}" style="font-size:20px;display:block;margin-bottom:6px;"></i>{{ $col['empty'] }}
                    </div>
                    <form class="cu-quickadd" data-quickadd="{{ $statusKey }}">
                        <i class="bi bi-plus-lg"></i>
                        <input type="text" placeholder="{{ __('Add task…') }}" data-status="{{ $statusKey }}">
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    {{-- LIST VIEW --}}
    <div class="cu-list-view" id="cuList">
        <div class="cu-list-head">
            <div>{{ __('Task') }}</div><div>{{ __('Project') }}</div><div>{{ __('Priority') }}</div>
            <div>{{ __('Assignee') }}</div><div>{{ __('Due Date') }}</div><div></div>
        </div>
        @foreach(collect($tasks)->flatten() as $task)
            @include('tasks._list-row', ['task' => $task])
        @endforeach
    </div>

    {{-- TREE VIEW --}}
    <div class="cu-tree-view" id="cuTree">
        @forelse($taskRoots as $task)
            @include('tasks._tree-node', ['task' => $task, 'depth' => 0])
        @empty
            <div class="cu-empty">
                <div class="cu-empty-icon"><i class="bi bi-diagram-3"></i></div>
                <h5>{{ __('No tasks yet') }}</h5>
                <p>{{ __('Create your first task to get started.') }}</p>
            </div>
        @endforelse
    </div>

    {{-- CHAPTERS VIEW — collapsible parent-task sections with progress --}}
    @php
        $flatAll   = collect($tasks)->flatten();
        $groupedBy = $flatAll->groupBy('parent_id');
        $chapterRoots = $taskRoots->filter(fn ($t) => $groupedBy->has($t->id))->values();
        $singleRoots  = $taskRoots->reject(fn ($t) => $groupedBy->has($t->id))->values();
    @endphp
    <div class="cu-chapters-view" id="cuChapters" style="display:none;">
        @foreach($chapterRoots as $root)
            @include('tasks._chapter-section', [
                'sectionId'    => 'ch-' . $root->id,
                'sectionTitle' => $root->title,
                'sectionUrl'   => route('tasks.show', $root->id),
                'tasks'        => collect([$root]),
                'grouped'      => $groupedBy,
                'collapsed'    => $root->status === 'completed',
            ])
        @endforeach
        @if($singleRoots->count() > 0)
            @include('tasks._chapter-section', [
                'sectionId'    => 'ch-other',
                'sectionTitle' => 'Other tasks',
                'sectionUrl'   => null,
                'tasks'        => $singleRoots,
                'grouped'      => $groupedBy,
                'collapsed'    => false,
            ])
        @endif
    </div>

    {{-- PROJECTS VIEW (global page only) — one collapsible section per project --}}
    @if(!isset($project))
        @php
            $orphanTasks = $flatAll->filter(fn ($t) => $t->project_id === null)->values();
            $projectsWithTasks = $projects->filter(
                fn ($p) => $flatAll->contains(fn ($t) => (int) $t->project_id === (int) $p->id)
            )->values();
        @endphp
        <div class="cu-chapters-view" id="cuProjects" style="display:none;">
            @if($orphanTasks->count())
                @include('tasks._chapter-section', [
                    'sectionId'    => 'p-none',
                    'sectionTitle' => 'No project',
                    'sectionUrl'   => null,
                    'tasks'        => $orphanTasks->filter(fn ($t) => $t->parent_id === null)->values(),
                    'grouped'      => $orphanTasks->groupBy('parent_id'),
                    'collapsed'    => true,
                ])
            @endif
            @foreach($projectsWithTasks as $proj)
                @php
                    $pTasks   = $flatAll->filter(fn ($t) => (int) $t->project_id === (int) $proj->id)->values();
                    $pGrouped = $pTasks->groupBy('parent_id');
                    $pRoots   = $pTasks->filter(fn ($t) => $t->parent_id === null)->values();
                @endphp
                @include('tasks._chapter-section', [
                    'sectionId'    => 'p-' . $proj->id,
                    'sectionTitle' => $proj->name,
                    'sectionUrl'   => route('projects.tasks.index', $proj),
                    'tasks'        => $pRoots,
                    'grouped'      => $pGrouped,
                    'collapsed'    => true,
                    'quickProjectId' => $proj->id,
                ])
            @endforeach
        </div>
    @endif

    @endif
</div>

{{-- Modal --}}
<div class="modal fade cu-modal" id="createTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>{{ __('New Task') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <form action="{{ isset($project) ? route('projects.tasks.store', $project) : route('tasks.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="cu-field">
                                <label class="cu-label">{{ __('Title') }} <span style="color:#e5484d;">*</span></label>
                                <input type="text" name="title" class="cu-input" placeholder="{{ __('Task title…') }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cu-field">
                                <label class="cu-label">{{ __('Priority') }} <span style="color:#e5484d;">*</span></label>
                                <select name="priority" class="cu-input cu-select" required>
                                    <option value="low">{{ __('Low') }}</option>
                                    <option value="medium" selected>{{ __('Medium') }}</option>
                                    <option value="high">{{ __('High') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="cu-field">
                        <label class="cu-label">{{ __('Description') }}</label>
                        <div id="task-quill-editor"></div>
                        <textarea name="description" id="task_description" style="display:none;"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="cu-field">
                                <label class="cu-label">{{ __('Project') }}</label>
                                <select name="project_id" class="cu-input cu-select">
                                    <option value="">{{ __('No Project') }}</option>
                                    @foreach($projects as $proj)
                                        <option value="{{ $proj->id }}" {{ isset($project) && $project->id == $proj->id ? 'selected' : '' }}>{{ $proj->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cu-field">
                                <label class="cu-label">{{ __('Due Date') }}</label>
                                <x-jalali-date name="due_date" class="cu-input" />
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cu-field">
                                <label class="cu-label">{{ __('Assign To') }} <span style="color:#e5484d;">*</span></label>
                                <select name="user_id" class="cu-input cu-select" required>
                                    <option value="{{ auth()->id() }}" selected>{{ __('Me') }}</option>
                                    @foreach($users as $u)
                                        @if($u->id !== auth()->id())
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <details class="cu-more">
                        <summary>{{ __('More options') }}</summary>
                        <div class="row g-3 mt-0">
                            <div class="col-md-4">
                                <div class="cu-field">
                                    <label class="cu-label">{{ __('Est. Time') }}</label>
                                    <div style="display:flex; gap:6px;">
                                        <input type="number" name="est_hours" class="cu-input" min="0" max="999" step="1" placeholder="{{ __('Hrs') }}" title="{{ __('Hours') }}">
                                        <input type="number" name="est_minutes" class="cu-input" min="0" max="59" step="1" placeholder="{{ __('Min') }}" title="{{ __('Minutes') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="cu-field">
                                    <label class="cu-label">{{ __('Parent Task') }}</label>
                                    <select name="parent_id" class="cu-input cu-select">
                                        <option value="">{{ __('None (top-level)') }}</option>
                                        @foreach(collect($tasks)->flatten()->sortBy('title') as $pt)
                                            <option value="{{ $pt->id }}">{{ $pt->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="cu-field">
                                    <label class="cu-label">{{ __('Weight') }}</label>
                                    <input type="number" name="weight" class="cu-input" min="0" step="0.25" value="1">
                                    <div class="form-check mt-1">
                                        <input type="hidden" name="auto_weight" value="0">
                                        <input type="checkbox" name="auto_weight" value="1" class="form-check-input" checked id="autoWeight">
                                        <label class="form-check-label" for="autoWeight" style="font-size:12px;">{{ __('Auto weight') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </details>
                    <input type="hidden" name="status" id="task_status" value="to_do">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="cu-btn-new" style="border-radius:6px;">
                        <i class="bi bi-check-lg"></i> {{ __('Create Task') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="cuToast"></div>

{{-- "Schedule task" — pick day (today / tomorrow / future) + time period --}}
<div class="cud-modal" id="addToDayModal" hidden>
    <div class="cud-backdrop" data-add-day-close></div>
    <div class="cud-box" role="dialog" aria-modal="true" aria-labelledby="addToDayTitle">
        <div class="cud-head">
            <div style="min-width:0;">
                <div class="cud-eyebrow"><i class="bi bi-calendar-plus"></i> {{ __('Schedule task') }}</div>
                <div class="cud-title" data-add-day-title>&nbsp;</div>
            </div>
            <button type="button" class="cud-x" data-add-day-close aria-label="{{ __('Close') }}">&times;</button>
        </div>
        <div class="cud-sec-label"><i class="bi bi-calendar3"></i> {{ __('Day') }}</div>
        <div class="cud-days" data-schedule-days></div>
        <div class="cud-custom">
            @if(app()->getLocale() === 'fa')
                {{-- Jalali picker (visible) + hidden Gregorian value — same mechanism as x-jalali-date --}}
                <input type="hidden" id="schedCustomDate" data-schedule-custom />
                <input type="text" id="schedCustomDate-jalali" class="cud-jalali"
                       data-jdp data-jdp-only-date data-jdp-format="YYYY/MM/DD"
                       data-target="schedCustomDate"
                       placeholder="۱۴۰۴/۰۷/۱۲" autocomplete="off" dir="ltr"
                       aria-label="{{ __('Custom date') }}" title="{{ __('Pick any future date') }}" />
            @else
                <input type="date" data-schedule-custom aria-label="{{ __('Custom date') }}" title="{{ __('Pick any future date') }}">
            @endif
        </div>
        <div class="cud-sec-divider"></div>
        <div class="cud-sec-label"><i class="bi bi-clock"></i> {{ __('Time of day') }}</div>
        <div class="cud-chips">
            <button type="button" class="cud-chip" data-period=""><i class="bi bi-infinity"></i> {{ __('Anytime') }}</button>
            @foreach(config('routines.periods', []) as $key => $p)
                <button type="button" class="cud-chip" data-period="{{ $key }}">
                    <i class="bi {{ $p['icon'] }}" style="color:{{ $p['color'] }};"></i> {{ __($p['label']) }}
                </button>
            @endforeach
        </div>
        <div class="cud-foot">
            <span class="cud-hint"><span class="dot"></span><span data-schedule-summary>{{ __('Pick a day, then a time') }}</span></span>
            <a href="{{ route('planner.index') }}" class="cud-link" data-schedule-open target="_blank" rel="noopener">{{ __('Open day') }} &rarr;</a>
        </div>
    </div>
</div>

{{-- Bulk action bar — modern floating pill --}}
<div id="cuBulkBar" aria-live="polite">
    <span class="cu-bulk-count">
        <span class="cu-bulk-count-num" id="cuBulkCount">0</span>
        <span class="cu-bulk-label">{{ __('selected') }}</span>
    </span>
    <button id="cuBulkSelectAll" type="button" title="{{ __('Select all visible') }}">{{ __('All') }}</button>
    <button id="cuBulkClear" type="button" title="{{ __('Clear selection') }}">{{ __('Clear') }}</button>
    <span class="cu-bulk-sep"></span>
    <span class="cu-bulk-actions">
        <button id="cuBulkDone" type="button" class="cu-bulk-btn primary" title="{{ __('Mark selected as completed') }}"><i class="bi bi-check2-all"></i> {{ __('Done') }}</button>
        <select id="cuBulkStatus" title="{{ __('Move to status') }}">
            <option value="to_do">{{ __('To Do') }}</option>
            <option value="in_progress">{{ __('In Progress') }}</option>
            <option value="on_hold">{{ __('On Hold') }}</option>
            <option value="in_review">{{ __('In Review') }}</option>
            <option value="completed">{{ __('Completed') }}</option>
        </select>
        <button id="cuBulkApply" type="button" class="cu-bulk-btn ghost"><i class="bi bi-arrow-right-circle"></i> {{ __('Move') }}</button>
        <button id="cuBulkDelete" type="button" class="cu-bulk-btn danger"><i class="bi bi-trash3"></i> {{ __('Delete') }}</button>
    </span>
    <button id="cuBulkCancel" type="button" title="{{ __('Cancel selection') }}"><i class="bi bi-x-lg"></i></button>
</div>
@endsection

@push('scripts')
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const kanban = document.getElementById('cuKanban');
    const list   = document.getElementById('cuList');
    const tree   = document.getElementById('cuTree');
    const csrf0 = '{{ csrf_token() }}';
    let csrf = csrf0;

    /* Toast */
    let toastTimer;
    function toast(msg) {
        const el = document.getElementById('cuToast');
        el.textContent = msg;
        el.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.remove('show'), 2400);
    }
    const TASK_STATUS_TOAST = {
        completed: @json(__('Task completed ✓')),
        reopened: @json(__('Task moved to To Do'))
    };
    const TASK_BULK_TOAST = {
        updated: @json(__(':count task(s) updated ✓')),
        deleted: @json(__(':count task(s) deleted'))
    };
    const ADD_TO_DAY_ERROR = @json(__('Could not add to day'));
    const NETWORK_ERROR = @json(__('network error'));
    const QUICK_CREATED = @json(__('Task added ✓'));
    const QUICK_ERROR = @json(__('Could not create task'));

    /* View switcher */
    const chapters = document.getElementById('cuChapters');
    const projectsView = document.getElementById('cuProjects');
    const unfinishedWrap = document.getElementById('cuUnfinishedWrap');
    const chapterExpandWrap = document.getElementById('cuChapterExpandWrap');
    const unfinishedBox = document.getElementById('cuUnfinished');

    function showView(v) {
        if (kanban)   kanban.style.display   = v === 'kanban'   ? 'grid'  : 'none';
        if (list)     list.style.display     = v === 'list'     ? 'block' : 'none';
        if (tree)     tree.style.display     = v === 'tree'     ? 'block' : 'none';
        if (chapters) chapters.style.display = v === 'chapters' ? 'flex'  : 'none';
        if (projectsView) projectsView.style.display = v === 'projects' ? 'flex' : 'none';
        const isGrouped = v === 'chapters' || v === 'projects';
        if (unfinishedWrap)    unfinishedWrap.style.display    = isGrouped ? '' : 'none';
        if (chapterExpandWrap) chapterExpandWrap.style.display = isGrouped ? 'inline-flex' : 'none';
    }
    document.querySelectorAll('.cu-view-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.cu-view-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            showView(btn.dataset.view);
            savePrefs();
        });
    });

    /* Tree toggles */
    document.querySelectorAll('.cu-tree-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target);
            if (!target) return;
            const hidden = target.style.display === 'none';
            target.style.display = hidden ? '' : 'none';
            btn.classList.toggle('collapsed', !hidden);
        });
    });

    /* Chapter collapse / expand */
    function setChapter(ch, collapsed) {
        ch.classList.toggle('collapsed', collapsed);
        ch.querySelector('.cu-ch-chevron')?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        savePrefs();
    }
    document.querySelectorAll('[data-chapter-toggle]').forEach(head => {
        head.addEventListener('click', e => {
            if (e.target.closest('a,button.dropdown-toggle,.dropdown-menu,[data-ch-quickadd],.cu-ch-quickform')) return;
            const ch = head.closest('.cu-chapter');
            setChapter(ch, !ch.classList.contains('collapsed'));
        });
    });
    /* Title click = collapse/expand (no accidental navigation).
       Ctrl/Cmd/Shift/Alt-click, middle-click, keyboard Enter, or a double-click still open the target. */
    document.querySelectorAll('a.cu-ch-nav').forEach(a => {
        a.addEventListener('click', e => {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0 || e.detail === 0) return;
            e.preventDefault();
            const ch = a.closest('.cu-chapter');
            setChapter(ch, !ch.classList.contains('collapsed'));
        });
        a.addEventListener('dblclick', e => {
            e.preventDefault();
            window.location.href = a.href;
        });
    });
    document.getElementById('cuExpandAll')?.addEventListener('click', () => {
        document.querySelectorAll('.cu-chapter').forEach(ch => setChapter(ch, false));
    });
    document.getElementById('cuCollapseAll')?.addEventListener('click', () => {
        document.querySelectorAll('.cu-chapter').forEach(ch => setChapter(ch, true));
    });
    /* Column collapse / expand (the "+" modal button is excluded; chevron toggles) */
    document.querySelectorAll('[data-col-toggle]').forEach(head => {
        head.addEventListener('click', e => {
            if (e.target.closest('[data-bs-toggle="modal"]')) return;
            const col = head.closest('.cu-col');
            col.classList.toggle('collapsed');
            col.dataset.collapsed = col.classList.contains('collapsed') ? '1' : '0';
        });
    });

    /* Filters */
    const searchInput    = document.getElementById('cuSearch');
    const prioritySelect = document.getElementById('cuPriority');
    const projectSelect  = document.getElementById('cuProject');
    const statusSelect   = document.getElementById('cuStatus');
    const sortSelect     = document.getElementById('cuSort');

    const PRIORITY_RANK = { high: 0, medium: 1, low: 2 };

    function rowMatches(el, term, priority, project, status) {
        return el.dataset.title.includes(term)
            && (!priority || el.dataset.priority === priority)
            && (!project  || el.dataset.project  === project)
            && (!status   || el.dataset.status   === status);
    }

    function applyFilters() {
        const term     = (searchInput?.value || '').toLowerCase();
        const priority = prioritySelect?.value || '';
        const project  = projectSelect?.value  || '';
        const status   = statusSelect?.value   || '';
        const onlyOpen = unfinishedBox?.checked || false;
        document.querySelectorAll('.cu-task-card').forEach(card => {
            card.style.display = rowMatches(card, term, priority, project, status) ? '' : 'none';
        });
        document.querySelectorAll('.cu-list-row').forEach(row => {
            row.style.display = rowMatches(row, term, priority, project, status) ? '' : 'none';
        });
        /* Chapter rows: same filters + unfinished-only; hide empty sections */
        document.querySelectorAll('.cu-ch-row').forEach(row => {
            const show = rowMatches(row, term, priority, project, status)
                && (!onlyOpen || row.dataset.status !== 'completed');
            row.style.display = show ? '' : 'none';
        });
        document.querySelectorAll('.cu-chapter').forEach(ch => {
            const visible = [...ch.querySelectorAll('.cu-ch-row')]
                .some(r => r.style.display !== 'none');
            ch.style.display = visible ? '' : 'none';
        });
        /* Tree rows */
        document.querySelectorAll('.cu-ttree-row').forEach(row => {
            const holder = row.closest('.cu-ttree-node');
            const target = holder || row;
            const show = rowMatches(row, term, priority, project, status)
                && (!onlyOpen || row.dataset.status !== 'completed');
            target.style.display = show ? '' : 'none';
            // if parent hidden, ensure children also hidden via recursion
            if(!show){
                const childrenWrap = holder ? document.getElementById('ttree-children-'+(holder.querySelector('.cu-tree-toggle')?.dataset.target?.replace('ttree-children-','')||'')) : null;
            }
        });
        // hide empty tree roots if filtered
        document.querySelectorAll('.cu-ttree-node').forEach(node=>{
            const row = node.querySelector(':scope > .cu-ttree-row');
            if(row && row.closest('.cu-ttree-children')===null){
                // root node: hide if its row hidden and all descendants hidden
                const allRows = [...node.querySelectorAll('.cu-ttree-row')];
                const anyVisible = allRows.some(r=> r.closest('.cu-ttree-node').style.display!=='none' && r.style.display!=='none');
                // don't hide if already handled
            }
        });
        updateCounts();
    }

    function sortValue(card) {
        switch (sortSelect.value) {
            case 'due': {
                const d = card.dataset.due;
                if (!d) return 99999999999999;              /* no due date → end */
                return new Date(d).getTime();
            }
            case 'priority': {
                return PRIORITY_RANK[card.dataset.priority] ?? 2;
            }
            case 'title':
                return (card.querySelector('.cu-task-title')?.textContent || '').trim().toLowerCase();
            default:
                return null;
        }
    }

    function applySort() {
        if (!sortSelect.value) return;
        /* Kanban columns */
        document.querySelectorAll('.cu-col-body').forEach(col => {
            const quick = col.querySelector('.cu-quickadd');
            const cards = [...col.querySelectorAll('.cu-task-card')];
            cards.sort((a, b) => {
                const va = sortValue(a), vb = sortValue(b);
                return va < vb ? -1 : va > vb ? 1 : 0;
            });
            cards.forEach(c => col.insertBefore(c, quick));
        });
        /* List rows */
        const listRowsWrap = document.getElementById('cuList');
        if (listRowsWrap) {
            const rows = [...listRowsWrap.querySelectorAll('.cu-list-row')];
            rows.sort((a, b) => {
                let va, vb;
                if (sortSelect.value === 'due') {
                    va = a.dataset.due ? new Date(a.dataset.due).getTime() : 99999999999999;
                    vb = b.dataset.due ? new Date(b.dataset.due).getTime() : 99999999999999;
                } else if (sortSelect.value === 'priority') {
                    va = PRIORITY_RANK[a.dataset.priority] ?? 2;
                    vb = PRIORITY_RANK[b.dataset.priority] ?? 2;
                } else {
                    va = a.dataset.title; vb = b.dataset.title;
                }
                return va < vb ? -1 : va > vb ? 1 : 0;
            });
            rows.forEach(r => listRowsWrap.appendChild(r));
        }
        updateCounts();
    }

    searchInput?.addEventListener('input', applyFilters);
    prioritySelect?.addEventListener('change', applyFilters);
    projectSelect?.addEventListener('change', applyFilters);
    statusSelect?.addEventListener('change', applyFilters);
    unfinishedBox?.addEventListener('change', () => { applyFilters(); savePrefs(); });
    sortSelect?.addEventListener('change', () => { applySort(); applyFilters(); });

    /* ─── Persistence (localStorage) ─── */
    const LS = 'cu_tasks_ui';
    function loadPrefs() {
        try {
            const p = JSON.parse(localStorage.getItem(LS) || '{}');
            if (p.view && document.querySelector(`.cu-view-btn[data-view="${p.view}"]`)) {
                document.querySelector(`.cu-view-btn[data-view="${p.view}"]`).click();
            }
            if (searchInput   && p.search)   searchInput.value   = p.search;
            if (prioritySelect && p.priority) prioritySelect.value = p.priority;
            if (projectSelect && p.project)  projectSelect.value = p.project;
            if (statusSelect  && p.status)   statusSelect.value  = p.status;
            if (sortSelect    && p.sort)     sortSelect.value    = p.sort;
            if (unfinishedBox) unfinishedBox.checked = !!p.unfinished;
            if (p.chapters) {
                Object.entries(p.chapters).forEach(([sectionId, isCollapsed]) => {
                    const ch = document.querySelector(`[data-chapter="${sectionId}"]`);
                    if (ch) setChapter(ch, !!isCollapsed);
                });
            }
            if (p.collapsed) {
                Object.entries(p.collapsed).forEach(([status, isCollapsed]) => {
                    const head = document.querySelector(`[data-col-toggle="${status}"]`);
                    if (!head) return;
                    const col = head.closest('.cu-col');
                    col.classList.toggle('collapsed', !!isCollapsed);
                    col.dataset.collapsed = isCollapsed ? '1' : '0';
                });
            }
        } catch (e) { /* ignore corrupt prefs */ }
    }
    function savePrefs() {
        try {
            const collapsed = {};
            document.querySelectorAll('[data-col-toggle]').forEach(h => {
                const col = h.closest('.cu-col');
                collapsed[col.querySelector('.cu-col-body').dataset.status] = col.classList.contains('collapsed');
            });
            const chaptersCollapsed = {};
            document.querySelectorAll('.cu-chapter').forEach(ch => {
                chaptersCollapsed[ch.dataset.chapter] = ch.classList.contains('collapsed');
            });
            localStorage.setItem(LS, JSON.stringify({
                view: document.querySelector('.cu-view-btn.active')?.dataset.view,
                search: searchInput?.value ?? '',
                priority: prioritySelect?.value ?? '',
                project: projectSelect?.value ?? '',
                status: statusSelect?.value ?? '',
                sort: sortSelect?.value ?? '',
                unfinished: unfinishedBox?.checked || false,
                collapsed,
                chapters: chaptersCollapsed
            }));
        } catch (e) { /* storage unavailable */ }
    }
    ['input','change'].forEach(ev => {
        [searchInput, prioritySelect, projectSelect, statusSelect, sortSelect, unfinishedBox]
            .forEach(el => el?.addEventListener(ev, savePrefs));
    });
    document.querySelectorAll('[data-col-toggle]').forEach(h =>
        h.addEventListener('click', () => setTimeout(savePrefs, 0)));
    loadPrefs();
    applySort();
    applyFilters();

    /* ─── Keyboard shortcuts ─── */
    /* Track hovered task so "T" adds THAT row/card to today's plan */
    let hoveredAddDayBtn = null;
    let hoveredChapter = null;
    document.addEventListener('mouseover', e => {
        const holder = e.target.closest?.('.cu-ch-row, .cu-task-card, .cu-list-row, .cu-ttree-row');
        if (holder) {
            const btn = holder.querySelector('[data-add-day]') || (holder.matches?.('[data-add-day]') ? holder : null);
            hoveredAddDayBtn = btn || null;
        }
        /* Track hovered project section so "A" quick-adds THERE */
        const ch = e.target.closest?.('.cu-chapter');
        hoveredChapter = (ch && ch.querySelector('[data-ch-quickadd]')) ? ch : null;
    }, { passive: true });
    document.addEventListener('keydown', e => {
        if (e.metaKey || e.ctrlKey || e.altKey) return;
        const t = e.target;
        if (t.matches?.('input, textarea, select') || t.isContentEditable) return;
        if (e.key === '/') { e.preventDefault(); searchInput?.focus(); searchInput?.select(); return; }
        if (e.key.toLowerCase() === 'n') {
            e.preventDefault();
            const m = bootstrap.Modal.getOrCreateInstance(document.getElementById('createTaskModal'));
            m.show();
            return;
        }
        /* T = add hovered / focused task to today's plan */
        if (e.key.toLowerCase() === 't' || e.key.toLowerCase() === 'ف') {
            const inModal = addDayModal && !addDayModal.hidden;
            if (inModal) return;
            let trigger = null;
            const focused = document.activeElement?.closest?.('.cu-ch-row, .cu-task-card, .cu-list-row, .cu-ttree-row');
            if (focused) trigger = focused.querySelector('[data-add-day]');
            if (!trigger) trigger = hoveredAddDayBtn;
            if (trigger && trigger.isConnected && trigger.offsetParent !== null) {
                e.preventDefault();
                openAddDay(trigger);
            }
            return;
        }
        /* A = quick-add a task to the hovered / focused project section */
        if (e.key.toLowerCase() === 'a' || e.key === 'ش') {
            const inModal = addDayModal && !addDayModal.hidden;
            if (inModal) return;
            let ch = document.activeElement?.closest?.('.cu-chapter');
            if (!ch || !ch.querySelector('[data-ch-quickadd]')) ch = hoveredChapter;
            if (ch && ch.isConnected) {
                e.preventDefault();
                openChQuick(ch.dataset.chapter);
            }
        }
    });

    /* Quill (full modal) */
    let taskQuill = null;
    const modal = document.getElementById('createTaskModal');
    modal?.addEventListener('show.bs.modal', e => {
        const status = e.relatedTarget?.dataset.status || 'to_do';
        const si = document.getElementById('task_status');
        if (si) si.value = status;
        if (!taskQuill) {
            setTimeout(() => {
                taskQuill = new Quill('#task-quill-editor', {
                    theme: 'snow',
                    placeholder: 'Describe the task…',
                    modules: { toolbar: [['bold','italic','underline'],[{'list':'ordered'},{'list':'bullet'}],['link'],['clean']] }
                });
                taskQuill.on('text-change', () => {
                    const el = document.getElementById('task_description');
                    if (el) el.value = taskQuill.root.innerHTML;
                });
            }, 80);
        }
    });

    /* ─── Drag & drop: whole-card drag, drop indicator, auto-scroll, order persist ─── */
    let dropTarget = null; /* {card, pos: 'before'|'after'} */
    function clearIndicators() {
        document.querySelectorAll('.cu-task-card.drop-before,.cu-task-card.drop-after')
            .forEach(c => c.classList.remove('drop-before', 'drop-after'));
        dropTarget = null;
    }
    if (kanban) {
        kanban.querySelectorAll('.cu-task-card').forEach(c => { c.draggable = true; });

        /* Remember what the user grabbed so controls stay click-only */
        let dragSource = null;
        kanban.addEventListener('mousedown', e => { dragSource = e.target; });

        kanban.addEventListener('dragstart', e => {
            const card = (e.target.closest && e.target.closest('.cu-task-card')) || e.target;
            if (!card.classList || !card.classList.contains('cu-task-card')) return;
            const grab = dragSource || e.target;
            if (document.body.classList.contains('cu-selecting')
                || grab.closest('.cu-select-box, .cu-check, .cu-add-day, .cu-card-menu, form')) {
                e.preventDefault();
                return;
            }
            card.classList.add('dragging');
            e.dataTransfer.setData('text/plain', card.dataset.id);
            e.dataTransfer.effectAllowed = 'move';
        });
        kanban.addEventListener('dragend', () => {
            document.querySelectorAll('.cu-task-card.dragging').forEach(c => {
                c.classList.remove('dragging');
            });
            document.querySelectorAll('.cu-col-body').forEach(c => c.classList.remove('drop-target'));
            clearIndicators();
        });
        document.querySelectorAll('.cu-col-body').forEach(col => {
            col.addEventListener('dragover', e => {
                e.preventDefault();
                col.classList.add('drop-target');
                /* Auto-scroll the column near its edges */
                const r = col.getBoundingClientRect();
                if (e.clientY < r.top + 48) col.scrollTop -= 10;
                else if (e.clientY > r.bottom - 48) col.scrollTop += 10;
                /* Insertion indicator between cards */
                clearIndicators();
                const over = e.target.closest('.cu-task-card:not(.dragging)');
                if (over && col.contains(over)) {
                    const mid = over.getBoundingClientRect().top + over.offsetHeight / 2;
                    const pos = e.clientY < mid ? 'before' : 'after';
                    over.classList.add(pos === 'before' ? 'drop-before' : 'drop-after');
                    dropTarget = { card: over, pos };
                }
            });
            col.addEventListener('dragleave', e => {
                if (!col.contains(e.relatedTarget)) col.classList.remove('drop-target');
            });
            col.addEventListener('drop', e => {
                e.preventDefault();
                col.classList.remove('drop-target');
                const taskId = e.dataTransfer.getData('text/plain');
                const card   = document.querySelector(`.cu-task-card[data-id="${taskId}"]`);
                const target = dropTarget;
                clearIndicators();
                if (!card) return;
                const status = col.dataset.status;
                const from = card.dataset.status;
                const quick = col.querySelector('.cu-quickadd');
                if (target && target.card.isConnected && col.contains(target.card) && target.card !== card) {
                    col.insertBefore(card, target.pos === 'before' ? target.card : target.card.nextSibling);
                    if (quick) col.appendChild(quick);
                } else {
                    col.insertBefore(card, quick);
                }
                updateCounts(); /* hide empty placeholder immediately, no server round-trip wait */
                updateStatus(taskId, status, () => {
                    syncTaskDoneUI(taskId, status, false);
                    if (from !== status) toast(@json(__('Task moved ✓')));
                    updateCounts();
                    applyFilters();
                    persistColumnOrder(col);
                });
            });
        });
    }

    /* Persist card order inside a column (parent links preserved) */
    function persistColumnOrder(col) {
        const items = [...col.querySelectorAll('.cu-task-card')].map((c, i) => ({
            id: parseInt(c.dataset.id, 10),
            parent_id: c.dataset.parent ? parseInt(c.dataset.parent, 10) : null,
            sort_order: i
        }));
        if (!items.length) return;
        fetch(`{{ route('tasks.reorder') }}`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify({ items })
        }).catch(() => toast(@json(__('Order not saved'))));
    }

    /* Quick check toggle — delegation (works for board / chapter / list / tree) */
    document.addEventListener('click', e => {
        const check = e.target.closest('.cu-check');
        if (check) {
            e.preventDefault();
            const holder = check.closest('.cu-task-card, .cu-ch-row, .cu-list-row, .cu-ttree-row, .cu-ttree-node');
            if (!holder) return;
            const id = holder.dataset.id;
            const isDone = holder.classList.contains('is-done');
            const newStatus = isDone ? 'to_do' : 'completed';
            updateStatus(id, newStatus, () => {
                syncTaskDoneUI(id, newStatus);
                updateCounts();
                applyFilters();
                toast(newStatus === 'completed' ? TASK_STATUS_TOAST.completed : TASK_STATUS_TOAST.reopened);
            });
            return;
        }

        const del = e.target.closest('.cu-del-task');
        if (del) {
            e.preventDefault();
            const id = del.dataset.id;
            confirmSwal('{{ __('Delete this task? This cannot be undone.') }}', { isDelete: true }).then(ok => {
                if (!ok) return;
                fetch(`/tasks/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                }).then(r => {
                    window.location.reload();
                }).catch(() => {
                    window.location.reload();
                });
            });
        }
    });

    /* ─── Bulk select mode — polished, works across all 5 views ─── */
    const bulkBar = document.getElementById('cuBulkBar');
    const bulkCount = document.getElementById('cuBulkCount');
    const bulkStatus = document.getElementById('cuBulkStatus');
    const selectModeBtn = document.getElementById('cuSelectMode');
    const selectBadge = document.getElementById('cuSelectBadge');
    const bulkSelectAllBtn = document.getElementById('cuBulkSelectAll');
    const bulkClearBtn = document.getElementById('cuBulkClear');
    let lastChecked = null;

    function selectedIds() {
        const ids = [...document.querySelectorAll('.cu-select-box:checked')].map(b => b.dataset.id);
        return [...new Set(ids)];
    }
    function syncIsSelected() {
        document.querySelectorAll('.cu-task-card, .cu-ch-row, .cu-list-row, .cu-ttree-row').forEach(el => {
            const box = el.querySelector('.cu-select-box');
            el.classList.toggle('is-selected', !!(box && box.checked));
        });
    }
    function refreshBulkBar() {
        const n = selectedIds().length;
        bulkCount.textContent = n;
        if(selectBadge) selectBadge.textContent = n;
        const show = n > 0 || document.body.classList.contains('cu-selecting');
        bulkBar.classList.toggle('show', show && n > 0);
        // keep select button active while selecting even with 0 chosen
        if(document.body.classList.contains('cu-selecting') && n===0) {
            bulkBar.classList.remove('show');
        }
        syncIsSelected();
    }
    function exitSelectMode() {
        document.body.classList.remove('cu-selecting');
        selectModeBtn?.classList.remove('on');
        document.querySelectorAll('.cu-select-box:checked').forEach(b => { b.checked = false; });
        lastChecked = null;
        syncIsSelected();
        refreshBulkBar();
    }
    function syncDuplicates(changedBox){
        const id = changedBox.dataset.id;
        const checked = changedBox.checked;
        document.querySelectorAll(`.cu-select-box[data-id="${id}"]`).forEach(b => {
            if(b!==changedBox) b.checked = checked;
        });
    }
    function allVisibleBoxes(){
        // visible checkboxes whose nearest task container is not display:none
        return [...document.querySelectorAll('.cu-select-box')].filter(b => {
            const holder = b.closest('.cu-task-card, .cu-ch-row, .cu-list-row, .cu-ttree-row');
            if(!holder) return false;
            if(holder.style.display==='none') return false;
            // also check if parent view is hidden
            let p = holder;
            while(p && p!==document.body){
                if(p.style && p.style.display==='none') return false;
                p = p.parentElement;
            }
            return holder.offsetParent !== null || holder.closest('#cuKanban, #cuList, #cuTree, #cuChapters, #cuProjects');
        }).filter(b=>{
            const holder = b.closest('.cu-task-card, .cu-ch-row, .cu-list-row, .cu-ttree-row');
            return holder && holder.style.display!=='none';
        });
    }
    selectModeBtn?.addEventListener('click', () => {
        const on = document.body.classList.toggle('cu-selecting');
        selectModeBtn.classList.toggle('on', on);
        if (!on) exitSelectMode(); else refreshBulkBar();
    });
    bulkClearBtn?.addEventListener('click', () => {
        document.querySelectorAll('.cu-select-box:checked').forEach(b=>b.checked=false);
        syncIsSelected(); refreshBulkBar();
    });
    bulkSelectAllBtn?.addEventListener('click', () => {
        const visible = [...document.querySelectorAll('.cu-select-box')].filter(b=>{
            const row = b.closest('.cu-task-card, .cu-ch-row, .cu-list-row, .cu-ttree-row');
            if(!row) return false;
            if(row.style.display==='none') return false;
            // check if its view container is hidden
            const view = row.closest('#cuKanban, #cuList, #cuTree, #cuChapters, #cuProjects');
            if(view && view.style.display==='none') return false;
            // also filter parent chapter collapsed (body hidden)
            const chBody = row.closest('.cu-chapter-body');
            if(chBody && chBody.offsetParent===null) return false;
            const treeParent = row.closest('.cu-ttree-children');
            if(treeParent && treeParent.style.display==='none') return false;
            return true;
        });
        const allChecked = visible.length>0 && visible.every(b=>b.checked);
        visible.forEach(b=> b.checked = !allChecked);
        // sync duplicates for each id
        const ids = [...new Set(visible.map(b=>b.dataset.id))];
        ids.forEach(id=>{
            const any = document.querySelector(`.cu-select-box[data-id="${id}"]`);
            const checked = document.querySelector(`.cu-select-box[data-id="${id}"]:checked`) ? true : false;
            // ensure all duplicates match majority state
            const state = visible.find(b=>b.dataset.id===id)?.checked ?? checked;
            document.querySelectorAll(`.cu-select-box[data-id="${id}"]`).forEach(b=> b.checked = state);
        });
        syncIsSelected(); refreshBulkBar();
    });
    document.addEventListener('change', e => {
        const box = e.target.closest?.('.cu-select-box');
        if (!box) return;
        syncDuplicates(box);
        /* Shift+click range select across visible boxes */
        if (e.shiftKey && lastChecked && lastChecked !== box) {
            const boxes = [...document.querySelectorAll('.cu-select-box')]
                .filter(b => {
                    const row = b.closest('.cu-task-card, .cu-ch-row, .cu-list-row, .cu-ttree-row');
                    if(!row || row.style.display==='none') return false;
                    const view = row.closest('#cuKanban, #cuList, #cuTree, #cuChapters, #cuProjects');
                    if(view && view.style.display==='none') return false;
                    return true;
                });
            const [a, b] = [boxes.indexOf(lastChecked), boxes.indexOf(box)].sort((x, y) => x - y);
            if(a>=0 && b>=0) boxes.slice(a, b + 1).forEach(x => {
                x.checked = box.checked;
                syncDuplicates(x);
            });
        }
        lastChecked = box;
        if (!document.body.classList.contains('cu-selecting')) {
            document.body.classList.add('cu-selecting');
            selectModeBtn?.classList.add('on');
        }
        refreshBulkBar();
    });
    // clicking the row itself toggles selection when in selecting mode (convenient on mobile)
    document.addEventListener('click', e=>{
        if(!document.body.classList.contains('cu-selecting')) return;
        const row = e.target.closest('.cu-task-card, .cu-ch-row, .cu-list-row, .cu-ttree-row');
        if(!row) return;
        if(e.target.closest('a, button, input, select, .dropdown-menu')) return;
        const box = row.querySelector('.cu-select-box');
        if(!box) return;
        box.checked = !box.checked;
        syncDuplicates(box);
        syncIsSelected(); refreshBulkBar();
    });
    document.getElementById('cuBulkCancel')?.addEventListener('click', exitSelectMode);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && document.body.classList.contains('cu-selecting')
            && !e.target.matches?.('input[type="text"], textarea')) exitSelectMode();
    });

    function bulkMove(status) {
        const ids = selectedIds();
        if (!ids.length) return;
        fetch(`{{ route('tasks.bulk-update') }}`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify({ ids, status })
        }).then(r => r.json()).then(j => {
            if (!j.ok) throw new Error();
            ids.forEach(id => syncTaskDoneUI(id, status));
            updateCounts();
            applyFilters();
            exitSelectMode();
            toast(TASK_BULK_TOAST.updated.replace(':count', j.updated));
        }).catch(() => toast(@json(__('Bulk update failed'))));
    }
    document.getElementById('cuBulkApply')?.addEventListener('click', () => bulkMove(bulkStatus.value));
    document.getElementById('cuBulkDone')?.addEventListener('click', () => bulkMove('completed'));
    document.getElementById('cuBulkDelete')?.addEventListener('click', async () => {
        const ids = selectedIds();
        if (!ids.length) return;
        const msg = '{{ app()->getLocale() === "fa" ? "آیا از حذف کارهای انتخاب‌شده مطمئن هستید؟" : "Delete selected tasks? This cannot be undone." }}';
        if (!await confirmSwal(msg, { isDelete: true })) return;
        fetch(`{{ route('tasks.bulk-destroy') }}`, {
            method: 'DELETE',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify({ ids })
        }).then(r => r.json()).then(j => {
            if (!j.ok) throw new Error();
            ids.forEach(id => removeTaskNodes(id));
            updateCounts();
            applyFilters();
            exitSelectMode();
            toast(TASK_BULK_TOAST.deleted.replace(':count', j.deleted));
        }).catch(() => toast(@json(__('Bulk delete failed'))));
    });

    /* Unified done-state sync across board card, chapter row and section progress */
    function applyDoneState(el, status) {
        const done = status === 'completed';
        el.classList.toggle('is-done', done);
        el.dataset.status = status;
        const check = el.querySelector('.cu-check');
        if (check) {
            check.classList.toggle('done', done);
            check.title = done ? 'Mark as To Do' : 'Mark as Completed';
            const ico = check.querySelector('i');
            if (ico) ico.className = 'bi ' + (done ? 'bi-check-circle-fill' : 'bi-circle');
        }
    }

    function refreshChapter(ch) {
        const rows = [...ch.querySelectorAll('.cu-ch-row')];
        const total = rows.length;
        const done = rows.filter(r => r.dataset.status === 'completed').length;
        const pct = total > 0 ? Math.round(done / total * 100) : 0;
        const fill = ch.querySelector('.cu-chapter-pb-fill');
        const count = ch.querySelector('.cu-chapter-count');
        const open = ch.querySelector('.cu-chapter-open');
        if (fill)  fill.style.width = pct + '%';
        if (count) { count.textContent = `${done}/${total}`; count.title = `${done} of ${total} done`; }
        if (open)  open.textContent = `${total - done} open`;
        ch.dataset.total = total;
        ch.dataset.done = done;
    }

    /* A task can appear in several views at once (board + chapters + projects + list + tree):
       always sync/remove EVERY copy. */
    function syncTaskDoneUI(id, status, moveCard = true) {
        const touched = new Set();
        document.querySelectorAll(`.cu-task-card[data-id="${id}"]`).forEach(card => {
            const col = document.getElementById(`col-${status}`);
            if (moveCard && col && card.closest('#cuKanban')) {
                col.insertBefore(card, col.querySelector('.cu-quickadd'));
                const colEl = col.closest('.cu-col');
                if (status === 'completed' && colEl.classList.contains('collapsed')) {
                    colEl.classList.remove('collapsed');
                    colEl.dataset.collapsed = '0';
                }
            }
            applyDoneState(card, status);
        });
        document.querySelectorAll(`.cu-ch-row[data-id="${id}"]`).forEach(row => {
            applyDoneState(row, status);
            const ch = row.closest('.cu-chapter');
            if (ch) touched.add(ch);
        });
        document.querySelectorAll(`.cu-list-row[data-id="${id}"]`).forEach(row => {
            applyDoneState(row, status);
        });
        document.querySelectorAll(`.cu-ttree-row[data-id="${id}"]`).forEach(row => {
            applyDoneState(row.closest('.cu-ttree-node') || row, status);
            // tree row itself holds data-status now
            row.dataset.status = status;
            const node = row.closest('.cu-ttree-node');
            if(node) node.dataset.status = status;
        });
        // also sync status chip inside tree/list
        document.querySelectorAll(`[data-id="${id}"]`).forEach(el=>{
            if(el.dataset) el.dataset.status = status;
        });
        touched.forEach(ch => refreshChapter(ch));
    }

    function removeTaskNodes(id) {
        const touched = new Set();
        document.querySelectorAll(`.cu-task-card[data-id="${id}"]`).forEach(c => c.remove());
        document.querySelectorAll(`.cu-ch-row[data-id="${id}"]`).forEach(r => {
            const ch = r.closest('.cu-chapter');
            if (ch) touched.add(ch);
            r.remove();
        });
        document.querySelectorAll(`.cu-list-row[data-id="${id}"]`).forEach(r => r.remove());
        document.querySelectorAll(`.cu-ttree-node:has(.cu-ttree-row[data-id="${id}"]), .cu-ttree-row[data-id="${id}"]`).forEach(n=>{
            // if whole node wraps the row, remove node; otherwise row
            if(n.classList.contains('cu-ttree-node')) n.remove();
        });
        // fallback: remove any remaining data-id containers
        document.querySelectorAll(`.cu-ttree-row[data-id="${id}"]`).forEach(r=>{
            const node = r.closest('.cu-ttree-node');
            if(node) node.remove(); else r.remove();
        });
        touched.forEach(ch => refreshChapter(ch));
    }

    function updateCounts() {
        ['to_do','in_progress','on_hold','in_review','completed'].forEach(s => {
            const col = document.getElementById(`col-${s}`);
            const cnt = document.getElementById(`cnt-${s}`);
            if (col && cnt) {
                cnt.textContent = [...col.querySelectorAll('.cu-task-card')]
                    .filter(c => c.style.display !== 'none').length;
            }
        });
        refreshEmptyStates();
    }

    /* Show/hide the "empty column" placeholder in sync with the cards */
    function refreshEmptyStates() {
        document.querySelectorAll('.cu-col-body').forEach(col => {
            const empty = col.querySelector('.cu-col-empty');
            if (!empty) return;
            const visible = [...col.querySelectorAll('.cu-task-card')]
                .filter(c => c.style.display !== 'none').length;
            empty.style.display = visible ? 'none' : '';
        });
    }

    function updateStatus(taskId, status, onDone) {
        fetch(`/tasks/${taskId}/update-status`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify({ status })
        }).then(r => {
            if (r.ok) { onDone && onDone(); }
            else location.reload();
        }).catch(() => location.reload());
    }

    /* ─── Quick add ─── */
    document.querySelectorAll('.cu-quickadd').forEach(form => {
        const input = form.querySelector('input');
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const title = input.value.trim();
                if (!title) return;
                quickAdd(form.dataset.quickadd, title);
            } else if (e.key === 'Escape') {
                input.value = '';
                input.blur();
            }
        });
    });

    function quickAdd(status, title) {
        /* On the global page fall back to the selected project filter;
           empty = task without a project */
        let projectId = `{{ $project->id ?? '' }}`;
        if (!projectId && projectSelect) {
            projectId = projectSelect.value || '';
        }

        /* Full POST + redirect back: server renders the complete card markup.
           On a project page use the project-scoped route so we stay here. */
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = `{{ isset($project) ? route('projects.tasks.store', $project) : route('tasks.store') }}`;
        f.innerHTML = `
            <input type="hidden" name="_token" value="${csrf}">
            <input type="hidden" name="title" value="">
            <input type="hidden" name="project_id" value="${projectId}">
            <input type="hidden" name="user_id" value="{{ auth()->id() }}">
            <input type="hidden" name="priority" value="medium">
            <input type="hidden" name="status" value="${status}">
            <input type="hidden" name="auto_weight" value="1">
            <input type="hidden" name="weight" value="1">`;
        f.querySelector('input[name="title"]').value = title;
        document.body.appendChild(f);
        f.submit();
    }

    /* ── Schedule task: day (today / tomorrow / future) + time period ── */
    const addDayModal = document.getElementById('addToDayModal');
    const PLANNER_BASE = @json(route('planner.index'));
    const APP_LOCALE = @json(app()->getLocale());
    const SCHED_TXT = {
        today: @json(__('Today')),
        tomorrow: @json(__('Tomorrow')),
        dayAfter: @json(__('Day after tomorrow')),
        anytime: @json(__('Anytime')),
        pickHint: @json(__('Pick a day, then a time')),
        changeSlot: @json(__("Change day's slot")),
        addToPlan: @json(__('Add to day plan')),
    };
    const INTL_LOCALE = APP_LOCALE === 'fa' ? 'fa-IR' : APP_LOCALE;
    const QUICK_DAYS = 8;
    let addDayState = null;
    let schedSaving = false;

    function isoOf(d) {
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${d.getFullYear()}-${m}-${day}`;
    }
    function parseISO(iso) {
        const [y, m, d] = iso.split('-').map(Number);
        return new Date(y, m - 1, d);
    }
    function todayISO() { return isoOf(new Date()); }
    function diffDays(iso) {
        const t = new Date(); t.setHours(0, 0, 0, 0);
        return Math.round((parseISO(iso) - t) / 86400000);
    }
    function schedDateLabel(iso) {
        const diff = diffDays(iso);
        if (diff === 0) return SCHED_TXT.today;
        if (diff === 1) return SCHED_TXT.tomorrow;
        if (diff === 2) return SCHED_TXT.dayAfter;
        try {
            const d = parseISO(iso);
            const wd = new Intl.DateTimeFormat(INTL_LOCALE, { weekday: 'short' }).format(d);
            const dm = new Intl.DateTimeFormat(INTL_LOCALE, { day: 'numeric', month: 'short' }).format(d);
            return `${wd} ${dm}`;
        } catch (e) { return iso; }
    }
    function schedPeriodLabel(period) {
        if (!period) return SCHED_TXT.anytime;
        const chip = addDayModal?.querySelector(`.cud-chip[data-period="${period}"]`);
        return chip ? chip.textContent.trim() : period;
    }

    function renderSchedDays() {
        const wrap = addDayModal.querySelector('[data-schedule-days]');
        if (!wrap || !addDayState) return;
        wrap.innerHTML = '';
        const base = new Date(); base.setHours(0, 0, 0, 0);
        for (let i = 0; i < QUICK_DAYS; i++) {
            const d = new Date(base); d.setDate(base.getDate() + i);
            const iso = isoOf(d);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cud-day' + (iso === addDayState.date ? ' active' : '');
            btn.dataset.date = iso;
            let top;
            if (i === 0) top = SCHED_TXT.today;
            else if (i === 1) top = SCHED_TXT.tomorrow;
            else if (i === 2) top = SCHED_TXT.dayAfter;
            else {
                try { top = new Intl.DateTimeFormat(INTL_LOCALE, { weekday: 'short' }).format(d); }
                catch (e) { top = ''; }
            }
            let num, mon;
            try {
                num = new Intl.DateTimeFormat(INTL_LOCALE, { day: 'numeric' }).format(d);
                mon = new Intl.DateTimeFormat(INTL_LOCALE, { month: 'short' }).format(d);
            } catch (e) { num = d.getDate(); mon = ''; }
            btn.innerHTML = `<span class="d-kbd">${i + 1}</span><span class="d-top"></span><span class="d-num"></span><span class="d-mon"></span>`;
            btn.querySelector('.d-top').textContent = top;
            btn.querySelector('.d-num').textContent = num;
            btn.querySelector('.d-mon').textContent = mon;
            btn.title = iso;
            wrap.appendChild(btn);
        }
        syncSchedCustomUI();
    }

    function schedCustomEls() {
        return {
            hidden: addDayModal.querySelector('[data-schedule-custom]'),
            vis: document.getElementById('schedCustomDate-jalali'),
        };
    }
    function schedInStrip() {
        const t = new Date(); t.setHours(0, 0, 0, 0);
        const dd = Math.round((parseISO(addDayState.date) - t) / 86400000);
        return dd >= 0 && dd < QUICK_DAYS;
    }
    function syncSchedCustomUI() {
        /* Mirror the Gregorian value into the visible input (Jalali text in fa locale) */
        const { hidden, vis } = schedCustomEls();
        if (!hidden || !addDayState) return;
        if ('min' in hidden) hidden.min = todayISO();
        const inStrip = schedInStrip();
        hidden.value = inStrip ? '' : addDayState.date;
        hidden.classList.toggle('has-value', !inStrip);
        if (vis) {
            if (typeof window.jalaliSyncVisible === 'function') window.jalaliSyncVisible('schedCustomDate');
            else vis.value = hidden.value;
            vis.classList.toggle('has-value', !inStrip);
        }
    }
    function clearSchedCustom() {
        const { hidden, vis } = schedCustomEls();
        if (hidden) { hidden.value = ''; hidden.classList.remove('has-value'); }
        if (vis) { vis.value = ''; vis.classList.remove('has-value'); }
    }

    function refreshSchedSummary() {
        if (!addDayState) return;
        const el = addDayModal.querySelector('[data-schedule-summary]');
        if (el) el.textContent = `${schedDateLabel(addDayState.date)} · ${schedPeriodLabel(addDayState.period)}`;
        const open = addDayModal.querySelector('[data-schedule-open]');
        if (open) open.href = `${PLANNER_BASE}?date=${addDayState.date}`;
    }

    function markSchedActive() {
        if (!addDayState) return;
        addDayModal.querySelectorAll('.cud-day').forEach(b => {
            b.classList.toggle('active', b.dataset.date === addDayState.date);
        });
        addDayModal.querySelectorAll('.cud-chip').forEach(c => {
            c.classList.toggle('active', (c.dataset.period || '') === (addDayState.period || ''));
        });
        refreshSchedSummary();
    }

    function openAddDay(trigger) {
        if (!addDayModal) return;
        /* Preselect the task's current due date when it is today or in the future */
        let initial = todayISO();
        const rawDue = trigger.dataset.date || trigger.dataset.due
            || trigger.closest?.('[data-due]')?.dataset.due || '';
        if (rawDue) {
            const iso = String(rawDue).slice(0, 10);
            if (/^\d{4}-\d{2}-\d{2}$/.test(iso) && iso >= todayISO()) initial = iso;
        }
        addDayState = {
            id: trigger.dataset.id,
            title: trigger.dataset.title || '',
            period: trigger.dataset.period || '',
            date: initial,
            hadPeriod: !!(trigger.dataset.period || ''),
            source: trigger,
        };
        schedSaving = false;
        const titleEl = addDayModal.querySelector('[data-add-day-title]');
        if (titleEl) titleEl.textContent = addDayState.title;
        renderSchedDays();
        markSchedActive();
        addDayModal.hidden = false;
    }

    function closeAddDay() {
        if (!addDayModal) return;
        addDayModal.hidden = true;
        addDayState = null;
        schedSaving = false;
    }

    async function applySchedule(period, chip) {
        if (!addDayState || schedSaving) return;
        if (typeof period === 'string') addDayState.period = period;
        const state = { ...addDayState };
        schedSaving = true;
        chip?.classList.add('busy');
        addDayModal.querySelectorAll('.cud-chip, .cud-day').forEach(b => b.classList.add('busy'));
        const send = () => fetch(`{{ url('tasks') }}/${state.id}/add-to-day`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ time_period: state.period || null, date: state.date }),
        });
        try {
            let res = await send();
            /* Session may have rotated the token since this page loaded */
            if (res.status === 419) {
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) { csrf = meta.content; res = await send(); }
            }
            if (res.status === 401) { window.location.reload(); return; }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            const dueISO = (json.due_date || state.date).slice(0, 10);

            /* Immediate feedback on EVERY copy of this task (board + chapters + projects) */
            document.querySelectorAll(`[data-add-day][data-id="${state.id}"]`).forEach(b => {
                b.dataset.period = state.period || '';
                b.dataset.date = dueISO;
                b.classList.add('set');
                b.classList.add('is-set');
                b.title = `${SCHED_TXT.changeSlot} (T)`;
                const icon = b.querySelector('i.bi, i');
                if (icon) {
                    if (b.classList.contains('cu-day-shortcut')) {
                        icon.className = 'bi bi-calendar2-check';
                    } else {
                        icon.className = 'bi ' + (state.period ? 'bi-calendar2-check' : 'bi-calendar-check');
                    }
                }
                if (b.classList.contains('cu-day-shortcut')) {
                    const label = b.querySelector('span:not(.cu-day-kbd)');
                    if (label) label.textContent = `${schedDateLabel(dueISO)} ✓`;
                }
                const holder = b.closest?.('[data-due]');
                if (holder) holder.dataset.due = dueISO;
            });

            closeAddDay();
            toast(`${schedDateLabel(dueISO)} · ${json.period_label} ✓`);
        } catch (err) {
            console.error('[Tasks] schedule failed', err);
            toast(`${ADD_TO_DAY_ERROR} (${err.message || NETWORK_ERROR})`);
        } finally {
            schedSaving = false;
            addDayModal.querySelectorAll('.busy').forEach(b => b.classList.remove('busy'));
            if (addDayModal.hidden) addDayState = null;
        }
    }

    document.addEventListener('click', e => {
        if (!addDayModal) return;
        if (!addDayModal.hidden) {
            const day = e.target.closest('.cud-day');
            if (day && addDayModal.contains(day)) {
                addDayState.date = day.dataset.date;
                markSchedActive();
                clearSchedCustom();
                /* 1-click reschedule when the task already has a time slot */
                if (addDayState.hadPeriod) { applySchedule(addDayState.period, day); }
                return;
            }
            const chip = e.target.closest('.cud-chip');
            if (chip && addDayModal.contains(chip)) {
                addDayState.hadPeriod = true;
                applySchedule(chip.dataset.period || '', chip);
                return;
            }
        }
        const trigger = e.target.closest('[data-add-day]');
        if (trigger) {
            e.preventDefault();
            e.stopPropagation();
            openAddDay(trigger);
            return;
        }
        if (e.target.closest('[data-add-day-close]')) closeAddDay();
    });
    addDayModal?.querySelector('[data-schedule-custom]')?.addEventListener('change', e => {
        if (!addDayState) return;
        /* In fa locale this event comes from the hidden Gregorian input,
           auto-synced from the visible Jalali picker. */
        const val = String(e.target.value || '').slice(0, 10);
        if (!val) return;
        /* Reject past/invalid dates (extra guard next to the picker's own limits) */
        if (!/^\d{4}-\d{2}-\d{2}$/.test(val) || val < todayISO()) { clearSchedCustom(); return; }
        addDayState.date = val;
        e.target.classList.add('has-value');
        const vis = document.getElementById('schedCustomDate-jalali');
        if (vis) vis.classList.add('has-value');
        markSchedActive();
        if (addDayState.hadPeriod) { applySchedule(addDayState.period, null); }
    });

    /* ── Project quick-add: inline task creation (Enter = save, Esc = close) ── */
    function openChQuick(sectionId) {
        const ch = document.querySelector(`[data-chapter="${sectionId}"]`);
        if (!ch) return;
        if (ch.classList.contains('collapsed')) {
            setChapter(ch, false);
        }
        const form = ch.querySelector('[data-ch-quickform]');
        if (!form) return;
        form.hidden = false;
        setTimeout(() => form.querySelector('[data-ch-quickinput]')?.focus(), 30);
    }

    function insertHtml(html) {
        const tmp = document.createElement('template');
        tmp.innerHTML = html.trim();
        return tmp.content.firstElementChild;
    }

    async function submitChQuick(form) {
        const input = form.querySelector('[data-ch-quickinput]');
        const title = input.value.trim();
        if (!title || form.dataset.busy) return;
        const ch = form.closest('.cu-chapter');
        const projectId = ch?.querySelector('[data-ch-quickadd]')?.dataset.chQuickadd || null;
        form.dataset.busy = '1';
        const send = () => fetch(`{{ route('tasks.quick-store') }}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ title, project_id: projectId }),
        });
        try {
            let res = await send();
            /* Session may have rotated the token since this page loaded */
            if (res.status === 419) {
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) { csrf = meta.content; res = await send(); }
            }
            if (res.status === 401) { window.location.reload(); return; }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            if (!json.ok) throw new Error('bad response');

            /* 1. chapter row → top of this project section */
            if (json.rowHtml && ch) {
                const row = insertHtml(json.rowHtml);
                if (row) {
                    row.classList.add('row-flash');
                    const body = ch.querySelector('.cu-chapter-body');
                    body.insertBefore(row, body.querySelector('.cu-ch-row'));
                    refreshChapter(ch);
                }
            }
            /* 2. kanban card → its status column */
            if (json.cardHtml) {
                const col = document.getElementById(`col-${json.status}`);
                if (col) {
                    const card = insertHtml(json.cardHtml);
                    if (card) {
                        card.draggable = true;
                        card.classList.add('row-flash');
                        col.insertBefore(card, col.querySelector('.cu-quickadd'));
                        const colEl = col.closest('.cu-col');
                        if (colEl && colEl.classList.contains('collapsed')) {
                            colEl.classList.remove('collapsed');
                            colEl.dataset.collapsed = '0';
                        }
                    }
                }
            }
            /* 3. list view + 4. tree view — stay in sync without reload */
            if (json.listHtml && list) list.insertAdjacentHTML('beforeend', json.listHtml);
            if (json.treeHtml && tree) {
                const node = insertHtml(json.treeHtml);
                if (node) { node.classList.add('row-flash'); tree.appendChild(node); }
            }
            updateCounts();
            applyFilters();
            input.value = '';
            input.focus();
            toast(QUICK_CREATED);
        } catch (err) {
            console.error('[Tasks] quick-store failed', err);
            toast(`${QUICK_ERROR} (${err.message || NETWORK_ERROR})`);
        } finally {
            delete form.dataset.busy;
        }
    }

    document.addEventListener('click', e => {
        const q = e.target.closest('[data-ch-quickadd]');
        if (q) {
            e.preventDefault();
            e.stopPropagation();
            openChQuick(q.dataset.section);
        }
    });
    document.addEventListener('keydown', e => {
        const inp = e.target.closest?.('[data-ch-quickinput]');
        if (!inp) return;
        if (e.key === 'Enter') {
            e.preventDefault();
            submitChQuick(inp.closest('[data-ch-quickform]'));
        } else if (e.key === 'Escape') {
            inp.value = '';
            inp.closest('[data-ch-quickform]').hidden = true;
            inp.blur();
        }
    });
    document.addEventListener('keydown', e => {
        if (addDayModal && !addDayModal.hidden) {
            if (e.key === 'Escape') { closeAddDay(); return; }
            /* 1-8: quick day pick while the modal is open */
            const n = parseInt(e.key, 10);
            if (n >= 1 && n <= QUICK_DAYS && !e.metaKey && !e.ctrlKey && !e.altKey) {
                const t = e.target;
                if (t.matches?.('input, textarea, select') || t.isContentEditable) return;
                const days = addDayModal.querySelectorAll('.cud-day');
                if (days[n - 1]) { days[n - 1].click(); e.preventDefault(); }
            }
        }
    });
});
</script>
@endpush
