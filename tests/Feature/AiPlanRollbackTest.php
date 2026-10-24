<?php

namespace Tests\Feature;

use App\Models\AiPlan;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPlanRollbackTest extends TestCase
{
    use RefreshDatabase;

    private function studyArgs(): array
    {
        return [
            'title' => 'Roshd chunk 1',
            'project' => [
                'name' => 'روانشناسی رشد',
                'daily_goal_value' => 15,
                'daily_goal_unit' => 'pages',
                'tasks' => [],
            ],
            'subprojects' => [[
                'name' => 'فصل ۱',
                'daily_goal_value' => 10,
                'daily_goal_unit' => 'pages',
                'tasks' => [[
                    'title' => 'درسنامه ۱',
                    'weight' => 12,
                    'subtasks' => [
                        ['title' => 'تعاریف', 'weight' => 2],
                        ['title' => 'موضوعات اساسی'],
                    ],
                ]],
            ]],
            'reminders' => [[
                'title' => 'مرور فصل ۱',
                'date' => now()->addWeek()->toDateString(),
                'time' => '18:00',
            ]],
            'notes' => [[
                'title' => 'خلاصه فصل ۱',
                'content' => 'نکته‌ها',
            ]],
        ];
    }

    private function makePlan(User $user, array $args): AiPlan
    {
        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', $args, $user);
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

    private function runAll(AiPlan $plan): void
    {
        $user = $plan->user;
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();
        $this->actingAs($user)
            ->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 0, 'run_all' => true])
            ->assertOk()->assertJsonPath('plan.status', 'done');
    }

    public function test_weight_and_goal_round_trip_through_plan(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan($user, $this->studyArgs());
        $this->runAll($plan);

        $book = Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->firstOrFail();
        $this->assertSame(15, (int) $book->daily_goal_value);
        $this->assertSame('pages', $book->daily_goal_unit);

        $chapter = Project::where('user_id', $user->id)->where('name', 'فصل ۱')->firstOrFail();
        $this->assertSame(10, (int) $chapter->daily_goal_value);

        $lesson = Task::where('user_id', $user->id)->where('title', 'درسنامه ۱')->firstOrFail();
        $this->assertEquals(12, (float) $lesson->weight);

        $micro = Task::where('user_id', $user->id)->where('title', 'تعاریف')->firstOrFail();
        $this->assertEquals(2, (float) $micro->weight);
        $this->assertSame($lesson->id, (int) $micro->parent_id);

        // Ledger recorded everything the plan created (nothing attached here).
        $items = $plan->fresh()->created_items;
        $this->assertCount(2, $items['projects']);
        $this->assertCount(3, $items['tasks']);
        $this->assertCount(1, $items['reminders']);
        $this->assertCount(1, $items['notes']);

        // Preview totals feed the plan card (pages + goals hooks).
        $res = $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 0]);
        $totals = $res->json('plan.preview.totals');
        $this->assertEquals(14, $totals['pages']);
        $this->assertSame(2, $totals['goals']);
        $this->assertTrue($res->json('plan.can_rollback'));
    }

    public function test_weight_and_goal_validation_rejects_bad_values(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;

        $badWeight = $this->studyArgs();
        $badWeight['subprojects'][0]['tasks'][0]['weight'] = 500;
        $check = $svc->validateCall('plan_propose', $badWeight, $user);
        $this->assertFalse($check['ok']);

        $badGoal = $this->studyArgs();
        $badGoal['project']['daily_goal_unit'] = 'chapters';
        $check = $svc->validateCall('plan_propose', $badGoal, $user);
        $this->assertFalse($check['ok']);
    }

    private function rollbackWithPreview(User $user, AiPlan $plan): \Illuminate\Testing\TestResponse
    {
        // P4 contract: preview first, then confirm with the bound token.
        $preview = $this->actingAs($user)->getJson(route('ai.plans.rollback-preview', $plan))->assertOk();

        return $this->actingAs($user)->postJson(route('ai.plans.rollback', $plan), [
            'preview_token' => $preview->json('preview_token'),
        ]);
    }

    public function test_rollback_deletes_everything_the_plan_created(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan($user, $this->studyArgs());
        $this->runAll($plan);

        $bookId = Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->firstOrFail()->id;

        $res = $this->rollbackWithPreview($user, $plan)->assertOk();
        $this->assertTrue($res->json('ok'));
        $this->assertSame(3, $res->json('deleted.tasks'));
        $this->assertSame(2, $res->json('deleted.projects'));
        $this->assertSame(1, $res->json('deleted.reminders'));
        $this->assertSame(1, $res->json('deleted.notes'));

        $this->assertDatabaseMissing('projects', ['id' => $bookId]);
        $this->assertDatabaseMissing('tasks', ['title' => 'درسنامه ۱']);
        $this->assertDatabaseMissing('tasks', ['title' => 'تعاریف']);
        $this->assertSame([], $plan->fresh()->created_items);

        // Second rollback is a harmless no-op.
        $again = $this->rollbackWithPreview($user, $plan)->assertOk();
        $this->assertTrue($again->json('ok'));
    }

    public function test_rollback_keeps_attached_and_user_content(): void
    {
        $user = User::factory()->create();
        $existing = Project::factory()->create(['user_id' => $user->id, 'name' => 'کنکور ارشد']);

        $args = $this->studyArgs();
        $args['project']['project_id'] = $existing->id;
        unset($args['reminders'], $args['notes']);
        $plan = $this->makePlan($user, $args);
        $this->runAll($plan);

        // Attached root must NOT be recorded for deletion.
        $items = $plan->fresh()->created_items;
        $this->assertNotContains($existing->id, $items['projects']);
        $this->assertCount(1, $items['projects']); // only فصل ۱

        // User adds their own task to the plan-created chapter afterwards.
        $chapter = Project::where('user_id', $user->id)->where('name', 'فصل ۱')->firstOrFail();
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $chapter->id, 'title' => 'یادداشت شخصی من']);

        $res = $this->rollbackWithPreview($user, $plan)->assertOk();

        // Plan tasks gone, attached root and user content survive.
        $this->assertDatabaseMissing('tasks', ['title' => 'درسنامه ۱']);
        $this->assertDatabaseHas('tasks', ['title' => 'یادداشت شخصی من']);
        $this->assertDatabaseHas('projects', ['id' => $existing->id]);
        $this->assertDatabaseHas('projects', ['id' => $chapter->id]);
        $this->assertNotEmpty($res->json('kept'));
    }

    public function test_rollback_guards(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $plan = $this->makePlan($user, $this->studyArgs());

        // Foreign user → 403 (before any other check).
        $this->actingAs($other)->postJson(route('ai.plans.rollback', $plan), ['preview_token' => 'x'])->assertForbidden();

        // Missing token → 422 validation (no preview, no confirm).
        $this->actingAs($user)->postJson(route('ai.plans.rollback', $plan))->assertStatus(422);

        // Proposed plan has nothing to roll back (status gate, regardless of token).
        $this->actingAs($user)->postJson(route('ai.plans.rollback', $plan), ['preview_token' => 'forged'])
            ->assertStatus(422);
    }

    public function test_chat_ui_offers_rollback_button(): void
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get(route('ai.index'))->assertOk()->getContent();

        $this->assertStringContainsString('appendRollbackButton', $html);
        $this->assertStringContainsString('/rollback', $html);
        $this->assertStringContainsString('حذف ساخته‌های این پلن', $html);
    }

    public function test_empty_project_plan_validates_but_flags_empty_totals(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;

        // A bare project shell stays VALID (skeleton and member-only plans
        // are legitimate flows) — the safety net is the UI: the proposed
        // card warns on zero content and the done card renders real counts.
        $check = $svc->validateCall('plan_propose', [
            'title' => 'کتاب رشد',
            'project' => ['name' => 'روانشناسی رشد', 'tasks' => []],
            'subprojects' => [],
        ], $user);
        $this->assertTrue($check['ok'], $check['error'] ?? 'validate failed');
        $this->assertSame(0, $check['resolved']['totals']['tasks']);
        $this->assertSame(0, $check['resolved']['totals']['subtasks']);
    }

    public function test_serialize_reports_real_created_counts(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan($user, $this->studyArgs());
        $this->runAll($plan);

        $res = $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 0]);
        $created = $res->json('plan.created');
        $this->assertSame(2, $created['projects']);
        $this->assertSame(3, $created['tasks']);
        $this->assertSame(1, $created['reminders']);
        $this->assertSame(1, $created['notes']);

        // Chunk continuation: concrete book id + name for the next prompt.
        $ids = $res->json('plan.created_ids.projects');
        $this->assertCount(2, $ids);
        $this->assertSame('روانشناسی رشد', $ids[0]['name']);
        $this->assertSame(
            Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->firstOrFail()->id,
            $ids[0]['id']
        );
    }

    public function test_next_chunk_message_with_id_routes_to_plan(): void
    {
        $msg = 'بسته بعدی ساختار کتاب «روانشناسی رشد»: فصل‌های ۴ تا ۶ را به پروژه موجود «روانشناسی رشد» (#25) وصل کن و بساز — جستجو لازم نیست، مستقیم پلن مرحله‌ای بده (فقط زیرپروژه/تسک جدید؛ پروژه تکراری نساز). تعداد صفحه هر درسنامه فقط در weight ثبت شود، نه در عنوان نه در توضیحات.';
        $r = \App\Services\AiIntentRouter::route($msg);
        $this->assertSame(\App\Services\AiIntentRouter::INTENT_PLAN, $r['intent']);
        $this->assertSame(\App\Services\AiIntentRouter::CONF_HIGH, $r['confidence']);
    }

    public function test_done_and_proposed_warnings_render(): void
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get(route('ai.index'))->assertOk()->getContent();

        // Dynamic done counts + empty-build warning + pre-confirm warning.
        $this->assertStringContainsString('ساخته شد:', $html);
        $this->assertStringContainsString('چیزی نساخت', $html);
        $this->assertStringContainsString('هیچ تسک و محتوایی ندارد', $html);
    }

    public function test_prompt_records_pages_as_metric_not_text(): void
    {
        $user = User::factory()->create();
        $controller = new \App\Http\Controllers\AiChatController(app(\App\Services\AiProviderService::class));
        $ref = new \ReflectionMethod($controller, 'buildMessages');

        $agent = $ref->invoke($controller, $user, 'CTX', [], 'hi', 'agent');
        // Product decision: titles stay CLEAN; pages live in description
        // line 1 ("📄 N صفحه") AND in weight — never in titles.
        $this->assertStringContainsString('titles stay CLEAN (no page numbers ever)', $agent[0]['content']);
        $this->assertStringContainsString('("📄 N صفحه") AND in weight', $agent[0]['content']);
        $this->assertStringContainsString('never in titles', $agent[0]['content']);
    }

    public function test_oversized_plan_fails_with_chunk_guidance(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;

        // 15 chapters like the user's book → guided failure, not cryptic max.
        $big = $this->studyArgs();
        $big['subprojects'] = array_map(
            fn ($i) => ['name' => "فصل $i", 'tasks' => [['title' => "درس $i"]]],
            range(1, 15)
        );
        $check = $svc->validateCall('plan_propose', $big, $user);
        $this->assertFalse($check['ok']);
        $this->assertStringContainsString('at most 3 chapters', $check['error']);
        $this->assertStringContainsString('بسته اول', $check['error']);

        // Too many tasks → guided failure as well.
        $manyTasks = $this->studyArgs();
        $manyTasks['subprojects'][0]['tasks'] = array_map(
            fn ($i) => ['title' => "درس $i"],
            range(1, 31)
        );
        $check = $svc->validateCall('plan_propose', $manyTasks, $user);
        $this->assertFalse($check['ok']);
        $this->assertStringContainsString('Split into smaller chunks', $check['error']);
    }

    public function test_ui_maps_cap_errors_to_chunk_guidance(): void
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get(route('ai.index'))->assertOk()->getContent();

        $this->assertStringContainsString('۳ زیرپروژه، ۳۰ تسک، ۱۰۰ زیرتسک', $html);
    }
}
