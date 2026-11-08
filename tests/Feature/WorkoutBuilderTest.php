<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_reuse_an_exercise(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('workouts.exercises.store'), [
            'name' => 'Pull-up',
            'aliases' => 'Pull Up, بارفیکس',
            'category' => 'Pull',
            'equipment' => 'Bodyweight',
            'muscle_groups' => 'Lats, Biceps',
            'instructions' => 'Keep the ribs controlled.',
        ])->assertRedirect(route('workouts.exercises.index'));

        $exercise = Exercise::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(['Pull Up', 'بارفیکس'], $exercise->aliases);
        $this->assertSame('pull-up', $exercise->normalized_name);

        $this->actingAs($user)->get(route('workouts.exercises.index'))
            ->assertOk()->assertSee('Pull-up');
    }

    public function test_user_can_build_a_seven_day_plan_with_one_reused_movement(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::create([
            'user_id' => $user->id,
            'name' => 'Pull-up',
            'normalized_name' => 'pull-up',
            'category' => 'Pull',
        ]);

        $days = collect(['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
            ->map(fn ($weekday) => [
                'weekday' => $weekday,
                'title' => $weekday === 'saturday' ? 'Pull A' : 'Rest',
                'type' => $weekday === 'saturday' ? 'training' : 'rest',
                'notes' => '',
                'exercises' => $weekday === 'saturday' ? [[
                    'exercise_id' => $exercise->id,
                    'section' => 'main',
                    'target_sets' => 4,
                    'rep_min' => 6,
                    'rep_max' => 8,
                    'target_rir' => 2,
                    'rest_seconds' => 150,
                ]] : [],
            ])->all();

        $response = $this->actingAs($user)->post(route('workouts.plans.store'), [
            'title' => 'Week 13 Training Plan',
            'week_number' => 13,
            'goal' => 'Muscle Gain + Strength',
            'status' => 'draft',
            'rules' => ['Main exercises → RIR 1–2'],
            'days' => $days,
        ]);

        $plan = $user->workoutPlans()->firstOrFail();
        $response->assertRedirect(route('workouts.plans.edit', $plan));
        $this->assertCount(7, $plan->days);
        $this->assertSame(1, $plan->days()->where('type', 'training')->first()->exercises()->count());
        $this->assertSame($exercise->id, $plan->days()->where('type', 'training')->first()->exercises()->first()->exercise_id);
        $this->assertDatabaseHas('workout_rules', ['workout_plan_id' => $plan->id, 'rule_text' => 'Main exercises → RIR 1–2']);
    }

    public function test_workout_plan_cannot_use_another_users_exercise(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $foreignExercise = Exercise::create([
            'user_id' => $owner->id,
            'name' => 'Private movement',
            'normalized_name' => 'private movement',
        ]);
        $days = collect(['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
            ->map(fn ($weekday) => [
                'weekday' => $weekday, 'title' => 'Rest', 'type' => 'rest', 'exercises' => [],
            ])->all();
        $days[0]['title'] = 'Training';
        $days[0]['type'] = 'training';
        $days[0]['exercises'] = [['exercise_id' => $foreignExercise->id, 'section' => 'main']];

        $this->actingAs($attacker)->post(route('workouts.plans.store'), [
            'title' => 'Bad plan', 'status' => 'draft', 'days' => $days,
        ])->assertForbidden();

        $this->assertDatabaseMissing('workout_plans', ['title' => 'Bad plan']);
    }

    public function test_spelling_variants_of_the_same_movement_cannot_be_added_twice(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('workouts.exercises.store'), ['name' => 'Pull-up'])
            ->assertRedirect(route('workouts.exercises.index'));

        foreach (['pull up', 'PULLUP', 'Pull — up'] as $variant) {
            $this->actingAs($user)->from(route('workouts.exercises.create'))
                ->post(route('workouts.exercises.store'), ['name' => $variant])
                ->assertRedirect(route('workouts.exercises.create'))
                ->assertSessionHasErrors('name');
        }

        $this->assertSame(1, Exercise::where('user_id', $user->id)->count());
    }

    public function test_aliases_are_part_of_the_duplicate_check(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('workouts.exercises.store'), [
            'name' => 'Pull-up',
            'aliases' => 'Pull Up, بارفیکس',
        ])->assertRedirect(route('workouts.exercises.index'));

        // A new movement whose name equals an existing alias.
        $this->actingAs($user)->from(route('workouts.exercises.create'))
            ->post(route('workouts.exercises.store'), ['name' => 'بارفیکس'])
            ->assertSessionHasErrors('name');

        // A new alias that collides with another movement's identity.
        $this->actingAs($user)->from(route('workouts.exercises.create'))
            ->post(route('workouts.exercises.store'), ['name' => 'Chin-up', 'aliases' => 'pull up'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Exercise::where('user_id', $user->id)->count());
    }

    public function test_renaming_an_exercise_onto_another_movement_is_rejected(): void
    {
        $user = User::factory()->create();
        $squat = Exercise::create(['user_id' => $user->id, 'name' => 'Squat', 'normalized_name' => 'squat']);
        Exercise::create(['user_id' => $user->id, 'name' => 'Front Squat', 'normalized_name' => 'front squat']);

        $this->actingAs($user)->from(route('workouts.exercises.edit', $squat))
            ->put(route('workouts.exercises.update', $squat), ['name' => 'front-squat'])
            ->assertSessionHasErrors('name');

        $this->assertSame('Squat', $squat->fresh()->name);
    }

    public function test_archived_exercise_is_restored_instead_of_duplicated(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::create([
            'user_id' => $user->id, 'name' => 'Squat', 'normalized_name' => 'squat', 'category' => 'Legs',
        ]);
        $exercise->delete();

        $this->actingAs($user)->post(route('workouts.exercises.store'), [
            'name' => 'squat',
            'category' => 'Legs',
            'instructions' => 'Knees track the toes.',
        ])->assertRedirect(route('workouts.exercises.index'))->assertSessionHasNoErrors();

        $this->assertSame(1, Exercise::withTrashed()->where('user_id', $user->id)->count());
        $restored = Exercise::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($exercise->id, $restored->id);
        $this->assertNull($restored->deleted_at);
        $this->assertSame('Knees track the toes.', $restored->instructions);
    }

    public function test_the_same_movement_name_cannot_be_duplicated_across_users_or_inside_one_plan(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Exercise::create(['user_id' => $owner->id, 'name' => 'Deadlift', 'normalized_name' => 'deadlift']);

        // Another user keeps their own separate movement with that name.
        $this->actingAs($other)->post(route('workouts.exercises.store'), ['name' => 'Deadlift'])
            ->assertRedirect(route('workouts.exercises.index'));

        $this->assertSame(1, Exercise::where('user_id', $other->id)->count());
        $this->assertSame(1, Exercise::where('user_id', $owner->id)->count());
    }
}
