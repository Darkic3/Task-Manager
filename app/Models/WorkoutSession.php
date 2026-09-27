<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutSession extends Model
{
    use HasFactory;

    public const IN_PROGRESS = 'in_progress';

    public const COMPLETED = 'completed';

    public const SKIPPED = 'skipped';

    protected $fillable = [
        'user_id', 'workout_day_id', 'workout_date', 'status', 'started_at',
        'ended_at', 'session_note', 'pain_note',
    ];

    protected $casts = [
        'workout_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(WorkoutDay::class, 'workout_day_id');
    }

    public function exerciseLogs(): HasMany
    {
        return $this->hasMany(WorkoutExerciseLog::class);
    }

    public function durationMinutes(): ?int
    {
        if (! $this->started_at || ! $this->ended_at) {
            return null;
        }

        return (int) round($this->started_at->diffInMinutes($this->ended_at));
    }
}
