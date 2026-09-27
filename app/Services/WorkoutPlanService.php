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
}
