<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'parent_id',
        'sort_order',
        'type',
        'name',
        'slug',
        'description',
        'start_date',
        'end_date',
        'status',
        'budget',
        'daily_goal_value',
        'daily_goal_unit',
        'metadata',
    ];

    protected $dates = [
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'daily_goal_value' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Use slug for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Auto-generate unique slug on creating/updating.
     */
    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->slug = static::uniqueSlug($project->name, $project->id);
        });

        static::updating(function (Project $project) {
            if ($project->isDirty('name')) {
                $project->slug = static::uniqueSlug($project->name, $project->id);
            }
        });
    }

    private static function uniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function parent()
    {
        return $this->belongsTo(Project::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Project::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive')->with('tasks');
    }

    /** A project can be completed only when every task and subproject is done. */
    public function hasIncompleteDescendants(): bool
    {
        $projectIds = $this->descendantProjectIds();
        $incompleteProjects = static::whereIn('id', $projectIds)
            ->where('id', '!=', $this->id)
            ->where('status', '!=', 'completed')->exists();

        if ($incompleteProjects) {
            return true;
        }

        return Task::where('user_id', $this->user_id)
            ->whereIn('project_id', $projectIds)
            ->where('status', '!=', 'completed')->exists();
    }

    /** Reopen completed project ancestors after this project is reopened. */
    public function reopenCompletedAncestors(): void
    {
        $parent = $this->parent;
        $guard = 0;
        while ($parent && $guard++ < 100) {
            if ($parent->status === 'completed') {
                $parent->update(['status' => 'in_progress']);
            }
            $parent = $parent->parent;
        }
    }

    /**
     * Chain of ancestors from the root down to (excluding) this project.
     * Uses the loaded `parent` relation when available (no queries).
     */
    public function breadcrumb(): array
    {
        $chain = [];
        $current = $this->relationLoaded('parent') ? $this->parent : $this->parent()->first();
        $guard = 0;
        while ($current && $guard++ < 50) {
            array_unshift($chain, $current);
            $current = $current->relationLoaded('parent') ? $current->parent : $current->parent()->first();
        }

        return $chain;
    }

    public function level(): int
    {
        return count($this->breadcrumb());
    }

    /**
     * All seconds logged on this project and its descendants (single query).
     */
    public function totalTimeSeconds(): int
    {
        $ids = [$this->id];
        $stack = $this->children()->pluck('id')->all();
        $guard = 0;
        while (! empty($stack) && $guard++ < 1000) {
            $id = array_pop($stack);
            $ids[] = $id;
            foreach (Project::where('parent_id', $id)->pluck('id')->all() as $childId) {
                $stack[] = $childId;
            }
        }

        $done = (int) TimeEntry::whereIn('project_id', $ids)
            ->where('status', TimeEntry::STATUS_STOPPED)
            ->sum('duration_seconds');
        $active = TimeEntry::whereIn('project_id', $ids)
            ->whereIn('status', [TimeEntry::STATUS_RUNNING, TimeEntry::STATUS_PAUSED])
            ->get()->sum(fn ($e) => $e->elapsedSeconds());

        return $done + $active;
    }

    /**
     * Aggregate weight = sum of root-task weights + child-project weights.
     * Empty branches weigh 0 so they don't drag the parent down.
     */
    public function aggregateWeight(): float
    {
        $weight = 0.0;
        foreach ($this->tasks->filter(fn ($t) => $t->parent_id === null) as $task) {
            $weight += $task->aggregateWeight();
        }
        foreach ($this->children as $child) {
            $weight += $child->aggregateWeight();
        }

        return $weight;
    }

    /**
     * Weighted progress (0-100) over root tasks + child projects.
     */
    public function progressPercent(int $depth = 0): float
    {
        if ($depth > 50) {
            return 0.0;
        }

        $total = 0.0;
        $weighted = 0.0;

        foreach ($this->tasks->filter(fn ($t) => $t->parent_id === null) as $task) {
            $w = $task->aggregateWeight();
            if ($w <= 0) {
                continue;
            }
            $total += $w;
            $weighted += $w * $task->progressPercent($depth + 1);
        }

        foreach ($this->children as $child) {
            $w = $child->aggregateWeight();
            if ($w <= 0) {
                continue;
            }
            $total += $w;
            $weighted += $w * $child->progressPercent($depth + 1);
        }

        return $total > 0 ? round($weighted / $total, 1) : 0.0;
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function getDerivedStatusAttribute()
    {
        $today = Carbon::now();

        if ($this->start_date && $today->lt($this->start_date)) {
            return 'pending';
        }

        if ($this->end_date && $this->end_date->lt($today)) {
            $unfinishedTasks = $this->tasks()->where('status', '!=', 'completed')->count();

            return $unfinishedTasks > 0 ? 'unfinished' : 'finished';
        }

        return 'on_going';
    }

    public function teamProjects()
    {
        return $this->belongsToMany(ProjectTeam::class, 'project_teams', 'project_id', 'user_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'project_teams', 'project_id', 'user_id');
    }

    // ── Daily goal ("حداقل قدم روزانه") ──────────────────────────

    public const DAILY_GOAL_UNITS = ['tasks', 'pages', 'minutes'];

    public const DAILY_GOAL_UNIT_LABELS = [
        'tasks' => 'Tasks',
        'pages' => 'Pages',
        'minutes' => 'Minutes',
    ];

    /** Request-level memo for descendantProjectIds() (perf: one walk per instance). */
    private ?array $descIdsMemo = null;

    public function hasDailyGoal(): bool
    {
        return $this->daily_goal_value !== null && (int) $this->daily_goal_value > 0;
    }

    public function dailyGoalUnit(): string
    {
        return in_array($this->daily_goal_unit, self::DAILY_GOAL_UNITS, true)
            ? $this->daily_goal_unit
            : 'tasks';
    }

    /**
     * Self + every descendant project id (breadth-first, cycle safe).
     *
     * @return array<int>
     */
    public function descendantProjectIds(): array
    {
        if ($this->descIdsMemo !== null) {
            return $this->descIdsMemo;
        }

        $ids = [(int) $this->id];
        $frontier = [(int) $this->id];
        $guard = 0;

        while ($frontier !== [] && $guard++ < 50) {
            $children = static::whereIn('parent_id', $frontier)->pluck('id')->all();
            $children = array_values(array_diff(array_map('intval', $children), $ids));

            if ($children === []) {
                break;
            }

            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $this->descIdsMemo = $ids;
    }

    /**
     * Today's progress toward the daily goal, aggregated over the whole subtree.
     *
     * - tasks:   completed tasks (by completed_at, falling back to updated_at) on $date
     * - pages:   sum of task weight (≈ pages) for those completed tasks
     * - minutes: focused time logged on $date (stopped + live elapsed)
     *
     * @return array{current: float|int, target: int, unit: string, percent: float, remaining: float|int, met: bool}
     */
    public function dailyGoalProgress($date = null): array
    {
        $target = (int) ($this->daily_goal_value ?? 0);
        $unit = $this->dailyGoalUnit();
        $empty = ['current' => 0, 'target' => $target, 'unit' => $unit, 'percent' => 0.0, 'remaining' => $target, 'met' => false];

        if ($target <= 0) {
            return $empty;
        }

        $day = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date ?? Carbon::today()->toDateString())->startOfDay();
        $key = $day->toDateString();
        $ids = $this->descendantProjectIds();

        if ($unit === 'minutes') {
            $entries = TimeEntry::where('user_id', $this->user_id)
                ->where(function ($q) use ($ids) {
                    $q->whereIn('project_id', $ids)
                        ->orWhereHas('task', fn ($t) => $t->whereIn('project_id', $ids));
                })
                ->whereDate('started_at', $key)
                ->get();

            $seconds = 0;
            foreach ($entries as $entry) {
                $seconds += $entry->status === TimeEntry::STATUS_STOPPED
                    ? (int) ($entry->duration_seconds ?? 0)
                    : $entry->elapsedSeconds();
            }
            $current = (int) floor($seconds / 60);
        } else {
            $completed = Task::where('user_id', $this->user_id)
                ->whereIn('project_id', $ids)
                ->where('status', 'completed')
                ->where(function ($q) use ($key) {
                    $q->whereDate('completed_at', $key)
                        ->orWhere(function ($qq) use ($key) {
                            $qq->whereNull('completed_at')->whereDate('updated_at', $key);
                        });
                });

            if ($unit === 'pages') {
                // weight ≈ pages (user maps 1 weight unit = 1 page).
                $current = (float) ($completed->sum('weight') ?? 0);
                // Tasks created before the weight column may hold null.
                if ($current <= 0) {
                    $current = (float) $completed->count();
                }
            } else {
                $current = (int) $completed->count();
            }
        }

        $percent = $target > 0 ? round(min(100, $current / $target * 100), 1) : 0.0;

        return [
            'current' => $current,
            'target' => $target,
            'unit' => $unit,
            'percent' => $percent,
            'remaining' => max(0, $target - $current),
            'met' => $current >= $target,
        ];
    }

    public function dailyGoalLabel(array $progress = null): string
    {
        $p = $progress ?? $this->dailyGoalProgress();
        $unitLabel = __(self::DAILY_GOAL_UNIT_LABELS[$p['unit']] ?? $p['unit']);

        if ($p['unit'] === 'minutes') {
            $fmt = fn ($m) => $m >= 60
                ? round($m / 60, 1).'h'
                : $m.'m';

            return $fmt((int) $p['current']).' / '.$fmt((int) $p['target']).' '.$unitLabel;
        }

        return rtrim(rtrim(number_format((float) $p['current'], 1, '.', ''), '0'), '.')
            .' / '.$p['target'].' '.$unitLabel;
    }

    /**
     * N-day daily-goal history (oldest → today), computed from the same
     * sources as dailyGoalProgress() but with 2 bounded queries instead of
     * one query per day. No extra table needed.
     *
     * @return array{days: array<int, array{date: string, current: float|int, target: int, met: bool}>, met: int, total: int, rate: int, streak: int}
     */
    public function dailyGoalHistory(int $days = 30): array
    {
        $days = max(1, min(90, $days));
        $target = (int) ($this->daily_goal_value ?? 0);
        $unit = $this->dailyGoalUnit();
        $empty = ['days' => [], 'met' => 0, 'total' => 0, 'rate' => 0, 'streak' => 0];

        if ($target <= 0) {
            return $empty;
        }

        $today = Carbon::today();
        $start = $today->copy()->subDays($days - 1)->startOfDay();
        $ids = $this->descendantProjectIds();

        // Per-day totals across the subtree.
        $perDay = [];
        $cursor = $start->copy();
        while ($cursor->lte($today)) {
            $perDay[$cursor->toDateString()] = 0.0;
            $cursor->addDay();
        }

        if ($unit === 'minutes') {
            $entries = TimeEntry::where('user_id', $this->user_id)
                ->where(function ($q) use ($ids) {
                    $q->whereIn('project_id', $ids)
                        ->orWhereHas('task', fn ($t) => $t->whereIn('project_id', $ids));
                })
                ->whereDate('started_at', '>=', $start->toDateString())
                ->get(['started_at', 'duration_seconds', 'status']);
            foreach ($entries as $entry) {
                $day = $entry->started_at instanceof Carbon
                    ? $entry->started_at->toDateString()
                    : Carbon::parse($entry->started_at)->toDateString();
                if (! array_key_exists($day, $perDay)) {
                    continue;
                }
                $perDay[$day] += $entry->status === TimeEntry::STATUS_STOPPED
                    ? (int) ($entry->duration_seconds ?? 0)
                    : $entry->elapsedSeconds();
            }
            foreach ($perDay as $day => $seconds) {
                $perDay[$day] = (int) floor($seconds / 60);
            }
        } else {
            $completed = Task::where('user_id', $this->user_id)
                ->whereIn('project_id', $ids)
                ->where('status', 'completed')
                ->where(function ($q) use ($start) {
                    $q->whereDate('completed_at', '>=', $start->toDateString())
                        ->orWhere(function ($qq) use ($start) {
                            $qq->whereNull('completed_at')->whereDate('updated_at', '>=', $start->toDateString());
                        });
                })
                ->get(['weight', 'completed_at', 'updated_at']);
            foreach ($completed as $task) {
                $at = $task->completed_at ?? $task->updated_at;
                $day = $at instanceof Carbon ? $at->toDateString() : ($at ? Carbon::parse($at)->toDateString() : null);
                if ($day === null || ! array_key_exists($day, $perDay) || $day > $today->toDateString()) {
                    continue;
                }
                $perDay[$day] += $unit === 'pages' ? max(0.0, (float) ($task->weight ?? 1)) : 1;
            }
            if ($unit === 'pages') {
                foreach ($perDay as $day => $value) {
                    $perDay[$day] = round($value, 1);
                }
            } else {
                foreach ($perDay as $day => $value) {
                    $perDay[$day] = (int) $value;
                }
            }
        }

        $out = [];
        $met = 0;
        foreach ($perDay as $day => $current) {
            $isMet = $current >= $target;
            if ($isMet) {
                $met++;
            }
            $out[] = ['date' => $day, 'current' => $current, 'target' => $target, 'met' => $isMet];
        }

        // Current streak: consecutive met days ending today; when today is
        // still open (unmet), the streak counts back from yesterday.
        $streak = 0;
        $keys = array_keys($perDay);
        $end = end($keys);
        if ($end !== false && ($perDay[$end] ?? 0) < $target) {
            array_pop($keys);
        }
        for ($i = count($keys) - 1; $i >= 0; $i--) {
            if (($perDay[$keys[$i]] ?? 0) >= $target) {
                $streak++;
            } else {
                break;
            }
        }

        $total = count($out);

        return [
            'days' => $out,
            'met' => $met,
            'total' => $total,
            'rate' => $total > 0 ? (int) round($met / $total * 100) : 0,
            'streak' => $streak,
        ];
    }

    /**
     * Hierarchy-aware labels for reports: [project_id => "Root / … / Name"].
     * Single query + in-memory walk (no N+1); cycle safe. Two chapters with
     * the same name in different books get different labels, so they never
     * merge in report breakdowns.
     *
     * @return array<int, string>
     */
    public static function pathMap(int $userId): array
    {
        $rows = static::where('user_id', $userId)->get(['id', 'name', 'parent_id']);
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row->id] = ['name' => (string) $row->name, 'parent' => $row->parent_id !== null ? (int) $row->parent_id : null];
        }

        $paths = [];
        foreach ($byId as $id => $info) {
            $parts = [$info['name']];
            $parent = $info['parent'];
            $guard = 0;
            while ($parent !== null && isset($byId[$parent]) && $guard++ < 50) {
                array_unshift($parts, $byId[$parent]['name']);
                if ($parent === $id) {
                    break;
                }
                $parent = $byId[$parent]['parent'];
            }
            $paths[$id] = implode(' / ', $parts);
        }

        return $paths;
    }
}
