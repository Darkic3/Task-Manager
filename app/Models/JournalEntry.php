<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'user_id',
        'book_id',
        'title',
        'content',
        'entry_date',
        'mood_score',
        'energy',
        'sleep_hours',
        'sleep_quality',
        'weather_condition',
        'weather_temp',
        'location_name',
        'location_lat',
        'location_lng',
        'word_count',
        'is_pinned',
        'is_favorite',
        'status',
        'edited_at',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'mood_score' => 'integer',
        'energy' => 'integer',
        'sleep_hours' => 'decimal:2',
        'sleep_quality' => 'integer',
        'weather_temp' => 'decimal:2',
        'location_lat' => 'decimal:7',
        'location_lng' => 'decimal:7',
        'word_count' => 'integer',
        'is_pinned' => 'boolean',
        'is_favorite' => 'boolean',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(JournalBook::class, 'book_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(JournalTag::class, 'journal_entry_tag', 'journal_entry_id', 'journal_tag_id');
    }

    public function moods(): BelongsToMany
    {
        return $this->belongsToMany(JournalMood::class, 'journal_entry_mood', 'journal_entry_id', 'journal_mood_id')
            ->withPivot(['intensity', 'note']);
    }

    public function links(): HasMany
    {
        return $this->hasMany(JournalEntryLink::class, 'journal_entry_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(JournalEntryRevision::class, 'journal_entry_id');
    }

    public function scopeOfUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_ARCHIVED);
    }

    public function scopeOnDate(Builder $query, $date): Builder
    {
        return $query->whereDate('entry_date', $date);
    }

    public function scopeBetween(Builder $query, $from, $to): Builder
    {
        if ($from) {
            $query->whereDate('entry_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('entry_date', '<=', $to);
        }

        return $query;
    }

    public function scopeInBook(Builder $query, ?int $bookId): Builder
    {
        if ($bookId === null) {
            return $query->whereNull('book_id');
        }

        return $query->where('book_id', $bookId);
    }

    public function scopeWithTag(Builder $query, int $tagId): Builder
    {
        return $query->whereHas('tags', fn ($q) => $q->where('journal_tags.id', $tagId));
    }

    public function scopeWithMood(Builder $query, int $moodId): Builder
    {
        return $query->whereHas('moods', fn ($q) => $q->where('journal_moods.id', $moodId));
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);
        if ($search === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';

        return $query->where(function ($q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('content', 'like', $like)
                ->orWhere('location_name', 'like', $like);
        });
    }

    public function scopeChronological(Builder $query, string $direction = 'desc'): Builder
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return $query->orderBy('entry_date', $direction)->orderBy('id', $direction);
    }
}
