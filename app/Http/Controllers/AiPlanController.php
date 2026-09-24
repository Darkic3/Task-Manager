<?php

namespace App\Http\Controllers;

use App\Models\AiMessage;
use App\Models\AiPlan;
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

            return response()->json(['ok' => false, 'error' => 'This plan has expired. Ask Lina to propose it again.'], 422);
        }

        $plan->status = AiPlan::STATUS_CONFIRMED;
        // Skip zero-count phases so the stepper starts at real work.
        $this->advancePastDone($plan);
        $plan->touchExpiry();
        $plan->save();

        Log::info('ai.plan.structure_confirmed', ['user_id' => Auth::id(), 'plan_id' => $plan->id]);
        $this->note($plan, '📋 Structure approved: ' . $plan->title);

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
        do {
            $result = $this->tools->executePlanPhase($plan->fresh(), Auth::user());
            if (! ($result['ok'] ?? false)) {
                Log::warning('ai.plan.phase_failed', ['user_id' => Auth::id(), 'plan_id' => $plan->id, 'error' => $result['message'] ?? null]);

                return response()->json(['ok' => false, 'error' => $result['message'] ?? 'Phase failed.', 'plan' => $this->serialize($plan->fresh())], 422);
            }
            $messages[] = $result['message'];
            $plan = $plan->fresh();
            // Auto-skip zero-count phases inside run_all too.
            $this->advancePastDone($plan);
            $plan->save();
            $plan = $plan->fresh();
        } while ($request->boolean('run_all') && $plan->status !== AiPlan::STATUS_DONE);

        Log::info('ai.plan.phase_executed', ['user_id' => Auth::id(), 'plan_id' => $plan->id]);
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
        $taskCount = count($structure['project']['tasks'] ?? [])
            + array_sum(array_map(fn ($s) => count($s['tasks'] ?? []), $structure['subprojects'] ?? []));
        $subCount = array_sum(array_map(fn ($t) => count($t['subtasks'] ?? []), $structure['project']['tasks'] ?? []))
            + array_sum(array_map(
                fn ($s) => array_sum(array_map(fn ($t) => count($t['subtasks'] ?? []), $s['tasks'] ?? [])),
                $structure['subprojects'] ?? []
            ));
        $routineCount = count($structure['routines'] ?? []);
        $stepCount = array_sum(array_map(fn ($r) => count($r['steps'] ?? []), $structure['routines'] ?? []));

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
                    'subprojects' => count($structure['subprojects'] ?? []),
                    'tasks' => $taskCount,
                    'subtasks' => $subCount,
                    'routines' => $routineCount,
                    'steps' => $stepCount,
                ],
            ]),
            'expires_at' => $plan->expires_at?->toIso8601String(),
        ];
    }
}
