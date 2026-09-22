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
     * Manual weight; time-based factor arrives with time tracking (phase 2).
     */
    public function effectiveWeight(): float
    {
        return max(0.0, (float) ($this->weight ?? 1));
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
