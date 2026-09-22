@extends('layouts.app')

@section('title', $project->name . ' — Project Details')

@push('styles')
<style>
    /* ─── Project Show – minimal ─────────────────────────────── */
    .main-content { padding:20px 24px 48px; background:#fafafa; min-height:100vh; }
    .pj-wrap { max-width:860px; margin:0 auto; }

    .pj-topbar { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
    .pj-back {
        display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:500;
        color:#8b8d98; text-decoration:none;
    }
    .pj-back:hover { color:#7c3aed; }
    .pj-crumbs { font-size:12px; color:#aeb2ba; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
    .pj-crumbs a { color:#7c3aed; text-decoration:none; }
    .pj-crumbs a:hover { text-decoration:underline; }
    .pj-top-actions { margin-left:auto; display:flex; gap:6px; }
    .pj-btn {
        display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:6px;
        border:1px solid #e5e7eb; background:white; color:#6b6f78; font-size:12px; font-weight:600;
        text-decoration:none; cursor:pointer; transition:all .12s; white-space:nowrap;
    }
    .pj-btn:hover { border-color:#7c3aed; color:#7c3aed; background:#f7f5ff; }
    .pj-btn.primary { background:#7c3aed; border-color:#7c3aed; color:white; }
    .pj-btn.primary:hover { background:#6d28d9; color:white; }
    .pj-btn.danger:hover { border-color:#e5484d; color:#e5484d; background:#fef2f2; }

    .pj-head { display:flex; align-items:flex-start; gap:12px; margin-bottom:4px; }
    .pj-avatar {
        width:42px; height:42px; border-radius:10px; flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
        font-weight:800; font-size:19px; color:white;
    }
    .pj-title {
        font-size:21px; font-weight:700; color:#1f2328; line-height:1.3;
        margin:0; word-break:break-word; flex:1; min-width:0;
    }
    .pj-chip {
        display:inline-flex; align-items:center; gap:5px; padding:2px 10px; border-radius:20px;
        font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.4px;
        flex-shrink:0; margin-top:6px;
    }
    .chip-not-started  { background:#eef0f2; color:#6b6f78; }
    .chip-in-progress  { background:#ece9fd; color:#7c3aed; }
    .chip-completed    { background:#e3f5ec; color:#29774b; }
    .chip-closed       { background:#eef0f2; color:#aeb2ba; }
    .chip-pending      { background:#fdf4de; color:#ad6800; }
    .chip-on-going     { background:#e2f0fd; color:#0b6bcb; }
    .chip-unfinished   { background:#fdebec; color:#e5484d; }
    .chip-finished     { background:#e3f5ec; color:#29774b; }

    .pj-progress { display:flex; align-items:center; gap:10px; margin:12px 0 4px; }
    .pj-progress-bar { flex:1; height:5px; background:#eef0f2; border-radius:4px; overflow:hidden; }
    .pj-progress-fill { height:100%; border-radius:4px; }
    .pj-progress-lbl { font-size:11.5px; font-weight:600; color:#6b6f78; white-space:nowrap; }

    .pj-facts { display:flex; flex-wrap:wrap; gap:6px 16px; margin:10px 0 0; }
    .pj-fact { font-size:12.5px; color:#6b6f78; display:inline-flex; align-items:center; gap:5px; }
    .pj-fact strong { color:#1f2328; font-weight:600; }
    .pj-fact.overdue strong { color:#e5484d; }

    .pj-divider { border:none; border-top:1px solid #eef0f2; margin:20px 0; }
    .pj-h {
        font-size:12px; font-weight:700; color:#8b8d98; text-transform:uppercase;
        letter-spacing:.5px; margin:0 0 8px; display:flex; align-items:center; gap:8px;
    }
    .pj-h .count { font-weight:400; text-transform:none; letter-spacing:0; }
    .pj-h .spacer { flex:1; }
    .pj-mini-btn {
        border:1px solid #e5e7eb; background:white; border-radius:6px; padding:3px 10px;
        font-size:11px; font-weight:600; color:#6b6f78; cursor:pointer; text-decoration:none;
        display:inline-flex; align-items:center; gap:4px;
    }
    .pj-mini-btn:hover { border-color:#7c3aed; color:#7c3aed; }

    .pj-row {
        display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:6px;
        text-decoration:none; color:inherit;
    }
    a.pj-row:hover { background:#f7f8fa; }
    .pj-row-av {
        width:30px; height:30px; border-radius:8px; flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
        font-weight:700; font-size:12px; color:white;
    }
    .pj-row-av.round { border-radius:50%; }
    .pj-row-main { flex:1; min-width:0; }
    .pj-row-title { font-size:13px; font-weight:600; color:#1f2328; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    a.pj-row:hover .pj-row-title { color:#7c3aed; }
    .pj-row-sub { font-size:11px; color:#8b8d98; margin-top:1px; }
    .pj-row-bar { width:80px; height:4px; background:#eef0f2; border-radius:4px; overflow:hidden; flex-shrink:0; }
    .pj-row-bar > span { display:block; height:100%; background:#30a46c; border-radius:4px; }
    .pj-row-val { font-size:12px; font-weight:600; color:#1f2328; white-space:nowrap; }

    .pj-desc { font-size:14px; line-height:1.8; color:#3d4149; }
    .pj-desc p { margin:0 0 10px; }
    .pj-muted { color:#8b8d98; font-size:13px; }
    .pj-muted a { color:#7c3aed; text-decoration:none; }
    .pj-muted a:hover { text-decoration:underline; }

    .pj-modal .modal-content { border:1px solid #e5e7eb; border-radius:10px; }
    .pj-modal .modal-header { border-bottom:1px solid #eef0f2; padding:12px 18px; }
    .pj-modal .modal-title { font-size:14px; font-weight:700; }
    .pj-modal .modal-body { padding:16px 18px; }
    .pj-modal .modal-footer { border-top:1px solid #eef0f2; padding:10px 18px; }

    @media(max-width:640px) {
        .main-content { padding:14px 14px 40px; }
        .pj-title { font-size:18px; }
        .pj-top-actions .pj-btn span { display:none; }
    }
</style>
@endpush

@section('content')
<div class="main-content">
<div class="pj-wrap">

    <div class="pj-topbar">
        <a href="{{ route('projects.index') }}" class="pj-back">
            <i class="bi bi-arrow-left"></i> Projects
        </a>
        @php $crumbs = $project->breadcrumb(); @endphp
        @if(count($crumbs) > 0)
            <nav class="pj-crumbs">
                @foreach($crumbs as $crumb)
                    <a href="{{ route('projects.show', $crumb) }}">{{ $crumb->name }}</a>
                    <span>›</span>
                @endforeach
            </nav>
        @endif
        <div class="pj-top-actions">
            <a href="{{ route('projects.tasks.index', $project) }}" class="pj-btn primary">
                <i class="bi bi-list-task"></i> <span>Tasks</span>
            </a>
            <a href="{{ route('projects.edit', $project) }}" class="pj-btn">
                <i class="bi bi-pencil"></i> <span>Edit</span>
            </a>
            <button type="button" class="pj-btn danger" onclick="confirmDelete()">
                <i class="bi bi-trash"></i>
            </button>
            <form id="deleteForm" action="{{ route('projects.destroy', $project) }}" method="POST" class="d-none">
                @csrf @method('DELETE')
            </form>
        </div>
    </div>

    @php
        $colors = ['#6366f1','#8b5cf6','#06b6d4','#10a37f','#f59e0b','#ef4444','#ec4899'];
        $projectColor = $colors[strlen($project->name) % count($colors)];

        /* Aggregate over the whole subtree (loaded in-memory, no extra queries) */
        $stack = $project->tasks->all();
        $aggTodo = $aggDone = 0;
        $aggLoad = 0;
        while (! empty($stack) && $aggLoad++ < 5000) {
            $t = array_shift($stack);
            if ($t->status === 'completed') { $aggDone++; } else { $aggTodo++; }
            foreach ($t->children ?? collect() as $kid) { $stack[] = $kid; }
        }
        foreach ($project->children as $cp) {
            $cpStack = $cp->tasks->all();
            $cpLoad = 0;
            while (! empty($cpStack) && $cpLoad++ < 5000) {
                $ct = array_shift($cpStack);
                if ($ct->status === 'completed') { $aggDone++; } else { $aggTodo++; }
                foreach ($ct->children ?? collect() as $cct) { $cpStack[] = $cct; }
            }
        }
        $aggTotal = $aggDone + $aggTodo;
        $progress = $aggTotal > 0 ? round($aggDone / $aggTotal * 100) : round($project->progressPercent());

        $rawStatus = $project->status;
        $chipMap = [
            'not_started' => ['label'=>'Not Started', 'class'=>'chip-not-started'],
            'in_progress' => ['label'=>'In Progress',  'class'=>'chip-in-progress'],
            'completed'   => ['label'=>'Completed',    'class'=>'chip-completed'],
            'closed'      => ['label'=>'Closed',       'class'=>'chip-closed'],
            'pending'     => ['label'=>'Pending',      'class'=>'chip-pending'],
            'on_going'    => ['label'=>'On Going',     'class'=>'chip-on-going'],
            'unfinished'  => ['label'=>'Unfinished',   'class'=>'chip-unfinished'],
            'finished'    => ['label'=>'Finished',     'class'=>'chip-finished'],
        ];
        $chip = $chipMap[$rawStatus] ?? ['label' => ucwords(str_replace('_',' ',$rawStatus)), 'class' => 'chip-not-started'];
    @endphp

    <div class="pj-head">
        <div class="pj-avatar" style="background:{{ $projectColor }};">
            {{ strtoupper(substr($project->name,0,1)) }}
        </div>
        <h1 class="pj-title">{{ $project->name }}</h1>
        <span class="pj-chip {{ $chip['class'] }}">{{ $chip['label'] }}</span>
    </div>

    <div class="pj-progress">
        <div class="pj-progress-bar"><div class="pj-progress-fill" style="background:{{ $projectColor }};width:{{ $progress }}%;"></div></div>
        @if($aggTotal > 0)
            <span class="pj-progress-lbl">{{ $aggDone }}/{{ $aggTotal }} · {{ $progress }}%</span>
        @else
            <span class="pj-progress-lbl">No tasks yet</span>
        @endif
    </div>

    <div class="pj-facts">
        @if($aggTotal > 0)
            <span class="pj-fact"><i class="bi bi-list-task"></i><strong>{{ $aggDone }}</strong> done · <strong>{{ $aggTodo }}</strong> open</span>
        @endif
        <span class="pj-fact"><i class="bi bi-people"></i><strong>{{ $teamMembers->count() }}</strong> members</span>
        @if($project->end_date)
            <span class="pj-fact {{ $project->end_date->isPast() ? 'overdue' : '' }}">
                <i class="bi bi-calendar-event"></i>Due <strong>{{ $project->end_date->format('M d, Y') }}</strong>
            </span>
        @endif
        @if($project->budget)
            <span class="pj-fact"><i class="bi bi-currency-dollar"></i><strong>${{ number_format($project->budget, 0) }}</strong></span>
        @endif
        <span class="pj-fact"><i class="bi bi-stopwatch"></i>{{ \App\Models\TimeEntry::formatDuration($project->totalTimeSeconds()) }}</span>
        <span class="pj-fact"><i class="bi bi-clock-history"></i>Updated {{ $project->updated_at->diffForHumans() }}</span>
    </div>

    @if($project->children->count() > 0)
        <hr class="pj-divider">
        <h2 class="pj-h">
            Sub-projects <span class="count">· {{ $project->children->count() }}</span>
            <span class="spacer"></span>
            <a href="{{ route('projects.create', ['parent' => $project->id]) }}" class="pj-mini-btn"><i class="bi bi-plus-lg"></i> Add</a>
        </h2>
        <div>
            @foreach($project->children as $child)
                @php $childProgress = $child->progressPercent(); @endphp
                <a href="{{ route('projects.show', $child) }}" class="pj-row">
                    <div class="pj-row-av" style="background:#7c3aed;">{{ strtoupper(substr($child->name,0,1)) }}</div>
                    <div class="pj-row-main">
                        <div class="pj-row-title">{{ $child->name }}</div>
                        <div class="pj-row-sub">{{ $child->tasks->count() }} tasks</div>
                    </div>
                    <div class="pj-row-bar"><span style="width:{{ round($childProgress) }}%;"></span></div>
                    <span class="pj-row-val">{{ round($childProgress) }}%</span>
                </a>
            @endforeach
        </div>
    @endif

    @php $recentTime = $project->timeEntries()->with(['task:id,title'])->latest('started_at')->take(5)->get(); @endphp
    @if($recentTime->count() > 0)
        <hr class="pj-divider">
        <h2 class="pj-h">
            Recent time
            <span class="spacer"></span>
            <a href="{{ route('time.reports', ['project_id' => $project->id]) }}" class="pj-mini-btn"><i class="bi bi-bar-chart-line"></i> Reports</a>
        </h2>
        <div>
            @foreach($recentTime as $entry)
                <div class="pj-row">
                    <div class="pj-row-av round" style="background:#eef0f2;color:#8b8d98;"><i class="bi bi-clock" style="font-size:12px;"></i></div>
                    <div class="pj-row-main">
                        <div class="pj-row-title">{{ $entry->task?->title ?? $entry->description ?? 'Work session' }}</div>
                        <div class="pj-row-sub">{{ $entry->started_at->format('M d, g:i A') }}</div>
                    </div>
                    <span class="pj-row-val">{{ \App\Models\TimeEntry::formatDuration($entry->status === 'stopped' ? (int) $entry->duration_seconds : $entry->elapsedSeconds()) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <hr class="pj-divider">
    <h2 class="pj-h">Description</h2>
    @if($project->description)
        <div class="pj-desc">{!! $project->description !!}</div>
    @else
        <p class="pj-muted" style="font-style:italic;">No description. <a href="{{ route('projects.edit', $project) }}">Add one →</a></p>
    @endif

    <hr class="pj-divider">
    <h2 class="pj-h">
        Team <span class="count">· {{ $teamMembers->count() }}</span>
        <span class="spacer"></span>
        <button class="pj-mini-btn" data-bs-toggle="modal" data-bs-target="#addMemberModal"><i class="bi bi-plus-lg"></i> Add</button>
    </h2>
    <div>
        @forelse($teamMembers as $member)
            <div class="pj-row">
                <div class="pj-row-av round" style="background:{{ $colors[strlen($member->name) % count($colors)] }};">
                    {{ strtoupper(substr($member->name,0,1)) }}
                </div>
                <div class="pj-row-main">
                    <div class="pj-row-title">{{ $member->name }}</div>
                    <div class="pj-row-sub">{{ $member->email }}</div>
                </div>
            </div>
        @empty
            <p class="pj-muted" style="font-style:italic;">No team members yet.</p>
        @endforelse
    </div>

</div>
</div>

{{-- Add Member Modal --}}
<div class="modal fade pj-modal" id="addMemberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title mb-0">Add team member</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('projects.addMember') }}" method="POST">
                @csrf
                <input type="hidden" name="project_id" value="{{ $project->id }}">
                <div class="modal-body">
                    <label for="user_id" class="form-label" style="font-size:12px;font-weight:600;color:#3d4149;">Select user</label>
                    <select class="form-select" name="user_id" id="user_id" required style="font-size:13px;">
                        <option value="">Choose a user…</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Add member</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function confirmDelete() {
    if (confirm('Delete "{{ addslashes($project->name) }}"? All tasks, files and data will be permanently removed. This cannot be undone.')) {
        document.getElementById('deleteForm').submit();
    }
}
</script>
@endpush
