<?php

namespace Tests\Feature;

use App\Models\AiPlan;
use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiCollabReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_add_member_validates_and_executes(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Hossein Faramarzi', 'email' => 'hossein@example.com']);
        $project = Project::factory()->create(['user_id' => $owner->id, 'name' => 'برساز']);

        $svc = new AiToolService;

        // By email.
        $check = $svc->validateCall('project_add_member', ['project' => 'برساز', 'user' => 'hossein@example.com'], $owner);
        $this->assertTrue($check['ok'], $check['error'] ?? 'validate failed');
        $this->assertEquals($project->id, $check['resolved']['project_id']);
        $this->assertEquals($member->id, $check['resolved']['member_id']);

        $result = $svc->execute('project_add_member', $check['resolved'], $owner);
        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('project_teams', ['project_id' => $project->id, 'user_id' => $member->id, 'role' => 'member']);

        // Idempotent: second run updates instead of duplicating.
        $result2 = $svc->execute('project_add_member', $check['resolved'], $owner);
        $this->assertTrue($result2['ok']);
        $this->assertStringContainsString('already', $result2['message']);
        $this->assertEquals(1, \DB::table('project_teams')->where('project_id', $project->id)->where('user_id', $member->id)->count());

        // By name fragment + custom role.
        $check2 = $svc->validateCall('project_add_member', ['project_id' => $project->id, 'user' => 'Hossein', 'role' => 'editor'], $owner);
        $this->assertTrue($check2['ok'], $check2['error'] ?? 'validate failed');
        $this->assertEquals('editor', $check2['resolved']['role']);

        // Unknown user rejected.
        $this->assertFalse($svc->validateCall('project_add_member', ['project' => 'برساز', 'user' => 'nobody-xyz'], $owner)['ok']);

        // Foreign project rejected.
        $other = User::factory()->create();
        $this->assertFalse($svc->validateCall('project_add_member', ['project' => 'برساز', 'user' => 'hossein@example.com'], $other)['ok']);

        // Adding yourself rejected.
        $this->assertFalse($svc->validateCall('project_add_member', ['project' => 'برساز', 'user' => (string) $owner->id], $owner)['ok']);
    }

    public function test_note_link_validates_and_executes(): void
    {
        $user = User::factory()->create();
        $note = Note::create(['user_id' => $user->id, 'title' => 'ایده برساز', 'content' => 'متن']);
        $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'برساز']);
        $task = Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'title' => 'پرداخت']);

        $svc = new AiToolService;
        $check = $svc->validateCall('note_link', ['note' => 'ایده برساز', 'target_type' => 'task', 'target' => 'پرداخت'], $user);
        $this->assertTrue($check['ok'], $check['error'] ?? 'validate failed');
        $this->assertEquals($note->id, $check['resolved']['note_id']);
        $this->assertEquals($task->id, $check['resolved']['target_id']);

        $result = $svc->execute('note_link', $check['resolved'], $user);
        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('note_links', [
            'note_id' => $note->id,
            'linkable_type' => Task::class,
            'linkable_id' => $task->id,
        ]);

        // Project target.
        $check2 = $svc->validateCall('note_link', ['note_id' => $note->id, 'target_type' => 'project', 'target_id' => $project->id], $user);
        $this->assertTrue($check2['ok']);
        $this->assertTrue($svc->execute('note_link', $check2['resolved'], $user)['ok']);

        // Self link rejected, foreign target rejected.
        $this->assertFalse($svc->validateCall('note_link', ['note_id' => $note->id, 'target_type' => 'note', 'target_id' => $note->id], $user)['ok']);
        $other = User::factory()->create();
        $foreignTask = Task::factory()->create(['user_id' => $other->id]);
        $this->assertFalse($svc->validateCall('note_link', ['note_id' => $note->id, 'target_type' => 'task', 'target_id' => $foreignTask->id], $user)['ok']);
    }

    public function test_report_generate_validates_and_returns_summary_without_writes(): void
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'status' => 'completed', 'completed_at' => now()]);

        $svc = new AiToolService;
        $check = $svc->validateCall('report_generate', ['range' => 'week'], $user);
        $this->assertTrue($check['ok'], $check['error'] ?? 'validate failed');

        $before = [
            Task::where('user_id', $user->id)->count(),
            Note::where('user_id', $user->id)->count(),
            Project::where('user_id', $user->id)->count(),
        ];
        $result = $svc->execute('report_generate', $check['resolved'], $user);
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Workspace report', $result['message']);
        $this->assertStringContainsString('Tasks:', $result['message']);
        $this->assertEquals($before, [
            Task::where('user_id', $user->id)->count(),
            Note::where('user_id', $user->id)->count(),
            Project::where('user_id', $user->id)->count(),
        ]);

        $this->assertFalse($svc->validateCall('report_generate', ['range' => 'year'], $user)['ok']);
    }

    public function test_plan_with_members_attaches_on_run_all(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'hossein@example.com']);

        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', [
            'title' => 'پلن مشترک',
            'projects' => [
                ['name' => 'برساز', 'tasks' => [['title' => 'پرداخت']], 'members' => ['hossein@example.com', 'ghost-unknown']],
            ],
        ], $owner);
        $this->assertTrue($check['ok'], $check['error'] ?? 'validate failed');
        // Totals count requested member refs; unknown names are reported as skipped at execution.
        $this->assertEquals(2, $check['resolved']['totals']['members']);

        $plan = AiPlan::create([
            'user_id' => $owner->id,
            'title' => $check['resolved']['title'],
            'structure' => $check['resolved']['structure'],
            'phases' => $svc->buildPlanPhases($check['resolved']['structure']),
            'status' => AiPlan::STATUS_PROPOSED,
            'current_phase' => 0,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);

        $this->actingAs($owner)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();
        $this->actingAs($owner)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 0, 'run_all' => true])
            ->assertOk()->assertJsonPath('plan.status', 'done');

        $project = Project::where('user_id', $owner->id)->where('name', 'برساز')->firstOrFail();
        $this->assertDatabaseHas('project_teams', ['project_id' => $project->id, 'user_id' => $member->id]);
    }
}
