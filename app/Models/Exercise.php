<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exercise extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'normalized_name', 'aliases', 'category',
        'muscle_groups', 'equipment', 'instructions',
    ];

    protected $casts = [
        'aliases' => 'array',
        'muscle_groups' => 'array',
        'equipment' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workoutExercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($query) use ($userId): void {
            $query->whereNull('user_id')->orWhere('user_id', $userId);
        });
    }

    public function getUsageCountAttribute(): int
    {
        return (int) ($this->workout_exercises_count ?? $this->workoutExercises()->count());
    }

    /**
     * Identity keys used to detect duplicate movements. Case, Persian/Arabic
     * letter variants, punctuation, separators and spacing all normalise away,
     * so "Pull-up", "pull up" and "Pullup" resolve to the same movement.
     *
     * @return array<int, string>
     */
    public static function identityKeys(string $value): array
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace(
            ['ي', 'ك', 'ة', 'ۀ', 'أ', 'إ', 'آ', 'ؤ', 'ئ', "\u{200C}", "\u{200D}", "\u{200E}", "\u{200F}"],
            ['ی', 'ک', 'ه', 'ه', 'ا', 'ا', 'ا', 'و', 'ی', ' ', ' ', '', ''],
            $value
        );
        // Arabic/Persian diacritics never change a movement identity.
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $value) ?? $value;
        $spaced = preg_replace('/[\p{P}\p{S}\s]+/u', ' ', $value) ?? $value;
        $spaced = trim(preg_replace('/\s+/u', ' ', $spaced) ?? $spaced);

        if ($spaced === '') {
            return [];
        }

        return array_values(array_unique([$spaced, str_replace(' ', '', $spaced)]));
    }

    /**
     * Every identity key this movement answers to (its name plus aliases).
     *
     * @return array<int, string>
     */
    public function identityKeysFor(): array
    {
        $keys = [];
        foreach (array_merge([$this->name], (array) ($this->aliases ?? [])) as $label) {
            foreach (static::identityKeys((string) $label) as $key) {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * The user's (or the shared library's) movement that already answers to
     * this name or any of these aliases — archived records included, so a
     * re-added movement is restored instead of duplicated. Null when new.
     *
     * @param  array<int, string>  $aliases
     */
    public static function findDuplicate(int $userId, string $name, array $aliases = [], ?int $ignoreId = null): ?self
    {
        $needles = [];
        foreach (array_merge([$name], array_values($aliases)) as $label) {
            foreach (static::identityKeys((string) $label) as $key) {
                $needles[$key] = true;
            }
        }

        if ($needles === []) {
            return null;
        }

        $needles = array_keys($needles);
        $candidates = static::withTrashed()
            ->forUser($userId)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get(['id', 'user_id', 'name', 'normalized_name', 'aliases', 'deleted_at']);

        foreach ($candidates as $candidate) {
            if (array_intersect($needles, $candidate->identityKeysFor()) !== []) {
                return $candidate;
            }
        }

        return null;
    }
}
