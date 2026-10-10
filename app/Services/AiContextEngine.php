<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\File;
use App\Models\Note;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\Routine;
use App\Models\Task;
use Illuminate\Support\Facades\Cache;

/**
 * Context Engine for Lina (AI agent).
 *
 * Replaces the old "dump the whole workspace into every prompt" approach
 * (AiChatController::buildContext) with a budgeted, relevance-first builder.
 *
 * Sections (rendered in this canonical order):
 *   1. GLOBAL              — date/timezone/user info, ONLY when the message needs them
 *   2. RELEVANT WORKSPACE  — projects/tasks/reminders/routines matched by
 *                            project, status, date, title keywords, relationships
 *   3. NOTES / FILES       — NEVER by default; only on explicit cues or attachments
 *   4. EXPLICIT ATTACHMENTS— note_ids / file_ids passed by the caller (ownership-checked)
 *   5. SUMMARY             — counts of everything not shown (always kept, tiny)
 *   Conversation history is returned separately (server-side AiMessage, capped).
 *
 * Per-surface profiles (chat / agent / planning) live in PROFILES so each
 * caller gets a different budget/shape without forking the code.
 */
class AiContextEngine
{
    /** Default workspace budget (chars) when a profile does not override it. */
    public const WORKSPACE_BUDGET = 6000;

    /** Short-lived cache for the repeated base snapshot (seconds). */
    public const CACHE_TTL = 60;

    public const HISTORY_PER_MESSAGE_CHARS = 800;

    /**
     * Per-surface profiles. Budgets are in CHARACTERS (tokens ≈ chars/4,
     * same heuristic used elsewhere in the codebase).
     */
    public const PROFILES = [
        // Intent-routed profile for casual/general questions: NO workspace
        // queries at all (skip_workspace) — only a static note + history.
        'minimal' => [
            'workspace_budget' => 800,
            'history_limit' => 4,
            'history_chars' => 1500,
            'task_lines' => 0,
            'project_lines' => 0,
            'reminders' => 'never',
            'routines' => 'never',
            'notes' => 'never',
            'files' => 'never',
            'skip_workspace' => true,
        ],
        'chat' => [
            'workspace_budget' => 4000,
            'history_limit' => 8,
            'history_chars' => 3000,
            'task_lines' => 20,
            'project_lines' => 8,
            'reminders' => 'cue',   // 'cue' | 'always' | 'never'
            'routines' => 'cue',
            'notes' => 'cue',
            'files' => 'cue',
        ],
        'agent' => [
            'workspace_budget' => 6000,
            'history_limit' => 10,
            'history_chars' => 4000,
            'task_lines' => 30,
            'project_lines' => 12,
            'reminders' => 'cue',
            'routines' => 'cue',
            'notes' => 'cue',
            'files' => 'cue',
        ],
        'planning' => [
            'workspace_budget' => 6000,
            'history_limit' => 6,
            'history_chars' => 2000,
            'task_lines' => 30,
            'project_lines' => 15,
            'reminders' => 'always',
            'routines' => 'always',
            'notes' => 'cue',
            'files' => 'cue',
        ],
    ];

    /* ── Public API ─────────────────────────────────────────────── */

