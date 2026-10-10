<?php

namespace Tests\Feature;

use App\Http\Controllers\AiChatController;
use App\Models\AiSetting;
use App\Models\Task;
use App\Models\User;
use App\Services\AiContextEngine;
use App\Services\AiIntentRouter;
use App\Services\AiProviderService;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiIntentRouterTest extends TestCase
{
    use RefreshDatabase;

    private function route(string $msg): array
    {
        return AiIntentRouter::route($msg);
    }

    private function assertShape(array $route): void
    {
        $this->assertContains($route['intent'], AiIntentRouter::INTENTS);
        $this->assertContains($route['confidence'], ['high', 'medium', 'low']);
        $this->assertArrayHasKey('objects', $route['entities']);
        $this->assertArrayHasKey('date_mentioned', $route['entities']);
        $this->assertArrayHasKey('list_detected', $route['entities']);
        $this->assertArrayHasKey('question', $route['entities']);
        $this->assertArrayHasKey('requires_clarification', $route);
        $this->assertArrayHasKey('context_profile', $route);
        $this->assertArrayHasKey('tool_filter', $route);
    }

    /* ── general chat (FA + EN) ──────────────────────────── */

    public function test_persian_greeting_is_general_high(): void
    {
        foreach (['سلام', 'سلام!', 'درود', 'صبح بخیر', 'مرسی', 'ممنون', 'خداحافظ'] as $msg) {
            $r = $this->route($msg);
            $this->assertSame('general_chat', $r['intent'], $msg);
            $this->assertSame('high', $r['confidence'], $msg);
            $this->assertShape($r);
        }
    }

    public function test_english_greeting_without_substring_false_positive(): void
    {
        $this->assertSame('general_chat', $this->route('hi')['intent']);
        $this->assertSame('general_chat', $this->route('hello')['intent']);
        // "hi" inside "this" must NOT route to greeting.
        $this->assertNotSame('general_chat', $this->route('this task is broken')['intent']);
    }

    public function test_identity_and_capabilities_are_general(): void
    {
        $this->assertSame('general_chat', $this->route('اسمت چیه؟')['intent']);
        $this->assertSame('general_chat', $this->route('who are you?')['intent']);
        $this->assertSame('general_chat', $this->route('چیکار میتونی بکنی؟')['intent']);
    }

    public function test_coding_and_knowledge_questions_are_general(): void
    {
        $r = $this->route('یک تابع پایتون بنویس که لیست را مرتب کند');
        $this->assertSame('general_chat', $r['intent']);

        $r = $this->route('پایتخت فرانسه کجاست؟');
        $this->assertSame('general_chat', $r['intent']);

        $r = $this->route('فرق array و object در جاوااسکریپت چیست؟');
        $this->assertSame('general_chat', $r['intent']);
    }

    /* ── workspace queries ───────────────────────────────── */

    public function test_persian_workspace_queries(): void
    {
        $r = $this->route('تسک‌های امروزم چیه؟');
        $this->assertSame('workspace_query', $r['intent']);
        $this->assertContains('task', $r['entities']['objects']);
        $this->assertTrue($r['entities']['date_mentioned']);

        $r = $this->route('لیست پروژه‌ها رو نشون بده');
        $this->assertSame('workspace_query', $r['intent']);

        $r = $this->route('فردا چه کارهایی دارم؟');
        $this->assertSame('workspace_query', $r['intent']);

        $r = $this->route('وضعیت تسکام چطوره؟');
        $this->assertSame('workspace_query', $r['intent']);
    }

    /* ── mutations ───────────────────────────────────────── */

    public function test_persian_mutations_win_over_nouns(): void
    {
        $r = $this->route('یه تسک بساز به نام خرید نون');
        $this->assertSame('task_mutation', $r['intent']);
        $this->assertSame('high', $r['confidence']);
        $this->assertContains('task', $r['entities']['objects']);

        $this->assertSame('task_mutation', $this->route('تسک گزارش را کامل کن')['intent']);
        $this->assertSame('task_mutation', $this->route('این تسک رو حذف کن')['intent']);
        $this->assertSame('task_mutation', $this->route('یادم بنداز فردا ساعت ۹ جلسه دارم')['intent']);
        // "ثبت گزارش" is a mutation, NOT a report request.
        $this->assertSame('task_mutation', $this->route('گزارش کار امروزم رو ثبت کن')['intent']);
        $this->assertSame('task_mutation', $this->route('یادداشت امروز رو ثبت کن')['intent']);
    }

    public function test_english_mutation(): void
    {
        $r = $this->route('remind me tomorrow at 9am to call Ali');
        $this->assertSame('task_mutation', $r['intent']);
        $this->assertContains('reminder', $r['entities']['objects']);

        $this->assertSame('task_mutation', $this->route('make a task for shopping')['intent']);
        $this->assertSame('task_mutation', $this->route('add a note about the meeting')['intent']);
    }

    /* ── planning ────────────────────────────────────────── */

    public function test_plan_build_fa(): void
    {
        $this->assertSame('plan_build', $this->route('سه تا پروژه بساز: فروشگاه، باشگاه، درس')['intent']);
        // ZWNJ form (می‌خوام / ماهانه) must still match.
        $this->assertSame('plan_build', $this->route('می‌خوام برنامه ماهانه‌ام رو بچینم')['intent']);
        $this->assertSame('plan_build', $this->route('روی این پروژه این تغییرات رو اعمال کن: دو تسک اضافه کن')['intent']);
    }

    /* ── workout ─────────────────────────────────────────── */

    public function test_workout_plan_detection(): void
    {
        $pasted = "WEEK 13\nDAY 1 PULL\nDead Hang 3×40s\nPull-up 4×6-8 RIR 2";
        $r = $this->route($pasted);
        $this->assertSame('workout_plan', $r['intent']);

        $this->assertSame('workout_plan', $this->route('برنامه تمرینی هفته بعد رو بساز')['intent']);
        // Bare "برنامه" is NOT a workout plan.
        $this->assertNotSame('workout_plan', $this->route('برنامه')['intent']);
    }

    /* ── reports ─────────────────────────────────────────── */

    public function test_report_requests(): void
    {
        $this->assertSame('report', $this->route('گزارش بده')['intent']);
        $this->assertSame('report', $this->route('گزارش هفتگی رو بگو')['intent']);
        $this->assertSame('report', $this->route('how am i doing?')['intent']);
    }

    /* ── ambiguous + mixed ───────────────────────────────── */

    public function test_ambiguous_fallback_requires_clarification(): void
    {
        foreach (['این', 'xyzqwv', '...'] as $msg) {
            $r = $this->route($msg);
            $this->assertSame('ambiguous', $r['intent'], $msg);
            $this->assertTrue($r['requires_clarification'], $msg);
            $this->assertNotEmpty($r['clarification_question'], $msg);
            $this->assertShape($r);
        }
        // Ambiguous narrows to the READ-only safe set (never the full
        // mutation surface): the model may answer or clarify, nothing else.
        $names = collect(AiIntentRouter::toolsFor($this->route('این'), new AiToolService))->map(fn ($d) => $d['function']['name'])->all();
        $this->assertSame(['report_generate'], $names);
    }

    public function test_mixed_report_and_mutation_prefers_mutation(): void
    {
        $r = $this->route('گزارش بده و یه تسک هم بساز');
        $this->assertSame('task_mutation', $r['intent']);
    }

    public function test_empty_message_is_ambiguous(): void
    {
        $r = $this->route('   ');
        $this->assertSame('ambiguous', $r['intent']);
        $this->assertSame('low', $r['confidence']);
    }

    /* ── wiring: profiles + tool subsets ─────────────────── */

    public function test_profile_mapping(): void
    {
        $general = ['intent' => 'general_chat', 'confidence' => 'high', 'context_profile' => 'minimal'];
        $this->assertSame('minimal', AiIntentRouter::profileFor('agent', $general));
        $this->assertSame('minimal', AiIntentRouter::profileFor('chat', $general));

        $mutation = ['intent' => 'task_mutation', 'confidence' => 'high', 'context_profile' => 'mode_default'];
        $this->assertSame('agent', AiIntentRouter::profileFor('agent', $mutation));
        $this->assertSame('chat', AiIntentRouter::profileFor('chat', $mutation));
    }

    public function test_tool_narrowing_only_on_high_confidence(): void
    {
        $svc = new AiToolService;

        $generalHigh = $this->route('سلام');
        $this->assertSame([], AiIntentRouter::toolsFor($generalHigh, $svc));

        $reportHigh = $this->route('گزارش بده');
        $names = collect(AiIntentRouter::toolsFor($reportHigh, $svc))->map(fn ($d) => $d['function']['name'])->all();
        $this->assertSame(['report_generate'], $names);

        // Medium confidence (non-ambiguous, bare noun-ish) keeps the FULL
        // set (fail-open). Ambiguous input instead gets the READ-only safe
        // set — never the full mutation surface.
        $medium = AiIntentRouter::route('پیشرفت');
        if ($medium['intent'] === AiIntentRouter::INTENT_AMBIGUOUS) {
            $names = collect(AiIntentRouter::toolsFor($medium, $svc))->map(fn ($d) => $d['function']['name'])->all();
            $this->assertSame(['report_generate'], $names);
        } elseif ($medium['confidence'] !== 'high') {
            $this->assertNull(AiIntentRouter::toolsFor($medium, $svc));
        }

        // Mutation narrows away the heavy plan/workout schemas.
        $mutationHigh = $this->route('یه تسک بساز');
        $names = collect(AiIntentRouter::toolsFor($mutationHigh, $svc))->map(fn ($d) => $d['function']['name'])->all();
        $this->assertContains('task_create', $names);
        $this->assertContains('report_generate', $names);
        $this->assertNotContains('plan_propose', $names);
        $this->assertNotContains('workout_plan_propose', $names);
    }

    public function test_definitions_filter_is_order_preserving_and_safe(): void
    {
        $svc = new AiToolService;
        $this->assertCount(count(AiToolService::TOOLS), $svc->definitions());
        $this->assertCount(count(AiToolService::TOOLS), $svc->definitions(null));

        $one = $svc->definitions(['report_generate', 'nope_not_a_tool']);
        $this->assertCount(1, $one);
        $this->assertSame('report_generate', $one[0]['function']['name']);

        $this->assertSame([], $svc->definitions([]));
    }

    /* ── integration: context, prompt, provider payload ──── */

    public function test_minimal_profile_loads_no_workspace_data(): void
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'title' => 'SecretTaskTitleXyz']);

        $ctx = app(AiContextEngine::class)->build($user, 'سلام', 'minimal', ['intent' => 'general_chat']);

        $this->assertStringNotContainsString('SecretTaskTitleXyz', $ctx['text']);
        $this->assertStringNotContainsString('TASKS', $ctx['text']);
        $this->assertSame('minimal', $ctx['meta']['profile']);
        $this->assertSame('general_chat', $ctx['meta']['intent']);
    }

    public function test_agent_greeting_sends_no_tools_end_to_end(): void
    {
        $user = User::factory()->create();
        AiSetting::create([
            'user_id' => $user->id, 'default_provider' => 'openai',
            'openai_key' => 'sk-test-key', 'openai_model' => 'gpt-4o-mini',
        ]);
        Http::fake(fn () => Http::response(['choices' => [['message' => ['content' => 'سلام!']]]], 200));

        $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'سلام', 'mode' => 'agent'])
            ->assertOk()->assertJsonPath('reply', 'سلام!');

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return ! array_key_exists('tools', $payload ?? []);
        });
    }

    public function test_agent_mutation_keeps_tools_end_to_end(): void
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
                && ! in_array('workout_plan_propose', $names, true);
        });
    }

    public function test_build_messages_injects_hint_only_when_needed(): void
    {
        $user = User::factory()->create();
        $controller = new AiChatController(app(AiProviderService::class));
        $ref = new \ReflectionMethod($controller, 'buildMessages');

        $amb = $ref->invoke($controller, $user, 'CTX', [], 'این', 'agent', AiIntentRouter::route('این'));
        $this->assertStringContainsString('ROUTER HINT', $amb[0]['content']);

        $mut = $ref->invoke($controller, $user, 'CTX', [], 'یه تسک بساز', 'agent', AiIntentRouter::route('یه تسک بساز'));
        $this->assertStringNotContainsString('ROUTER HINT', $mut[0]['content']);

        // Backward compatible: omitted route behaves like before.
        $plain = $ref->invoke($controller, $user, 'CTX', [], 'hi', 'chat');
        $this->assertStringContainsString('MODE: CHAT', $plain[0]['content']);
    }

    public function test_router_output_never_authorizes_execution(): void
    {
        // Even when the router says "general", the pipeline still validates
        // and executes a directly-proposed tool call — the router gates
        // nothing but budgets and prompt hints.
        $user = User::factory()->create();
        $route = $this->route('سلام');
        $this->assertSame([], AiIntentRouter::toolsFor($route, new AiToolService));

        $svc = new AiToolService;
        $check = $svc->validateCall('task_create', ['title' => 'Still allowed'], $user);
        $this->assertTrue($check['ok']);
        $result = $svc->execute('task_create', $check['resolved'], $user);
        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('tasks', ['title' => 'Still allowed']);
    }
}
