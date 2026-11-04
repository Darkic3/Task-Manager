<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LawEvent extends Model
{
    use HasFactory;

    public const DIRECTION_FOR = 1;

    public const DIRECTION_AGAINST = -1;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_AUTO = 'auto';

    protected $fillable = [
        'law_id',
        'user_id',
        'occurred_on',
        'direction',
        'xp_delta',
        'source',
        'linkable_type',
        'linkable_id',
        'note',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'direction' => 'integer',
        'xp_delta' => 'integer',
    ];

    public static function dateKey($date): string
    {
        return $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();
    }

    public function law(): BelongsTo
    {
        return $this->belongsTo(Law::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isFor(): bool
    {
        return (int) $this->direction === self::DIRECTION_FOR;
    }
}
