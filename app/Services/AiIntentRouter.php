<?php

namespace App\Services;

/**
 * Intent Router for Lina (AI assistant).
 *
 * Runs BEFORE the heavy workspace context is built and BEFORE tool
 * definitions are attached, so a casual/general question never pays for
 * the full workspace dump or the ~22 function schemas.
 *
 * Design notes (MVP):
 * - Pure rule-based classifier (Persian + English cues). Deterministic,
 *   zero DB queries, zero extra LLM calls. A future ML reranker can sit
 *   behind the same `route()` signature when traffic justifies it.
 * - Advisory ONLY: the output selects context budgets and tool subsets.
 *   It is NEVER an authorization — ToolPipeline validation, ownership
 *   checks and user confirmation still gate every single mutation.
 * - Fail-open: on `medium`/`low` confidence nothing is narrowed (full
 *   context + full toolset, i.e. today's behavior). Only `high`
 *   confidence narrows anything.
 *
 * Output shape:
 *   ['intent' => ..., 'confidence' => 'high|medium|low',
 *    'entities' => ['objects'=>[...], 'date_mentioned'=>bool,
 *                   'list_detected'=>bool, 'question'=>bool],
 *    'requires_clarification' => bool, 'clarification_question' => ?string,
 *    'context_profile' => 'minimal|mode_default',
 *    'tool_filter' => null|array]   // null = keep full toolset
 */
final class AiIntentRouter
{
    public const INTENT_GENERAL = 'general_chat';
    public const INTENT_QUERY = 'workspace_query';
    public const INTENT_MUTATION = 'task_mutation';
    public const INTENT_PLAN = 'plan_build';
    public const INTENT_WORKOUT = 'workout_plan';
    public const INTENT_REPORT = 'report';
    public const INTENT_AMBIGUOUS = 'ambiguous';

    public const INTENTS = [
        self::INTENT_GENERAL,
        self::INTENT_QUERY,
        self::INTENT_MUTATION,
        self::INTENT_PLAN,
        self::INTENT_WORKOUT,
        self::INTENT_REPORT,
        self::INTENT_AMBIGUOUS,
    ];

    public const CONF_HIGH = 'high';
    public const CONF_MEDIUM = 'medium';
    public const CONF_LOW = 'low';

    /* ── Public API ─────────────────────────────────────────── */

    public static function route(string $message): array
    {
        $norm = self::normalize($message);
        $raw = mb_strtolower(trim($message));

        if ($raw === '') {
            return self::result(self::INTENT_AMBIGUOUS, self::CONF_LOW, $message);
        }

        $entities = self::entities($norm, $message);

        // 1) Workout plans first: "برنامه" overlaps with plan_build, so the
        // training-specific markers must win before generic planning cues.
        if ($w = self::matchWorkout($norm, $entities)) {
            return self::result(self::INTENT_WORKOUT, $w, $message, $entities);
        }

        // 2) Multi-project planning (incl. plan revisions).
        if ($p = self::matchPlan($norm, $entities)) {
            return self::result(self::INTENT_PLAN, $p, $message, $entities);
        }

        // 3) Explicit report requests ("گزارش بده") — before mutation so a
        // bare report request isn't mistaken for "ثبت گزارش".
        if ($r = self::matchReport($norm)) {
            return self::result(self::INTENT_REPORT, $r, $message, $entities);
        }

        // 4) Single-item mutations (verb + workspace object).
        if ($m = self::matchMutation($norm, $entities)) {
            // Mixed "report + do something" → the broader mutation wins.
            return self::result(self::INTENT_MUTATION, $m, $message, $entities);
        }

        // 5) Read-only workspace questions.
        if ($q = self::matchQuery($norm, $entities)) {
            return self::result(self::INTENT_QUERY, $q, $message, $entities);
        }

        // 6) Casual / general-knowledge / coding help.
        if ($g = self::matchGeneral($norm, $raw, $entities)) {
            return self::result(self::INTENT_GENERAL, $g, $message, $entities);
        }

        return self::result(self::INTENT_AMBIGUOUS, self::CONF_LOW, $message, $entities);
    }

