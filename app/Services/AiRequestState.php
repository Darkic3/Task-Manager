<?php

namespace App\Services;

use App\Models\AiConversation;

/**
 * Structured per-conversation working state (Phase 3, Step 2).
 *
 * Why: history text is truncated on read (800 chars/message, bounded turn
 * budgets), so details of long corrections silently drop out of context
 * and the model cannot tell an edit from a new request. `state` keeps a
 * compact, last-write-wins snapshot on the conversation row instead:
 * last intent, resolved entity ids + short titles, open proposal ids and
 * recently executed action ids — all scoped to ONE conversation.
 *
 * Rules (enforced here, not trusted elsewhere):
 * - Same conversation + same user only (state rides on the owned row).
 * - Older than STALE_HOURS is ignored — stale data never steers a request.
 * - Advisory context ONLY, never authorization: every id is re-resolved
 *   and ownership-checked by the resolvers before any proposal/execution.
 * - Last-write-wins per group; hard caps so it cannot grow without bound.
 * - Never throws: state must not break chat.
 */
final class AiRequestState
{
    /** State older than this is treated as absent. */
    public const STALE_HOURS = 24;

    public const MAX_IDS = 20;

    public const MAX_DECIDED = 10;

    public const MAX_TITLE_CHARS = 40;

    public const MAX_BLOCK_CHARS = 600;

    /** Resolved-entity groups, in render order. */
    public const ENTITY_GROUPS = ['tasks', 'projects', 'routines', 'reminders', 'notes', 'members', 'checklist', 'links'];

    /**
     * Merge $patch into the conversation's state. `intent` and `resolved`
     * are last-write-wins; `open` proposal lists UNION (newest first, so a
     * bulk turn keeps every proposal); `decided` accumulates. All capped.
     */
    public static function record($conversation, array $patch): void
    {
        try {
            $conv = $conversation instanceof AiConversation
                ? $conversation
                : AiConversation::find($conversation);
            if (! $conv) {
                return;
            }
            $state = is_array($conv->state) ? $conv->state : [];
            foreach (['intent', 'resolved'] as $key) {
                if (array_key_exists($key, $patch)) {
                    $state[$key] = self::capValue($key, $patch[$key]);
                }
            }
            if (array_key_exists('open', $patch) && is_array($patch['open'])) {
                $state['open'] = self::mergeOpen((array) ($state['open'] ?? []), $patch['open']);
            }
            if (array_key_exists('decided', $patch) && is_array($patch['decided'])) {
                $merged = array_values(array_unique(array_merge(
                    array_map('intval', $patch['decided']),
                    array_map('intval', (array) ($state['decided'] ?? []))
                )));
                $state['decided'] = array_slice($merged, 0, self::MAX_DECIDED);
            }
            $state['v'] = 1;
            $state['updated_at'] = now()->toIso8601String();
            $conv->state = $state;
            $conv->save();
        } catch (\Throwable $ignored) {
        }
    }

