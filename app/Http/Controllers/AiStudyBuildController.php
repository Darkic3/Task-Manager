<?php

namespace App\Http\Controllers;

use App\Models\AiMessage;
use App\Models\AiPlan;
use App\Models\AiStudyBuild;
use App\Services\AiLogger;
use App\Services\Study\StudyBuildOrchestrator;
use App\Support\AiTrace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * P1 Master Plan: versioned chain structure (show / approve / revise).
 *
 * The AiStudyBuild row is the master record. Approving version N is the
 * explicit gate that unlocks package execution (enforced by the confirm
 * gate in AiPlanController) — a bare flag would be meaningless because
 * the structure stays mutable through revisions.
 */
class AiStudyBuildController extends Controller
{
    /**
     * All chain builds of one conversation, oldest first, each with the
     * full recovery payload (master + packages + progress). The UI calls
     * this when a conversation is opened and rebuilds every master card
     * and the current head preview card purely from server records, so a
     * refresh/reconnect resumes exactly the stored stage.
     */
    public function forConversation(\App\Models\AiConversation $conversation)
    {
        abort_if($conversation->user_id !== Auth::id(), 403);

        $builds = AiStudyBuild::where('user_id', Auth::id())
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'ok' => true,
            'builds' => $builds->map(fn (AiStudyBuild $build) => [
                'master' => StudyBuildOrchestrator::masterDetail($build),
                'study_build' => $build->progress(),
                'packages' => StudyBuildOrchestrator::packageList($build),
            ])->all(),
        ]);
    }

    /**
     * Server state for a master structure + its packages (refresh-safe:
     * the UI rebuilds the master card and all package cards from this
     * after a page reload — never from browser memory).
     */
    public function show(AiStudyBuild $build)
    {
        abort_if($build->user_id !== Auth::id(), 403);

        return response()->json([
            'ok' => true,
            'master' => StudyBuildOrchestrator::masterDetail($build),
            'study_build' => $build->fresh()->progress(),
            'packages' => StudyBuildOrchestrator::packageList($build),
        ]);
    }

    /**
     * Approve a specific master version. Idempotent for repeats.
     */
    public function approve(Request $request, AiStudyBuild $build)
    {
        abort_if($build->user_id !== Auth::id(), 403);

        $request->validate(['version' => 'required|integer|min:1|max:100']);
        $rid = AiLogger::newRequestId();

        $res = StudyBuildOrchestrator::approveVersion($build, Auth::user(), (int) $request->input('version'), $rid);
        if (! ($res['ok'] ?? false)) {
            $status = ($res['code'] ?? null) === 'not_found' ? 404 : 422;
            if (($res['code'] ?? null) === 'superseded_version' || ($res['code'] ?? null) === 'unknown_version') {
                $status = 409;
            }

            return response()->json(['ok' => false, 'code' => $res['code'] ?? 'error', 'error' => $res['error'] ?? __('Approval failed.'), 'master' => StudyBuildOrchestrator::masterDetail($build->fresh() ?? $build)], $status);
        }

        $fresh = $res['build'];
        if (empty($res['deduped'])) {
            $this->note($fresh, __('✅ ساختار کلان نسخه :version تأیید شد — حالا بسته‌ها را یکی‌یکی تأیید و اجرا کنید.', ['version' => $fresh->version]));
        }

        return response()->json([
            'ok' => true,
            'deduped' => ! empty($res['deduped']),
            'master' => $res['detail'],
            'study_build' => $fresh->progress(),
            'packages' => StudyBuildOrchestrator::packageList($fresh),
        ]);
    }

    /**
     * Stage C: approve the package list of the current master version.
     * Idempotent for repeats.
     */
    public function approvePackages(Request $request, AiStudyBuild $build)
    {
        abort_if($build->user_id !== Auth::id(), 403);

        $request->validate(['version' => 'required|integer|min:1|max:100']);
        $rid = AiLogger::newRequestId();

        $res = StudyBuildOrchestrator::approvePackages($build, Auth::user(), (int) $request->input('version'), $rid);
        if (! ($res['ok'] ?? false)) {
            $status = ($res['code'] ?? null) === 'not_found' ? 404 : 422;
            if (($res['code'] ?? null) === 'superseded_version' || ($res['code'] ?? null) === 'unknown_version') {
                $status = 409;
            }

            return response()->json(['ok' => false, 'code' => $res['code'] ?? 'error', 'error' => $res['error'] ?? __('Approval failed.'), 'master' => StudyBuildOrchestrator::masterDetail($build->fresh() ?? $build)], $status);
        }

        $fresh = $res['build'];
        if (empty($res['deduped'])) {
            $this->note($fresh, __('📦 فهرست بسته‌ها (نسخه :version) تأیید شد — حالا بستهٔ اول را بررسی و تأیید کنید.', ['version' => $fresh->version]));
        }

        return response()->json([
            'ok' => true,
            'deduped' => ! empty($res['deduped']),
            'master' => $res['detail'],
            'study_build' => $fresh->progress(),
            'packages' => StudyBuildOrchestrator::packageList($fresh),
        ]);
    }

    /**
     * Revise the master from free-text feedback. Produces version N+1 and
     * preserves N; stores nothing when the provider or validation fails,
     * so retry is a plain repeat of the same request.
     */
    public function revise(Request $request, AiStudyBuild $build)
    {
        abort_if($build->user_id !== Auth::id(), 403);

        $request->validate(['feedback' => 'required|string|min:1|max:2000']);
        $rid = AiLogger::newRequestId();
        $t0 = microtime(true);
        // Observable from the second the request lands: while the UI shows
        // its spinner, tailing the ai log for this rid shows request →
        // fast-path/model → done with per-stage latency.
        AiLogger::log('study_build.revise_request', AiTrace::ids(null, $rid, Auth::id(), $build->conversation_id) + [
            'build_id' => $build->id,
            'feedback_len' => mb_strlen((string) $request->input('feedback')),
            'feedback_head' => mb_substr(trim((string) $request->input('feedback')), 0, 120),
        ]);

        // A lock-order deadlock with a concurrent confirm is resolved by
        // the database; it rolls everything back, so reporting it as a
        // concurrent modification (retryable 409) is honest and safe.
        try {
            $res = StudyBuildOrchestrator::reviseFromFeedback($build, Auth::user(), (string) $request->input('feedback'), $rid);
        } catch (\Illuminate\Database\QueryException $e) {
            $conflict = in_array($e->errorInfo[1] ?? null, [1205, 1213, 5], true)
                || str_contains(strtolower($e->getMessage()), 'deadlock');
            AiLogger::error('study_build.revise_db_failed', AiTrace::ids(null, $rid, Auth::id(), $build->conversation_id) + ['build_id' => $build->id, 'conflict' => $conflict, 'error' => mb_substr($e->getMessage(), 0, 200)]);

            $ms = (int) round((microtime(true) - $t0) * 1000);
            AiLogger::error('study_build.revise_done', AiTrace::ids(null, $rid, Auth::id(), $build->conversation_id) + ['build_id' => $build->id, 'ok' => false, 'code' => $conflict ? 'version_changed' : 'error', 'elapsed_ms' => $ms]);

            return response()->json([
                'ok' => false,
                'rid' => $rid,
                'code' => $conflict ? 'version_changed' : 'error',
                'error' => $conflict
                    ? __('The structure changed while revising — review the current version and try again.')
                    : 'خطا در اصلاح ساختار — لطفاً دوباره تلاش کنید.',
                'master' => StudyBuildOrchestrator::masterDetail($build->fresh() ?? $build),
            ], $conflict ? 409 : 500);
        }
        if (! ($res['ok'] ?? false)) {
            $status = match ($res['code'] ?? null) {
                'provider_error' => 502,
                'version_changed' => 409,
                default => 422,
            };
            $ms = (int) round((microtime(true) - $t0) * 1000);
            AiLogger::log('study_build.revise_done', AiTrace::ids(null, $rid, Auth::id(), $build->conversation_id) + ['build_id' => $build->id, 'ok' => false, 'code' => $res['code'] ?? 'error', 'elapsed_ms' => $ms]);

            return response()->json(['ok' => false, 'rid' => $rid, 'code' => $res['code'] ?? 'error', 'error' => $res['error'] ?? __('Revision failed.'), 'master' => StudyBuildOrchestrator::masterDetail($build->fresh() ?? $build)], $status);
        }

        $fresh = $res['build'];
        $this->note($fresh, __('📝 ساختار کلان نسخه :version ساخته شد — بررسی و تأیید کنید.', ['version' => $fresh->version]));
        $ms = (int) round((microtime(true) - $t0) * 1000);
        AiLogger::log('study_build.revise_done', AiTrace::ids(null, $rid, Auth::id(), $build->conversation_id) + ['build_id' => $fresh->id, 'ok' => true, 'version' => $fresh->version, 'fast_path' => ($res['fast_path'] ?? false), 'elapsed_ms' => $ms]);

        return response()->json([
            'ok' => true,
            'rid' => $rid,
            'master' => $res['detail'],
            'study_build' => $fresh->progress(),
            'packages' => StudyBuildOrchestrator::packageList($fresh),
            'plan' => app(AiPlanController::class)->serialize($res['plan']),
        ]);
    }

    private function note(AiStudyBuild $build, string $text): void
    {
        if (! $build->conversation_id) {
            return;
        }
        AiMessage::create([
            'conversation_id' => $build->conversation_id,
            'role' => 'assistant',
            'content' => $text,
        ]);
        \App\Models\AiConversation::where('id', $build->conversation_id)->touch();
    }
}
