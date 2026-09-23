<?php

namespace Tests\Feature;

use App\Http\Controllers\AiChatController;
use App\Models\AiSetting;
use App\Models\Project;
use App\Models\Routine;
use App\Models\User;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiModesTest extends TestCase
{
    use RefreshDatabase;

    private function withOpenAi(User $user): void
    {
        AiSetting::create([
            'user_id' => $user->id,
            'default_provider' => 'openai',
            'openai_key' => 'sk-test-key',
            'openai_model' => 'gpt-4o-mini',
        ]);
    }

    public function test_chat_mode_sends_no_tools(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);

        Http::fake(fn () => Http::response(['choices' => [['message' => ['content' => 'Hi there']]]], 200));

        $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Build me a task', 'mode' => 'chat'])
            ->assertOk()
            ->assertJsonMissing(['proposal'])
            ->assertJsonPath('reply', 'Hi there');

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return ! array_key_exists('tools', $payload ?? []);
        });
        $this->assertDatabaseCount('ai_pending_actions', 0);
    }

    public function test_agent_mode_creates_proposal_from_tool_call(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);
        Project::factory()->create(['user_id' => $user->id]);

        Http::fake(fn () => Http::response(['choices' => [['message' => [
            'content' => '',
            'tool_calls' => [[
                'id' => 'call_1', 'type' => 'function',
                'function' => ['name' => 'task.create', 'arguments' => json_encode(['title' => 'Agent task'])],
            ]],
        ]]]], 200));

        $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Make a task', 'mode' => 'agent'])
            ->assertOk()
            ->assertJsonPath('proposal.tool', 'task.create');

        $this->assertDatabaseHas('ai_pending_actions', ['user_id' => $user->id, 'tool' => 'task.create']);
    }

    public function test_chat_prompt_is_read_only_and_agent_prompt_acts(): void
    {
        $user = User::factory()->create();
        $controller = new AiChatController(app(\App\Services\AiProviderService::class));
        $ref = new \ReflectionMethod($controller, 'buildMessages');

        $chat = $ref->invoke($controller, $user, 'CTX', [], 'hi', 'chat');
        $this->assertStringContainsString('MODE: CHAT', $chat[0]['content']);
        $this->assertStringNotContainsString('tool_choice', $chat[0]['content']);

        $agent = $ref->invoke($controller, $user, 'CTX', [], 'hi', 'agent');
        $this->assertStringContainsString('MODE: AGENT', $agent[0]['content']);
    }

    public function test_routine_tools_validate_execute_and_preview(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;

        $check = $svc->validateCall('routine.create', [
            'title' => 'Workout', 'frequency' => 'weekly', 'days' => ['monday', 'wednesday'],
        ], $user);
        $this->assertTrue($check['ok']);

        $result = $svc->execute('routine.create', $check['resolved'], $user);
        $this->assertTrue($result['ok']);
        $routine = Routine::find($result['id']);
        $this->assertEquals(['monday', 'wednesday'], $routine->decodedDays());

        $bad = $svc->validateCall('routine.create', ['title' => 'X', 'frequency' => 'weekly'], $user);
        $this->assertFalse($bad['ok']);

        $done = $svc->execute('routine.complete', ['id' => $routine->id, 'title' => $routine->title, 'date' => now()->toDateString()], $user);
        $this->assertTrue($done['ok']);
        $this->assertTrue($routine->fresh()->completedOn(now()));

        $again = $svc->execute('routine.complete', ['id' => $routine->id, 'title' => $routine->title, 'date' => now()->toDateString()], $user);
        $this->assertStringContainsString('already done', $again['message']);

        $preview = $svc->preview('routine.delete', ['id' => $routine->id, 'title' => $routine->title], $user);
        $this->assertTrue($preview['danger']);
        $this->assertStringContainsString('1 recorded', $preview['impact']);

        $del = $svc->execute('routine.delete', ['id' => $routine->id], $user);
        $this->assertTrue($del['ok']);
        $this->assertSoftDeleted('routines', ['id' => $routine->id]);
    }
}
