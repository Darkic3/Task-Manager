<?php

namespace App\Services\AiTooling;

use App\Models\AiPendingAction;
use App\Models\ChecklistItem;
use App\Models\Note;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\Routine;
use App\Models\Task;
use App\Services\AiLogger;
use App\Services\AiSecurity;
use App\Services\AiToolService;

/**
 * Shared server-side validation pipeline for every Lina tool call.
 *
 * Flow per call:
 *   Tool Call → Schema → Normalization → Security → Business →
 *   Ownership → State → Confirmation Policy → Execute → Result
 *
 * Security checks live in AiSecurity and are invoked as their OWN stage —
 * never mixed into validation logic; both run on every proposal and again
 * on every execution (state may have changed in between).
 */
final class ToolPipeline
{
    /** Max tool calls accepted from a single model turn. */
    public const MAX_TOOL_CALLS_PER_TURN = 20;

    /** Identical (tool + args) repeats allowed per turn before loop cut. */
    public const MAX_IDENTICAL_PER_TURN = 3;

    /** Grand-total cap across all plan_propose items (dimensions capped in business). */
    public const MAX_PLAN_TOTAL_ITEMS = 200;

    /**
     * Full proposal-time validation. No DB writes (except reads).
     * Returns ['ok','code','error','resolved','idempotency_key'] (+ field).
     */
    public static function validateForProposal($user, string $tool, $rawArgs, array $options = []): array
    {
        $tool = AiToolService::normalizeToolName((string) $tool);
        if (! in_array($tool, AiToolService::TOOLS, true)) {
            return ToolError::fail(ToolError::UNKNOWN_TOOL, "Unknown tool: {$tool}.");
        }

        // 1) Schema: envelope (shape/size) on raw args.
        $env = ToolSchema::envelope($rawArgs);
        if (! ($env['ok'] ?? false)) {
            return $env;
        }

        // 2) Security screen — own stage, runs before anything is trusted.
        $denied = self::securityScreen($tool, $env['args']);
        if ($denied !== null) {
            return $denied;
        }

        // 3) Normalization (alias mapping, id casts, identity stripping).
        $service = new AiToolService;
        $args = $service->normalizeArgs($env['args'], $tool);

        // 4) Schema: shape of normalized args.
        $shape = ToolSchema::check($tool, $args);
        if (! ($shape['ok'] ?? false)) {
            return $shape;
        }

        // 5) Business validation (existing per-tool validators).
        $check = $service->validateCall($tool, $args, $user);
        if (! ($check['ok'] ?? false)) {
            return ToolError::fail(
                $check['code'] ?? ToolError::VALIDATION_ERROR,
                $check['error'] ?? 'Invalid action.'
            );
        }
        $resolved = $check['resolved'] ?? [];

        // 6) Ownership: independent re-check of every resolved resource.
        $own = self::checkOwnership($tool, $resolved, $user);
        if ($own !== null) {
            return $own;
        }

        // 7) State: current resource state must accept the mutation.
        $state = self::checkState($tool, $resolved, $user);
        if ($state !== null) {
            return $state;
        }

        // 8) Confirmation policy (+ plan totals guard).
        $policy = self::checkPolicy($tool, $resolved);
        if ($policy !== null) {
            return $policy;
        }

        $key = self::deriveKey((int) $user->id, $options['conversation_id'] ?? null, $tool, $resolved);

        return ToolError::ok(['resolved' => $resolved, 'idempotency_key' => $key]);
    }

