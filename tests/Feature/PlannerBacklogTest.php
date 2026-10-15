<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerBacklogTest extends TestCase
{
    use RefreshDatabase;

    private function seedTree(User $user): array
    {
        $projectA = Project::factory()->create(['user_id' => $user->id, 'name' => 'Alpha']);
        $subB = Project::factory()->create(['user_id' => $user->id, 'name' => 'Beta sub', 'parent_id' => $projectA->id]);
        $projectC = Project::factory()->create(['user_id' => $user->id, 'name' => 'Gamma']);

        $make = fn (array $attrs) => Task::factory()->create(array_merge(['user_id' => $user->id], $attrs));

        return [
            'a' => $projectA,
            'b' => $subB,
            'c' => $projectC,
            'unscheduled' => $make(['title' => 'Unscheduled A', 'project_id' => $projectA->id, 'due_date' => null]),
            'subTask' => $make(['title' => 'Sub project task', 'project_id' => $subB->id, 'due_date' => null]),
            'otherProject' => $make(['title' => 'Other project task', 'project_id' => $projectC->id, 'due_date' => null]),
            'unassigned' => $make(['title' => 'No project task', 'project_id' => null, 'due_date' => null]),
            'overdue' => $make([
                'title' => 'Overdue timed', 'project_id' => $projectA->id,
                'due_date' => today()->subDays(2)->toDateString(),
                'time_period' => 'morning', 'due_time' => '09:00:00',
            ]),
            'noTimeFuture' => $make([
                'title' => 'Future no time', 'project_id' => $projectA->id,
                'due_date' => today()->addDays(2)->toDateString(),
                'time_period' => null, 'due_time' => null,
            ]),
            'futureTimed' => $make([
                'title' => 'Future with time', 'project_id' => $projectA->id,
                'due_date' => today()->addDays(2)->toDateString(),
                'time_period' => 'evening', 'due_time' => '20:00:00',
            ]),
            'completed' => $make([
                'title' => 'Done task', 'project_id' => $projectA->id,
                'due_date' => null, 'status' => 'completed',
            ]),
            'todayNoTime' => $make([
                'title' => 'Today no time', 'project_id' => $projectA->id,
                'due_date' => today()->toDateString(),
                'time_period' => null, 'due_time' => null,
            ]),
        ];
    }

    private function titles(array $tasks): array
    {
        return collect($tasks)->pluck('title')->all();
    }

    public function test_backlog_lists_unscheduled_overdue_and_time_less_tasks(): void
    {
        $user = User::factory()->create();
        $this->seedTree($user);

        $titles = $this->titles($this->actingAs($user)->getJson(route('planner.backlog'))->assertOk()->json('tasks'));

        $this->assertContains('Unscheduled A', $titles);
        $this->assertContains('Sub project task', $titles);
        $this->assertContains('Overdue timed', $titles);
        $this->assertContains('Future no time', $titles);
        $this->assertNotContains('Future with time', $titles);
        $this->assertNotContains('Done task', $titles);
    }

    public function test_backlog_project_filter_covers_sub_projects(): void
    {
        $user = User::factory()->create();
        $s = $this->seedTree($user);

        // Selecting A also returns tasks of sub-project B, but not C.
        $titlesA = $this->titles(
            $this->actingAs($user)->getJson(route('planner.backlog', ['project_id' => $s['a']->id]))->assertOk()->json('tasks')
        );
        $this->assertContains('Unscheduled A', $titlesA);
        $this->assertContains('Sub project task', $titlesA);
        $this->assertNotContains('Other project task', $titlesA);
        $this->assertNotContains('No project task', $titlesA);

        // Selecting B alone returns only B's tasks.
        $titlesB = $this->titles(
            $this->actingAs($user)->getJson(route('planner.backlog', ['project_id' => $s['b']->id]))->assertOk()->json('tasks')
        );
        $this->assertContains('Sub project task', $titlesB);
        $this->assertNotContains('Unscheduled A', $titlesB);

        // 'none' returns only unassigned tasks.
        $titlesNone = $this->titles(
            $this->actingAs($user)->getJson(route('planner.backlog', ['project_id' => 'none']))->assertOk()->json('tasks')
        );
        $this->assertContains('No project task', $titlesNone);
        $this->assertNotContains('Unscheduled A', $titlesNone);
    }

    public function test_backlog_search_combines_with_project_filter(): void
    {
        $user = User::factory()->create();
        $s = $this->seedTree($user);

        $titles = $this->titles(
            $this->actingAs($user)
                ->getJson(route('planner.backlog', ['q' => 'Sub project', 'project_id' => $s['a']->id]))
                ->assertOk()->json('tasks')
        );
        $this->assertContains('Sub project task', $titles);
    }

    public function test_backlog_excludes_tasks_already_on_target_day_and_rejects_foreign_project(): void
    {
        $user = User::factory()->create();
        $s = $this->seedTree($user);
        $other = User::factory()->create();
        $foreign = Project::factory()->create(['user_id' => $other->id]);

        $titles = $this->titles(
            $this->actingAs($user)
                ->getJson(route('planner.backlog', ['date' => today()->toDateString()]))
                ->assertOk()->json('tasks')
        );
        $this->assertNotContains('Today no time', $titles);
        $this->assertContains('Unscheduled A', $titles);

        $this->actingAs($user)
            ->getJson(route('planner.backlog', ['project_id' => $foreign->id]))
            ->assertStatus(422);
    }

    public function test_day_view_renders_backlog_project_filter(): void
    {
        $user = User::factory()->create();
        $s = $this->seedTree($user);

        $html = $this->actingAs($user)->get(route('planner.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="plBacklogProject"', $html);
        $this->assertStringContainsString('Alpha', $html);
        $this->assertStringContainsString('Beta sub', $html);
    }

    public function test_backlog_marks_overdue_and_due_labels(): void
    {
        $user = User::factory()->create();
        $this->seedTree($user);

        $tasks = collect($this->actingAs($user)->getJson(route('planner.backlog'))->assertOk()->json('tasks'))
            ->keyBy('title');

        $this->assertTrue($tasks['Overdue timed']['is_overdue']);
        $this->assertNotEmpty($tasks['Overdue timed']['due_label']);
        $this->assertFalse($tasks['Future no time']['is_overdue']);
        $this->assertSame(__('No date'), $tasks['Unscheduled A']['due_label']);
    }
}
