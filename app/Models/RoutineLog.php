<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'routine_id',
        'checklist_item_id',
        'completed_date',
        'set_no',
        'value',
    ];

    protected $casts = [
        'completed_date' => 'date',
        'value' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(RoutineChecklistItem::class, 'checklist_item_id');
    }

    public static function dateKey($date): string
    {
        return $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();
    }

    /**
     * Idempotent per-day/per-set write: re-logging the same set updates it.
     */
    public static function logValue($userId, $routineId, $date, $value, ?int $itemId = null, int $setNo = 1): self
    {
        return static::updateOrCreate(
            [
                'routine_id' => $routineId,
                'checklist_item_id' => $itemId,
                'completed_date' => static::dateKey($date),
                'set_no' => $setNo,
            ],
            ['user_id' => $userId, 'value' => $value]
        );
    }
}
