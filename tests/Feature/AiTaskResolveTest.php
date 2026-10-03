<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task tools resolve records by title (+ optional project scope), so the
 * model never has to ask the user for numeric IDs.
 */
class AiTaskResolveTest extends TestCase
{
    use RefreshDatabase;

    private function task(User $user, string $title, ?int $projectId = null): Task
    {
        return Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'title' => $title,
            'status' => 'to_do',
        ]);
    }

    public function test_update_resolves_exact_title_and_executes(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user, 'شیوع و مراحل اسکیزوفرنی');
        $svc = new AiToolService;

        $check = $svc->validateCall('task_update', [
            'task' => 'شیوع و مراحل اسکیزوفرنی',
            'description' => '5 صفحه',
        ], $user);

        $this->assertTrue($check['ok']);
        $this->assertSame($task->id, $check['resolved']['id']);

        $result = $svc->execute('task_update', $check['resolved'], $user);
        $this->assertTrue($result['ok']);
        $this->assertSame('5 صفحه', $task->fresh()->description);
    }

    public function test_update_resolves_partial_title(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user, 'اختلال اسکیزو افکتیو');
        $svc = new AiToolService;

        $check = $svc->validateCall('task_update', [
            'task' => 'اسکیزو افکتیو',
            'description' => '6 صفحه',
        ], $user);

        $this->assertTrue($check['ok']);
        $this->assertSame($task->id, $check['resolved']['id']);
    }

    public function test_update_scopes_duplicate_titles_by_project(): void
    {
        $user = User::factory()->create();
        $p1 = Project::factory()->create(['user_id' => $user->id, 'name' => 'روانپزشکی']);
        $p2 = Project::factory()->create(['user_id' => $user->id, 'name' => 'آزاد']);
        $wanted = $this->task($user, 'درمان', $p2->id);
        $this->task($user, 'درمان', $p1->id);
        $svc = new AiToolService;

        // Ambiguous without scope: error names the candidates.
        $ambiguous = $svc->validateCall('task_update', ['task' => 'درمان', 'description' => 'x'], $user);
        $this->assertFalse($ambiguous['ok']);
        $this->assertStringContainsString('Multiple tasks match', $ambiguous['error']);

        // Project name scopes it to the right row.
        $check = $svc->validateCall('task_update', [
            'task' => 'درمان', 'project' => 'آزاد', 'description' => '16 صفحه',
        ], $user);
        $this->assertTrue($check['ok']);
        $this->assertSame($wanted->id, $check['resolved']['id']);
    }

    public function test_update_rejects_unknown_title_and_other_users_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->task($other, 'محرمانه');
        $svc = new AiToolService;

        $missing = $svc->validateCall('task_update', ['task' => 'چیزی که نیست', 'description' => 'x'], $user);
        $this->assertFalse($missing['ok']);
        $this->assertStringContainsString('not found', $missing['error']);

        $foreign = $svc->validateCall('task_update', ['task' => 'محرمانه', 'description' => 'x'], $user);
        $this->assertFalse($foreign['ok']);

        $empty = $svc->validateCall('task_update', ['description' => 'x'], $user);
        $this->assertFalse($empty['ok']);
    }

    public function test_complete_and_delete_resolve_by_title(): void
    {
        $user = User::factory()->create();
        $done = $this->task($user, 'کاتاتونیا');
        $gone = $this->task($user, 'علل سایکوتیک');
        $svc = new AiToolService;

        $check = $svc->validateCall('task_complete', ['task' => 'کاتاتونیا'], $user);
        $this->assertTrue($check['ok']);
        $result = $svc->execute('task_complete', $check['resolved'], $user);
        $this->assertTrue($result['ok']);
        $this->assertSame('completed', $done->fresh()->status);

        $check = $svc->validateCall('task_delete', ['task' => 'علل سایکوتیک'], $user);
        $this->assertTrue($check['ok']);
        $this->assertSame('علل سایکوتیک', $check['resolved']['title']);
        $result = $svc->execute('task_delete', $check['resolved'], $user);
        $this->assertTrue($result['ok']);
        $this->assertDatabaseMissing('tasks', ['id' => $gone->id]);
    }

    public function test_id_still_works_and_wins_over_title(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user, 'درمان واقعی');
        $svc = new AiToolService;

        $check = $svc->validateCall('task_update', [
            'id' => $task->id, 'task' => 'عنوان اشتباه', 'description' => 'ok',
        ], $user);
        $this->assertTrue($check['ok']);
        $this->assertSame($task->id, $check['resolved']['id']);
    }

    public function test_checklist_add_resolves_task_title(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user, 'نظریه های شناخت گرایی');
        $svc = new AiToolService;

        $check = $svc->validateCall('checklist_add', ['task' => 'شناخت گرایی', 'name' => 'خلاصه فصل'], $user);
        $this->assertTrue($check['ok']);
        $result = $svc->execute('checklist_add', $check['resolved'], $user);
        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('checklist_items', ['task_id' => $task->id, 'name' => 'خلاصه فصل']);
    }

    public function test_bulk_title_updates_all_validate_like_one_turn(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'طیف اسکیزوفرنی']);
        $titles = ['سمپتوم های آزاددهنده', 'شیوع و مراحل', 'سایر اختلالات سایکوتیک', 'درمان'];
        foreach ($titles as $t) {
            $this->task($user, $t, $project->id);
        }
        $svc = new AiToolService;

        // One call per task, same turn — the bulk flow from the user request.
        foreach ($titles as $i => $t) {
            $check = $svc->validateCall('task_update', [
                'task' => $t, 'project' => 'طیف اسکیزوفرنی', 'description' => ($i + 1).' صفحه',
            ], $user);
            $this->assertTrue($check['ok'], "failed for '{$t}': ".($check['error'] ?? ''));
            $result = $svc->execute('task_update', $check['resolved'], $user);
            $this->assertTrue($result['ok']);
        }

        foreach ($titles as $i => $t) {
            $this->assertDatabaseHas('tasks', [
                'user_id' => $user->id, 'title' => $t, 'description' => ($i + 1).' صفحه',
            ]);
        }
    }

    public function test_update_preview_shows_resolved_title(): void
    {
        $user = User::factory()->create();
        $this->task($user, 'پیش آگهی طیف');
        $svc = new AiToolService;

        $check = $svc->validateCall('task_update', ['task' => 'پیش آگهی', 'description' => '2 صفحه'], $user);
        $this->assertTrue($check['ok']);
        $preview = $svc->preview('task_update', $check['resolved'], $user);
        $this->assertSame('Update task', $preview['title']);
        $this->assertContains(['k' => 'task_title', 'v' => 'پیش آگهی طیف'], $preview['rows']);
    }
}
