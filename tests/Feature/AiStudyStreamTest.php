<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiPlan;
use App\Models\AiStudyBuild;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\StudyOutlineFixture;

/**
 * Phase 4: the live agent endpoint intercepts a multi-chunk book paste
 * server-side — no provider key needed, no LLM call — and emits a
 * plan_proposal card with the study_build progress. Single-chunk pastes
 * and chat mode fall through untouched.
 */
class AiStudyStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_stream_intercepts_full_book_without_provider(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/ai/stream', [
            'message' => StudyOutlineFixture::text(),
            'mode' => 'agent',
        ]);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('plan_proposal', $content);
        $this->assertStringContainsString('study_build', $content);
        $this->assertStringContainsString('"total_chunks":6', $content);

        // The Persian title is JSON-escaped on the wire — decode the packet.
        $packet = null;
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'data: ')) {
                $decoded = json_decode(substr($line, 6), true);
                if (($decoded['type'] ?? null) === 'plan_proposal') {
                    $packet = $decoded;
                    break;
                }
            }
        }
        $this->assertNotNull($packet, 'plan_proposal packet missing from SSE stream');
        $this->assertSame('روانشناسی رشد — بسته 1 از 6 (فصل 1 تا 3)', $packet['plan']['title']);
        $this->assertSame(6, $packet['study_build']['total_chunks']);
        $this->assertSame('روانشناسی رشد', $packet['study_build']['title']);

        // Chain + first chunk plan persisted; nothing executed yet.
        $this->assertSame(1, AiStudyBuild::count());
        $build = AiStudyBuild::firstOrFail();
        $this->assertSame(6, $build->total_chunks);
        $this->assertSame(0, $build->done_chunks);
        $plan = AiPlan::firstOrFail();
        $this->assertSame(AiPlan::STATUS_PROPOSED, $plan->status);
        $this->assertCount(1, $plan->structure['projects']);
        $this->assertCount(3, $plan->structure['projects'][0]['subprojects']);

        // Assistant summary saved to the owned conversation.
        $conv = AiConversation::where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($conv->messages()->where('role', 'assistant')->exists());
    }

    public function test_chat_mode_falls_through_to_normal_flow(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/ai/stream', [
            'message' => StudyOutlineFixture::text(),
            'mode' => 'chat',
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('plan_proposal', $response->streamedContent());
        $this->assertSame(0, AiStudyBuild::count());
        $this->assertSame(0, AiPlan::count());
    }

    public function test_single_chunk_paste_falls_through_to_normal_flow(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/ai/stream', [
            'message' => "کتاب ریاضی را بساز:\nفصل ۱: جبر (۱۰ صفحه)\nدرسنامه ۱: معادلات (۵ صفحه)\nمعادله درجه اول\n",
            'mode' => 'agent',
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('plan_proposal', $response->streamedContent());
        $this->assertSame(0, AiStudyBuild::count());
    }

    public function test_non_stream_chat_intercepts_full_book(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/ai/chat', [
            'message' => StudyOutlineFixture::text(),
            'mode' => 'agent',
        ]);

        $response->assertOk()
            ->assertJsonPath('proposals.0.study_build.total_chunks', 6)
            ->assertJsonPath('proposals.0.plan.preview.totals.subprojects', 3);
        $this->assertSame(1, AiStudyBuild::count());
    }
}
