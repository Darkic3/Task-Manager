<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeEntryOutcomeService
{
    /** Return the completed-work choices and valid time targets for a stopped entry. */
    public function choices(TimeEntry $entry): array
    {
        $tasks = collect();
        $projects = collect();

        if ($entry->task_id) {
            $tasks = $this->descendantTasks(Task::findOrFail($entry->task_id));
        } elseif ($entry->project_id) {
            $projects = $this->descendantProjects(Project::findOrFail($entry->project_id));
            $projectIds = $projects->pluck('id')->push($entry->project_id)->all();
            $tasks = Task::where('user_id', $entry->user_id)
                ->whereIn('project_id', $projectIds)
                ->orderBy('sort_order')->orderBy('id')->get();
        }

        $taskChoices = $tasks->map(fn (Task $task) => [
            'id' => (int) $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'parent_id' => $task->parent_id ? (int) $task->parent_id : null,
            'depth' => $this->taskDepth($task, $entry->task_id),
        ])->values()->all();

        $projectChoices = $projects->map(fn (Project $project) => [
            'id' => (int) $project->id,
            'name' => $project->name,
            'status' => $project->status,
            'parent_id' => $project->parent_id ? (int) $project->parent_id : null,
            'depth' => $this->projectDepth($project, $entry->project_id),
        ])->values()->all();

        $targets = [];
        if ($entry->task) {
            $targets[] = ['type' => 'task', 'id' => (int) $entry->task->id, 'label' => $entry->task->title];
        } elseif ($entry->project) {
            $targets[] = ['type' => 'project', 'id' => (int) $entry->project->id, 'label' => $entry->project->name];
        }
        foreach ($projectChoices as $project) {
            $targets[] = ['type' => 'project', 'id' => $project['id'], 'label' => $project['name']];
        }
        foreach ($taskChoices as $task) {
            $targets[] = ['type' => 'task', 'id' => $task['id'], 'label' => $task['title']];
        }

        return ['tasks' => $taskChoices, 'projects' => $projectChoices, 'targets' => $targets];
    }

    /** Apply only the work the user explicitly marked complete and move the stopped time entry. */
    public function apply(TimeEntry $entry, array $data): void
    {
        if ($entry->status !== TimeEntry::STATUS_STOPPED) {
            throw ValidationException::withMessages(['entry' => __('Stop the timer before recording its outcome.')]);
        }

        $taskIds = array_map('intval', $data['completed_task_ids'] ?? []);
        $projectIds = array_map('intval', $data['completed_project_ids'] ?? []);
        $completeRoot = (bool) ($data['complete_root'] ?? false);
        $rootTask = $entry->task_id ? Task::where('user_id', $entry->user_id)->findOrFail($entry->task_id) : null;
        $rootProject = ! $rootTask && $entry->project_id
            ? Project::where('user_id', $entry->user_id)->findOrFail($entry->project_id)
            : null;

        $taskChoices = collect($this->choices($entry)['tasks'])->pluck('id')->all();
        $projectChoices = collect($this->choices($entry)['projects'])->pluck('id')->all();
        if (array_diff($taskIds, $taskChoices) || array_diff($projectIds, $projectChoices)) {
            throw ValidationException::withMessages(['completed_task_ids' => __('Selected work must belong to this timer item.')]);
        }

        if ($rootTask && $completeRoot) {
            $taskIds[] = (int) $rootTask->id;
        }
        if ($rootProject && $completeRoot) {
            $projectIds[] = (int) $rootProject->id;
        }
        $taskIds = array_values(array_unique($taskIds));
        $projectIds = array_values(array_unique($projectIds));

        $targetType = $data['target_type'] ?? null;
        $targetId = (int) ($data['target_id'] ?? 0);
        $targets = collect($this->choices($entry)['targets']);
        $target = $targets->first(fn ($choice) => $choice['type'] === $targetType && $choice['id'] === $targetId);
        if (! $target) {
            throw ValidationException::withMessages(['target_id' => __('Choose a time target within this task or project.')]);
        }

        DB::transaction(function () use ($entry, $taskIds, $projectIds, $targetType, $targetId) {
            foreach ($taskIds as $id) {
                $task = Task::where('user_id', $entry->user_id)->findOrFail($id);
                $remaining = $this->descendantTasks($task)
                    ->where('status', '!=', 'completed')
                    ->whereNotIn('id', $taskIds);
                if ($remaining->isNotEmpty()) {
                    throw ValidationException::withMessages(['completed_task_ids' => __('Complete every subtask before completing its parent.')]);
                }
            }

            foreach ($projectIds as $id) {
                $project = Project::where('user_id', $entry->user_id)->findOrFail($id);
                $descendantProjectIds = $this->descendantProjects($project)->pluck('id')->all();
                $unselectedProjects = Project::whereIn('id', $descendantProjectIds)
                    ->where('status', '!=', 'completed')->whereNotIn('id', $projectIds)->exists();
                $allProjectIds = array_merge([$id], $descendantProjectIds);
                $unselectedTasks = Task::where('user_id', $entry->user_id)->whereIn('project_id', $allProjectIds)
                    ->where('status', '!=', 'completed')->whereNotIn('id', $taskIds)->exists();
                if ($unselectedProjects || $unselectedTasks) {
                    throw ValidationException::withMessages(['completed_project_ids' => __('Complete every task and subproject before completing its parent project.')]);
                }
            }

            Task::where('user_id', $entry->user_id)->whereIn('id', $taskIds)->update([
                'status' => 'completed', 'completed_at' => now(), 'failed_at' => null, 'fail_note' => null,
            ]);
            Project::where('user_id', $entry->user_id)->whereIn('id', $projectIds)->update(['status' => 'completed']);

            if ($targetType === 'task') {
                $task = Task::where('user_id', $entry->user_id)->findOrFail($targetId);
                $entry->update(['task_id' => $task->id, 'project_id' => $task->project_id]);
            } else {
                $project = Project::where('user_id', $entry->user_id)->findOrFail($targetId);
                $entry->update(['task_id' => null, 'project_id' => $project->id]);
            }
        });
    }

    private function descendantTasks(Task $root)
    {
        $ids = [];
        $frontier = [$root->id];
        while ($frontier !== []) {
            $children = Task::where('user_id', $root->user_id)->whereIn('parent_id', $frontier)
                ->orderBy('sort_order')->orderBy('id')->get(['id', 'title', 'status', 'parent_id']);
            $frontier = $children->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
            if (count($ids) > 10000) break;
        }

        return Task::whereIn('id', $ids)->orderBy('sort_order')->orderBy('id')->get(['id', 'title', 'status', 'parent_id']);
    }

    private function descendantProjects(Project $root)
    {
        $ids = [];
        $frontier = [$root->id];
        while ($frontier !== []) {
            $children = Project::where('user_id', $root->user_id)->whereIn('parent_id', $frontier)
                ->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'status', 'parent_id']);
            $frontier = $children->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
            if (count($ids) > 10000) break;
        }

        return Project::whereIn('id', $ids)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'status', 'parent_id']);
    }

    private function taskDepth(Task $task, ?int $rootId): int
    {
        $depth = 1;
        $parentId = $task->parent_id;
        while ($parentId && $parentId !== $rootId && $depth < 100) {
            $parentId = Task::where('user_id', $task->user_id)->whereKey($parentId)->value('parent_id');
            $depth++;
        }

        return $depth;
    }

    private function projectDepth(Project $project, ?int $rootId): int
    {
        $depth = 1;
        $parentId = $project->parent_id;
        while ($parentId && $parentId !== $rootId && $depth < 100) {
            $parentId = Project::where('user_id', $project->user_id)->whereKey($parentId)->value('parent_id');
            $depth++;
        }

        return $depth;
    }
}
