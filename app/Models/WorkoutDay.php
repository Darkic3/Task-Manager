<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutDay extends Model
{
    use HasFactory;

    protected $fillable = ['workout_plan_id', 'weekday', 'title', 'type', 'notes', 'sort_order'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class, 'workout_plan_id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class)->with('exercise')->orderBy('sort_order');
    }

    public function isTraining(): bool
    {
        return $this->type === 'training';
    }
}
