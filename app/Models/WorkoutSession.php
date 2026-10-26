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
        'user_id', 'workout_plan_id', 'workout_day_id', 'plan_title', 'day_title',
        'weekday', 'workout_date', 'status', 'started_at',
        'ended_at', 'duration_seconds', 'paused_seconds', 'paused_at',
        'session_note', 'pain_note',
        'bodyweight_kg', 'bodyweight_recorded_at',
    ];

    protected $casts = [
        'workout_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'paused_at' => 'datetime',
        'bodyweight_recorded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(WorkoutDay::class, 'workout_day_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class, 'workout_plan_id');
    }

    /**
     * Display title of the trained day. Prefers the live template row,
     * falls back to the snapshot taken when the session started so
     * history stays readable after the plan is edited or deleted.
     */
    public function displayDayTitle(): string
    {
        return $this->day?->title
            ?? $this->day_title
            ?? ($this->weekday ? ucfirst($this->weekday) : __('Workout'));
    }

    /**
     * Display title of the plan this session belonged to.
     */
    public function displayPlanTitle(): ?string
    {
        return $this->plan?->title
            ?? $this->day?->plan?->title
            ?? $this->plan_title;
    }

    public function exerciseLogs(): HasMany
    {
        return $this->hasMany(WorkoutExerciseLog::class);
    }

    public function isPaused(): bool
    {
        return $this->started_at !== null && $this->ended_at === null && $this->paused_at !== null;
    }

    public function isRunning(): bool
    {
        return $this->started_at !== null && $this->ended_at === null && $this->paused_at === null;
    }

    public function isOpen(): bool
    {
        return $this->started_at !== null && $this->ended_at === null;
    }

    /**
     * Elapsed seconds so far, excluding paused time. Uses the frozen
     * duration_seconds once finished, otherwise measures live against
     * started_at minus accumulated paused_seconds. While paused the clock
     * is frozen at the pause instant so refreshes can't inflate it.
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

            $gross = (int) max(0, $this->ended_at->diffInSeconds($this->started_at, true));

            return (int) max(0, $gross - (int) ($this->paused_seconds ?? 0));
        }

        $anchor = ($this->paused_at && $this->isPaused()) ? $this->paused_at : ($now ?? now());
        $gross = (int) max(0, $anchor->diffInSeconds($this->started_at, true));

        return (int) max(0, $gross - (int) ($this->paused_seconds ?? 0));
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