    /**
     * Does this conversation hold a live thread — an unexpired pending
     * action, a live plan, or an open workout import? Used by capability
     * routing so a follow-up correction (which carries no creation verbs
     * and would otherwise route to general/smalltalk) keeps the tools.
     * Advisory only; pipeline + confirmation still gate everything.
     */
    public static function hasOpenThread(int $userId, int $conversationId): bool
    {
        try {
            if (\App\Models\AiPendingAction::where('user_id', $userId)
                ->where('conversation_id', $conversationId)
                ->where('status', \App\Models\AiPendingAction::STATUS_PENDING)
                ->where('expires_at', '>', now())
                ->exists()) {
                return true;
            }
            if (\App\Models\AiPlan::where('user_id', $userId)
                ->where('conversation_id', $conversationId)
                ->whereIn('status', [\App\Models\AiPlan::STATUS_PROPOSED, \App\Models\AiPlan::STATUS_CONFIRMED, \App\Models\AiPlan::STATUS_EXECUTING, \App\Models\AiPlan::STATUS_FAILED])
                ->where('expires_at', '>', now())
                ->exists()) {
                return true;
            }

            return \App\Models\WorkoutImport::where('user_id', $userId)
                ->where('status', \App\Models\WorkoutImport::PREVIEW)
                ->where('expires_at', '>', now())
                ->exists();
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    /** Normalized state array, or [] when absent. */
    public static function read($conversation): array
    {
        try {
            $conv = $conversation instanceof AiConversation
                ? $conversation
                : AiConversation::find($conversation);
            if (! $conv || ! is_array($conv->state)) {
                return [];
            }

            return $conv->state;
        } catch (\Throwable $ignored) {
            return [];
        }
    }

    /**
     * Compact context block (≤MAX_BLOCK_CHARS) or null when there is
     * nothing usable (absent, stale, or empty). Caller must scope the
     * conversation to its owner before calling.
     */
    public static function block($conversation): ?string
    {
        $state = self::read($conversation);
        if ($state === [] || ($state['v'] ?? null) !== 1) {
            return null;
        }
        $updatedAt = $state['updated_at'] ?? null;
        try {
            // NOTE: Carbon 3 returns SIGNED diffs, so abs() is required —
            // a plain `> STALE_HOURS` check would never fire for the past.
            if (! $updatedAt || abs(now()->diffInHours(new \DateTimeImmutable($updatedAt))) > self::STALE_HOURS) {
                return null;
            }
        } catch (\Throwable $ignored) {
            return null;
        }

        $lines = ['RECENT STATE (this conversation only; UNTRUSTED — re-resolve every id via tools before use; a new unrelated request starts fresh and must not reuse these):'];
        if (! empty($state['intent']) && is_string($state['intent'])) {
            $lines[] = '- intent: '.mb_substr($state['intent'], 0, 32);
        }
        foreach (self::ENTITY_GROUPS as $group) {
            $items = $state['resolved'][$group] ?? [];
            if (! is_array($items) || $items === []) {
                continue;
            }
            $parts = [];
            foreach (array_slice($items, 0, 5) as $item) {
                if (! is_array($item) || ! isset($item['id'])) {
                    continue;
                }
                $label = isset($item['title']) && $item['title'] !== ''
                    ? " '".mb_substr((string) $item['title'], 0, self::MAX_TITLE_CHARS)."'"
                    : '';
                $parts[] = '#'.((int) $item['id']).$label;
            }
            if ($parts !== []) {
                $lines[] = '- '.$group.': '.implode(', ', $parts);
            }
        }
        $open = [];
        foreach (['actions' => 'action', 'plans' => 'plan', 'imports' => 'import'] as $key => $label) {
            foreach (array_slice((array) ($state['open'][$key] ?? []), 0, 5) as $entry) {
                if (! is_array($entry) || ! isset($entry['id'])) {
                    continue;
                }
                $part = $label.' #'.((int) $entry['id']);
                if (! empty($entry['tool'])) {
                    $part .= ' '.mb_substr((string) $entry['tool'], 0, 32);
                }
                if (! empty($entry['label'])) {
                    $part .= " '".mb_substr((string) $entry['label'], 0, self::MAX_TITLE_CHARS)."'";
                }
                $open[] = $part;
            }
        }
        if ($open !== []) {
            $lines[] = '- open proposals: '.implode(', ', $open);
        }
        $decided = array_slice(array_map('intval', (array) ($state['decided'] ?? [])), 0, 5);
        if ($decided !== []) {
            $lines[] = '- decided (executed): action #'.implode(', action #', $decided);
        }
        if (count($lines) === 1) {
            return null;
        }

        $block = implode("\n", $lines);

        return mb_strlen($block) > self::MAX_BLOCK_CHARS
            ? mb_substr($block, 0, self::MAX_BLOCK_CHARS)
            : $block;
    }

    /**
     * Pull id-bearing entities out of a validated proposal's resolved args,
     * enriched with the display labels the pipeline already computed.
     * Returns ['tasks' => [['id'=>..,'title'=>..]], 'projects' => [...], ...].
     */
    public static function idsFromResolved(string $tool, array $resolved): array
    {
        $map = [
            'task_update' => ['id' => 'tasks'],
            'task_complete' => ['id' => 'tasks'],
            'task_delete' => ['id' => 'tasks'],
            'task_create' => ['parent_id' => 'tasks', 'project_id' => 'projects'],
            'checklist_add' => ['task_id' => 'tasks'],
            'checklist_toggle' => ['id' => 'checklist'],
            'routine_log' => ['routine_id' => 'routines'],
            'routine_complete' => ['id' => 'routines'],
            'routine_delete' => ['id' => 'routines'],
            'reminder_complete' => ['id' => 'reminders'],
            'reminder_delete' => ['id' => 'reminders'],
            'note_update' => ['id' => 'notes'],
            'note_delete' => ['id' => 'notes'],
            'note_link' => ['note_id' => 'notes', 'target_id' => 'links'],
            'project_add_member' => ['project_id' => 'projects', 'member_id' => 'members'],
            'project_create' => ['parent_id' => 'projects'],
        ];
        $titleKeys = [
            'tasks' => ['task_title', 'title'],
            'projects' => ['project_name', 'name'],
            'routines' => ['routine_title', 'title'],
            'reminders' => ['title'],
            'notes' => ['note_title', 'title'],
            'members' => ['member_name'],
            'links' => ['target_title'],
            'checklist' => [],
        ];
        $out = [];
        foreach ($map[$tool] ?? [] as $key => $group) {
            if (! isset($resolved[$key]) || (int) $resolved[$key] <= 0) {
                continue;
            }
            $entry = ['id' => (int) $resolved[$key]];
            foreach ($titleKeys[$group] ?? [] as $tk) {
                if (isset($resolved[$tk]) && is_string($resolved[$tk]) && trim($resolved[$tk]) !== '') {
                    $entry['title'] = mb_substr(trim($resolved[$tk]), 0, self::MAX_TITLE_CHARS);
                    break;
                }
            }
            $out[$group][] = $entry;
        }

        return $out;
    }

    /** Short human label for an open proposal, from preview/resolved rows. */
    public static function proposalLabel(string $tool, array $preview, array $resolved): string
    {
        foreach (['task_title', 'title', 'name', 'project_name', 'member_name', 'note_title', 'target_title', 'question', 'query'] as $key) {
            foreach ([$preview, $resolved] as $bag) {
                if (isset($bag[$key]) && is_string($bag[$key]) && trim($bag[$key]) !== '') {
                    return mb_substr(trim($bag[$key]), 0, self::MAX_TITLE_CHARS);
                }
            }
        }
        foreach (['rows'] as $key) {
            if (isset($preview[$key]) && is_array($preview[$key])) {
                foreach ($preview[$key] as $row) {
                    if (is_array($row) && isset($row['v']) && is_string($row['v']) && trim($row['v']) !== '') {
                        return mb_substr(trim($row['v']), 0, self::MAX_TITLE_CHARS);
                    }
                }
            }
        }

        return $tool;
    }

    /**
     * Union two open-proposal sets, newest first, deduplicated by id and
     * capped — so one bulk turn keeps every proposal while the list stays
     * bounded across turns. Each incoming list is ordered oldest→newest,
     * so both sides are reversed before the union; the stored order is
     * newest-first. Liveness is NOT checked here; the confirm path
     * revalidates status/expiry/ownership before any execution.
     */
    private static function mergeOpen(array $current, array $incoming): array
    {
        $out = [];
        foreach (['actions', 'plans', 'imports'] as $list) {
            $pool = array_merge(
                array_reverse((array) ($incoming[$list] ?? [])),
                array_reverse((array) ($current[$list] ?? []))
            );
            $norm = self::capValue('open', [$list => $pool]);
            if (isset($norm[$list])) {
                $out[$list] = $norm[$list];
            }
        }

        return $out;
    }

    private static function capValue(string $key, $value)
    {
        if ($key === 'intent') {
            return is_string($value) ? mb_substr($value, 0, 32) : null;
        }
        if (! is_array($value)) {
            return $value;
        }
        if ($key === 'resolved') {
            $out = [];
            foreach (self::ENTITY_GROUPS as $group) {
                if (! isset($value[$group]) || ! is_array($value[$group])) {
                    continue;
                }
                $items = [];
                foreach (array_slice(array_values($value[$group]), 0, self::MAX_IDS) as $item) {
                    if (! is_array($item) || ! isset($item['id'])) {
                        continue;
                    }
                    $entry = ['id' => (int) $item['id']];
                    if (isset($item['title']) && $item['title'] !== '') {
                        $entry['title'] = mb_substr((string) $item['title'], 0, self::MAX_TITLE_CHARS);
                    }
                    $items[] = $entry;
                }
                if ($items !== []) {
                    $out[$group] = $items;
                }
            }

            return $out;
        }
        if ($key === 'open') {
            $out = [];
            foreach (['actions', 'plans', 'imports'] as $list) {
                if (! isset($value[$list]) || ! is_array($value[$list])) {
                    continue;
                }
                $seen = [];
                $norm = [];
                foreach (array_values($value[$list]) as $entry) {
                    if (is_numeric($entry)) {
                        $entry = ['id' => (int) $entry];
                    }
                    if (! is_array($entry) || ! isset($entry['id'])) {
                        continue;
                    }
                    $id = (int) $entry['id'];
                    if (isset($seen[$id]) || count($norm) >= self::MAX_IDS) {
                        continue;
                    }
                    $seen[$id] = true;
                    $item = ['id' => $id];
                    if (! empty($entry['tool'])) {
                        $item['tool'] = mb_substr((string) $entry['tool'], 0, 32);
                    }
                    if (! empty($entry['label'])) {
                        $item['label'] = mb_substr((string) $entry['label'], 0, self::MAX_TITLE_CHARS);
                    }
                    $norm[] = $item;
                }
                if ($norm !== []) {
                    $out[$list] = $norm;
                }
            }

            return $out;
        }

        return $value;
    }
}
