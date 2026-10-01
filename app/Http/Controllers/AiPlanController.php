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

        if (in_array($plan->status, [AiPlan::STATUS_CONFIRMED, AiPlan::STATUS_EXECUTING, AiPlan::STATUS_DONE], true)) {
            return response()->json(['ok' => true, 'deduped' => true, 'plan' => $this->serialize($plan->fresh())]);
        }
        if (! $plan->isActionable()) {
            $this->expire($plan);
            AiLogger::log('plan.structure_expired', ['user_id' => Auth::id(), 'plan_id' => $plan->id, 'status' => $plan->status]);

            return response()->json(['ok' => false, 'error' => 'This plan has expired. Ask Lina to propose it again.'], 422);
        }

        $plan->status = AiPlan::STATUS_CONFIRMED;
        // Skip zero-count phases so the stepper starts at real work.
        $this->advancePastDone($plan);
        $plan->touchExpiry();
        $plan->save();

        Log::info('ai.plan.structure_confirmed', ['user_id' => Auth::id(), 'plan_id' => $plan->id]);
        AiLogger::log('plan.structure_confirmed', ['user_id' => Auth::id(), 'plan_id' => $plan->id, 'title' => $plan->title, 'note' => 'structure approved only — nothing created yet until confirm-phase']);
        $this->note($plan, '📋 Structure approved (nothing created yet — run the phases below to build): ' . $plan->title);

        return response()->json(['ok' => true, 'plan' => $this->serialize($plan->fresh())]);
    }

    public function confirmPhase(Request $request, AiPlan $plan)
    {
        abort_if($plan->user_id !== Auth::id(), 403);

        $request->validate([
            'phase' => 'nullable|integer|min:0|max:10',
            'run_all' => 'nullable|boolean',
        ]);

        if (! $plan->isActionable() || ! in_array($plan->status, [AiPlan::STATUS_CONFIRMED, AiPlan::STATUS_EXECUTING], true)) {
            if ($plan->status === AiPlan::STATUS_DONE) {
                return response()->json(['ok' => true, 'deduped' => true, 'plan' => $this->serialize($plan->fresh())]);
            }
            $this->expire($plan);

            return response()->json(['ok' => false, 'error' => 'This plan has expired. Ask Lina to propose it again.'], 422);
        }

        // Phase index must match the server pointer: stale double-clicks
        // never execute the NEXT phase by accident.
        $wanted = $request->input('phase', $plan->current_phase);
        if ((int) $wanted !== (int) $plan->current_phase) {
            return response()->json(['ok' => true, 'deduped' => true, 'plan' => $this->serialize($plan->fresh())]);
        }

        $messages = [];
        $phasesRun = 0;
        do {
            $result = $this->tools->executePlanPhase($plan->fresh(), Auth::user());
            if (! ($result['ok'] ?? false)) {
                Log::warning('ai.plan.phase_failed', ['user_id' => Auth::id(), 'plan_id' => $plan->id, 'error' => $result['message'] ?? null]);
                AiLogger::error('plan.phase_failed', ['user_id' => Auth::id(), 'plan_id' => $plan->id, 'phase' => $plan->fresh()->current_phase, 'error' => $result['message'] ?? null]);

                return response()->json(['ok' => false, 'error' => $result['message'] ?? 'Phase failed.', 'plan' => $this->serialize($plan->fresh())], 422);
            }
            $messages[] = $result['message'];
            $phasesRun++;
            $plan = $plan->fresh();
            // Auto-skip zero-count phases inside run_all too.
            $this->advancePastDone($plan);
            $plan->save();
            $plan = $plan->fresh();
        } while ($request->boolean('run_all') && $plan->status !== AiPlan::STATUS_DONE);

        Log::info('ai.plan.phase_executed', ['user_id' => Auth::id(), 'plan_id' => $plan->id]);
        AiLogger::log('plan.phase_executed', ['user_id' => Auth::id(), 'plan_id' => $plan->id, 'phases_run' => $phasesRun, 'run_all' => $request->boolean('run_all'), 'status' => $plan->status, 'messages' => implode(' ', $messages)]);
        $this->note($plan, '✅ ' . implode(' ', $messages));

        return response()->json(['ok' => true, 'plan' => $this->serialize($plan->fresh())]);
    }

    public function cancel(AiPlan $plan)
    {
        abort_if($plan->user_id !== Auth::id(), 403);

        if (! in_array($plan->status, [AiPlan::STATUS_DONE, AiPlan::STATUS_CANCELLED], true)) {
            $plan->status = AiPlan::STATUS_CANCELLED;
            $plan->save();
            Log::info('ai.plan.cancelled', ['user_id' => Auth::id(), 'plan_id' => $plan->id]);
            AiLogger::log('plan.cancelled', ['user_id' => Auth::id(), 'plan_id' => $plan->id]);
        }

        return response()->json(['ok' => true, 'message' => 'Plan cancelled — already-created items stay.']);
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
