<?php

namespace App\Services\AiTooling;

use App\Services\AiToolService;

/**
 * Tool/Capability Routing registry for Lina.
 *
 * Goal: instead of sending all 22 tool definitions on every request, only
 * the capabilities relevant to the current Intent (+ detected entities) are
 * exposed to the model. Restriction is enforced SERVER-SIDE (payload +
 * proposal-time allowlist), never by prompt text alone.
 *
 * What this is NOT:
 * - NOT an authorization layer. Ownership checks (ToolPipeline::checkOwnership),
 *   input validation (ToolSchema + AiToolService validators) and user
 *   confirmation (AiPendingAction / AiPlan flows) still gate every mutation.
 * - NOT a framework. One declarative map + two pure functions. New tools are
 *   added by extending CAPABILITIES / CATEGORY_TOOLS below — nothing else.
 *
 * Operation types mirror AiToolService::TOOL_RISK:
 *   READ        — no state change (report_generate).
 *   WRITE       — creates/updates scoped to the authenticated user.
 *   DESTRUCTIVE — deletes/completes; needs confirmation + impact preview.
 *   SENSITIVE   — shares data with others; needs confirmation + preview.
 */
final class ToolCapabilityRegistry
{
    public const OP_READ = 'READ';

    public const OP_WRITE = 'WRITE';

    public const OP_DESTRUCTIVE = 'DESTRUCTIVE';

    public const OP_SENSITIVE = 'SENSITIVE';

    /** Stable category keys (used for multi-category union on mixed requests). */
    public const CATEGORY_TASK = 'task';

    public const CATEGORY_REMINDER = 'reminder';

    public const CATEGORY_NOTE = 'note';

    public const CATEGORY_PROJECT = 'project';

    public const CATEGORY_CHECKLIST = 'checklist';

    public const CATEGORY_ROUTINE = 'routine';

    public const CATEGORY_REPORT = 'report';

    public const CATEGORY_LINK = 'link';

    public const CATEGORY_PLAN = 'plan';

    public const CATEGORY_WORKOUT = 'workout';

    public const CATEGORIES = [
        self::CATEGORY_TASK,
        self::CATEGORY_REMINDER,
        self::CATEGORY_NOTE,
        self::CATEGORY_PROJECT,
        self::CATEGORY_CHECKLIST,
        self::CATEGORY_ROUTINE,
        self::CATEGORY_REPORT,
        self::CATEGORY_LINK,
        self::CATEGORY_PLAN,
        self::CATEGORY_WORKOUT,
    ];

    /**
     * Category → tools. Single source of truth for grouping; AiToolService
     * keeps its legacy TOOL_GROUPS for backward compatibility.
     */
    public const CATEGORY_TOOLS = [
        'task' => ['task_create', 'task_update', 'task_complete', 'task_delete'],
        'reminder' => ['reminder_create', 'reminder_complete', 'reminder_delete'],
        'note' => ['note_create', 'note_update', 'note_delete'],
        'link' => ['note_link'],
        'project' => ['project_create', 'project_add_member'],
        'checklist' => ['checklist_add', 'checklist_toggle'],
        'routine' => ['routine_create', 'routine_complete', 'routine_delete', 'routine_log'],
        'report' => ['report_generate'],
        'plan' => ['plan_propose'],
        'workout' => ['workout_plan_propose'],
    ];

