<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkoutPlan extends Model
{
    use HasFactory, SoftDeletes;

    public const WEEKDAYS = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    protected $fillable = [
        'user_id', 'parent_id', 'title', 'week_number', 'cycle_no', 'goal',
        'description', 'start_date', 'status',
    ];

    protected $casts = ['start_date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(WorkoutDay::class)->orderBy('sort_order');
    }

    /**
     * Days in execution order: when the plan has a start_date, DAY 1 is the
     * start date's weekday (e.g. Sunday), so a week starting Sunday shows
     * Sunday first instead of Saturday (day 7). Without a start_date the
     * stored Saturday-first order is kept.
     */
    public function orderedDays()
    {
        $days = $this->days;
        if (! $this->start_date) {
            return $days->values();
        }

        $order = array_flip(self::WEEKDAYS);
        $startIdx = $order[strtolower($this->start_date->format('l'))] ?? 0;

        return $days
            ->sortBy(fn ($day) => (($order[$day->weekday] ?? 0) - $startIdx + 7) % 7)
            ->values();
    }

    public function rules(): HasMany
    {
        return $this->hasMany(WorkoutRule::class)->orderBy('sort_order');
    }

    public function sessions(): HasManyThrough
    {
        return $this->hasManyThrough(WorkoutSession::class, WorkoutDay::class);
    }
}
