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

        $build = AiStudyBuild::create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'title' => (string) $outline['book_title'],
            'outline_json' => $outline,
            'chunks_json' => $chunks,
            'plan_ids' => [],
            'total_chunks' => count($chunks),
            'done_chunks' => 0,
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
}
