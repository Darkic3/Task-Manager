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

        return response()->json(['ok' => true, 'code' => 'ok', 'deduped' => $result['deduped'] ?? false, 'message' => $result['message'] ?? null]);
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
            } elseif (($result['code'] ?? null) === 'expired') {
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

            if (! $fresh->isActionable()) {
                if ($fresh->isPending() && $fresh->isExpired()) {
                    $fresh->markExpired();
                }
                AiLogger::log('action.confirm_expired', ['user_id' => $user->id, 'action_id' => $fresh->id, 'tool' => $fresh->tool, 'status' => $fresh->status]);

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

            // Atomic claim: only one concurrent confirmer moves pending → confirmed.
            $claimed = AiPendingAction::where('id', $fresh->id)
                ->where('status', AiPendingAction::STATUS_PENDING)
                ->update(['status' => AiPendingAction::STATUS_CONFIRMED]);
            if (! $claimed) {
                return ['ok' => true, 'code' => \App\Services\AiTooling\ToolError::OK, 'deduped' => true, 'message' => __('Already executed.')];
            }
            $fresh->status = AiPendingAction::STATUS_CONFIRMED;

            $result = \App\Services\AiTooling\ToolPipeline::normalizeResult(
                $fresh->tool,
                $this->tools->execute($fresh->tool, $check['resolved'], $user)
            );

            if (! ($result['ok'] ?? false)) {
                AiLogger::error('action.execute_failed', ['user_id' => $user->id, 'action_id' => $fresh->id, 'tool' => $fresh->tool, 'code' => $result['code'] ?? null, 'error' => $result['message'] ?? 'Execution failed.']);

                return ['ok' => false, 'code' => $result['code'] ?? 'execute_failed', 'error' => $result['message'] ?? __('Execution failed.')];
            }

            $fresh->status = AiPendingAction::STATUS_EXECUTED;
            $fresh->executed_at = now();
            $fresh->save();

            \Illuminate\Support\Facades\Log::info('ai.tool.executed', [
                'user_id' => $user->id, 'tool' => $fresh->tool, 'action_id' => $fresh->id,
            ]);
            AiLogger::log('action.executed', ['user_id' => $user->id, 'action_id' => $fresh->id, 'tool' => $fresh->tool, 'message' => $result['message'] ?? null, 'created_id' => $result['id'] ?? null]);

            if ($fresh->conversation_id) {
                AiMessage::create([
                    'conversation_id' => $fresh->conversation_id,
                    'role' => 'assistant',
                    'content' => '✅ ' . $result['message'],
                ]);
            }

            return ['ok' => true, 'message' => $result['message']];
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
