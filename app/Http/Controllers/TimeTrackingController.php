<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TimeTrackingController extends Controller
{
    public function active()
    {
        $entry = $this->currentEntry();
        if (! $entry) {
            return response()->json(['active' => null]);
        }

        return response()->json(['active' => $this->serialize($entry)]);
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', Auth::id())],
            'task_id' => 'nullable|integer|exists:tasks,id',
            'description' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:50',
        ]);

        $task = null;
        if (! empty($data['task_id'])) {
            $task = Task::where('id', $data['task_id'])->where('user_id', Auth::id())->first();
            abort_if(! $task, 422, 'Invalid task.');
            if (empty($data['project_id'])) {
                $data['project_id'] = $task->project_id;
            }
            abort_if((int) $task->project_id !== (int) $data['project_id'], 422, 'Task does not belong to the project.');
        }

        $current = $this->currentEntry();
        if ($current) {
            $current->stop();
        }

        $entry = Auth::user()->timeEntries()->create([
            'project_id' => $data['project_id'] ?? null,
            'task_id' => $task?->id,
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'status' => TimeEntry::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        return response()->json(['ok' => true, 'active' => $this->serialize($entry->fresh())], 201);
    }

    public function tasksForProject(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('user_id', Auth::id())],
        ]);

        $tasks = Task::where('project_id', $data['project_id'])
            ->where('user_id', Auth::id())
            ->where('status', '!=', 'completed')
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'title', 'status', 'parent_id']);

        return response()->json(['tasks' => $tasks]);
    }

    public function pause(TimeEntry $entry)
    {
        $this->authorizeEntry($entry);
        $entry->pause();

        return response()->json(['ok' => true, 'active' => $this->serialize($entry->fresh())]);
    }

    public function resume(TimeEntry $entry)
    {
        $this->authorizeEntry($entry);
        $entry->resume();

        return response()->json(['ok' => true, 'active' => $this->serialize($entry->fresh())]);
    }

    public function stop(TimeEntry $entry)
    {
        $this->authorizeEntry($entry);
        $entry->stop();

        return response()->json(['ok' => true, 'active' => null, 'duration' => $entry->duration_seconds]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', Auth::id())],
            'task_id' => 'nullable|integer|exists:tasks,id',
            'started_at' => 'required|date',
            'ended_at' => 'required|date|after_or_equal:started_at',
            'description' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:50',
        ]);

        if (! empty($data['task_id'])) {
            $task = Task::where('id', $data['task_id'])->where('user_id', Auth::id())->first();
            abort_if(! $task, 422, 'Invalid task.');
            if (empty($data['project_id'])) {
                $data['project_id'] = $task->project_id;
            }
            abort_if((int) $task->project_id !== (int) $data['project_id'], 422, 'Task does not belong to the project.');
        }

        $start = Carbon::parse($data['started_at']);
        $end = Carbon::parse($data['ended_at']);

        $entry = Auth::user()->timeEntries()->create([
            'project_id' => $data['project_id'] ?? null,
            'task_id' => $data['task_id'] ?? null,
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'status' => TimeEntry::STATUS_STOPPED,
            'started_at' => $start,
            'ended_at' => $end,
            'duration_seconds' => (int) max(0, $end->diffInSeconds($start, true)),
        ]);

        return redirect()->route('time.reports')->with('success', 'Time entry added.');
    }

    public function destroy(TimeEntry $entry)
    {
        $this->authorizeEntry($entry);
        $entry->delete();

        return redirect()->route('time.reports')->with('success', 'Time entry deleted.');
    }

    public function reports(Request $request)
    {
        $user = Auth::user();
        $range = in_array($request->input('range'), ['today', 'week', 'month'], true)
            ? $request->input('range')
            : 'week';

        $start = match ($range) {
            'today' => now()->startOfDay(),
            'month' => now()->startOfMonth(),
            default => now()->startOfWeek(Carbon::SATURDAY)->startOfDay(),
        };

        $projectFilter = $request->input('project_id');

        $entries = $user->timeEntries()
            ->with(['project:id,name', 'task:id,title'])
            ->where('status', TimeEntry::STATUS_STOPPED)
            ->where('started_at', '>=', $start)
            ->when($projectFilter, fn ($q) => $q->where('project_id', $projectFilter))
            ->orderByDesc('started_at')
            ->get();

        $total = (int) $entries->sum('duration_seconds');

        $perProject = $entries->groupBy(fn ($e) => $e->project?->name ?? 'No project')
            ->map(fn ($g) => (int) $g->sum('duration_seconds'))
            ->sortDesc();

        $days = [];
        $cursor = $start->copy()->startOfDay();
        $endDay = now()->startOfDay();
        $guard = 0;
        while ($cursor->lte($endDay) && $guard++ < 62) {
            $days[] = [
                'label' => $cursor->format('D'),
                'date' => $cursor->format('M j'),
                'seconds' => 0,
                'key' => $cursor->toDateString(),
            ];
            $cursor->addDay();
        }
        $byDay = $entries->groupBy(fn ($e) => $e->started_at->toDateString());
        foreach ($days as $i => $day) {
            $days[$i]['seconds'] = isset($byDay[$day['key']]) ? (int) $byDay[$day['key']]->sum('duration_seconds') : 0;
        }
        $maxDay = max(1, max(array_column($days, 'seconds')));

        $projects = $user->projects()->orderBy('name')->get(['id', 'name']);

        return view('time.reports', compact('entries', 'total', 'perProject', 'days', 'maxDay', 'range', 'projects', 'projectFilter'));
    }

    private function currentEntry(): ?TimeEntry
    {
        return Auth::user()->timeEntries()
            ->whereIn('status', [TimeEntry::STATUS_RUNNING, TimeEntry::STATUS_PAUSED])
            ->with(['project:id,name', 'task:id,title'])
            ->latest()
            ->first();
    }

    private function authorizeEntry(TimeEntry $entry): void
    {
        abort_if($entry->user_id !== Auth::id(), 403);
    }

    private function serialize(TimeEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'status' => $entry->status,
            'project' => $entry->project ? ['id' => $entry->project->id, 'name' => $entry->project->name] : null,
            'task' => $entry->task ? ['id' => $entry->task->id, 'title' => $entry->task->title] : null,
            'description' => $entry->description,
            'category' => $entry->category,
            'started_at' => $entry->started_at->toIso8601String(),
            'elapsed' => $entry->elapsedSeconds(),
        ];
    }
}
