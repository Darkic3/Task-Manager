<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\WorkoutPlan;
use Illuminate\Support\Facades\DB;

class WorkoutPlanService
{
    public function save(array $data, int $userId, ?WorkoutPlan $plan = null): WorkoutPlan
    {
        // The previous start date (update path) decides whether weekday
        // labels follow a start_date change — see reanchorIndex().
        $previousStart = $plan?->start_date;
        if ($previousStart) {
            $previousStart = $previousStart->copy();
        }

        return DB::transaction(function () use ($data, $userId, $plan, $previousStart): WorkoutPlan {
            if ($plan) {
                $plan->update(collect($data)->except(['days', 'rules'])->all());
                $plan->days()->delete();
                $plan->rules()->delete();
            } else {
                $plan = WorkoutPlan::create(collect($data)->except(['days', 'rules'])->merge(['user_id' => $userId])->all());
            }

            $this->enforceSingleActive($plan);

            foreach ($data['rules'] ?? [] as $order => $rule) {
                $plan->rules()->create(['rule_text' => $rule, 'sort_order' => $order]);
            }

            $allowedExerciseIds = Exercise::forUser($userId)->pluck('id')->all();
            // Anchor semantics: on update, when the start date changes AND
            // the submitted days are anchored to the previous start (DAY 1
            // on the old start date), the program order is kept and DAY 1
            // is re-pinned to the new start date. Otherwise the submitted
            // weekday labels are explicit intent (fixed weekly template,
            // fresh import already anchored by the importer) and kept.
            $anchorIdx = $this->reanchorIndex($data['days'] ?? [], $previousStart, $data['start_date'] ?? null);
            foreach ($data['days'] as $dayOrder => $dayData) {
                $day = $plan->days()->create([
                    'weekday' => $anchorIdx !== null
                        ? WorkoutPlan::WEEKDAYS[($anchorIdx + $dayOrder) % 7]
                        : $dayData['weekday'],
                    'title' => $dayData['title'],
                    'type' => $dayData['type'],
                    'notes' => $dayData['notes'] ?? null,
                    'sort_order' => $dayOrder,
                ]);

                foreach ($dayData['exercises'] ?? [] as $exerciseOrder => $exerciseData) {
                    abort_unless(in_array((int) $exerciseData['exercise_id'], $allowedExerciseIds, true), 403);
                    $alternatives = collect($exerciseData['alternatives'] ?? [])
                        ->map(fn ($id) => (int) $id)
                        ->filter(fn (int $id) => in_array($id, $allowedExerciseIds, true))
                        ->values()
                        ->all();
                    $day->exercises()->create(collect($exerciseData)->except(['exercise_id', 'alternatives'])->merge([
                        'exercise_id' => (int) $exerciseData['exercise_id'],
                        'sort_order' => $exerciseOrder,
                        'alternatives' => $alternatives,
                    ])->all());
                }
            }

            return $plan->fresh(['days.exercises.exercise', 'rules']);
        });
    }

    /**
     * Weekday index to re-anchor submitted days to, or null to keep the
     * submitted labels. Re-anchoring happens only on update, only when
     * the start date actually changes, and only when the submitted day
     * order is currently anchored to the previous start (DAY 1 on the
     * old start date) — otherwise the labels are explicit intent.
     */
    private function reanchorIndex(array $days, $previousStart, $newStart): ?int
    {
        $oldIdx = self::startWeekdayIndex($previousStart);
        $newIdx = self::startWeekdayIndex($newStart);
        if ($oldIdx === null || $newIdx === null || $oldIdx === $newIdx) {
            return null;
        }
        foreach (array_values($days) as $position => $day) {
            $expected = WorkoutPlan::WEEKDAYS[($oldIdx + $position) % 7];
            if (strtolower((string) (((array) $day)['weekday'] ?? '')) !== $expected) {
                return null;
            }
        }

        return $newIdx;
    }

    /**
     * Weekday index (0 = saturday) of the given start date, or null when
     * there is no (parseable) start date.
     */
    private static function startWeekdayIndex($startDate): ?int
    {
        if (empty($startDate)) {
            return null;
        }
        try {
            $weekday = strtolower(\Carbon\Carbon::parse($startDate)->format('l'));
        } catch (\Throwable) {
            return null;
        }

        return array_flip(WorkoutPlan::WEEKDAYS)[$weekday] ?? null;
    }

    /**
     * Only one plan per user may be active at a time: activating this
     * plan archives every other active plan of the same user.
     */
    public function enforceSingleActive(WorkoutPlan $plan): void
    {
        if (($plan->status ?? null) !== 'active') {
            return;
        }

        WorkoutPlan::where('user_id', $plan->user_id)
            ->whereKeyNot($plan->id)
            ->where('status', 'active')
            ->update(['status' => 'draft']);
    }

    /**
     * Activate a plan (and deactivate the rest) in one transaction.
     */
    public function activate(WorkoutPlan $plan): WorkoutPlan
    {
        return DB::transaction(function () use ($plan): WorkoutPlan {
            $plan->update(['status' => 'active']);
            $this->enforceSingleActive($plan);

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