    /**
     * Execution-time re-validation: same stages, fresh reads.
     * Must be called inside (or just before) the execution transaction.
     */
    public static function revalidateForExecute($user, string $tool, array $args): array
    {
        $tool = AiToolService::normalizeToolName((string) $tool);
        if (! in_array($tool, AiToolService::TOOLS, true)) {
            return ToolError::fail(ToolError::UNKNOWN_TOOL, "Unknown tool: {$tool}.");
        }

        $denied = self::securityScreen($tool, $args);
        if ($denied !== null) {
            return $denied;
        }

        $service = new AiToolService;
        $normalized = $service->normalizeArgs($args, $tool);

        $shape = ToolSchema::check($tool, $normalized);
        if (! ($shape['ok'] ?? false)) {
            return $shape;
        }

        $check = $service->validateCall($tool, $normalized, $user);
        if (! ($check['ok'] ?? false)) {
            $code = $check['code'] ?? ToolError::VALIDATION_ERROR;
            // A resource that vanished (or moved owners) between proposal and
            // confirm surfaces as NOT_FOUND, never as a silent no-op.
            if ($code === ToolError::VALIDATION_ERROR && self::looksLikeMissing($check['error'] ?? '')) {
                $code = ToolError::NOT_FOUND;
            }

            return ToolError::fail($code, $check['error'] ?? 'No longer valid.');
        }
        $resolved = $check['resolved'] ?? [];

        $own = self::checkOwnership($tool, $resolved, $user);
        if ($own !== null) {
            return $own;
        }
        $state = self::checkState($tool, $resolved, $user);
        if ($state !== null) {
            return $state;
        }

        return ToolError::ok(['resolved' => $resolved]);
    }

    /* ── Stage: Security (AiSecurity only — never validation logic) ── */

    public static function securityScreen(string $tool, array $args): ?array
    {
        $forbidden = AiSecurity::findForbiddenKey($args);
        if ($forbidden !== null && ! ($tool === 'project_add_member' && strtolower($forbidden) === 'user')) {
            AiLogger::log('tool.security_denied', ['tool' => $tool, 'key' => $forbidden]);

            return ToolError::fail(
                ToolError::AUTHORIZATION_ERROR,
                "Argument '{$forbidden}' is not allowed."
            );
        }

        return null;
    }

    /* ── Stage: Ownership (independent of business validators) ─────── */

    /**
     * resolved → [model, id] pairs per tool. Returns error array or null.
     * Missing resources (or foreign ones) → NOT_FOUND: existence of other
     * users' rows is never confirmed.
     */
    public static function checkOwnership(string $tool, array $resolved, $user): ?array
    {
        $uid = (int) $user->id;
        $pairs = [];

        switch ($tool) {
            case 'task_create':
                if (! empty($resolved['project_id'])) {
                    $pairs[] = [Project::class, (int) $resolved['project_id'], 'Project'];
                }
                if (! empty($resolved['parent_id'])) {
                    $pairs[] = [Task::class, (int) $resolved['parent_id'], 'Parent task'];
                }
                break;
            case 'task_update':
            case 'task_complete':
            case 'task_delete':
                $pairs[] = [Task::class, (int) ($resolved['id'] ?? 0), 'Task'];
                break;
            case 'reminder_complete':
            case 'reminder_delete':
                $pairs[] = [Reminder::class, (int) ($resolved['id'] ?? 0), 'Reminder'];
                break;
            case 'note_update':
            case 'note_delete':
                $pairs[] = [Note::class, (int) ($resolved['id'] ?? 0), 'Note'];
                break;
            case 'checklist_add':
                $pairs[] = [Task::class, (int) ($resolved['task_id'] ?? 0), 'Task'];
                break;
            case 'project_create':
                if (! empty($resolved['parent_id'])) {
                    $pairs[] = [Project::class, (int) $resolved['parent_id'], 'Parent project'];
                }
                break;
            case 'project_add_member':
                $pairs[] = [Project::class, (int) ($resolved['project_id'] ?? 0), 'Project'];
                break;
            case 'routine_complete':
            case 'routine_delete':
            case 'routine_log':
                $pairs[] = [Routine::class, (int) ($resolved['routine_id'] ?? $resolved['id'] ?? 0), 'Routine'];
                break;
            case 'note_link':
                $pairs[] = [Note::class, (int) ($resolved['note_id'] ?? 0), 'Note'];
                $type = $resolved['target_type'] ?? null;
                $map = is_string($type) && class_exists($type)
                    ? $type
                    : ['project' => Project::class, 'task' => Task::class, 'note' => Note::class][$resolved['target_kind'] ?? ''] ?? null;
                if ($map && ! empty($resolved['target_id'])) {
                    $pairs[] = [$map, (int) $resolved['target_id'], 'Target'];
                }
                break;
            case 'checklist_toggle':
                $item = ChecklistItem::where('id', (int) ($resolved['id'] ?? 0))
                    ->whereHas('task', fn ($q) => $q->where('user_id', $uid))
                    ->first(['id']);
                if (! $item) {
                    return ToolError::fail(ToolError::NOT_FOUND, 'Checklist item not found.');
                }
                return null;
            default:
                return null; // create-only / read-only / plan tools: nothing to re-check
        }

        foreach ($pairs as [$model, $id, $label]) {
            if ($id <= 0) {
                return ToolError::fail(ToolError::VALIDATION_ERROR, "{$label} reference is missing.");
            }
            $exists = $model::where('id', $id)->where('user_id', $uid)->exists();
            if (! $exists) {
                return ToolError::fail(ToolError::NOT_FOUND, "{$label} not found.");
            }
        }

        return null;
    }

