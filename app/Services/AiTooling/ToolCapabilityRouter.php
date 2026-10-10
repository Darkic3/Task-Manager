<?php

namespace App\Services\AiTooling;

use App\Services\AiIntentRouter;
use App\Services\AiToolService;

/**
 * Tool/Capability Router for Lina.
 *
 * Sits ON TOP of AiIntentRouter (which stays the pure intent classifier).
 * Selects the minimal tool subset to actually send to the model:
 *
 *   message + intent route + request context + provider capabilities
 *     → categories → tool names → definitions
 *
 * Rules:
 * - Agent mode + OpenAI-compatible provider only. Anything else → no tools
 *   (chat mode is read-only; Gemini/Anthropic have no function-calling here).
 * - High-confidence intents narrow to the matching categories.
 * - Composite requests union several categories (detected workspace objects
 *   + explicit context cues like attached notes/files).
 * - Ambiguous / low-confidence → SAFE_FALLBACK (READ-only report_generate)
 *   so the model can answer or clarify with zero mutation surface. Medium
 *   confidence stays fail-open (null = full set) to protect recall.
 * - plan_build stays fail-open (null = full set): a plan may legally bundle
 *   projects + tasks + reminders + notes + routines + members.
 * - This router is a COST/RELEVANCE optimization only. It never authorizes:
 *   ToolPipeline validation, ownership checks and user confirmation still
 *   gate every mutation. Off-route model calls are additionally REJECTED at
 *   proposal time (visibility enforcement), but that rejection is separate
 *   from — and never a replacement for — authorization.
 */
final class ToolCapabilityRouter
{
    /**
     * Workspace object (AiIntentRouter entity) → capability categories.
     * A single object can light up more than one category (task ↔ checklist).
     * Alias of the registry map (single source of truth); context cues
     * (attachments) extend it per-request in categoriesForMutation().
     */
    public const OBJECT_CATEGORIES = ToolCapabilityRegistry::OBJECT_TO_CATEGORIES;

    /** Does this provider type support function-calling in this codebase? */
    public static function supportsTools(?string $providerType): bool
    {
        return ($providerType ?? 'openai') === 'openai';
    }

    /**
     * Select tool names for a request.
     *
     * @param  array  $route        AiIntentRouter::route() output.
     * @param  array  $context      Request context cues:
     *                              ['mode' => 'agent|chat',
     *                               'provider_type' => 'openai|gemini|...',
     *                               'has_note_attachments' => bool,
     *                               'has_file_attachments' => bool]
     * @return array{tools: ?array, categories: array, reason: string}
     *         tools=null means "keep the full set" (fail-open).
     *         tools=[] means "send no tools at all".
     */
    public static function select(array $route, array $context = []): array
    {
        $mode = $context['mode'] ?? 'agent';
        $providerType = $context['provider_type'] ?? 'openai';

        // 1) Non-agent surface or non-function-calling provider → no tools.
        if ($mode !== 'agent') {
            return ['tools' => [], 'categories' => [], 'reason' => 'chat_mode_read_only'];
        }
        if (! self::supportsTools($providerType)) {
            return ['tools' => [], 'categories' => [], 'reason' => 'provider_no_tool_support'];
        }

        $intent = $route['intent'] ?? AiIntentRouter::INTENT_AMBIGUOUS;
        $confidence = $route['confidence'] ?? AiIntentRouter::CONF_LOW;

        // 2) Casual/smalltalk with confidence → nothing to send.
        if ($intent === AiIntentRouter::INTENT_GENERAL && $confidence === AiIntentRouter::CONF_HIGH) {
            return ['tools' => [], 'categories' => [], 'reason' => 'general_chat_no_tools'];
        }

        // 3) Unclear intent → limited SAFE set (READ-only), clarification path
        //    is driven by AiIntentRouter::hintFor() in the system prompt.
        if ($intent === AiIntentRouter::INTENT_AMBIGUOUS || $confidence === AiIntentRouter::CONF_LOW) {
            return [
                'tools' => ToolCapabilityRegistry::SAFE_FALLBACK,
                'categories' => ['report'],
                'reason' => 'unclear_intent_safe_set',
            ];
        }

        // 4) Medium confidence → fail-open (today's behavior) to protect recall.
        if ($confidence !== AiIntentRouter::CONF_HIGH) {
            return ['tools' => null, 'categories' => [], 'reason' => 'medium_confidence_fail_open'];
        }

        // 5) High-confidence intent → category union.
        switch ($intent) {
            case AiIntentRouter::INTENT_QUERY:
            case AiIntentRouter::INTENT_REPORT:
                return [
                    'tools' => ToolCapabilityRegistry::toolsForCategories(['report']),
                    'categories' => ['report'],
                    'reason' => 'read_only_report',
                ];

            case AiIntentRouter::INTENT_WORKOUT:
                return [
                    'tools' => ToolCapabilityRegistry::toolsForCategories(['workout']),
                    'categories' => ['workout'],
                    'reason' => 'workout_plan_only',
                ];

            case AiIntentRouter::INTENT_PLAN:
                // Plans bundle arbitrary dimensions — narrowing would break them.
                return ['tools' => null, 'categories' => [], 'reason' => 'plan_fail_open'];

            case AiIntentRouter::INTENT_MUTATION:
                $categories = self::categoriesForMutation($route, $context);

                return [
                    'tools' => ToolCapabilityRegistry::toolsForCategories($categories),
                    'categories' => $categories,
                    'reason' => 'mutation_categories:'.implode(',', $categories),
                ];

            default:
                return ['tools' => null, 'categories' => [], 'reason' => 'unknown_intent_fail_open'];
        }
    }

