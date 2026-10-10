<?php

namespace App\Http\Controllers;

use App\Models\AiMessage;
use App\Models\AiPlan;
use App\Services\AiLogger;
use App\Services\AiToolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AiPlanController extends Controller
{
    protected AiToolService $tools;

    public function __construct(AiToolService $tools)
    {
        $this->tools = $tools;
    }

    public function confirmStructure(AiPlan $plan)
    {
        abort_if($plan->user_id !== Auth::id(), 403);

        // Row lock: the status check and the proposed → confirmed flip must
        // be atomic, otherwise two concurrent clicks both confirm and both
        // append the "Structure approved" note.
        return \Illuminate\Support\Facades\DB::transaction(function () use ($plan) {
            /** @var AiPlan $locked */
            $locked = AiPlan::where('id', $plan->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->user_id !== Auth::id(), 403);

            if (in_array($locked->status, [AiPlan::STATUS_CONFIRMED, AiPlan::STATUS_EXECUTING, AiPlan::STATUS_DONE], true)) {
                return response()->json(['ok' => true, 'deduped' => true, 'plan' => $this->serialize($locked->fresh())]);
            }
            if (! $locked->isActionable()) {
                $this->expire($locked);
                AiLogger::log('plan.structure_expired', ['user_id' => Auth::id(), 'plan_id' => $locked->id, 'status' => $locked->status]);

                return response()->json(['ok' => false, 'error' => __('This plan has expired. Ask Lina to propose it again.')], 422);
            }

            $locked->status = AiPlan::STATUS_CONFIRMED;
            // Skip zero-count phases so the stepper starts at real work.
            $this->advancePastDone($locked);
            $locked->expires_at = now()->addMinutes(AiPlan::EXPIRY_MINUTES);
            $locked->save();

            Log::info('ai.plan.structure_confirmed', ['user_id' => Auth::id(), 'plan_id' => $locked->id]);
            AiLogger::log('plan.structure_confirmed', ['user_id' => Auth::id(), 'plan_id' => $locked->id, 'title' => $locked->title, 'note' => 'structure approved only — nothing created yet until confirm-phase']);
            $this->note($locked, __('📋 Structure approved (nothing created yet — run the phases below to build): :title', ['title' => $locked->title]));

            return response()->json(['ok' => true, 'plan' => $this->serialize($locked->fresh())]);
        });
    }

    public function confirmPhase(Request $request, AiPlan $plan)
    {
        abort_if($plan->user_id !== Auth::id(), 403);

        $request->validate([
            'phase' => 'nullable|integer|min:0|max:10',
            'run_all' => 'nullable|boolean',
        ]);

        // Row lock: the phase-pointer check and the phase execution must be
        // atomic, otherwise two concurrent run_all calls interleave phases.
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $plan) {
            /** @var AiPlan $locked */
            $locked = AiPlan::where('id', $plan->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->user_id !== Auth::id(), 403);

            if (! $locked->isActionable() || ! in_array($locked->status, [AiPlan::STATUS_CONFIRMED, AiPlan::STATUS_EXECUTING], true)) {
                if ($locked->status === AiPlan::STATUS_DONE) {
                    return response()->json(['ok' => true, 'deduped' => true, 'plan' => $this->serialize($locked->fresh())]);
                }
                $this->expire($locked);

                return response()->json(['ok' => false, 'error' => __('This plan has expired. Ask Lina to propose it again.')], 422);
            }

            // Phase index must match the server pointer: stale double-clicks
            // never execute the NEXT phase by accident.
            $wanted = $request->input('phase', $locked->current_phase);
            if ((int) $wanted !== (int) $locked->current_phase) {
                return response()->json(['ok' => true, 'deduped' => true, 'plan' => $this->serialize($locked->fresh())]);
            }

            $messages = [];
            $phasesRun = 0;
            do {
                $result = $this->tools->executePlanPhase($locked->fresh(), Auth::user());
                if (! ($result['ok'] ?? false)) {
                    \Illuminate\Support\Facades\Log::warning('ai.plan.phase_failed', ['user_id' => Auth::id(), 'plan_id' => $locked->id, 'error' => $result['message'] ?? null]);
                    AiLogger::error('plan.phase_failed', ['user_id' => Auth::id(), 'plan_id' => $locked->id, 'phase' => $locked->fresh()->current_phase, 'error' => $result['message'] ?? null, 'completed_phases' => $messages]);
                    // Partial failure is reported, never swallowed: what already
                    // ran stays (by design, no rollback) and is listed both in
                    // the response and in the conversation history.
                    if (! empty($messages)) {
                        $this->note($locked, '⚠ ' . __('Partial progress before failure: :details', ['details' => implode(' ', $messages)]));
                    }

                    return response()->json([
                        'ok' => false,
                        'code' => 'phase_failed',
                        'error' => $result['message'] ?? __('Phase failed.'),
                        'completed' => $messages,
                        'failed_phase' => $locked->fresh()->current_phase,
                        'plan' => $this->serialize($locked->fresh()),
                    ], 422);
                }
                $messages[] = $result['message'];
                $phasesRun++;
                $locked = $locked->fresh();
                // Auto-skip zero-count phases inside run_all too.
                $this->advancePastDone($locked);
                $locked->save();
                $locked = $locked->fresh();
            } while ($request->boolean('run_all') && $locked->status !== AiPlan::STATUS_DONE);

            \Illuminate\Support\Facades\Log::info('ai.plan.phase_executed', ['user_id' => Auth::id(), 'plan_id' => $locked->id]);
            AiLogger::log('plan.phase_executed', ['user_id' => Auth::id(), 'plan_id' => $locked->id, 'phases_run' => $phasesRun, 'run_all' => $request->boolean('run_all'), 'status' => $locked->status, 'messages' => implode(' ', $messages)]);
            $this->note($locked, '✅ ' . implode(' ', $messages));

            return response()->json(['ok' => true, 'plan' => $this->serialize($locked->fresh())]);
        });
    }

    public function cancel(AiPlan $plan)
    {
        abort_if($plan->user_id !== Auth::id(), 403);

        // Race-safe: only an actionable (non-terminal) plan can be cancelled.
        // A concurrent confirmStructure/confirmPhase that already flipped the
        // plan to confirmed/executing/done wins — the conditional update below
        // makes the loser a deduped no-op instead of clobbering the winner.
        return \Illuminate\Support\Facades\DB::transaction(function () use ($plan) {
            /** @var AiPlan|null $locked */
            $locked = AiPlan::where('id', $plan->id)->lockForUpdate()->first();
            if (! $locked || (int) $locked->user_id !== (int) Auth::id()) {
                abort(403);
            }
            if (in_array($locked->status, [AiPlan::STATUS_DONE, AiPlan::STATUS_CANCELLED], true)) {
                return response()->json(['ok' => true, 'deduped' => true, 'message' => __('Plan already resolved — nothing changed.')]);
            }
            if (! in_array($locked->status, [AiPlan::STATUS_PROPOSED, AiPlan::STATUS_CONFIRMED, AiPlan::STATUS_EXECUTING], true)) {
                return response()->json(['ok' => true, 'deduped' => true, 'message' => __('Plan already resolved — nothing changed.')]);
            }

            $locked->status = AiPlan::STATUS_CANCELLED;
            $locked->save();
            Log::info('ai.plan.cancelled', ['user_id' => Auth::id(), 'plan_id' => $locked->id]);
            AiLogger::log('plan.cancelled', ['user_id' => Auth::id(), 'plan_id' => $locked->id]);

            return response()->json(['ok' => true, 'message' => __('Plan cancelled — already-created items stay.')]);
        });
    }

    private function advancePastDone(AiPlan $plan): void
    {
        $phases = $plan->phases;
        while (isset($phases[$plan->current_phase]) && ($phases[$plan->current_phase]['status'] ?? null) === 'done') {
            $plan->current_phase++;
        }
        if ($plan->current_phase >= count($phases)) {
            $plan->status = AiPlan::STATUS_DONE;
            $plan->executed_at = now();
        }
    }

    private function expire(AiPlan $plan): void
    {
        if (in_array($plan->status, [AiPlan::STATUS_PROPOSED, AiPlan::STATUS_CONFIRMED, AiPlan::STATUS_EXECUTING], true)) {
            $plan->status = AiPlan::STATUS_EXPIRED;
            $plan->save();
        }
    }

    private function note(AiPlan $plan, string $text): void
    {
        if (! $plan->conversation_id) {
            return;
        }
        AiMessage::create([
            'conversation_id' => $plan->conversation_id,
            'role' => 'assistant',
            'content' => $text,
        ]);
        \App\Models\AiConversation::where('id', $plan->conversation_id)->touch();
    }

    public function serialize(AiPlan $plan): array
    {
        $structure = $plan->structure;
        // planRoots() unifies old single-tree and new multi-project shapes.
        $roots = $this->tools->planRoots($structure);
        $projectCount = count($roots);
        $subProjectCount = array_sum(array_map(fn ($r) => count($r['subprojects'] ?? []), $roots));
        $taskCount = array_sum(array_map(fn ($r) => count($r['tasks'] ?? [])
            + array_sum(array_map(fn ($s) => count($s['tasks'] ?? []), $r['subprojects'] ?? [])), $roots));
        $subCount = array_sum(array_map(fn ($r) => array_sum(array_map(fn ($t) => count($t['subtasks'] ?? []), $r['tasks'] ?? [])), $roots))
            + array_sum(array_map(
                fn ($r) => array_sum(array_map(
                    fn ($s) => array_sum(array_map(fn ($t) => count($t['subtasks'] ?? []), $s['tasks'] ?? [])),
                    $r['subprojects'] ?? []
                )),
                $roots
            ));
        $routineCount = count($structure['routines'] ?? []);
        $stepCount = array_sum(array_map(fn ($r) => count($r['steps'] ?? []), $structure['routines'] ?? []));
        $memberCount = array_sum(array_map(fn ($r) => count($r['members'] ?? []), $roots));

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'status' => $plan->status,
            'current_phase' => $plan->current_phase,
            'phases' => $plan->phases,
            'preview' => $this->tools->previewPlan([
                'title' => $plan->title,
                'structure' => $structure,
                'totals' => [
                    'projects' => $projectCount,
                    'subprojects' => $subProjectCount,
                    'tasks' => $taskCount,
                    'subtasks' => $subCount,
                    'routines' => $routineCount,
                    'steps' => $stepCount,
                    'reminders' => count($structure['reminders'] ?? []),
                    'notes' => count($structure['notes'] ?? []),
                    'members' => $memberCount,
                ],
            ]),
            'expires_at' => $plan->expires_at?->toIso8601String(),
        ];
    }
}