    /* ── Stage: State (fresh reads — catches changes since proposal) ─ */

    public static function checkState(string $tool, array $resolved, $user): ?array
    {
        $uid = (int) $user->id;

        switch ($tool) {
            case 'task_complete':
                $task = Task::where('id', (int) ($resolved['id'] ?? 0))->where('user_id', $uid)->first(['id', 'status', 'title']);
                if ($task && $task->status === 'completed') {
                    return ToolError::fail(ToolError::CONFLICT, "Task '{$task->title}' is already completed.");
                }
                break;
            case 'reminder_complete':
                $rem = Reminder::where('id', (int) ($resolved['id'] ?? 0))->where('user_id', $uid)->first(['id', 'is_completed', 'title']);
                if ($rem && (bool) $rem->is_completed) {
                    return ToolError::fail(ToolError::CONFLICT, "Reminder '{$rem->title}' is already completed.");
                }
                break;
            case 'routine_complete':
                $routine = Routine::where('id', (int) ($resolved['id'] ?? 0))->where('user_id', $uid)->first(['id', 'title']);
                if ($routine && method_exists($routine, 'completedOn') && $routine->completedOn($resolved['date'] ?? now()->toDateString())) {
                    return ToolError::fail(ToolError::CONFLICT, "Routine '{$routine->title}' is already done for ".($resolved['date'] ?? 'today').'.');
                }
                break;
            default:
                break;
        }

        return null;
    }

    /* ── Stage: Confirmation Policy ────────────────────────────────── */

    public static function checkPolicy(string $tool, array $resolved): ?array
    {
        // Policy itself lives in AiToolService (risk map); this stage enforces it.
        if (! AiToolService::requiresConfirmation($tool)) {
            return null; // READ tools: no confirmation needed.
        }

        if ($tool === 'plan_propose') {
            $totals = $resolved['totals'] ?? [];
            $sum = 0;
            foreach (['projects', 'subprojects', 'tasks', 'subtasks', 'reminders', 'notes', 'routines', 'members'] as $k) {
                $sum += (int) ($totals[$k] ?? 0);
            }
            if ($sum > self::MAX_PLAN_TOTAL_ITEMS) {
                return ToolError::fail(
                    ToolError::VALIDATION_ERROR,
                    'Plan is too large ('.$sum.' items, max '.self::MAX_PLAN_TOTAL_ITEMS.'). Split it into smaller plans.'
                );
            }
        }

        return null;
    }

    /* ── Idempotency ───────────────────────────────────────────────── */

    public static function deriveKey(int $userId, $conversationId, string $tool, array $resolvedArgs): string
    {
        $canon = self::canonicalJson($resolvedArgs);

        return hash('sha256', implode('|', ['v1', $userId, $conversationId ?? 'none', $tool, $canon]));
    }

    public static function canonicalJson(array $args): string
    {
        $sorted = self::sortRecursive($args);

        return (string) json_encode($sorted, JSON_UNESCAPED_UNICODE);
    }

