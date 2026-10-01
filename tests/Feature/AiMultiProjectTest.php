<?php

namespace Tests\Feature;

use App\Models\AiPlan;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use App\Services\AiToolService;
use App\Services\FaDateParser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiMultiProjectTest extends TestCase
{
    use RefreshDatabase;

    private function multiArgs(): array
    {
        return [
            'title' => 'ساختار چندپروژه‌ای',
            'projects' => [
                ['name' => 'برساز', 'description' => 'SpotPlayer work', 'tasks' => [
                    ['title' => 'راه اندازی سیستم پرداخت'],
                    ['title' => 'فیکس باگ واترمارک', 'subtasks' => [['title' => 'تست پلیر']]],
                ]],
                ['name' => 'ذخیره انرژی', 'tasks' => [
                    ['title' => 'بررسی سناریو'],
                ]],
                ['name' => 'Task Manager', 'tasks' => [
                    ['title' => 'سازماندهی تسک‌ها'],
                ]],
            ],
            'reminders' => [
                ['title' => 'بازارچه لاله', 'date' => '2026-10-01', 'time' => '18:00', 'location' => 'بازارچه لاله پیش علی'],
            ],
            'notes' => [
                ['title' => 'ایده برساز', 'content' => 'متن نوت'],
            ],
        ];
    }

    public function test_multi_project_plan_validates_totals(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', $this->multiArgs(), $user);
        $this->assertTrue($check['ok'], $check['error'] ?? 'validate failed');
        $this->assertEquals(3, $check['resolved']['totals']['projects']);
        $this->assertEquals(4, $check['resolved']['totals']['tasks']);
        $this->assertEquals(1, $check['resolved']['totals']['subtasks']);
        $this->assertEquals(1, $check['resolved']['totals']['reminders']);
        $this->assertEquals(1, $check['resolved']['totals']['notes']);

        $phases = $svc->buildPlanPhases($check['resolved']['structure']);
        $this->assertCount(6, $phases);
        $this->assertEquals('projects', $phases[0]['key']);
        $this->assertEquals(3, $phases[0]['total']);
        $this->assertEquals('reminders', $phases[4]['key']);
        $this->assertEquals('notes', $phases[5]['key']);
    }

    public function test_multi_project_run_all_builds_everything(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', $this->multiArgs(), $user);
        $this->assertTrue($check['ok'], $check['error'] ?? 'validate failed');

        $plan = AiPlan::create([
            'user_id' => $user->id,
            'title' => $check['resolved']['title'],
            'structure' => $check['resolved']['structure'],
            'phases' => $svc->buildPlanPhases($check['resolved']['structure']),
            'status' => AiPlan::STATUS_PROPOSED,
            'current_phase' => 0,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 0, 'run_all' => true])
            ->assertOk()->assertJsonPath('plan.status', 'done');

        // 3 independent root projects (no parents).
        $this->assertEquals(3, Project::where('user_id', $user->id)->whereNull('parent_id')->count());
        $barsaz = Project::where('user_id', $user->id)->where('name', 'برساز')->firstOrFail();
        $this->assertNull($barsaz->parent_id);
        $this->assertEquals(2, Task::where('project_id', $barsaz->id)->whereNull('parent_id')->count());
        $payTask = Task::where('project_id', $barsaz->id)->where('title', 'فیکس باگ واترمارک')->firstOrFail();
        $this->assertEquals(1, Task::where('parent_id', $payTask->id)->count());

        // Reminder with date/time/location + note.
        $rem = Reminder::where('user_id', $user->id)->where('title', 'بازارچه لاله')->firstOrFail();
        $this->assertEquals('2026-10-01', $rem->date->toDateString());
        $this->assertEquals('بازارچه لاله پیش علی', $rem->location);
        Note::where('user_id', $user->id)->where('title', 'ایده برساز')->firstOrFail();
    }

    public function test_reminder_only_plan_has_no_project_phases(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', [
            'title' => 'فقط یادآوری',
            'reminders' => [['title' => 'قرار امروز', 'date' => '2026-10-01', 'time' => '18:00']],
        ], $user);
        $this->assertTrue($check['ok'], $check['error'] ?? 'validate failed');

        $plan = AiPlan::create([
            'user_id' => $user->id,
            'title' => $check['resolved']['title'],
            'structure' => $check['resolved']['structure'],
            'phases' => $svc->buildPlanPhases($check['resolved']['structure']),
            'status' => AiPlan::STATUS_PROPOSED,
            'current_phase' => 0,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);

        // Project phases are pre-marked done; first actionable is reminders.
        $this->assertEquals('done', $plan->phases[0]['status']);
        $this->assertEquals(0, Project::where('user_id', $user->id)->count());

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();
        $plan = $plan->fresh();
        $this->assertEquals(4, $plan->current_phase); // skipped to reminders
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 4, 'run_all' => true])
            ->assertOk()->assertJsonPath('plan.status', 'done');
        $this->assertEquals(0, Project::where('user_id', $user->id)->count());
        Reminder::where('user_id', $user->id)->where('title', 'قرار امروز')->firstOrFail();
    }

    public function test_mixing_styles_and_caps_rejected(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;

        $mixed = $this->multiArgs();
        $mixed['project'] = ['name' => 'اضافی'];
        $this->assertFalse($svc->validateCall('plan_propose', $mixed, $user)['ok']);

        $tooMany = ['title' => 'Big', 'projects' => array_map(
            fn ($i) => ['name' => "P$i"], range(1, 6)
        )];
        $this->assertFalse($svc->validateCall('plan_propose', $tooMany, $user)['ok']);

        $withRoutines = $this->multiArgs();
        $withRoutines['routines'] = [['title' => 'X', 'frequency' => 'daily']];
        $this->assertFalse($svc->validateCall('plan_propose', $withRoutines, $user)['ok']);

        $this->assertFalse($svc->validateCall('plan_propose', ['title' => 'Empty'], $user)['ok']);
    }

    public function test_fa_parser_handles_persian_cues(): void
    {
        $now = Carbon::create(2026, 10, 1); // Thursday
        $this->assertEquals(
            ['date' => '2026-10-01', 'time' => '18:00'],
            FaDateParser::parse('برای امروز ساعت 6 بعد از ظهر یک رویداد تعریف کن', $now)
        );
        $this->assertEquals(
            ['date' => '2026-10-02', 'time' => '09:00'],
            FaDateParser::parse('فردا ساعت ۹ صبح', $now)
        );
        $this->assertEquals('2026-10-03', FaDateParser::parse('پس‌فردا', $now)['date']);
        // Friday (جمعه) from Thursday Oct 1 → Oct 2.
        $this->assertEquals('2026-10-02', FaDateParser::parse('جمعه', $now)['date']);
        // Jalali numeric date 1403/07/09 → 2024-09-30.
        $this->assertEquals('2024-09-30', FaDateParser::parse('1403/07/09', $now)['date']);
    }

    public function test_reminder_create_parses_persian_datetime(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;
        Carbon::setTestNow(Carbon::create(2026, 10, 1, 10, 0));
        try {
            $check = $svc->validateCall('reminder_create', [
                'title' => 'بازارچه لاله',
                'description' => 'برای امروز ساعت 6 بعد از ظهر پیش علی',
            ], $user);
            $this->assertTrue($check['ok'], $check['error'] ?? 'validate failed');
            $this->assertEquals('2026-10-01', $check['resolved']['date']);
            $this->assertEquals('18:00', $check['resolved']['time']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
