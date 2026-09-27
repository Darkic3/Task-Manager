<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\WorkoutPlan;
use Illuminate\Support\Facades\DB;

class WorkoutPlanService
{
    public function save(array $data, int $userId, ?WorkoutPlan $plan = null): WorkoutPlan
    {
        return DB::transaction(function () use ($data, $userId, $plan): WorkoutPlan {
            if ($plan) {
                $plan->update(collect($data)->except(['days', 'rules'])->all());
                $plan->days()->delete();
                $plan->rules()->delete();
            } else {
                $plan = WorkoutPlan::create(collect($data)->except(['days', 'rules'])->merge(['user_id' => $userId])->all());
            }

            foreach ($data['rules'] ?? [] as $order => $rule) {
                $plan->rules()->create(['rule_text' => $rule, 'sort_order' => $order]);
            }

            $allowedExerciseIds = Exercise::forUser($userId)->pluck('id')->all();
            foreach ($data['days'] as $dayOrder => $dayData) {
                $day = $plan->days()->create([
                    'weekday' => $dayData['weekday'],
                    'title' => $dayData['title'],
                    'type' => $dayData['type'],
                    'notes' => $dayData['notes'] ?? null,
                    'sort_order' => $dayOrder,
                ]);

                foreach ($dayData['exercises'] ?? [] as $exerciseOrder => $exerciseData) {
                    abort_unless(in_array((int) $exerciseData['exercise_id'], $allowedExerciseIds, true), 403);
                    $day->exercises()->create(collect($exerciseData)->except(['exercise_id'])->merge([
                        'exercise_id' => (int) $exerciseData['exercise_id'],
                        'sort_order' => $exerciseOrder,
                    ])->all());
                }
            }

            return $plan->fresh(['days.exercises.exercise', 'rules']);
        });
    }

    public function cloneCycle(WorkoutPlan $source): WorkoutPlan
    {
        return DB::transaction(function () use ($source): WorkoutPlan {
            $copy = $source->replicate(['deleted_at']);
            $copy->parent_id = $source->id;
            $copy->cycle_no = ((int) $source->cycle_no) + 1;
            $copy->week_number = $source->week_number ? ((int) $source->week_number) + 1 : null;
            $copy->start_date = $source->start_date?->copy()->addWeek();
            $copy->status = 'draft';
            $copy->title = preg_replace('/\s+\(Cycle \d+\)$/', '', $source->title).' (Cycle '.$copy->cycle_no.')';
            $copy->save();

            foreach ($source->rules()->get() as $rule) {
                $copy->rules()->create([
                    'rule_text' => $rule->rule_text,
                    'sort_order' => $rule->sort_order,
                ]);
            }

            foreach ($source->days()->with('exercises')->get() as $day) {
                $newDay = $copy->days()->create([
                    'weekday' => $day->weekday,
                    'title' => $day->title,
                    'type' => $day->type,
                    'notes' => $day->notes,
                    'sort_order' => $day->sort_order,
                ]);

                foreach ($day->exercises as $exercise) {
                    $newDay->exercises()->create($exercise->only([
                        'exercise_id', 'section', 'sort_order', 'target_sets', 'rep_min',
                        'rep_max', 'duration_seconds', 'target_weight', 'target_rir',
                        'rest_seconds', 'tempo', 'side_mode', 'is_amrap', 'is_circuit',
                        'circuit_rounds', 'circuit_rest_seconds', 'alternatives', 'notes',
                    ]));
                }
            }

            return $copy->fresh(['days.exercises.exercise', 'rules']);
        });
    }
}
