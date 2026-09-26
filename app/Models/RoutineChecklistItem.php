<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutineChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'routine_id',
        'user_id',
        'name',
        'sort_order',
        'target_sets',
        'unit',
        'time_period',
        'scheduled_time',
    ];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(RoutineCheckitemCompletion::class, 'checklist_item_id');
    }

    public function completedOn($date): bool
    {
        $key = RoutineCheckitemCompletion::dateKey($date);

        if ($this->relationLoaded('completions')) {
            return $this->completions->contains(function ($c) use ($key) {
                $value = $c->completed_date ?? null;
                if ($value instanceof \Carbon\Carbon) {
                    return $value->toDateString() === $key;
                }
                if (is_string($value) && strlen($value) >= 10) {
                    return substr($value, 0, 10) === $key;
                }

                return false;
            });
        }

        return $this->completions()
            ->where('completed_date', $key)
            ->exists();
    }

    /**
     * Idempotent per-date toggle, mirroring Routine::toggleOn().
     */
    public function toggleOn($date): bool
    {
        $key = RoutineCheckitemCompletion::dateKey($date);

        $existing = $this->completions()->where('completed_date', $key)->get();

        if ($existing->isNotEmpty()) {
            $existing->each->delete();

            return false;
        }

        $this->completions()->create([
            'user_id' => $this->user_id,
            'completed_date' => $key,
            'completed_at' => now(),
        ]);

        return true;
    }

    /**
     * Human label for the assigned time-of-day period, if any.
     */
    public function periodLabel(): ?string
    {
        if (! $this->time_period) {
            return null;
        }

        return config("routines.periods.{$this->time_period}.label") ?? ucfirst((string) $this->time_period);
    }

    public function periodIcon(): ?string
    {
        if (! $this->time_period) {
            return null;
        }

        return config("routines.periods.{$this->time_period}.icon");
    }

    public function periodColor(): ?string
    {
        if (! $this->time_period) {
            return null;
        }

        return config("routines.periods.{$this->time_period}.color");
    }

    /**
     * Exact planned time formatted for display (null when unset).
     */
    public function scheduledTimeLabel(): ?string
    {
        if (! $this->scheduled_time) {
            return null;
        }

        try {
            return Carbon::parse($this->scheduled_time)->format('g:i A');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function hasSchedule(): bool
    {
        return $this->time_period !== null || $this->scheduled_time !== null;
    }

    /**
     * Short label for the assigned schedule: period label or exact time.
     */
    public function scheduleLabel(): ?string
    {
        return $this->periodLabel() ?: $this->scheduledTimeLabel();
    }

    public function scheduleIcon(): ?string
    {
        return $this->periodIcon() ?: ($this->scheduled_time ? 'bi-clock' : null);
    }

    public function scheduleColor(): ?string
    {
        return $this->periodColor() ?: ($this->scheduled_time ? '#64748b' : null);
    }

    /**
     * Ordering key: exact times first, then periods, then unscheduled.
     * Letter prefixes (a/b/z) keep comparisons byte-wise — numeric-looking
     * prefixes would make PHP compare some keys as numbers.
     */
    public function sortKey(): string
    {
        if ($this->scheduled_time) {
            return 'a'.substr((string) $this->scheduled_time, 0, 5);
        }

        if ($this->time_period) {
            $order = (int) config("routines.periods.{$this->time_period}.order", 99);

            return 'b'.str_pad((string) $order, 2, '0', STR_PAD_LEFT);
        }

        return 'z';
    }
}