    /**
     * Category union for a high-confidence mutation:
     * base categories from detected objects + context cues + always REPORT.
     * Empty objects → legacy broad single-item set (backward compatible).
     */
    public static function categoriesForMutation(array $route, array $context = []): array
    {
        $objects = $route['entities']['objects'] ?? [];
        $categories = [];

        foreach ($objects as $object) {
            foreach (self::OBJECT_CATEGORIES[$object] ?? [] as $cat) {
                if (! in_array($cat, $categories, true)) {
                    $categories[] = $cat;
                }
            }
        }

        // Context cues (explicit request signals, ownership-checked downstream).
        if (! empty($context['has_note_attachments'])) {
            foreach (['note', 'link'] as $cat) {
                if (! in_array($cat, $categories, true)) {
                    $categories[] = $cat;
                }
            }
        }

        // No object detected → legacy broad single-item set (no plan/workout).
        // Mirrors the old TOOL_GROUPS['single'] coverage (incl. note_link).
        if (empty($categories)) {
            $categories = ['task', 'checklist', 'reminder', 'note', 'link', 'project', 'routine'];
        }

        // Report is always visible alongside mutations (read-only, cheap).
        if (! in_array('report', $categories, true)) {
            $categories[] = 'report';
        }

        // Canonical registry order for stable payloads.
        usort($categories, fn ($a, $b) => array_search($a, ToolCapabilityRegistry::CATEGORIES) <=> array_search($b, ToolCapabilityRegistry::CATEGORIES));

        return $categories;
    }

    /**
     * Tool definitions for a route + context (null = full set, [] = none).
     * Thin wrapper over select() for call sites that build provider payloads.
     */
    public static function definitionsFor(array $route, AiToolService $svc, array $context = []): ?array
    {
        $selected = self::select($route, $context);

        if ($selected['tools'] === null) {
            return null;
        }

        return $svc->definitions($selected['tools']);
    }

    /** Allowed tool names for enforcement (null = all tools allowed). */
    public static function allowedNames(array $route, array $context = []): ?array
    {
        $selected = self::select($route, $context);

        return $selected['tools'];
    }

    /**
     * Visibility enforcement: was this model-proposed tool actually visible
     * to the model for this request? Null allowlist = full set = always true.
     * Unknown tools are never allowed.
     */
    public static function isAllowed(string $tool, ?array $allowedNames): bool
    {
        return ToolCapabilityRegistry::isAllowed($tool, $allowedNames);
    }

    /**
     * Before/after token comparison for one route (chars/4 heuristic).
     * Returns ['full'=>int,'routed'=>int,'saved'=>int,'saved_pct'=>float].
     */
    public static function tokenComparison(array $route, AiToolService $svc, array $context = []): array
    {
        $full = $svc->definitions();
        $fullTokens = ToolCapabilityRegistry::estimateTokens($full);

        $defs = self::definitionsFor($route, $svc, $context);
        if ($defs === null) {
            $defs = $full;
        }
        $routedTokens = ToolCapabilityRegistry::estimateTokens($defs);
        $saved = max(0, $fullTokens - $routedTokens);

        return [
            'full' => $fullTokens,
            'routed' => $routedTokens,
            'saved' => $saved,
            'saved_pct' => $fullTokens > 0 ? round($saved / $fullTokens * 100, 1) : 0.0,
        ];
    }
}
