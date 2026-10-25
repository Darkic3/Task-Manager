<?php

namespace App\Services\Study;

/**
 * StudyPlanChunker — Phase 2 of the automatic book-chain build.
 *
 * Packs a parsed outline (StudyOutlineParser::parse) into sequential chunks
 * that each fit the server-side plan caps (AiPlan::MAX_*), so the agent can
 * propose them one by one instead of getting the whole book rejected with
 * "Too many sub-projects / tasks".
 *
 * Packing rules (locked by product decision):
 * - A chapter is NEVER split across chunks — unless the single chapter alone
 *   exceeds the caps, in which case its lessons are split (with `continued`).
 * - Model B: every chunk gets its own confirmation card. Chunk 1 builds the
 *   book project; chunks 2+ attach via the real book project id (filled by
 *   the orchestrator from created_ids — chunker marks them `attach: true`).
 * - Page counts: title stays CLEAN, pages go to line 1 of `description`
 *   ("📄 N صفحه") AND to `weight` (0-99, mirroring AiToolService validation).
 *
 * Chunk shape (aligned with the plan_propose server shape):
 *   [
 *     'chunk_index'  => 1,
 *     'total_chunks' => 6,
 *     'title'        => 'روانشناسی رشد — بسته ۱ از ۶ (فصل ۱ تا ۳)',
 *     'book_title'   => 'روانشناسی رشد',
 *     'attach'       => false,   // true from chunk 2 on (needs book project id)
 *     'subprojects'  => [
 *       ['title'=>..., 'pages'=>40|null, 'description'=>"📄 ۴۰ صفحه"|null,
 *        'tasks'=>[
 *          ['title'=>..., 'pages'=>10|null, 'description'=>..., 'weight'=>10|null,
 *           'subtasks'=>[['title'=>...], ...]],
 *        ]],
 *     ],
 *     'totals' => ['subprojects'=>3, 'tasks'=>7, 'subtasks'=>27, 'pages'=>134],
 *   ]
 *
 * Pure function, no DB.
 */
class StudyPlanChunker
{
    /** Hard server caps live in AiPlan; chunker stays safely below them. */
    public const MAX_SUBPROJECTS_PER_CHUNK = 3;

    public const MAX_TASKS_PER_CHUNK = 25;

    public const MAX_SUBTASKS_PER_CHUNK = 60;

    /** Mirrors AiToolService title/description limits. */
    public const TITLE_MAX = 120;

    public const DESCRIPTION_MAX = 500;

    public static function chunk(array $outline): array
    {
        $bookTitle = (string) ($outline['book_title'] ?? 'کتاب بدون عنوان');
        $chapters = array_values((array) ($outline['chapters'] ?? []));

        $groups = self::packChapters($chapters);
        $total = count($groups);
        $chunks = [];

        foreach ($groups as $i => $group) {
            $subprojects = [];
            $taskCount = 0;
            $subCount = 0;
            $pageSum = 0;

            foreach ($group as $entry) {
                $node = self::buildSubproject($entry['chapter'], $entry['lesson_slice'] ?? null);
                $subprojects[] = $node;
                $taskCount += count($node['tasks']);
                foreach ($node['tasks'] as $task) {
                    $subCount += count($task['subtasks']);
                    $pageSum += (float) ($task['weight'] ?? 0);
                }
            }

            $idx = $i + 1;
            $chunks[] = [
                'chunk_index' => $idx,
                'total_chunks' => $total,
                'title' => self::chunkTitle($bookTitle, $idx, $total, $group),
                'book_title' => $bookTitle,
                'attach' => $idx > 1,
                'subprojects' => $subprojects,
                'totals' => [
                    'subprojects' => count($subprojects),
                    'tasks' => $taskCount,
                    'subtasks' => $subCount,
                    'pages' => $pageSum > 0 ? round($pageSum, 1) : 0,
                ],
            ];
        }

        return $chunks;
    }

    /**
     * Greedy pack: fill each chunk with whole chapters until the next one
     * would break a cap. Oversized single chapters are split by lesson.
     * Each group entry: ['chapter'=>..., 'lesson_slice'=>null|[from,count]].
     */
    private static function packChapters(array $chapters): array
    {
        $groups = [];
        $current = [];
        $curTasks = 0;
        $curSubs = 0;

        $flush = function () use (&$groups, &$current, &$curTasks, &$curSubs) {
            if ($current !== []) {
                $groups[] = $current;
                $current = [];
                $curTasks = 0;
                $curSubs = 0;
            }
        };

        foreach ($chapters as $chapter) {
            $chTasks = StudyOutlineParser::countTasks($chapter);
            $chSubs = StudyOutlineParser::countSubtasks($chapter);

            // Single chapter bigger than a whole chunk → split by lesson.
            if ($chTasks > self::MAX_TASKS_PER_CHUNK || $chSubs > self::MAX_SUBTASKS_PER_CHUNK) {
                $flush();
                foreach (self::splitChapterByLessons($chapter) as $slice) {
                    $groups[] = [$slice];
                }
                continue;
            }

            $wouldBreak = count($current) >= self::MAX_SUBPROJECTS_PER_CHUNK
                || $curTasks + $chTasks > self::MAX_TASKS_PER_CHUNK
                || $curSubs + $chSubs > self::MAX_SUBTASKS_PER_CHUNK;

            if ($wouldBreak) {
                $flush();
            }

            $current[] = ['chapter' => $chapter, 'lesson_slice' => null];
            $curTasks += $chTasks;
            $curSubs += $chSubs;
        }
        $flush();

        return $groups;
    }

