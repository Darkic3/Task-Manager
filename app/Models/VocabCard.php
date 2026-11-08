<?php

namespace App\Models;

use App\Services\Vocabulary\LeitnerScheduler;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VocabCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'deck_id',
        'front',
        'back',
        'example',
        'note',
        'box',
        'next_review_at',
        'last_reviewed_at',
        'review_count',
        'success_count',
        'is_mastered',
    ];

    protected $casts = [
        'box' => 'integer',
        'next_review_at' => 'date',
        'last_reviewed_at' => 'datetime',
        'review_count' => 'integer',
        'success_count' => 'integer',
        'is_mastered' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deck()
    {
        return $this->belongsTo(VocabDeck::class, 'deck_id');
    }

    public function reviews()
    {
        return $this->hasMany(VocabReview::class, 'vocab_card_id');
    }

    /**
     * Identity key used to spot duplicate words. Case, Persian/Arabic letter
     * variants, diacritics, zero-width joiners, punctuation and spacing all
     * normalise away, so "Apple", "apple." and "apple" are the same word.
     */
    public static function normalizeFront(string $front): string
    {
        $value = mb_strtolower(trim($front), 'UTF-8');
        $value = str_replace(
            ['ي', 'ى', 'ك', 'ة', 'ۀ', 'أ', 'إ', 'آ', 'ؤ', 'ئ', "\u{200C}", "\u{200D}", "\u{0640}"],
            ['ی', 'ی', 'ک', 'ه', 'ه', 'ا', 'ا', 'ا', 'و', 'ی', ' ', ' ', ' '],
            $value
        );
        // Arabic/Persian diacritics never change which word this is.
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $value) ?? $value;
        $value = preg_replace('/[\p{P}\p{S}\s]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    public function duplicateKey(): string
    {
        return static::normalizeFront((string) $this->front);
    }

    /**
     * An existing card in the same deck that already means this word.
     * Duplicates are per deck: the same word may live in several decks.
     */
    public static function findDuplicateInDeck(int $userId, int $deckId, string $front, ?int $ignoreId = null): ?self
    {
        $needle = static::normalizeFront($front);

        if ($needle === '') {
            return null;
        }

        return static::query()
            ->where('user_id', $userId)
            ->where('deck_id', $deckId)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get(['id', 'front'])
            ->first(fn (self $card) => $card->duplicateKey() === $needle);
    }

    public function getIsDueAttribute(): bool
    {
        if ($this->next_review_at === null) {
            return true;
        }

        return $this->next_review_at->isToday() || $this->next_review_at->isPast();
    }

    public function getSuccessRateAttribute(): ?float
    {
        if ($this->review_count === 0) {
            return null;
        }

        return round(($this->success_count / $this->review_count) * 100, 1);
    }

    // Scopes
    public function scopeDue($query)
    {
        return $query->whereDate('next_review_at', '<=', today());
    }

    public function scopeByBox($query, int $box)
    {
        return $query->where('box', $box);
    }

    public function scopeByDeck($query, int $deckId)
    {
        return $query->where('deck_id', $deckId);
    }

    public function scopeMastered($query)
    {
        return $query->where('is_mastered', true);
    }

    public function scopeLearning($query)
    {
        return $query->where('is_mastered', false);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('front', 'like', "%{$search}%")
                ->orWhere('back', 'like', "%{$search}%");
        });
    }

    /**
     * Apply a Leitner review result to this card.
     */
    public function markResult(bool $remembered): void
    {
        $resolved = LeitnerScheduler::resolve($remembered, (int) $this->box);

        $this->update([
            'box' => $resolved['box'],
            'next_review_at' => $resolved['next_review_at'],
            'last_reviewed_at' => now(),
            'review_count' => $this->review_count + 1,
            'success_count' => $this->success_count + ($remembered ? 1 : 0),
            'is_mastered' => $resolved['is_mastered'],
        ]);
    }

    /**
     * Send the card back to Box 1 (e.g. after editing the word/meaning).
     */
    public function resetProgress(): void
    {
        $initial = LeitnerScheduler::initialState();

        $this->update([
            'box' => $initial['box'],
            'next_review_at' => $initial['next_review_at'],
            'last_reviewed_at' => null,
            'review_count' => 0,
            'success_count' => 0,
            'is_mastered' => false,
        ]);
    }
}
