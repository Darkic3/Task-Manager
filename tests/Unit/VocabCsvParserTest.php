<?php

namespace Tests\Unit;

use App\Services\Vocabulary\VocabCsvParser;
use PHPUnit\Framework\TestCase;

class VocabCsvParserTest extends TestCase
{
    public function test_parses_simple_csv_with_header(): void
    {
        $parsed = VocabCsvParser::parse("front,back,example\napple,سیب,an apple\nbook,کتاب,\n");

        $this->assertTrue($parsed['skipped_header']);
        $this->assertCount(2, $parsed['rows']);
        $this->assertSame('apple', $parsed['rows'][0]['front']);
        $this->assertSame('سیب', $parsed['rows'][0]['back']);
        $this->assertSame('an apple', $parsed['rows'][0]['example']);
        $this->assertNull($parsed['rows'][1]['example']);
        $this->assertSame([], $parsed['errors']);
    }

    public function test_detects_semicolon_delimiter_and_reports_bad_rows(): void
    {
        $parsed = VocabCsvParser::parse("word;meaning\napple;سیب\nlonely;\n;empty\n");

        $this->assertTrue($parsed['skipped_header']);
        $this->assertCount(1, $parsed['rows']);
        $this->assertCount(2, $parsed['errors']);
        $this->assertSame(3, $parsed['errors'][0]['line']);
    }

    public function test_strips_bom_and_skips_blank_lines(): void
    {
        $parsed = VocabCsvParser::parse("\xEF\xBB\xBFapple,سیب\n\nbook,کتاب\n");

        $this->assertFalse($parsed['skipped_header']);
        $this->assertCount(2, $parsed['rows']);
        $this->assertSame([], $parsed['errors']);
    }
}