    private static function sortRecursive(array $args): array
    {
        foreach ($args as $k => $v) {
            if (is_array($v)) {
                $args[$k] = self::sortRecursive($v);
            }
        }
        if (array_is_list($args)) {
            return $args;
        }
        ksort($args);

        return $args;
    }

    /** Live pending with the same derived key, if any (retry/reconnect safe). */
    public static function findDuplicatePending(int $userId, string $key): ?AiPendingAction
    {
        return AiPendingAction::where('user_id', $userId)
            ->where('idempotency_key', $key)
            ->whereIn('status', [
                AiPendingAction::STATUS_PENDING,
                AiPendingAction::STATUS_EXECUTING,
                AiPendingAction::STATUS_CONFIRMED, // legacy claim state
            ])
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->first();
    }

    /* ── Turn guard: per-turn cap + loop detection ─────────────────── */

    /**
     * Filter one model turn's tool calls.
     * $calls: [['name'=>..., 'arguments'=>string|array], ...]
     * Returns ['kept'=>[...], 'dropped'=>[['name','code','error'], ...]].
     * Identical calls beyond MAX_IDENTICAL_PER_TURN collapse (loop); calls
     * beyond MAX_TOOL_CALLS_PER_TURN are cut. Order is preserved.
     */
    public static function filterTurnCalls(array $calls): array
    {
        $kept = [];
        $dropped = [];
        $fingerprints = [];

        foreach (array_values($calls) as $call) {
            $name = AiToolService::normalizeToolName((string) ($call['name'] ?? ''));
            $fp = $name.'|'.self::canonicalJson(is_array($call['arguments'] ?? null) ? $call['arguments'] : (json_decode((string) ($call['arguments'] ?? '{}'), true) ?? []));
            $fingerprints[$fp] = ($fingerprints[$fp] ?? 0) + 1;

            if ($fingerprints[$fp] > self::MAX_IDENTICAL_PER_TURN) {
                $dropped[] = [
                    'name' => $name,
                    'code' => ToolError::LOOP_DETECTED,
                    'error' => "Repeated identical '{$name}' call dropped (possible model loop) — the first one is kept.",
                ];
                AiLogger::log('tool.loop_dropped', ['tool' => $name, 'repeat' => $fingerprints[$fp]]);
                continue;
            }

            if (count($kept) >= self::MAX_TOOL_CALLS_PER_TURN) {
                $dropped[] = [
                    'name' => $name,
                    'code' => ToolError::RATE_LIMITED,
                    'error' => 'Too many tool calls in one turn (max '.self::MAX_TOOL_CALLS_PER_TURN.'). The rest were dropped — ask the user to split the request.',
                ];
                AiLogger::log('tool.turn_capped', ['tool' => $name]);
                continue;
            }

            $kept[] = $call;
        }

        return ['kept' => $kept, 'dropped' => $dropped];
    }

    /* ── Result validation ─────────────────────────────────────────── */

    /**
     * Normalize an execute() outcome into a structured result so the agent
     * never guesses success from text. Always has ok/code/message.
     */
    public static function normalizeResult(string $tool, $result): array
    {
        if (! is_array($result)) {
            return ToolError::fail(ToolError::EXECUTION_ERROR, 'Tool returned an invalid result.');
        }
        $ok = (bool) ($result['ok'] ?? false);
        $message = trim((string) ($result['message'] ?? $result['error'] ?? ''));
        if ($message === '') {
            $message = $ok ? 'Done.' : 'Execution failed.';
        }
        $out = [
            'ok' => $ok,
            'code' => $result['code'] ?? ($ok ? ToolError::OK : ToolError::EXECUTION_ERROR),
            'message' => $message,
        ];
        if (array_key_exists('id', $result)) {
            $out['id'] = $result['id'];
        }
        if (array_key_exists('deduped', $result)) {
            $out['deduped'] = (bool) $result['deduped'];
        }

        return $out;
    }

    private static function looksLikeMissing(string $error): bool
    {
        foreach (['not found', 'no longer valid', 'not yours', 'missing'] as $needle) {
            if (mb_stripos($error, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
