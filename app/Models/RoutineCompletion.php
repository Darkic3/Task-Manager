<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'routine_id',
        'completed_date',
    ];

    protected $casts = [
        'completed_date' => 'date',
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
