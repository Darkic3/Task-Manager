<?php

namespace App\Http\Controllers;

use App\Models\AiMessage;
use App\Models\AiPlan;
use App\Models\AiStudyBuild;
use App\Services\AiLogger;
use App\Services\AiToolService;
use App\Services\Study\StudyBuildOrchestrator;
use App\Support\AiTrace;
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

    /**
     * Store an already-validated plan_propose resolution as an AiPlan.
     * Shared by the chat path and the study-build orchestrator so both get
     * the same idempotency-key derivation and race-safe insert.
     *
     * @return array{plan: AiPlan, deduped: bool, race_reused: bool, rotations: int}
     */
    public function storeResolvedPlan(int $userId, $conversationId, array $resolved): array
    {
        $pipeline = \App\Services\AiTooling\ToolPipeline::class;

        // Stable key from the VALIDATED title + structure: retries,
        // reconnects and double-sends derive the identical key.
        $key = $pipeline::derivePlanKey($userId, $conversationId, $resolved['title'], $resolved['structure']);

        // Retry/reconnect safe: reuse the live plan instead of stacking one.
        $duplicate = $pipeline::findLivePlanByKey($userId, $key);
        if ($duplicate) {
            return ['plan' => $duplicate, 'deduped' => true, 'race_reused' => false, 'rotations' => 0];
        }

        $attrs = [
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'title' => $resolved['title'],
            // Deliberately NO trace key inside `structure`: the plan
            // idempotency key is derived from title + structure
            // (ToolPipeline::derivePlanKey), so mutating it would break
            // dedupe. Plans correlate through `plan_id` instead.
            'structure' => $resolved['structure'],
            'phases' => $this->tools->buildPlanPhases($resolved['structure']),
            'status' => AiPlan::STATUS_PROPOSED,
            'current_phase' => 0,
            'expires_at' => now()->addMinutes(AiPlan::EXPIRY_MINUTES),
            'idempotency_key' => $key,
        ];

        $plan = null;
        $raceReused = false;
        $rotations = 0;
        for ($attempt = 0; $attempt < 3 && $plan === null; $attempt++) {
            try {
                $plan = AiPlan::create($attrs);
            } catch (\Illuminate\Database\QueryException $e) {
                if (! str_contains(strtolower($e->getMessage()), 'duplicate')) {
                    throw $e;
                }
                $decision = $pipeline::resolvePlanKeyCollision($userId, $attrs['idempotency_key']);
                if (isset($decision['reuse'])) {
                    $plan = $decision['reuse'];
                    $raceReused = true;
                } else {
                    $attrs['idempotency_key'] = $decision['key'];
                    $rotations++;
                }
            }
        }
        if ($plan === null) {
            $attrs['idempotency_key'] .= '_'.bin2hex(random_bytes(8));
            $plan = AiPlan::create($attrs);
        }

        return ['plan' => $plan, 'deduped' => $raceReused, 'race_reused' => $raceReused, 'rotations' => $rotations];
    }

    /**
     * Trace ids for a plan event.
     *
     * Deliberately no run_id here: the plan idempotency key is derived from
     * title + structure (ToolPipeline::derivePlanKey), so any stored trace
     * inside `structure` would break dedupe — and a new column was not
     * justified. Plans therefore correlate by `plan_id` to
     * `plan.proposed`, which carries the run that produced them.
     */
    private function traceFor(AiPlan $plan, ?string $rid = null): array
    {
        return AiTrace::ids(
            null,
            $rid,
            $plan->user_id,
            $plan->conversation_id,
            null,
            $plan->id
        );
    }

    public function confirmStructure(AiPlan $plan)
    {
        abort_if($plan->user_id !== Auth::id(), 403);

        // Row lock: the status check and the proposed → confirmed flip must
        // be atomic, otherwise two concurrent clicks both confirm and both
        // append the "Structure approved" note.
        // One rid per HTTP request.
        $rid = AiLogger::newRequestId();

        return \Illuminate\Support\Facades\DB::transaction(function () use ($plan, $rid) {
            /** @var AiPlan $locked */
            $locked = AiPlan::where('id', $plan->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->user_id !== Auth::id(), 403);

            if (in_array($locked->status, [AiPlan::STATUS_CONFIRMED, AiPlan::STATUS_EXECUTING, AiPlan::STATUS_DONE], true)) {
                return response()->json(['ok' => true, 'deduped' => true, 'plan' => $this->serialize($locked->fresh())]);
            }
            if ($locked->isSuperseded()) {
                AiLogger::error('plan.updated', $this->traceFor($locked, $rid) + ['transition' => 'superseded_confirm_blocked', 'status' => $locked->status, 'replaced_by' => $locked->superseded_by_plan_id]);

                return response()->json(['ok' => false, 'code' => 'superseded', 'replaced_by' => $locked->superseded_by_plan_id, 'error' => __('This plan was replaced by a newer plan.')], 422);
            }
            if (! $locked->isActionable()) {
                $this->expire($locked);
                AiLogger::log('plan.structure_expired', $this->traceFor($locked, $rid) + ['status' => $locked->status]);

                return response()->json(['ok' => false, 'error' => __('This plan has expired. Ask Lina to propose it again.')], 422);
            }

            $locked->status = AiPlan::STATUS_CONFIRMED;
            // Skip zero-count phases so the stepper starts at real work.
            $this->advancePastDone($locked);
            $locked->expires_at = now()->addMinutes(AiPlan::EXPIRY_MINUTES);
            $locked->save();

            Log::info('ai.plan.structure_confirmed', ['user_id' => Auth::id(), 'plan_id' => $locked->id]);
            AiLogger::log('plan.updated', $this->traceFor($locked, $rid) + ['transition' => 'proposed→confirmed', 'title' => $locked->title, 'status' => $locked->status, 'note' => 'structure approved only — nothing created yet until confirm-phase']);
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
        // One rid per HTTP request.
        $rid = AiLogger::newRequestId();

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $plan, $rid) {
            /** @var AiPlan $locked */
            $locked = AiPlan::where('id', $plan->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->user_id !== Auth::id(), 403);

            if (! $locked->isActionable() || ! in_array($locked->status, [AiPlan::STATUS_CONFIRMED, AiPlan::STATUS_EXECUTING], true)) {
                if ($locked->status === AiPlan::STATUS_DONE) {
                    $out = ['ok' => true, 'deduped' => true, 'plan' => $this->serialize($locked->fresh())];
                    // Retry-safe: a lost confirm-phase response still surfaces the
                    // already-proposed next chunk instead of stalling the chain.
                    $pending = StudyBuildOrchestrator::pendingNextPlan($locked->fresh(), Auth::user());
                    if ($pending) {
                        $out['next_plan'] = $this->serialize($pending);
                        $chain = AiStudyBuild::where('user_id', Auth::id())
                            ->where('last_plan_id', $pending->id)->first();
                        if ($chain) {
                            $out['study_build'] = $chain->progress();
                            $out['chain_report'] = $this->chainReport($chain->fresh());
                        }
                    } else {
                        // Recovery: the auto-advance after DONE may never have run
                        // (lost response, manual 📦 tap on a stale card, stalled
                        // first attempt). Rebuild the next chunk from the stored
                        // chain — never by re-sending the request to the model.
                        // advanceIfPlanDone is idempotent here: once it succeeds,
                        // last_plan_id moves to the new head, so a repeated
                        // recovery on the same DONE plan returns null.
                        $recovered = StudyBuildOrchestrator::advanceIfPlanDone($locked->fresh(), Auth::user(), $rid);
                        if ($recovered !== null) {
                            $out['study_build'] = $recovered['build']->progress();
                            $out['chain_report'] = $this->chainReport($recovered['build']->fresh());
                            if (! empty($recovered['error'])) {
                                $out['next_error'] = $recovered['error'];
                                $this->note($locked, '⚠ '.$recovered['error']);
                            } elseif (! empty($recovered['next_plan'])) {
                                $out['next_plan'] = $this->serialize($recovered['next_plan']);
                                $this->note(
                                    $locked,
                                    __('📦 بسته :done از :total ساخته شد — بسته بعدی آماده تأیید است (:title)', [
                                        'done' => $recovered['build']->done_chunks,
                                        'total' => $recovered['build']->total_chunks,
                                        'title' => $recovered['next_plan']->title,
                                    ])
                                );
                            } elseif (! empty($recovered['finished'])) {
                                $this->note($locked, __('🎉 زنجیره ساخت کتاب کامل شد (:total بسته).', ['total' => $recovered['build']->total_chunks]));
                            }
                        }
                    }

                    return response()->json($out);
                }
                if ($locked->status === AiPlan::STATUS_PROPOSED && ! $locked->isExpired()) {
                    // Unconfirmed but live structure: refuse to run, but NEVER
                    // expire the draft as a side effect — the user can still
                    // confirm the structure and then execute. Expiring here
                    // destroyed a valid proposal after one premature call.
                    // (A genuinely expired draft still falls through to the
                    // expire path below.)
                    AiLogger::log('plan.updated', $this->traceFor($locked, $rid) + ['transition' => 'unconfirmed_execute_blocked', 'status' => $locked->status]);

                    return response()->json(['ok' => false, 'code' => 'not_confirmed', 'error' => __('Confirm the structure first — nothing is built until you approve it.'), 'plan' => $this->serialize($locked->fresh())], 422);
                }
                if ($locked->isSuperseded()) {
                    AiLogger::error('plan.updated', $this->traceFor($locked, $rid) + ['transition' => 'superseded_phase_blocked', 'status' => $locked->status, 'replaced_by' => $locked->superseded_by_plan_id]);

                    return response()->json(['ok' => false, 'code' => 'superseded', 'replaced_by' => $locked->superseded_by_plan_id, 'error' => __('This plan was replaced by a newer plan.')], 422);
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
                    AiLogger::error('plan.updated', $this->traceFor($locked, $rid) + ['transition' => 'phase_failed', 'phase' => $locked->fresh()->current_phase, 'status' => 'failed', 'error' => $result['message'] ?? null, 'completed_phases' => $messages]);
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

            AiLogger::log('plan.updated', $this->traceFor($locked, $rid) + ['transition' => 'phase_executed', 'phases_run' => $phasesRun, 'run_all' => $request->boolean('run_all'), 'status' => $locked->status, 'messages' => implode(' ', $messages)]);
            $this->note($locked, '✅ ' . implode(' ', $messages));

            $out = ['ok' => true, 'plan' => $this->serialize($locked->fresh())];

            // Study chain (Model B): a finished chunk head auto-proposes the
            // next chunk attached to the real book project id. The user still
            // confirms every chunk card before anything is built.
            $advance = StudyBuildOrchestrator::advanceIfPlanDone($locked->fresh(), Auth::user(), $rid);
            if ($advance !== null) {
                $out['study_build'] = $advance['build']->progress();
                $out['chain_report'] = $this->chainReport($advance['build']->fresh());
                if (! empty($advance['error'])) {
                    $out['next_error'] = $advance['error'];
                    $this->note($locked, '⚠ '.$advance['error']);
                } elseif (! empty($advance['next_plan'])) {
                    $out['next_plan'] = $this->serialize($advance['next_plan']);
                    $chain = $advance['build'];
                    $this->note(
                        $locked,
                        __('📦 بسته :done از :total ساخته شد — بسته بعدی آماده تأیید است (:title)', [
                            'done' => $chain->done_chunks,
                            'total' => $chain->total_chunks,
                            'title' => $advance['next_plan']->title,
                        ])
                    );
                } elseif (! empty($advance['finished'])) {
                    $this->note($locked, __('🎉 زنجیره ساخت کتاب کامل شد (:total بسته).', ['total' => $advance['build']->total_chunks]));
                }
            }

            return response()->json($out);
        });
    }

    /**
     * Delete everything this plan created (reverse dependency order).
     * Unlike cancel (which leaves created items), rollback removes the
     * plan's own projects/tasks/reminders/notes and soft-deletes its
     * routines. Projects that gained non-plan content afterwards are kept
     * and reported. Attached pre-existing records are never touched.
     */
    public function rollback(AiPlan $plan)
    {
        abort_if($plan->user_id !== Auth::id(), 403);

        $rid = AiLogger::newRequestId();

        return \Illuminate\Support\Facades\DB::transaction(function () use ($plan, $rid) {
            /** @var AiPlan $locked */
            $locked = AiPlan::where('id', $plan->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->user_id !== Auth::id(), 403);

            if (! in_array($locked->status, [AiPlan::STATUS_CONFIRMED, AiPlan::STATUS_EXECUTING, AiPlan::STATUS_DONE], true)) {
                if ($locked->isSuperseded()) {
                    return response()->json(['ok' => false, 'code' => 'superseded', 'error' => __('This plan was replaced by a newer plan.')], 422);
                }

                return response()->json(['ok' => false, 'error' => __('Nothing was built yet — there is nothing to roll back.')], 422);
            }

            $result = $this->tools->rollbackPlan($locked->fresh(), Auth::user());
            if (! ($result['ok'] ?? false)) {
                return response()->json(['ok' => false, 'error' => $result['message'] ?? __('Rollback failed.')], 422);
            }

            AiLogger::log('plan.updated', $this->traceFor($locked, $rid) + ['transition' => 'rolled_back', 'status' => $locked->fresh()->status, 'deleted' => $result['deleted'] ?? [], 'kept' => $result['kept'] ?? []]);
            $this->note($locked, $result['message']);

            return response()->json(['ok' => true, 'message' => $result['message'], 'deleted' => $result['deleted'] ?? [], 'kept' => $result['kept'] ?? [], 'plan' => $this->serialize($locked->fresh())]);
        });
    }

    public function cancel(Request $request, AiPlan $plan)
    {        abort_if($plan->user_id !== Auth::id(), 403);

        $request->validate(['replaced_by_plan_id' => 'nullable|integer|min:1']);

        // Race-safe: only an actionable (non-terminal) plan can be cancelled.
        // A concurrent confirmStructure/confirmPhase that already flipped the
        // plan to confirmed/executing/done wins — the conditional update below
        // makes the loser a deduped no-op instead of clobbering the winner.
        // One rid per HTTP request.
        $rid = AiLogger::newRequestId();

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $plan, $rid) {
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

            // Explicit replacement only: the old plan is retired only by the
            // user's own decision in this call, naming a live plan they own
            // as the replacement — never automatically, never implicitly.
            $replacementId = $request->input('replaced_by_plan_id');
            $replacement = null;
            if ($replacementId) {
                if ((int) $replacementId === (int) $locked->id) {
                    return response()->json(['ok' => false, 'code' => 'invalid_replacement', 'error' => __('A plan cannot replace itself.')], 422);
                }
                $replacement = AiPlan::where('id', (int) $replacementId)->where('user_id', Auth::id())->first();
                if (! $replacement || ! $replacement->isActionable()) {
                    return response()->json(['ok' => false, 'code' => 'invalid_replacement', 'error' => __('The replacement plan is not available.')], 422);
                }
                $locked->superseded_by_plan_id = $replacement->id;
            }

            $locked->status = AiPlan::STATUS_CANCELLED;
            $locked->save();
            Log::info('ai.plan.cancelled', ['user_id' => Auth::id(), 'plan_id' => $locked->id]);
            AiLogger::log('plan.updated', $this->traceFor($locked, $rid) + ['transition' => $replacement ? 'superseded' : 'cancelled', 'status' => $locked->status, 'replaced_by' => $replacement?->id]);

            $out = ['ok' => true, 'message' => $replacement
                ? __('Plan replaced — already-created items stay.')
                : __('Plan cancelled — already-created items stay.')];

            // Cancelling a chain head stops the whole chain (already-built
            // chunks stay — same rule as single plans).
            $chain = StudyBuildOrchestrator::cancelForPlan($locked->fresh(), Auth::user(), $rid);
            if ($chain) {
                $out['study_build'] = $chain->progress();
                $this->note($locked, __('⛔ زنجیره ساخت کتاب متوقف شد (بسته‌های ساخته‌شده می‌مانند).'));
            }

            return response()->json($out);
        });
    }

    /**
     * Per-package chain summary built ONLY from server records (ai_plans
     * rows + created_items ledgers) — never from model output. Lets the UI
     * render an honest final report: which packages succeeded, which were
     * cancelled/expired, and which are still awaiting confirmation.
     */
    private function chainReport(AiStudyBuild $build): array
    {
        $ids = array_values(array_map('intval', (array) ($build->plan_ids ?? [])));
        $rows = AiPlan::whereIn('id', $ids)->get()->keyBy('id');
        $items = [];
        foreach ($ids as $id) {
            /** @var AiPlan|null $plan */
            $plan = $rows->get($id);
            if (! $plan) {
                $items[] = ['id' => $id, 'status' => 'missing', 'title' => null, 'created' => []];
                continue;
            }
            $status = $plan->status;
            if ($status === AiPlan::STATUS_CANCELLED && $plan->superseded_by_plan_id) {
                $status = 'superseded';
            }
            $items[] = [
                'id' => $plan->id,
                'status' => $status,
                'title' => $plan->title,
                'is_head' => (int) $build->last_plan_id === (int) $plan->id,
                'created' => [
                    'projects' => count((array) ($plan->created_items['projects'] ?? [])),
                    'tasks' => count((array) ($plan->created_items['tasks'] ?? [])),
                    'reminders' => count((array) ($plan->created_items['reminders'] ?? [])),
                    'notes' => count((array) ($plan->created_items['notes'] ?? [])),
                    'routines' => count((array) ($plan->created_items['routines'] ?? [])),
                ],
            ];
        }

        return [
            'build_id' => $build->id,
            'status' => $build->status,
            'done_chunks' => $build->done_chunks,
            'total_chunks' => $build->total_chunks,
            'items' => $items,
        ];
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

    /**
     * Created record ids (+ project names) for chunk continuation prompts.
     * Projects phase runs first, so projects[0] is the book/root node.
     */
    private function createdIds(AiPlan $plan): array
    {
        $items = $plan->created_items ?? [];
        $projectIds = array_values(array_map('intval', (array) ($items['projects'] ?? [])));
        $names = [];
        if ($projectIds !== []) {
            $names = \App\Models\Project::where('user_id', $plan->user_id)
                ->whereIn('id', $projectIds)
                ->pluck('name', 'id')
                ->all();
        }

        return [
            'projects' => array_map(
                fn ($id) => ['id' => $id, 'name' => $names[$id] ?? null],
                $projectIds
            ),
        ];
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
        $weightOf = fn ($t) => isset($t['weight']) && is_numeric($t['weight']) ? (float) $t['weight'] : 0.0;
        $pagesOfTasks = function ($tasks) use ($weightOf): float {
            $sum = 0.0;
            foreach ((array) $tasks as $t) {
                $sum += $weightOf(is_array($t) ? $t : []);
                foreach ((is_array($t) ? ($t['subtasks'] ?? []) : []) as $s) {
                    $sum += $weightOf(is_array($s) ? $s : []);
                }
            }

            return $sum;
        };
        $pagesSum = 0.0;
        $goalCount = 0;
        foreach ($roots as $r) {
            if (! empty($r['daily_goal_value'])) {
                $goalCount++;
            }
            $pagesSum += $pagesOfTasks($r['tasks'] ?? []);
            foreach ($r['subprojects'] ?? [] as $s) {
                if (! empty($s['daily_goal_value'])) {
                    $goalCount++;
                }
                $pagesSum += $pagesOfTasks($s['tasks'] ?? []);
            }
        }

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
                    'pages' => $pagesSum > 0 ? round($pagesSum, 1) : 0,
                    'goals' => $goalCount,
                    'routines' => $routineCount,
                    'steps' => $stepCount,
                    'reminders' => count($structure['reminders'] ?? []),
                    'notes' => count($structure['notes'] ?? []),
                    'members' => $memberCount,
                ],
            ]),
            'can_rollback' => ! empty($plan->created_items) && (
                ! empty($plan->created_items['projects'])
                || ! empty($plan->created_items['tasks'])
                || ! empty($plan->created_items['reminders'])
                || ! empty($plan->created_items['notes'])
                || ! empty($plan->created_items['routines'])
            ),
            // What was ACTUALLY built (vs proposed totals) — the done card
            // renders this so an empty build can never claim success.
            'created' => [
                'projects' => count((array) ($plan->created_items['projects'] ?? [])),
                'tasks' => count((array) ($plan->created_items['tasks'] ?? [])),
                'reminders' => count((array) ($plan->created_items['reminders'] ?? [])),
                'notes' => count((array) ($plan->created_items['notes'] ?? [])),
                'routines' => count((array) ($plan->created_items['routines'] ?? [])),
            ],
            // Concrete ids + names for chunk continuation ("attach chunk 2
            // to book #25") so the next prompt carries the target id and the
            // model skips the search detour. Own records only.
            'created_ids' => $this->createdIds($plan),
            'expires_at' => $plan->expires_at?->toIso8601String(),
        ];
    }
}
