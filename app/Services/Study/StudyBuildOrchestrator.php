<?php

namespace App\Services\Study;

use App\Http\Controllers\AiPlanController;
use App\Models\AiPlan;
use App\Models\AiStudyBuild;
use App\Services\AiLogger;
use App\Services\AiToolService;
use App\Support\AiTrace;

/**
 * StudyBuildOrchestrator — Phase 3 of the automatic book-chain build.
 *
 * Owns the multi-chunk chain (Model B: one confirmation card per chunk):
 * - startFromOutline(): parse → chunk → persist AiStudyBuild → propose chunk 1.
 * - advanceIfPlanDone(): after a chunk plan reaches DONE, propose the next
 *   chunk attached to the REAL book project id (never a re-guessed name, so
 *   no duplicate book project can ever be created).
 * - pendingNextPlan(): retry-safe lookup for a lost confirm-phase response.
 *
 * HTTP-agnostic: returns Eloquent models + plain arrays; the controller
 * serializes them for the chat cards.
 */
class StudyBuildOrchestrator
{
    /**
     * Server-side full-paste detector: returns ['outline'=>..., 'chunks'=>...]
     * when the message is a multi-chunk study outline with an explicit build
     * demand, otherwise null (normal model flow handles it).
     *
     * Rules (all must hold):
     * - agent mode is checked by the caller, not here;
     * - a build verb (بساز/بنا کن/ایجاد کن/درست کن/...) is present;
     * - chapter/lesson headers are present;
     * - the parsed outline needs MORE THAN ONE chunk (single-chunk pastes
     *   stay on the model plan_propose path — no behavior change for them).
     */
    public static function detectFromMessage(string $message): ?array
    {
        $norm = \App\Services\AiIntentRouter::normalize($message);

        $hasVerb = false;
        foreach (['بساز', 'بنا کن', 'ایجاد', 'درست کن', 'راه بنداز', 'راهاندازی', 'برنامهریزی', 'طراحی کن', 'بشکن', 'تقسیم کن'] as $verb) {
            if (str_contains($norm, $verb)) {
                $hasVerb = true;
                break;
            }
        }
        if (! $hasVerb) {
            return null;
        }

        if (\App\Services\AiIntentRouter::countStructureHeaders($norm) < 2
            && ! str_contains($norm, 'فصل') && ! str_contains($norm, 'درسنامه')) {
            return null;
        }

        $outline = StudyOutlineParser::parse($message);
        if (count($outline['chapters'] ?? []) < 2) {
            return null;
        }

        $chunks = StudyPlanChunker::chunk($outline);
        if (count($chunks) <= 1) {
            // Fits one plan_propose — leave it to the model path.
            return null;
        }

        foreach ($chunks as $chunk) {
            if (StudyPlanChunker::checkChunk($chunk) !== null) {
                return null;
            }
        }

        return ['outline' => $outline, 'chunks' => $chunks];
    }

    /**
     * Map one chunker chunk to plan_propose args.
     * Chunk 1 builds the book project; later chunks attach via project_id.
     */
    public static function buildPlanArgs(array $chunk, ?int $bookProjectId = null): array
    {
        $project = [
            'name' => $chunk['book_title'],
            'description' => $chunk['book_title'].' — '.$chunk['total_chunks'].' بسته',
        ];
        if ($bookProjectId !== null) {
            $project['project_id'] = $bookProjectId;
        }

        $subprojects = [];
        foreach ($chunk['subprojects'] as $sub) {
            $tasks = [];
            foreach ($sub['tasks'] as $task) {
                $subtasks = [];
                foreach ($task['subtasks'] as $s) {
                    $subtasks[] = ['title' => $s['title']];
                }
                $t = ['title' => $task['title'], 'subtasks' => $subtasks];
                if (($task['description'] ?? null) !== null) {
                    $t['description'] = $task['description'];
                }
                if (($task['weight'] ?? null) !== null) {
                    $t['weight'] = $task['weight'];
                }
                $tasks[] = $t;
            }
            $s = ['name' => $sub['title'], 'tasks' => $tasks];
            if (($sub['description'] ?? null) !== null) {
                $s['description'] = $sub['description'];
            }
            $subprojects[] = $s;
        }

        return [
            'title' => $chunk['title'],
            'project' => $project,
            'subprojects' => $subprojects,
        ];
    }