    /**
     * Fallback for a giant chapter: pack its lessons greedily into slices.
     * Extra (lesson-less) tasks ride with the first slice.
     */
    private static function splitChapterByLessons(array $chapter): array
    {
        $slices = [];
        $current = [];
        $curTasks = 0;
        $curSubs = 0;

        foreach (array_values($chapter['lessons']) as $lesson) {
            $t = 1;
            $s = count($lesson['topics']);
            if ($current !== [] && ($curTasks + $t > self::MAX_TASKS_PER_CHUNK || $curSubs + $s > self::MAX_SUBTASKS_PER_CHUNK)) {
                $slices[] = $current;
                $current = [];
                $curTasks = 0;
                $curSubs = 0;
            }
            $current[] = $lesson;
            $curTasks += $t;
            $curSubs += $s;
        }

        if ($current !== [] || $slices === []) {
            $slices[] = $current;
        }

        $out = [];
        foreach ($slices as $k => $lessons) {
            $part = $chapter;
            $part['lessons'] = $lessons;
            // Lesson-less tasks travel with the first slice only.
            $part['extra_tasks'] = $k === 0 ? $chapter['extra_tasks'] : [];
            $out[] = ['chapter' => $part, 'lesson_slice' => ['part' => $k + 1, 'of' => count($slices)]];
        }

        return $out;
    }

    private static function buildSubproject(array $chapter, ?array $slice): array
    {
        $tasks = [];
        foreach ($chapter['lessons'] as $lesson) {
            $tasks[] = self::buildTask($lesson['title'], $lesson['pages'], $lesson['topics']);
        }
        foreach ($chapter['extra_tasks'] as $extra) {
            $tasks[] = self::buildTask($extra, null, []);
        }

        $title = (string) $chapter['title'];
        if ($slice !== null && ($slice['of'] ?? 1) > 1) {
            $title .= ' (ادامه '.$slice['part'].' از '.$slice['of'].')';
        }

        return [
            'title' => self::shortTitle($title),
            'pages' => $chapter['pages'] ?? null,
            'description' => self::pageDescription($chapter['pages'] ?? null),
            'tasks' => $tasks,
        ];
    }

    private static function buildTask(string $title, ?int $pages, array $topics): array
    {
        $subtasks = [];
        foreach ($topics as $topic) {
            $topic = trim((string) $topic);
            if ($topic === '') {
                continue;
            }
            $subtasks[] = ['title' => self::shortTitle($topic)];
        }

        return [
            'title' => self::shortTitle($title),
            'pages' => $pages,
            'description' => self::pageDescription($pages),
            'weight' => is_int($pages) && $pages >= 0 && $pages <= 99 ? $pages : null,
            'subtasks' => $subtasks,
        ];
    }

    /** "📄 N صفحه" — pages live here AND in weight, NEVER in the title. */
    public static function pageDescription(?int $pages): ?string
    {
        if (! is_int($pages) || $pages <= 0) {
            return null;
        }

        return mb_substr('📄 '.$pages.' صفحه', 0, self::DESCRIPTION_MAX);
    }

    /** Mirror of the server-side 120-char title rule (117 + …). */
    public static function shortTitle(string $title): string
    {
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));
        if (mb_strlen($title) > self::TITLE_MAX) {
            return mb_substr($title, 0, self::TITLE_MAX - 3).'…';
        }

        return $title;
    }

    private static function chunkTitle(string $book, int $idx, int $total, array $group): string
    {
        // The range label uses the REAL chapter numbers (not the running
        // position), so outlines starting above 1 stay honest.
        $firstEntry = $group[0]['chapter'];
        $lastEntry = $group[count($group) - 1]['chapter'];
        $first = $firstEntry['number'] ?? $firstEntry['index'] ?? $idx;
        $last = $lastEntry['number'] ?? $lastEntry['index'] ?? $idx;
        $range = $first === $last ? "فصل {$first}" : "فصل {$first} تا {$last}";

        return self::shortTitle("{$book} — بسته {$idx} از {$total} ({$range})");
    }

    /**
     * Guard used by tests and (later) the orchestrator: every chunk must fit
     * the server caps. Returns null when OK, otherwise a human-readable error.
     */
    public static function checkChunk(array $chunk): ?string
    {
        $t = $chunk['totals'] ?? [];
        if (($t['subprojects'] ?? 0) > \App\Models\AiPlan::MAX_SUBPROJECTS) {
            return 'Too many sub-projects in chunk '.$chunk['chunk_index'];
        }
        if (($t['tasks'] ?? 0) > \App\Models\AiPlan::MAX_TASKS) {
            return 'Too many tasks in chunk '.$chunk['chunk_index'];
        }
        if (($t['subtasks'] ?? 0) > \App\Models\AiPlan::MAX_SUBTASKS) {
            return 'Too many subtasks in chunk '.$chunk['chunk_index'];
        }
        foreach ($chunk['subprojects'] ?? [] as $sub) {
            foreach ($sub['tasks'] ?? [] as $task) {
                if (mb_strlen($task['title'] ?? '') > self::TITLE_MAX) {
                    return 'Task title too long in chunk '.$chunk['chunk_index'];
                }
                // A single-number page marker must never survive in a title
                // (page RANGES like "(صفحات ۳۶۳ تا ۳۶۴)" stay verbatim).
                if (preg_match('/\(\s*(?:حدود\s+)?\d+\s*صفحه\s*\)/u', $task['title'] ?? '')
                    || preg_match('/\d+\s*صفحه\s*$/u', $task['title'] ?? '')) {
                    return 'Page count leaked into a task title in chunk '.$chunk['chunk_index'];
                }
            }
            if (preg_match('/\(\s*(?:حدود\s+)?\d+\s*صفحه\s*\)/u', $sub['title'] ?? '')) {
                // Chapter headers never carry page counts (parser strips them).
                return 'Page count leaked into a sub-project title in chunk '.$chunk['chunk_index'];
            }
        }

        return null;
    }
}
