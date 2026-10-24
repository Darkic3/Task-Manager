<?php

namespace Tests\Unit;

use App\Services\AiIntentRouter;
use App\Services\Study\StudyBuildOrchestrator;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4: server-side full-paste detection + router signals for study
 * outlines. A multi-chunk outline with a build verb must be recognized
 * without the word "پروژه" and without line-start bullets; anything else
 * must fall through to the normal model flow untouched.
 */
class StudyDetectTest extends TestCase
{
    private function smallOutline(): string
    {
        return <<<'OUTLINE'
کتاب ریاضی را بساز:
فصل ۱: جبر (۱۰ صفحه)
درسنامه ۱: معادلات (۵ صفحه)
معادله درجه اول
معادله درجه دوم
فصل ۲: هندسه (۸ صفحه)
درسنامه ۱: مثلثات (۸ صفحه)
سینوس
کسینوس
OUTLINE;
    }

    public function test_structure_count_sees_midline_persian_headers(): void
    {
        $norm = AiIntentRouter::normalize(StudyOutlineFixture::text());

        // 15 numbered فصل + 37 درسنامه + unnumbered بخش appendices.
        $this->assertGreaterThanOrEqual(40, AiIntentRouter::countStructureHeaders($norm));
        $this->assertSame(4, AiIntentRouter::countStructureHeaders(AiIntentRouter::normalize($this->smallOutline())));
        $this->assertSame(0, AiIntentRouter::countStructureHeaders(AiIntentRouter::normalize('یه تسک بساز: خرید شیر')));
    }

    public function test_full_outline_without_project_noun_routes_plan_build(): void
    {
        $msg = "این کتاب را بساز:\n".$this->smallOutline();
        $route = AiIntentRouter::route($msg);

        $this->assertSame('plan_build', $route['intent']);
        $this->assertSame('high', $route['confidence']);
        $this->assertGreaterThanOrEqual(2, $route['entities']['structure_count']);
    }

    public function test_single_task_build_verb_without_structure_stays_mutation(): void
    {
        $route = AiIntentRouter::route('یه تسک بساز: خرید شیر');

        $this->assertSame('task_mutation', $route['intent']);
    }

    public function test_structure_question_without_verb_is_not_plan_build(): void
    {
        $route = AiIntentRouter::route('فصل ۱ روانشناسی رشد درباره چیست؟');

        $this->assertNotSame('plan_build', $route['intent']);
    }

    public function test_detect_full_outline_returns_six_chunks(): void
    {
        $detected = StudyBuildOrchestrator::detectFromMessage(StudyOutlineFixture::text());

        $this->assertNotNull($detected);
        $this->assertCount(6, $detected['chunks']);
        $this->assertSame('روانشناسی رشد', $detected['outline']['book_title']);
    }

    public function test_detect_ignores_single_chunk_paste(): void
    {
        // Fits one plan_propose (1 chapter) → model path, not the chain.
        $this->assertNull(StudyBuildOrchestrator::detectFromMessage($this->smallOutline()));
    }

    public function test_detect_ignores_outline_without_build_verb(): void
    {
        // Strip every build verb the detector looks for ("درست کنی" covers
        // the "درست کن" needle) — structure alone must not trigger the chain.
        $text = str_replace(
            ['بساز', 'بنا کن', 'ایجاد', 'درست کن', 'راه بنداز', 'راهاندازی', 'برنامهریزی', 'طراحی کن'],
            'بررسی',
            StudyOutlineFixture::text()
        );

        $this->assertNull(StudyBuildOrchestrator::detectFromMessage($text));
    }

    public function test_detect_ignores_plain_text(): void
    {
        $this->assertNull(StudyBuildOrchestrator::detectFromMessage('سلام، گزارش هفته را بده'));
        $this->assertNull(StudyBuildOrchestrator::detectFromMessage(''));
    }
}
