@extends('layouts.app')

@section('title', isset($project) ? $project->name . ' — Tasks' : 'My Tasks')

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
    .cu-kanban{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;align-items:start;}
    @media(max-width:1100px){.cu-kanban{grid-template-columns:repeat(3,1fr);}}
    @media(max-width:860px){.cu-kanban{grid-template-columns:1fr;}}
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
    .cu-col-body{padding:4px 8px 8px;min-height:100px;display:flex;flex-direction:column;gap:8px;}
    .cu-col.collapsed .cu-col-body{display:none;}
    .cu-col-body.drop-target{background:#ece9fd;border-radius:6px;}

    /* ─── Task card — minimal ─── */
    .cu-task-card{
        background:white;border:1px solid #e5e7eb;border-radius:6px;
        padding:10px 10px 8px;cursor:grab;transition:border-color .12s, box-shadow .12s;position:relative;
    }
    .cu-task-card:hover{border-color:#d3d7de;box-shadow:0 1px 3px rgba(0,0,0,.06);}
    .cu-task-card:hover .cu-task-menu-btn{opacity:1;}
    .cu-task-card.dragging{opacity:.4;}
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

    /* ─── Chapters view ─── */
    .cu-chapters-view{display:none;flex-direction:column;gap:10px;}
    .cu-chapter{background:white;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;}
    .cu-chapter-head{
        display:flex;align-items:center;gap:8px;padding:10px 12px;cursor:pointer;user-select:none;
    }
    .cu-chapter-head:hover{background:#fafbfc;}
    .cu-chapter.collapsed .cu-col-chevron{transform:rotate(-90deg);}
    .cu-chapter.collapsed .cu-chapter-body{display:none;}
    .cu-chapter-title{font-size:13.5px;font-weight:600;color:#1f2328;text-decoration:none;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    a.cu-chapter-title:hover{color:#7c3aed;}
    span.cu-chapter-title{cursor:default;}
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
                <div class="cu-header-title">{{ isset($project) ? $project->name . ' — Tasks' : 'My Tasks' }}</div>
                <div class="cu-header-sub">{{ isset($project) ? 'Manage and track tasks for this project' : 'All active tasks across your projects' }}</div>
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
                <button class="cu-view-btn active" data-view="kanban"><i class="bi bi-kanban"></i> Board</button>
                <button class="cu-view-btn" data-view="chapters"><i class="bi bi-collection"></i> Chapters</button>
                <button class="cu-view-btn" data-view="list"><i class="bi bi-list-ul"></i> List</button>
                <button class="cu-view-btn" data-view="tree"><i class="bi bi-diagram-3"></i> Tree</button>
            </div>
            <input type="text" class="cu-search-input" id="cuSearch" placeholder="Search tasks…">
            <select class="cu-filter-select" id="cuPriority">
                <option value="">All priorities</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
            <select class="cu-filter-select" id="cuProject">
                <option value="">All projects</option>
                @foreach($projects as $proj)
                    <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                @endforeach
            </select>
            <select class="cu-filter-select" id="cuStatus">
                <option value="">All statuses</option>
                <option value="to_do">To Do</option>
                <option value="in_progress">In Progress</option>
                <option value="on_hold">On Hold</option>
                <option value="in_review">In Review</option>
                <option value="completed">Completed</option>
            </select>
            <select class="cu-filter-select" id="cuSort" title="Sort tasks">
                <option value="">Sort: manual</option>
                <option value="due">Due date</option>
                <option value="priority">Priority</option>
                <option value="title">Title</option>
            </select>
            <label class="cu-checkline" id="cuUnfinishedWrap" title="Show only unfinished tasks" style="display:none;">
                <input type="checkbox" id="cuUnfinished"> Unfinished only
            </label>
            <span id="cuChapterExpandWrap" style="display:none;gap:4px;">
                <button class="cu-mini-btn" id="cuExpandAll" title="Expand all chapters">Expand all</button>
                <button class="cu-mini-btn" id="cuCollapseAll" title="Collapse all chapters">Collapse all</button>
            </span>
        </div>
        <div class="cu-toolbar-right">
            <span style="font-size:12px;color:#8b8d98;">{{ $totalCnt }} task{{ $totalCnt != 1 ? 's' : '' }}</span>
            <button class="cu-btn-new" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                <i class="bi bi-plus-lg"></i> New Task
            </button>
        </div>
    </div>

    @if(!$hasAny)
        <div class="cu-empty">
            <div class="cu-empty-icon"><i class="bi bi-list-task"></i></div>
            <h5>No tasks yet</h5>
            <p>{{ isset($project) ? 'Create the first task for ' . $project->name . '.' : 'No tasks found. Create one to get started.' }}</p>
            <button class="cu-btn-new" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                <i class="bi bi-plus-lg"></i> Create Task
            </button>
        </div>
    @else

    {{-- KANBAN --}}
    <div class="cu-kanban" id="cuKanban">
        @php
            $columns = [
                'to_do'      => ['label' => 'To Do',        'dot' => '#94a3b8',            'empty' => 'No tasks here',     'icon' => 'bi-circle',           'collapsed' => false],
                'in_progress'=> ['label' => 'In Progress',   'dot' => '#7c3aed',            'empty' => 'Nothing active',    'icon' => 'bi-arrow-clockwise',  'collapsed' => false],
                'on_hold'    => ['label' => 'On Hold',       'dot' => '#ad6800',            'empty' => 'None on hold',      'icon' => 'bi-pause-circle',     'collapsed' => true],
                'in_review'  => ['label' => 'In Review',     'dot' => '#0b6bcb',            'empty' => 'Nothing to review', 'icon' => 'bi-eye',              'collapsed' => true],
                'completed'  => ['label' => 'Completed',     'dot' => '#29774b',            'empty' => 'Nothing done yet',  'icon' => 'bi-check-circle',     'collapsed' => true],
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
                        <button class="cu-col-add" data-bs-toggle="modal" data-bs-target="#createTaskModal" data-status="{{ $statusKey }}" title="New task in this column">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                        <button class="cu-col-add" data-chevron="{{ $statusKey }}" title="Collapse / expand">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                </div>
                <div class="cu-col-body" id="col-{{ $statusKey }}" data-status="{{ $statusKey }}">
                    @forelse($tasks[$statusKey] ?? [] as $task)
                        @include('tasks._card', ['task' => $task])
                    @empty
                        <div class="cu-col-empty"><i class="bi {{ $col['icon'] }}" style="font-size:20px;display:block;margin-bottom:6px;"></i>{{ $col['empty'] }}</div>
                    @endforelse
                    <form class="cu-quickadd" data-quickadd="{{ $statusKey }}">
                        <i class="bi bi-plus-lg"></i>
                        <input type="text" placeholder="Add task…" data-status="{{ $statusKey }}">
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    {{-- LIST VIEW --}}
    <div class="cu-list-view" id="cuList">
        <div class="cu-list-head">
            <div>Task</div><div>Project</div><div>Priority</div>
            <div>Assignee</div><div>Due Date</div><div></div>
        </div>
        @foreach(collect($tasks)->flatten() as $task)
        <div class="cu-list-row" data-title="{{ strtolower($task->title) }}" data-priority="{{ $task->priority }}" data-project="{{ $task->project_id }}" data-status="{{ $task->status }}" data-due="{{ $task->due_date ?? '' }}">
            <div>
                <div class="cu-list-title">{{ $task->title }}</div>
                <div class="cu-list-sub">
                    <span class="cu-status-chip {{ $task->status }}">
                        <i class="bi bi-circle-fill" style="font-size:5px;"></i>
                        {{ ucwords(str_replace('_',' ',$task->status)) }}
                    </span>
                </div>
            </div>
            <div class="cu-list-project">
                @if($task->project)
                    <i class="bi bi-folder" style="color:#8b8d98;font-size:11px;"></i>
                    {{ $task->project->name }}
                @else
                    <span style="color:#c4c9d4;">—</span>
                @endif
            </div>
            <div><span class="cu-priority {{ $task->priority }}">{{ ucfirst($task->priority) }}</span></div>
            <div style="font-size:12px;color:#3d4149;">
                @if($task->user)
                    <div style="display:flex;align-items:center;gap:6px;">
                        <div class="cu-assignee" style="width:24px;height:24px;font-size:10px;">{{ strtoupper(substr($task->user->name,0,1)) }}</div>
                        <span>{{ $task->user->name }}</span>
                    </div>
                @else
                    <span style="color:#c4c9d4;">Unassigned</span>
                @endif
            </div>
            <div class="cu-due {{ $task->due_date && \Carbon\Carbon::parse($task->due_date)->startOfDay()->lt(now()->startOfDay()) && $task->status !== 'completed' ? 'overdue' : '' }}" style="font-size:12px;">
                @if($task->due_date)
                    <i class="bi bi-calendar-event" style="font-size:11px;"></i>
                    {{ \Carbon\Carbon::parse($task->due_date)->format('M d, Y') }}
                @else
                    <span style="color:#c4c9d4;">—</span>
                @endif
            </div>
            <div class="cu-list-actions">
                <a href="{{ route('tasks.show', $task->id) }}" class="cu-task-btn" title="View"><i class="bi bi-eye"></i></a>
                <a href="{{ route('tasks.edit', $task->id) }}" class="cu-task-btn" title="Edit"><i class="bi bi-pencil"></i></a>
            </div>
        </div>
        @endforeach
    </div>

    {{-- TREE VIEW --}}
    <div class="cu-tree-view" id="cuTree">
        @forelse($taskRoots as $task)
            @include('tasks._tree-node', ['task' => $task, 'depth' => 0])
        @empty
            <div class="cu-empty">
                <div class="cu-empty-icon"><i class="bi bi-diagram-3"></i></div>
                <h5>No tasks yet</h5>
                <p>Create your first task to get started.</p>
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

    @endif
</div>

{{-- Modal --}}
<div class="modal fade cu-modal" id="createTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ isset($project) ? route('projects.tasks.store', $project) : route('tasks.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="cu-field">
                                <label class="cu-label">Title <span style="color:#e5484d;">*</span></label>
                                <input type="text" name="title" class="cu-input" placeholder="Task title…" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cu-field">
                                <label class="cu-label">Priority <span style="color:#e5484d;">*</span></label>
                                <select name="priority" class="cu-input cu-select" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="cu-field">
                        <label class="cu-label">Description</label>
                        <div id="task-quill-editor"></div>
                        <textarea name="description" id="task_description" style="display:none;"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="cu-field">
                                <label class="cu-label">Project <span style="color:#e5484d;">*</span></label>
                                <select name="project_id" class="cu-input cu-select" required>
                                    <option value="">Select…</option>
                                    @foreach($projects as $proj)
                                        <option value="{{ $proj->id }}" {{ isset($project) && $project->id == $proj->id ? 'selected' : '' }}>{{ $proj->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cu-field">
                                <label class="cu-label">Due Date</label>
                                <input type="date" name="due_date" class="cu-input">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cu-field">
                                <label class="cu-label">Assign To <span style="color:#e5484d;">*</span></label>
                                <select name="user_id" class="cu-input cu-select" required>
                                    <option value="{{ auth()->id() }}" selected>Me</option>
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
                        <summary>More options</summary>
                        <div class="row g-3 mt-0">
                            <div class="col-md-4">
                                <div class="cu-field">
                                    <label class="cu-label">Est. Time</label>
                                    <div style="display:flex; gap:6px;">
                                        <input type="number" name="est_hours" class="cu-input" min="0" max="999" step="1" placeholder="Hrs" title="Hours">
                                        <input type="number" name="est_minutes" class="cu-input" min="0" max="59" step="1" placeholder="Min" title="Minutes">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="cu-field">
                                    <label class="cu-label">Parent Task</label>
                                    <select name="parent_id" class="cu-input cu-select">
                                        <option value="">None (top-level)</option>
                                        @foreach(collect($tasks)->flatten()->sortBy('title') as $pt)
                                            <option value="{{ $pt->id }}">{{ $pt->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="cu-field">
                                    <label class="cu-label">Weight</label>
                                    <input type="number" name="weight" class="cu-input" min="0" step="0.25" value="1">
                                    <div class="form-check mt-1">
                                        <input type="hidden" name="auto_weight" value="0">
                                        <input type="checkbox" name="auto_weight" value="1" class="form-check-input" checked id="autoWeight">
                                        <label class="form-check-label" for="autoWeight" style="font-size:12px;">Auto weight</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </details>
                    <input type="hidden" name="status" id="task_status" value="to_do">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="cu-btn-new" style="border-radius:6px;">
                        <i class="bi bi-check-lg"></i> Create Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="cuToast"></div>
@endsection

@push('scripts')
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const kanban = document.getElementById('cuKanban');
    const list   = document.getElementById('cuList');
    const tree   = document.getElementById('cuTree');
    const csrf   = '{{ csrf_token() }}';

    /* Toast */
    let toastTimer;
    function toast(msg) {
        const el = document.getElementById('cuToast');
        el.textContent = msg;
        el.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.remove('show'), 2400);
    }

    /* View switcher */
    const chapters = document.getElementById('cuChapters');
    const unfinishedWrap = document.getElementById('cuUnfinishedWrap');
    const chapterExpandWrap = document.getElementById('cuChapterExpandWrap');
    const unfinishedBox = document.getElementById('cuUnfinished');

    function showView(v) {
        if (kanban)   kanban.style.display   = v === 'kanban'   ? 'grid'  : 'none';
        if (list)     list.style.display     = v === 'list'     ? 'block' : 'none';
        if (tree)     tree.style.display     = v === 'tree'     ? 'block' : 'none';
        if (chapters) chapters.style.display = v === 'chapters' ? 'flex'  : 'none';
        const isCh = v === 'chapters';
        if (unfinishedWrap)    unfinishedWrap.style.display    = isCh ? '' : 'none';
        if (chapterExpandWrap) chapterExpandWrap.style.display = isCh ? 'inline-flex' : 'none';
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
        savePrefs();
    }
    document.querySelectorAll('[data-chapter-toggle]').forEach(head => {
        head.addEventListener('click', e => {
            if (e.target.closest('a,button.dropdown-toggle,.dropdown-menu')) return;
            const ch = head.closest('.cu-chapter');
            setChapter(ch, !ch.classList.contains('collapsed'));
        });
    });
    document.getElementById('cuExpandAll')?.addEventListener('click', () => {
        document.querySelectorAll('.cu-chapter').forEach(ch => ch.classList.remove('collapsed'));
        savePrefs();
    });
    document.getElementById('cuCollapseAll')?.addEventListener('click', () => {
        document.querySelectorAll('.cu-chapter').forEach(ch => ch.classList.add('collapsed'));
        savePrefs();
    });
    /* Column collapse / expand */
    document.querySelectorAll('[data-col-toggle]').forEach(head => {
        head.addEventListener('click', e => {
            if (e.target.closest('.cu-col-add')) return;
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
                    if (ch) ch.classList.toggle('collapsed', !!isCollapsed);
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
    document.addEventListener('keydown', e => {
        if (e.metaKey || e.ctrlKey || e.altKey) return;
        const t = e.target;
        if (t.matches?.('input, textarea, select') || t.isContentEditable) return;
        if (e.key === '/') { e.preventDefault(); searchInput?.focus(); searchInput?.select(); }
        if (e.key.toLowerCase() === 'n') {
            e.preventDefault();
            const m = bootstrap.Modal.getOrCreateInstance(document.getElementById('createTaskModal'));
            m.show();
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

    /* Drag & drop — event delegation so new cards work too */
    if (kanban) {
        kanban.addEventListener('dragstart', e => {
            const card = e.target.closest('.cu-task-card');
            if (!card) return;
            card.classList.add('dragging');
            e.dataTransfer.setData('text/plain', card.dataset.id);
            e.dataTransfer.effectAllowed = 'move';
        });
        kanban.addEventListener('dragend', () => {
            document.querySelectorAll('.cu-task-card.dragging').forEach(c => c.classList.remove('dragging'));
            document.querySelectorAll('.cu-col-body').forEach(c => c.classList.remove('drop-target'));
        });
        document.querySelectorAll('.cu-col-body').forEach(col => {
            col.addEventListener('dragover',  e => {
                if (e.target.closest('.cu-quickadd') || e.target.closest('.cu-empty')) return;
                e.preventDefault(); col.classList.add('drop-target');
            });
            col.addEventListener('dragleave', () => col.classList.remove('drop-target'));
            col.addEventListener('drop', e => {
                e.preventDefault();
                col.classList.remove('drop-target');
                const taskId = e.dataTransfer.getData('text/plain');
                const card   = document.querySelector(`.cu-task-card[data-id="${taskId}"]`);
                if (!card) return;
                const prev = card.closest('.cu-col-body');
                const from = card.dataset.status;
                const status = col.dataset.status;
                updateStatus(taskId, status, () => {
                    syncTaskDoneUI(taskId, status);
                    if (from !== status) toast('Task moved ✓');
                    updateCounts();
                    applyFilters();
                });
            });
        });
    }

    /* Quick check toggle — delegation (works for board cards AND chapter rows) */
    document.addEventListener('click', e => {
        const check = e.target.closest('.cu-check');
        if (check) {
            e.preventDefault();
            const holder = check.closest('.cu-task-card, .cu-ch-row');
            if (!holder) return;
            const id = holder.dataset.id;
            const isDone = holder.classList.contains('is-done');
            const newStatus = isDone ? 'to_do' : 'completed';
            updateStatus(id, newStatus, () => {
                syncTaskDoneUI(id, newStatus);
                updateCounts();
                applyFilters();
                toast(newStatus === 'completed' ? 'Task completed ✓' : 'Task moved to To Do');
            });
            return;
        }

        const del = e.target.closest('.cu-del-task');
        if (del) {
            e.preventDefault();
            const id = del.dataset.id;
            if (!confirm('Delete this task? This cannot be undone.')) return;
            fetch(`/tasks/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            }).then(r => {
                if (!r.ok) throw new Error();
                document.querySelector(`.cu-task-card[data-id="${id}"]`)?.remove();
                const row = document.querySelector(`.cu-ch-row[data-id="${id}"]`);
                const ch = row?.closest('.cu-chapter');
                row?.remove();
                if (ch) refreshChapter(ch);
                updateCounts();
                applyFilters();
                toast('Task deleted');
            }).catch(() => {
                /* Fallback: regular form submit (handles subtasks / full page delete) */
                const f = document.createElement('form');
                f.method = 'POST';
                f.action = `/tasks/${id}`;
                f.innerHTML = `<input type="hidden" name="_token" value="${csrf}"><input type="hidden" name="_method" value="DELETE">`;
                document.body.appendChild(f);
                f.submit();
            });
        }
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

    function syncTaskDoneUI(id, status) {
        const card = document.querySelector(`.cu-task-card[data-id="${id}"]`);
        if (card) {
            const col = document.getElementById(`col-${status}`);
            if (col) {
                col.insertBefore(card, col.querySelector('.cu-quickadd'));
                const colEl = col.closest('.cu-col');
                if (status === 'completed' && colEl.classList.contains('collapsed')) {
                    colEl.classList.remove('collapsed');
                    colEl.dataset.collapsed = '0';
                }
            }
            applyDoneState(card, status);
        }
        const row = document.querySelector(`.cu-ch-row[data-id="${id}"]`);
        if (row) {
            applyDoneState(row, status);
            const ch = row.closest('.cu-chapter');
            if (ch) refreshChapter(ch);
        }
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
        /* On global page fall back to the selected project filter (or first project) */
        let projectId = `{{ $project->id ?? '' }}`;
        if (!projectId && projectSelect) {
            projectId = projectSelect.value || (projectSelect.options[1]?.value || '');
        }
        if (!projectId) { toast('Select a project first'); return; }

        /* Full POST + redirect back: server renders the complete card markup */
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = `{{ route('tasks.store') }}`;
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
});
</script>
@endpush
