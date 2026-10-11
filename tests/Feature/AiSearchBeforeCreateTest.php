<?php

namespace Tests\Feature;

use App\Models\AiPlan;
use App\Models\Project;
use App\Models\User;
use App\Services\AiContextEngine;
use App\Services\AiTooling\ToolError;
use App\Services\AiTooling\ToolPipeline;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Search-before-create policy (docs/AI_AGENT_IMPROVEMENT_PLAN.md, phases 0+2):
 * - The context contains a compact project tree (id + hierarchy) so the
 *   model can attach to existing projects instead of duplicating them.
 * - project_create refuses strongly-similar names (duplicate_target +
 *   candidates) — enforced server-side, independent of the prompt.
 * - plan_propose auto-attaches nodes to existing projects (explicit
 *   project_id or strong name match) and previews the reuse.
 */
class AiSearchBeforeCreateTest extends TestCase
{
    use RefreshDatabase;

    private function project(User $user, string $name, ?int $parentId = null): Project
    {
        return Project::create([
            'user_id' => $user->id,
            'name' => $name,
            'status' => 'in_progress',
            'type' => 'project',
            'parent_id' => $parentId,
            'sort_order' => 0,
        ]);
    }

    /* ── Phase 0: context project tree ──────────────────────── */

    public function test_context_contains_project_tree_with_ids_and_hierarchy(): void
    {
        $user = User::factory()->create();
        $root = $this->project($user, 'Task Manager');
        $this->project($user, 'Chapter 1', $root->id);
        $this->project($user, 'Chapter 2', $root->id);
        $this->project($user, 'Unrelated');

        Cache::flush();
        $text = app(AiContextEngine::class)->build($user, 'add stuff to my project', 'agent')['text'];

        $this->assertStringContainsString('PROJECTS', $text);
        $this->assertStringContainsString("[id:{$root->id}]", $text);
        $this->assertStringContainsString('Task Manager', $text);
        // Children are indented under their parent.
        $this->assertMatchesRegularExpression('/- \[id:\d+\]   Chapter 1 \(/', $text);
    }

    public function test_context_project_tree_scopes_to_own_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->project($owner, 'Secret Project');
        $secret = $this->project($other, 'Other Users Thing');

        Cache::flush();
        $text = app(AiContextEngine::class)->build($owner, 'what projects do I have', 'agent')['text'];

