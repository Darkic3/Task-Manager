<?php

namespace Tests\Feature;

use App\Http\Controllers\AiChatController;
use App\Models\AiSetting;
use App\Models\User;
use App\Services\AiIntentRouter;
use App\Services\AiProviderService;
use App\Services\AiTooling\ToolCapabilityRegistry;
use App\Services\AiTooling\ToolCapabilityRouter;
use App\Services\AiTooling\ToolError;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tool/Capability Routing for Lina.
 *
 * Only capabilities relevant to (intent + context + provider) reach the
 * model. Restriction is server-side (payload + proposal-time allowlist),
 * never prompt text alone. Routing never replaces authorization,
 * input validation or the confirmation flow.
 */
class AiToolCapabilityRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function agentContext(string $providerType = 'openai'): array
    {
        return ['mode' => 'agent', 'provider_type' => $providerType];
    }

    /* ── Registry documents the current capabilities ── */

    public function test_registry_covers_every_tool(): void
    {
        foreach (AiToolService::TOOLS as $tool) {
            $cap = ToolCapabilityRegistry::capabilityOf($tool);
            $this->assertNotNull($cap, $tool);
            $this->assertNotEmpty($cap['category'], $tool);
            $this->assertNotEmpty($cap['desc'], $tool);
            $this->assertNotEmpty($cap['access'], $tool);
            $this->assertSame(AiToolService::riskOf($tool), $cap['op'], $tool);
            $this->assertSame(
                AiToolService::requiresConfirmation($tool),
                $cap['needs_confirmation'],
                $tool
            );
        }
        $this->assertCount(22, AiToolService::TOOLS);
        $this->assertCount(22, ToolCapabilityRegistry::CAPABILITIES);
    }

    public function test_only_report_generate_is_read_only(): void
    {
        $readOnly = array_values(array_filter(
            AiToolService::TOOLS,
            fn ($t) => AiToolService::isReadOnly($t)
        ));
        $this->assertSame(['report_generate'], $readOnly);
        $this->assertSame(['report_generate'], ToolCapabilityRegistry::SAFE_FALLBACK);
    }

    /* ── Routing: unrelated tools are not sent ── */

    public function test_single_task_request_hides_plan_and_workout(): void
    {
        $route = AiIntentRouter::route('یه تسک بساز به نام خرید نون');
        $this->assertSame(AiIntentRouter::INTENT_MUTATION, $route['intent']);

        $sel = ToolCapabilityRouter::select($route, $this->agentContext());
        $this->assertContains('task_create', $sel['tools']);
        $this->assertNotContains('plan_propose', $sel['tools']);
        $this->assertNotContains('workout_plan_propose', $sel['tools']);
        $this->assertNotContains('routine_create', $sel['tools']);
        $this->assertNotContains('project_add_member', $sel['tools']);
    }

    public function test_reminder_request_only_exposes_reminder_and_report(): void
    {
        $route = AiIntentRouter::route('یادم بنداز فردا ساعت ۹ جلسه دارم');
        $sel = ToolCapabilityRouter::select($route, $this->agentContext());

        $this->assertContains('reminder_create', $sel['tools']);
        $this->assertContains('report_generate', $sel['tools']);
        $this->assertNotContains('task_create', $sel['tools']);
        $this->assertNotContains('plan_propose', $sel['tools']);
    }

    public function test_report_request_only_exposes_read_only_tool(): void
    {
        $route = AiIntentRouter::route('گزارش بده');
        $sel = ToolCapabilityRouter::select($route, $this->agentContext());

        $this->assertSame(['report_generate'], $sel['tools']);
    }

    public function test_workout_request_only_exposes_workout_tool(): void
    {
        $route = AiIntentRouter::route('برنامه تمرینی هفته بعد رو بساز');
        $sel = ToolCapabilityRouter::select($route, $this->agentContext());

        $this->assertSame(['workout_plan_propose'], $sel['tools']);
    }

    /* ── Composite (multi-category) requests ── */

    public function test_composite_task_reminder_note_unions_categories(): void
    {
        $route = AiIntentRouter::route('یه تسک بساز و یه یادآوری برای فردا بذار و یه یادداشت هم بنویس');
        $this->assertSame(AiIntentRouter::INTENT_MUTATION, $route['intent']);
        $this->assertContains('task', $route['entities']['objects']);
        $this->assertContains('reminder', $route['entities']['objects']);
        $this->assertContains('note', $route['entities']['objects']);

        $sel = ToolCapabilityRouter::select($route, $this->agentContext());
        foreach (['task_create', 'reminder_create', 'note_create', 'report_generate'] as $tool) {
            $this->assertContains($tool, $sel['tools'], $tool);
        }
        // Heavy builders stay out of single-item mutations.
        $this->assertNotContains('plan_propose', $sel['tools']);
        $this->assertNotContains('workout_plan_propose', $sel['tools']);
    }

    public function test_note_attachment_widens_to_note_category(): void
    {
        $route = AiIntentRouter::route('این تسک رو کامل کن');
        $plain = ToolCapabilityRouter::select($route, $this->agentContext());
        $withNote = ToolCapabilityRouter::select($route, $this->agentContext() + ['has_note_attachments' => true]);

        $this->assertContains('task_complete', $plain['tools']);
        $this->assertContains('note_create', $withNote['tools']);
    }

    public function test_plan_build_stays_fail_open(): void
    {
        $route = AiIntentRouter::route('سه تا پروژه بساز: فروشگاه، باشگاه، درس');
        $this->assertSame(AiIntentRouter::INTENT_PLAN, $route['intent']);

        $sel = ToolCapabilityRouter::select($route, $this->agentContext());
        $this->assertNull($sel['tools']);
    }

    /* ── Unclear intent → safe set + clarification ── */

    public function test_ambiguous_intent_gets_safe_set_and_clarification(): void
    {
        $route = AiIntentRouter::route('این');
        $this->assertSame(AiIntentRouter::INTENT_AMBIGUOUS, $route['intent']);
        $this->assertTrue($route['requires_clarification']);
        $this->assertNotEmpty($route['clarification_question']);

        $sel = ToolCapabilityRouter::select($route, $this->agentContext());
        $this->assertSame(['report_generate'], $sel['tools']);

        $defs = ToolCapabilityRouter::definitionsFor($route, new AiToolService, $this->agentContext());
        $this->assertCount(1, $defs);
        $this->assertSame('report_generate', $defs[0]['function']['name']);
    }

    /* ── Provider / mode gating is preserved ── */

    public function test_chat_mode_sends_no_tools(): void
    {
        $route = AiIntentRouter::route('یه تسک بساز');
        $sel = ToolCapabilityRouter::select($route, ['mode' => 'chat', 'provider_type' => 'openai']);
        $this->assertSame([], $sel['tools']);
    }

    public function test_non_openai_provider_sends_no_tools(): void
    {
        $route = AiIntentRouter::route('یه تسک بساز');
        foreach (['gemini', 'anthropic'] as $type) {
            $sel = ToolCapabilityRouter::select($route, ['mode' => 'agent', 'provider_type' => $type]);
            $this->assertSame([], $sel['tools'], $type);
        }
        $this->assertFalse(ToolCapabilityRouter::supportsTools('gemini'));
        $this->assertTrue(ToolCapabilityRouter::supportsTools('openai'));
    }

    /* ── Server-side visibility enforcement ── */

    public function test_off_route_tool_is_not_allowed(): void
    {
        $route = AiIntentRouter::route('گزارش بده');
        $allowed = ToolCapabilityRouter::allowedNames($route, $this->agentContext());

        $this->assertTrue(ToolCapabilityRouter::isAllowed('report_generate', $allowed));
        $this->assertFalse(ToolCapabilityRouter::isAllowed('task_create', $allowed));
        $this->assertFalse(ToolCapabilityRouter::isAllowed('plan_propose', $allowed));
        $this->assertFalse(ToolCapabilityRouter::isAllowed('no_such_tool', $allowed));
        // Null allowlist (fail-open) permits any KNOWN tool, never unknown ones.
        $this->assertTrue(ToolCapabilityRouter::isAllowed('task_create', null));
        $this->assertFalse(ToolCapabilityRouter::isAllowed('no_such_tool', null));
    }

    public function test_off_route_model_call_is_rejected_end_to_end(): void
    {
        $user = User::factory()->create();
        AiSetting::create([
            'user_id' => $user->id, 'default_provider' => 'openai',
            'openai_key' => 'sk-test-key', 'openai_model' => 'gpt-4o-mini',
        ]);
        // Report intent: only report_generate is visible. The model tries
        // task_create anyway → must be rejected with tool_not_routed and
        // nothing may be created.
        Http::fake(fn () => Http::response(['choices' => [[
            'message' => [
                'content' => '',
                'tool_calls' => [[
                    'id' => 'call_1', 'type' => 'function',
                    'function' => ['name' => 'task_create', 'arguments' => json_encode(['title' => 'Sneaky'])],
                ]],
            ],
        ]]], 200));

        $res = $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'گزارش بده', 'mode' => 'agent'])->assertOk();
        $proposals = $res->json('proposals');
        $this->assertNotEmpty($proposals);
        $this->assertSame(ToolError::NOT_ROUTED, $proposals[0]['code']);
        $this->assertDatabaseMissing('tasks', ['title' => 'Sneaky']);
        $this->assertSame(0, \App\Models\AiPendingAction::where('user_id', $user->id)->count());
    }

    public function test_routed_tool_still_passes_pipeline_and_confirmation(): void
    {
        $user = User::factory()->create();
        AiSetting::create([
            'user_id' => $user->id, 'default_provider' => 'openai',
            'openai_key' => 'sk-test-key', 'openai_model' => 'gpt-4o-mini',
        ]);
        Http::fake(fn () => Http::response(['choices' => [[
            'message' => [
                'content' => '',
                'tool_calls' => [[
                    'id' => 'call_1', 'type' => 'function',
                    'function' => ['name' => 'task_create', 'arguments' => json_encode(['title' => 'Routed task'])],
                ]],
            ],
        ]]], 200));

        // task_mutation intent: task_create IS visible → proposal created,
        // but nothing executes before explicit user confirmation.
        $res = $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'یه تسک بساز به نام Routed task', 'mode' => 'agent'])->assertOk();
        $this->assertArrayHasKey('action_id', $res->json('proposal'));
        $this->assertDatabaseMissing('tasks', ['title' => 'Routed task']);
    }

    public function test_unrelated_tools_never_reach_provider_payload(): void
    {
        $user = User::factory()->create();
        AiSetting::create([
            'user_id' => $user->id, 'default_provider' => 'openai',
            'openai_key' => 'sk-test-key', 'openai_model' => 'gpt-4o-mini',
        ]);
        Http::fake(fn () => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200));

        $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'یه تسک بساز به نام نون', 'mode' => 'agent'])->assertOk();

        Http::assertSent(function ($request) {
            $payload = $request->data();
            if (! isset($payload['tools'])) {
                return false;
            }
            $names = collect($payload['tools'])->map(fn ($t) => $t['function']['name'] ?? null)->all();

            return in_array('task_create', $names, true)
                && ! in_array('plan_propose', $names, true)
                && ! in_array('workout_plan_propose', $names, true)
                && ! in_array('routine_create', $names, true)
                && ! in_array('project_add_member', $names, true);
        });
    }

    /* ── Guards that must survive routing ── */

    public function test_turn_cap_and_loop_guard_survive_routing(): void
    {
        $calls = [];
        for ($i = 0; $i < 25; $i++) {
            $calls[] = ['name' => 'task_create', 'arguments' => ['title' => 'T'.$i]];
        }
        $filtered = \App\Services\AiTooling\ToolPipeline::filterTurnCalls($calls);
        $this->assertCount(20, $filtered['kept']);
        $this->assertNotEmpty($filtered['dropped']);

        $loop = array_fill(0, 5, ['name' => 'task_create', 'arguments' => ['title' => 'Same']]);
        $filteredLoop = \App\Services\AiTooling\ToolPipeline::filterTurnCalls($loop);
        $this->assertCount(3, $filteredLoop['kept']);
        $this->assertSame(ToolError::LOOP_DETECTED, $filteredLoop['dropped'][0]['code']);
    }

    public function test_streaming_rejects_off_route_tools(): void
    {
        $controller = new AiChatController(app(AiProviderService::class));
        $user = User::factory()->create();
        $conv = \App\Models\AiConversation::create(['user_id' => $user->id, 'label' => 't']);
        $flush = function () {};
        $accum = [['name' => 'task_create', 'arguments' => json_encode(['title' => 'Sneaky stream'])]];
        $allowed = ['report_generate'];

        ob_start();
        $controller->emitToolProposals($accum, $user->id, $conv->id, $flush, 'rid-test', $allowed);
        $out = ob_get_clean();

        $this->assertStringContainsString(ToolError::NOT_ROUTED, $out);
        $this->assertDatabaseMissing('tasks', ['title' => 'Sneaky stream']);
        $this->assertSame(0, \App\Models\AiPendingAction::where('user_id', $user->id)->count());
    }

    /* ── Token comparison ── */

    public function test_routing_saves_tokens_on_narrow_intents(): void
    {
        $svc = new AiToolService;
        $ctx = $this->agentContext();

        $cmp = ToolCapabilityRouter::tokenComparison(AiIntentRouter::route('گزارش بده'), $svc, $ctx);
        $this->assertGreaterThan(0, $cmp['full']);
        $this->assertLessThan($cmp['full'], $cmp['routed']);
        $this->assertGreaterThan(50, $cmp['saved_pct']);

        $plan = ToolCapabilityRouter::tokenComparison(AiIntentRouter::route('سه تا پروژه بساز: فروشگاه، باشگاه، درس'), $svc, $ctx);
        $this->assertSame(0, $plan['saved']);

        $mutation = ToolCapabilityRouter::tokenComparison(AiIntentRouter::route('یه تسک بساز'), $svc, $ctx);
        $this->assertLessThan($mutation['full'], $mutation['routed']);
    }
}
