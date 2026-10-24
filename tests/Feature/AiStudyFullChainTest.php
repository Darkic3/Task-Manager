<?php

namespace Tests\Feature;

use App\Models\AiPlan;
use App\Models\AiStudyBuild;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Study\StudyOutlineParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\StudyOutlineFixture;

/**
 * Phase 5 — the user's own scenario, end to end: one pasted book prompt in
 * agent mode (no provider key, no LLM) → 6 chained chunk cards → every
 * chunk confirmed → the whole book exists exactly once with pages recorded.
 */
class AiStudyFullChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_book_builds_exactly_once_across_all_chunks(): void
    {
        $user = User::factory()->create();

        // 1) The user's paste hits the server fast-path (no provider needed).
        $stream = $this->actingAs($user)->post('/ai/stream', [
            'message' => StudyOutlineFixture::text(),
            'mode' => 'agent',
        ])->assertOk()->streamedContent();

        $planId = null;
        foreach (explode("\n", $stream) as $line) {
            $line = trim($line);
            if (! str_starts_with($line, 'data: ')) {
                continue;
            }
            $decoded = json_decode(substr($line, 6), true);
            if (($decoded['type'] ?? null) === 'plan_proposal' && isset($decoded['plan']['id'])) {
                $planId = $decoded['plan']['id'];
                break;
            }
        }
        $this->assertNotNull($planId, 'stream must emit a plan_proposal for the pasted book');

        // 2) Confirm every chunk card until the chain reports done.
        $iterations = 0;
        $lastPlan = null;
        while ($planId !== null && $iterations < 8) {
            $iterations++;
            $plan = AiPlan::findOrFail($planId);
            if ($plan->status === AiPlan::STATUS_PROPOSED) {
                $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();
            }
            $res = $this->actingAs($user)->postJson(
                route('ai.plans.confirm-phase', $plan),
                ['run_all' => true]
            )->assertOk();

            $res->assertJsonPath('plan.status', 'done');
            $lastPlan = $res;
            $planId = $res->json('next_plan.id');
        }

        $this->assertNotNull($lastPlan);
        $lastPlan->assertJsonPath('study_build.status', 'done');
        $lastPlan->assertJsonPath('study_build.done_chunks', 6);
        $this->assertLessThan(8, $iterations, 'chain must finish within 6 chunks + margin');

        // 3) The whole book exists exactly once.
        $build = AiStudyBuild::firstOrFail();
        $this->assertSame(AiStudyBuild::STATUS_DONE, $build->status);
        $this->assertSame(6, $build->done_chunks);
        $this->assertCount(6, $build->plan_ids);

        $books = Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد');
        $this->assertSame(1, $books->count(), 'book project must never duplicate');
        $book = $books->first();

        $this->assertSame(
            16,
            Project::where('user_id', $user->id)->where('parent_id', $book->id)->count(),
            '15 chapters + appendices as sub-projects'
        );

        // 4) Expected volumes derived from the parser — never hardcoded.
        $outline = StudyOutlineParser::parse(StudyOutlineFixture::text());
        $expectedTasks = 0;
        $expectedSubs = 0;
        foreach ($outline['chapters'] as $ch) {
            $expectedTasks += StudyOutlineParser::countTasks($ch);
            $expectedSubs += StudyOutlineParser::countSubtasks($ch);
        }

        $this->assertSame($expectedTasks, Task::where('user_id', $user->id)->whereNotNull('project_id')->whereNull('parent_id')->count());
        $this->assertSame($expectedSubs, Task::where('user_id', $user->id)->whereNotNull('parent_id')->count());

        // 5) Pages contract: clean titles, pages in description + weight.
        $lesson = Task::where('user_id', $user->id)->where('title', 'درسنامه ۱: پیاژه')->firstOrFail();
        $this->assertSame(34, (int) $lesson->weight);
        $this->assertStringStartsWith('📄 34 صفحه', (string) $lesson->description);
        $this->assertStringNotContainsString('(', 'درسنامه ۱: پیاژه');

        $chapter = Project::where('user_id', $user->id)->where('name', 'فصل ۴: نظریه‌های شناختی (پیاژه و برونر)')->firstOrFail();
        $this->assertSame($book->id, (int) $chapter->parent_id);
    }
}