    /**
     * Build context for a user message.
     *
     * $options: conversation_id, exclude_message_id, note_ids[], file_ids[]
     * Returns ['text'=>string,'history'=>array,'meta'=>array].
     * All data is scoped to $user->id. Never throws for empty data.
     */
    public function build($user, string $message, string $profile = 'agent', array $options = []): array
    {
        $cfg = self::PROFILES[$profile] ?? self::PROFILES['agent'];
        $budget = (int) ($cfg['workspace_budget'] ?? self::WORKSPACE_BUDGET);
        $intent = $options['intent'] ?? null;

        // Intent-routed fast path: general questions skip every workspace
        // query (no snapshot, no relevance retrieval — zero workspace I/O).
        if (! empty($cfg['skip_workspace'])) {
            $history = $this->loadHistory($user->id, $options, (int) $cfg['history_limit'], (int) $cfg['history_chars']);
            $text = 'WORKSPACE: skipped (general question — no workspace data loaded).';

            return [
                'text' => $text,
                'history' => $history['turns'],
                'meta' => [
                    'profile' => $profile,
                    'intent' => $intent,
                    'workspace_chars' => mb_strlen($text),
                    'workspace_budget' => $budget,
                    'history_count' => count($history['turns']),
                    'history_chars' => $history['chars'],
                    'included' => [],
                    'dropped' => ['global', 'projects', 'tasks', 'reminders', 'routines', 'notes', 'files'],
                    'trimmed' => [],
                    'cache_hit' => false,
                    'project_match' => null,
                    'keyword_count' => 0,
                ],
            ];
        }

        $keywords = $this->keywords($message);
        $cues = $this->cues($message);

        [$snapshot, $cacheHit] = $this->snapshot($user);
        $project = $this->matchProject($snapshot['projects'], $message, $keywords);

        $tasks = $this->relevantTasks($user->id, $project, $keywords, $cues, (int) $cfg['task_lines']);
        $reminders = $this->relevantReminders($user->id, $keywords, $cues, $cfg['reminders']);
        $routines = $this->relevantRoutines($user->id, $cues, $cfg['routines']);
        $notes = $this->relevantNotes($user->id, $keywords, $cues, $cfg['notes']);
        $files = $this->relevantFiles($user->id, $keywords, $cues, $cfg['files']);
        $attachments = $this->loadAttachments($user->id, $options);
        $global = $this->globalLines($user, $cues);

        $history = $this->loadHistory($user->id, $options, (int) $cfg['history_limit'], (int) $cfg['history_chars']);

        // Assemble by priority, WHOLE LINES only: reserve the tiny summary first,
        // then fill higher-priority sections line-by-line. Low-priority lines are
        // dropped — important data is never cut mid-line, and the summary always
        // survives so the model knows what was omitted.
        $sections = [
            'global' => ['priority' => 0, 'lines' => $global],
            'attachments' => ['priority' => 5, 'lines' => $attachments],
            'projects' => ['priority' => 10, 'lines' => $this->projectLines($snapshot['projects'], $project, (int) $cfg['project_lines'])],
            'tasks' => ['priority' => 20, 'lines' => $this->taskLines($tasks)],
            'reminders' => ['priority' => 30, 'lines' => $this->reminderLines($reminders)],
            'routines' => ['priority' => 40, 'lines' => $this->routineLines($routines)],
            'notes' => ['priority' => 50, 'lines' => $this->noteLines($notes)],
            'files' => ['priority' => 60, 'lines' => $this->fileLines($files)],
        ];
        $summaryLines = $this->summaryLines($snapshot['counts'], count($tasks), $project);
        $summaryBlock = $this->renderSection('summary', $summaryLines);

        $kept = [];
        $used = mb_strlen($summaryBlock);
        $dropped = [];
        $trimmed = [];
        $order = collect($sections)->sortBy('priority')->keys()->all();
        foreach ($order as $key) {
            $lines = $sections[$key]['lines'];
            if (empty($lines)) {
                continue;
            }
            $headerLen = mb_strlen($this->sectionHeader($key, count($lines))."\n");
            $taken = [];
            $need = $used + $headerLen;
            foreach ($lines as $line) {
                $lineLen = mb_strlen($line) + 1; // + newline
                if ($need + $lineLen <= $budget) {
                    $taken[] = $line;
                    $need += $lineLen;
                } else {
                    break; // stop at whole-line boundary
                }
            }
            if (empty($taken)) {
                $dropped[] = $key;
                continue;
            }
            if (count($taken) < count($lines)) {
                $trimmed[] = $key;
            }
            // Re-render header with the kept line count.
            $kept[$key] = $this->renderSection($key, $taken);
            $used = $need;
        }
        $kept['summary'] = $summaryBlock;

        // Canonical render order.
        $renderOrder = ['global', 'projects', 'tasks', 'reminders', 'routines', 'notes', 'files', 'attachments', 'summary'];
        $parts = [];
        foreach ($renderOrder as $key) {
            if (isset($kept[$key])) {
                $parts[] = $kept[$key];
            }
        }
        $text = implode("\n\n", $parts);

        return [
            'text' => $text,
            'history' => $history['turns'],
            'meta' => [
                'profile' => $profile,
                'intent' => $intent,
                'workspace_chars' => mb_strlen($text),
                'workspace_budget' => $budget,
                'history_count' => count($history['turns']),
                'history_chars' => $history['chars'],
                'included' => array_keys($kept),
                'dropped' => $dropped,
                'trimmed' => $trimmed,
                'cache_hit' => $cacheHit,
                'project_match' => $project['name'] ?? null,
                'keyword_count' => count($keywords),
            ],
        ];
    }

