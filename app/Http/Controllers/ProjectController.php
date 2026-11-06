<?php

namespace App\Http\Controllers;

use App\Models\Project;
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

        // Grouped grid/list: one card per root, children nested inside.
        // Search index per root (own name + every descendant name) so a
        // group stays visible when a sub-project matches the search term.
        $collectNames = function ($nodes) use (&$collectNames): array {
            $out = [];
            foreach ($nodes as $node) {
                $out[] = $node->name;
                if ($node->relationLoaded('childrenRecursive')) {
                    $out = array_merge($out, $collectNames($node->childrenRecursive));
                }
            }

            return $out;
        };
        $groupSearch = [];
        foreach ($roots as $root) {
            $names = array_merge([$root->name], $collectNames($root->childrenRecursive));
            $groupSearch[$root->id] = mb_strtolower(implode(' ', $names));
        }

        return view('projects.index', compact('projects', 'roots', 'groupSearch'));
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
            'daily_goal_value' => 'nullable|integer|min:1|max:10000',
            'daily_goal_unit' => 'nullable|in:tasks,pages,minutes',
        ]);

        $data = $this->normalizeDailyGoal($data);
        $data['type'] = $data['type'] ?? 'project';
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $this->assertNoCycle($data['parent_id'] ?? null);

        $createdProject = Auth::user()->projects()->create($data);
        $createdProject->reopenCompletedAncestors();

        return redirect()->route('projects.index')->with('success', __('Project created successfully.'));
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
        $linkedNotes = \App\Models\Note::ofUser((int) Auth::id())
            ->linkedTo(\App\Models\Project::class, (int) $project->id)
            ->notArchived()
            ->with(['labels'])
            ->chronological('desc')
            ->limit(20)
            ->get();

        $noteLinks = \App\Models\NoteLink::where('linkable_type', \App\Models\Project::class)
            ->where('linkable_id', (int) $project->id)
            ->whereHas('note', fn ($q) => $q->where('user_id', Auth::id()))
            ->with(['note:id,title,updated_at'])
            ->get();

        $recentNotes = \App\Models\Note::ofUser((int) Auth::id())
            ->notArchived()
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'title']);

        return view('projects.show', compact('project', 'linkedNotes', 'recentNotes', 'noteLinks'));
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
            'daily_goal_value' => 'nullable|integer|min:1|max:10000',
            'daily_goal_unit' => 'nullable|in:tasks,pages,minutes',
        ]);

        $data = $this->normalizeDailyGoal($data);

        if ($data['status'] === 'completed' && $project->hasIncompleteDescendants()) {
            abort(422, __('Complete all tasks and subprojects before completing this project.'));
        }

        $this->assertNoCycle($data['parent_id'] ?? null, $project->id);

        $project->update($data);
        if ($data['status'] !== 'completed' || $project->wasChanged('parent_id')) {
            $project->reopenCompletedAncestors();
        }

        return redirect()->route('projects.index')->with('success', __('Project updated successfully.'));
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('projects.index')->with('success', __('Project deleted successfully.'));
    }

    /**
     * Full subtree as JSON for the Structure Manager (chapters/lessons tree).
     * Loads the same relations as show() so progress math matches.
     */
    public function structure(Project $project)
    {
        abort_if($project->user_id !== Auth::id(), 403);
        $project->load([
            'childrenRecursive.tasks.childrenRecursive',
            'childrenRecursive.tasks.timeEntries',
            'childrenRecursive.childrenRecursive',
            'tasks.childrenRecursive',
            'tasks.timeEntries',
        ]);

        return response()->json(['ok' => true, 'tree' => $this->serializeNode($project)]);
    }

    /**
     * Quick-create a chapter/lesson/section/subproject under this tree.
     * parent_node_id null/empty = direct child of $project.
     */
    public function storeNode(Request $request, Project $project)
    {
        abort_if($project->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:project,subproject,lesson,chapter,section',
            'parent_node_id' => 'nullable|integer',
        ]);

        $parent = $project;
        if (! empty($data['parent_node_id'])) {
            $parent = Auth::user()->projects()->find($data['parent_node_id']);
            abort_if(! $parent, 422, __('Invalid destination.'));
            // Parent must live inside this tree (or be the root itself).
            $allowed = $project->descendantProjectIds();
            abort_if(! in_array((int) $parent->id, $allowed, true), 422, __('Destination is outside this project.'));
        }

        $maxSort = (int) Auth::user()->projects()->where('parent_id', $parent->id)->max('sort_order');
        $node = Auth::user()->projects()->create([
            'name' => $data['name'],
            'type' => $data['type'] ?? 'chapter',
            'parent_id' => $parent->id,
            'status' => 'not_started',
            'sort_order' => $maxSort + 1,
        ]);
        $node->reopenCompletedAncestors();

        $node->load(['tasks', 'children']);
        return response()->json(['ok' => true, 'node' => $this->serializeNode($node)]);
    }

    /**
     * Bulk-move nodes inside this tree (drag & drop + "move to" menu + bulk).
     * destination_id null/empty = direct child of $project (ungrouped).
     */
    public function moveNodes(Request $request, Project $project)
    {
        abort_if($project->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer',
            'destination_id' => 'nullable|integer',
        ]);

        $allowed = $project->descendantProjectIds();
        foreach ($data['ids'] as $id) {
            abort_if((int) $id === (int) $project->id, 422, __('Cannot move the root project.'));
            abort_if(! in_array((int) $id, $allowed, true), 422, __('Some items are outside this project.'));
        }

        $destination = $project;
        if (! empty($data['destination_id'])) {
            abort_if(in_array((int) $data['destination_id'], array_map('intval', $data['ids']), true), 422, __('Cannot move into itself.'));
            $destination = Auth::user()->projects()->find($data['destination_id']);
            abort_if(! $destination, 422, __('Invalid destination.'));
            abort_if(! in_array((int) $destination->id, $allowed, true), 422, __('Destination is outside this project.'));
            // Destination must not be a descendant of any moved node (cycle).
            $descOfMoved = [];
            foreach ($data['ids'] as $id) {
                $n = Auth::user()->projects()->find($id);
                if ($n) {
                    $descOfMoved = array_merge($descOfMoved, $n->descendantProjectIds());
                }
            }
            abort_if(in_array((int) $destination->id, array_map('intval', $descOfMoved), true), 422, __('Cannot move a node under its own descendant.'));
        }

        $baseSort = (int) Auth::user()->projects()->where('parent_id', $destination->id)->max('sort_order');
        $nodes = Auth::user()->projects()->whereIn('id', $data['ids'])->orderBy('sort_order')->orderBy('id')->get();
        foreach ($nodes as $i => $node) {
            $node->parent_id = $destination->id;
            $node->sort_order = $baseSort + $i + 1;
            $node->save();
            $node->reopenCompletedAncestors();
        }

        return response()->json(['ok' => true, 'moved' => $nodes->count(), 'destination_id' => (int) $destination->id]);
    }

    /**
     * Convert a node's type (lesson <-> chapter <-> section ...) without moving it.
     */
    public function convertNode(Request $request, Project $node)
    {
        abort_if($node->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'type' => 'required|in:project,subproject,lesson,chapter,section',
        ]);

        $node->update(['type' => $data['type']]);

        return response()->json(['ok' => true, 'node' => ['id' => $node->id, 'type' => $node->type]]);
    }

    private function serializeNode(Project $node): array
    {
        $directTotal = $node->tasks->count();
        $directDone = $node->tasks->where('status', 'completed')->count();
        $agg = $this->aggregateCounts($node);

        $children = [];
        foreach ($node->children as $child) {
            $children[] = $this->serializeNode($child);
        }

        return [
            'id' => (int) $node->id,
            'name' => (string) $node->name,
            'slug' => (string) $node->slug,
            'type' => (string) ($node->type ?? 'project'),
            'status' => (string) $node->status,
            'sort_order' => (int) ($node->sort_order ?? 0),
            'url' => route('projects.show', $node),
            'progress' => round((float) $node->progressPercent()),
            'tasks_total' => $directTotal,
            'tasks_done' => $directDone,
            'agg_total' => $agg['total'],
            'agg_done' => $agg['done'],
            'tasks' => $node->tasks->map(fn ($t) => [
                'id' => (int) $t->id,
                'title' => (string) $t->title,
                'status' => (string) $t->status,
            ])->values()->all(),
            'children' => $children,
        ];
    }

    private function aggregateCounts(Project $node): array
    {
        $total = $node->tasks->count();
        $done = $node->tasks->where('status', 'completed')->count();
        foreach ($node->children as $child) {
            $sub = $this->aggregateCounts($child);
            $total += $sub['total'];
            $done += $sub['done'];
        }

        return ['total' => $total, 'done' => $done];
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

    /**
     * Empty goal value = disabled (both columns nulled); otherwise default unit to tasks.
     */
    private function normalizeDailyGoal(array $data): array
    {
        if (empty($data['daily_goal_value'])) {
            $data['daily_goal_value'] = null;
            $data['daily_goal_unit'] = null;
        } else {
            $data['daily_goal_value'] = (int) $data['daily_goal_value'];
            if (empty($data['daily_goal_unit']) || ! in_array($data['daily_goal_unit'], Project::DAILY_GOAL_UNITS, true)) {
                $data['daily_goal_unit'] = 'tasks';
            }
        }

        return $data;
    }

    /**
     * Attach an existing note to this project.
     */
    public function attachNote(Request $request, Project $project, \App\Services\Notes\NoteLinkService $links)
    {
        abort_if($project->user_id !== Auth::id(), 403);

        $data = $request->validate(['note_id' => ['required', 'integer', 'min:1']]);

        $note = \App\Models\Note::ofUser(Auth::id())->whereKey($data['note_id'])->first();

        if (! $note) {
            return response()->json(['success' => false, 'message' => __('Note not found.')], 422);
        }

        $link = $links->attach($note, \App\Models\Project::class, (int) $project->id);

        if (! $link) {
            return response()->json(['success' => false, 'message' => __('Could not attach.')], 422);
        }

        return response()->json([
            'success' => true,
            'note' => ['id' => $note->id, 'title' => $note->title, 'url' => route('notes.show', $note)],
        ]);
    }

    public function detachNote(Project $project, \App\Models\NoteLink $link)
    {
        abort_if($project->user_id !== Auth::id(), 403);

        if ($link->linkable_type !== \App\Models\Project::class || (int) $link->linkable_id !== (int) $project->id) {
            return response()->json(['success' => false], 404);
        }

        $note = \App\Models\Note::ofUser(Auth::id())->whereKey($link->note_id)->first();

        if (! $note) {
            return response()->json(['success' => false], 404);
        }

        $link->delete();

        return response()->json(['success' => true]);
    }
}
