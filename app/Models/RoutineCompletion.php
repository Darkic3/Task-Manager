<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineCompletion extends Model
{
    use HasFactory;

    public const STATUS_DONE = 'done';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_REST = 'rest';

    public const SKIP_REASONS = [
        'no_time' => 'No time',
        'low_energy' => 'Low energy',
        'off_plan' => 'Off plan',
    ];

    /**
     * Internal marker (stored in skip_reason) for routine skips created
     * automatically because every step was skipped — never shown as a
     * reason, and only auto rows are auto-removed when a step reopens.
     */
    public const AUTO_ALL_STEPS = 'auto_all_steps';

    protected $fillable = [
        'user_id',
        'routine_id',
        'completed_date',
        'completed_at',
        'status',
        'skip_reason',
    ];

    protected $casts = [
        'completed_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public static function dateKey($date): string
    {
        return $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();
    }
}
