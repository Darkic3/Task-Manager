<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutSession;
use App\Services\WorkoutPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_cycle_clones_the_plan_without_changing_the_source(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::create(['user_id' => $user->id, 'name' => 'Pull-up', 'normalized_name' => 'pull-up']);
        $source = app(WorkoutPlanService::class)->save($this->planPayload($exercise->id), $user->id);

        $this->actingAs($user)->post(route('workouts.plans.new-cycle', $source))
            ->assertRedirect();

        $copy = WorkoutPlan::where('parent_id', $source->id)->firstOrFail();
        $this->assertSame(1, $source->cycle_no);
        $this->assertSame(2, $copy->cycle_no);
        $this->assertSame(13, $source->week_number);
        $this->assertSame(14, $copy->week_number);
        $this->assertSame($exercise->id, $copy->days()->first()->exercises()->first()->exercise_id);
        $this->assertNotSame($source->days()->first()->id, $copy->days()->first()->id);
    }

    public function test_user_can_start_a_session_save_and_update_a_set_then_finish(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::create(['user_id' => $user->id, 'name' => 'Pull-up', 'normalized_name' => 'pull-up']);
        $plan = app(WorkoutPlanService::class)->save($this->planPayload($exercise->id), $user->id);
        $day = $plan->days()->first();
        $workoutExercise = $day->exercises()->first();

        $this->actingAs($user)->post(route('workouts.sessions.start', $day), [
            'date' => '2026-09-27',
        ])->assertRedirect();

        $session = WorkoutSession::firstOrFail();
        $this->actingAs($user)->postJson(route('workouts.sessions.sets.store', $session), [
            'workout_exercise_id' => $workoutExercise->id,
            'set_number' => 1,
            'reps' => 7,
            'weight' => 7,
            'rir' => 2,
            'form_rating' => 4,
            'pain_level' => 0,
        ])->assertOk()->assertJsonPath('set.reps', '7.00');

        $setId = $session->exerciseLogs()->first()->setLogs()->first()->id;
        $this->actingAs($user)->postJson(route('workouts.sessions.sets.store', $session), [
            'workout_exercise_id' => $workoutExercise->id,
            'set_number' => 1,
            'reps' => 8,
            'weight' => 7,
            'rir' => 1,
            'form_rating' => 5,
            'pain_level' => 0,
        ])->assertOk();

        $this->assertSame($setId, $session->exerciseLogs()->first()->setLogs()->first()->id);
        $this->assertSame('8.00', (string) $session->fresh()->exerciseLogs()->first()->setLogs()->first()->reps);

        $this->actingAs($user)->patch(route('workouts.sessions.finish', $session), [
            'status' => 'completed',
            'session_note' => 'Strong session.',
        ])->assertRedirect(route('workouts.sessions.show', $session));

        $this->assertSame(WorkoutSession::COMPLETED, $session->fresh()->status);
        $this->assertNotNull($session->fresh()->ended_at);
    }

    public function test_session_is_scoped_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $exercise = Exercise::create(['user_id' => $owner->id, 'name' => 'Squat', 'normalized_name' => 'squat']);
        $plan = app(WorkoutPlanService::class)->save($this->planPayload($exercise->id), $owner->id);
        $session = WorkoutSession::create([
            'user_id' => $owner->id,
            'workout_day_id' => $plan->days()->first()->id,
            'workout_date' => '2026-09-27',
            'status' => WorkoutSession::IN_PROGRESS,
        ]);

        $this->actingAs($other)->get(route('workouts.sessions.show', $session))->assertForbidden();
    }

    private function planPayload(int $exerciseId): array
    {
        return [
            'title' => 'Week 13', 'week_number' => 13, 'goal' => 'Strength',
            'status' => 'active', 'rules' => ['Form over reps'],
            'days' => collect(WorkoutPlan::WEEKDAYS)->map(fn ($weekday, $index) => [
                'weekday' => $weekday,
                'title' => $index === 0 ? 'Pull A' : 'Rest',
                'type' => $index === 0 ? 'training' : 'rest',
                'exercises' => $index === 0 ? [[
                    'exercise_id' => $exerciseId, 'section' => 'main', 'target_sets' => 1,
                    'rep_min' => 6, 'rep_max' => 8,
                ]] : [],
            ])->all(),
        ];
    }
}
