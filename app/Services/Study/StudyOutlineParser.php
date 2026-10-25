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
 * - Explicit chapter and lesson headers are recognized before any metadata
 *   handling, so ordinary words in titles can never hide structure.
 * - A trailing "نکات:" block is excluded from book content.
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
        $parseErrors = [];
        $currentChapter = null;
        $currentLesson = null;
        $skipDamagedBlock = false;
        $inMetaBlock = false;
        $explicitTitle = self::extractExplicitBookTitle($text);

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

        foreach (preg_split("/\r\n|\r|\n/u", $text) as $lineIndex => $rawLine) {
            $line = trim((string) $rawLine);
            if ($line === '') {
                continue;
            }
            $norm = self::normalizeForMatch($line);

            $chapterHit = self::matchChapter($norm);
            if ($chapterHit !== null) {
                $flushChapter();
                $inMetaBlock = false;
                $skipDamagedBlock = false;
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

            // Do not let unsupported explicit headings turn into topics in
            // the preceding chapter. Keep a diagnostic for master validation.
            if (self::looksLikeChapterHeader($norm)) {
                $flushChapter();
                $currentChapter = null;
                $currentLesson = null;
                $skipDamagedBlock = true;
                $parseErrors[] = 'سرعنوان فصل در خط '.($lineIndex + 1).' شماره‌گذاری پشتیبانی‌شده ندارد.';
                continue;
            }

            $lessonHit = self::matchLesson($norm);
            if ($lessonHit !== null) {
                if ($currentChapter === null) {
                    $skipDamagedBlock = true;
                    $parseErrors[] = 'درسنامهٔ خط '.($lineIndex + 1).' پیش از یک فصل قرار دارد.';
                    continue;
                }
                $flushLesson();
                $skipDamagedBlock = false;
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

            if (self::looksLikeLessonHeader($norm)) {
                $flushLesson();
                $skipDamagedBlock = true;
                $parseErrors[] = 'سرعنوان درسنامه در خط '.($lineIndex + 1).' شماره‌گذاری پشتیبانی‌شده ندارد.';
                continue;
            }

            if ($inMetaBlock || $skipDamagedBlock) {
                continue;
            }

            if (self::isMetaHeading($norm)) {
                $flushLesson();
                $inMetaBlock = true;
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
            'book_title' => $explicitTitle ?? self::detectBookTitle($preChapterLines),
            'chapters' => $chapters,
            'parse_errors' => $parseErrors,
        ];
    }

    /** Extract a title attached to common request phrases before line filtering. */
    private static function extractExplicitBookTitle(string $text): ?string
    {
        $text = (string) (preg_split('/^(?:فصل|بخش|چاپبر|چاپتر)\s/um', $text, 2)[0] ?? $text);
        // A bare structure keyword ("فصل‌ها را ...", "درسنامه ۱ ...") also
        // ends the title; the lookahead after it keeps words like
        // "فصل‌بندی" (keyword glued to more letters) inside the title.
        $end = '(?=\s+(?:و\s+(?:(?:این|تمام)\s+)?(?:فصل(?:‌ها|ها)?|بخش(?:‌ها|ها)?|زیرفصل(?:‌ها|ها)?)|(?:فصل|بخش|زیرفصل|درسنامه|درس|گفتار|واحد)(?:‌ها|ها)?(?=\s|$|[،؛:])|(?:فصل|بخش)\s+[0-9۰-۹٠-٩]|را\s+(?:به|اضافه)|اضافه\s+کن)|[،؛:]|$)';
        $patterns = [
            '/(?:^|[\s،])(?:به|با)\s+عنوان\s+(?<title>.+?)'.$end.'/u',
            '/(?:^|[\s،])به\s+(?:اسم|نام)\s+(?<title>.+?)'.$end.'/u',
            '/(?:^|[\s،])(?:اسم|نام|عنوان)\s+کتاب\s*[:：]?\s*(?<title>.+?)'.$end.'/u',
            '/(?:^|[\s،])کتاب\s*[:：]\s*(?<title>.+?)'.$end.'/u',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $text, $match)) {
                continue;
            }
            $candidate = self::cleanTitleCandidate($match['title'] ?? '');
            if (self::isPlausibleTitle($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Unicode-safe trim of wrapping punctuation. trim() with a multibyte
     * mask is byte-based and would eat the first bytes of Persian letters
     * (e.g. D8 from ،/؛), corrupting the title into invalid UTF-8.
     */
    private static function cleanTitleCandidate(string $title): string
    {
        $title = trim($title);
        // Hyphen first so it is literal, never a range inside the class.
        $title = (string) preg_replace('/^[\s:：،؛«»"\'“”‘’\-–—]+/u', '', $title);
        $title = (string) preg_replace('/[\s:：،؛«»"\'“”‘’\-–—]+$/u', '', $title);
        $title = (string) preg_replace('/\s+/u', ' ', $title);

        return trim($title);
    }

    private static function isPlausibleTitle(string $title): bool
    {
        if ($title === '' || mb_strlen($title) > 120) {
            return false;
        }

        $norm = self::normalizeForMatch($title);

        if (preg_match('/(?:می\s*خواهم|می\s*خوام|بساز(?:ی|ید)?|درست\s+کن|اضافه\s+کن|بهش|این\s+فصل|فصل\s+ها|بخش\s+ها|زیربخش|موضوعاتش|پروژه)/u', $norm)) {
            return false;
        }
        // A request description ("یک پروژه با تمام ...") is not a title.
        if (preg_match('/(?:^|\s)(?:یک|این|آن|چند|چندین|همه|تمام)(?:\s|$)/u', $norm)) {
            return false;
        }

        return true;
    }

    /**
     * Unsupported explicit headings (e.g. "فصل چهارم: ...") must be reported
     * instead of silently merging into the previous chapter. A colon plus a
     * whole-word keyword is required so ordinary topics such as
     * "گفتار خودمحورانه" are never mistaken for headings.
     */
    private static function looksLikeChapterHeader(string $norm): bool
    {
        return (bool) preg_match('/^(?:فصل|بخش|چاپبر|چاپتر)(?![\p{L}]).*[:：]/u', $norm);
    }

    private static function looksLikeLessonHeader(string $norm): bool
    {
        return (bool) preg_match('/^(?:درسنامه|درس|گفتار|واحد)(?![\p{L}]).*[:：]/u', $norm);
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

    private static function isMetaHeading(string $norm): bool
    {
        return (bool) preg_match('/^(?:نکات|نکته|دستورالعمل|راهنما)\s*[:：]?$/u', $norm);
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
     * Fallback title = last short, non-instruction line before the first
     * chapter. Inline request titles are extracted separately.
     */
    private static function detectBookTitle(array $preChapterLines): string
    {
        for ($i = count($preChapterLines) - 1; $i >= 0; $i--) {
            $candidate = self::cleanTitleCandidate((string) $preChapterLines[$i]);
            if ($candidate === '' || mb_strlen($candidate) > 100) {
                continue;
            }
            if (! self::looksLikeInstructionLine(self::normalizeForMatch($candidate))
                && self::isPlausibleTitle($candidate)) {
                return trim($candidate);
            }
        }

        return 'کتاب بدون عنوان';
    }

    private static function looksLikeInstructionLine(string $norm): bool
    {
        $hasRequestPhrase = (bool) preg_match('/^(?:من\s+)?(?:می\s*خواهم|می\s*خوام|لطفا|لطفاً)/u', $norm);
        $hasAction = (bool) preg_match('/(?:بساز|درست\s+کن|اضافه\s+کن|تعریف\s+کن|ذکر\s+شود|ذکر\s+بشه|برداشتن)/u', $norm);

        return $hasRequestPhrase && $hasAction;
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
