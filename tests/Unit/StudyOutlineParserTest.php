<?php

namespace Tests\Unit;

use App\Services\Study\StudyOutlineParser;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1 golden tests: the real growth-psychology outline must parse into
 * 1 book + 16 chapters (15 + appendices as فصل ۱۶) with lessons, topics and
 * page counts — and no instruction text leaking into the structure.
 */
class StudyOutlineParserTest extends TestCase
{
    private array $parsed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parsed = StudyOutlineParser::parse(StudyOutlineFixture::text());
    }

    public function test_detects_book_title_and_16_chapters(): void
    {
        $this->assertSame('روانشناسی رشد', $this->parsed['book_title']);
        $this->assertCount(16, $this->parsed['chapters']);
    }

    public function test_first_chapter_lessons_topics_and_pages(): void
    {
        $ch1 = $this->parsed['chapters'][0];

        $this->assertSame(1, $ch1['number']);
        $this->assertSame(40, $ch1['pages']);
        $this->assertStringStartsWith('فصل ۱:', $ch1['title']);
        $this->assertStringNotContainsString('۴۰', $ch1['title']);
        $this->assertCount(2, $ch1['lessons']);

        $l1 = $ch1['lessons'][0];
        $this->assertSame(10, $l1['pages']);
        $this->assertSame('درسنامه ۱: کلیات روانشناسی رشد', $l1['title']);
        $this->assertSame([
            'رشد انسان به عنوان حوزه‌ای علمی، میان‌رشته‌ای و کاربردی',
            'تعاریف',
            'موضوعات اساسی',
            'مفهوم مرحله در روانشناسی',
            'مفهوم مرحله در فیزیولوژی',
        ], $l1['topics']);

        $l2 = $ch1['lessons'][1];
        $this->assertSame(30, $l2['pages']);
        $this->assertCount(7, $l2['topics']);
    }

    public function test_page_counts_extracted_for_long_lessons(): void
    {
        $ch4 = $this->parsed['chapters'][3];
        $this->assertSame(36, $ch4['pages']);
        $this->assertSame(34, $ch4['lessons'][0]['pages']);
        $this->assertSame('درسنامه ۱: پیاژه', $ch4['lessons'][0]['title']);
        $this->assertCount(8, $ch4['lessons'][0]['topics']);
    }

    public function test_appendices_become_chapter_16_with_exam_tasks(): void
    {
        $ch16 = $this->parsed['chapters'][15];

        $this->assertNull($ch16['number']);
        $this->assertSame(12, $ch16['pages']);
        $this->assertStringContainsString('آزمون', $ch16['title']);
        $this->assertSame([], $ch16['lessons']);
        // 4 exam rows + references line → lesson-less tasks.
        $this->assertCount(5, $ch16['extra_tasks']);
        $this->assertStringContainsString('دکتری ۱۴۰۲', $ch16['extra_tasks'][0]);
    }

    public function test_total_volume_justifies_chunking(): void
    {
        $lessons = 0;
        $subtasks = 0;
        foreach ($this->parsed['chapters'] as $ch) {
            $lessons += count($ch['lessons']);
            foreach ($ch['lessons'] as $lesson) {
                $subtasks += count($lesson['topics']);
            }
        }

        $this->assertSame(37, $lessons);
        // Well above the 100-subtask server cap: one plan can never hold it.
        $this->assertGreaterThan(100, $subtasks);
    }

    public function test_no_instruction_text_leaks_into_structure(): void
    {
        $allText = [$this->parsed['book_title']];
        foreach ($this->parsed['chapters'] as $ch) {
            $allText[] = $ch['title'];
            foreach ($ch['lessons'] as $lesson) {
                $allText[] = $lesson['title'];
                array_push($allText, ...$lesson['topics']);
            }
            array_push($allText, ...$ch['extra_tasks']);
        }
        $joined = implode("\n", $allText);

        $this->assertStringNotContainsString('میخوام', $joined);
        $this->assertStringNotContainsString('نکات', $joined);
        // NOTE: 'مقدمه' legitimately contains 'قدم' — never assert on it.
    }

    public function test_meta_lines_are_ignored_not_chapters(): void
    {
        foreach ($this->parsed['chapters'] as $ch) {
            $this->assertStringNotContainsString('پروژه', $ch['title']);
        }
    }
}
