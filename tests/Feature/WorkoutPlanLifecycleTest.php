<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutSession;
use App\Services\WorkoutPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutPlanLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_plan_can_be_active_at_a_time(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::create(['user_id' => $user->id, 'name' => 'Pull-up', 'normalized_name' => 'pull-up']);
        $service = app(WorkoutPlanService::class);

        $first = $service->save($this->planPayload($exercise->id, 'Plan A', 'active'), $user->id);
        $second = $service->save($this->planPayload($exercise->id, 'Plan B', 'active'), $user->id);

        $this->assertSame('draft', $first->fresh()->status);
        $this->assertSame('active', $second->fresh()->status);

        // One-click activation flips the pair back.
        $this->actingAs($user)->post(route('workouts.plans.activate', $first))
            ->assertRedirect(route('workouts.plans.index'));

        $this->assertSame('active', $first->fresh()->status);
        $this->assertSame('draft', $second->fresh()->status);
    }

    public function test_updating_a_plan_without_changes_preserves_session_history(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::create(['user_id' => $user->id, 'name' => 'Pull-up', 'normalized_name' => 'pull-up']);
        $plan = app(WorkoutPlanService::class)->save($this->planPayload($exercise->id), $user->id);
        $day = $plan->days()->first();
        $workoutExercise = $day->exercises()->firstOrFail();

        $this->actingAs($user)->post(route('workouts.sessions.start', $day), ['date' => now()->toDateString()]);
        $session = WorkoutSession::firstOrFail();
        $this->actingAs($user)->postJson(route('workouts.sessions.sets.store', $session), [
            'workout_exercise_id' => $workoutExercise->id,
            'set_number' => 1, 'reps' => 7, 'weight' => 10,
        ])->assertOk();
        $this->actingAs($user)->patch(route('workouts.sessions.finish', $session), ['status' => 'completed']);

        // Save the plan again without changes (same payload, section intact).
        $payload = $this->planPayload($exercise->id);
        $this->actingAs($user)->put(route('workouts.plans.update', $plan), $payload)
            ->assertRedirect(route('workouts.plans.edit', $plan));

        // History survives the edit: session, exercise log and set are kept…
        $session = $session->fresh();
        $this->assertNotNull($session);
        $this->assertSame(1, $session->exerciseLogs()->count());
        $this->assertSame(1, $session->exerciseLogs()->firstOrFail()->setLogs()->count());

        // …with snapshots so reports stay readable even though the
        // template rows were rebuilt.
        $this->assertSame('Pull A', $session->day_title);
        $this->assertSame('Pull-up', $session->exerciseLogs()->firstOrFail()->exercise_name);
    }

    public function test_session_cannot_start_for_a_draft_plan(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::create(['user_id' => $user->id, 'name' => 'Squat', 'normalized_name' => 'squat']);
        $plan = app(WorkoutPlanService::class)->save($this->planPayload($exercise->id, 'Draft plan', 'draft'), $user->id);
        $day = $plan->days()->where('type', 'training')->firstOrFail();

        $this->actingAs($user)->post(route('workouts.sessions.start', $day), ['date' => now()->toDateString()])
            ->assertStatus(422);
        $this->assertSame(0, WorkoutSession::count());

        $this->actingAs($user)->post(route('workouts.plans.activate', $plan))->assertRedirect();
        $this->actingAs($user)->post(route('workouts.sessions.start', $day), ['date' => now()->toDateString()])
            ->assertRedirect();
        $this->assertSame(1, WorkoutSession::count());
    }

    public function test_planner_shows_only_the_active_plan(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::create(['user_id' => $user->id, 'name' => 'Squat', 'normalized_name' => 'squat']);
        app(WorkoutPlanService::class)->save($this->planPayload($exercise->id, 'Draft plan', 'draft'), $user->id);

        // No active plan → no workout leaks into My Day (no latest fallback).
        $this->actingAs($user)->get(route('planner.index'))
            ->assertOk()
            ->assertViewHas('activeWorkoutPlan', null)
            ->assertViewHas('todayWorkoutDay', null);

        $active = app(WorkoutPlanService::class)->save($this->planPayload($exercise->id, 'Live plan', 'active'), $user->id);

        $response = $this->actingAs($user)->get(route('planner.index'))->assertOk();
        $this->assertSame($active->id, $response->viewData('activeWorkoutPlan')->id);
        $this->assertNotNull($response->viewData('todayWorkoutDay'));

        // Week view carries the active plan only.
        $week = $this->actingAs($user)->get(route('planner.index', ['view' => 'week']))->assertOk();
        $this->assertSame($active->id, $week->viewData('activeWorkoutPlan')->id);
        $this->assertNotEmpty($week->viewData('workoutWeek'));
    }

    public function test_reports_survive_plan_edit_and_delete(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::create(['user_id' => $user->id, 'name' => 'Pull-up', 'normalized_name' => 'pull-up']);
        $plan = app(WorkoutPlanService::class)->save($this->planPayload($exercise->id), $user->id);
        $day = $plan->days()->where('type', 'training')->firstOrFail();
        $workoutExercise = $day->exercises()->firstOrFail();

        $this->actingAs($user)->post(route('workouts.sessions.start', $day), ['date' => now()->toDateString()]);
        $session = WorkoutSession::firstOrFail();
        $this->actingAs($user)->postJson(route('workouts.sessions.sets.store', $session), [
            'workout_exercise_id' => $workoutExercise->id,
            'set_number' => 1, 'reps' => 7, 'weight' => 10,
        ])->assertOk();
        $this->actingAs($user)->patch(route('workouts.sessions.finish', $session), ['status' => 'completed']);

        // Edit keeps the report…
        $this->actingAs($user)->put(route('workouts.plans.update', $plan), $this->planPayload($exercise->id));
        $this->actingAs($user)->get(route('workouts.reports.index'))
            ->assertOk()
            ->assertSee('Pull A')
            ->assertSee('Pull-up');

        // …and so does archiving (soft delete) the whole plan.
        $this->actingAs($user)->delete(route('workouts.plans.destroy', $plan))
            ->assertRedirect(route('workouts.plans.index'));
        $this->assertSoftDeleted('workout_plans', ['id' => $plan->id]);

        $this->actingAs($user)->get(route('workouts.reports.index'))
            ->assertOk()
            ->assertSee('Pull A')
            ->assertSee('Pull-up');

        // The detached session opens as immutable history, not a crash.
        $this->actingAs($user)->get(route('workouts.sessions.show', $session))
            ->assertOk()
            ->assertSee('Pull A')
            ->assertSee('saved history');
    }

    private function planPayload(int $exerciseId, string $title = 'Week 13', string $status = 'active'): array
    {
        return [
            'title' => $title, 'week_number' => 13, 'goal' => 'Strength',
            'status' => $status, 'rules' => ['Form over reps'],
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
