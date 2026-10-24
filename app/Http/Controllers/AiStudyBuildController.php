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
     * Revise the master from free-text feedback. Produces version N+1 and
     * preserves N; stores nothing when the provider or validation fails,
     * so retry is a plain repeat of the same request.
     */
    public function revise(Request $request, AiStudyBuild $build)
    {
        abort_if($build->user_id !== Auth::id(), 403);

        $request->validate(['feedback' => 'required|string|min:1|max:2000']);
        $rid = AiLogger::newRequestId();

        $res = StudyBuildOrchestrator::reviseFromFeedback($build, Auth::user(), (string) $request->input('feedback'), $rid);
        if (! ($res['ok'] ?? false)) {
            $status = match ($res['code'] ?? null) {
                'provider_error' => 502,
                'version_changed' => 409,
                default => 422,
            };

            return response()->json(['ok' => false, 'code' => $res['code'] ?? 'error', 'error' => $res['error'] ?? __('Revision failed.'), 'master' => StudyBuildOrchestrator::masterDetail($build->fresh() ?? $build)], $status);
        }

        $fresh = $res['build'];
        $this->note($fresh, __('📝 ساختار کلان نسخه :version ساخته شد — بررسی و تأیید کنید.', ['version' => $fresh->version]));

        return response()->json([
            'ok' => true,
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
