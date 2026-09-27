<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutSetLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_exercise_log_id', 'set_number', 'reps', 'weight', 'duration_seconds',
        'rir', 'form_rating', 'pain_level', 'completed', 'note',
    ];

    protected $casts = [
        'reps' => 'decimal:2', 'weight' => 'decimal:2', 'rir' => 'decimal:1',
        'completed' => 'boolean',
    ];

    public function exerciseLog(): BelongsTo
    {
        return $this->belongsTo(WorkoutExerciseLog::class, 'workout_exercise_log_id');
    }
}
