<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'task_id',
        'started_at',
        'ended_at',
        'duration_seconds',
        'paused_seconds',
        'last_pause_at',
        'status',
        'description',
        'category',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_pause_at' => 'datetime',
    ];

    public const STATUS_RUNNING = 'running';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_STOPPED = 'stopped';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_RUNNING, self::STATUS_PAUSED], true);
    }

    /**
     * Live elapsed seconds for a running/paused entry.
     */
    public function elapsedSeconds(?Carbon $now = null): int
    {
        $now = $now ?? now();
        $end = $this->ended_at ?? $now;
        $paused = (int) ($this->paused_seconds ?? 0);

        if ($this->status === self::STATUS_PAUSED && $this->last_pause_at) {
            $paused += $this->last_pause_at->diffInSeconds($now, true);
        }

        return (int) max(0, $end->diffInSeconds($this->started_at, true) - $paused);
    }

    public function pause(): void
    {
        if ($this->status !== self::STATUS_RUNNING) {
            return;
        }
        $this->status = self::STATUS_PAUSED;
        $this->last_pause_at = now();
        $this->save();
    }

    public function resume(): void
    {
        if ($this->status !== self::STATUS_PAUSED) {
            return;
        }
        if ($this->last_pause_at) {
            $this->paused_seconds = (int) $this->paused_seconds + $this->last_pause_at->diffInSeconds(now(), true);
        }
        $this->status = self::STATUS_RUNNING;
        $this->last_pause_at = null;
        $this->save();
    }

    public function stop(): void
    {
        if ($this->status === self::STATUS_PAUSED && $this->last_pause_at) {
            $this->paused_seconds = (int) $this->paused_seconds + $this->last_pause_at->diffInSeconds(now(), true);
            $this->last_pause_at = null;
        }
        $this->status = self::STATUS_STOPPED;
        $this->ended_at = $this->ended_at ?? now();
        $this->duration_seconds = (int) max(0, $this->ended_at->diffInSeconds($this->started_at, true) - (int) $this->paused_seconds);
        $this->save();
    }

    public static function formatDuration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0
            ? sprintf('%dh %02dm', $h, $m)
            : sprintf('%02d:%02d', $m, $s);
    }
}
