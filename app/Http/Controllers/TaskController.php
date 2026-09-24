<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TaskController extends Controller
{
    public function index(?Project $project = null)
    {
        $user = Auth::user();

        if ($project) {
            // Show tasks for a specific project (closed projects still accessible directly)
            $tasks = Task::where('user_id', $user->id)
                ->where('project_id', $project->id)
                ->with(['project:id,name,slug', 'childrenRecursive', 'checklistItems', 'timeEntries'])
                ->withCount('children')
                ->get()
                ->groupBy('status');
        } else {
            // Show all tasks — exclude tasks from completed or closed projects
            $tasks = Task::where('user_id', $user->id)
                ->whereHas('project', function ($query) {
                    $query->whereNotIn('status', ['completed', 'closed']);
                })
                ->with(['project:id,name,slug', 'childrenRecursive', 'checklistItems', 'timeEntries'])
                ->withCount('children')
                ->get()
                ->groupBy('status');
        }

        $allTasks = collect($tasks)->flatten();
        $taskRoots = $allTasks->filter(fn ($t) => $t->parent_id === null)->values();

        // Only show projects whose tasks are actually loaded (exclude completed & closed)
        $projects = Project::where('user_id', $user->id)->whereNotIn('status', ['completed', 'closed'])->get(['id', 'name', 'slug', 'status']);
        $users = User::query()->get(['id', 'name', 'email']);

        return view('tasks.index', compact('tasks', 'taskRoots', 'projects', 'users', 'project'));
    }

    public function create()
    {
        $projects = Project::all();
        $users = User::all();

        return view('tasks.create', compact('projects', 'users'));
    }

    public function store(Request $request, ?Project $project = null)
    {
        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'required|in:low,medium,high',
            'status' => 'required|in:to_do,in_progress,on_hold,in_review,completed',
            'estimated_hours' => 'nullable|numeric|min:0',
            'est_hours' => 'nullable|integer|min:0|max:999',
            'est_minutes' => 'nullable|integer|min:0|max:59',
            'parent_id' => 'nullable|integer|exists:tasks,id',
            'weight' => 'nullable|numeric|min:0|max:99',
            'auto_weight' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $this->resolveParent($request->input('parent_id'), $request->input('project_id'), Auth::id());

        $data['estimated_hours'] = Task::combineEstimate($data['est_hours'] ?? null, $data['est_minutes'] ?? null, $data['estimated_hours'] ?? null);
        unset($data['est_hours'], $data['est_minutes']);

        Task::create($data);

        // Redirect based on context
        if ($project) {
            return redirect()->route('projects.tasks.index', $project)->with('success', 'Task created successfully.');
        } else {
            return redirect()->route('tasks.index')->with('success', 'Task created successfully.');
        }
    }

    public function show(Task $task)
    {
        $task->load(['user:id,name', 'project:id,name,slug', 'checklistItems', 'parent:id,title', 'childrenRecursive', 'timeEntries']);

        return view('tasks.show', compact('task'));
    }

    public function edit(Task $task)
    {
        $projects = Project::all();
        $users = User::all();
        $exclude = array_merge([$task->id], $this->descendantIds($task));
        $parentOptions = Task::where('project_id', $task->project_id)
            ->whereNotIn('id', $exclude)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'title', 'parent_id']);

        return view('tasks.edit', compact('task', 'projects', 'users', 'parentOptions'));
    }

    public function update(Request $request, Task $task)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'required|in:low,medium,high',
            'status' => 'required|in:to_do,in_progress,on_hold,in_review,completed',
            'estimated_hours' => 'nullable|numeric|min:0',
            'est_hours' => 'nullable|integer|min:0|max:999',
            'est_minutes' => 'nullable|integer|min:0|max:59',
            'parent_id' => 'nullable|integer|exists:tasks,id',
            'weight' => 'nullable|numeric|min:0|max:99',
            'auto_weight' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $this->resolveParent($request->input('parent_id'), $task->project_id, Auth::id(), $task->id);

        $data['estimated_hours'] = Task::combineEstimate($data['est_hours'] ?? null, $data['est_minutes'] ?? null, $data['estimated_hours'] ?? null);
        unset($data['est_hours'], $data['est_minutes']);
        $data['completed_at'] = ($data['status'] ?? null) === 'completed'
            ? ($task->completed_at ?? now())
            : null;

        $task->update($data);

        return redirect()->route('tasks.show', $task->id)->with('success', 'Task updated successfully.');
    }

    public function destroy(Request $request, Task $task)
    {
        $task->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Task deleted successfully.']);
        }

        return redirect()->route('tasks.index')->with('success', 'Task deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.parent_id' => 'nullable|integer',
            'items.*.sort_order' => 'nullable|integer|min:0',
        ]);

        $tasks = Task::where('user_id', Auth::id())
            ->whereIn('id', collect($data['items'])->pluck('id')->all())
            ->get()->keyBy('id');

        foreach ($data['items'] as $item) {
            $task = $tasks->get($item['id']);
            if (! $task) {
                continue;
            }

            $parentId = $item['parent_id'] ?? null;
            if ($parentId) {
                try {
                    $this->resolveParent($parentId, $task->project_id, Auth::id(), $task->id);
                } catch (HttpException $e) {
                    continue;
                }
                $task->parent_id = $parentId;
            } else {
                $task->parent_id = null;
            }
            $task->sort_order = $item['sort_order'] ?? 0;
            $task->save();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Ensure the proposed parent belongs to the same project + user and
     * does not create a cycle.
     */
    private function resolveParent($parentId, int $projectId, int $userId, ?int $selfId = null): void
    {
        if (! $parentId) {
            return;
        }
        if ($selfId && (int) $parentId === (int) $selfId) {
            abort(422, 'A task cannot be its own parent.');
        }

        $parent = Task::where('id', $parentId)
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->first();
        abort_if(! $parent, 422, 'Invalid parent task.');

        $current = $parent;
        $guard = 0;
        while ($current && $guard++ < 100) {
            if ($selfId && (int) $current->id === (int) $selfId) {
                abort(422, 'Cannot move a task under its own descendant.');
            }
            $current = $current->parent_id ? Task::find($current->parent_id) : null;
        }
    }

    private function descendantIds(Task $task): array
    {
        $ids = [];
        $stack = $task->children()->pluck('id')->all();
        $guard = 0;
        while (! empty($stack) && $guard++ < 1000) {
            $id = array_pop($stack);
            $ids[] = $id;
            foreach (Task::where('parent_id', $id)->pluck('id')->all() as $childId) {
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * Bulk status change for the current user's own tasks.
     */
    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'integer',
            'status' => 'required|in:to_do,in_progress,on_hold,in_review,completed',
        ]);

        $tasks = Task::where('user_id', Auth::id())
            ->whereIn('id', $data['ids'])
            ->get();

        foreach ($tasks as $task) {
            $task->status = $data['status'];
            $task->completed_at = $data['status'] === 'completed'
                ? ($task->completed_at ?? now())
                : null;
            $task->save();
        }

        return response()->json(['ok' => true, 'updated' => $tasks->count()]);
    }

    /**
     * Bulk delete for the current user's own tasks.
     */
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'integer',
        ]);

        $count = Task::where('user_id', Auth::id())
            ->whereIn('id', $data['ids'])
            ->delete();

        return response()->json(['ok' => true, 'deleted' => $count]);
    }

    public function updateStatus(Request $request, Task $task)
    {
        $status = $request->input('status');
        $task->status = $status;
        $task->completed_at = $status === 'completed' ? ($task->completed_at ?? now()) : null;
        $task->save();

        return response()->json(['message' => 'Task status updated successfully.']);
    }
}
