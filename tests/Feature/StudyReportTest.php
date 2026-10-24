<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Reports\StudyReportService;
use App\Services\Reports\UnifiedReportService;
use App\Services\Reports\ReportRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class StudyReportTest extends TestCase
{
    use RefreshDatabase;

    private function range(): ReportRange
    {
        return ReportRange::fromRequest('week', null, null);
    }

    public function test_pages_and_minutes_per_chapter_with_hierarchy_paths(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Konkur']);
        $book = Project::factory()->create(['user_id' => $user->id, 'parent_id' => $root->id, 'name' => 'Shenakhti']);
        $ch1 = Project::factory()->create(['user_id' => $user->id, 'parent_id' => $book->id, 'name' => 'Fasl 1']);
        // Same chapter name under another book must NOT merge.
        $book2 = Project::factory()->create(['user_id' => $user->id, 'parent_id' => $root->id, 'name' => 'Roshd']);
        $ch1b = Project::factory()->create(['user_id' => $user->id, 'parent_id' => $book2->id, 'name' => 'Fasl 1']);

        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $ch1->id,
            'status' => 'completed', 'completed_at' => now(), 'weight' => 15,
        ]);
        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $ch1->id,
            'status' => 'to_do', 'weight' => 5,
        ]);
        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $ch1b->id,
            'status' => 'completed', 'completed_at' => now(), 'weight' => 7,
        ]);
        TimeEntry::create([
            'user_id' => $user->id, 'project_id' => $ch1->id,
            'status' => TimeEntry::STATUS_STOPPED,
            'started_at' => now()->subHour(), 'ended_at' => now()->subMinutes(30),
            'duration_seconds' => 1800,
        ]);

        $range = $this->range();
        $study = StudyReportService::summarize($user->id, $range->from, $range->to);

        $this->assertEquals(22.0, $study['pages_completed']);
        $this->assertSame(1800, $study['minutes_total']);

        $paths = array_column($study['rows'], 'path');
        $this->assertContains('Konkur / Shenakhti / Fasl 1', $paths);
        $this->assertContains('Konkur / Roshd / Fasl 1', $paths);

        $row = collect($study['rows'])->firstWhere('path', 'Konkur / Shenakhti / Fasl 1');
        $this->assertEquals(15.0, $row['pages_done']);
        $this->assertEquals(20.0, $row['pages_total']);
        $this->assertEquals(75.0, $row['progress']);
        $this->assertSame(1800, $row['minutes_in_range']);
    }

    public function test_completed_without_completed_at_still_counts_via_updated_at(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $task = Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $project->id,
            'status' => 'completed', 'completed_at' => null, 'weight' => 4,
        ]);
        // Simulate a legacy row: NULL completed_at, updated today.
        \DB::table('tasks')->where('id', $task->id)->update(['completed_at' => null, 'updated_at' => now()]);

        $range = $this->range();
        $study = StudyReportService::summarize($user->id, $range->from, $range->to);

        $this->assertEquals(4.0, $study['pages_completed']);
    }

    public function test_daily_goal_adherence_counts_met_days(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id, 'daily_goal_value' => 2, 'daily_goal_unit' => 'tasks',
        ]);

        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $project->id,
            'status' => 'completed', 'completed_at' => now(),
        ]);
        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $project->id,
            'status' => 'completed', 'completed_at' => now(),
        ]);

        $range = $this->range();
        $study = StudyReportService::summarize($user->id, $range->from, $range->to);

        $this->assertGreaterThanOrEqual(1, $study['goal_days_met']);
        $this->assertGreaterThanOrEqual($study['goal_days_met'], $study['goal_days_total']);
        $this->assertGreaterThan(0, $study['goal_days_total']);
    }

    public function test_overview_renders_study_section_and_overdue_insight(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Konkur']);
        $book = Project::factory()->create(['user_id' => $user->id, 'parent_id' => $root->id, 'name' => 'Shenakhti']);
        $ch = Project::factory()->create(['user_id' => $user->id, 'parent_id' => $book->id, 'name' => 'Fasl 6']);

        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $ch->id,
            'status' => 'completed', 'completed_at' => now(), 'weight' => 10,
        ]);
        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $ch->id,
            'status' => 'to_do', 'due_date' => now()->subDay()->toDateString(), 'weight' => 5,
        ]);

        $html = $this->actingAs($user)->get(route('reports.overview', ['range' => 'week']))->assertOk()->getContent();

        $this->assertStringContainsString('Per chapter', $html);
        $this->assertStringContainsString('Pages read per day', $html);
        $this->assertStringContainsString('Konkur / Shenakhti / Fasl 6', $html);
        $this->assertStringContainsString('Strongest chapter', $html);
        $this->assertStringContainsString('overdue', $html);
    }

    public function test_unified_overview_includes_study_and_deltas(): void
    {
        $user = User::factory()->create();
        $range = $this->range();

        $report = UnifiedReportService::overview($user->id, $range);

        $this->assertArrayHasKey('study', $report);
        $this->assertArrayHasKey('study_pages', $report['deltas']);
        $this->assertArrayHasKey('study_goal_rate', $report['deltas']);
    }
}