    /** Forget the cached base snapshot for a user (tests + settings changes). */
    public static function flushUserCache(int $userId): void
    {
        Cache::forget(self::cacheKey($userId));
    }

    /** Extension point: per-user preferences (no preferences table exists yet). */
    public function userPreferences($user): array
    {
        return [];
    }

    /* ── Message analysis ───────────────────────────────────────── */

    /** Significant keywords from the message (stopwords removed, capped). */
    public function keywords(string $message): array
    {
        $words = preg_split('/[^\p{L}\p{N}\/]+/u', mb_strtolower($message)) ?: [];
        $out = [];
        foreach ($words as $w) {
            $w = trim($w);
            if (mb_strlen($w) < 3 || isset(self::stopwords()[$w])) {
                continue;
            }
            $out[$w] = true;
            if (count($out) >= 12) {
                break;
            }
        }

        return array_keys($out);
    }

    /** Intent cues driving which sections load. */
    public function cues(string $message): array
    {
        $m = mb_strtolower($message);
        $has = fn (array $needles) => collect($needles)->contains(fn ($n) => mb_strpos($m, $n) !== false);

        return [
            'date' => $has(['امروز', 'فردا', 'پس‌فردا', 'پس فردا', 'امشب', 'این هفته', 'دیروز', 'today', 'tomorrow', 'tonight', 'this week', 'yesterday', 'due', 'overdue', 'deadline', 'مهلت', 'سررسید', 'عقب افتاده', 'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
            'reminder' => $has(['یادآور', 'ریمایندر', 'یادآوری', 'reminder', 'remind', 'آلارم', 'alarm', 'سر ساعت']),
            'routine' => $has(['روتین', 'routine', 'عادت', 'habit', 'روزانه', 'هفتگی', 'daily', 'weekly', 'هر روز']),
            'note' => $has(['نوت', 'یادداشت', 'note', 'جزوه', 'دفترچه']),
            'file' => $has(['فایل', 'file', 'عکس', 'photo', 'picture', 'سند', 'document', 'pdf', 'پیوست', 'attachment']),
            'report' => $has(['گزارش', 'report', 'آمار', 'وضعیت', 'خلاصه وضعیت', 'how am i doing', 'پیشرفت کلی']),
            'self' => $has([' اسم من', 'my name', 'myself', 'خودم', 'منم ', 'درباره من', 'about me']),
        ];
    }

    private static function stopwords(): array
    {
        static $sw = null;
        if ($sw !== null) {
            return $sw;
        }
        $list = ['این', 'آن', 'یک', 'را', 'با', 'از', 'به', 'که', 'در', 'و', 'یا', 'برای', 'روی', 'هم', 'چه', 'چرا', 'چطور', 'چگونه', 'من', 'تو', 'او', 'ما', 'شما', 'خود', 'های', 'هایی', 'تر', 'ترین', 'است', 'هست', 'بود', 'شد', 'کن', 'بده', 'بکن', 'اضافه', 'تسک', 'پروژه', 'لطفا', 'لطفاً', 'میشه', 'میخوام', 'می‌خوام', 'ببین', 'کنی', 'کند', 'the', 'a', 'an', 'and', 'or', 'for', 'with', 'this', 'that', 'what', 'when', 'how', 'please', 'task', 'tasks', 'project', 'add', 'create', 'make', 'show', 'list', 'give', 'want', 'you', 'your', 'are'];
        $sw = array_fill_keys($list, true);

        return $sw;
    }

    /* ── Cached base snapshot ───────────────────────────────────── */

    private static function cacheKey(int $userId): string
    {
        return "ai_ctx_base:u{$userId}";
    }

    /**
     * Projects + cheap counts, cached 60s. Returns [snapshot, cacheHit].
     * Only aggregate queries — no row dumps.
     */
    private function snapshot($user): array
    {
        $key = self::cacheKey((int) $user->id);
        $hit = Cache::has($key);
        $snap = Cache::remember($key, self::CACHE_TTL, function () use ($user) {
            $uid = $user->id;
            $projects = Project::where('user_id', $uid)
                ->orderBy('id')
                ->get(['id', 'name', 'status'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'status' => $p->status])
                ->all();

            $taskAgg = Task::where('user_id', $uid)
                ->selectRaw('COUNT(*) as total, SUM(status != "completed") as open_count')
                ->first();

            return [
                'projects' => $projects,
                'counts' => [
                    'projects' => count($projects),
                    'tasks_total' => (int) ($taskAgg->total ?? 0),
                    'tasks_open' => (int) ($taskAgg->open_count ?? 0),
                    'reminders_pending' => Reminder::where('user_id', $uid)->where('is_completed', false)->count(),
                    'routines' => Routine::where('user_id', $uid)->count(),
                    'notes' => Note::where('user_id', $uid)->count(),
                    'files' => File::where('user_id', $uid)->count(),
                ],
            ];
        });

        return [$snap, $hit];
    }

    /* ── Relevance retrieval (all user-scoped, all limited) ────── */

    private function matchProject(array $projects, string $message, array $keywords): ?array
    {
        if (empty($projects) || trim($message) === '') {
            return null;
        }
        $msg = mb_strtolower($message);
        foreach ($projects as $p) {
            $name = mb_strtolower(trim((string) $p['name']));
            if ($name !== '' && (mb_strpos($msg, $name) !== false || mb_strpos($name, $msg) !== false)) {
                return $p;
            }
        }
        $best = null;
        $bestScore = 0;
        foreach ($projects as $p) {
            $score = 0;
            foreach (preg_split('/\s+/u', mb_strtolower((string) $p['name'])) ?: [] as $w) {
                $w = trim($w);
                if (mb_strlen($w) < 3) {
                    continue;
                }
                $stem = mb_substr($w, 0, 4);
                if (in_array($w, $keywords, true) || mb_strpos($msg, $w) !== false || mb_strpos($msg, $stem) !== false) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $p;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    private function openFirstOrder($query)
    {
        return $query->orderByRaw('CASE WHEN status = "completed" THEN 1 ELSE 0 END')
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderByDesc('id');
    }

    /**
     * Relevant tasks: matched project → keyword title hits → due/open (on date
     * cue) → recent open fallback. Deduped, capped. Eager project name.
     */
    private function relevantTasks(int $userId, ?array $project, array $keywords, array $cues, int $limit): array
    {
        $seen = [];
        $out = [];
        $take = function ($rows) use (&$seen, &$out, $limit) {
            foreach ($rows as $t) {
                if (isset($seen[$t->id])) {
                    continue;
                }
                $seen[$t->id] = true;
                $out[] = $t;
                if (count($out) >= $limit) {
                    break;
                }
            }
        };

        $cols = ['id', 'title', 'status', 'priority', 'due_date', 'project_id'];

        if ($project) {
            $take($this->openFirstOrder(
                Task::where('user_id', $userId)->where('project_id', $project['id'])
                    ->with('project:id,name')
            )->limit($limit)->get($cols));
        }

        $kw = array_slice($keywords, 0, 4);
        if (! empty($kw) && count($out) < $limit) {
            $q = Task::where('user_id', $userId)->with('project:id,name');
            $q->where(function ($w) use ($kw) {
                foreach ($kw as $k) {
                    $w->orWhere('title', 'like', "%{$k}%");
                }
            });
            if ($project) {
                $q->where('project_id', '!=', $project['id']);
            }
            $take($this->openFirstOrder($q)->limit($limit)->get($cols));
        }

        if ($cues['date'] && count($out) < $limit) {
            $take($this->openFirstOrder(
                Task::where('user_id', $userId)->where('status', '!=', 'completed')
                    ->whereNotNull('due_date')->with('project:id,name')
            )->limit(10)->get($cols));
        }

        if (empty($out)) {
            $take($this->openFirstOrder(
                Task::where('user_id', $userId)->where('status', '!=', 'completed')
                    ->with('project:id,name')
            )->limit(min(10, $limit))->get($cols));
        }

        return $out;
    }

    private function relevantReminders(int $userId, array $keywords, array $cues, string $mode): array
    {
        if ($mode === 'never') {
            return [];
        }
        if ($mode === 'cue' && ! ($cues['reminder'] || $cues['date'] || $cues['report'])) {
            return [];
        }

        return Reminder::where('user_id', $userId)
            ->where('is_completed', false)
            ->orderByRaw('date IS NULL, date ASC')
            ->orderByDesc('id')
            ->limit(12)
            ->get(['id', 'title', 'date', 'time', 'priority'])
            ->all();
    }

    private function relevantRoutines(int $userId, array $cues, string $mode): array
    {
        if ($mode === 'never') {
            return [];
        }
        if ($mode === 'cue' && ! ($cues['routine'] || $cues['report'])) {
            return [];
        }

        return Routine::where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'title', 'frequency'])
            ->all();
    }

    private function relevantNotes(int $userId, array $keywords, array $cues, string $mode): array
    {
        if ($mode === 'never') {
            return [];
        }
        if ($mode === 'cue' && ! ($cues['note'] || $cues['report'])) {
            return [];
        }
        $kw = array_slice($keywords, 0, 3);
        if (empty($kw)) {
            return [];
        }
        $q = Note::where('user_id', $userId);
        $q->where(function ($w) use ($kw) {
            foreach ($kw as $k) {
                $w->orWhere('title', 'like', "%{$k}%");
            }
        });

        return $q->orderByDesc('id')->limit(3)->get(['id', 'title', 'content'])->all();
    }

    private function relevantFiles(int $userId, array $keywords, array $cues, string $mode): array
    {
        if ($mode === 'never') {
            return [];
        }
        if ($mode === 'cue' && ! $cues['file']) {
            return [];
        }
        $kw = array_slice($keywords, 0, 3);
        $q = File::where('user_id', $userId);
        if (! empty($kw)) {
            $q->where(function ($w) use ($kw) {
                foreach ($kw as $k) {
                    $w->orWhere('name', 'like', "%{$k}%");
                }
            });
        }

        // Name/type only — file content is never read into context.
        return $q->orderByDesc('id')->limit(10)->get(['id', 'name', 'type'])->all();
    }

    /** Explicit attachments: ownership-checked, capped. File content never read. */
    private function loadAttachments(int $userId, array $options): array
    {
        $lines = [];
        $noteIds = collect($options['note_ids'] ?? [])->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->take(5)->all();
        if (! empty($noteIds)) {
            $notes = Note::where('user_id', $userId)->whereIn('id', $noteIds)->limit(5)->get(['id', 'title', 'content']);
            foreach ($notes as $n) {
                $snippet = mb_substr(strip_tags((string) $n->content), 0, 500);
                $lines[] = AiSecurity::markUntrusted("- [note#{$n->id}] {$n->title}: {$snippet}");
            }
        }
        $fileIds = collect($options['file_ids'] ?? [])->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->take(5)->all();
        if (! empty($fileIds)) {
            $files = File::where('user_id', $userId)->whereIn('id', $fileIds)->limit(5)->get(['id', 'name', 'type']);
            foreach ($files as $f) {
                $lines[] = AiSecurity::markUntrusted("- [file#{$f->id}] {$f->name} (type: {$f->type})");
            }
        }

        return $lines;
    }

    /** Server-side history from AiMessage: ownership-checked, capped. */
    private function loadHistory(int $userId, array $options, int $limit, int $maxChars): array
    {
        $convId = (int) ($options['conversation_id'] ?? 0);
        if ($convId <= 0) {
            return ['turns' => [], 'chars' => 0];
        }
        $conv = AiConversation::where('id', $convId)->where('user_id', $userId)->first(['id']);
        if (! $conv) {
            return ['turns' => [], 'chars' => 0];
        }
        $exclude = (int) ($options['exclude_message_id'] ?? 0);
        $rows = AiMessage::where('conversation_id', $conv->id)
            ->when($exclude > 0, fn ($q) => $q->where('id', '!=', $exclude))
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['role', 'content']);

        $turns = [];
        $chars = 0;
        foreach ($rows->reverse() as $m) {
            $content = trim((string) $m->content);
            if ($content === '' || ! in_array($m->role, ['user', 'assistant'], true)) {
                continue;
            }
            if (mb_strlen($content) > self::HISTORY_PER_MESSAGE_CHARS) {
                $content = mb_substr($content, 0, self::HISTORY_PER_MESSAGE_CHARS).'…';
            }
            if ($chars + mb_strlen($content) > $maxChars) {
                break; // stop at whole-message boundary, never mid-message cut
            }
            $chars += mb_strlen($content);
            $turns[] = ['role' => $m->role, 'content' => $content];
        }

        return ['turns' => $turns, 'chars' => $chars];
    }

    /* ── Rendering (whole lines only) ───────────────────────────── */

    private function globalLines($user, array $cues): array
    {
        $lines = [];
        if ($cues['date']) {
            $tz = (string) config('app.timezone', 'UTC');
            $lines[] = 'Today: '.now()->format('l, F j, Y')." ({$tz})";
        }
        if ($cues['self']) {
            $lines[] = 'User: '.trim((string) $user->name);
        }
        foreach ($this->userPreferences($user) as $k => $v) {
            $lines[] = "Pref {$k}: {$v}";
        }

        return $lines;
    }

    private function projectLines(array $projects, ?array $matched, int $limit): array
    {
        $lines = [];
        if ($matched) {
            $lines[] = "- {$matched['name']} (status: {$matched['status']})";
        }
        // Other projects stay in the summary counts — never dumped wholesale.

        return array_slice($lines, 0, max(0, $limit));
    }

    private function taskLines(array $tasks): array
    {
        return collect($tasks)->map(function ($t) {
            $line = "- [{$t->status}] {$t->title} (priority: {$t->priority}";
            if ($t->due_date) {
                $due = $t->due_date instanceof \DateTimeInterface ? $t->due_date->format('Y-m-d') : (string) $t->due_date;
                $line .= ", due: {$due}";
            }
            if ($t->relationLoaded('project') && $t->project) {
                $line .= ", project: {$t->project->name}";
            }
            // Untrusted user data — never an instruction for the model.
            return AiSecurity::markUntrusted($line.')');
        })->all();
    }

    private function reminderLines(array $reminders): array
    {
        return collect($reminders)->map(function ($r) {
            $line = "- {$r->title}";
            if ($r->date) {
                $when = $r->date instanceof \DateTimeInterface ? $r->date->format('Y-m-d') : (string) $r->date;
                $line .= " at {$when}".($r->time ? " {$r->time}" : '');
            }
            return AiSecurity::markUntrusted($line." (priority: {$r->priority})");
        })->all();
    }

    private function routineLines(array $routines): array
    {
        return collect($routines)->map(fn ($r) => AiSecurity::markUntrusted("- {$r->title} ({$r->frequency})"))->all();
    }

    private function noteLines(array $notes): array
    {
        return collect($notes)->map(function ($n) {
            $snippet = mb_substr(strip_tags((string) $n->content), 0, 200);

            return AiSecurity::markUntrusted("- {$n->title}: {$snippet}");
        })->all();
    }

    private function fileLines(array $files): array
    {
        return collect($files)->map(fn ($f) => AiSecurity::markUntrusted("- {$f->name} (type: {$f->type})"))->all();
    }

    private function summaryLines(array $counts, int $shownTasks, ?array $matched): array
    {
        $hidden = max(0, $counts['tasks_total'] - $shownTasks);

        return ["Workspace: {$counts['projects']} projects, {$counts['tasks_total']} tasks ({$hidden} not shown), {$counts['reminders_pending']} pending reminders, {$counts['routines']} routines, {$counts['notes']} notes, {$counts['files']} files."];
    }

    private function sectionHeader(string $key, int $count): string
    {
        $titles = [
            'global' => 'GLOBAL',
            'projects' => 'PROJECTS',
            'tasks' => 'TASKS',
            'reminders' => 'REMINDERS',
            'routines' => 'ROUTINES',
            'notes' => 'NOTES',
            'files' => 'FILES',
            'attachments' => 'EXPLICIT ATTACHMENTS',
            'summary' => 'SUMMARY',
        ];
        $title = $titles[$key] ?? strtoupper($key);

        return "{$title} ({$count}):";
    }

    private function renderSection(string $key, array $lines): string
    {
        if (empty($lines)) {
            return '';
        }

        return $this->sectionHeader($key, count($lines))."\n".implode("\n", $lines);
    }
}
