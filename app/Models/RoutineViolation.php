<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineViolation extends Model
{
    use HasFactory;

    protected $fillable = [
        'routine_id',
        'checklist_item_id',
        'user_id',
        'occurred_at',
        'occurred_date',
        'quantity',
        'mood',
        'location',
        'trigger',
        'note',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'occurred_date' => 'date',
        'quantity' => 'integer',
        'mood' => 'integer',
    ];

    public static function dateKey($date): string
    {
        return $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RoutineChecklistItem::class, 'checklist_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
