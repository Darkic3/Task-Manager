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
        'metadata',
    ];

    protected $dates = [
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
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

    /**
     * Chain of ancestors from the root down to (excluding) this project.
     */
    public function breadcrumb(): array
    {
        $chain = [];
        $current = $this->parent;
        $guard = 0;
        while ($current && $guard++ < 50) {
            array_unshift($chain, $current);
            $current = $current->parent;
        }

        return $chain;
    }

    public function level(): int
    {
        return count($this->breadcrumb());
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
}
