<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutExercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_day_id', 'exercise_id', 'section', 'sort_order', 'target_sets',
        'rep_min', 'rep_max', 'duration_seconds', 'target_weight', 'target_rir',
        'rest_seconds', 'tempo', 'side_mode', 'is_amrap', 'is_circuit',
        'circuit_rounds', 'circuit_rest_seconds', 'alternatives', 'notes',
    ];

    protected $casts = [
        'target_weight' => 'decimal:2', 'target_rir' => 'decimal:1',
        'is_amrap' => 'boolean', 'is_circuit' => 'boolean', 'alternatives' => 'array',
    ];

    public function day(): BelongsTo
    {
        return $this->belongsTo(WorkoutDay::class, 'workout_day_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function targetLabel(): string
    {
        if ($this->is_circuit) {
            $rounds = $this->circuit_rounds ?: ($this->target_sets ?: 1);

            return "Circuit × {$rounds}";
        }
        if ($this->is_amrap) {
            return ($this->target_sets ?: 1).' × AMRAP';
        }
        if ($this->duration_seconds) {
            return ($this->target_sets ?: 1).' × '.gmdate('i:s', $this->duration_seconds);
        }
        if ($this->target_sets && ($this->rep_min || $this->rep_max)) {
            $reps = $this->rep_min === $this->rep_max ? (string) $this->rep_min : "{$this->rep_min}–{$this->rep_max}";

            return "{$this->target_sets} × {$reps}";
        }

        return $this->target_sets ? "{$this->target_sets} sets" : 'Open target';
    }

    public function requiredSetCount(): int
    {
        return max(1, (int) ($this->is_circuit ? ($this->circuit_rounds ?: $this->target_sets) : $this->target_sets));
    }
}
