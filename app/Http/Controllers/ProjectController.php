<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Auth::user()->projects()->withCount(['tasks as to_do_tasks' => function ($query) {
            $query->where('status', 'to_do');
        }, 'tasks as in_progress_tasks' => function ($query) {
            $query->where('status', 'in_progress');
        }, 'tasks as completed_tasks' => function ($query) {
            $query->where('status', 'completed');
        }])->get();

        $roots = Auth::user()->projects()->whereNull('parent_id')
            ->with(['childrenRecursive', 'tasks'])
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        return view('projects.index', compact('projects', 'roots'));
    }

    public function create(Request $request)
    {
        $parent = null;
        if ($request->filled('parent')) {
            $parent = Auth::user()->projects()->find($request->input('parent'));
        }
        $parentOptions = Auth::user()->projects()->orderBy('name')->get(['id', 'name', 'parent_id']);

        return view('projects.create', compact('parent', 'parentOptions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'required|in:not_started,in_progress,completed,closed',
            'budget' => 'nullable|numeric',
            'parent_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', Auth::id())],
            'type' => 'nullable|in:project,subproject,lesson,chapter,section',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['type'] = $data['type'] ?? 'project';
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $this->assertNoCycle($data['parent_id'] ?? null);

        Auth::user()->projects()->create($data);

        return redirect()->route('projects.index')->with('success', 'Project created successfully.');
    }

    public function show(Project $project)
    {
        $project->load([
            'parent.parent.parent',
            'childrenRecursive.tasks.childrenRecursive',
            'childrenRecursive.tasks.timeEntries',
            'tasks.childrenRecursive',
            'tasks.timeEntries',
            'tasks.checklistItems',
        ]);
        $teamMembers = $project->users()->get(['users.id', 'users.name', 'users.email']);
        $users = User::query()->get(['id', 'name', 'email']);

        return view('projects.show', compact('project', 'teamMembers', 'users'));
    }

    public function edit(Project $project)
    {
        $exclude = array_merge([$project->id], $this->descendantIds($project));
        $parentOptions = Auth::user()->projects()
            ->whereNotIn('id', $exclude)
            ->orderBy('name')->get(['id', 'name', 'parent_id']);

        return view('projects.edit', compact('project', 'parentOptions'));
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'required|in:not_started,in_progress,completed,closed',
            'budget' => 'nullable|numeric',
            'parent_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', Auth::id())],
            'type' => 'nullable|in:project,subproject,lesson,chapter,section',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $this->assertNoCycle($data['parent_id'] ?? null, $project->id);

        $project->update($data);

        return redirect()->route('projects.index')->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.parent_id' => 'nullable|integer',
            'items.*.sort_order' => 'nullable|integer|min:0',
        ]);

        $projects = Auth::user()->projects()
            ->whereIn('id', collect($data['items'])->pluck('id')->all())
            ->get()->keyBy('id');

        foreach ($data['items'] as $item) {
            $project = $projects->get($item['id']);
            if (! $project) {
                continue;
            }

            $parentId = $item['parent_id'] ?? null;
            if ($parentId) {
                $parent = Auth::user()->projects()->find($parentId);
                if (! $parent) {
                    continue;
                }
                try {
                    $this->assertNoCycle($parentId, $project->id);
                } catch (HttpException $e) {
                    continue;
                }
                $project->parent_id = $parentId;
            } else {
                $project->parent_id = null;
            }
            $project->sort_order = $item['sort_order'] ?? 0;
            $project->save();
        }

        return response()->json(['ok' => true]);
    }

    private function assertNoCycle(?int $parentId, ?int $selfId = null): void
    {
        if (! $parentId) {
            return;
        }
        if ($selfId && (int) $parentId === (int) $selfId) {
            abort(422, 'A project cannot be its own parent.');
        }

        $current = Project::find($parentId);
        $guard = 0;
        while ($current && $guard++ < 100) {
            if ($selfId && (int) $current->id === (int) $selfId) {
                abort(422, 'Cannot move a project under its own descendant.');
            }
            $current = $current->parent_id ? Project::find($current->parent_id) : null;
        }
    }

    private function descendantIds(Project $project): array
    {
        $ids = [];
        $stack = $project->children()->pluck('id')->all();
        $guard = 0;
        while (! empty($stack) && $guard++ < 1000) {
            $id = array_pop($stack);
            $ids[] = $id;
            $childIds = Project::where('parent_id', $id)->pluck('id')->all();
            foreach ($childIds as $childId) {
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    public function addMember(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $project = Project::find($request->project_id);
        $project->teamProjects()->attach($request->user_id);

        return redirect()->back()->with('success', 'User added successfully.');
    }
}
