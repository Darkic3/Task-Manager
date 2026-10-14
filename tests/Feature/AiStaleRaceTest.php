<?php

namespace Tests\Feature;

use App\Models\AiPendingAction;
use App\Models\AiSetting;
use App\Models\Routine;
use App\Models\RoutineCompletion;
use App\Models\Task;
use App\Models\User;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 3, Step 4 — Stale data & concurrent races.
 *
 * Before execution, ownership, status and dependencies are revalidated
 * server-side on fresh reads: a proposal is a snapshot, never a promise.
 */
class AiStaleRaceTest extends TestCase
{
    use RefreshDatabase;

    private function pending(User $user, string $tool, array $resolved): AiPendingAction
    {
        return AiPendingAction::create([
            'user_id' => $user->id,
            'conversation_id' => null,
            'tool' => $tool,
            'args' => $resolved,
            'preview' => ['title' => $tool],
            'status' => AiPendingAction::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
    }

    /* ── data changed after the proposal was shown ── */

    public function test_confirm_after_external_routine_completion_conflicts_once(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Run',
        ]);

        $svc = new AiToolService;
        $check = $svc->validateCall('routine_complete', ['id' => $routine->id], $user);
        $this->assertTrue($check['ok']);
        $action = $this->pending($user, 'routine_complete', $check['resolved']);

        // Someone (or another device) completes it before the confirm lands.
        $done = $svc->execute('routine_complete', $check['resolved'], $user);
        $this->assertTrue($done['ok']);

        $this->actingAs($user)->postJson('/ai/actions/'.$action->id.'/confirm')->assertStatus(422);

        $this->assertSame(
            1,
            RoutineCompletion::where('routine_id', $routine->id)->where('completed_date', now()->toDateString())->count(),
            'exactly one completion may exist'
        );
    }

    public function test_confirm_after_external_task_delete_fails_then_expires(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Doomed']);

        $svc = new AiToolService;
        $check = $svc->validateCall('task_delete', ['id' => $task->id], $user);
        $this->assertTrue($check['ok']);
        $action = $this->pending($user, 'task_delete', $check['resolved']);

        $task->delete();

        // Revalidation on fresh reads: no blind execution, structured code.
        $this->actingAs($user)->postJson('/ai/actions/'.$action->id.'/confirm')
            ->assertStatus(422)
            ->assertJsonPath('code', \App\Services\AiTooling\ToolError::NOT_FOUND);

        // A revalidation failure is not an execution failure: the row stays
        // pending (retryable if the world changes back) until it expires.
        $this->assertSame(AiPendingAction::STATUS_PENDING, $action->fresh()->status);

        $action->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($user)->postJson('/ai/actions/'.$action->id.'/confirm')
            ->assertStatus(422)
            ->assertJsonPath('code', \App\Services\AiTooling\ToolError::EXPIRED_ACTION);
        $this->assertSame(AiPendingAction::STATUS_EXPIRED, $action->fresh()->status);
    }

    /* ── edit while a proposal is still pending (stream keeps the thread) ── */

    public function test_edit_while_pending_creates_sibling_proposal_without_auto_execute(): void
    {
        $user = User::factory()->create();
        AiSetting::create([
            'user_id' => $user->id,
            'default_provider' => 'openai',
            'openai_key' => 'sk-test-key',
            'openai_model' => 'gpt-4o-mini',
        ]);

        $titles = ['First Title', 'Second Title'];
        $calls = 0;
        Http::fake(function () use (&$calls, $titles) {
            $title = $titles[min($calls, 1)];
            $calls++;

            return Http::response(
                'data: '.json_encode(['choices' => [['delta' => ['tool_calls' => [[
                    'index' => 0, 'id' => 'call_'.$calls, 'type' => 'function',
                    'function' => ['name' => 'task_create', 'arguments' => json_encode(['title' => $title])],
                ]]]]]])."\n\n".'data: [DONE]'."\n\n",
                200,
                ['Content-Type' => 'text/event-stream']
            );
        });

        // Turn 1: proposal for a new task (stream opens the conversation).
        $first = $this->actingAs($user)->post(route('ai.stream'), ['message' => 'یه تسک بساز', 'mode' => 'agent']);
        $first->assertOk();
        $first->streamedContent();
        $convId = AiPendingAction::where('user_id', $user->id)->firstOrFail()->conversation_id;

        // Turn 2, same conversation: the user corrects themselves before
        // confirming anything. A sibling proposal appears; nothing executes.
        $second = $this->actingAs($user)->post(route('ai.stream'), [
            'conversation_id' => $convId, 'message' => 'نه، اسمش Second Title باشه', 'mode' => 'agent',
        ]);
        $second->assertOk();
        $second->streamedContent();

        $this->assertSame(2, AiPendingAction::where('user_id', $user->id)->where('status', 'pending')->count());
        $this->assertSame(0, Task::where('user_id', $user->id)->count(), 'a correction must not auto-execute either proposal');
    }

    /* ── double-click confirm stays single-execution ── */

    public function test_double_click_confirm_executes_once(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Click me']);

        $svc = new AiToolService;
        $check = $svc->validateCall('task_complete', ['id' => $task->id], $user);
        $this->assertTrue($check['ok']);
        $action = $this->pending($user, 'task_complete', $check['resolved']);

        $this->actingAs($user)->postJson('/ai/actions/'.$action->id.'/confirm')->assertOk();
        $second = $this->actingAs($user)->postJson('/ai/actions/'.$action->id.'/confirm')->assertOk();

        $this->assertTrue($second->json('deduped'));
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertSame(AiPendingAction::STATUS_EXECUTED, $action->fresh()->status);
    }
}
