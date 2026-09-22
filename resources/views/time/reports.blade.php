@extends('layouts.app')

@section('title', 'Time Reports')

@push('styles')
<style>
    .main-content { padding: 14px 16px; background: #f7f8fa; min-height: 100vh; }
    .tm-header {
        background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
        border-radius: 10px; padding: 12px 18px; color: white;
        margin-bottom: 14px; position: relative; overflow: hidden;
        border: 1px solid #6d28d9; box-shadow: 0 2px 8px rgba(124,58,237,.3);
    }
    .tm-header::before {
        content: ''; position: absolute; top: 0; right: 0;
        width: 80px; height: 80px; background: rgba(255,255,255,.08);
        border-radius: 50%; transform: translate(20px,-20px);
    }
    .tm-header-title { font-weight: 700; font-size: 17px; margin: 0; position: relative; z-index: 1; }
    .tm-header-sub { font-size: 12px; opacity: .85; margin: 2px 0 0; position: relative; z-index: 1; }
    .tm-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; margin-bottom: 14px; }
    @media(max-width:700px) { .tm-stats { grid-template-columns: 1fr; } }
    .tm-stat { background: white; border: 1px solid #e3e4e8; border-radius: 10px; padding: 14px; }
    .tm-stat-val { font-size: 22px; font-weight: 800; color: #1a1d23; line-height: 1; }
    .tm-stat-label { font-size: 11px; color: #8a8f98; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-top: 6px; }
    .tm-card { background: white; border: 1px solid #e3e4e8; border-radius: 10px; overflow: hidden; margin-bottom: 14px; }
    .tm-card-head {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        padding: 11px 16px; background: #fafbfc; border-bottom: 1px solid #e3e4e8;
    }
    .tm-card-title { font-size: 13px; font-weight: 700; color: #1a1d23; }
    .tm-card-tools { margin-left: auto; display: flex; align-items: center; gap: 6px; }
    .tm-toggle { display: flex; background: #f0f1f3; border-radius: 7px; padding: 3px; gap: 2px; }
    .tm-toggle a {
        padding: 4px 12px; border-radius: 5px; font-size: 11px; font-weight: 700;
        color: #8a8f98; text-decoration: none;
    }
    .tm-toggle a.active { background: white; color: #1a1d23; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
    .tm-select { border: 1px solid #e3e4e8; border-radius: 7px; padding: 5px 8px; font-size: 12px; color: #3d4149; background: white; }
    .tm-card-body { padding: 16px; }
    .tm-chart { display: flex; align-items: flex-end; gap: 4px; height: 100px; }
    .tm-bar-wrap { flex: 1; height: 100%; display: flex; align-items: flex-end; }
    .tm-bar { width: 100%; min-height: 2px; border-radius: 3px 3px 0 0; background: #ddd6fe; }
    .tm-bar-wrap:hover .tm-bar { background: #7c3aed; }
    .tm-axis { display: flex; margin-top: 6px; }
    .tm-axis span { flex: 1; text-align: center; font-size: 9px; color: #adb0b8; font-weight: 600; white-space: nowrap; }
    .tm-proj { display: flex; align-items: center; gap: 8px; padding: 7px 0; border-bottom: 1px solid #f0f1f3; font-size: 12px; }
    .tm-proj:last-child { border-bottom: none; }
    .tm-proj-name { font-weight: 600; color: #1a1d23; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 45%; }
    .tm-proj-bar { flex: 1; height: 5px; background: #f0f1f3; border-radius: 4px; overflow: hidden; }
    .tm-proj-fill { height: 100%; background: #7c3aed; border-radius: 4px; }
    .tm-proj-time { font-weight: 700; color: #3d4149; white-space: nowrap; }
    .tm-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .tm-table th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #8a8f98; padding: 6px 8px; border-bottom: 1px solid #e3e4e8; }
    .tm-table td { padding: 8px; border-bottom: 1px solid #f0f1f3; color: #3d4149; vertical-align: middle; }
    .tm-table tr:last-child td { border-bottom: none; }
    .tm-del { border: 1px solid #e3e4e8; background: white; color: #8a8f98; border-radius: 6px; width: 26px; height: 26px; cursor: pointer; }
    .tm-del:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }
    .tm-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    @media(max-width:700px) { .tm-form-row { grid-template-columns: 1fr; } }
    .tm-field { margin-bottom: 10px; }
    .tm-field label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #8a8f98; margin-bottom: 3px; }
    .tm-input { width: 100%; border: 1px solid #e3e4e8; border-radius: 7px; padding: 7px 10px; font-size: 13px; color: #1a1d23; background: white; outline: none; }
    .tm-input:focus { border-color: #7c3aed; }
    .tm-save {
        padding: 7px 18px; background: #7c3aed; border: 1px solid #7c3aed; color: white;
        border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer;
    }
    .tm-save:hover { background: #6d28d9; }
    .tm-empty { text-align: center; color: #adb0b8; font-size: 12.5px; padding: 18px; }
</style>
@endpush

@section('content')
<div class="main-content">

    <div class="tm-header">
        <div style="position:relative;z-index:1;">
            <h1 class="tm-header-title"><i class="bi bi-stopwatch me-2"></i>Time Reports</h1>
            <p class="tm-header-sub">Where your hours went · total {{ \App\Models\TimeEntry::formatDuration($total) }}</p>
        </div>
    </div>

    <div class="tm-stats">
        <div class="tm-stat">
            <div class="tm-stat-val">{{ \App\Models\TimeEntry::formatDuration($total) }}</div>
            <div class="tm-stat-label">Total · {{ ucfirst($range) }}</div>
        </div>
        <div class="tm-stat">
            <div class="tm-stat-val">{{ $entries->count() }}</div>
            <div class="tm-stat-label">Sessions</div>
        </div>
        <div class="tm-stat">
            <div class="tm-stat-val">{{ $perProject->count() }}</div>
            <div class="tm-stat-label">Projects touched</div>
        </div>
    </div>

    <div class="tm-card">
        <div class="tm-card-head">
            <span class="tm-card-title">Daily activity</span>
            <div class="tm-card-tools">
                <div class="tm-toggle">
                    <a href="{{ route('time.reports', ['range' => 'today', 'project_id' => $projectFilter]) }}" class="{{ $range === 'today' ? 'active' : '' }}">Today</a>
                    <a href="{{ route('time.reports', ['range' => 'week', 'project_id' => $projectFilter]) }}" class="{{ $range === 'week' ? 'active' : '' }}">Week</a>
                    <a href="{{ route('time.reports', ['range' => 'month', 'project_id' => $projectFilter]) }}" class="{{ $range === 'month' ? 'active' : '' }}">Month</a>
                </div>
                <form method="GET" action="{{ route('time.reports') }}" style="display:inline;">
                    <input type="hidden" name="range" value="{{ $range }}">
                    <select name="project_id" class="tm-select" onchange="this.form.submit()">
                        <option value="">All projects</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ (string) $projectFilter === (string) $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
        <div class="tm-card-body">
            <div class="tm-chart">
                @foreach($days as $day)
                    <div class="tm-bar-wrap" title="{{ $day['date'] }} — {{ \App\Models\TimeEntry::formatDuration($day['seconds']) }}">
                        <div class="tm-bar" style="height: {{ round($day['seconds'] / $maxDay * 100) }}%;"></div>
                    </div>
                @endforeach
            </div>
            <div class="tm-axis">
                @foreach($days as $day)
                    <span>{{ substr($day['label'], 0, 1) }}</span>
                @endforeach
            </div>
        </div>
    </div>

    <div class="tm-card">
        <div class="tm-card-head">
            <span class="tm-card-title">By project</span>
        </div>
        <div class="tm-card-body" style="padding-top:8px; padding-bottom:8px;">
            @forelse($perProject as $name => $seconds)
                <div class="tm-proj">
                    <span class="tm-proj-name">{{ $name }}</span>
                    <div class="tm-proj-bar">
                        <div class="tm-proj-fill" style="width: {{ $total > 0 ? round($seconds / $total * 100) : 0 }}%;"></div>
                    </div>
                    <span class="tm-proj-time">{{ \App\Models\TimeEntry::formatDuration($seconds) }}</span>
                </div>
            @empty
                <div class="tm-empty">No tracked time in this range yet.</div>
            @endforelse
        </div>
    </div>

    <div class="tm-card">
        <div class="tm-card-head">
            <span class="tm-card-title">Log time manually</span>
        </div>
        <div class="tm-card-body">
            <form method="POST" action="{{ route('time.entries.store') }}">
                @csrf
                <div class="tm-form-row">
                    <div class="tm-field">
                        <label>Project</label>
                        <select name="project_id" class="tm-input" id="tm-manual-project">
                            <option value="">No project</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="tm-field">
                        <label>Task</label>
                        <select name="task_id" class="tm-input" id="tm-manual-task" disabled>
                            <option value="">Select a project first</option>
                        </select>
                    </div>
                </div>
                <div class="tm-form-row">
                    <div class="tm-field">
                        <label>Started at</label>
                        <input type="datetime-local" name="started_at" class="tm-input" required value="{{ now()->subHour()->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="tm-field">
                        <label>Ended at</label>
                        <input type="datetime-local" name="ended_at" class="tm-input" required value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                </div>
                <div class="tm-field">
                    <label>Description</label>
                    <input type="text" name="description" class="tm-input" placeholder="What did you work on?" maxlength="500">
                </div>
                <button type="submit" class="tm-save"><i class="bi bi-check-lg me-1"></i>Save Entry</button>
            </form>
        </div>
    </div>

    <div class="tm-card">
        <div class="tm-card-head">
            <span class="tm-card-title">Sessions</span>
        </div>
        <div class="tm-card-body" style="padding:8px 16px;">
            @if($entries->count())
                <div style="overflow-x:auto;">
                    <table class="tm-table">
                        <thead>
                            <tr><th>Date</th><th>Project</th><th>Task</th><th>Notes</th><th>Duration</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach($entries as $entry)
                                <tr>
                                    <td style="white-space:nowrap;">{{ $entry->started_at->format('M j, g:i A') }}</td>
                                    <td>{{ $entry->project?->name ?? '—' }}</td>
                                    <td>{{ $entry->task?->title ?? '—' }}</td>
                                    <td style="max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $entry->description ?? '—' }}</td>
                                    <td style="font-weight:700; white-space:nowrap;">{{ \App\Models\TimeEntry::formatDuration((int) $entry->duration_seconds) }}</td>
                                    <td>
                                        <form action="{{ route('time.entries.destroy', $entry) }}" method="POST" onsubmit="return confirm('Delete this entry?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="tm-del" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="tm-empty">No sessions in this range.</div>
            @endif
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const projectSel = document.getElementById('tm-manual-project');
    const taskSel = document.getElementById('tm-manual-task');
    if (!projectSel || !taskSel) return;
    projectSel.addEventListener('change', () => {
        taskSel.innerHTML = '<option value="">Loading…</option>';
        taskSel.disabled = true;
        if (!projectSel.value) {
            taskSel.innerHTML = '<option value="">Select a project first</option>';
            return;
        }
        fetch('{{ route('time.tasks') }}?project_id=' + encodeURIComponent(projectSel.value), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(j => {
                taskSel.innerHTML = '<option value="">No specific task</option>' +
                    (j.tasks || []).map(t => '<option value="' + t.id + '">' + String(t.title).replace(/</g, '&lt;') + '</option>').join('');
                taskSel.disabled = false;
            })
            .catch(() => { taskSel.innerHTML = '<option value="">Could not load tasks</option>'; });
    });
});
</script>
@endpush
