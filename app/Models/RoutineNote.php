<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineNote extends Model
{
    use HasFactory;

    public const KIND_CRAVING = 'craving';
    public const KIND_NOTE = 'note';

    public const KINDS = [self::KIND_CRAVING, self::KIND_NOTE];

    protected $fillable = [
        'routine_id',
        'checklist_item_id',
        'user_id',
        'kind',
        'mood',
        'location',
        'trigger',
        'occurred_at',
        'note',
    ];

    public const MOOD_MIN = 1;
    public const MOOD_MAX = 10;

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

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
