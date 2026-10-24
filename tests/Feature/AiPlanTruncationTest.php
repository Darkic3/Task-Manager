<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AiProviderService;
use App\Services\AiTooling\ToolError;
use App\Services\AiTooling\ToolSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPlanTruncationTest extends TestCase
{
    use RefreshDatabase;

    public function test_truncated_plan_args_get_distinct_code(): void
    {
        // Simulates a provider cut off mid-arguments: long, unbalanced JSON
        // (a realistic cut-off payload is several KB of Persian titles).
        $lesson = '{"title":"درسنامه مفاهیم اساسی در نظریه روانشناسی رشد و تحول"},"';
        $cut = '{"title":"کتاب رشد","project":{"name":"روانشناسی رشد","tasks":[{"title":"فصل ۱: موضوعات اساسی","subtasks":['
            . rtrim(str_repeat($lesson, 60), ',')
            . ',{"title":"درسنامه ۱';
        $this->assertGreaterThan(2000, mb_strlen($cut));
        $r = ToolSchema::envelope($cut);
        $this->assertFalse($r['ok']);
        $this->assertSame(ToolError::TRUNCATED_RESPONSE, $r['code']);
        $this->assertStringContainsString('Split the request', $r['error']);
    }

    public function test_short_garbage_keeps_generic_error(): void
    {
        $r = ToolSchema::envelope('not json at all');
        $this->assertFalse($r['ok']);
        $this->assertSame(ToolError::VALIDATION_ERROR, $r['code']);
        $this->assertSame('Tool arguments must be a JSON object.', $r['error']);

        // Cleanly-closed scalar (e.g. double-encoded string) is a model bug,
        // not truncation — even when moderately long it must not misfire.
        $r = ToolSchema::envelope('"just a string"');
        $this->assertFalse($r['ok']);
        $this->assertSame(ToolError::VALIDATION_ERROR, $r['code']);
    }

    public function test_valid_json_string_still_passes(): void
    {
        $r = ToolSchema::envelope('{"title":"x"}');
        $this->assertTrue($r['ok']);
        $this->assertSame(['title' => 'x'], $r['args']);
    }

    public function test_plan_tool_gets_16000_max_tokens(): void
    {
        $svc = new AiProviderService;
        $plan = [['function' => ['name' => 'plan_propose']]];
        $task = [['function' => ['name' => 'task_create']]];
        $workout = [['function' => ['name' => 'workout_plan_propose']]];

        $this->assertSame(16000, $svc->openAiPayload([], 'm', false, null, $plan)['max_tokens']);
        $this->assertSame(16000, $svc->openAiPayload([], 'm', false, null, $workout)['max_tokens']);
        $this->assertSame(4096, $svc->openAiPayload([], 'm', false, null, $task)['max_tokens']);
        $this->assertSame(2048, $svc->openAiPayload([], 'm')['max_tokens']);
    }

    public function test_ui_maps_truncation_to_persian_chunk_guidance(): void
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get(route('ai.index'))->assertOk()->getContent();

        $this->assertStringContainsString('ناقص ماند', $html);
        $this->assertStringContainsString('بسته بعدی', $html);
    }
}