        $this->assertStringContainsString('Secret Project', $text);
        $this->assertStringNotContainsString('Other Users Thing', $text);
        $this->assertStringNotContainsString("[id:{$secret->id}]", $text);
    }

    /* ── Phase 2: project_create duplicate guard ────────────── */

    public function test_project_create_refuses_duplicate_name_with_candidates(): void
    {
        $user = User::factory()->create();
        $existing = $this->project($user, 'Task Manager');

        $svc = new AiToolService;
        $check = $svc->validateCall('project_create', ['name' => 'task  manager'], $user);

        $this->assertFalse($check['ok']);
        $this->assertEquals(ToolError::DUPLICATE_TARGET, $check['code']);
        $this->assertNotEmpty($check['candidates'] ?? []);
        $this->assertEquals($existing->id, $check['candidates'][0]['id']);
        $this->assertEquals('Task Manager', $check['candidates'][0]['path']);
    }

    public function test_project_create_duplicate_guard_folds_persian_variants(): void
    {
        $user = User::factory()->create();
        // Arabic ye + ZWNJ in the existing name.
        $existing = $this->project($user, "كتاب\u{200C}خانه");

        $svc = new AiToolService;
        // Persian ye, no ZWNJ — must still match.
        $check = $svc->validateCall('project_create', ['name' => 'کتابخانه'], $user);

        $this->assertFalse($check['ok']);
        $this->assertEquals(ToolError::DUPLICATE_TARGET, $check['code']);
        $this->assertEquals($existing->id, $check['candidates'][0]['id']);
    }

    public function test_project_create_allows_genuinely_new_names(): void
    {
        $user = User::factory()->create();
        $this->project($user, 'Task Manager');

        $svc = new AiToolService;
        $check = $svc->validateCall('project_create', ['name' => 'Gym App'], $user);

        $this->assertTrue($check['ok'], $check['error'] ?? '');
        $this->assertEquals('Gym App', $check['resolved']['name']);
    }

    public function test_duplicate_guard_hides_other_users_projects(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $this->project($owner, 'Task Manager');

        $svc = new AiToolService;
        $check = $svc->validateCall('project_create', ['name' => 'Task Manager'], $intruder);

        $this->assertTrue($check['ok'], $check['error'] ?? '');
    }

    /* ── Phase 2: candidates reach the model through the pipeline ── */

    public function test_pipeline_passes_candidates_through_on_duplicate(): void
    {
        $user = User::factory()->create();
        $existing = $this->project($user, 'Task Manager');

        $result = ToolPipeline::validateForProposal($user, 'project_create', ['name' => 'task manager']);

        $this->assertFalse($result['ok']);
        $this->assertEquals(ToolError::DUPLICATE_TARGET, $result['code']);
        $this->assertEquals($existing->id, $result['candidates'][0]['id']);
    }

    /* ── Phase 2: plan_propose attaches to existing projects ── */

    public function test_plan_auto_attaches_to_same_named_existing_project(): void
    {
        $user = User::factory()->create();
        $existing = $this->project($user, 'Task Manager');

        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', [
            'title' => 'Add features',
            'projects' => [[
                'name' => 'Task Manager',
                'tasks' => [['title' => 'New feature']],
            ]],
        ], $user);

        $this->assertTrue($check['ok'], $check['error'] ?? '');
        $node = $check['resolved']['structure']['projects'][0];
        $this->assertEquals($existing->id, $node['project_id']);
        $this->assertTrue($node['attached']);

        // Execute the projects phase: it must REUSE, not create.
        $plan = AiPlan::create([
            'user_id' => $user->id,
            'title' => $check['resolved']['title'],
            'structure' => $check['resolved']['structure'],
            'phases' => $svc->buildPlanPhases($check['resolved']['structure']),
            'status' => AiPlan::STATUS_CONFIRMED,
            'current_phase' => 0,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);

        $phase = $svc->executePlanPhase($plan, $user);
        $this->assertTrue($phase['ok'], $phase['message'] ?? '');
        $this->assertEquals(1, Project::where('user_id', $user->id)->count(), 'no duplicate project may be created');
        $this->assertEquals($existing->id, $phase['phase']['result']['projects'][0]['id']);
        $this->assertTrue($phase['phase']['result']['projects'][0]['attached']);
    }

    public function test_plan_respects_explicit_project_id(): void
    {
        $user = User::factory()->create();
        $target = $this->project($user, 'Alpha');
        $this->project($user, 'Beta');

        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', [
            'title' => 'Move work',
            'projects' => [[
                'name' => 'Completely Different Name',
                'project_id' => $target->id,
                'tasks' => [['title' => 'T1']],
            ]],
        ], $user);

        $this->assertTrue($check['ok'], $check['error'] ?? '');
        $node = $check['resolved']['structure']['projects'][0];
        $this->assertEquals($target->id, $node['project_id']);
        $this->assertTrue($node['attached']);
    }

    public function test_plan_rejects_foreign_project_id(): void
    {
        $user = User::factory()->create();
        $foreign = $this->project(User::factory()->create(), 'Not yours');

        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', [
            'title' => 'Sneaky',
            'projects' => [[
                'name' => 'Whatever',
                'project_id' => $foreign->id,
                'tasks' => [['title' => 'T1']],
            ]],
        ], $user);

        $this->assertFalse($check['ok']);
        $this->assertEquals(ToolError::NOT_FOUND, $check['code']);
    }

    public function test_plan_subproject_only_attaches_under_same_parent(): void
    {
        $user = User::factory()->create();
        $rootA = $this->project($user, 'Book');
        $rootB = $this->project($user, 'Other');
        $this->project($user, 'Chapter 1', $rootB->id); // same name, OTHER branch
        $sibling = $this->project($user, 'Chapter 2', $rootA->id);

        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', [
            'title' => 'Chapters',
            'project' => ['name' => 'Book', 'tasks' => [['title' => 'Intro']]],
            'subprojects' => [
                // Same name but lives under 'Other' → NOT a duplicate → create.
                ['name' => 'Chapter 1', 'tasks' => [['title' => 'A']]],
                // Same name under the same parent → attach.
                ['name' => 'Chapter 2', 'tasks' => [['title' => 'B']]],
            ],
        ], $user);

        $this->assertTrue($check['ok'], $check['error'] ?? '');
        $subs = $check['resolved']['structure']['subprojects'];
        $this->assertNull($subs[0]['project_id'] ?? null, 'other-branch name must stay a fresh sub-project');
        $this->assertEquals($sibling->id, $subs[1]['project_id']);
        $this->assertTrue($subs[1]['attached']);
        $this->assertEquals($rootA->id, $check['resolved']['structure']['project']['project_id']);
    }

    public function test_plan_preview_marks_attached_nodes(): void
    {
        $user = User::factory()->create();
        $this->project($user, 'Task Manager');

        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', [
            'title' => 'Add features',
            'projects' => [[
                'name' => 'Task Manager',
                'tasks' => [['title' => 'New feature']],
            ]],
        ], $user);
        $this->assertTrue($check['ok'], $check['error'] ?? '');

        $preview = $svc->previewPlan($check['resolved']);
        $this->assertTrue($preview['tree']['projects'][0]['attached']);
        $this->assertEquals('Task Manager', $preview['tree']['projects'][0]['attached_name']);
    }
}
