<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutineChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'routine_id',
        'user_id',
        'name',
        'sort_order',
        'target_sets',
        'unit',
    ];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(RoutineCheckitemCompletion::class, 'checklist_item_id');
    }

    public function completedOn($date): bool
    {
        $key = RoutineCheckitemCompletion::dateKey($date);

        if ($this->relationLoaded('completions')) {
            return $this->completions->contains(function ($c) use ($key) {
                $value = $c->completed_date ?? null;
                if ($value instanceof \Carbon\Carbon) {
                    return $value->toDateString() === $key;
                }
                if (is_string($value) && strlen($value) >= 10) {
                    return substr($value, 0, 10) === $key;
                }

                return false;
            });
        }

        return $this->completions()
            ->where('completed_date', $key)
            ->exists();
    }

    /**
     * Idempotent per-date toggle, mirroring Routine::toggleOn().
     */
    public function toggleOn($date): bool
    {
        $key = RoutineCheckitemCompletion::dateKey($date);

        $existing = $this->completions()->where('completed_date', $key)->get();

        if ($existing->isNotEmpty()) {
            $existing->each->delete();

            return false;
        }

        $this->completions()->create([
            'user_id' => $this->user_id,
            'completed_date' => $key,
            'completed_at' => now(),
        ]);

        return true;
    }
}