    /**
     * Per-tool capability descriptor: category, short purpose, access rule.
     * `op` is derived from AiToolService::TOOL_RISK (kept here for docs;
     * runtime reads riskOf() so the two can never drift).
     */
    public const CAPABILITIES = [
        'task_create' => ['category' => 'task', 'desc' => 'Create a task (or subtask via parent_id)', 'access' => 'owner-only'],
        'task_update' => ['category' => 'task', 'desc' => 'Edit a task resolved by id/title (+project scope)', 'access' => 'owner-only'],
        'task_complete' => ['category' => 'task', 'desc' => 'Mark a task completed', 'access' => 'owner-only'],
        'task_delete' => ['category' => 'task', 'desc' => 'Delete a task (subtask impact preview)', 'access' => 'owner-only'],
        'reminder_create' => ['category' => 'reminder', 'desc' => 'Create a reminder with date/time', 'access' => 'owner-only'],
        'reminder_complete' => ['category' => 'reminder', 'desc' => 'Mark a reminder completed', 'access' => 'owner-only'],
        'reminder_delete' => ['category' => 'reminder', 'desc' => 'Delete a reminder', 'access' => 'owner-only'],
        'note_create' => ['category' => 'note', 'desc' => 'Create a note', 'access' => 'owner-only'],
        'note_update' => ['category' => 'note', 'desc' => 'Edit a note by ID', 'access' => 'owner-only'],
        'note_delete' => ['category' => 'note', 'desc' => 'Delete a note by ID', 'access' => 'owner-only'],
        'note_link' => ['category' => 'link', 'desc' => 'Link a note to a project/task/note', 'access' => 'owner-only, both sides owned'],
        'project_create' => ['category' => 'project', 'desc' => 'Create a project (or sub-project via parent)', 'access' => 'owner-only'],
        'project_add_member' => ['category' => 'project', 'desc' => 'Add a collaborator to an owned project', 'access' => 'owner-only project, member by email/name/id'],
        'checklist_add' => ['category' => 'checklist', 'desc' => 'Add a checklist item to a task', 'access' => 'owner-only (via task)'],
        'checklist_toggle' => ['category' => 'checklist', 'desc' => 'Toggle a checklist item state', 'access' => 'owner-only (via task)'],
        'routine_create' => ['category' => 'routine', 'desc' => 'Create a recurring routine', 'access' => 'owner-only'],
        'routine_complete' => ['category' => 'routine', 'desc' => 'Mark a routine done for a date', 'access' => 'owner-only'],
        'routine_delete' => ['category' => 'routine', 'desc' => 'Delete a routine (history impact preview)', 'access' => 'owner-only'],
        'routine_log' => ['category' => 'routine', 'desc' => 'Log a tracked number for a routine day', 'access' => 'owner-only'],
        'report_generate' => ['category' => 'report', 'desc' => 'Read-only workspace summary (today/week/month)', 'access' => 'owner-only, read-only'],
        'plan_propose' => ['category' => 'plan', 'desc' => 'Propose a multi-project/routine build as one plan', 'access' => 'owner-only, phase-by-phase after approval'],
        'workout_plan_propose' => ['category' => 'workout', 'desc' => 'Propose a structured 7-day workout plan', 'access' => 'owner-only, via import preview after approval'],
    ];

    /**
     * Router entity object → capability categories. Used for fine-grained
     * multi-category selection on mutation intents (combined requests union
     * all matched categories). Canonical map — ToolCapabilityRouter reuses
     * it (plus request-context cues) so the two can never drift.
     */
    public const OBJECT_TO_CATEGORIES = [
        'task' => ['task', 'checklist'],
        'checklist' => ['checklist', 'task'],
        'reminder' => ['reminder'],
        'note' => ['note', 'link'],
        'routine' => ['routine'],
        'project' => ['project'],
        'member' => ['project'],
        'workout' => ['workout'],
        // 'file' maps to no tool category (no file tools exist).
    ];

    /** READ-only, always-safe fallback for unclear intents. */
    public const SAFE_FALLBACK = ['report_generate'];

    /** Alias kept for router call sites (same safe set). */
    public const SAFE_SET = ['report_generate'];

    /* ── Lookups ─────────────────────────────────────────────── */

    public static function capabilityOf(string $tool): ?array
    {
        $tool = AiToolService::normalizeToolName($tool);
        $cap = self::CAPABILITIES[$tool] ?? null;
        if ($cap === null) {
            return null;
        }

        return $cap + [
            'name' => $tool,
            'op' => AiToolService::riskOf($tool),
            'needs_confirmation' => AiToolService::requiresConfirmation($tool),
        ];
    }

    public static function categoryOf(string $tool): ?string
    {
        return self::capabilityOf($tool)['category'] ?? null;
    }

    /** @return string[] operation tags (e.g. ['WRITE','DESTRUCTIVE']) */
    public static function operationOf(string $tool): array
    {
        return AiToolService::riskOf(AiToolService::normalizeToolName($tool));
    }

    /** @return string[] tools for the given categories (order-preserving, deduped) */
    public static function toolsForCategories(array $categories): array
    {
        $out = [];
        foreach ($categories as $cat) {
            foreach (self::CATEGORY_TOOLS[$cat] ?? [] as $tool) {
                if (! in_array($tool, $out, true)) {
                    $out[] = $tool;
                }
            }
        }

        return $out;
    }

    /** @return string[] every known tool in canonical order */
    public static function allTools(): array
    {
        return AiToolService::TOOLS;
    }

