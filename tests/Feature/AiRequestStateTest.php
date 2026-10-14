<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\Task;
use App\Models\User;
use App\Services\AiContextEngine;
use App\Services\AiRequestState;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3, Step 2 — Request State.
 *
 * History text is truncated on read (800 chars/message, bounded budgets);
 * structured per-conversation state bridges what truncation drops, without
 * ever becoming authorization (ids are re-resolved + ownership-checked).
 */
class AiRequestStateTest extends TestCase
{
    use RefreshDatabase;

    private function conversation(User $user): AiConversation
    {
        return AiConversation::create(['user_id' => $user->id, 'label' => 'T']);
    }

    private function say(AiConversation $conv, string $role, string $content): void
    {
        $conv->messages()->create(['role' => $role, 'content' => $content]);
    }

    /* ── repro: long correction details are cut from history text ── */

    public function test_long_correction_is_truncated_in_history_text(): void
    {
        $user = User::factory()->create();
        $conv = $this->conversation($user);
        // Entity mention buried past the 800-char per-message cut.
        $this->say($conv, 'user', str_repeat('x', 900).' تسک خرید شیر ');

        $ctx = app(AiContextEngine::class)->build(
            $user, 'تاریخش کن فردا', 'agent', ['conversation_id' => $conv->id]
        );

        $historyText = collect($ctx['history'])->pluck('content')->join("\n");
        $this->assertStringNotContainsString('خرید شیر', $historyText, 'repro: detail past the cut never reaches the model as text');
    }

    /* ── state bridges the cut: id survives ── */

