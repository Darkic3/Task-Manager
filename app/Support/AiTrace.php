<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * AI execution trace contract.
 *
 * Two identifiers, two different meanings. Mixing them up is what made the
 * old logs unreconstructible:
 *
 *   run_id  — a LOGICAL AI execution: one user message, its routing, context,
 *             tool proposals, the-user's confirmation(s) and the resulting
 *             executions/undo/plan phases. Spans MANY HTTP requests and can
 *             last minutes (the user may confirm 10 minutes later). Generated
 *             once, at request.received, and preserved through retries,
 *             re-proposals after a clarification, and execution.
 *
 *   rid     — one HTTP request. A logical run contains at least two: the
 *             message request and the confirm request. Never use rid as the
 *             identity of a run.
 *
 * Every event carries: run_id, rid, user_id, conversation_id (when known),
 * action_id / plan_id (when relevant), event, status, occurred_at plus
 * stage-specific metrics. AiLogger injects occurred_at automatically.
 *
 * Secrets are never part of a trace (see AiLogger::sanitize).
 */
final class AiTrace
{
    /* ── Events ─────────────────────────────────────────────────────────── */

    /** Request entered the system (chat or stream). */
    public const REQUEST_RECEIVED = 'request.received';

    /** Terminal: the request finished, for any reason. `status` says which. */
    public const REQUEST_COMPLETED = 'request.completed';

    /** Terminal: the request could not be answered at all. */
    public const REQUEST_FAILED = 'request.failed';

    public const INTENT_ROUTED = 'intent.routed';
    public const CONTEXT_BUILT = 'context.built';
    public const TOOLS_ROUTED = 'tools.routed';

    /** A tool call became a pending action awaiting confirmation. */
    public const TOOL_PROPOSED = 'tool.proposed';

    /** The confirmation card was delivered to the client. */
    public const CONFIRMATION_REQUESTED = 'confirmation.requested';

    public const ACTION_EXECUTING = 'action.executing';
    public const ACTION_EXECUTED = 'action.executed';
    /**
     * Execution failed. Named `action.execute_failed` to match the event
     * this project already emits — one name per meaning, never two.
     */
    public const ACTION_FAILED = 'action.execute_failed';
    public const ACTION_REJECTED = 'action.rejected';
    public const ACTION_UNDONE = 'action.undone';

    public const PLAN_PROPOSED = 'plan.proposed';

    /**
     * Plan state transitions emit ONE event — `plan.updated` — with a
     * `transition` field naming the change (proposed→confirmed,
     * phase_executed, phase_failed, cancelled, expired). One event + one
     * discriminator, never a family of synonymous plan.* names.
     *
     * The run that proposed the plan is recoverable: createPlanFromToolCall
     * stores `_trace` inside the plan's structure JSON, so a later
     * confirm/phase/cancel request — a new HTTP request — attributes the
     * transition to that run.
     */
    public const PLAN_UPDATED = 'plan.updated';

    /**
     * One provider attempt failed. NON-terminal by design: a fallback may
     * still recover, so this must never be read as the request's outcome.
     * The outcome is only ever request.completed / request.failed.
     */
    public const PROVIDER_ATTEMPT_FAILED = 'provider.attempt_failed';

    /* ── Terminal request statuses ───────────────────────────────────────── */

    /** A normal assistant reply was produced. */
    public const OUTCOME_REPLIED = 'replied';

    /** At least one confirmation card reached the client. */
    public const OUTCOME_CONFIRMATION_REQUESTED = 'confirmation_requested';

    /** Reply produced but the model called no tool. */
    public const OUTCOME_NO_TOOLS = 'no_tools';

    /** No provider configured — offline read-only fallback answered. */
    public const OUTCOME_OFFLINE_FALLBACK = 'offline_fallback';

    /** Agent mode blocked (provider without function-calling). */
    public const OUTCOME_AGENT_BLOCKED = 'agent_blocked';

    /** Provider call(s) failed; a formatted error was shown instead. */
    public const OUTCOME_PROVIDER_ERROR = 'provider_error';

    /** Unexpected server error. */
    public const OUTCOME_SYSTEM_ERROR = 'system_error';

    /** The client vanished mid-stream. */
    public const OUTCOME_STREAM_INTERRUPTED = 'stream_interrupted';

    /** The provider closed the body without [DONE]. */
    public const OUTCOME_STREAM_TRUNCATED = 'stream_truncated';

    public const OUTCOME_INVALID_REQUEST = 'invalid_request';
    public const OUTCOME_FORBIDDEN = 'forbidden';

    /**
     * Build the identifier block shared by every event of one run.
     *
     * @param  string|null  $runId
     * @param  string|null  $rid
     */
    public static function ids(
        ?string $runId,
        ?string $rid,
        $userId = null,
        ?int $conversationId = null,
        ?int $actionId = null,
        ?int $planId = null
    ): array {
        return [
            'run_id' => $runId,
            'rid' => $rid,
            'user_id' => $userId !== null ? (int) $userId : null,
            'conversation_id' => $conversationId,
            'action_id' => $actionId,
            'plan_id' => $planId,
        ];
    }

    public static function newRunId(): string
    {
        return 'run_'.Str::lower(Str::random(12));
    }

    /** Milliseconds since $startedAt (as returned by microtime(true)). */
    public static function elapsedMs(float $startedAt): int
    {
        return (int) max(0, round((microtime(true) - $startedAt) * 1000));
    }
}
