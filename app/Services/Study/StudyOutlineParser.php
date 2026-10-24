<?php

namespace App\Services\Study;

/**
 * StudyOutlineParser — Phase 1 of the automatic book-chain build.
 *
 * Turns a pasted Persian book outline (chapters / lessons / micro-topics,
 * e.g. فصل / درسنامه) into a clean machine-readable structure.
 *
 * Contract:
 * - Titles are kept VERBATIM (user wording), only page markers are stripped.
 * - Page counts like "(۴۰ صفحه)" / "(حدود ۱۲ صفحه)" are extracted to `pages`.
 * - Instruction/meta lines (preamble, "نکات: ...", anything with میخوام/باید/...)
 *   are ignored — they are agent instructions, not book content.
 * - An unnumbered "بخش ..." block (e.g. exams + references at the end of a
 *   book) becomes a regular chapter (appendices), per the فصل-۱۶ decision.
 *
 * Output shape:
 *   [
 *     'book_title' => 'روانشناسی رشد',
 *     'chapters'   => [
 *       [
 *         'index'       => 1,          // running order (1-based)
 *         'number'      => 1|null,     // parsed chapter number, if any
 *         'title'       => 'فصل ۱: ...', // clean header, no page marker
 *         'pages'       => 40|null,
 *         'lessons'     => [
 *           ['index'=>1,'number'=>1,'title'=>'درسنامه ۱: ...','pages'=>10|null,
 *            'topics'=>['...', ...]],
 *         ],
 *         'extra_tasks' => [...],      // topic lines with no lesson above them
 *       ],
 *     ],
 *   ]
 *
 * Pure function, no DB — safe to call from the agent pipeline or tests.
 */
class StudyOutlineParser
{
    private const CHAPTER_KEYWORDS = ['فصل', 'بخش', 'چاپبر', 'چاپتر'];

    private const LESSON_KEYWORDS = ['درسنامه', 'درس', 'گفتار', 'واحد'];

    private const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const AR_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /** Substrings that mark a line as an agent instruction, not book content. */
    private const META_NEEDLES = [
        'میخوام', 'می خواهم', 'باید', 'نکات', 'توجه', 'راهنما',
        'توی عنوان', 'عنوانش', 'زیربخش', 'زیر بخش', 'قدم های', 'چانک', 'بسته ',
        'پیاده سازی', 'ساختار کامل', 'موضوعاتش',
    ];

    /**
     * Normalize a line for DETECTION only (titles keep the original text).
     * Mirrors AiIntentRouter::normalize: fa/ar digits → latin, Arabic
     * kaf/yeh → Persian, ZWNJ/ZWJ/tatweel removed, spaces collapsed.
     */
    public static function normalizeForMatch(string $line): string
    {
        $line = str_replace(self::FA_DIGITS, range(0, 9), $line);
        $line = str_replace(self::AR_DIGITS, range(0, 9), $line);
        $line = str_replace(['ي', 'ك', 'ة', 'ـ', "\xE2\x80\x8C", "\xE2\x80\x8D"], ['ی', 'ک', 'ه', '', '', ''], $line);
        $line = (string) preg_replace('/\s+/u', ' ', trim($line));

        return mb_strtolower($line);
    }

    public static function parse(string $text): array
    {
        $chapters = [];
        $preChapterLines = [];
        $currentChapter = null;
        $currentLesson = null;

        $flushLesson = function () use (&$currentChapter, &$currentLesson) {
            if ($currentLesson !== null && $currentChapter !== null) {
                $currentChapter['lessons'][] = $currentLesson;
                $currentLesson = null;
            }
        };
        $flushChapter = function () use (&$chapters, &$currentChapter, &$currentLesson, $flushLesson) {
            $flushLesson();
            if ($currentChapter !== null) {
                $chapters[] = $currentChapter;
                $currentChapter = null;
            }
        };

        foreach (preg_split("/\r\n|\r|\n/u", $text) as $rawLine) {
            $line = trim((string) $rawLine);
            if ($line === '') {
                continue;
            }
            $norm = self::normalizeForMatch($line);

            if (self::isMetaLine($norm)) {
                continue;
            }

            $chapterHit = self::matchChapter($norm);
            if ($chapterHit !== null) {
                $flushChapter();
                [$pages, $cleanTitle] = self::extractPages($line);
                $currentChapter = [
                    'index' => count($chapters) + 1,
                    'number' => $chapterHit['number'],
                    'title' => $cleanTitle !== '' ? $cleanTitle : $line,
                    'pages' => $pages,
                    'lessons' => [],
                    'extra_tasks' => [],
                ];
                continue;
            }

            $lessonHit = self::matchLesson($norm);
            if ($lessonHit !== null) {
                if ($currentChapter === null) {
                    // A lesson with no chapter above it: orphan input, skip
                    // rather than inventing a phantom chapter.
                    continue;
                }
                $flushLesson();
                [$pages, $cleanTitle] = self::extractPages($line);
                $currentLesson = [
                    'index' => count($currentChapter['lessons']) + 1,
                    'number' => $lessonHit['number'],
                    'title' => $cleanTitle !== '' ? $cleanTitle : $line,
                    'pages' => $pages,
                    'topics' => [],
                ];
                continue;
            }

            if ($currentLesson !== null && $currentChapter !== null) {
                $currentLesson['topics'][] = $line;
            } elseif ($currentChapter !== null) {
                $currentChapter['extra_tasks'][] = $line;
            } else {
                $preChapterLines[] = $line;
            }
        }
        $flushChapter();

        return [
            'book_title' => self::detectBookTitle($preChapterLines),
            'chapters' => $chapters,
        ];
    }

