<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerDayVueTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_view_bootstraps_vue_island_state(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Vue bootstrapped task',
            'due_date' => today()->toDateString(),
            'time_period' => 'morning',
            'status' => 'to_do',
        ]);

        $html = $this->actingAs($user)->get(route('planner.index'))->assertOk()->getContent();

        // Vue island contract: initial state + vendored lib + island app.
        $this->assertStringContainsString('__PL_DAY_INITIAL__', $html);
        $this->assertStringContainsString('assets/vendor/vue/vue.global.prod.js', $html);
        $this->assertStringContainsString('assets/js/planner-day-app.js', $html);
        $this->assertStringContainsString('"group":"morning"', $html);
        $this->assertStringContainsString('Vue bootstrapped task', $html);
        $this->assertStringContainsString('"id":'.$task->id, $html);
    }

    public function test_quick_add_payload_matches_vue_add_task_contract(): void
    {
        $user = User::factory()->create();

        $json = $this->actingAs($user)->postJson(route('planner.quick-add.task'), [
            'title' => 'Vue quick task',
            'date' => today()->toDateString(),
            'time_period' => 'evening',
        ])->assertCreated()->assertJsonPath('ok', true)->json();

        $this->assertSame('evening', $json['group']);
        $this->assertArrayHasKey('html', $json);
        $this->assertArrayHasKey('id', $json['task']);
        $this->assertStringContainsString('Vue quick task', $json['html']);
        $this->assertStringContainsString('data-task-item', $json['html']);
    }

    public function test_schedule_payload_matches_vue_add_task_contract(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Vue inbox task',
            'due_date' => null,
            'time_period' => null,
        ]);

        $json = $this->actingAs($user)->postJson(
            route('planner.tasks.schedule', $task),
            ['due_date' => today()->toDateString(), 'view' => 'day']
        )->assertOk()->assertJsonPath('ok', true)->json();

        $this->assertArrayHasKey('html', $json);
        $this->assertArrayHasKey('group', $json);
        $this->assertStringContainsString('Vue inbox task', $json['html']);
        $this->assertStringContainsString('data-task-item', $json['html']);
    }

    public function test_vue_vendor_build_is_shipped_locally(): void
    {
        $path = public_path('assets/vendor/vue/vue.global.prod.js');
        $this->assertFileExists($path);
        $this->assertStringContainsString('createApp', (string) file_get_contents($path));

        $app = public_path('assets/js/planner-day-app.js');
        $this->assertFileExists($app);
        $js = (string) file_get_contents($app);
        // Bridge contract the Blade flows rely on: reactive add, verified
        // remove with DOM←store reconciliation, and order sync after drag&drop.
        foreach (['addTask', 'removeTask', 'scheduleVerify', 'syncOrderFromDom', 'pl-vue-row'] as $marker) {
            $this->assertStringContainsString($marker, $js);
        }
    }

    public function test_fa_translations_cover_new_task_ctas(): void
    {
        $fa = json_decode(file_get_contents(lang_path('fa.json')), true);
        $this->assertNotEmpty($fa['Create a new task for today'] ?? null);
        $this->assertNotEmpty($fa['Add a new task directly to this day'] ?? null);
        $this->assertNotSame('Create a new task for today', $fa['Create a new task for today']);
    }

    public function test_day_view_empty_state_and_ctas_are_translated_in_fa(): void
    {
        $user = User::factory()->create();
        app()->setLocale('fa');

        $html = $this->actingAs($user)->get(route('planner.index'))->assertOk()->getContent();

        $this->assertStringContainsString('ساخت تسک جدید برای امروز', $html);
        $this->assertStringContainsString('افزودن مستقیم تسک جدید به امروز', $html);
        $this->assertStringNotContainsString('Create a new task for today', $html);
    }

    public function test_day_view_always_renders_empty_template_for_client_reinserts(): void
    {
        $user = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Still pending',
            'due_date' => today()->toDateString(),
            'status' => 'to_do',
        ]);

        $html = $this->actingAs($user)->get(route('planner.index'))->assertOk()->getContent();

        // Even with pending tasks, the inert template exists so the client can
        // bring the empty block back without a refresh once zero open remain.
        $this->assertStringContainsString('id="plEmptyTpl"', $html);
        $this->assertStringContainsString('pl-qadd-btn', $html);
    }

    public function test_day_view_with_no_pending_tasks_shows_full_empty_block(): void
    {
        $user = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Already done',
            'due_date' => today()->toDateString(),
            'status' => 'completed',
        ]);

        $html = $this->actingAs($user)->get(route('planner.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="plPendingBody"', $html);
        $this->assertStringContainsString('Nothing scheduled for this day', $html);
        $this->assertStringContainsString('Create a new task for today', $html);
    }

    public function test_vue_app_tracks_open_count_for_empty_state(): void
    {
        $js = (string) file_get_contents(public_path('assets/js/planner-day-app.js'));
        foreach (['openCount', 'setCompleted', 'plEmptyTpl'] as $marker) {
            $this->assertStringContainsString($marker, $js);
        }
    }
}
