<?php

namespace Tests\Unit;

use App\Models\AiPlan;
use App\Services\Study\StudyOutlineParser;
use App\Services\Study\StudyPlanChunker;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2 golden tests: the parsed book must pack into sequential chunks
 * that each fit the server caps (3 sub-projects / 30 tasks / 100 subtasks),
 * with clean titles and page counts in description + weight (Model B).
 */
class StudyPlanChunkerTest extends TestCase
{
    /** @var array<int, array> */
    private array $chunks;

    protected function setUp(): void
    {
        parent::setUp();
        $outline = StudyOutlineParser::parse(StudyOutlineFixture::text());
        $this->chunks = StudyPlanChunker::chunk($outline);
    }

    public function test_book_packs_into_six_chunks(): void
    {
        $this->assertCount(6, $this->chunks);
    }

    public function test_every_chunk_fits_server_caps(): void
    {
        foreach ($this->chunks as $chunk) {
            $this->assertNull(
                StudyPlanChunker::checkChunk($chunk),
                'Chunk '.$chunk['chunk_index'].' violates caps'
            );
            $this->assertLessThanOrEqual(AiPlan::MAX_SUBPROJECTS, $chunk['totals']['subprojects']);
            $this->assertLessThanOrEqual(AiPlan::MAX_TASKS, $chunk['totals']['tasks']);
            $this->assertLessThanOrEqual(AiPlan::MAX_SUBTASKS, $chunk['totals']['subtasks']);
        }
    }

    public function test_chunk_ranges_cover_all_16_chapters_in_order(): void
    {
        $seen = [];
        foreach ($this->chunks as $chunk) {
            foreach ($chunk['subprojects'] as $sub) {
                $seen[] = $sub['title'];
            }
        }

        $this->assertCount(16, $seen);
        $this->assertStringStartsWith('فصل ۱:', $seen[0]);
        $this->assertStringStartsWith('فصل ۳:', $seen[2]);
        $this->assertStringContainsString('آزمون', $seen[15]);

        // Chunk 1 = chapters 1-3, last chunk = appendices alone.
        $this->assertCount(3, $this->chunks[0]['subprojects']);
        $this->assertCount(1, $this->chunks[5]['subprojects']);
        $this->assertSame(1, $this->chunks[0]['chunk_index']);
        $this->assertSame(6, $this->chunks[5]['total_chunks']);
    }

    public function test_first_chunk_builds_book_later_chunks_attach(): void
    {
        $this->assertFalse($this->chunks[0]['attach']);
        $this->assertSame('روانشناسی رشد', $this->chunks[0]['book_title']);

        foreach (array_slice($this->chunks, 1) as $chunk) {
            $this->assertTrue($chunk['attach']);
            $this->assertSame('روانشناسی رشد', $chunk['book_title']);
        }
    }

    public function test_no_task_is_lost_across_chunks(): void
    {
        $outline = StudyOutlineParser::parse(StudyOutlineFixture::text());

        $expectedTasks = 0;
        $expectedSubs = 0;
        foreach ($outline['chapters'] as $ch) {
            $expectedTasks += StudyOutlineParser::countTasks($ch);
            $expectedSubs += StudyOutlineParser::countSubtasks($ch);
        }

        $gotTasks = 0;
        $gotSubs = 0;
        foreach ($this->chunks as $chunk) {
            $gotTasks += $chunk['totals']['tasks'];
            $gotSubs += $chunk['totals']['subtasks'];
        }

        $this->assertSame($expectedTasks, $gotTasks);
        $this->assertSame($expectedSubs, $gotSubs);
        // 37 lessons + 5 appendix tasks.
        $this->assertSame(42, $gotTasks);
    }

    public function test_pages_in_description_and_weight_never_in_title(): void
    {
        $checked = 0;
        foreach ($this->chunks as $chunk) {
            foreach ($chunk['subprojects'] as $sub) {
                foreach ($sub['tasks'] as $task) {
                    if (($task['pages'] ?? null) !== null) {
                        $checked++;
                        $this->assertSame('📄 '.$task['pages'].' صفحه', $task['description']);
                        $this->assertSame($task['pages'], $task['weight']);
                        // A single-number page marker must not survive in the title
                        // (ranges like "(صفحات ۳۶۳ تا ۳۶۴)" stay verbatim).
                        $this->assertDoesNotMatchRegularExpression(
                            '/\(\s*(?:حدود\s+)?\d+\s*صفحه\s*\)/u',
                            $task['title']
                        );
                    }
                }
            }
        }
        $this->assertGreaterThan(30, $checked);
    }

    public function test_long_titles_are_truncated_to_120_chars(): void
    {
        $long = str_repeat('الف ', 80);
        $this->assertLessThanOrEqual(120, mb_strlen(StudyPlanChunker::shortTitle($long)));
    }
}