    /**
     * Numbered "فصل ۱: ..." (or بخش/چاپبر + number), plus unnumbered
     * "بخش ..." (appendices such as exams + references → فصل ۱۶).
     */
    private static function matchChapter(string $norm): ?array
    {
        if (preg_match('/^(فصل|بخش|چاپبر|چاپتر)\s+(\d+)\s*[:：\-–—.]?\s*(.*)$/u', $norm, $m)) {
            return ['number' => (int) $m[2]];
        }
        if (preg_match('/^بخش\s+(.+)$/u', $norm, $m) && trim($m[1]) !== '') {
            return ['number' => null];
        }

        return null;
    }

    private static function matchLesson(string $norm): ?array
    {
        if (preg_match('/^(درسنامه|درس|گفتار|واحد)\s+(\d+)\s*[:：\-–—.]?\s*(.*)$/u', $norm, $m)) {
            return ['number' => (int) $m[2]];
        }

        return null;
    }

    private static function isMetaLine(string $norm): bool
    {
        foreach (self::META_NEEDLES as $needle) {
            if (mb_strpos($norm, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pull "(۴۰ صفحه)" / "(حدود ۱۲ صفحه)" out of a header line.
     * Returns [pages|null, cleaned title]. Ranges ("صفحات ۳۶۳ تا ۳۶۴")
     * are left inside the title — they don't fit the 0-99 weight metric.
     */
    private static function extractPages(string $line): array
    {
        $norm = self::normalizeForMatch($line);
        $pages = null;

        if (preg_match('/\(\s*(?:حدود\s+)?(\d+)\s*صفحه\s*\)/u', $norm, $m)) {
            $pages = (int) $m[1];
            // Remove the same parenthetical from the ORIGINAL title.
            $line = (string) preg_replace('/\(\s*(?:حدود\s+)?\d+\s*صفحه\s*\)/u', '', $line);
        } elseif (preg_match('/(\d+)\s*صفحه\s*$/u', $norm, $m)) {
            $pages = (int) $m[1];
            $line = (string) preg_replace('/\d+\s*صفحه\s*$/u', '', $line);
        }

        $clean = trim((string) preg_replace('/\s+/u', ' ', $line));
        $clean = rtrim($clean, ':：-–—');

        return [$pages, trim($clean)];
    }

    /**
     * Book title = last short, non-meta line before the first chapter
     * (e.g. "روانشناسی رشد :"). Falls back to a placeholder, never null.
     */
    private static function detectBookTitle(array $preChapterLines): string
    {
        for ($i = count($preChapterLines) - 1; $i >= 0; $i--) {
            $candidate = trim($preChapterLines[$i]);
            if ($candidate === '' || mb_strlen($candidate) > 100) {
                continue;
            }
            $candidate = rtrim($candidate, ':：');
            if ($candidate !== '') {
                return trim($candidate);
            }
        }

        return 'کتاب بدون عنوان';
    }

    // ── Small counters for the chunker / orchestrator ──

    public static function countTasks(array $chapter): int
    {
        return count($chapter['lessons']) + count($chapter['extra_tasks']);
    }

    public static function countSubtasks(array $chapter): int
    {
        $n = 0;
        foreach ($chapter['lessons'] as $lesson) {
            $n += count($lesson['topics']);
        }

        return $n;
    }
}
