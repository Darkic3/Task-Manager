<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'parent_id',
        'title',
        'description',
        'due_date',
        'priority',
        'status',
        'weight',
        'auto_weight',
        'sort_order',
        'completed_at',
        'estimated_hours',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'auto_weight' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function parent()
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    public static function statusProgress(string $status): float
    {
        return match ($status) {
            'completed' => 100.0,
            'in_review' => 75.0,
            'in_progress' => 50.0,
            'on_hold' => 10.0,
            default => 0.0,
        };
    }

    /**
     * Split decimal hours into [hours, minutes] for form inputs.
     */
    public static function splitHours($decimal): array
    {
        if ($decimal === null || $decimal === '') {
            return ['', ''];
        }
        $total = (int) round((float) $decimal * 60);
        $h = intdiv($total, 60);
        $m = $total % 60;

        return [$h > 0 ? $h : '', $m > 0 ? $m : ''];
    }

    /**
     * Combine hours + minutes inputs into decimal hours (null when empty).
     */
    public static function combineEstimate($hours, $minutes, $fallback = null): ?float
    {
        if (($hours === null || $hours === '') && ($minutes === null || $minutes === '')) {
            return ($fallback !== null && $fallback !== '') ? round((float) $fallback, 2) : null;
        }
        $total = ((int) ($hours ?? 0)) * 60 + ((int) ($minutes ?? 0));

        return $total > 0 ? round($total / 60, 2) : null;
    }

    /**
     * Human label like "1h 30m", "45m" or "2h" (null when unset).
     */
    public function estimatedLabel(): ?string
    {
        if ($this->estimated_hours === null) {
            return null;
        }
        [$h, $m] = static::splitHours($this->estimated_hours);
        $h = (int) $h;
        $m = (int) $m;
        if ($h > 0 && $m > 0) {
            return "{$h}h {$m}m";
        }
        if ($h > 0) {
            return "{$h}h";
        }
        if ($m > 0) {
            return "{$m}m";
        }

        return null;
    }

    /**
     * Manual weight adjusted by time spent when auto_weight is on:
     * manual × (1 + actual/estimated), or manual × (1 + actual/10h).
     */
    public function effectiveWeight(): float
    {
        $manual = max(0.0, (float) ($this->weight ?? 1));
        if (! $this->auto_weight) {
            return $manual;
        }

        $hours = $this->ownTimeSeconds() / 3600;
        if ($hours <= 0) {
            return $manual;
        }

        $estimated = (float) ($this->estimated_hours ?? 0);
        $factor = $estimated > 0 ? $hours / $estimated : $hours / 10;

        return round($manual * (1 + $factor), 2);
    }

    public function ownTimeSeconds(): int
    {
        if ($this->relationLoaded('timeEntries')) {
            $done = (int) $this->timeEntries->where('status', TimeEntry::STATUS_STOPPED)->sum('duration_seconds');
            $active = $this->timeEntries
                ->whereIn('status', [TimeEntry::STATUS_RUNNING, TimeEntry::STATUS_PAUSED])
                ->sum(fn ($e) => $e->elapsedSeconds());

            return $done + $active;
        }

        $done = (int) $this->timeEntries()
            ->where('status', TimeEntry::STATUS_STOPPED)
            ->sum('duration_seconds');
        $active = $this->timeEntries()
            ->whereIn('status', [TimeEntry::STATUS_RUNNING, TimeEntry::STATUS_PAUSED])
            ->get()->sum(fn ($e) => $e->elapsedSeconds());

        return $done + $active;
    }

    public function totalTimeSeconds(): int
    {
        $ids = array_merge([$this->id], $this->descendantTaskIds());

        if (count($ids) === 1 && $this->relationLoaded('timeEntries')) {
            return $this->ownTimeSeconds();
        }

        $done = (int) TimeEntry::whereIn('task_id', $ids)
            ->where('status', TimeEntry::STATUS_STOPPED)
            ->sum('duration_seconds');
        $active = TimeEntry::whereIn('task_id', $ids)
            ->whereIn('status', [TimeEntry::STATUS_RUNNING, TimeEntry::STATUS_PAUSED])
            ->get()->sum(fn ($e) => $e->elapsedSeconds());

        return $done + $active;
    }

    private function descendantTaskIds(): array
    {
        // Prefer already-loaded recursive relations (no queries).
        if ($this->relationLoaded('childrenRecursive')) {
            return $this->collectDescendantIds($this->childrenRecursive);
        }
        if ($this->relationLoaded('children')) {
            return $this->collectDescendantIds($this->children);
        }

        $ids = [];
        $stack = $this->children()->pluck('id')->all();
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

    private function collectDescendantIds($children): array
    {
        $ids = [];
        foreach ($children as $child) {
            $ids[] = $child->id;
            if ($child instanceof self) {
                if ($child->relationLoaded('childrenRecursive')) {
                    $ids = array_merge($ids, $this->collectDescendantIds($child->childrenRecursive));
                } elseif ($child->relationLoaded('children')) {
                    $ids = array_merge($ids, $this->collectDescendantIds($child->children));
                }
            }
        }

        return $ids;
    }

    public function aggregateWeight(): float
    {
        if ($this->children->isEmpty()) {
            return $this->effectiveWeight();
        }

        $weight = 0.0;
        foreach ($this->children as $child) {
            $weight += $child->aggregateWeight();
        }

        return $weight;
    }

    /**
     * Progress (0-100): weighted average of subtasks, or own status if leaf.
     */
    public function progressPercent(int $depth = 0): float
    {
        if ($depth > 50 || $this->children->isEmpty()) {
            return static::statusProgress($this->status);
        }

        $total = 0.0;
        $weighted = 0.0;
        foreach ($this->children as $child) {
            $w = $child->aggregateWeight();
            if ($w <= 0) {
                continue;
            }
            $total += $w;
            $weighted += $w * $child->progressPercent($depth + 1);
        }

        return $total > 0 ? round($weighted / $total, 1) : static::statusProgress($this->status);
    }

    public function getStatusColorAttribute()
    {
        switch ($this->status) {
            case 'to_do':
                return 'primary';
            case 'in_progress':
                return 'warning';
            case 'on_hold':
                return 'secondary';
            case 'in_review':
                return 'info';
            case 'completed':
                return 'success';
            default:
                return 'secondary';
        }
    }

    public function checklistItems()
    {
        return $this->hasMany(ChecklistItem::class);
    }
}
