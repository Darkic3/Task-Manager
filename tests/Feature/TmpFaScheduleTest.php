<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmpFaScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_page_fa_renders_jalali_schedule_picker(): void
    {
        $user = User::factory()->create(['locale' => 'fa']);
        $project = Project::factory()->create(['user_id' => $user->id]);
        Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => 'تسک قابل برنامه‌ریزی',
            'status' => 'to_do',
        ]);

        $response = $this->actingAs($user)->get(route('tasks.index'));
        $response->assertOk();
        $html = $response->getContent();

        /* Jalali custom-date picker (fa only) */
        $this->assertStringContainsString('schedCustomDate-jalali', $html);
        $this->assertStringContainsString('data-jdp', $html);
        /* Day strip + period chips + summary still present */
        $this->assertStringContainsString('data-schedule-days', $html);
        $this->assertStringContainsString('data-schedule-summary', $html);
    }

    public function test_tasks_page_en_renders_native_date_input(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $project = Project::factory()->create(['user_id' => $user->id]);
        Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => 'Plannable task',
            'status' => 'to_do',
        ]);

        $response = $this->actingAs($user)->get(route('tasks.index'));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('id="schedCustomDate-jalali"', $html);
        $this->assertStringContainsString('data-schedule-custom', $html);
        $this->assertStringContainsString('data-schedule-days', $html);
    }
}
