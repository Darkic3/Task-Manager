<?php

namespace App\Services;

use App\Models\Law;
use App\Models\LawLink;
use App\Models\Project;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Static law ↔ entity links with preset direction.
 *
 * Resolution order for a task (decided once at link time, never per event):
 *   1. Direct task link (not excluded) wins.
 *   2. An `is_excluded` task link opts out of every project inheritance.
 *   3. Otherwise the project chain is walked bottom-up: the nearest
 *      linked ancestor project applies (covers sub-projects too).
 * Routines and projects resolve by direct link only.
 */
class LawLinkService
{
    public const LINKABLE = [
        Task::class => 'title',
        Routine::class => 'title',
        Project::class => 'name',
    ];

    public const TYPE_ALIASES = [
        'task' => Task::class,
        'routine' => Routine::class,
        'project' => Project::class,
    ];

    public function typeFromAlias(string $alias): ?string
    {
        return self::TYPE_ALIASES[$alias] ?? null;
    }

    public function aliasFromType(string $type): ?string
    {
        $alias = array_search($type, self::TYPE_ALIASES, true);

        return $alias === false ? null : $alias;
    }

    /**
     * Attach (or re-target) an entity to a law with a preset direction.
     * Returns null when the target is unknown or belongs to another user.
     */
    public function attach(Law $law, string $type, int $id, int $effect): ?LawLink
    {
        if (! isset(self::LINKABLE[$type]) || ! in_array($effect, [LawLink::EFFECT_FOR, LawLink::EFFECT_AGAINST], true)) {
            return null;
        }

        $model = $type::find($id);

        if (! $model || (int) $model->user_id !== (int) $law->user_id) {
            return null;
        }

        return LawLink::updateOrCreate(
            [
                'law_id' => $law->id,
                'linkable_type' => $type,
                'linkable_id' => $id,
            ],
            ['effect' => $effect, 'is_excluded' => false],
        );
    }

    /**
     * Opt a single task out of its projects' inheritance for one law.
     */
    public function excludeTask(Law $law, Task $task): ?LawLink
    {
        if ((int) $task->user_id !== (int) $law->user_id) {
            return null;
        }

        return LawLink::updateOrCreate(
            [
                'law_id' => $law->id,
                'linkable_type' => Task::class,
                'linkable_id' => $task->id,
            ],
            ['effect' => LawLink::EFFECT_FOR, 'is_excluded' => true],
        );
    }

    /**
     * Search the user's entities for the link picker (excludes already-linked ids).
     *
     * @return Collection<int, array{id:int, title:string, sub:string}>
     */
    public function candidates(User $user, string $type, string $query = '', int $limit = 20): Collection
    {
        $nameColumn = self::LINKABLE[$type] ?? null;

        if ($nameColumn === null) {
            return collect();
        }

        $q = trim($query);

        $rows = $type::where('user_id', $user->id)
            ->when($q !== '', fn ($query) => $query->where($nameColumn, 'like', "%{$q}%"))
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', $nameColumn.' as title']);

        return $rows->map(fn ($row) => [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'sub' => $this->candidateSub($type, $row->id, $user->id),
        ]);
    }

    private function candidateSub(string $type, int $id, int $userId): string
    {
        if ($type === Task::class) {
            $task = Task::where('user_id', $userId)->whereKey($id)->first(['project_id', 'status']);

            if (! $task || ! $task->project_id) {
                return '';
            }

            return (string) (Project::where('user_id', $userId)->whereKey($task->project_id)->value('name') ?? '');
        }

        return '';
    }

    /**
     * Links of a law grouped by entity type with targets eager-loaded.
     *
     * @return Collection<string, Collection<int, LawLink>>
     */
    public function grouped(Law $law): Collection
    {
        $law->loadMissing('links.linkable');

        return $law->links
            ->filter(fn (LawLink $link) => ! $link->is_excluded)
            ->groupBy('linkable_type');
    }

