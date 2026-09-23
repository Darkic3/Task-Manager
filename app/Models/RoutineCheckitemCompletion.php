<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineCheckitemCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_item_id',
        'user_id',
        'completed_date',
        'completed_at',
    ];

    protected $casts = [
        'completed_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(RoutineChecklistItem::class, 'checklist_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function dateKey($date): string
    {
        return $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();
    }
}
