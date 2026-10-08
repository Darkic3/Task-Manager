<?php

namespace App\Services;

use App\Models\AiPendingAction;
use App\Models\Reminder;
use App\Models\RoutineCompletion;
use App\Models\Task;
use App\Services\AiTooling\ToolError;

/**
 * Limited Undo for simple, safe AI mutations.
 *
 * Supported tools: task_complete, reminder_complete, routine_complete,
 * task_update. Everything else (creates, deletes, links, members, plans,
 * imports) is NOT undoable: deletes destroy rows that snapshots cannot
 * faithfully restore, and creates are removed via the normal delete flow.
 *
 * Mechanics: runAction captures a before-image into ai_pending_actions.
 * undo_data BEFORE execute (and the created completion id AFTER execute
 * for routine_complete). Undo reverts owned rows inside a locked
 * transaction, only within AiPendingAction::UNDO_WINDOW_MINUTES.
 */
class AiUndoService
{
    public const REVERSIBLE = ['task_complete', 'reminder_complete', 'routine_complete', 'task_update'];

    public static function supports(string $tool): bool
    {
        return in_array(AiToolService::normalizeToolName($tool), self::REVERSIBLE, true);
    }

    /**
     * Before-image for reversible tools (fresh, owned reads). Returns null
     * when there is nothing safe to capture — then no undo is offered.
     */
    public static function capture(string $tool, array $resolved, $user): ?array
    {
        $tool = AiToolService::normalizeToolName($tool);
        $uid = (int) $user->id;

        switch ($tool) {
            case 'task_complete':
                $task = Task::where('id', (int) ($resolved['id'] ?? 0))->where('user_id', $uid)->first();
                if (! $task) {
                    return null;
                }

                return ['before' => [
                    'status' => $task->status,
                    'completed_at' => $task->completed_at?->toIso8601String(),
                ]];
            case 'reminder_complete':
                $rem = Reminder::where('id', (int) ($resolved['id'] ?? 0))->where('user_id', $uid)->first();
                if (! $rem) {
                    return null;
                }

                return ['before' => [
                    'is_completed' => (bool) $rem->is_completed,
                    'completed_at' => $rem->completed_at?->toIso8601String(),
                ]];
            case 'routine_complete':
                // The completion row is created BY execute; finalized after.
                $routineId = (int) ($resolved['id'] ?? 0);
                if (! \App\Models\Routine::where('id', $routineId)->where('user_id', $uid)->exists()) {
                    return null;
                }

                return ['routine_id' => $routineId, 'date' => $resolved['date'] ?? now()->toDateString(), 'pending_completion' => true];
            case 'task_update':
                $task = Task::where('id', (int) ($resolved['id'] ?? 0))->where('user_id', $uid)->first();
                if (! $task) {
                    return null;
                }
                $before = [];
                foreach (['title', 'due_date', 'priority', 'status', 'description'] as $col) {
                    if (array_key_exists($col, $resolved)) {
                        $val = $task->{$col};
                        $before[$col] = $val instanceof \DateTimeInterface ? $val->format('Y-m-d') : $val;
                    }
                }
                if (array_key_exists('status', $resolved)) {
                    $before['completed_at'] = $task->completed_at?->toIso8601String();
                }
                if (empty($before)) {
                    return null;
                }

                return ['before' => $before];
            default:
                return null;
        }
    }

    /**
     * Post-execute enrichment (e.g. resolve the created completion id).
     */
    public static function finalize(string $tool, array $resolved, $user, ?array $undo): ?array
    {
        if ($undo === null) {
            return null;
        }
        $tool = AiToolService::normalizeToolName($tool);
        if ($tool === 'routine_complete' && ! empty($undo['pending_completion'])) {
            $row = RoutineCompletion::where('user_id', (int) $user->id)
                ->where('routine_id', (int) $undo['routine_id'])
                ->where('completed_date', $undo['date'])
                ->orderByDesc('id')
                ->first(['id']);
            if (! $row) {
                return null;
            }
            unset($undo['pending_completion']);
            $undo['completion_id'] = $row->id;
        }

        return $undo;
    }

    /**
     * Revert an executed action. Structured result; never guesses.
     */
    public static function revert(AiPendingAction $action, $user): array
    {
        $tool = AiToolService::normalizeToolName($action->tool);
        if (! self::supports($tool)) {
            return ToolError::fail(ToolError::VALIDATION_ERROR, 'This action cannot be undone.');
        }
        $undo = $action->undo_data;
        if (empty($undo) || ! is_array($undo)) {
            return ToolError::fail(ToolError::VALIDATION_ERROR, 'Nothing to undo for this action.');
        }
        $uid = (int) $user->id;

        switch ($tool) {
            case 'task_complete': {
                $task = Task::where('id', (int) ($action->args['id'] ?? 0))->where('user_id', $uid)->first();
                if (! $task) {
                    return ToolError::fail(ToolError::NOT_FOUND, 'Task not found.');
                }
                $task->update([
                    'status' => $undo['before']['status'] ?? 'to_do',
                    'completed_at' => $undo['before']['completed_at'] ?? null,
                ]);

                return ToolError::ok(['message' => "Undone: task '{$task->title}' restored."]);
            }
            case 'reminder_complete': {
                $rem = Reminder::where('id', (int) ($action->args['id'] ?? 0))->where('user_id', $uid)->first();
                if (! $rem) {
                    return ToolError::fail(ToolError::NOT_FOUND, 'Reminder not found.');
                }
                $rem->update([
                    'is_completed' => (bool) ($undo['before']['is_completed'] ?? false),
                    'completed_at' => $undo['before']['completed_at'] ?? null,
                ]);

                return ToolError::ok(['message' => "Undone: reminder '{$rem->title}' restored."]);
            }
            case 'routine_complete': {
                $row = RoutineCompletion::where('id', (int) ($undo['completion_id'] ?? 0))
                    ->where('user_id', $uid)
                    ->first();
                if (! $row) {
                    return ToolError::fail(ToolError::NOT_FOUND, 'Completion record not found.');
                }
                $row->delete();

                return ToolError::ok(['message' => 'Undone: routine completion removed.']);
            }
            case 'task_update': {
                $task = Task::where('id', (int) ($action->args['id'] ?? 0))->where('user_id', $uid)->first();
                if (! $task) {
                    return ToolError::fail(ToolError::NOT_FOUND, 'Task not found.');
                }
                $before = $undo['before'] ?? [];
                $task->update($before);

                return ToolError::ok(['message' => "Undone: task '{$task->title}' restored."]);
            }
            default:
                return ToolError::fail(ToolError::VALIDATION_ERROR, 'This action cannot be undone.');
        }
    }
}
