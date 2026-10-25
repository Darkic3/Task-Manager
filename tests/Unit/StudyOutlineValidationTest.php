<?php

namespace Tests\Unit;

use App\Services\Study\StudyBuildOrchestrator;
use App\Services\Study\StudyOutlineParser;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1 regression: validateOutline() is the gate before a parsed
 * structure may be stored or offered for approval as a Master Plan.
 * Pure unit test — no database.
 */
class StudyOutlineValidationTest extends TestCase
{
    private function validOutline(): array
    {
        return StudyBuildOrchestrator::sanitizeOutline(
            StudyOutlineParser::parse(StudyOutlineFixture::cognitiveRegressionExcerpt())
        );
    }

    public function test_healthy_regression_outline_is_valid(): void
    {
        $this->assertNull(StudyBuildOrchestrator::validateOutline($this->validOutline()));
    }

    public function test_growth_psychology_fixture_stays_valid(): void
    {
        $outline = StudyBuildOrchestrator::sanitizeOutline(
            StudyOutlineParser::parse(StudyOutlineFixture::text())
        );

        $this->assertNull(StudyBuildOrchestrator::validateOutline($outline));
    }

    public function test_placeholder_title_is_rejected(): void
    {
        $outline = $this->validOutline();
        $outline['book_title'] = 'کتاب بدون عنوان';

        $this->assertNotNull(StudyBuildOrchestrator::validateOutline($outline));
    }

    public function test_missing_title_is_rejected(): void
    {
        $outline = $this->validOutline();
        $outline['book_title'] = '   ';

        $this->assertNotNull(StudyBuildOrchestrator::validateOutline($outline));
    }

    public function test_parse_errors_are_rejected(): void
    {
        $outline = $this->validOutline();
        $outline['parse_errors'] = ['سرعنوان فصل در خط 9 شماره‌گذاری پشتیبانی‌شده ندارد.'];

        $error = StudyBuildOrchestrator::validateOutline($outline);
        $this->assertNotNull($error);
        $this->assertStringContainsString('کامل و روشن نیست', (string) $error);
    }

    public function test_duplicate_chapter_numbers_are_rejected(): void
    {
        $outline = $this->validOutline();
        $outline['chapters'][1]['number'] = 1;
        $outline['chapters'][1]['title'] = 'فصل ۱: تکراری';

        $error = StudyBuildOrchestrator::validateOutline($outline);
        $this->assertNotNull($error);
        $this->assertStringContainsString('تکراری', (string) $error);
    }

    public function test_skipped_chapter_number_is_rejected(): void
    {
        // Simulates the lost "فصل ۴": chapter 5 follows chapter 3.
        $outline = $this->validOutline();
        $outline['chapters'][3]['number'] = 5;
        $outline['chapters'][3]['title'] = 'فصل ۵: توجه و هشیاری';

        $error = StudyBuildOrchestrator::validateOutline($outline);
        $this->assertNotNull($error);
        $this->assertStringContainsString('جا افتاده', (string) $error);
    }

    public function test_duplicate_lesson_numbers_are_rejected(): void
    {
        $outline = $this->validOutline();
        $outline['chapters'][0]['lessons'][1]['number'] = 1;
        $outline['chapters'][0]['lessons'][1]['title'] = 'درسنامه ۱: تکراری';

        $error = StudyBuildOrchestrator::validateOutline($outline);
        $this->assertNotNull($error);
        $this->assertStringContainsString('تکراری', (string) $error);
    }

    public function test_chapter_lesson_page_mismatch_is_rejected(): void
    {
        $outline = $this->validOutline();
        $outline['chapters'][0]['pages'] = 999;

        $error = StudyBuildOrchestrator::validateOutline($outline);
        $this->assertNotNull($error);
        $this->assertStringContainsString('صفحات', (string) $error);
    }

    public function test_single_chapter_outline_stays_on_simple_plan_path(): void
    {
        $outline = $this->validOutline();
        $outline['chapters'] = [$outline['chapters'][0]];

        $this->assertNotNull(StudyBuildOrchestrator::validateOutline($outline));
    }
}
