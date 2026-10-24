<?php

namespace Tests\Feature;

use App\Http\Controllers\AiChatController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPlanDirectBuildTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_prompt_skips_search_on_explicit_full_structure_paste(): void
    {
        $user = User::factory()->create();
        $controller = new AiChatController(app(\App\Services\AiProviderService::class));
        $ref = new \ReflectionMethod($controller, 'buildMessages');

        $agent = $ref->invoke($controller, $user, 'CTX', [], 'hi', 'agent');
        $system = $agent[0]['content'];
        $this->assertStringContainsString('EXCEPTION', $system);
        $this->assertStringContainsString('do NOT call workspace_search first', $system);
        $this->assertStringContainsString('STUDY BUILDS', $system);

        $chat = $ref->invoke($controller, $user, 'CTX', [], 'hi', 'chat');
        $this->assertStringNotContainsString('EXCEPTION — explicit full-structure paste', $chat[0]['content']);
    }

    public function test_explicit_no_search_build_routes_to_plan(): void
    {
        $r = \App\Services\AiIntentRouter::route('بدون جستجو، مستقیم به صورت پلن مرحله‌ای بساز — بسته اول: پروژه کتاب روان‌شناسی رشد + فصل‌های ۱ تا ۳');
        $this->assertSame(\App\Services\AiIntentRouter::INTENT_PLAN, $r['intent']);
        $this->assertSame(\App\Services\AiIntentRouter::CONF_HIGH, $r['confidence']);
    }

    public function test_plan_card_renders_understanding_header(): void
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get(route('ai.index'))->assertOk()->getContent();

        $this->assertStringContainsString('lina-plan-understand', $html);
        $this->assertStringContainsString('می‌سازم (بعد از تأیید نهایی تو)', $html);
    }

    public function test_search_confirm_auto_continues_build_and_prompt_covers_it(): void
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get(route('ai.index'))->assertOk()->getContent();

        // Frontend auto-sends the follow-up after a flagged search confirm.
        $this->assertStringContainsString('auto_continue', $html);

        // Prompt: explicit ids are authoritative + continue after search.
        $controller = new AiChatController(app(\App\Services\AiProviderService::class));
        $ref = new \ReflectionMethod($controller, 'buildMessages');
        $agent = $ref->invoke($controller, $user, 'CTX', [], 'hi', 'agent');
        $this->assertStringContainsString('that id is authoritative', $agent[0]['content']);
        $this->assertStringContainsString('AFTER SEARCH, CONTINUE', $agent[0]['content']);
        // Listed project names count as resolved — no search detour.
        $this->assertStringContainsString('COUNTS AS RESOLVED', $agent[0]['content']);
    }

    public function test_version_endpoint_tracks_asset_changes(): void
    {
        $user = User::factory()->create();
        $this->get(route('ai.version'))->assertRedirect(route('login'));

        $res = $this->actingAs($user)->get(route('ai.version'))->assertOk();
        $v = $res->json('v');
        $this->assertNotEmpty($v);
        $this->assertSame(\App\Http\Controllers\AiChatController::chatAssetVersion(), $v);

        // Banner wiring present in the page.
        $html = $this->actingAs($user)->get(route('ai.index'))->assertOk()->getContent();
        $this->assertStringContainsString('linaStaleBanner', $html);
        $this->assertStringContainsString('LINA_ASSET_V', $html);
    }
}
