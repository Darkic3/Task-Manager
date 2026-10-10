<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\NoteLink;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteEntityLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_create_with_task_project_and_subtask_picks(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'title' => 'Main task']);
        $subtask = Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'parent_id' => $task->id, 'title' => 'Subtask one']);

        $res = $this->actingAs($user)->post(route('notes.store'), [
            'title' => 'Linked note',
            'content' => 'Body text here.',
            'mentions_submitted' => '1',
            'mentions' => [
                ['type' => 'project', 'id' => $project->id],
                ['type' => 'task', 'id' => $task->id],
                ['type' => 'task', 'id' => $subtask->id],
            ],
        ]);

        $res->assertRedirect();
        $note = Note::where('user_id', $user->id)->where('title', 'Linked note')->firstOrFail();

        $this->assertTrue($note->links()->where('linkable_type', Project::class)->where('linkable_id', $project->id)->exists());
        $this->assertTrue($note->links()->where('linkable_type', Task::class)->where('linkable_id', $task->id)->exists());
        $this->assertTrue($note->links()->where('linkable_type', Task::class)->where('linkable_id', $subtask->id)->exists());

        // Note show page renders the linked section with targets.
        $show = $this->actingAs($user)->get(route('notes.show', $note))->assertOk();
        $show->assertSee('Linked to');
        $show->assertSee($project->name);
        $show->assertSee('Main task');
        $show->assertSee('Subtask one');
    }

    public function test_note_create_prefill_from_task_attaches_on_store(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Prefill task']);

        // Prefill chip is offered on the create form.
        $form = $this->actingAs($user)->get(route('notes.create', ['linked_type' => 'task', 'linked_id' => $task->id]))->assertOk();
        $form->assertSee('Prefill task');

        // And it is attached even without composer picks (e.g. JS disabled).
        $this->actingAs($user)->post(route('notes.store'), [
            'title' => 'Note for task',
            'content' => 'Some body.',
            'linked_type' => 'task',
            'linked_id' => $task->id,
        ])->assertRedirect();

        $note = Note::where('user_id', $user->id)->where('title', 'Note for task')->firstOrFail();
        $this->assertTrue($note->links()->where('linkable_type', Task::class)->where('linkable_id', $task->id)->exists());
    }

    public function test_note_update_prunes_removed_task_link(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Old task']);
        $note = Note::create(['user_id' => $user->id, 'title' => 'N', 'content' => 'B']);
        $note->links()->create(['linkable_type' => Task::class, 'linkable_id' => $task->id, 'label' => 'Old task']);

        $this->actingAs($user)->put(route('notes.update', $note), [
            'title' => 'N',
            'content' => 'B',
            'mentions_submitted' => '1',
            'mentions' => [],
        ])->assertRedirect();

        $this->assertFalse($note->links()->where('linkable_type', Task::class)->where('linkable_id', $task->id)->exists());
    }

    public function test_task_show_lists_notes_and_attach_detach_roundtrip(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Task with notes']);
        $note = Note::create(['user_id' => $user->id, 'title' => 'Task note A', 'content' => 'Body']);

        // Attach from the task side.
        $attach = $this->actingAs($user)->postJson(route('tasks.notes.attach', $task), ['note_id' => $note->id])->assertOk();
        $attach->assertJsonPath('success', true);

        $link = NoteLink::where('linkable_type', Task::class)->where('linkable_id', $task->id)->where('note_id', $note->id)->firstOrFail();

        // Task page shows the note.
        $this->actingAs($user)->get(route('tasks.show', $task))->assertOk()->assertSee('Task note A');

        // Notes index filter finds it too.
        $this->assertTrue(
            Note::ofUser($user->id)->linkedTo(Task::class, $task->id)->whereKey($note->id)->exists()
        );

        // Detach keeps the note itself.
        $this->actingAs($user)->deleteJson(route('tasks.notes.detach', [$task, $link]))->assertOk()->assertJsonPath('success', true);
        $this->assertFalse(NoteLink::whereKey($link->id)->exists());
        $this->assertTrue(Note::whereKey($note->id)->exists());
    }

    public function test_project_show_lists_notes_and_attach_detach_roundtrip(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'Project with notes']);
        $note = Note::create(['user_id' => $user->id, 'title' => 'Project note A', 'content' => 'Body']);

        $this->actingAs($user)->postJson(route('projects.notes.attach', $project), ['note_id' => $note->id])
            ->assertOk()->assertJsonPath('success', true);

        $link = NoteLink::where('linkable_type', Project::class)->where('linkable_id', $project->id)->where('note_id', $note->id)->firstOrFail();

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->assertSee('Project note A');

        $this->actingAs($user)->deleteJson(route('projects.notes.detach', [$project, $link]))->assertOk()->assertJsonPath('success', true);
        $this->assertFalse(NoteLink::whereKey($link->id)->exists());
        $this->assertTrue(Note::whereKey($note->id)->exists());
    }

    public function test_cannot_attach_foreign_note_or_task(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Mine']);
        $foreignNote = Note::create(['user_id' => $other->id, 'title' => 'Theirs', 'content' => 'x']);
        $foreignTask = Task::factory()->create(['user_id' => $other->id, 'title' => 'Theirs task']);

        $this->actingAs($user)->postJson(route('tasks.notes.attach', $task), ['note_id' => $foreignNote->id])->assertStatus(422);

        $mine = Note::create(['user_id' => $user->id, 'title' => 'Mine note', 'content' => 'y']);
        $this->actingAs($user)->postJson(route('notes.links.store', $mine), ['type' => 'task', 'id' => $foreignTask->id])->assertStatus(422);
    }
}
