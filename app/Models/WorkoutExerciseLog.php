<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutExerciseLog extends Model
{
    use HasFactory;

    protected $fillable = ['workout_session_id', 'workout_exercise_id', 'completed', 'skip_reason', 'note'];

    protected $casts = ['completed' => 'boolean'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    public function workoutExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutExercise::class);
    }

    public function setLogs(): HasMany
    {
        return $this->hasMany(WorkoutSetLog::class)->orderBy('set_number');
    }
}
