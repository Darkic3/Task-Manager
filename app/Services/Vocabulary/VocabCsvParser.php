<?php

namespace App\Services\Vocabulary;

/**
 * Pure CSV parser for bulk vocabulary import.
 *
 * Expected columns: front, back, [example], [note].
 * - Delimiter auto-detected from , ; tab.
 * - UTF-8 BOM stripped; optional header row auto-skipped.
 * - No DB access — easily unit-testable.
 */
class VocabCsvParser
{
    public const MAX_ROWS = 1000;

    public const MAX_FRONT = 255;

    public const MAX_BACK = 2000;

    public const MAX_EXTRA = 2000;

    /**
     * @return array{rows: list<array{front: string, back: string, example: ?string, note: ?string}>, errors: list<array{line: int, message: string}>, skipped_header: bool, truncated: bool}
     */
    public static function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = preg_split('/\r\n|\r|\n/', $contents ?? '');

        $rows = [];
        $errors = [];
        $skippedHeader = false;
        $truncated = false;
        $lineNo = 0;

        foreach ($lines as $line) {
            $lineNo++;

            if (trim($line) === '') {
                continue;
            }

            // Explicit empty escape (no escape char) — also silences the PHP 8.4
            // deprecation about str_getcsv's default $escape value changing.
            $cells = str_getcsv($line, static::detectDelimiter($line), '"', '');
            $cells = array_map(fn ($c) => trim((string) $c), $cells);

            // Header auto-detect on the first non-empty line.
            if ($rows === [] && $errors === [] && ! $skippedHeader && static::looksLikeHeader($cells)) {
                $skippedHeader = true;
                continue;
            }

            if (count($rows) >= static::MAX_ROWS) {
                $truncated = true;
                break;
            }

            $front = $cells[0] ?? '';
            $back = $cells[1] ?? '';
            $example = $cells[2] ?? null;
            $note = $cells[3] ?? null;

            if ($front === '' || $back === '') {
                $errors[] = ['line' => $lineNo, 'message' => 'Missing word or meaning.'];
                continue;
            }

            if (mb_strlen($front) > static::MAX_FRONT || mb_strlen($back) > static::MAX_BACK) {
                $errors[] = ['line' => $lineNo, 'message' => 'Word or meaning is too long.'];
                continue;
            }

            $example = $example === '' || $example === null ? null : mb_substr($example, 0, static::MAX_EXTRA);
            $note = $note === '' || $note === null ? null : mb_substr($note, 0, static::MAX_EXTRA);

            $rows[] = ['front' => $front, 'back' => $back, 'example' => $example, 'note' => $note];
        }

        return ['rows' => $rows, 'errors' => $errors, 'skipped_header' => $skippedHeader, 'truncated' => $truncated];
    }

    public static function detectDelimiter(string $line): string
    {
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];

        $top = array_keys($counts, max($counts))[0];

        return $counts[$top] > 0 ? $top : ',';
    }

    private static function looksLikeHeader(array $cells): bool
    {
        $first = mb_strtolower($cells[0] ?? '');
        $second = mb_strtolower($cells[1] ?? '');

        $frontHeaders = ['front', 'word', 'term', 'vocab', 'vocabulary', 'foreign', 'english', 'question', 'لغت', 'کلمه'];
        $backHeaders = ['back', 'meaning', 'translation', 'definition', 'answer', 'persian', 'farsi', 'معنی', 'ترجمه'];

        return in_array($first, $frontHeaders, true) || in_array($second, $backHeaders, true);
    }
}
