<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutSession;
use App\Services\WorkoutPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_circuit_rounds_are_used_as_required_sets(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::create(['user_id' => $user->id, 'name' => 'Burpee', 'normalized_name' => 'burpee']);
        $plan = app(WorkoutPlanService::class)->save($this->payload($exercise->id), $user->id);
        $workoutExercise = $plan->days()->first()->exercises()->first();
        $workoutExercise->update(['is_circuit' => true, 'circuit_rounds' => 2, 'target_sets' => 1]);
        $day = $plan->days()->first();

        $this->actingAs($user)->post(route('workouts.sessions.start', $day), ['date' => '2026-09-27']);
        $session = WorkoutSession::firstOrFail();

        $this->actingAs($user)->postJson(route('workouts.sessions.sets.store', $session), [
            'workout_exercise_id' => $workoutExercise->id, 'set_number' => 1, 'reps' => 8,
        ])->assertJsonPath('exercise_completed', false);
        $this->actingAs($user)->postJson(route('workouts.sessions.sets.store', $session), [
            'workout_exercise_id' => $workoutExercise->id, 'set_number' => 2, 'reps' => 8,
        ])->assertJsonPath('exercise_completed', true);
    }

    public function test_daily_weekly_and_exercise_reports_render_completed_data(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::create(['user_id' => $user->id, 'name' => 'Squat', 'normalized_name' => 'squat']);
        $plan = app(WorkoutPlanService::class)->save($this->payload($exercise->id), $user->id);
        $day = $plan->days()->first();
        $this->actingAs($user)->post(route('workouts.sessions.start', $day), ['date' => '2026-09-27']);
        $session = WorkoutSession::firstOrFail();

        $this->actingAs($user)->postJson(route('workouts.sessions.sets.store', $session), [
            'workout_exercise_id' => $day->exercises()->first()->id,
            'set_number' => 1, 'reps' => 10, 'weight' => 20, 'rir' => 2, 'pain_level' => 1,
        ]);
        $this->actingAs($user)->patch(route('workouts.sessions.finish', $session), ['status' => 'completed']);

        $this->actingAs($user)->get(route('workouts.reports.index', ['view' => 'day', 'date' => '2026-09-27']))
            ->assertOk()->assertSee('10')->assertSee('20');
        $this->actingAs($user)->get(route('workouts.reports.index', ['view' => 'week', 'date' => '2026-09-27']))
            ->assertOk()->assertSee('Sessions done');
        $this->actingAs($user)->get(route('workouts.reports.exercise', $exercise))
            ->assertOk()->assertSee('Squat')->assertSee('20');
    }

    private function payload(int $exerciseId): array
    {
        return [
            'title' => 'Report week', 'week_number' => 13, 'status' => 'active',
            'days' => collect(WorkoutPlan::WEEKDAYS)->map(fn ($weekday, $index) => [
                'weekday' => $weekday, 'title' => $index === 0 ? 'Training' : 'Rest',
                'type' => $index === 0 ? 'training' : 'rest',
                'exercises' => $index === 0 ? [[
                    'exercise_id' => $exerciseId, 'section' => 'main', 'target_sets' => 1,
                    'rep_min' => 8, 'rep_max' => 12,
                ]] : [],
            ])->all(),
        ];
    }
}