    /** Only OpenAI-compatible providers support function-calling. */
    public static function providerSupportsTools(array $resolved): bool
    {
        return ($resolved['type'] ?? 'openai') === 'openai';
    }

    /* ── Routing ─────────────────────────────────────────────── */

    /**
     * Resolve the tool allowlist for a routed request.
     *
     * Returns null  = keep the FULL set (fail-open, today's behavior).
     * Returns []    = no tools needed (confident smalltalk / chat mode).
     * Returns array = narrowed allowlist; the SAME list must gate both the
     *   outbound payload AND inbound proposal validation (server-side).
     *
     * Entity-aware: a mutation with detected objects maps to the union of
     * matching categories (+ report for read-back), so combined requests
     * like "task + reminder + note" keep every relevant category while the
     * heavy plan/workout schemas stay out.
     */
    public static function resolveForRoute(array $route): ?array
    {
        $intent = $route['intent'] ?? null;
        $entities = $route['entities'] ?? [];
        $objects = $entities['objects'] ?? [];

        // Unclear requests never get the full mutation set: READ-only tools
        // plus the clarification question (route['clarification_question']).
        if ($intent === \App\Services\AiIntentRouter::INTENT_AMBIGUOUS) {
            return self::SAFE_FALLBACK;
        }

        switch ($intent) {
            case \App\Services\AiIntentRouter::INTENT_GENERAL:
                return [];
            case \App\Services\AiIntentRouter::INTENT_QUERY:
            case \App\Services\AiIntentRouter::INTENT_REPORT:
                return self::CATEGORY_TOOLS['report'];
            case \App\Services\AiIntentRouter::INTENT_WORKOUT:
                return self::CATEGORY_TOOLS['workout'];
            case \App\Services\AiIntentRouter::INTENT_PLAN:
                // Plans embed projects/tasks/reminders/notes/routines/members:
                // narrowing would break multi-step builds → fail-open.
                return null;
            case \App\Services\AiIntentRouter::INTENT_MUTATION:
                // Single implementation lives in ToolCapabilityRouter (adds
                // the report read-back + legacy fallback); delegated so the
                // advisory tool_filter and the enforced payload never drift.
                return self::toolsForCategories(
                    ToolCapabilityRouter::categoriesForMutation(['entities' => ['objects' => (array) $objects]])
                );
            default:
                return null;
        }
    }

    /**
     * Allowlist check for an inbound model tool call.
     * $allowed = null means "no routing restriction" (full set).
     */
    public static function isAllowed(string $tool, ?array $allowed): bool
    {
        if ($allowed === null) {
            return in_array(AiToolService::normalizeToolName($tool), AiToolService::TOOLS, true);
        }
        $keep = array_fill_keys(
            array_map([AiToolService::class, 'normalizeToolName'], $allowed),
            true
        );

        return isset($keep[AiToolService::normalizeToolName($tool)]);
    }

    /**
     * Sanity: every AiToolService::TOOLS entry has a capability descriptor
     * and lives in exactly one category. Returns missing keys (empty = OK).
     */
    public static function validate(): array
    {
        $missing = [];
        foreach (AiToolService::TOOLS as $tool) {
            if (! isset(self::CAPABILITIES[$tool])) {
                $missing[] = $tool.':capability';
            }
        }
        foreach (self::CAPABILITIES as $tool => $cap) {
            if (! in_array($tool, AiToolService::TOOLS, true)) {
                $missing[] = $tool.':unknown_tool';
            }
            if (! in_array($cap['category'] ?? '', self::CATEGORIES, true)) {
                $missing[] = $tool.':category';
            }
        }

        return $missing;
    }

    /** Tool names carried by OpenAI-style $definitions (for logging/tests). */
    public static function namesFromDefinitions(?array $definitions): ?array
    {
        if ($definitions === null) {
            return null;
        }

        return array_values(array_map(
            fn ($d) => $d['function']['name'] ?? null,
            $definitions
        ));
    }

    /**
     * Rough token estimate for a definitions payload (chars/4 heuristic).
     * For before/after comparison only — never a billing measure.
     */
    public static function estimateTokens(array $definitions): int
    {
        $json = json_encode($definitions, JSON_UNESCAPED_UNICODE);

        return $json === false ? 0 : (int) ceil(strlen($json) / 4);
    }

    /** JSON byte size of a definitions payload (exact, for comparisons). */
    public static function payloadBytes(array $definitions): int
    {
        $json = json_encode($definitions, JSON_UNESCAPED_UNICODE);

        return $json === false ? 0 : strlen($json);
    }
}
