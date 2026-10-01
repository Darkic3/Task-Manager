<?php

namespace App\Models;

use Carbon\Carbon;
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
        'ended_at', 'duration_seconds', 'session_note', 'pain_note',
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

    public function isRunning(): bool
    {
        return $this->started_at !== null && $this->ended_at === null;
    }

    /**
     * Elapsed seconds so far. Uses the frozen duration_seconds once finished,
     * otherwise measures live against started_at.
     */
    public function elapsedSeconds(?Carbon $now = null): int
    {
        if (! $this->started_at) {
            return 0;
        }

        if ($this->ended_at) {
            if ($this->duration_seconds !== null) {
                return (int) $this->duration_seconds;
            }

            return (int) max(0, $this->ended_at->diffInSeconds($this->started_at, true));
        }

        return (int) max(0, ($now ?? now())->diffInSeconds($this->started_at, true));
    }

    public function durationMinutes(): ?int
    {
        if (! $this->started_at || ! $this->ended_at) {
            return null;
        }

        return (int) round($this->started_at->diffInMinutes($this->ended_at));
    }

    public static function formatDuration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0
            ? sprintf('%d:%02d:%02d', $h, $m, $s)
            : sprintf('%02d:%02d', $m, $s);
    }
}
