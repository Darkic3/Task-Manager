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

        // The bootstrap payload must be pure, parseable JSON (exactly what the
        // browser evaluates) — including the trickiest real-world titles.
        $nasty = Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'نظریه "آنا" \\ فروید (۱) — test',
            'due_date' => today()->toDateString(),
            'status' => 'to_do',
        ]);
        $html = $this->actingAs($user)->get(route('planner.index'))->assertOk()->getContent();
        preg_match('/window\.__PL_DAY_INITIAL__\s*=\s*(\{.*?\});\s*<\/script>/s', $html, $m);
        $this->assertNotEmpty($m, 'bootstrap JSON block not found');
        $data = json_decode($m[1], true);
        $this->assertNotNull($data, 'bootstrap payload is not valid JSON: '.json_last_error_msg());
        $this->assertSame(['items', 'periods', 'order'], array_keys($data));
        $byId = collect($data['items'])->keyBy('id');
        $this->assertTrue(isset($byId[$task->id]) && isset($byId[$nasty->id]));
        foreach ($data['items'] as $item) {
            $this->assertArrayHasKey('html', $item);
            $this->assertStringContainsString('data-task-item', $item['html']);
        }
        $this->assertSame(0, substr_count(strtolower($m[1]), '</script'), 'script breakout in payload');
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

    public function test_vue_app_mutates_state_through_mounted_instance(): void
    {
        // Regression: mutating the raw seed array bypasses Vue 3's reactive
        // proxy and silently skips rendering (empty block got stuck). All
        // writes must go through the mounted component instance.
        $js = (string) file_get_contents(public_path('assets/js/planner-day-app.js'));
        $this->assertStringContainsString('vm = app.mount(mountEl)', $js);
        foreach (['vm.items.push', 'vm.items.splice', 'vm.items[ix].done', 'vm.items[ix].group'] as $marker) {
            $this->assertStringContainsString($marker, $js);
        }
        $this->assertStringNotContainsString('store.items.push', $js);
        $this->assertStringNotContainsString('store.items.splice(ix, 1)', $js);
    }

    public function test_day_view_always_renders_archival_completed_section(): void
    {
        $user = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Open task',
            'due_date' => today()->toDateString(),
            'status' => 'to_do',
        ]);

        // No completed tasks at all AND with completed tasks: the archival
        // section must exist (hidden when empty) so done rows can move there
        // without a refresh.
        $html = $this->actingAs($user)->get(route('planner.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="plDoneSection"', $html);
        $this->assertStringContainsString('id="plDoneBody"', $html);
        $this->assertStringContainsString('id="plDoneCount"', $html);

        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Done task',
            'due_date' => today()->toDateString(),
            'status' => 'completed',
        ]);
        $html = $this->actingAs($user)->get(route('planner.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Done task', $html);
    }

    public function test_day_view_moves_done_rows_out_of_the_work_box(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('planner.index'))->assertOk()->getContent();
        foreach (['plPlaceTaskRows', 'plUpdateDoneSection', 'plScrubRowForDone', 'plPruneDayGroups'] as $marker) {
            $this->assertStringContainsString($marker, $html);
        }
    }

    public function test_day_view_has_versioned_vue_assets_and_empty_invariant_guard(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('planner.index'))->assertOk()->getContent();

        // Cache-busted assets: the browser can never run a stale island build
        // against newer inline handlers (or vice versa).
        $this->assertStringContainsString('assets/js/planner-day-app.js?v=', $html);
        $this->assertStringContainsString('assets/vendor/vue/vue.global.prod.js?v=', $html);
        // Path-independent self-healing guard for the empty-day block.
        $this->assertStringContainsString('enforceEmptyInvariant', $html);
        $this->assertStringContainsString('MutationObserver', $html);
        $this->assertStringContainsString('id="plEmptyTpl"', $html);
    }
}
