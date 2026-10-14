<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Routine;
use App\Models\RoutineLog;
use App\Models\User;
use App\Services\AiToolService;
use App\Services\AiTooling\ToolError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3, Step 1 — Entity Resolution.
 *
 * No important resolver may silently pick the first match on ambiguity.
 * Six states per resolver: exact-unique, fuzzy-unique, multi-match,
 * missing, foreign (unauthorized), invalid/stale id.
 */
class AiEntityResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function trackedRoutine(User $user, string $title): Routine
    {
        return Routine::factory()->create([
            'user_id' => $user->id,
            'frequency' => 'daily',
            'title' => $title,
            'tracking_mode' => 'value',
            'value_kind' => 'number',
        ]);
    }

    private function logCall(User $user, array $args): array
    {
        return (new AiToolService)->validateCall('routine_log', $args + ['value' => 5], $user);
    }

    /* ── routine: exact-unique ── */

    public function test_routine_log_resolves_exact_title(): void
    {
        $user = User::factory()->create();
        $routine = $this->trackedRoutine($user, 'Morning Run');

        $check = $this->logCall($user, ['routine' => 'Morning Run']);

        $this->assertTrue($check['ok'], $check['error'] ?? '');
        $this->assertSame($routine->id, $check['resolved']['routine_id']);
    }

    /* ── routine: fuzzy-unique ── */

    public function test_routine_log_resolves_fuzzy_unique_title(): void
    {
        $user = User::factory()->create();
        $routine = $this->trackedRoutine($user, 'Morning Run');

        $check = $this->logCall($user, ['routine' => 'Morning']);

        $this->assertTrue($check['ok'], $check['error'] ?? '');
        $this->assertSame($routine->id, $check['resolved']['routine_id']);
    }

    /* ── routine: multi-match → CONFLICT, never auto-pick ── */

    public function test_routine_log_ambiguous_title_conflicts_without_side_effect(): void
    {
        $user = User::factory()->create();
        $first = $this->trackedRoutine($user, 'Evening Walk Park');
        $second = $this->trackedRoutine($user, 'Evening Walk Hill');

        $check = $this->logCall($user, ['routine' => 'Evening Walk']);

        $this->assertFalse($check['ok']);
        $this->assertSame(ToolError::CONFLICT, $check['code']);
        $this->assertStringContainsString('Multiple routines match', $check['error']);
        $this->assertSame(0, RoutineLog::count(), 'ambiguity must not log anything anywhere');
        $this->assertNotEquals($first->id, $second->id);
    }

    /* ── routine: missing / foreign / invalid ── */

    public function test_routine_log_missing_title_is_not_found(): void
    {
        $user = User::factory()->create();

        $check = $this->logCall($user, ['routine' => 'No Such Routine']);

        $this->assertFalse($check['ok']);
        $this->assertSame(ToolError::NOT_FOUND, $check['code']);
    }

    public function test_routine_log_foreign_id_does_not_leak_existence(): void
    {
        $user = User::factory()->create();
        $foreign = $this->trackedRoutine(User::factory()->create(), 'Secret Routine');

        $check = $this->logCall($user, ['routine_id' => $foreign->id]);

        $this->assertFalse($check['ok']);
        $this->assertSame(ToolError::NOT_FOUND, $check['code']);
        $this->assertStringNotContainsString('Secret Routine', $check['error']);
    }

    public function test_routine_log_stale_id_is_not_found(): void
    {
        $user = User::factory()->create();

        $check = $this->logCall($user, ['routine_id' => 999999]);

        $this->assertFalse($check['ok']);
        $this->assertSame(ToolError::NOT_FOUND, $check['code']);
    }

    public function test_routine_log_without_locator_is_validation_error(): void
    {
        $user = User::factory()->create();

        $check = $this->logCall($user, []);

        $this->assertFalse($check['ok']);
        $this->assertSame(ToolError::VALIDATION_ERROR, $check['code']);
    }

    /* ── routine step: ambiguity ── */

    public function test_routine_log_ambiguous_step_conflicts(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create([
            'user_id' => $user->id,
            'frequency' => 'weekly',
            'title' => 'PULL',
            'tracking_mode' => 'sets',
        ]);
        $routine->checklistItems()->create(['user_id' => $user->id, 'name' => 'Pull-up wide', 'sort_order' => 0, 'target_sets' => 2]);
        $routine->checklistItems()->create(['user_id' => $user->id, 'name' => 'Pull-up close', 'sort_order' => 1, 'target_sets' => 2]);

        $check = (new AiToolService)->validateCall('routine_log', [
            'routine_id' => $routine->id, 'value' => 1, 'item' => 'Pull-up',
        ], $user);

        $this->assertFalse($check['ok']);
        $this->assertSame(ToolError::CONFLICT, $check['code']);
        $this->assertStringContainsString('Multiple steps match', $check['error']);
    }

    public function test_routine_log_unique_step_resolves(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create([
            'user_id' => $user->id,
            'frequency' => 'weekly',
            'title' => 'PULL',
            'tracking_mode' => 'sets',
        ]);
        $item = $routine->checklistItems()->create(['user_id' => $user->id, 'name' => 'Pull-up', 'sort_order' => 0, 'target_sets' => 2]);

        $check = (new AiToolService)->validateCall('routine_log', [
            'routine_id' => $routine->id, 'value' => 1, 'item' => 'Pull',
        ], $user);

        $this->assertTrue($check['ok'], $check['error'] ?? '');
        $this->assertSame($item->id, $check['resolved']['item_id']);
    }

    /* ── plan attach: exact attaches, fuzzy creates new ── */

    public function test_plan_attaches_exact_project_name(): void
    {
        $user = User::factory()->create();
        $existing = Project::factory()->create(['user_id' => $user->id, 'name' => 'Website']);

        $check = (new AiToolService)->validateCall('plan_propose', [
            'title' => 'Launch',
            'projects' => [['name' => 'Website', 'tasks' => [['title' => 'T1']]]],
        ], $user);

        $this->assertTrue($check['ok'], $check['error'] ?? '');
        $node = $check['resolved']['structure']['projects'][0];
        $this->assertTrue($node['attached']);
        $this->assertSame($existing->id, $node['project_id']);
    }

    public function test_plan_does_not_attach_fuzzy_project_name(): void
    {
        $user = User::factory()->create();
        Project::factory()->create(['user_id' => $user->id, 'name' => 'Website Redesign']);

        $check = (new AiToolService)->validateCall('plan_propose', [
            'title' => 'Launch',
            'projects' => [['name' => 'Website', 'tasks' => [['title' => 'T1']]]],
        ], $user);

        $this->assertTrue($check['ok'], $check['error'] ?? '');
        $node = $check['resolved']['structure']['projects'][0];
        $this->assertFalse($node['attached'] ?? false, 'a merely similar project must not be silently picked');
        $this->assertEmpty($node['project_id'] ?? null);
    }
}
