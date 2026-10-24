<?php

use App\Models\WorkoutPlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Repair program order for AI-imported plans: the import structure
     * holds the true DAY 1..DAY 7 execution order, but form re-saves
     * rotated sort_order to the display order, so DAY 1 drifted (e.g.
     * Week 14 showed PULL B on the start date instead of PULL A).
     * Going forward WorkoutPlanService::save() derives weekday labels
     * from the start date, keeping DAY 1 pinned to it.
     */
    public function up(): void
    {
        $imports = DB::table('workout_imports')
            ->where('status', 'confirmed')
            ->whereNotNull('workout_plan_id')
            ->get();

        $fixed = 0;
        foreach ($imports as $import) {
            $structure = $import->structure;
            if (is_string($structure)) {
                $structure = json_decode($structure, true);
            }
            $structDays = is_array($structure) ? array_values((array) ($structure['days'] ?? [])) : [];
            if (count($structDays) !== 7) {
                continue;
            }
            $structWeekdays = collect($structDays)
                ->map(fn ($d) => strtolower((string) ((array) $d)['weekday'] ?? ''))
                ->values();
            if ($structWeekdays->unique()->count() !== 7) {
                continue;
            }

            /** @var WorkoutPlan|null $plan */
            $plan = WorkoutPlan::with('days')->find($import->workout_plan_id);
            if (! $plan || $plan->days->count() !== 7) {
                continue;
            }
            $byWeekday = $plan->days->keyBy(fn ($d) => strtolower($d->weekday));
            if ($byWeekday->keys()->sort()->values()->all() !== $structWeekdays->sort()->values()->all()) {
                continue;
            }

            $startIdx = null;
            if ($plan->start_date) {
                $order = array_flip(WorkoutPlan::WEEKDAYS);
                $startIdx = $order[strtolower($plan->start_date->format('l'))] ?? null;
            }

            DB::transaction(function () use ($plan, $structDays, $byWeekday, $startIdx, &$fixed): void {
                // Two phases: the (plan, weekday) pair is unique, so move
                // every row to a temporary label first, then to its final
                // weekday — otherwise intermediate states collide.
                $targets = [];
                foreach ($structDays as $index => $structDay) {
                    $day = $byWeekday->get(strtolower((string) ((array) $structDay)['weekday'] ?? ''));
                    if (! $day) {
                        throw new RuntimeException('Workout day mismatch during re-anchor.');
                    }
                    $targets[] = [
                        'day' => $day,
                        'sort_order' => $index,
                        'weekday' => $startIdx !== null
                            ? WorkoutPlan::WEEKDAYS[($startIdx + $index) % 7]
                            : strtolower((string) ((array) $structDay)['weekday']),
                    ];
                }
                foreach ($targets as $i => $target) {
                    $target['day']->update(['sort_order' => $target['sort_order'], 'weekday' => '__tmp'.$i]);
                }
                foreach ($targets as $target) {
                    $target['day']->update(['weekday' => $target['weekday']]);
                }
                $fixed++;
            });
        }

        Log::info('workout plan days re-anchored', ['plans' => $fixed]);
    }

    public function down(): void
    {
        // Data repair cannot be reversed (original rotations are unknown).
    }
};