    /**
     * Effective context-engine profile for (UI mode, route).
     * Only general_chat narrows to 'minimal'; everything else keeps the
     * mode default, i.e. today's behavior.
     */
    public static function profileFor(string $mode, array $route): string
    {
        if (($route['context_profile'] ?? null) === 'minimal') {
            return 'minimal';
        }

        return $mode === 'chat' ? 'chat' : 'agent';
    }

    /**
     * Tool definitions for a route.
     * - high confidence → entity-aware allowlist from ToolCapabilityRegistry
     *   (multi-category union for combined requests).
     * - ambiguous (any confidence) → READ-only safe fallback + clarification.
     * - anything else below high confidence → null (full set, fail-open).
     * An empty array means "no tools needed" (confident smalltalk).
     */
    public static function toolsFor(array $route, AiToolService $svc): ?array
    {
        if (($route['intent'] ?? null) === self::INTENT_AMBIGUOUS) {
            return $svc->definitions(\App\Services\AiTooling\ToolCapabilityRegistry::SAFE_FALLBACK);
        }
        if (($route['confidence'] ?? null) !== self::CONF_HIGH) {
            return null;
        }
        $filter = $route['tool_filter'] ?? null;
        if ($filter === null) {
            return null;
        }

        return $svc->definitions($filter);
    }

    /** Short advisory hint injected into the system prompt (never a permit). */
    public static function hintFor(array $route): ?string
    {
        return match ($route['intent'] ?? null) {
            self::INTENT_AMBIGUOUS => 'The user request is unclear — ask ONE brief clarifying question before acting, and do not call any tools until they clarify.',
            self::INTENT_GENERAL => 'Casual or general question — answer directly from knowledge. No tools or workspace data needed.',
            default => null,
        };
    }

    /* ── Result builder ─────────────────────────────────────── */

    private static function result(string $intent, string $confidence, string $message, array $entities = []): array
    {
        $entities += ['objects' => [], 'date_mentioned' => false, 'list_detected' => false, 'question' => false];
        $needsClarify = $intent === self::INTENT_AMBIGUOUS;

        return [
            'intent' => $intent,
            'confidence' => $confidence,
            'entities' => $entities,
            'requires_clarification' => $needsClarify,
            'clarification_question' => $needsClarify ? self::clarificationQuestion() : null,
            'context_profile' => $intent === self::INTENT_GENERAL ? 'minimal' : 'mode_default',
            'tool_filter' => self::toolFilter($intent, $entities),
        ];
    }

    /** Tool subset per intent (applied on high confidence only; entity-aware for mutations). */
    private static function toolFilter(string $intent, array $entities = []): ?array
    {
        if ($intent === self::INTENT_AMBIGUOUS) {
            return \App\Services\AiTooling\ToolCapabilityRegistry::SAFE_FALLBACK;
        }

        return match ($intent) {
            self::INTENT_GENERAL => [],
            self::INTENT_QUERY => ['report_generate'],
            self::INTENT_REPORT => ['report_generate'],
            self::INTENT_MUTATION => \App\Services\AiTooling\ToolCapabilityRegistry::resolveForRoute(
                ['intent' => $intent, 'entities' => $entities]
            ),
            self::INTENT_WORKOUT => AiToolService::TOOL_GROUPS['workout'],
            // plan_build keeps the FULL set (fail-open): plans embed
            // projects/tasks/reminders/notes/routines/members.
            default => null,
        };
    }

    private static function clarificationQuestion(): string
    {
        return app()->getLocale() === 'fa'
            ? 'دقیقاً می‌خوای چیکار کنم؟ (مثلاً ساخت تسک، گزارش، برنامه‌ریزی یا یه سؤال عمومی)'
            : 'What exactly would you like me to do? (e.g. create a task, a report, planning, or a general question)';
    }

    /* ── Normalization + entities ───────────────────────────── */

    /** Lowercase, Arabic→Persian chars, strip ZWNJ/tatweel, collapse space. */
    public static function normalize(string $message): string
    {
        $m = mb_strtolower($message);
        $m = str_replace(['ي', 'ك', 'ة', 'ـ'], ['ی', 'ک', 'ه', ''], $m);
        $m = str_replace(["\u{200C}", "\u{200D}"], '', $m); // ZWNJ/ZWJ
        // Punctuation → space (so "سلام!" matches "سلام"), but keep the
        // training-notation chars (×x*) and sentence marks used by matchers.
        $m = (string) preg_replace('/[^\p{L}\p{N}\s×x*?.-]+/u', ' ', $m);
        $m = (string) preg_replace('/\s+/u', ' ', $m);

        return trim($m);
    }