    /**
     * Project chain bottom-up: [self, parent, grandparent, …] ids.
     *
     * @return array<int>
     */
    public function projectChainIds(Project $project): array
    {
        $ids = [(int) $project->id];
        $cursor = $project;
        $guard = 0;

        while ($cursor->parent_id && $guard++ < 50) {
            $parent = Project::whereKey($cursor->parent_id)->first(['id', 'parent_id']);

            if (! $parent) {
                break;
            }

            $ids[] = (int) $parent->id;
            $cursor = $parent;
        }

        return $ids;
    }

    /**
     * Effective direction of ONE law for ONE task (null = no relation).
     *
     * @return array{effect:int, direct:bool, via:?string}|null
     */
    public function resolveForTask(Task $task, Law $law): ?array
    {
        if ((int) $task->user_id !== (int) $law->user_id) {
            return null;
        }

        $direct = LawLink::where('law_id', $law->id)
            ->where('linkable_type', Task::class)
            ->where('linkable_id', $task->id)
            ->first();

        if ($direct) {
            if ($direct->is_excluded) {
                return null;
            }

            return ['effect' => (int) $direct->effect, 'direct' => true, 'via' => null];
        }

        if (! $task->project_id) {
            return null;
        }

        $project = Project::whereKey($task->project_id)->first(['id', 'parent_id', 'name']);

        if (! $project) {
            return null;
        }

        foreach ($this->projectChainIds($project) as $projectId) {
            $link = LawLink::where('law_id', $law->id)
                ->where('linkable_type', Project::class)
                ->where('linkable_id', $projectId)
                ->where('is_excluded', false)
                ->first();

            if ($link) {
                $via = $projectId === (int) $project->id
                    ? $project->name
                    : (string) (Project::whereKey($projectId)->value('name') ?? '');

                return ['effect' => (int) $link->effect, 'direct' => false, 'via' => $via];
            }
        }

        return null;
    }

    /**
     * Every law of the user with its effective direction for the given task.
     * Used by the auto-event hooks (phase 4) and the "where does this count" UI.
     *
     * @return Collection<int, array{law:Law, effect:int, direct:bool, via:?string}>
     */
    public function effectiveLawsForTask(Task $task): Collection
    {
        $laws = Law::where('user_id', $task->user_id)->get();
        $out = collect();

        foreach ($laws as $law) {
            $resolved = $this->resolveForTask($task, $law);

            if ($resolved !== null) {
                $out->push(['law' => $law] + $resolved);
            }
        }

        return $out;
    }

    /**
     * Tasks of a project tree annotated with their effective direction for a law.
     *
     * @return Collection<int, array{task:Task, effect:?int, direct:bool, via:?string, excluded:bool}>
     */
    public function projectTasksForLaw(Project $project, Law $law, int $limit = 30): Collection
    {
        $ids = $this->projectTreeIds((int) $project->user_id, (int) $project->id);

        $tasks = Task::where('user_id', $project->user_id)
            ->whereIn('project_id', $ids)
            ->where('status', '!=', 'completed')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $links = LawLink::where('law_id', $law->id)
            ->where('linkable_type', Task::class)
            ->whereIn('linkable_id', $tasks->pluck('id')->all())
            ->get()
            ->keyBy('linkable_id');

        return $tasks->map(function (Task $task) use ($law, $links) {
            $direct = $links->get($task->id);

            if ($direct && $direct->is_excluded) {
                return ['task' => $task, 'effect' => null, 'direct' => false, 'via' => null, 'excluded' => true];
            }

            $resolved = $this->resolveForTask($task, $law);

            return [
                'task' => $task,
                'effect' => $resolved['effect'] ?? null,
                'direct' => (bool) ($resolved['direct'] ?? false),
                'via' => $resolved['via'] ?? null,
                'excluded' => false,
            ];
        });
    }

    /**
     * A project id plus every descendant id below it (sub-projects).
     *
     * @return array<int>
     */
    private function projectTreeIds(int $userId, int $rootId): array
    {
        $ids = [$rootId];
        $stack = [$rootId];
        $guard = 0;

        while (! empty($stack) && $guard++ < 1000) {
            $children = Project::where('user_id', $userId)
                ->whereIn('parent_id', $stack)
                ->pluck('id')
                ->all();
            $stack = array_values(array_diff($children, $ids));
            $ids = array_merge($ids, $stack);
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
