<?php

namespace App\Http\Controllers;

use App\Models\AiMessage;
use App\Models\AiPendingAction;
use App\Services\AiLogger;
use App\Services\AiToolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AiActionController extends Controller
{
    protected AiToolService $tools;

    public function __construct(AiToolService $tools)
    {
        $this->tools = $tools;
    }

    public function confirm(Request $request, AiPendingAction $action)
    {
        abort_if($action->user_id !== Auth::id(), 403);

        $result = $this->runAction($action, Auth::user());
        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'code' => $result['code'] ?? 'invalid', 'error' => $result['error']], 422);
        }

        $payload = ['ok' => true, 'code' => 'ok', 'deduped' => $result['deduped'] ?? false, 'message' => $result['message'] ?? null];
        if (! empty($result['undoable'])) {
            $payload['undoable'] = true;
            $payload['undo_expires_at'] = $result['undo_expires_at'] ?? null;
        }

        return response()->json($payload);
    }

    /**
     * Limited Undo: revert a recently executed reversible action.
     * Only task_complete / reminder_complete / routine_complete / task_update,
     * only within UNDO_WINDOW_MINUTES, only once.
     */
    public function undo(Request $request, AiPendingAction $action)
    {
        abort_if($action->user_id !== Auth::id(), 403);
        $user = Auth::user();

        return \Illuminate\Support\Facades\DB::transaction(function () use ($action, $user) {
            /** @var AiPendingAction|null $fresh */
            $fresh = AiPendingAction::where('id', $action->id)->lockForUpdate()->first();
            if (! $fresh || (int) $fresh->user_id !== (int) $user->id) {
                return response()->json(['ok' => false, 'code' => \App\Services\AiTooling\ToolError::AUTHORIZATION_ERROR, 'error' => __('Not allowed.')], 403);
            }
            if ($fresh->status !== AiPendingAction::STATUS_EXECUTED) {
                return response()->json(['ok' => false, 'code' => \App\Services\AiTooling\ToolError::VALIDATION_ERROR, 'error' => __('Only executed actions can be undone.')], 422);
            }
            if ($fresh->isUndone()) {
                return response()->json(['ok' => true, 'code' => 'ok', 'deduped' => true, 'message' => __('Already undone.')]);
            }
            if (! $fresh->isUndoable()) {
                $expired = $fresh->isUndoExpired();

                return response()->json([
                    'ok' => false,
                    'code' => $expired ? \App\Services\AiTooling\ToolError::EXPIRED_ACTION : \App\Services\AiTooling\ToolError::VALIDATION_ERROR,
                    'error' => $expired ? __('Undo window expired.') : __('This action cannot be undone.'),
                ], 422);
            }

            $result = \App\Services\AiUndoService::revert($fresh, $user);
            if (! ($result['ok'] ?? false)) {
                return response()->json(['ok' => false, 'code' => $result['code'] ?? 'invalid', 'error' => $result['error']], 422);
            }

            $fresh->undone_at = now();
            $fresh->save();

            \Illuminate\Support\Facades\Log::info('ai.tool.undone', ['user_id' => $user->id, 'tool' => $fresh->tool, 'action_id' => $fresh->id]);
            AiLogger::log('action.undone', ['user_id' => $user->id, 'action_id' => $fresh->id, 'tool' => $fresh->tool]);

            if ($fresh->conversation_id) {
                AiMessage::create([
                    'conversation_id' => $fresh->conversation_id,
                    'role' => 'assistant',
                    'content' => '↩️ ' . ($result['message'] ?? __('Undone.')),
                ]);
            }

            return response()->json(['ok' => true, 'code' => 'ok', 'message' => $result['message']]);
        });
    }

    /**
     * Confirm every pending action at once (oldest first). Each item is
     * validated + executed independently so one failure never blocks the rest.
     */
    public function confirmAll(Request $request)
    {
        $user = Auth::user();
        $pending = AiPendingAction::where('user_id', $user->id)
            ->where('status', AiPendingAction::STATUS_PENDING)
            ->orderBy('id')
            ->limit(AiPendingAction::MAX_OPEN)
            ->get();

        $done = [];
        $failed = [];
        $expired = 0;
        foreach ($pending as $action) {
            $result = $this->runAction($action, $user);
            if ($result['ok'] ?? false) {
                if (! ($result['deduped'] ?? false)) {
                    $done[] = $result['message'] ?? $action->tool;
                }
            } elseif (($result['code'] ?? null) === \App\Services\AiTooling\ToolError::EXPIRED_ACTION) {
                $expired++;
            } else {
                $failed[] = '#' . $action->id . ' ' . ($result['error'] ?? __('failed'));
            }
        }

        AiLogger::log('action.confirm_all', ['user_id' => $user->id, 'done' => count($done), 'failed' => count($failed), 'expired' => $expired]);

        $parts = [];
        if ($done) {
            $parts[] = __(':count action(s) executed.', ['count' => count($done)]);
        }
        if ($failed) {
            $parts[] = __(':count failed: :details', ['count' => count($failed), 'details' => implode('; ', $failed)]);
        }
        if ($expired) {
            $parts[] = __(':count expired — ask Lina again for those.', ['count' => $expired]);
        }
        if (! $done && ! $failed && ! $expired) {
            $parts[] = __('Nothing pending.');
        }

        return response()->json(['ok' => empty($failed), 'message' => implode(' ', $parts), 'details' => $done]);
    }

    /**
     * Cancel every pending action at once. Already-created items stay.
     */
    public function rejectAll(Request $request)
    {
        $user = Auth::user();
        $count = 0;
        AiPendingAction::where('user_id', $user->id)
            ->where('status', AiPendingAction::STATUS_PENDING)
            ->orderBy('id')
            ->limit(AiPendingAction::MAX_OPEN)
            ->get()
            ->each(function (AiPendingAction $action) use (&$count) {
                if ($action->isExpired()) {
                    $action->markExpired();
                } else {
                    $action->status = AiPendingAction::STATUS_REJECTED;
                    $action->save();
                    $count++;
                }
            });

        AiLogger::log('action.reject_all', ['user_id' => $user->id, 'cancelled' => $count]);

        return response()->json(['ok' => true, 'message' => $count > 0 ? __(':count pending action(s) cancelled — nothing changed.', ['count' => $count]) : __('Nothing pending.')]);
    }

    /**
     * Shared single-action runner: dedupe, expiry, re-validate, execute.
     * Returns structured ['ok','code','message'|'error','deduped'?].
     *
     * Security Boundary: confirmation is NOT authorization. The pending row
     * is locked (FOR UPDATE), ownership of the action AND of the linked
     * conversation is re-checked, then the full pipeline re-validates
     * (schema → security → business → ownership → state) on fresh reads
     * before anything executes.
     */
    private function runAction(AiPendingAction $action, $user): array
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($action, $user) {
            /** @var AiPendingAction|null $fresh */
            $fresh = AiPendingAction::where('id', $action->id)->lockForUpdate()->first();
            if (! $fresh) {
                return ['ok' => false, 'code' => \App\Services\AiTooling\ToolError::NOT_FOUND, 'error' => __('No longer valid.')];
            }
            // Ownership of the pending action itself (confirmation ≠ authorization).
            if ((int) $fresh->user_id !== (int) $user->id) {
                AiLogger::log('action.confirm_forbidden', ['user_id' => $user->id, 'action_id' => $fresh->id, 'owner' => $fresh->user_id]);

                return ['ok' => false, 'code' => \App\Services\AiTooling\ToolError::AUTHORIZATION_ERROR, 'error' => __('Not allowed.')];
            }
            // Ownership of the linked conversation (a pending may not be
            // moved across conversations/users by tampering with IDs).
            if ($fresh->conversation_id) {
                $conv = \App\Models\AiConversation::where('id', $fresh->conversation_id)->first(['id', 'user_id']);
                if (! $conv || (int) $conv->user_id !== (int) $user->id) {
                    AiLogger::log('action.confirm_invalid', ['user_id' => $user->id, 'action_id' => $fresh->id, 'tool' => $fresh->tool, 'error' => 'conversation ownership mismatch']);

                    return ['ok' => false, 'code' => \App\Services\AiTooling\ToolError::AUTHORIZATION_ERROR, 'error' => __('No longer valid.')];
                }
            }

            if ($fresh->status === AiPendingAction::STATUS_EXECUTED) {
                return ['ok' => true, 'code' => \App\Services\AiTooling\ToolError::OK, 'deduped' => true, 'message' => __('Already executed.')];
            }
            if ($fresh->status === AiPendingAction::STATUS_EXECUTING) {
                // Another worker holds the claim right now — never double-run.
                return ['ok' => true, 'code' => \App\Services\AiTooling\ToolError::OK, 'deduped' => true, 'message' => __('Already being processed.')];
            }

            if (! $fresh->isActionable()) {
                if ($fresh->isPending() && $fresh->isExpired()) {
                    $fresh->markExpired();
                }
                AiLogger::log('action.confirm_expired', ['user_id' => $user->id, 'action_id' => $fresh->id, 'tool' => $fresh->tool, 'status' => $fresh->status]);

                if ($fresh->status === AiPendingAction::STATUS_REJECTED) {
                    return ['ok' => false, 'code' => \App\Services\AiTooling\ToolError::CANCELLED, 'error' => __('This confirmation was cancelled — nothing changed.')];
                }
                if ($fresh->status === AiPendingAction::STATUS_FAILED) {
                    return ['ok' => false, 'code' => \App\Services\AiTooling\ToolError::EXECUTION_ERROR, 'error' => __('This action failed: :details', ['details' => $fresh->error ?? __('unknown error')])];
                }

                return ['ok' => false, 'code' => \App\Services\AiTooling\ToolError::EXPIRED_ACTION, 'error' => __('This confirmation has expired. Ask Lina again.')];
            }

            // Full pipeline re-validation on fresh reads: a resource that
            // vanished, changed owner, or changed state since proposal can
            // never execute blindly.
            $check = \App\Services\AiTooling\ToolPipeline::revalidateForExecute($user, $fresh->tool, (array) $fresh->args);
            if (! ($check['ok'] ?? false)) {
                AiLogger::log('action.confirm_invalid', ['user_id' => $user->id, 'action_id' => $fresh->id, 'tool' => $fresh->tool, 'code' => $check['code'] ?? null, 'error' => $check['error'] ?? 'No longer valid.']);

                return ['ok' => false, 'code' => $check['code'] ?? 'invalid', 'error' => $check['error'] ?? __('No longer valid.')];
            }

            // Atomic claim: exactly one concurrent confirmer flips
            // pending → executing. Everyone else dedupes above/below.
            $claimed = AiPendingAction::where('id', $fresh->id)
                ->where('status', AiPendingAction::STATUS_PENDING)
                ->update(['status' => AiPendingAction::STATUS_EXECUTING]);
            if (! $claimed) {
                return ['ok' => true, 'code' => \App\Services\AiTooling\ToolError::OK, 'deduped' => true, 'message' => __('Already executed.')];
            }
            $fresh->status = AiPendingAction::STATUS_EXECUTING;

            // Before-image for limited undo (reversible tools only).
            $undo = \App\Services\AiUndoService::supports($fresh->tool)
                ? \App\Services\AiUndoService::capture($fresh->tool, $check['resolved'], $user)
                : null;

            try {
                $result = \App\Services\AiTooling\ToolPipeline::normalizeResult(
                    $fresh->tool,
                    $this->tools->execute($fresh->tool, $check['resolved'], $user)
                );
            } catch (\Throwable $e) {
                // A throwing execute must never leave a zombie claim behind:
                // terminal FAILED with the reason stored, never retryable blindly.
                $fresh->markFailed($e->getMessage());
                \Illuminate\Support\Facades\Log::error('ai.tool.exception', ['user_id' => $user->id, 'tool' => $fresh->tool, 'action_id' => $fresh->id, 'error' => $e->getMessage()]);
                AiLogger::error('action.execute_failed', ['user_id' => $user->id, 'action_id' => $fresh->id, 'tool' => $fresh->tool, 'code' => \App\Services\AiTooling\ToolError::EXECUTION_ERROR, 'error' => $e->getMessage()]);

                return ['ok' => false, 'code' => \App\Services\AiTooling\ToolError::EXECUTION_ERROR, 'error' => __('Execution failed: :details', ['details' => $e->getMessage()])];
            }

            if (! ($result['ok'] ?? false)) {
                $fresh->markFailed($result['message'] ?? 'Execution failed.');
                AiLogger::error('action.execute_failed', ['user_id' => $user->id, 'action_id' => $fresh->id, 'tool' => $fresh->tool, 'code' => $result['code'] ?? null, 'error' => $result['message'] ?? 'Execution failed.']);

                return ['ok' => false, 'code' => $result['code'] ?? 'execute_failed', 'error' => $result['message'] ?? __('Execution failed.')];
            }

            $undo = \App\Services\AiUndoService::finalize($fresh->tool, $check['resolved'], $user, $undo);
            $fresh->status = AiPendingAction::STATUS_EXECUTED;
            $fresh->executed_at = now();
            $fresh->undo_data = $undo;
            $fresh->save();

            \Illuminate\Support\Facades\Log::info('ai.tool.executed', [
                'user_id' => $user->id, 'tool' => $fresh->tool, 'action_id' => $fresh->id,
            ]);
            AiLogger::log('action.executed', ['user_id' => $user->id, 'action_id' => $fresh->id, 'tool' => $fresh->tool, 'message' => $result['message'] ?? null, 'created_id' => $result['id'] ?? null, 'undoable' => $undo !== null]);

            if ($fresh->conversation_id) {
                AiMessage::create([
                    'conversation_id' => $fresh->conversation_id,
                    'role' => 'assistant',
                    'content' => '✅ ' . $result['message'],
                ]);
            }

            $out = ['ok' => true, 'code' => \App\Services\AiTooling\ToolError::OK, 'message' => $result['message']];
            if ($undo !== null) {
                $out['undoable'] = true;
                $out['undo_expires_at'] = now()->addMinutes(AiPendingAction::UNDO_WINDOW_MINUTES)->toIso8601String();
            }

            return $out;
        });
    }

    public function reject(AiPendingAction $action)
    {
        abort_if($action->user_id !== Auth::id(), 403);

        if (! $action->isPending()) {
            return response()->json(['ok' => true, 'deduped' => true]);
        }

        $action->status = AiPendingAction::STATUS_REJECTED;
        $action->save();

        Log::info('ai.tool.rejected', [
            'user_id' => Auth::id(), 'tool' => $action->tool, 'action_id' => $action->id,
        ]);
        AiLogger::log('action.rejected', ['user_id' => Auth::id(), 'action_id' => $action->id, 'tool' => $action->tool]);

        return response()->json(['ok' => true, 'message' => __('Cancelled — nothing changed.')]);
    }
}
