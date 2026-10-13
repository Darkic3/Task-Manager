<?php

namespace Tests\Feature;

use App\Models\AiPendingAction;
use App\Models\AiPlan;
use App\Models\Project;
use App\Models\User;
use App\Services\AiToolService;
use App\Services\AiTooling\ToolError;
use App\Services\AiTooling\ToolPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Project member selection must never guess.
 *
 * A collaborator reference that matches more than one real user is refused
 * with `ambiguous_target` + bounded candidates, so the agent asks the user
 * instead of attaching the wrong person. The chosen id is re-validated on the
 * server both at proposal time and at execution time.
 */
class AiProjectMemberSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function svc(): AiToolService
    {
        return new AiToolService;
    }

    /**
     * The acting owner. Named deterministically: UserFactory uses fake()->name(),
     * so a random owner name could otherwise leak into a partial-name search
     * and make these assertions order-dependent.
     */
    private function owner(): User
    {
        return User::factory()->create(['name' => 'ZZZ Owner NoMatch']);
    }

    private function projectFor(User $owner): Project
    {
        return Project::create(['user_id' => $owner->id, 'name' => 'Ù¾Ø±ÙˆÚ˜Ù‡']);
    }

    /* â”€â”€ unambiguous resolution â”€â”€ */

    public function test_exact_email_resolves(): void
    {
        $owner = $this->owner();
        $member = User::factory()->create(['name' => 'Hossein Faramarzi', 'email' => 'hossein@example.com']);
        $this->projectFor($owner);

        $check = $this->svc()->validateCall('project_add_member', [
            'project' => 'Ù¾Ø±ÙˆÚ˜Ù‡', 'user' => 'hossein@example.com',
        ], $owner);

        $this->assertTrue($check['ok']);
        $this->assertSame($member->id, $check['resolved']['member_id']);
    }

    public function test_exact_full_name_resolves(): void
    {
        $owner = $this->owner();
        $member = User::factory()->create(['name' => 'Hossein Faramarzi']);
        $this->projectFor($owner);

        $check = $this->svc()->validateCall('project_add_member', [
            'project' => 'Ù¾Ø±ÙˆÚ˜Ù‡', 'user' => 'Hossein Faramarzi',
        ], $owner);

        $this->assertTrue($check['ok']);
        $this->assertSame($member->id, $check['resolved']['member_id']);
    }

    public function test_exact_user_id_resolves(): void
    {
        $owner = $this->owner();
        $member = User::factory()->create(['name' => 'Sara Ahmadi']);
        $this->projectFor($owner);

        $check = $this->svc()->validateCall('project_add_member', [
            'project' => 'Ù¾Ø±ÙˆÚ˜Ù‡', 'user' => (string) $member->id,
        ], $owner);

        $this->assertTrue($check['ok']);
        $this->assertSame($member->id, $check['resolved']['member_id']);
    }

    public function test_unambiguous_partial_name_resolves(): void
    {
        $owner = $this->owner();
        $member = User::factory()->create(['name' => 'Hossein Faramarzi']);
        $this->projectFor($owner);

        $check = $this->svc()->validateCall('project_add_member', [
            'project' => 'Ù¾Ø±ÙˆÚ˜Ù‡', 'user' => 'Faramarzi',
        ], $owner);

        $this->assertTrue($check['ok']);
        $this->assertSame($member->id, $check['resolved']['member_id']);
    }

    /* â”€â”€ ambiguity â”€â”€ */

    public function test_ambiguous_name_is_refused_with_candidates(): void
    {
        $owner = $this->owner();
        User::factory()->create(['name' => 'Ali Rezaei', 'email' => 'ali1@example.com']);
        User::factory()->create(['name' => 'Ali Moradi', 'email' => 'ali2@example.com']);
        $this->projectFor($owner);

        $check = $this->svc()->validateCall('project_add_member', [
            'project' => 'Ù¾Ø±ÙˆÚ˜Ù‡', 'user' => 'Ali',
        ], $owner);

        $this->assertFalse($check['ok'], 'An ambiguous reference must never propose an action');
        $this->assertSame(ToolError::AMBIGUOUS_TARGET, $check['code']);
        $this->assertArrayHasKey('candidates', $check);
        $this->assertCount(2, $check['candidates']);
        foreach ($check['candidates'] as $c) {
            $this->assertArrayHasKey('id', $c);
            $this->assertArrayHasKey('name', $c);
            // no email disclosure in the candidate list
            $this->assertArrayNotHasKey('email', $c);
        }
        // No member was attached.
        $this->assertDatabaseCount('project_teams', 0);
    }

    public function test_duplicate_exact_names_are_ambiguous(): void
    {
        $owner = $this->owner();
        User::factory()->create(['name' => 'Hossein Faramarzi']);
        User::factory()->create(['name' => 'Hossein Faramarzi']);
        $this->projectFor($owner);

        $check = $this->svc()->validateCall('project_add_member', [
            'project' => 'Ù¾Ø±ÙˆÚ˜Ù‡', 'user' => 'Hossein Faramarzi',
        ], $owner);

        $this->assertFalse($check['ok']);
        $this->assertSame(ToolError::AMBIGUOUS_TARGET, $check['code']);
        $this->assertDatabaseCount('project_teams', 0);
    }

    public function test_candidate_list_is_bounded(): void
    {
        $owner = $this->owner();
        for ($i = 0; $i < 9; $i++) {
            User::factory()->create(['name' => 'Hossein Test '.$i]);
        }
        $this->projectFor($owner);

        $check = $this->svc()->validateCall('project_add_member', [
            'project' => 'Ù¾Ø±ÙˆÚ˜Ù‡', 'user' => 'Hossein',
        ], $owner);

        $this->assertFalse($check['ok']);
        $this->assertSame(ToolError::AMBIGUOUS_TARGET, $check['code']);
        $this->assertLessThanOrEqual(AiToolService::MEMBER_CANDIDATE_LIMIT, count($check['candidates']));
    }

    public function test_ambiguous_error_message_does_not_leak_emails(): void
    {
        $owner = $this->owner();
        User::factory()->create(['name' => 'Ali Rezaei', 'email' => 'ali1@example.com']);
        User::factory()->create(['name' => 'Ali Moradi', 'email' => 'ali2@example.com']);
        $this->projectFor($owner);

        $check = $this->svc()->validateCall('project_add_member', [
            'project' => 'Ù¾Ø±ÙˆÚ˜Ù‡', 'user' => 'Ali',
        ], $owner);

        $this->assertStringNotContainsString('ali1@example.com', $check['error']);
        $this->assertStringNotContainsString('ali2@example.com', $check['error']);
    }

    /* â”€â”€ no result â”€â”€ */

    public function test_unknown_reference_is_not_found_and_offers_no_candidates(): void
    {
        $owner = $this->owner();
        $this->projectFor($owner);

        $check = $this->svc()->validateCall('project_add_member', [
            'project' => 'Ù¾Ø±ÙˆÚ˜Ù‡', 'user' => 'ghost-nobody-xyz',
        ], $owner);

        $this->assertFalse($check['ok']);
        // Behaviour preserved from before the fix: a plain "not found".
        $this->assertSame(ToolError::VALIDATION_ERROR, $check['code']);
        $this->assertStringContainsString('not found', $check['error']);
        $this->assertArrayNotHasKey('candidates', $check);
        $this->assertDatabaseCount('project_teams', 0);
    }

    /* â”€â”€ authorization / ownership â”€â”€ */

    public function test_foreign_project_is_rejected(): void
    {
        $owner = $this->owner();
        $victim = User::factory()->create();
        Project::create(['user_id' => $victim->id, 'name' => 'Ù¾Ø±ÙˆÚ˜Ù‡â€ŒÛŒ Ø¯ÛŒÚ¯Ø±ÛŒ']);
        $member = User::factory()->create(['email' => 'm@example.com']);

        $check = $this->svc()->validateCall('project_add_member', [
            'project' => 'Ù¾Ø±ÙˆÚ˜Ù‡â€ŒÛŒ Ø¯ÛŒÚ¯Ø±ÛŒ', 'user' => 'm@example.com',
        ], $owner);

        $this->assertFalse($check['ok']);
        $this->assertSame(ToolError::NOT_FOUND, $check['code']);
    }

    public function test_owner_cannot_be_added_to_own_project(): void
    {
        $owner = $this->owner();
        $this->projectFor($owner);

        $check = $this->svc()->validateCall('project_add_member', [
            'project' => 'Ù¾Ø±ÙˆÚ˜Ù‡', 'user' => (string) $owner->id,
        ], $owner);

        $this->assertFalse($check['ok']);
        $this->assertDatabaseCount('project_teams', 0);
    }

    /* â”€â”€ execution-time re-validation of the chosen id â”€â”€ */

    public function test_chosen_member_id_survives_execution_revalidation(): void
    {
        $owner = $this->owner();
        $member = User::factory()->create(['email' => 'hossein@example.com']);
        $this->projectFor($owner);

        $check = $this->svc()->validateCall('project_add_member', [
            'project' => 'Ù¾Ø±ÙˆÚ˜Ù‡', 'user' => 'hossein@example.com',
        ], $owner);

        // Exactly what gets persisted as AiPendingAction::args
        $storedArgs = $check['resolved'];

        $revalidated = ToolPipeline::revalidateForExecute($owner, 'project_add_member', $storedArgs);

        $this->assertTrue($revalidated['ok'], json_encode($revalidated, JSON_UNESCAPED_UNICODE));
        $this->assertSame($member->id, $revalidated['resolved']['member_id']);
    }

    public function test_deleted_member_is_rejected_at_execution(): void
    {
        $owner = $this->owner();
        $member = User::factory()->create(['email' => 'gone@example.com']);
        $this->projectFor($owner);

        $check = $this->svc()->validateCall('project_add_member', [
            'project' => 'Ù¾Ø±ÙˆÚ˜Ù‡', 'user' => 'gone@example.com',
        ], $owner);
        $this->assertTrue($check['ok']);

        $member->delete();

        $result = $this->svc()->execute('project_add_member', $check['resolved'], $owner);

        $this->assertFalse($result['ok']);
        $this->assertSame(ToolError::NOT_FOUND, $result['code']);
        $this->assertDatabaseCount('project_teams', 0);
    }

    public function test_plan_build_never_guesses_an_ambiguous_member(): void
    {
        $owner = $this->owner();
        User::factory()->create(['name' => 'Ali Rezaei']);
        User::factory()->create(['name' => 'Ali Moradi']);

        $svc = $this->svc();
        $check = $svc->validateCall('plan_propose', [
            'title' => 'Ù¾Ù„Ù†',
            'projects' => [[
                'name' => 'Ù¾Ø±ÙˆÚ˜Ù‡â€ŒÛŒ Ø¬Ø¯ÛŒØ¯',
                'members' => ['Ali', 'ghost-nobody'],
            ]],
        ], $owner);

        $this->assertTrue($check['ok'], json_encode($check, JSON_UNESCAPED_UNICODE));

        $plan = AiPlan::create([
            'user_id' => $owner->id,
            'title' => 'Ù¾Ù„Ù†',
            'structure' => $check['resolved']['structure'],
            'phases' => $svc->buildPlanPhases($check['resolved']['structure']),
            'status' => AiPlan::STATUS_PROPOSED,
            'current_phase' => 0,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);

        $this->actingAs($owner)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();
        $this->actingAs($owner)
            ->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 0, 'run_all' => true])
            ->assertOk();

        // Ambiguous and unknown refs are both skipped and reported, never guessed.
        $this->assertDatabaseCount('project_teams', 0);
        $this->assertDatabaseHas('projects', ['user_id' => $owner->id, 'name' => 'Ù¾Ø±ÙˆÚ˜Ù‡â€ŒÛŒ Ø¬Ø¯ÛŒØ¯']);
    }

    public function test_pipeline_keep_candidates_for_ambiguous_reference(): void
    {
        $owner = $this->owner();
        User::factory()->create(['name' => 'Ali Rezaei']);
        User::factory()->create(['name' => 'Ali Moradi']);
        $this->projectFor($owner);

        $check = ToolPipeline::validateForProposal($owner, 'project_add_member', [
            'project' => 'Ù¾Ø±ÙˆÚ˜Ù‡', 'user' => 'Ali',
        ]);

        $this->assertFalse($check['ok']);
        $this->assertSame(ToolError::AMBIGUOUS_TARGET, $check['code']);
        $this->assertNotEmpty($check['candidates'], 'candidates must survive the pipeline for ask_user_choice');
    }
}
