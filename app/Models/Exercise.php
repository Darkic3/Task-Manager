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
}