    /**
     * Start a chain from a parsed outline. Returns
     * ['build'=>AiStudyBuild, 'plan'=>AiPlan] or ['error'=>..., 'code'=>...].
     */
    public static function startFromOutline($user, $conversationId, array $outline, ?string $rid = null): array
    {
        if (empty($outline['chapters'])) {
            return ['ok' => false, 'error' => __('The outline has no chapters to build.')];
        }

        $chunks = StudyPlanChunker::chunk($outline);
        foreach ($chunks as $chunk) {
            $violation = StudyPlanChunker::checkChunk($chunk);
            if ($violation !== null) {
                AiLogger::log('study_build.chunk_invalid', AiTrace::ids(null, $rid, $user->id, $conversationId) + ['chunk' => $chunk['chunk_index'], 'error' => $violation]);

                return ['ok' => false, 'error' => $violation];
            }
        }

        $now = now()->toIso8601String();
        $build = AiStudyBuild::create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'title' => (string) $outline['book_title'],
            'outline_json' => $outline,
            'chunks_json' => $chunks,
            'plan_ids' => [],
            'total_chunks' => count($chunks),
            'done_chunks' => 0,
            // P1 Master Plan: the first structure version is born proposed —
            // nothing may execute until the user explicitly approves it.
            'version' => 1,
            'approved_version' => null,
            'approved_at' => null,
            'approved_by' => null,
            'versions' => [[
                'version' => 1,
                'status' => 'proposed',
                'outline' => $outline,
                'total_chunks' => count($chunks),
                'note' => null,
                'created_at' => $now,
            ]],
            'status' => AiStudyBuild::STATUS_BUILDING,
            'expires_at' => now()->addHours(AiStudyBuild::EXPIRY_HOURS),
        ]);

        $created = self::createChunkPlan($user, $conversationId, $build, $chunks[0], $rid);
        if (! ($created['ok'] ?? false)) {
            return $created;
        }

        AiLogger::log('study_build.started', AiTrace::ids(null, $rid, $user->id, $conversationId) + ['build_id' => $build->id, 'chunks' => count($chunks)]);

        return ['ok' => true, 'build' => $build->fresh(), 'plan' => $created['plan']];
    }

    /**
     * Propose the plan for one chunk (validates + stores, nothing executes).
     * Returns ['ok'=>true, 'plan'=>AiPlan] or ['ok'=>false, 'error'=>...].
     */
    public static function createChunkPlan($user, $conversationId, AiStudyBuild $build, array $chunk, ?string $rid = null): array
    {
        $service = new AiToolService;
        $args = self::buildPlanArgs($chunk, $build->book_project_id);

        $check = $service->validateCall('plan_propose', $args, $user);
        if (! ($check['ok'] ?? false)) {
            $build->update(['last_error' => mb_substr($check['error'] ?? 'Invalid chunk.', 0, 500)]);
            AiLogger::log('study_build.chunk_invalid', AiTrace::ids(null, $rid, $user->id, $conversationId) + ['build_id' => $build->id, 'chunk' => $chunk['chunk_index'], 'error' => $check['error'] ?? null]);

            return ['ok' => false, 'error' => $check['error'] ?? __('Invalid chunk.')];
        }

        $controller = app(AiPlanController::class);
        $stored = $controller->storeResolvedPlan((int) $user->id, $conversationId, $check['resolved']);
        $plan = $stored['plan'];

        // P2 package identity: stable link to (build, master version,
        // position). Retired heads keep their stamps for audit.
        if ($plan->master_build_id === null) {
            $plan->update([
                'master_build_id' => $build->id,
                'master_version' => (int) ($build->version ?? 1),
                'package_index' => (int) ($chunk['chunk_index'] ?? 0) ?: null,
            ]);
        }

        $known = array_map('intval', (array) ($build->plan_ids ?? []));
        if (! in_array((int) $plan->id, $known, true)) {
            $known[] = (int) $plan->id;
        }
        $build->update([
            'last_plan_id' => $plan->id,
            'plan_ids' => array_values($known),
            'last_error' => null,
        ]);

        AiLogger::log('study_build.chunk_proposed', AiTrace::ids(null, $rid, $user->id, $conversationId, null, $plan->id) + ['build_id' => $build->id, 'chunk' => $chunk['chunk_index']]);

        return ['ok' => true, 'plan' => $plan];
    }

    /**
     * Called after a chunk plan reaches DONE. Advances the chain:
     * returns ['build'=>..., 'next_plan'=>?AiPlan, 'finished'=>bool]
     * or ['error'=>...] when the chain cannot continue, or null when the
     * plan is not the active head of any actionable build.
     */
    public static function advanceIfPlanDone(AiPlan $plan, $user, ?string $rid = null): ?array
    {
        // Row lock: two concurrent confirm-phase calls on the same DONE head
        // must serialize here. The plan row alone does not protect the build
        // row (under REPEATABLE READ a second transaction could otherwise read
        // a stale last_plan_id and propose the same chunk twice). Callers run
        // inside a DB transaction; on SQLite the lock is a harmless no-op.
        $build = AiStudyBuild::where('user_id', $user->id)
            ->where('status', AiStudyBuild::STATUS_BUILDING)
            ->where('last_plan_id', $plan->id)
            ->lockForUpdate()
            ->first();

        if (! $build || $build->isExpired()) {
            if ($build && $build->isExpired()) {
                $build->update(['status' => AiStudyBuild::STATUS_EXPIRED]);
            }

            return null;
        }

        if ($plan->status !== AiPlan::STATUS_DONE) {
            return null;
        }

        $bookId = $build->book_project_id ?? self::extractBookProjectId($plan);
        if ($bookId !== null && $build->book_project_id === null) {
            $build->update(['book_project_id' => $bookId]);
        }

        // Finish check BEFORE any counter change: the final head needs no
        // book project, and a stalled middle chunk must NOT consume a
        // done_chunks increment per retry (that drifted the next-chunk index
        // and could falsely complete the chain after repeated retries).
        if ($build->done_chunks + 1 >= $build->total_chunks) {
            $build->update(['done_chunks' => $build->done_chunks + 1, 'status' => AiStudyBuild::STATUS_DONE]);
            AiLogger::log('study_build.done', AiTrace::ids(null, $rid, $user->id, $build->conversation_id) + ['build_id' => $build->id]);

            return ['build' => $build->fresh(), 'next_plan' => null, 'finished' => true];
        }

        if ($bookId === null) {
            // Chunk 1 produced no book project (e.g. user deleted it before
            // confirming). Stall with a message instead of guessing a name.
            // No counter change: a later retry resumes at the same chunk.
            $build->update(['last_error' => 'Book project is missing — cannot attach the next chunk.']);
            AiLogger::log('study_build.stalled', AiTrace::ids(null, $rid, $user->id, $build->conversation_id) + ['build_id' => $build->id, 'reason' => 'missing_book_project']);

            return ['build' => $build->fresh(), 'next_plan' => null, 'finished' => false, 'error' => __('Book project is missing. Cancel this chain and start over.')];
        }

        $build->update(['done_chunks' => $build->done_chunks + 1]);
        $build = $build->fresh();

        $chunks = $build->chunks_json ?? [];
        $next = $chunks[$build->done_chunks] ?? null; // done_chunks is now 1-based count of finished
        if ($next === null) {
            $build->update(['status' => AiStudyBuild::STATUS_DONE]);

            return ['build' => $build->fresh(), 'next_plan' => null, 'finished' => true];
        }

        $created = self::createChunkPlan($user, $build->conversation_id, $build->fresh(), $next, $rid);
        if (! ($created['ok'] ?? false)) {
            return ['build' => $build->fresh(), 'next_plan' => null, 'finished' => false, 'error' => $created['error']];
        }

        return ['build' => $build->fresh(), 'next_plan' => $created['plan'], 'finished' => false];
    }

    /**
     * Retry-safe: a lost confirm-phase response (client got deduped:true)
     * can still retrieve the already-proposed next chunk.
     */
    public static function pendingNextPlan(AiPlan $plan, $user): ?AiPlan
    {
        // Locking read: pairs with the lock in advanceIfPlanDone so a
        // concurrent recovery in the confirm-phase DONE branch cannot miss a
        // just-proposed head. No-op on SQLite, FOR UPDATE on MySQL.
        $build = AiStudyBuild::where('user_id', $user->id)
            ->where('status', AiStudyBuild::STATUS_BUILDING)
            ->whereJsonContains('plan_ids', $plan->id)
            ->lockForUpdate()
            ->first();

        if (! $build) {
            return null;
        }

        $next = $build->lastPlan;
        if ($next && $next->status === AiPlan::STATUS_PROPOSED && $next->id !== $plan->id) {
            return $next;
        }

        return null;
    }

    // ── P1 Master Plan: versioned structure + explicit approval ──
    //
    // The build row IS the master record (no extra table): approve/revise
    // serialize on the same lockForUpdate the chain already uses, and the
    // version history stays a small bounded JSON document (MAX_VERSIONS).
    // No operational data (projects/tasks/…) may be created before the
    // current version is explicitly approved — enforced by the confirm
    // gate in AiPlanController, not by convention.

    /**
     * Normalize a model- or parser-produced outline to the strict schema.
     * Unknown keys are stripped (never trusted downstream); shape, titles
     * and chunkability are enforced separately by validateOutline().
     */
    public static function sanitizeOutline(array $outline): array
    {
        $chapters = [];
        foreach (array_values((array) ($outline['chapters'] ?? [])) as $i => $ch) {
            if (! is_array($ch)) {
                continue;
            }
            $lessons = [];
            foreach (array_values((array) ($ch['lessons'] ?? [])) as $j => $ls) {
                if (! is_array($ls)) {
                    continue;
                }
                $topics = [];
                foreach ((array) ($ls['topics'] ?? []) as $t) {
                    if (is_string($t) && trim($t) !== '') {
                        $topics[] = mb_substr(trim($t), 0, 500);
                    }
                }
                $lessons[] = [
                    'index' => $j + 1,
                    'number' => isset($ls['number']) && is_numeric($ls['number']) ? (int) $ls['number'] : null,
                    'title' => (string) ($ls['title'] ?? ''),
                    'pages' => isset($ls['pages']) && is_numeric($ls['pages']) ? (int) $ls['pages'] : null,
                    'topics' => $topics,
                ];
            }
            $extra = [];
            foreach ((array) ($ch['extra_tasks'] ?? []) as $t) {
                if (is_string($t) && trim($t) !== '') {
                    $extra[] = mb_substr(trim($t), 0, 500);
                }
            }
            $chapters[] = [
                'index' => $i + 1,
                'number' => isset($ch['number']) && is_numeric($ch['number']) ? (int) $ch['number'] : null,
                'title' => (string) ($ch['title'] ?? ''),
                'pages' => isset($ch['pages']) && is_numeric($ch['pages']) ? (int) $ch['pages'] : null,
                'lessons' => $lessons,
                'extra_tasks' => $extra,
            ];
        }

        return [
            'book_title' => (string) ($outline['book_title'] ?? ''),
            'chapters' => $chapters,
        ];
    }

    /**
     * Server-side master validation — the final authority before the UI
     * may offer approval. Model output is never trusted. Returns the
     * Persian reason or null when the outline is acceptable.
     */
    public static function validateOutline(array $outline): ?string
    {
        $title = trim((string) ($outline['book_title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 120 || $title === 'کتاب بدون عنوان') {
            return 'عنوان کتاب معتبر نیست — ساختار بدون عنوان قابل تأیید نیست.';
        }
        $chapters = array_values((array) ($outline['chapters'] ?? []));
        if (count($chapters) < 2 || count($chapters) > 60) {
            return 'تعداد فصل‌ها باید بین ۲ تا ۶۰ باشد — ساختار ناقص قابل تأیید نیست.';
        }
        foreach ($chapters as $ch) {
            if (! is_array($ch)) {
                return 'یک فصل ساختار نامعتبر دارد.';
            }
            $ct = trim((string) ($ch['title'] ?? ''));
            if ($ct === '' || mb_strlen($ct) > 200) {
                return 'عنوان یک فصل خالی یا بیش از حد طولانی است.';
            }
            $lessons = array_values((array) ($ch['lessons'] ?? []));
            if (count($lessons) > 30) {
                return 'تعداد درس‌های یک فصل بیش از حد مجاز است (سقف ۳۰).';
            }
            if (count($lessons) + count((array) ($ch['extra_tasks'] ?? [])) < 1) {
                return 'یک فصل هیچ درس یا موضوعی ندارد ('.$ct.').';
            }
            foreach ($lessons as $ls) {
                if (! is_array($ls)) {
                    return 'یک درسنامه ساختار نامعتبر دارد.';
                }
                $lt = trim((string) ($ls['title'] ?? ''));
                if ($lt === '' || mb_strlen($lt) > 200) {
                    return 'عنوان یک درسنامه خالی یا بیش از حد طولانی است.';
                }
                if (count((array) ($ls['topics'] ?? [])) > 50) {
                    return 'تعداد زیرموضوع‌های یک درسنامه بیش از حد مجاز است (سقف ۵۰).';
                }
            }
        }

        // Chunkability: the master must split into executable packages.
        $chunks = StudyPlanChunker::chunk($outline);
        if (count($chunks) <= 1) {
            return 'این ساختار فقط یک بسته می‌شود — زنجیره لازم ندارد؛ از مسیر پلن ساده استفاده کنید.';
        }
        foreach ($chunks as $chunk) {
            if (StudyPlanChunker::checkChunk($chunk) !== null) {
                return 'یک بسته از سقف مجاز بزرگ‌تر است — ساختار باید خردتر شود.';
            }
        }

        return null;
    }

    /**
     * Full master state for the UI card and the refresh endpoint — built
     * only from server records (versions history + current outline).
     * Pre-P1 rows without a versions array get a synthesized v1 entry.
     */
    public static function masterDetail(AiStudyBuild $build): array
    {
        $build = $build->fresh() ?? $build;
        $versions = array_values((array) ($build->versions ?? []));
        if ($versions === [] && is_array($build->outline_json)) {
            $versions[] = [
                'version' => (int) ($build->version ?? 1),
                'status' => $build->approved_version !== null ? 'approved' : 'proposed',
                'outline' => $build->outline_json,
                'total_chunks' => (int) $build->total_chunks,
                'note' => 'grandfathered',
                'created_at' => optional($build->created_at)->toIso8601String(),
            ];
        }
        // History entries never carry execution state — only the outline.
        $history = array_map(fn ($v) => [
            'version' => (int) ($v['version'] ?? 0),
            'status' => (string) ($v['status'] ?? 'proposed'),
            'outline' => $v['outline'] ?? null,
            'total_chunks' => (int) ($v['total_chunks'] ?? 0),
            'note' => $v['note'] ?? null,
            'created_at' => $v['created_at'] ?? null,
        ], $versions);

        return [
            'id' => $build->id,
            'title' => $build->title,
            'status' => $build->status,
            'version' => (int) ($build->version ?? 1),
            'approved_version' => $build->approved_version !== null ? (int) $build->approved_version : null,
            'approved_at' => $build->approved_at?->toIso8601String(),
            'approved_by' => $build->approved_by,
            'done_chunks' => (int) $build->done_chunks,
            'total_chunks' => (int) $build->total_chunks,
            'book_project_id' => $build->book_project_id,
            'expires_at' => $build->expires_at?->toIso8601String(),
            'outline' => $build->outline_json,
            'validation' => ($err = self::validateOutline((array) $build->outline_json))
                ? ['ok' => false, 'error' => $err]
                : ['ok' => true, 'error' => null],
            'versions' => $history,
        ];
    }

    /**
     * Approve a specific master version. The approval binds to the version
     * number — approving v2 retires v1, and a later v3 needs its own
     * approval. Idempotent for repeat approvals of the same version.
     */
    public static function approveVersion(AiStudyBuild $build, $user, int $version, ?string $rid = null): array
    {
        $locked = AiStudyBuild::where('id', $build->id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();
        if (! $locked) {
            return ['ok' => false, 'code' => 'not_found', 'error' => __('Master structure not found.')];
        }
        if ($locked->status !== AiStudyBuild::STATUS_BUILDING) {
            if ($locked->approved_version !== null && (int) $locked->approved_version === $version) {
                return ['ok' => true, 'deduped' => true, 'build' => $locked->fresh(), 'detail' => self::masterDetail($locked->fresh())];
            }

            return ['ok' => false, 'code' => 'not_building', 'error' => __('This chain is no longer active.')];
        }
        if ($version !== (int) $locked->version) {
            $code = $version < (int) $locked->version ? 'superseded_version' : 'unknown_version';

            return ['ok' => false, 'code' => $code, 'error' => $version < (int) $locked->version
                ? __('This structure version was replaced by a newer one (v:version).', ['version' => $locked->version])
                : __('Unknown structure version.')];
        }
        if (($err = self::validateOutline((array) $locked->outline_json)) !== null) {
            AiLogger::log('study_build.approve_invalid', AiTrace::ids(null, $rid, $user->id, $locked->conversation_id) + ['build_id' => $locked->id, 'version' => $version, 'error' => $err]);

            return ['ok' => false, 'code' => 'invalid_structure', 'error' => $err];
        }
        if ($locked->approved_version !== null && (int) $locked->approved_version === $version) {
            return ['ok' => true, 'deduped' => true, 'build' => $locked->fresh(), 'detail' => self::masterDetail($locked->fresh())];
        }

        $versions = array_values((array) ($locked->versions ?? []));
        foreach ($versions as &$v) {
            if ((int) ($v['version'] ?? 0) === $version) {
                $v['status'] = 'approved';
            } elseif (($v['status'] ?? '') === 'proposed') {
                $v['status'] = 'superseded';
            }
        }
        unset($v);
        $locked->update([
            'versions' => $versions,
            'approved_version' => $version,
            'approved_at' => now(),
            'approved_by' => $user->id,
            'last_error' => null,
        ]);
        // The waiting head was proposed while the master was unapproved and
        // may have expired during review — extend it so approval is usable.
        $head = $locked->lastPlan;
        if ($head && $head->status === \App\Models\AiPlan::STATUS_PROPOSED) {
            $head->update(['expires_at' => now()->addMinutes(\App\Models\AiPlan::EXPIRY_MINUTES)]);
        }

        AiLogger::log('study_build.approved', AiTrace::ids(null, $rid, $user->id, $locked->conversation_id) + ['build_id' => $locked->id, 'version' => $version]);
        $fresh = $locked->fresh();

        return ['ok' => true, 'build' => $fresh, 'detail' => self::masterDetail($fresh)];
    }

    /**
     * Revise the master from free-text feedback. Only before any package
     * executed (done_chunks == 0 and no plan past proposed) — mid-chain
     * restructuring belongs to P2. Produces version N+1, preserves N, and
     * proposes the new first chunk. The provider is consulted; a failure
     * stores nothing, so retry is a plain repeat.
     */
    public static function reviseFromFeedback(AiStudyBuild $build, $user, string $feedback, ?string $rid = null): array
    {
        $feedback = trim($feedback);
        if ($feedback === '' || mb_strlen($feedback) > 2000) {
            return ['ok' => false, 'code' => 'invalid_feedback', 'error' => __('Describe the change you want (up to 2000 characters).')];
        }

        $locked = AiStudyBuild::where('id', $build->id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();
        if (! $locked || $locked->status !== AiStudyBuild::STATUS_BUILDING) {
            return ['ok' => false, 'code' => 'not_building', 'error' => __('This chain is no longer active.')];
        }
        if ((int) $locked->done_chunks !== 0) {
            return ['ok' => false, 'code' => 'already_started', 'error' => __('Packages are already built — the structure can no longer be revised.')];
        }
        $livePlans = \App\Models\AiPlan::where('user_id', $user->id)
            ->whereIn('id', array_map('intval', (array) ($locked->plan_ids ?? [])))
            ->whereNotIn('status', [\App\Models\AiPlan::STATUS_PROPOSED])
            ->exists();
        if ($livePlans) {
            return ['ok' => false, 'code' => 'already_started', 'error' => __('A package is already confirmed — the structure can no longer be revised.')];
        }
        if (count((array) ($locked->versions ?? [])) >= \App\Models\AiStudyBuild::MAX_VERSIONS) {
            return ['ok' => false, 'code' => 'too_many_revisions', 'error' => __('Too many revisions — cancel this chain and start a fresh one.')];
        }
        $baseVersion = (int) $locked->version;
        $conversationId = $locked->conversation_id;
        $currentOutline = (array) $locked->outline_json;

        // Provider call happens OUTSIDE the row lock (up to 90s).
        $providers = app(\App\Services\AiProviderService::class);
        $resolved = $providers->resolve($user);
        if (! $resolved) {
            return ['ok' => false, 'code' => 'no_provider', 'error' => __('No AI provider is configured.')];
        }
        try {
            $raw = self::requestRevisionModel($providers, $resolved, $currentOutline, $feedback);
            $decoded = self::decodeModelJson($raw);
            $outline = self::sanitizeOutline(is_array($decoded) ? $decoded : []);
        } catch (\RuntimeException $e) {
            AiLogger::error('study_build.revise_provider_failed', AiTrace::ids(null, $rid, $user->id, $conversationId) + ['build_id' => $locked->id, 'error' => mb_substr($e->getMessage(), 0, 300)]);

            return ['ok' => false, 'code' => 'provider_error', 'error' => $e->getMessage()];
        }
        if (($err = self::validateOutline($outline)) !== null) {
            AiLogger::log('study_build.revise_invalid', AiTrace::ids(null, $rid, $user->id, $conversationId) + ['build_id' => $locked->id, 'error' => $err]);

            return ['ok' => false, 'code' => 'invalid_outline', 'error' => $err];
        }
        $chunks = \App\Services\Study\StudyPlanChunker::chunk($outline);

        // Re-lock: the master must be untouched since we read it — including
        // the pre-execution condition (a package could have been confirmed
        // during the ~90s provider call).
        $fresh = AiStudyBuild::where('id', $locked->id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();
        if (! $fresh || (int) $fresh->version !== $baseVersion || $fresh->status !== AiStudyBuild::STATUS_BUILDING) {
            return ['ok' => false, 'code' => 'version_changed', 'error' => __('The structure changed while revising — review the current version and try again.')];
        }
        if ((int) $fresh->done_chunks !== 0 || \App\Models\AiPlan::where('user_id', $user->id)
                ->whereIn('id', array_map('intval', (array) ($fresh->plan_ids ?? [])))
                ->whereNotIn('status', [\App\Models\AiPlan::STATUS_PROPOSED])
                ->exists()) {
            return ['ok' => false, 'code' => 'already_started', 'error' => __('A package was confirmed while revising — the structure can no longer be revised.')];
        }
        // Retire stale proposed heads from the old version (unconfirmed
        // drafts only — already_started above guarantees nothing executed).
        // P2: retired as OBSOLETE (not cancelled) so the UI can tell
        // "replaced by restructure" apart from "cancelled by user".
        $retiredIds = \App\Models\AiPlan::where('user_id', $user->id)
            ->whereIn('id', array_map('intval', (array) ($fresh->plan_ids ?? [])))
            ->where('status', \App\Models\AiPlan::STATUS_PROPOSED)
            ->pluck('id')->all();

        $versions = array_values((array) ($fresh->versions ?? []));
        foreach ($versions as &$v) {
            if (($v['status'] ?? '') === 'proposed') {
                $v['status'] = 'superseded';
            }
        }
        unset($v);
        $newVersion = $baseVersion + 1;
        $versions[] = [
            'version' => $newVersion,
            'status' => 'proposed',
            'outline' => $outline,
            'total_chunks' => count($chunks),
            'note' => mb_substr($feedback, 0, 200),
            'created_at' => now()->toIso8601String(),
        ];
        $fresh->update([
            'outline_json' => $outline,
            'chunks_json' => $chunks,
            'total_chunks' => count($chunks),
            'title' => (string) ($outline['book_title'] ?? $fresh->title),
            'version' => $newVersion,
            'approved_version' => null,
            'approved_at' => null,
            'approved_by' => null,
            'versions' => $versions,
            'last_error' => null,
        ]);

        $created = self::createChunkPlan($user, $conversationId, $fresh->fresh(), $chunks[0], $rid);
        if (! ($created['ok'] ?? false)) {
            return ['ok' => false, 'code' => 'chunk_invalid', 'error' => $created['error'] ?? __('Invalid chunk.')];
        }
        if ($retiredIds !== []) {
            \App\Models\AiPlan::where('user_id', $user->id)
                ->whereIn('id', $retiredIds)
                ->update([
                    'status' => \App\Models\AiPlan::STATUS_OBSOLETE,
                    'superseded_by_plan_id' => $created['plan']->id,
                ]);
        }

        AiLogger::log('study_build.revised', AiTrace::ids(null, $rid, $user->id, $conversationId) + ['build_id' => $fresh->id, 'version' => $newVersion]);
        $done = $fresh->fresh();

        return ['ok' => true, 'build' => $done, 'plan' => $created['plan'], 'detail' => self::masterDetail($done)];
    }

    /**
     * Ask the provider for a revised outline as strict JSON. Mirrors the
     * WorkoutImportService transport (openai/gemini/anthropic branches).
     *
     * @throws \RuntimeException on provider failure or unusable output.
     */
    private static function requestRevisionModel($providers, array $resolved, array $outline, string $feedback): string
    {
        $schema = '{"book_title":"...","chapters":[{"title":"...","lessons":[{"title":"...","topics":["..."]}]}]}';
        $system = 'You revise a book outline. Reply with JSON ONLY matching this schema: '.$schema
            .' Rules: keep chapter/lesson order and counts unless the feedback asks otherwise;'
            .' keep titles verbatim except requested changes; every chapter needs at least one lesson;'
            .' no extra keys, no markdown, no explanation.';
        $userText = 'Current outline JSON: '.json_encode($outline, JSON_UNESCAPED_UNICODE)
            ."\n\nRequested change: ".$feedback;

        return self::requestJsonModel($providers, $resolved, $system, $userText);
    }

    /**
     * Shared JSON-model transport (openai/gemini/anthropic branches).
     *
     * @throws \RuntimeException on provider failure.
     */
    private static function requestJsonModel($providers, array $resolved, string $system, string $userText): string
    {
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userText],
        ];

        if ($resolved['type'] === 'gemini') {
            $cfg = $resolved['config'];
            $url = rtrim($cfg['base_url'], '/').'/'.$resolved['model'].':generateContent?key='.$resolved['key'];
            $response = $providers->postJson($url, ['contents' => [['role' => 'user', 'parts' => [['text' => $system."\n\n".$userText]]]], 'generationConfig' => ['responseMimeType' => 'application/json']], [], 90);
            if ($response->failed()) {
                throw new \RuntimeException($providers->formatErrorResponse($response));
            }

            return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        }
        if ($resolved['type'] === 'anthropic') {
            $cfg = $resolved['config'];
            $response = $providers->postJson($cfg['base_url'], ['model' => $resolved['model'], 'max_tokens' => 6000, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $userText]]], ['x-api-key' => $resolved['key'], 'anthropic-version' => '2023-06-01'], 90);
            if ($response->failed()) {
                throw new \RuntimeException($providers->formatErrorResponse($response));
            }

            return (string) data_get($response->json(), 'content.0.text', '');
        }

        $cfg = $resolved['config'];
        $payload = $providers->openAiPayload($messages, $resolved['model'], false, $cfg['base_url']);
        $payload['response_format'] = ['type' => 'json_object'];
        $response = $providers->postJson($providers->endpointFor($cfg['base_url'], $resolved['type']), $payload, ['Authorization' => 'Bearer '.$resolved['key']], 90);
        if ($response->failed()) {
            throw new \RuntimeException($providers->formatErrorResponse($response));
        }

        return (string) data_get($response->json(), 'choices.0.message.content', '');
    }

    private static function decodeModelJson(string $content): array
    {
        $clean = trim((string) preg_replace('/^```(?:json)?|```$/m', '', $content));
        $decoded = json_decode($clean, true);
        if (! is_array($decoded)) {
            $start = strpos($clean, '{');
            $end = strrpos($clean, '}');
            $decoded = $start !== false && $end !== false ? json_decode(substr($clean, $start, $end - $start + 1), true) : null;
        }
        if (! is_array($decoded)) {
            throw new \RuntimeException(__('The AI returned an unusable structure — please try again.'));
        }

        return $decoded;
    }

    /** Mark the chain cancelled when its head plan is cancelled. */
    public static function cancelForPlan(AiPlan $plan, $user, ?string $rid = null): ?AiStudyBuild
    {
        $build = AiStudyBuild::where('user_id', $user->id)
            ->where('status', AiStudyBuild::STATUS_BUILDING)
            ->where('last_plan_id', $plan->id)
            ->first();

        if (! $build) {
            return null;
        }

        $build->update(['status' => AiStudyBuild::STATUS_CANCELLED]);
        AiLogger::log('study_build.cancelled', AiTrace::ids(null, $rid, $user->id, $build->conversation_id) + ['build_id' => $build->id]);

        return $build->fresh();
    }

    /** Book project = first created project of chunk 1. */
    private static function extractBookProjectId(AiPlan $plan): ?int
    {
        $items = $plan->created_items ?? [];
        $ids = array_values(array_map('intval', (array) ($items['projects'] ?? [])));

        return $ids[0] ?? null;
    }

    // ── P2 packages: independent, reviewable, revisable units ──
    //
    // Three levels stay separate:
    //   Master Plan (AiStudyBuild + versions) → the whole request.
    //   Package (AiPlan + master_build_id/version/index) → one executable chunk.
    //   Phase (phases[] inside a plan) → internal steps of one package.
    // No new table: the package identity columns on ai_plans give stable
    // links + indexed dependency lookup (see migration rationale).

    /**
     * Ordered package view of a chain, from server records only. Each
     * package carries its scope, dependencies, expected vs actual output,
     * and the actions valid for its current state (the server re-checks
     * every action — this list only drives the UI).
     */
    public static function packageList(AiStudyBuild $build): array
    {
        $build = $build->fresh() ?? $build;
        $ids = array_values(array_map('intval', (array) ($build->plan_ids ?? [])));
        $rows = \App\Models\AiPlan::whereIn('id', $ids)->get()->keyBy('id');
        $plans = [];
        foreach ($ids as $id) {
            if ($rows->has($id)) {
                $plans[] = $rows->get($id);
            }
        }
        usort($plans, fn ($a, $b) => ((int) ($a->package_index ?? 999999)) <=> ((int) ($b->package_index ?? 999999)) ?: $a->id <=> $b->id);

        $controller = app(AiPlanController::class);
        $chunks = [];
        foreach ((array) ($build->chunks_json ?? []) as $ch) {
            if (isset($ch['chunk_index'])) {
                $chunks[(int) $ch['chunk_index']] = $ch;
            }
        }
        $knownIndex = [];
        foreach ($plans as $plan) {
            if ($plan->package_index !== null) {
                $knownIndex[(int) $plan->package_index] = true;
            }
        }

        $out = [];
        // Future slots: a valid view of not-yet-proposed packages derived
        // from the stored chunks — no rows are created for them. They stay
        // locked until the chain actually proposes them in order.
        for ($i = 1; $i <= (int) $build->total_chunks; $i++) {
            if (isset($knownIndex[$i])) {
                continue;
            }
            $chunk = $chunks[$i] ?? null;
            $scope = [];
            foreach ((array) ($chunk['subprojects'] ?? []) as $sub) {
                if (isset($sub['title']) && $sub['title'] !== '') {
                    $scope[] = (string) $sub['title'];
                }
            }
            $totals = (array) ($chunk['totals'] ?? []);
            $out[] = [
                'package_index' => $i,
                'plan_id' => null,
                'title' => (string) ($chunk['title'] ?? 'بسته '.$i),
                'status' => 'planned',
                'master_version' => (int) $build->version,
                'is_current_version' => true,
                'is_head' => false,
                'scope' => $scope,
                'depends_on' => $i > 1 ? [$i - 1] : [],
                'expected' => [
                    'tasks' => (int) ($totals['tasks'] ?? 0),
                    'subtasks' => (int) ($totals['subtasks'] ?? 0),
                    'subprojects' => (int) ($totals['subprojects'] ?? 0),
                ],
                'created' => [],
                'last_error' => null,
                'actions' => ['view'],
                'plan' => null,
            ];
        }

        foreach ($plans as $plan) {
            $idx = $plan->package_index !== null ? (int) $plan->package_index : null;
            $chunk = ($idx !== null && isset($chunks[$idx])) ? $chunks[$idx] : null;
            $scope = [];
            if ($chunk) {
                foreach ((array) ($chunk['subprojects'] ?? []) as $sub) {
                    if (isset($sub['title']) && $sub['title'] !== '') {
                        $scope[] = (string) $sub['title'];
                    }
                }
            }
            if ($scope === []) {
                foreach ((array) ($plan->structure['projects'][0]['subprojects'] ?? []) as $sub) {
                    if (isset($sub['name']) && $sub['name'] !== '') {
                        $scope[] = (string) $sub['name'];
                    }
                }
            }
            $serialized = $controller->serialize($plan);
            $out[] = [
                'package_index' => $idx,
                'plan_id' => $plan->id,
                'title' => $plan->title,
                'status' => $plan->status,
                'master_version' => $plan->master_version !== null ? (int) $plan->master_version : null,
                'is_current_version' => $plan->master_version !== null && (int) $plan->master_version === (int) $build->version,
                'is_head' => (int) $build->last_plan_id === (int) $plan->id,
                'scope' => $scope,
                'depends_on' => ($idx !== null && $idx > 1) ? [$idx - 1] : [],
                'expected' => $serialized['preview']['totals'] ?? [],
                'created' => $serialized['created'] ?? [],
                'last_error' => $plan->last_error,
                'actions' => self::packageActions($plan, $build),
                'plan' => $serialized,
            ];
        }

        usort($out, fn ($a, $b) => ((int) ($a['package_index'] ?? 999999)) <=> ((int) ($b['package_index'] ?? 999999)));

        return $out;
    }

    /**
     * Actions valid for a package in its current state (UI hint only).
     */
    public static function packageActions(\App\Models\AiPlan $plan, AiStudyBuild $build): array
    {
        $actions = ['view'];
        if ($plan->isObsolete() || $plan->isSuperseded()) {
            return $actions;
        }
        if ($plan->master_version === null || (int) $plan->master_version !== (int) $build->version) {
            return $actions;
        }
        $approved = $build->approved_version !== null && (int) $build->approved_version === (int) $build->version;
        switch ($plan->status) {
            case \App\Models\AiPlan::STATUS_PROPOSED:
                if ($approved) {
                    array_push($actions, 'confirm', 'execute', 'revise');
                }

                break;
            case \App\Models\AiPlan::STATUS_CONFIRMED:
            case \App\Models\AiPlan::STATUS_EXECUTING:
            case \App\Models\AiPlan::STATUS_FAILED:
                array_push($actions, 'execute');

                break;
            default:
                break;
        }

        return $actions;
    }

    /**
     * Targeted revision of ONE package: a new proposal scoped to the same
     * package (same book attach, same position). The old proposal becomes
     * obsolete (linked via superseded_by_plan_id); untouched independent
     * packages are left alone. Later non-terminal packages of the same
     * chain are reported as affected for review.
     */
    public static function revisePackage(\App\Models\AiPlan $plan, $user, string $feedback, ?string $rid = null): array
    {
        $feedback = trim($feedback);
        if ($feedback === '' || mb_strlen($feedback) > 2000) {
            return ['ok' => false, 'code' => 'invalid_feedback', 'error' => __('Describe the change you want (up to 2000 characters).')];
        }
        if ((int) $plan->user_id !== (int) $user->id) {
            return ['ok' => false, 'code' => 'not_found', 'error' => __('Package not found.')];
        }
        if ($plan->master_build_id === null) {
            return ['ok' => false, 'code' => 'not_a_package', 'error' => __('Only chain packages can be revised this way.')];
        }
        if (! in_array($plan->status, [\App\Models\AiPlan::STATUS_PROPOSED, \App\Models\AiPlan::STATUS_FAILED], true)) {
            return ['ok' => false, 'code' => 'already_executed', 'error' => __('Only unexecuted packages can be revised.')];
        }
        if ($plan->status === \App\Models\AiPlan::STATUS_FAILED && ! empty($plan->created_items)) {
            return ['ok' => false, 'code' => 'has_partial_data', 'error' => __('This package already created data — remove its items first, then propose a fresh package.')];
        }
        $build = AiStudyBuild::where('id', $plan->master_build_id)
            ->where('user_id', $user->id)
            ->first();
        if (! $build || $build->status !== AiStudyBuild::STATUS_BUILDING) {
            return ['ok' => false, 'code' => 'not_building', 'error' => __('This chain is no longer active.')];
        }
        if ($plan->master_version === null || (int) $plan->master_version !== (int) $build->version) {
            return ['ok' => false, 'code' => 'obsolete_package', 'error' => __('This package belongs to a retired master version.')];
        }
        if ($build->approved_version === null || (int) $build->approved_version !== (int) $build->version) {
            return ['ok' => false, 'code' => 'master_not_approved', 'error' => __('Approve the master structure first.')];
        }

        $providers = app(\App\Services\AiProviderService::class);
        $resolved = $providers->resolve($user);
        if (! $resolved) {
            return ['ok' => false, 'code' => 'no_provider', 'error' => __('No AI provider is configured.')];
        }
        try {
            $raw = self::requestJsonModel($providers, $resolved, self::packageRevisionPrompt(), 'Current package JSON: '.json_encode($plan->structure, JSON_UNESCAPED_UNICODE)."\n\nRequested change: ".$feedback);
            $args = self::decodeModelJson($raw);
        } catch (\RuntimeException $e) {
            AiLogger::error('study_build.pkg_revise_provider_failed', AiTrace::ids(null, $rid, $user->id, $build->conversation_id) + ['build_id' => $build->id, 'plan_id' => $plan->id, 'error' => mb_substr($e->getMessage(), 0, 300)]);

            return ['ok' => false, 'code' => 'provider_error', 'error' => $e->getMessage()];
        }

        $service = new AiToolService;
        $check = $service->validateCall('plan_propose', is_array($args) ? $args : [], $user);
        if (! ($check['ok'] ?? false)) {
            AiLogger::log('study_build.pkg_revise_invalid', AiTrace::ids(null, $rid, $user->id, $build->conversation_id) + ['build_id' => $build->id, 'plan_id' => $plan->id, 'error' => $check['error'] ?? null]);

            return ['ok' => false, 'code' => 'invalid_package', 'error' => $check['error'] ?? __('Invalid package.')];
        }
        if (($scopeErr = self::checkPackageScope($plan->structure, $check['resolved']['structure'], (int) $user->id)) !== null) {
            return ['ok' => false, 'code' => 'scope_changed', 'error' => $scopeErr];
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($plan, $user, $build, $check, $rid) {
            $lockedPlan = \App\Models\AiPlan::where('id', $plan->id)->lockForUpdate()->first();
            $lockedBuild = AiStudyBuild::where('id', $build->id)->lockForUpdate()->first();
            if (! $lockedPlan || ! $lockedBuild) {
                return ['ok' => false, 'code' => 'not_found', 'error' => __('Package not found.')];
            }
            if (! in_array($lockedPlan->status, [\App\Models\AiPlan::STATUS_PROPOSED, \App\Models\AiPlan::STATUS_FAILED], true)
                || (int) $lockedPlan->master_version !== (int) $lockedBuild->version
                || $lockedBuild->status !== AiStudyBuild::STATUS_BUILDING) {
                return ['ok' => false, 'code' => 'version_changed', 'error' => __('The package changed while revising — review and try again.')];
            }

            $controller = app(AiPlanController::class);
            $stored = $controller->storeResolvedPlan((int) $user->id, $lockedBuild->conversation_id, $check['resolved']);
            $new = $stored['plan'];
            $new->update([
                'master_build_id' => $lockedBuild->id,
                'master_version' => (int) $lockedBuild->version,
                'package_index' => $lockedPlan->package_index,
            ]);
            $lockedPlan->update([
                'status' => \App\Models\AiPlan::STATUS_OBSOLETE,
                'superseded_by_plan_id' => $new->id,
            ]);

            $known = array_map('intval', (array) ($lockedBuild->plan_ids ?? []));
            if (! in_array((int) $new->id, $known, true)) {
                $known[] = (int) $new->id;
            }
            $update = ['plan_ids' => array_values($known), 'last_error' => null];
            // The chain head moves only when the revised package WAS the head;
            // independent later packages keep pointing at their own head.
            if ((int) $lockedBuild->last_plan_id === (int) $lockedPlan->id) {
                $update['last_plan_id'] = $new->id;
            }
            $lockedBuild->update($update);

            $affected = \App\Models\AiPlan::where('user_id', $user->id)
                ->where('master_build_id', $lockedBuild->id)
                ->where('id', '!=', $new->id)
                ->whereNotNull('package_index')
                ->where('package_index', '>', (int) ($lockedPlan->package_index ?? 0))
                ->whereIn('status', [\App\Models\AiPlan::STATUS_PROPOSED, \App\Models\AiPlan::STATUS_CONFIRMED, \App\Models\AiPlan::STATUS_EXECUTING, \App\Models\AiPlan::STATUS_FAILED])
                ->orderBy('package_index')
                ->pluck('id')->all();

            AiLogger::log('study_build.pkg_revised', AiTrace::ids(null, $rid, $user->id, $lockedBuild->conversation_id) + ['build_id' => $lockedBuild->id, 'old_plan_id' => $lockedPlan->id, 'new_plan_id' => $new->id]);
            $freshBuild = $lockedBuild->fresh();

            return [
                'ok' => true,
                'plan' => $new->fresh(),
                'affected' => array_map('intval', $affected),
                'detail' => self::masterDetail($freshBuild),
                'packages' => self::packageList($freshBuild),
            ];
        });
    }

    private static function packageRevisionPrompt(): string
    {
        return 'You revise ONE executable package (a slice of a book build). Reply with JSON ONLY:'
            .' a plan_propose args object {"title":...,"project":{"name":...,"tasks":[...]},'
            .' "subprojects":[{"name":...,"tasks":[{"title":"...","subtasks":[{"title":"..."}]}]}]}.'
            .' Rules: change ONLY what the feedback asks; keep the same book attach'
            .' (same project_id when present — never invent a new book project); stay within'
            .' caps (<=3 subprojects, <=30 tasks, <=100 subtasks); keep titles verbatim except'
            .' requested changes; no extra keys, no markdown, no explanation.';
    }

    /**
     * Scope guard: a package revision must not silently switch books.
     * When the old attach target still exists, the revision must keep it;
     * when it is gone (deleted), rebuilding under the same name is the
     * honest recovery and is allowed. Returns the Persian reason or null
     * when the scope is acceptable.
     */
    private static function checkPackageScope(array $old, array $new, int $userId): ?string
    {
        $refOf = function (array $s): ?int {
            $id = $s['project']['project_id'] ?? $s['projects'][0]['project_id'] ?? null;

            return $id !== null ? (int) $id : null;
        };
        $oldRef = $refOf($old);
        if ($oldRef === null) {
            return null;
        }
        $stillThere = \App\Models\Project::where('id', $oldRef)->where('user_id', $userId)->exists();
        if ($stillThere && $refOf($new) !== $oldRef) {
            return 'محدوده بسته عوض شد (اتصال به پروژه دیگر) — اصلاح بسته نباید کتاب دیگری بسازد.';
        }

        return null;
    }
}
