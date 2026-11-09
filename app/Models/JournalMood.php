<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class JournalMood extends Model
{
    use HasFactory;

    public const CATEGORY_POSITIVE = 'positive';

    public const CATEGORY_NEGATIVE = 'negative';

    public const CATEGORY_NEUTRAL = 'neutral';

    public const CATEGORIES = [
        self::CATEGORY_POSITIVE,
        self::CATEGORY_NEGATIVE,
        self::CATEGORY_NEUTRAL,
    ];

    protected $fillable = [
        'user_id',
        'name',
        'color',
        'icon',
        'category',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(JournalEntry::class, 'journal_entry_mood', 'journal_mood_id', 'journal_entry_id')
            ->withPivot(['intensity', 'note']);
    }
}
