<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Law extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ARCHIVED = 'archived';

    /** Virtual status for active laws whose end date has passed (no renewal). */
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_PAUSED, self::STATUS_ARCHIVED];

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'icon',
        'color',
        'coefficient',
        'starts_on',
        'ends_on',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'coefficient' => 'integer',
        'sort_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(LawLink::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LawEvent::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Whether the law window has passed. Expired laws are read-only history:
     * they never renew — the user creates a new law instead.
     */
    public function isExpired(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        return $this->ends_on !== null
            && Carbon::parse($this->ends_on)->startOfDay()->lt(today());
    }

    /**
     * Effective status for tabs and badges: active|paused|expired|archived.
     */
    public function effectiveStatus(): string
    {
        if ($this->status === self::STATUS_ACTIVE && $this->isExpired()) {
            return self::STATUS_EXPIRED;
        }

        return $this->status;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * Whether new events may be recorded (only live, active laws).
     */
    public function acceptsEvents(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }

    /**
     * Human-readable window label, localized via the lang files.
     */
    public function windowLabel(): string
    {
        $from = $this->starts_on ? app_date($this->starts_on) : null;

        if ($this->ends_on === null) {
            return $from ? __('From :date (ongoing)', ['date' => $from]) : __('Ongoing');
        }

        return __(':from – :to', ['from' => $from, 'to' => app_date($this->ends_on)]);
    }

    /**
     * Aggregate XP stats from the loaded `events` relation when available.
     *
     * @return array{total:int, plus:int, minus:int}
     */
    public function xpStats(): array
    {
        $events = $this->relationLoaded('events') ? $this->events : $this->events()->get();

        return [
            'total' => (int) $events->sum('xp_delta'),
            'plus' => $events->where('direction', 1)->count(),
            'minus' => $events->where('direction', -1)->count(),
        ];
    }
}
