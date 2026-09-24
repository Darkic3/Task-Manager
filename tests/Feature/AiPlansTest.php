<?php

namespace Tests\Feature;

use App\Models\AiPlan;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiPlansTest extends TestCase
{
    use RefreshDatabase;

    private function workoutArgs(): array
    {
        return [
            'title' => 'Weekly workout',
            'project' => ['name' => 'ورزش', 'tasks' => []],
            'subprojects' => [[
                'name' => 'هفته ۱۳',
                'tasks' => [
                    ['title' => 'روز ۴: PULL B', 'due_date' => '2026-09-23', 'subtasks' => [
                        ['title' => 'Dead Hang 30-45s'], ['title' => 'Pull-up 4x6-8'],
                    ]],
                    ['title' => 'روز ۵: PUSH B', 'due_date' => '2026-09-24', 'subtasks' => []],
                ],
            ]],
        ];
    }

    private function routinePlanArgs(): array
    {
        return [
            'title' => 'Workout routines',
            'routines' => [
                [
                    'title' => 'PULL A', 'frequency' => 'weekly', 'days' => ['saturday'],
                    'tracking_mode' => 'sets',
                    'steps' => [
                        ['name' => 'Pull-up', 'target_sets' => 4],
                        ['name' => 'Row', 'target_sets' => 3],
                    ],
                ],
                [
                    'title' => 'Weigh in', 'frequency' => 'daily',
                    'tracking_mode' => 'value', 'value_kind' => 'weight',
                    'value_unit' => 'kg', 'value_label' => 'Weight',
                ],
            ],
        ];
    }

    public function test_routine_plan_branch_builds_routines(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', $this->routinePlanArgs(), $user);
        $this->assertTrue($check['ok'], $check['error'] ?? 'validate failed');
        $this->assertEquals(2, $check['resolved']['totals']['routines']);

        $plan = AiPlan::create([
            'user_id' => $user->id,
            'title' => $check['resolved']['title'],
            'structure' => $check['resolved']['structure'],
            'phases' => $svc->buildPlanPhases($check['resolved']['structure']),
            'status' => AiPlan::STATUS_PROPOSED,
            'current_phase' => 0,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
        $this->assertCount(1, $plan->phases);
        $this->assertEquals('routines', $plan->phases[0]['key']);

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 0])
            ->assertOk()->assertJsonPath('plan.status', 'done');

        $pull = \App\Models\Routine::where('title', 'PULL A')->firstOrFail();
        $this->assertEquals(['saturday'], $pull->decodedDays());
        $this->assertEquals('sets', $pull->tracking_mode);
        $this->assertEquals(2, $pull->checklistItems()->count());
        $this->assertEquals(4, $pull->checklistItems()->where('name', 'Pull-up')->first()->target_sets);
        $weigh = \App\Models\Routine::where('title', 'Weigh in')->firstOrFail();
        $this->assertEquals('weight', $weigh->value_kind);
    }

    public function test_plan_rejects_mixed_tree_and_routines(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;

        $mixed = $this->workoutArgs();
        $mixed['routines'] = [['title' => 'X', 'frequency' => 'daily']];
        $this->assertFalse($svc->validateCall('plan_propose', $mixed, $user)['ok']);

        $tooMany = ['title' => 'Big', 'routines' => array_map(
            fn ($i) => ['title' => "R$i", 'frequency' => 'daily'],
            range(1, 8)
        )];
        $this->assertFalse($svc->validateCall('plan_propose', $tooMany, $user)['ok']);

        $this->assertFalse($svc->validateCall('plan_propose', ['title' => 'Empty'], $user)['ok']);
    }

    private function makePlan(User $user, array $args = null): AiPlan
    {
        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', $args ?? $this->workoutArgs(), $user);
        $this->assertTrue($check['ok'], $check['error'] ?? 'validate failed');

        return AiPlan::create([
            'user_id' => $user->id,
            'title' => $check['resolved']['title'],
            'structure' => $check['resolved']['structure'],
            'phases' => $svc->buildPlanPhases($check['resolved']['structure']),
            'status' => AiPlan::STATUS_PROPOSED,
            'current_phase' => 0,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
    }

    public function test_plan_validation_enforces_caps(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;

        $tooMany = $this->workoutArgs();
        $tooMany['subprojects'][0]['tasks'] = array_map(
            fn ($i) => ['title' => "Day $i"],
            range(1, 31)
        );
        $this->assertFalse($svc->validateCall('plan_propose', $tooMany, $user)['ok']);

        $tooManySubs = $this->workoutArgs();
        $tooManySubs['subprojects'] = array_map(
            fn ($i) => ['name' => "Week $i"],
            range(1, 4)
        );
        $this->assertFalse($svc->validateCall('plan_propose', $tooManySubs, $user)['ok']);

        $empty = $this->workoutArgs();
        $empty['subprojects'][0]['tasks'] = [];
        $this->assertFalse($svc->validateCall('plan_propose', $empty, $user)['ok']);

        $long = $this->workoutArgs();
        $long['subprojects'][0]['tasks'][0]['title'] = str_repeat('x', 121);
        $this->assertFalse($svc->validateCall('plan_propose', $long, $user)['ok']);
    }

    public function test_full_plan_flow_builds_hierarchy(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan($user);

        // 1. Confirm structure.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))
            ->assertOk()->assertJsonPath('plan.status', 'confirmed');

        // 2. Phase 0: project.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 0])
            ->assertOk();
        $project = Project::where('user_id', $user->id)->where('name', 'ورزش')->firstOrFail();

        // 3. Phase 1: sub-project under it.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 1])
            ->assertOk();
        $sub = Project::where('user_id', $user->id)->where('name', 'هفته ۱۳')->firstOrFail();
        $this->assertEquals($project->id, $sub->parent_id);

        // 4. Phase 2: daily tasks in the sub-project with due dates.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 2])
            ->assertOk();
        $day4 = Task::where('project_id', $sub->id)->where('title', 'روز ۴: PULL B')->firstOrFail();
        $this->assertEquals('2026-09-23', $day4->due_date->toDateString());

        // 5. Phase 3: exercise subtasks under their day.
        $res = $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 3])
            ->assertOk()->assertJsonPath('plan.status', 'done');
        $this->assertEquals(2, Task::where('parent_id', $day4->id)->count());
        $this->assertEquals($sub->id, Task::where('parent_id', $day4->id)->first()->project_id);
    }

    public function test_phase_index_mismatch_never_runs_next_phase(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan($user);

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();
        // Stale/wrong index: must not execute anything.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 2])
            ->assertOk()->assertJson(['deduped' => true]);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_run_all_executes_remaining_phases(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan($user);

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 0, 'run_all' => true])
            ->assertOk()->assertJsonPath('plan.status', 'done');

        $this->assertEquals(2, Project::where('user_id', $user->id)->count());
        $this->assertEquals(4, Task::where('user_id', $user->id)->count()); // 2 tasks + 2 subtasks
    }

    public function test_foreign_user_and_expiry_blocked(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $plan = $this->makePlan($user);

        $this->actingAs($other)->postJson(route('ai.plans.confirm-structure', $plan))->assertForbidden();
        $this->actingAs($other)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 0])->assertForbidden();

        $plan->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))->assertStatus(422);
        $this->assertEquals(AiPlan::STATUS_EXPIRED, $plan->fresh()->status);
    }

    public function test_single_tools_support_parents(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;

        $root = $svc->execute('project_create',
            $svc->validateCall('project_create', ['name' => 'ورزش'], $user)['resolved'], $user);
        $sub = $svc->execute('project_create',
            $svc->validateCall('project_create', ['name' => 'هفته ۱۳', 'parent' => 'ورزش'], $user)['resolved'], $user);
        $this->assertEquals($root['id'], Project::find($sub['id'])->parent_id);

        $task = $svc->execute('task_create',
            $svc->validateCall('task_create', ['title' => 'روز ۴', 'project_id' => $sub['id']], $user)['resolved'], $user);
        $child = $svc->execute('task_create',
            $svc->validateCall('task_create', ['title' => 'Pull-up', 'parent_id' => $task['id']], $user)['resolved'], $user);
        $childRow = Task::find($child['id']);
        $this->assertEquals($task['id'], $childRow->parent_id);
        $this->assertEquals($sub['id'], $childRow->project_id); // inherited
    }

    public function test_plan_propose_via_chat_creates_plan_packet(): void
    {
        $user = User::factory()->create();
        \App\Models\AiSetting::create([
            'user_id' => $user->id, 'default_provider' => 'openai',
            'openai_key' => 'sk-test-key', 'openai_model' => 'gpt-4o-mini',
        ]);

        Http::fake(fn () => Http::response(['choices' => [['message' => [
            'content' => '',
            'tool_calls' => [[
                'id' => 'call_1', 'type' => 'function',
                'function' => ['name' => 'plan_propose', 'arguments' => json_encode($this->workoutArgs())],
            ]],
        ]]]], 200));

        $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Build it', 'mode' => 'agent'])
            ->assertOk()
            ->assertJsonPath('proposal.plan.title', 'Weekly workout');

        $this->assertDatabaseHas('ai_plans', ['user_id' => $user->id, 'status' => 'proposed']);
    }
}
