<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Morilog\Jalali\Jalalian;
use Tests\TestCase;

/**
 * In fa locale every date/datetime input is a Jalali picker (visible) backed
 * by a hidden Gregorian input carrying the original name — controllers and
 * validation keep receiving Gregorian exactly like native inputs did.
 */
class JalaliPickerTest extends TestCase
{
    use RefreshDatabase;

    private function asFa(): void
    {
        app()->setLocale('fa');
    }

    public function test_create_form_uses_jalali_picker_in_fa(): void
    {
        $this->asFa();
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('tasks.create'))->assertOk()->getContent();

        $this->assertStringContainsString('data-jdp', $html);
        $this->assertStringContainsString('name="due_date"', $html);
        $this->assertStringNotContainsString('type="date"', $html);
    }

    public function test_create_form_uses_native_input_in_en(): void
    {
        app()->setLocale('en');
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('tasks.create'))->assertOk()->getContent();

        $this->assertStringContainsString('type="date"', $html);
        $this->assertStringNotContainsString('data-jdp', $html);
    }

    public function test_edit_form_prefills_matching_gregorian_and_jalali(): void
    {
        $this->asFa();
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'due_date' => '2026-10-04']);

        $html = $this->actingAs($user)->get(route('tasks.edit', $task))->assertOk()->getContent();

        $expectedJalali = Jalalian::fromCarbon(\Carbon\Carbon::parse('2026-10-04'))->format('Y/m/d');
        // Hidden Gregorian input keeps the original name for the controller.
        $this->assertStringContainsString('name="due_date" id="due_date" value="2026-10-04"', $html);
        // Visible Jalali input shows the converted date and targets the hidden one.
        $this->assertStringContainsString('value="'.$expectedJalali.'"', $html);
        $this->assertStringContainsString('data-target="due_date"', $html);
    }

    public function test_layout_loads_picker_assets_only_in_fa(): void
    {
        $user = User::factory()->create();

        $this->asFa();
        $fa = $this->actingAs($user)->get(route('tasks.create'))->assertOk()->getContent();
        $this->assertStringContainsString('assets/jalali/jalalidatepicker.min.css', $fa);
        $this->assertStringContainsString('assets/jalali/jalalidatepicker.min.js', $fa);
        $this->assertStringContainsString('assets/jalali/jalaali.js', $fa);
        $this->assertStringContainsString('jalaliDatepicker.startWatch', $fa);

        app()->setLocale('en');
        $en = $this->actingAs($user)->get(route('tasks.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString('assets/jalali/', $en);
        $this->assertStringNotContainsString('data-jdp', $en);
    }

    public function test_hidden_gregorian_payload_passes_store_validation(): void
    {
        $this->asFa();
        $user = User::factory()->create();

        // Exactly what the hidden input submits in fa mode.
        $response = $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Jalali task',
            'priority' => 'medium',
            'due_date' => '2026-10-04',
        ]);

        $response->assertRedirect(route('tasks.index'));
        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Jalali task']);
    }

    public function test_datetime_hidden_format_passes_time_entry_validation(): void
    {
        $this->asFa();
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        // Space-separated 'Y-m-d H:i' is what the datetime hidden input sends.
        $response = $this->actingAs($user)->post(route('time.entries.store'), [
            'task_id' => $task->id,
            'started_at' => '2026-10-04 09:00',
            'ended_at' => '2026-10-04 10:00',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('time_entries', ['user_id' => $user->id, 'task_id' => $task->id]);
    }

    public function test_planner_week_strip_renders_jalali_in_fa(): void
    {
        $this->asFa();
        $user = User::factory()->create();
        $today = now()->startOfDay();

        $html = $this->actingAs($user)->get(route('planner.index', ['view' => 'week']))->assertOk()->getContent();

        $expectedDay = Jalalian::fromCarbon($today)->format('l');
        $this->assertStringContainsString($expectedDay, $html);
    }
}
