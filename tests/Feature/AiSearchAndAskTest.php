<?php

namespace Tests\Feature;

use App\Http\Controllers\AiChatController;
use App\Models\AiPendingAction;
use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AiProviderService;
use App\Services\AiTooling\ToolCapabilityRegistry;
use App\Services\AiTooling\ToolError;
use App\Services\AiTooling\ToolPipeline;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 + 3 of the improvement plan:
 * - workspace_search: Persian-friendly READ search across the user's own
 *   workspace (search-before-create), always visible on routed intents.
 * - ask_user_choice: clarification cards. The chosen option is proposed
 *   through the NORMAL pipeline and still needs its own confirmation —
 *   picking an option never executes anything by itself.
 */
class AiSearchAndAskTest extends TestCase
{
    use RefreshDatabase;

    private function propose($user, string $tool, array $args, $conversationId = null): array
    {
        $controller = new AiChatController(app(AiProviderService::class));
        $ref = new \ReflectionMethod($controller, 'createPendingFromToolCall');

        return $ref->invoke($controller, $user, $conversationId, $tool, json_encode($args, JSON_UNESCAPED_UNICODE));
    }

    /* ── workspace_search ───────────────────────────────────── */

    public function test_workspace_search_finds_persian_normalized_names(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id, 'name' => "کتاب\u{200C}خانه", 'status' => 'in_progress', 'type' => 'project', 'sort_order' => 0,
        ]);
        Task::create(['user_id' => $user->id, 'title' => 'بررسی منابع', 'status' => 'to_do', 'priority' => 'medium']);
        Note::create(['user_id' => $user->id, 'title' => 'جلسه هفتگی', 'content' => 'x']);

        $svc = new AiToolService;
        // Arabic ye in the query must still match the Persian-name project.
        $check = $svc->validateCall('workspace_search', ['query' => 'كتابخانه', 'kinds' => ['project']], $user);
        $this->assertTrue($check['ok'], $check['error'] ?? '');
        $result = ToolPipeline::normalizeResult('workspace_search', $svc->execute('workspace_search', $check['resolved'], $user));
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString("project #{$project->id}", $result['message']);
        $this->assertStringContainsString('کتاب‌خانه', $result['message']);

        // Kinds filter: task-only search must not surface the project.
        $check = $svc->validateCall('workspace_search', ['query' => 'بررسی', 'kinds' => ['task']], $user);
        $result = ToolPipeline::normalizeResult('workspace_search', $svc->execute('workspace_search', $check['resolved'], $user));
        $this->assertStringContainsString('بررسی منابع', $result['message']);
        $this->assertStringNotContainsString('کتاب‌خانه', $result['message']);

        // Note kind works too.
        $check = $svc->validateCall('workspace_search', ['query' => 'جلسه', 'kinds' => ['note']], $user);
        $result = ToolPipeline::normalizeResult('workspace_search', $svc->execute('workspace_search', $check['resolved'], $user));
        $this->assertStringContainsString('جلسه هفتگی', $result['message']);
    }

    public function test_workspace_search_is_scoped_to_own_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Project::create(['user_id' => $owner->id, 'name' => 'Alpha', 'status' => 'in_progress', 'type' => 'project', 'sort_order' => 0]);
        Project::create(['user_id' => $other->id, 'name' => 'Beta Secret', 'status' => 'in_progress', 'type' => 'project', 'sort_order' => 0]);

        $svc = new AiToolService;
        $check = $svc->validateCall('workspace_search', ['query' => 'secret'], $owner);
        $result = ToolPipeline::normalizeResult('workspace_search', $svc->execute('workspace_search', $check['resolved'], $owner));
        $this->assertStringContainsString('No matches', $result['message']);
    }

    public function test_workspace_search_no_match_advises_asking_not_creating(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;
        $check = $svc->validateCall('workspace_search', ['query' => 'zzz-not-there'], $user);
        $result = ToolPipeline::normalizeResult('workspace_search', $svc->execute('workspace_search', $check['resolved'], $user));
        $this->assertStringContainsString('ask the user', $result['message']);
    }

    public function test_workspace_search_is_read_only_and_safely_routed(): void
    {
        $this->assertEquals(['READ'], AiToolService::riskOf('workspace_search'));
        $this->assertFalse(AiToolService::requiresConfirmation('workspace_search'));
        $this->assertContains('workspace_search', ToolCapabilityRegistry::SAFE_FALLBACK);
        $this->assertContains('ask_user_choice', ToolCapabilityRegistry::SAFE_FALLBACK);
        $this->assertEmpty(ToolCapabilityRegistry::validate(), 'registry sanity must stay green');
        // Query/report routes now carry search + question alongside report.
        $route = \App\Services\AiIntentRouter::route('امروز چه یادآوری هایی دارم');
        $this->assertEquals(\App\Services\AiIntentRouter::INTENT_QUERY, $route['intent']);
        $tools = \App\Services\AiIntentRouter::toolsFor($route, new AiToolService);
        $names = ToolCapabilityRegistry::namesFromDefinitions($tools);
        $this->assertContains('workspace_search', $names);
        $this->assertContains('ask_user_choice', $names);
    }

    /* ── ask_user_choice validation ─────────────────────────── */

    public function test_ask_user_choice_validates_options(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;

        // Single option is rejected.
        $check = $svc->validateCall('ask_user_choice', [
            'question' => 'کدام؟',
            'options' => [['label' => 'فقط یکی', 'tool' => 'task_create', 'args' => ['title' => 'x']]],
        ], $user);
        $this->assertFalse($check['ok']);

        // Unknown tool inside an option is rejected.
        $check = $svc->validateCall('ask_user_choice', [
            'question' => 'کدام؟',
            'options' => [
                ['label' => 'A', 'tool' => 'task_create', 'args' => ['title' => 'x']],
                ['label' => 'B', 'tool' => 'rm_rf', 'args' => []],
            ],
        ], $user);
        $this->assertFalse($check['ok']);

        // Nested clarification is rejected.
        $check = $svc->validateCall('ask_user_choice', [
            'question' => 'کدام؟',
            'options' => [
                ['label' => 'A', 'tool' => 'ask_user_choice', 'args' => []],
                ['label' => 'B', 'tool' => 'task_create', 'args' => ['title' => 'x']],
            ],
        ], $user);
        $this->assertFalse($check['ok']);

        // Valid case: labels and tools normalized into resolved.
        $check = $svc->validateCall('ask_user_choice', [
            'question' => 'کدام پروژه؟',
            'options' => [
                ['label' => 'الف', 'tool' => 'task_create', 'args' => ['title' => 'T1', 'project_id' => 1]],
                ['label' => 'ب', 'tool' => 'project_create', 'args' => ['name' => 'New']],
            ],
        ], $user);
        $this->assertTrue($check['ok'], $check['error'] ?? '');
        $this->assertCount(2, $check['resolved']['options']);

        // Preview carries the labels for the choice card UI.
        $preview = $svc->preview('ask_user_choice', $check['resolved'], $user);
        $this->assertEquals(['الف', 'ب'], $preview['options']);
        $this->assertEquals('کدام پروژه؟', $preview['question']);
    }

    /* ── choice flow: pick → new pending → confirm executes ── */

    public function test_picking_an_option_creates_a_normal_pending_action(): void
    {
        $user = User::factory()->create();
        $proposal = $this->propose($user, 'ask_user_choice', [
            'question' => 'منظورت کدومه؟',
            'options' => [
                ['label' => 'ساخت تسک جدید', 'tool' => 'task_create', 'args' => ['title' => 'تسک انتخابی']],
                ['label' => 'ساخت پروژه جدید', 'tool' => 'project_create', 'args' => ['name' => 'پروژه انتخابی']],
            ],
        ]);
        $this->assertArrayHasKey('action_id', $proposal, $proposal['error'] ?? '');
        $clarification = AiPendingAction::find($proposal['action_id']);
        $this->assertEquals('ask_user_choice', $clarification->tool);
        $this->assertEquals(AiPendingAction::STATUS_PENDING, $clarification->status);
        $this->assertEquals('ساخت تسک جدید', $clarification->preview['options'][0]);

        // Pick option 0 → the clarification is consumed, a NORMAL task_create
        // pending action is proposed (nothing executed yet).
        $res = $this->actingAs($user)
            ->postJson(route('ai.actions.choice', $clarification), ['option_index' => 0])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('type', 'tool_proposal')
            ->json();
        $this->assertNotEmpty($res['action_id']);
        $this->assertEquals('task_create', $res['tool']);
        $this->assertEquals(AiPendingAction::STATUS_REJECTED, $clarification->fresh()->status);
        $this->assertEquals(0, Task::count(), 'picking an option must not execute anything by itself');

        // The chosen action still goes through the standard confirmation.
        $second = AiPendingAction::find($res['action_id']);
        $this->assertEquals('task_create', $second->tool);
        $this->actingAs($user)
            ->postJson(route('ai.actions.confirm', $second))
            ->assertOk();
        $this->assertEquals(1, Task::where('title', 'تسک انتخابی')->count());
    }

    public function test_choice_is_gated_by_ownership_state_and_bounds(): void
    {
        $user = User::factory()->create();
        $intruder = User::factory()->create();
        $args = [
            'question' => 'کدام؟',
            'options' => [
                ['label' => 'گزینه ۱', 'tool' => 'task_create', 'args' => ['title' => 'X']],
                ['label' => 'گزینه ۲', 'tool' => 'task_create', 'args' => ['title' => 'Y']],
            ],
        ];
        $proposal = $this->propose($user, 'ask_user_choice', $args);
        $clarification = AiPendingAction::find($proposal['action_id']);

        // Foreign user: 403, row untouched.
        $this->actingAs($intruder)
            ->postJson(route('ai.actions.choice', $clarification), ['option_index' => 0])
            ->assertStatus(403);
        $this->assertEquals(AiPendingAction::STATUS_PENDING, $clarification->fresh()->status);

        // Out-of-bounds option: 422, row untouched.
        $this->actingAs($user)
            ->postJson(route('ai.actions.choice', $clarification), ['option_index' => 7])
            ->assertStatus(422);
        $this->assertEquals(AiPendingAction::STATUS_PENDING, $clarification->fresh()->status);

        // Non-clarification rows: 422.
        $taskProposal = $this->propose($user, 'task_create', ['title' => 'Plain']);
        $taskAction = AiPendingAction::find($taskProposal['action_id']);
        $this->actingAs($user)
            ->postJson(route('ai.actions.choice', $taskAction), ['option_index' => 0])
            ->assertStatus(422);

        // Expired clarification: 422 + marked expired.
        $expired = $this->propose($user, 'ask_user_choice', $args);
        $expiredRow = AiPendingAction::find($expired['action_id']);
        AiPendingAction::where('id', $expiredRow->id)->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($user)
            ->postJson(route('ai.actions.choice', $expiredRow->fresh()), ['option_index' => 0])
            ->assertStatus(422);

        // Double-answer race: second pick on an already-consumed row → 422.
        $fresh = $this->propose($user, 'ask_user_choice', $args);
        $row = AiPendingAction::find($fresh['action_id']);
        $this->actingAs($user)->postJson(route('ai.actions.choice', $row), ['option_index' => 1])->assertOk();
        $this->actingAs($user)->postJson(route('ai.actions.choice', $row), ['option_index' => 1])->assertStatus(422);
    }

    public function test_choice_option_with_foreign_args_is_rejected_by_revalidation(): void
    {
        $user = User::factory()->create();
        $foreign = Project::create([
            'user_id' => User::factory()->create()->id, 'name' => 'Not yours', 'status' => 'in_progress', 'type' => 'project', 'sort_order' => 0,
        ]);
        $proposal = $this->propose($user, 'ask_user_choice', [
            'question' => 'کدام؟',
            'options' => [
                // A model trying to hide a foreign target behind a benign label:
                ['label' => 'بی‌گناه', 'tool' => 'task_create', 'args' => ['title' => 'X', 'project_id' => $foreign->id]],
                ['label' => 'دیگری', 'tool' => 'task_create', 'args' => ['title' => 'Y']],
            ],
        ]);
        $clarification = AiPendingAction::find($proposal['action_id']);

        $res = $this->actingAs($user)
            ->postJson(route('ai.actions.choice', $clarification), ['option_index' => 0])
            ->assertOk()
            ->json();

        // The chosen option is re-validated through the full pipeline: the
        // foreign project_id must surface as an error, never a pending action.
        $this->assertArrayHasKey('error', $res);
        $this->assertArrayNotHasKey('action_id', $res);
        $this->assertEquals(0, Task::count());
    }
}
