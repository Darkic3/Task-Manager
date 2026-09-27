<?php

namespace Tests\Feature;

use App\Http\Controllers\AiChatController;
use App\Models\AiSetting;
use App\Models\User;
use App\Models\WorkoutImport;
use App\Models\WorkoutPlan;
use App\Services\AiToolService;
use App\Services\WorkoutImportService;
use App\Services\WorkoutPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkoutChatImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_workout_tool_is_advertised_and_project_tool_excludes_workouts(): void
    {
        $definitions = (new AiToolService)->definitions();
        $names = collect($definitions)->map(fn ($definition) => $definition['function']['name'] ?? null)->all();

        $this->assertContains('workout_plan_propose', $names);
        $this->assertContains('plan_propose', $names);

        $planPropose = collect($definitions)->firstWhere(fn ($definition) => ($definition['function']['name'] ?? null) === 'plan_propose');
        $description = strtolower($planPropose['function']['description'] ?? '');
        $this->assertStringContainsString('workout', $description);
        $this->assertStringContainsString('never', $description);
    }

    public function test_workout_plan_propose_validates_full_week_with_details(): void
    {
        $service = new AiToolService;
        $args = [
            'title' => 'Week 14 Training Plan',
            'week_number' => 14,
            'start_date' => '2026-09-27',
            'goal' => 'Muscle Gain + Strength + Calisthenics',
            'rules' => ['Main exercises → RIR 1–2'],
            'days' => [
                ['title' => 'PULL A', 'type' => 'training', 'exercises' => [
                    ['name' => 'Pull-up', 'section' => 'main', 'target_sets' => 4, 'rep_min' => 6, 'rep_max' => 8, 'target_rir' => 2, 'rest_seconds' => 150],
                    ['name' => 'Dead Hang', 'section' => 'warmup', 'duration_seconds' => 45],
                ]],
                ['title' => 'PUSH A', 'type' => 'training', 'exercises' => [
                    ['name' => 'Pike Push-up', 'section' => 'main', 'target_sets' => 4, 'rep_min' => 8, 'rep_max' => 12],
                ]],
                ['title' => 'LEGS', 'type' => 'training', 'exercises' => [
                    ['name' => 'Bulgarian Split Squat', 'section' => 'main', 'target_sets' => 3, 'rep_min' => 10, 'rep_max' => 12, 'side_mode' => 'per_side', 'tempo' => '3s پایین رفتن'],
                ]],
                ['title' => 'PULL B', 'type' => 'training', 'exercises' => []],
                ['title' => 'PUSH B', 'type' => 'training', 'exercises' => [
                    ['name' => 'Close Push-up AMRAP', 'section' => 'main', 'target_sets' => 1, 'is_amrap' => true],
                ]],
                ['title' => 'FULL BODY', 'type' => 'training', 'exercises' => [
                    ['name' => 'Burpee', 'section' => 'main', 'target_sets' => 3, 'rep_min' => 8, 'rep_max' => 10, 'is_circuit' => true, 'circuit_rounds' => 3, 'circuit_rest_seconds' => 120],
                ]],
                ['title' => 'RECOVERY', 'type' => 'recovery', 'exercises' => []],
            ],
        ];

        $check = $service->validateCall('workout_plan_propose', $args, User::factory()->create());

        $this->assertTrue($check['ok']);
        $this->assertSame(7, $check['resolved']['totals']['days']);
        $this->assertSame('2026-09-27', $check['resolved']['structure']['start_date']);
    }

    public function test_day_one_maps_to_today_when_starting_from_sunday(): void
    {
        $service = app(WorkoutImportService::class);
        $structure = $service->normalizeStructure([
            'title' => 'Week 14',
            'week_number' => 14,
            'start_date' => '2026-09-27',
            'days' => [
                ['title' => 'PULL A', 'type' => 'training', 'exercises' => [['name' => 'Pull-up']]],
                ['title' => 'PUSH A', 'type' => 'training', 'exercises' => []],
                ['title' => 'LEGS', 'type' => 'training', 'exercises' => []],
                ['title' => 'PULL B', 'type' => 'training', 'exercises' => []],
                ['title' => 'PUSH B', 'type' => 'training', 'exercises' => []],
                ['title' => 'FULL BODY', 'type' => 'training', 'exercises' => []],
                ['title' => 'RECOVERY', 'type' => 'recovery', 'exercises' => []],
            ],
        ], '2026-09-27');

        $this->assertSame('2026-09-27', $structure['start_date']);
        $this->assertSame(14, $structure['week_number']);

        $byWeekday = collect($structure['days'])->keyBy('weekday');
        // DAY 1 must stay on today (Sunday 2026-09-27), DAY 7 wraps to Saturday.
        $this->assertSame('PULL A', $byWeekday['sunday']['title']);
        $this->assertSame('RECOVERY', $byWeekday['saturday']['title']);
        $this->assertCount(7, $structure['days']);
    }

    public function test_workout_intent_detection_needs_two_signals_and_length(): void
    {
        $controller = app(AiChatController::class);
        $method = new \ReflectionMethod($controller, 'isWorkoutIntent');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, str_repeat('WEEK 13 DAY 1 PULL A 4×6–8 RIR ', 20)));
        $this->assertFalse($method->invoke($controller, 'hello how are you'));
        $this->assertFalse($method->invoke($controller, 'WEEK'));
    }

    public function test_fallback_builds_import_preview_from_pasted_plan_without_tool_call(): void
    {
        $user = User::factory()->create();
        AiSetting::create([
            'user_id' => $user->id,
            'default_provider' => 'openai',
            'openai_key' => 'test-key',
            'openai_model' => 'gpt-4o-mini',
        ]);
        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'title' => 'Week 14 Training Plan',
                    'week_number' => 14,
                    'start_date' => '2026-09-27',
                    'days' => [
                        ['weekday' => 'sunday', 'title' => 'PULL A', 'type' => 'training', 'exercises' => [['name' => 'Pull-up']]],
                        ['weekday' => 'monday', 'title' => 'PUSH A', 'type' => 'training', 'exercises' => []],
                        ['weekday' => 'tuesday', 'title' => 'LEGS', 'type' => 'training', 'exercises' => []],
                        ['weekday' => 'wednesday', 'title' => 'PULL B', 'type' => 'training', 'exercises' => []],
                        ['weekday' => 'thursday', 'title' => 'PUSH B', 'type' => 'training', 'exercises' => []],
                        ['weekday' => 'friday', 'title' => 'FULL BODY', 'type' => 'training', 'exercises' => []],
                        ['weekday' => 'saturday', 'title' => 'RECOVERY', 'type' => 'recovery', 'exercises' => []],
                    ],
                ])]]],
            ], 200),
        ]);

        $controller = app(AiChatController::class);
        $method = new \ReflectionMethod($controller, 'buildWorkoutFallbackProposal');
        $method->setAccessible(true);

        $userMessage = str_repeat('WEEK 13 DAY 1 PULL A Pull-up 4×6–8 RIR برنامه شروع از امروز ', 20);
        $result = $method->invoke($controller, $user, null, $userMessage, 'عالیه! بذار ثبت کنم.');

        $this->assertArrayHasKey('import', $result);
        $import = WorkoutImport::find($result['import']['id']);
        $this->assertNotNull($import);
        $this->assertSame('2026-09-27', $import->structure['start_date']);

        // Idempotent: second call with the same message reuses the preview.
        $again = $method->invoke($controller, $user, null, $userMessage, 'عالیه! بذار ثبت کنم.');
        $this->assertSame($result['import']['id'], $again['import']['id']);
        $this->assertSame(1, WorkoutImport::where('user_id', $user->id)->count());
    }

    public function test_agent_chat_creates_workout_import_preview_instead_of_project(): void
    {
        $user = User::factory()->create();
        $controller = app(AiChatController::class);

        $method = new \ReflectionMethod($controller, 'createWorkoutImportFromToolCall');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $user, null, json_encode([
            'title' => 'Week 14 Training Plan',
            'week_number' => 14,
            'start_date' => '2026-09-27',
            'goal' => 'Muscle Gain + Strength',
            'days' => [
                ['title' => 'PULL A', 'type' => 'training', 'exercises' => [
                    ['name' => 'Pull-up', 'section' => 'main', 'target_sets' => 4, 'rep_min' => 6, 'rep_max' => 8],
                ]],
                ['title' => 'PUSH A', 'type' => 'training', 'exercises' => []],
                ['title' => 'LEGS', 'type' => 'training', 'exercises' => []],
                ['title' => 'PULL B', 'type' => 'training', 'exercises' => []],
                ['title' => 'PUSH B', 'type' => 'training', 'exercises' => []],
                ['title' => 'FULL BODY', 'type' => 'training', 'exercises' => []],
                ['title' => 'RECOVERY', 'type' => 'recovery', 'exercises' => []],
            ],
        ]));

        $this->assertArrayHasKey('import', $result);
        $import = WorkoutImport::find($result['import']['id']);
        $this->assertNotNull($import);
        $this->assertSame(WorkoutImport::PREVIEW, $import->status);
        $this->assertSame('Week 14 Training Plan', $import->structure['title']);

        $byWeekday = collect($import->structure['days'])->keyBy('weekday');
        $this->assertSame('PULL A', $byWeekday['sunday']['title']);
        $this->assertDatabaseMissing('projects', ['name' => 'Week 14 Training Plan']);
    }

    public function test_plan_days_show_in_execution_order_with_sunday_start(): void
    {
        $user = User::factory()->create();
        $plan = app(WorkoutPlanService::class)->save([
            'title' => 'Week 14',
            'week_number' => 14,
            'start_date' => '2026-09-27',
            'status' => 'active',
            'days' => collect(WorkoutPlan::WEEKDAYS)->map(fn ($weekday) => [
                'weekday' => $weekday,
                'title' => $weekday === 'sunday' ? 'PULL A' : ($weekday === 'saturday' ? 'RECOVERY' : 'Rest'),
                'type' => in_array($weekday, ['sunday'], true) ? 'training' : ($weekday === 'saturday' ? 'recovery' : 'rest'),
                'exercises' => [],
            ])->all(),
        ], $user->id);

        // Stored canonically (Saturday first), but displayed DAY 1 first.
        $this->assertSame('saturday', $plan->days->first()->weekday);

        $ordered = $plan->fresh()->orderedDays();
        $this->assertSame('sunday', $ordered->first()->weekday);
        $this->assertSame('PULL A', $ordered->first()->title);
        $this->assertSame('saturday', $ordered->last()->weekday);
        $this->assertSame('RECOVERY', $ordered->last()->title);
    }

    public function test_sequential_import_keeps_day_one_first(): void
    {
        $service = app(WorkoutImportService::class);
        $structure = $service->normalizeStructure([
            'title' => 'Week 14',
            'week_number' => 14,
            'start_date' => '2026-09-27',
            'days' => [
                ['title' => 'PULL A', 'type' => 'training', 'exercises' => [['name' => 'Pull-up']]],
                ['title' => 'PUSH A', 'type' => 'training', 'exercises' => []],
                ['title' => 'LEGS', 'type' => 'training', 'exercises' => []],
                ['title' => 'PULL B', 'type' => 'training', 'exercises' => []],
                ['title' => 'PUSH B', 'type' => 'training', 'exercises' => []],
                ['title' => 'FULL BODY', 'type' => 'training', 'exercises' => []],
                ['title' => 'RECOVERY', 'type' => 'recovery', 'exercises' => []],
            ],
        ], '2026-09-27');

        $this->assertSame('PULL A', $structure['days'][0]['title']);
        $this->assertSame('sunday', $structure['days'][0]['weekday']);
        $this->assertSame('RECOVERY', $structure['days'][6]['title']);
        $this->assertSame('saturday', $structure['days'][6]['weekday']);
    }
}
