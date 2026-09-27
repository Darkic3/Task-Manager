<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutImport extends Model
{
    use HasFactory;

    public const PREVIEW = 'preview';

    public const CONFIRMED = 'confirmed';

    public const FAILED = 'failed';

    protected $fillable = ['user_id', 'source_text', 'structure', 'status', 'workout_plan_id', 'expires_at'];

    protected $casts = ['structure' => 'array', 'expires_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class, 'workout_plan_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->greaterThan($this->expires_at);
    }
}