    public function test_state_injects_resolved_entity_despite_truncation(): void
    {
        $user = User::factory()->create();
        $conv = $this->conversation($user);
        $this->say($conv, 'user', str_repeat('x', 900).' تسک خرید شیر ');
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'خرید شیر']);

        AiRequestState::record($conv, [
            'resolved' => ['tasks' => [['id' => $task->id, 'title' => 'خرید شیر']]],
            'open' => ['actions' => [11]],
        ]);

        $ctx = app(AiContextEngine::class)->build(
            $user, 'تاریخش کن فردا', 'agent', ['conversation_id' => $conv->id]
        );

        $this->assertTrue($ctx['meta']['state_applied'] ?? false);
        $this->assertStringContainsString((string) $task->id, $ctx['text']);
    }

    /* ── consecutive edits resolve to the same row ── */

    public function test_consecutive_edits_keep_the_same_task(): void
    {
        $user = User::factory()->create();
        $conv = $this->conversation($user);
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'خرید شیر']);

        // Turn 1: proposal records the resolved entity.
        AiRequestState::record($conv, [
            'resolved' => ['tasks' => [['id' => $task->id, 'title' => 'خرید شیر']]],
        ]);

        // Turn 2: correction reuses the state id (as the model would).
        $state = AiRequestState::read($conv->fresh());
        $taskId = $state['resolved']['tasks'][0]['id'];

        $check = (new AiToolService)->validateCall('task_update', [
            'id' => $taskId, 'due_date' => now()->addDay()->toDateString(),
        ], $user);

        $this->assertTrue($check['ok'], $check['error'] ?? '');
        $this->assertSame($task->id, $check['resolved']['id']);
    }

    /* ── isolation: state never leaks across conversations/users ── */

    public function test_state_is_scoped_to_its_conversation(): void
    {
        $user = User::factory()->create();
        $convA = $this->conversation($user);
        $convB = $this->conversation($user);
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'محرمانه']);

        AiRequestState::record($convA, [
            'resolved' => ['tasks' => [['id' => $task->id, 'title' => 'محرمانه']]],
        ]);

        $ctx = app(AiContextEngine::class)->build(
            $user, 'سلام', 'agent', ['conversation_id' => $convB->id]
        );

        $this->assertFalse($ctx['meta']['state_applied'] ?? false);
        $this->assertStringNotContainsString((string) $task->id, $ctx['text']);

        // And never to another user, even with a guessed conversation id.
        $other = User::factory()->create();
        $ctxOther = app(AiContextEngine::class)->build(
            $other, 'سلام', 'agent', ['conversation_id' => $convA->id]
        );
        $this->assertStringNotContainsString((string) $task->id, $ctxOther['text']);
    }

    /* ── staleness: old state is ignored ── */

    public function test_stale_state_is_ignored(): void
    {
        $user = User::factory()->create();
        $conv = $this->conversation($user);
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'قدیمی']);

        AiRequestState::record($conv, [
            'resolved' => ['tasks' => [['id' => $task->id, 'title' => 'قدیمی']]],
        ]);
        $conv->update(['state' => array_merge($conv->fresh()->state, [
            'updated_at' => now()->subDays(2)->toIso8601String(),
        ])]);

        $ctx = app(AiContextEngine::class)->build(
            $user, 'سلام', 'agent', ['conversation_id' => $conv->id]
        );

        $this->assertFalse($ctx['meta']['state_applied'] ?? false);
        $this->assertStringNotContainsString((string) $task->id, $ctx['text']);
    }

    /* ── untrusted: state ids still go through resolvers ── */

    public function test_state_id_for_foreign_task_still_rejected(): void
    {
        $user = User::factory()->create();
        $conv = $this->conversation($user);
        $foreign = Task::factory()->create(['user_id' => User::factory()->create()->id, 'title' => 'بیگانه']);

        // Even if poisoned state names a foreign id, resolution refuses it.
        AiRequestState::record($conv, [
            'resolved' => ['tasks' => [['id' => $foreign->id, 'title' => 'بیگانه']]],
        ]);

        $check = (new AiToolService)->validateCall('task_update', [
            'id' => $foreign->id, 'description' => 'x',
        ], $user);

        $this->assertFalse($check['ok']);
    }

    /* ── bounded: open lists union newest-first under a hard cap ── */

    public function test_state_rewrites_stay_bounded(): void
    {
        $user = User::factory()->create();
        $conv = $this->conversation($user);

        for ($i = 1; $i <= 5; $i++) {
            AiRequestState::record($conv, [
                'open' => ['actions' => range($i * 10, $i * 10 + 30)],
            ]);
        }

        $state = AiRequestState::read($conv->fresh());
        $ids = array_column($state['open']['actions'], 'id');
        $this->assertCount(20, $ids);
        $this->assertContains(80, $ids, 'newest first');
        $this->assertNotContains(10, $ids, 'oldest evicted under the cap');
    }

    /* ── end-to-end: proposal writes state, next turn reads it ── */

    public function test_stream_proposal_records_state_for_next_turn(): void
    {
        $user = User::factory()->create();
        \App\Models\AiSetting::create([
            'user_id' => $user->id,
            'default_provider' => 'openai',
            'openai_key' => 'sk-test-key',
            'openai_model' => 'gpt-4o-mini',
        ]);

        $calls = 0;
        \Illuminate\Support\Facades\Http::fake(function ($request) use (&$calls) {
            $calls++;
            if ($calls === 1) {
                return \Illuminate\Support\Facades\Http::response(
                    'data: '.json_encode(['choices' => [['delta' => ['tool_calls' => [[
                        'index' => 0, 'id' => 'call_1', 'type' => 'function',
                        'function' => ['name' => 'task_create', 'arguments' => json_encode(['title' => 'State task'])],
                    ]]]]]])."\n\n".'data: [DONE]'."\n\n",
                    200,
                    ['Content-Type' => 'text/event-stream']
                );
            }

            return \Illuminate\Support\Facades\Http::response(
                'data: '.json_encode(['choices' => [['delta' => ['content' => 'ok']]]])."\n\n".'data: [DONE]'."\n\n",
                200,
                ['Content-Type' => 'text/event-stream']
            );
        });

        // Turn 1: proposal for a new task.
        $res = $this->actingAs($user)->post(route('ai.stream'), ['message' => 'یه تسک بساز', 'mode' => 'agent']);
        $res->assertOk();
        $res->streamedContent();
        $actionId = \App\Models\AiPendingAction::where('user_id', $user->id)->firstOrFail()->id;
        $convId = \App\Models\AiPendingAction::where('user_id', $user->id)->firstOrFail()->conversation_id;

        $state = AiRequestState::read(\App\Models\AiConversation::find($convId));
        $this->assertSame('task_mutation', $state['intent'] ?? null);
        $this->assertSame($actionId, $state['open']['actions'][0]['id']);

        // Turn 2 (same conversation): the correction turn sees the open proposal.
        $this->actingAs($user)->post(route('ai.stream'), [
            'conversation_id' => $convId, 'message' => 'عنوانش کن تسک ایالتی', 'mode' => 'agent',
        ])->streamedContent();
        $ctx = app(AiContextEngine::class)->build(
            $user, 'عنوانش کن تسک ایالتی', 'agent', ['conversation_id' => $convId]
        );
        $this->assertStringContainsString('action #'.$actionId, $ctx['text']);
    }
}
