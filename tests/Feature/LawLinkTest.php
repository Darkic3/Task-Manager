<?php

namespace Tests\Feature;

use App\Models\Law;
use App\Models\LawLink;
use App\Models\Project;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use App\Services\LawLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LawLinkTest extends TestCase
{
    use RefreshDatabase;

    private function makeLaw(User $user): Law
    {
        return $user->laws()->create([
            'title' => 'Test law',
            'coefficient' => 4,
            'starts_on' => today()->toDateString(),
            'status' => Law::STATUS_ACTIVE,
        ]);
    }

    public function test_attach_task_with_preset_direction(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Gym']);

        $this->actingAs($user)->post(route('laws.links.store', $law), [
            'type' => 'task',
            'id' => $task->id,
            'effect' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('law_links', [
            'law_id' => $law->id,
            'linkable_type' => Task::class,
            'linkable_id' => $task->id,
            'effect' => 1,
        ]);
    }

    public function test_reattaching_same_entity_only_retargets_direction(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('laws.links.store', $law), ['type' => 'task', 'id' => $task->id, 'effect' => 1]);
        $this->actingAs($user)->post(route('laws.links.store', $law), ['type' => 'task', 'id' => $task->id, 'effect' => -1]);

        $this->assertEquals(1, LawLink::where('law_id', $law->id)->count());
        $this->assertEquals(-1, LawLink::where('law_id', $law->id)->first()->effect);
    }

    public function test_cannot_attach_other_users_task(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $strangerTask = Task::factory()->create(); // another user

        $this->actingAs($user)->post(route('laws.links.store', $law), [
            'type' => 'task',
            'id' => $strangerTask->id,
            'effect' => 1,
        ])->assertSessionHasErrors('id');

        $this->assertEquals(0, LawLink::where('law_id', $law->id)->count());
    }

    public function test_update_and_destroy_link(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $link = $law->links()->create([
            'linkable_type' => Routine::class,
            'linkable_id' => $routine->id,
            'effect' => 1,
        ]);

        $this->actingAs($user)->patch(route('laws.links.update', [$law, $link]), ['effect' => -1])->assertRedirect();
        $this->assertEquals(-1, $link->fresh()->effect);

        $this->actingAs($user)->delete(route('laws.links.destroy', [$law, $link]))->assertRedirect();
        $this->assertDatabaseMissing('law_links', ['id' => $link->id]);
    }

    public function test_project_inheritance_with_opt_out(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $service = app(LawLinkService::class);

        $parent = Project::factory()->create(['user_id' => $user->id, 'name' => 'Health']);
        $child = Project::factory()->create(['user_id' => $user->id, 'name' => 'Sport', 'parent_id' => $parent->id]);
        $task = Task::factory()->create(['user_id' => $user->id, 'project_id' => $child->id, 'title' => 'Run']);

        // No links yet: nothing resolves.
        $this->assertNull($service->resolveForTask($task, $law));

        // Link the parent project as "for": the task in the sub-project inherits.
        $service->attach($law, Project::class, $parent->id, LawLink::EFFECT_FOR);
        $resolved = $service->resolveForTask($task, $law);
        $this->assertNotNull($resolved);
        $this->assertSame(1, $resolved['effect']);
        $this->assertFalse($resolved['direct']);
        $this->assertSame('Health', $resolved['via']);

        // A direct task link overrides the inheritance.
        $service->attach($law, Task::class, $task->id, LawLink::EFFECT_AGAINST);
        $resolved = $service->resolveForTask($task, $law);
        $this->assertSame(-1, $resolved['effect']);
        $this->assertTrue($resolved['direct']);

        // Removing the direct link + excluding opts the task fully out.
        LawLink::where('law_id', $law->id)
            ->where('linkable_type', Task::class)
            ->where('linkable_id', $task->id)
            ->delete();
        $service->excludeTask($law, $task);
        $this->assertNull($service->resolveForTask($task, $law));
    }

    public function test_effective_laws_for_task_lists_all_matching_laws(): void
    {
        $user = User::factory()->create();
        $service = app(LawLinkService::class);

        $lawA = $this->makeLaw($user);
        $lawB = $user->laws()->create([
            'title' => 'Second law', 'coefficient' => 2,
            'starts_on' => today()->toDateString(), 'status' => Law::STATUS_ACTIVE,
        ]);
        $task = Task::factory()->create(['user_id' => $user->id]);

        $service->attach($lawA, Task::class, $task->id, LawLink::EFFECT_FOR);
        $service->attach($lawB, Task::class, $task->id, LawLink::EFFECT_AGAINST);

        $effective = $service->effectiveLawsForTask($task);

        $this->assertCount(2, $effective);
        $this->assertSame(1, $effective->firstWhere('law.id', $lawA->id)['effect']);
        $this->assertSame(-1, $effective->firstWhere('law.id', $lawB->id)['effect']);
    }

    public function test_candidates_exclude_already_linked_items(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $linked = Task::factory()->create(['user_id' => $user->id, 'title' => 'Linked task xyz']);
        $free = Task::factory()->create(['user_id' => $user->id, 'title' => 'Free task xyz']);

        app(LawLinkService::class)->attach($law, Task::class, $linked->id, 1);

        $res = $this->actingAs($user)->getJson(route('laws.candidates', [$law, 'type' => 'task', 'q' => 'xyz']))
            ->assertOk();

        $ids = collect($res->json('items'))->pluck('id')->all();
        $this->assertContains($free->id, $ids);
        $this->assertNotContains($linked->id, $ids);
    }

    public function test_intruder_cannot_touch_foreign_law_links(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $law = $this->makeLaw($owner);
        $task = Task::factory()->create(['user_id' => $intruder->id]);

        $this->actingAs($intruder)->post(route('laws.links.store', $law), [
            'type' => 'task', 'id' => $task->id, 'effect' => 1,
        ])->assertForbidden();
    }

    public function test_show_page_renders_linked_activities(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Morning run xyz']);
        $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'Health xyz']);

        app(LawLinkService::class)->attach($law, Task::class, $task->id, 1);
        app(LawLinkService::class)->attach($law, Project::class, $project->id, -1);

        $this->actingAs($user)->get(route('laws.show', $law))
            ->assertOk()
            ->assertSee('Morning run xyz')
            ->assertSee('Health xyz');
    }
}
