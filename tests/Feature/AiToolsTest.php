<?php

namespace Tests\Feature;

use App\Models\AiPendingAction;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_dotted_tool_names_still_work(): void
    {
        $user = User::factory()->create();
        Project::factory()->create(['user_id' => $user->id]);
        $svc = new AiToolService;

        // Pending rows stored before the rename used dots.
        $check = $svc->validateCall('task.create', ['title' => 'Legacy'], $user);
        $this->assertTrue($check['ok']);
        $result = $svc->execute('task.create', $check['resolved'], $user);
        $this->assertTrue($result['ok']);
    }

    public function test_service_accepts_camelcase_aliases(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $svc = new AiToolService;

        // Models via OpenRouter often send projectId/dueDate despite the schema.
        $check = $svc->validateCall('task_create', [
            'title' => 'Alias task',
            'projectId' => (string) $project->id,
            'dueDate' => '2026-10-01',
        ], $user);
        $this->assertTrue($check['ok']);
        $this->assertEquals($project->id, $check['resolved']['project_id']);
        $this->assertEquals('2026-10-01', $check['resolved']['due_date']);
    }

    public function test_service_validates_and_executes_task_create(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $svc = new AiToolService;

        $check = $svc->validateCall('task_create', ['title' => 'AI task'], $user);
        $this->assertTrue($check['ok']);
        $this->assertEquals($project->id, $check['resolved']['project_id']);

        $result = $svc->execute('task_create', $check['resolved'], $user);
        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('tasks', ['id' => $result['id'], 'title' => 'AI task', 'user_id' => $user->id]);
    }

    public function test_service_rejects_foreign_project(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = Project::factory()->create(['user_id' => $other->id]);
        $svc = new AiToolService;

        $check = $svc->validateCall('task_create', ['title' => 'X', 'project_id' => $foreign->id], $user);
        $this->assertFalse($check['ok']);
    }

    public function test_confirm_executes_and_reject_cancels(): void
    {
        $user = User::factory()->create();
        Project::factory()->create(['user_id' => $user->id]);
        $svc = new AiToolService;
        $check = $svc->validateCall('task_create', ['title' => 'Confirm me'], $user);
        $this->assertTrue($check['ok']);

        $action = AiPendingAction::create([
            'user_id' => $user->id,
            'tool' => 'task_create',
            'args' => $check['resolved'],
            'preview' => $svc->preview('task_create', $check['resolved'], $user),
            'status' => AiPendingAction::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $action), [])
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('tasks', ['title' => 'Confirm me']);

        // Double confirm is deduped, no duplicate row.
        $this->actingAs($user)->postJson(route('ai.actions.confirm', $action), [])
            ->assertOk()->assertJson(['ok' => true, 'deduped' => true]);
        $this->assertEquals(1, Task::where('title', 'Confirm me')->count());

        // Reject path creates nothing.
        $check2 = $svc->validateCall('note_create', ['title' => 'N', 'content' => 'C'], $user);
        $action2 = AiPendingAction::create([
            'user_id' => $user->id,
            'tool' => 'note_create',
            'args' => $check2['resolved'],
            'status' => AiPendingAction::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
        $this->actingAs($user)->postJson(route('ai.actions.reject', $action2), [])
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseMissing('notes', ['title' => 'N']);
    }

    public function test_expired_and_foreign_actions_blocked(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Project::factory()->create(['user_id' => $user->id]);
        $svc = new AiToolService;
        $check = $svc->validateCall('task_create', ['title' => 'Old'], $user);

        $expired = AiPendingAction::create([
            'user_id' => $user->id,
            'tool' => 'task_create',
            'args' => $check['resolved'],
            'status' => AiPendingAction::STATUS_PENDING,
            'expires_at' => now()->subMinute(),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
        $this->actingAs($user)->postJson(route('ai.actions.confirm', $expired), [])
            ->assertStatus(422);
        $this->assertDatabaseMissing('tasks', ['title' => 'Old']);

        $foreign = AiPendingAction::create([
            'user_id' => $other->id,
            'tool' => 'note_create',
            'args' => ['title' => 'H', 'content' => 'C'],
            'status' => AiPendingAction::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
        $this->actingAs($user)->postJson(route('ai.actions.confirm', $foreign), [])
            ->assertForbidden();
    }
}
