<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutExerciseLog extends Model
{
    use HasFactory;

    protected $fillable = ['workout_session_id', 'workout_exercise_id', 'exercise_id', 'exercise_name', 'target_label', 'completed', 'skip_reason', 'note'];

    protected $casts = ['completed' => 'boolean'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    public function workoutExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutExercise::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /**
     * Library exercise for this log: live template first, then the
     * direct link, so detached history still resolves.
     */
    public function resolvedExercise(): ?Exercise
    {
        return $this->workoutExercise?->exercise ?? $this->exercise;
    }

    /**
     * Human-readable movement name that survives template deletion.
     */
    public function displayExerciseName(): string
    {
        return $this->workoutExercise?->exercise?->name
            ?? $this->exercise?->name
            ?? $this->exercise_name
            ?? __('Exercise');
    }

    /**
     * Planned target label for this log (snapshot-aware).
     */
    public function displayTargetLabel(): ?string
    {
        return $this->workoutExercise?->targetLabel() ?? $this->target_label;
    }

    public function setLogs(): HasMany
    {
        return $this->hasMany(WorkoutSetLog::class)->orderBy('set_number');
    }
}