    private static function entities(string $norm, string $original): array
    {
        $objects = [];
        foreach (self::objectCues() as $object => $needles) {
            if (self::containsAny($norm, $needles)) {
                $objects[] = $object;
            }
        }

        return [
            'objects' => array_values(array_unique($objects)),
            'date_mentioned' => self::containsAny($norm, self::dateCues()),
            'list_detected' => (bool) preg_match('/^\s*(\d+[.)]|[-*•])\s+.+/mu', $original)
                && (bool) preg_match_all('/^\s*(\d+[.)]|[-*•])\s+.+/mu', $original) >= 2,
            'question' => str_contains($original, '؟') || str_ends_with(rtrim($original), '?'),
        ];
    }

    /** Canonical workspace object → cue words (normalized, ZWNJ-free). */
    private static function objectCues(): array
    {
        return [
            'task' => ['تسک', 'تسکها', 'وظیفه', 'وظایف', 'task'],
            'reminder' => ['یادآور', 'یادآوری', 'یادم', 'ریمایندر', 'reminder', 'remind', 'آلارم', 'alarm'],
            // "گزارش" doubles as a log/note artifact ("گزارش ثبت کن" is a
            // mutation); pure report requests are claimed earlier by
            // matchReport, so this never steals them.
            'note' => ['یادداشت', 'نوت', 'note', 'جزوه', 'گزارش', 'report'],
            'routine' => ['روتین', 'routine', 'عادت', 'habit'],
            'project' => ['پروژه', 'project'],
            'checklist' => ['چکلیست', 'چک لیست', 'checklist'],
            'member' => ['عضو', 'همکار', 'دسترسی', 'دعوت', 'member', 'collaborator', 'invite'],
            'file' => ['فایل', 'file', 'عکس', 'سند', 'pdf', 'پیوست'],
            'workout' => ['تمرین', 'ورزش', 'باشگاه', 'عضله', 'حرکت', 'workout', 'training', 'جلسه تمرینی'],
        ];
    }

