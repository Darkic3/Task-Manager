<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LawLink extends Model
{
    use HasFactory;

    /** The linked entity supports the law (counts +). */
    public const EFFECT_FOR = 1;

    /** The linked entity opposes the law (counts −). */
    public const EFFECT_AGAINST = -1;

    protected $fillable = [
        'law_id',
        'linkable_type',
        'linkable_id',
        'effect',
        'is_excluded',
    ];

    protected $casts = [
        'effect' => 'integer',
        'is_excluded' => 'boolean',
    ];

    public function law(): BelongsTo
    {
        return $this->belongsTo(Law::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isFor(): bool
    {
        return (int) $this->effect === self::EFFECT_FOR;
    }
}