    private static function dateCues(): array
    {
        return ['امروز', 'فردا', 'پسفردا', 'امشب', 'دیروز', 'این هفته', 'شنبه', 'یکشنبه', 'دوشنبه', 'سهشنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'today', 'tomorrow', 'tonight', 'this week', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    }

    /* ── Matchers (ordered by precedence) ───────────────────── */

    private static function matchWorkout(string $norm, array $entities): ?string
    {
        $training = ['تمرین', 'ورزش', 'باشگاه', 'عضله', 'workout', 'training'];
        $hasTraining = self::containsAny($norm, $training);
        $markers = ['rir', 'amrap', 'ایزومتریک', 'سوپرست', 'دایره ای', 'circuit'];
        $hits = 0;
        foreach ($markers as $mk) {
            if (str_contains($norm, $mk)) {
                $hits++;
            }
        }
        $setsReps = (bool) preg_match('/\d+\s*(×|x|\*)\s*\d+/u', $norm); // 4×8, 3x12
        $dayN = (bool) preg_match('/(day\s*\d|روز\s*\d|week\s*\d|هفته\s*\d)/u', $norm);
        $explicitBuild = self::containsAny($norm, ['برنامه تمرینی', 'برنامه ورزشی', 'workout plan', 'training plan', 'پلن تمرینی', 'پلن ورزشی']);

        if ($explicitBuild || ($hasTraining && ($hits >= 1 || ($setsReps && $dayN)))) {
            return self::CONF_HIGH;
        }
        // English paste without the noun ("DAY 1 ... 4×8 RIR 2"): the full
        // marker trio is distinctive enough on its own.
        if ($hits >= 1 && $setsReps && $dayN) {
            return self::CONF_HIGH;
        }
        // Pasted training block: numbered list + training nouns.
        if ($entities['list_detected'] && $hasTraining) {
            return $setsReps || $dayN || $hits >= 1 ? self::CONF_HIGH : self::CONF_MEDIUM;
        }
        if ($dayN && ($setsReps || $hasTraining)) {
            return self::CONF_MEDIUM;
        }

        return null;
    }

    private static function matchPlan(string $norm, array $entities): ?string
    {
        // Plan revisions ("روی این پروژه این تغییرات رو اعمال کن").
        if (self::containsAny($norm, ['روی این', 'رو این']) && self::containsAny($norm, ['پروژه', 'پلن', 'plan', 'project'])
            && self::containsAny($norm, ['تغییر', 'اعمال', 'ویرایش', 'اصلاح', 'اضافه'])) {
            return self::CONF_HIGH;
        }
        $planNouns = ['پلن', 'plan', 'نقشه راه', 'roadmap', 'ساختار', 'استراتژی', 'strategy', 'معماری'];
        $hasPlanNoun = self::containsAny($norm, $planNouns);
        $hasPlanVerb = self::containsAny($norm, ['بساز', 'ب ساز', 'بسازید', 'ایجاد کن', 'برنامهریزی', 'طراحی کن', 'بشکن', 'تقسیم کن']);
        $multiProject = self::containsAny($norm, ['چند پروژه', 'چندپروژه', 'سه پروژه', 'دو پروژه', 'پروژه ها', 'projects']);
        $monthlyPlan = self::containsAny($norm, ['برنامه ماهانه', 'برنامه هفتگی', 'برنامه ماه', 'monthly plan', 'weekly plan']);

        if ($multiProject || $monthlyPlan) {
            return self::CONF_HIGH;
        }
        if ($hasPlanNoun && ($hasPlanVerb || $entities['list_detected'])) {
            return self::CONF_HIGH;
        }
        // Numbered task list + project name ≈ bulk build (model fallback path).
        if ($entities['list_detected'] && in_array('project', $entities['objects'], true)) {
            return self::CONF_MEDIUM;
        }
        if ($hasPlanNoun || ($hasPlanVerb && in_array('project', $entities['objects'], true))) {
            return self::CONF_MEDIUM;
        }

        return null;
    }

    private static function matchReport(string $norm): ?string
    {
        // Creation verbs win: "گزارش ثبت کن" is a mutation, not a report.
        if (self::containsAny($norm, ['ثبت کن', 'ثبت کنی', 'ایجاد کن', 'بساز'])) {
            return null;
        }
        $asks = ['بده', 'بگو', 'نشان', 'نمایش', 'نمایش بده', 'نمودار', 'show', 'tell me', 'give me', 'how am i doing', 'my progress'];
        $nouns = ['گزارش', 'آمار', 'report', 'خلاصه وضعیت', 'وضعیت کلی', 'پیشرفت کلی'];
        if (self::containsAny($norm, $nouns) && self::containsAny($norm, $asks)) {
            return self::CONF_HIGH;
        }
        if (self::containsAny($norm, ['خلاصه وضعیت', 'وضعیت کلی', 'how am i doing'])) {
            return self::CONF_HIGH;
        }
        if (self::containsAny($norm, ['پیشرفت']) && self::containsAny($norm, array_merge(['پروژه', 'کار', 'project'], $asks))) {
            return self::CONF_MEDIUM;
        }

        return null;
    }

    private static function matchMutation(string $norm, array $entities): ?string
    {
        if (empty($entities['objects'])) {
            // Bare verbs ("بساز", "حذف کن") with no object → too vague.
            return null;
        }
        // Code-writing is general knowledge, not a workspace mutation.
        if (self::looksLikeCode($norm) && count(array_intersect($entities['objects'], ['task', 'reminder', 'note', 'routine', 'project', 'checklist', 'member'])) === 0) {
            return null;
        }
        // Note: bare "بذار" is NOT a verb ("بذار ببینم" = let me see → a
        // query); only the explicit "یادآوری بذار" phrase counts.
        $verbs = ['بساز', 'بسازید', 'ایجاد', 'اضافه', 'ثبت', 'بزن', 'بنداز', 'بنویس', 'درست کن', 'تکمیل', 'کامل کن', 'انجام شد', 'انجام بده', 'انجامش بده', 'تمام شد', 'تیک', 'حذف', 'پاک کن', 'پاکش کن', 'delete', 'ویرایش', 'تغییر', 'اصلاح', 'آپدیت', 'update', 'بهروزرسانی', 'بروزرسانی', 'عقب بنداز', 'جابجا', 'یادآوری کن', 'یادم بنداز', 'یادآوری بذار', 'create', 'make', 'add', 'complete', 'done', 'remind'];
        if (self::containsAny($norm, $verbs)) {
            return self::CONF_HIGH;
        }
        // "یادم بنداز ساعت ۶" — reminder phrasing without a classic verb.
        if (in_array('reminder', $entities['objects'], true) && self::containsAny($norm, ['یادم', 'ساعت', 'اطلاع بده', 'خبرم کن'])) {
            return self::CONF_HIGH;
        }

        return null;
    }

    private static function matchQuery(string $norm, array $entities): ?string
    {
        if (empty($entities['objects']) && ! $entities['date_mentioned']) {
            return null;
        }
        $q = ['چه', 'چی', 'چیست', 'چیه', 'کدام', 'کدوم', 'نشان', 'نشون', 'بگو', 'بگین', 'ببینم', 'لیست', 'فهرست', 'چند', 'چندتا', 'کی', 'آیا', 'هست', 'مونده', 'باقیمونده', 'باقی مونده', 'دارم', 'داریم', 'وضعیت', 'show', 'list', 'what', 'which', 'how many', 'tell me', 'do i have'];
        if (self::containsAny($norm, $q) || $entities['question']) {
            return empty($entities['objects']) ? self::CONF_MEDIUM : self::CONF_HIGH;
        }
        // "تسک‌های امروز" — object + date, no verb: still a question.
        if (! empty($entities['objects']) && $entities['date_mentioned']) {
            return self::CONF_MEDIUM;
        }

        return null;
    }

    private static function matchGeneral(string $norm, string $raw, array $entities): ?string
    {
        // Word-boundary match only: short needles like "hi"/"هی" must not
        // fire inside unrelated words ("this"، "تهیه").
        $smalltalk = ['سلام', 'درود', 'هی', 'hello', 'hi', 'hey', 'صبح بخیر', 'ظهر بخیر', 'عصر بخیر', 'شب بخیر', 'خداحافظ', 'بای', 'bye', 'مرسی', 'ممنون', 'متشکرم', 'مچکرم', 'thanks', 'thank you', 'باشه', 'ok', 'خب', 'قربانت'];
        foreach ($smalltalk as $s) {
            if ($norm === $s || (bool) preg_match('/(^|\s)'.preg_quote($s, '/').'($|\s)/u', $norm)) {
                return self::CONF_HIGH;
            }
        }
        if (self::containsAny($norm, ['اسمت چیه', 'کی هستی', 'who are you', 'your name', 'سازندت', 'سازنده ات', 'what can you do', 'چیکار میتونی', 'چه کاری میتونی', 'راهنما', 'کمکم کن', 'help me'])) {
            return self::CONF_HIGH;
        }
        if ($entities['question'] && empty($entities['objects']) && ! $entities['date_mentioned']) {
            // Bare general question ("پایتخت فرانسه کجاست؟").
            return self::CONF_MEDIUM;
        }
        if (self::looksLikeCode($norm) && empty($entities['objects'])) {
            return self::CONF_MEDIUM;
        }
        // Follow-ups needing history, not workspace ("ادامه بده").
        if (self::containsAny($norm, ['ادامه بده', 'ادامه بدم', 'بیشتر بگو', 'بیشتر توضیح', 'continue', 'go on'])) {
            return self::CONF_MEDIUM;
        }

        return null;
    }

    private static function looksLikeCode(string $norm): bool
    {
        return self::containsAny($norm, [
            'کد', 'برنامه نویسی', 'پایتون', 'python', 'جاوااسکریپت', 'javascript', 'php', 'laravel',
            'فانکشن', 'function', 'تابع', 'کلاس', 'class', 'متد', 'method', 'آرایه', 'array',
            'sql', 'دیتابیس', 'گیت', 'git', 'داکر', 'docker', 'api', 'الگوریتم', 'regex',
            'دیباگ', 'debug', 'باگ', 'کامپایلر', 'سرور', 'کدنویسی',
        ]);
    }

    /* ── Small helpers ──────────────────────────────────────── */

    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if ($n !== '' && str_contains($haystack, $n)) {
                return true;
            }
        }

        return false;
    }
}
