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
            $status = ($result['code'] ?? null) === 'expired' ? 422 : 422;

            return response()->json(['ok' => false, 'error' => $result['error']], $status);
        }

        return response()->json(['ok' => true, 'deduped' => $result['deduped'] ?? false, 'message' => $result['message'] ?? null]);
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
            ->limit(20)
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
            ->limit(20)
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
     * Returns ['ok'=>bool,'message'=>?string,'error'=>?string,'code'=>?string,'deduped'=>bool].
     */
    private function runAction(AiPendingAction $action, $user): array
    {
        if ($action->status === AiPendingAction::STATUS_EXECUTED) {
            return ['ok' => true, 'deduped' => true, 'message' => __('Already executed.')];
        }

        if (! $action->isActionable()) {
            if ($action->isPending() && $action->isExpired()) {
                $action->markExpired();
            }
            AiLogger::log('action.confirm_expired', ['user_id' => $user->id, 'action_id' => $action->id, 'tool' => $action->tool, 'status' => $action->status]);

            return ['ok' => false, 'code' => 'expired', 'error' => __('This confirmation has expired. Ask Lina again.')];
        }

        // Re-validate at execution time (ownership may have changed).
        $check = $this->tools->validateCall($action->tool, (array) $action->args, $user);
        if (! ($check['ok'] ?? false)) {
            AiLogger::log('action.confirm_invalid', ['user_id' => $user->id, 'action_id' => $action->id, 'tool' => $action->tool, 'error' => $check['error'] ?? 'No longer valid.']);

            return ['ok' => false, 'code' => 'invalid', 'error' => $check['error'] ?? __('No longer valid.')];
        }

        $action->status = AiPendingAction::STATUS_CONFIRMED;
        $action->save();

        $result = $this->tools->execute($action->tool, $check['resolved'], $user);

        if (! ($result['ok'] ?? false)) {
            AiLogger::error('action.execute_failed', ['user_id' => $user->id, 'action_id' => $action->id, 'tool' => $action->tool, 'error' => $result['message'] ?? 'Execution failed.']);

            return ['ok' => false, 'code' => 'execute_failed', 'error' => $result['message'] ?? __('Execution failed.')];
        }

        $action->status = AiPendingAction::STATUS_EXECUTED;
        $action->executed_at = now();
        $action->save();

        Log::info('ai.tool.executed', [
            'user_id' => $user->id, 'tool' => $action->tool, 'action_id' => $action->id,
        ]);
        AiLogger::log('action.executed', ['user_id' => $user->id, 'action_id' => $action->id, 'tool' => $action->tool, 'message' => $result['message'] ?? null, 'created_id' => $result['id'] ?? null]);

        if ($action->conversation_id) {
            AiMessage::create([
                'conversation_id' => $action->conversation_id,
                'role' => 'assistant',
                'content' => '✅ ' . $result['message'],
            ]);
        }

        return ['ok' => true, 'message' => $result['message']];
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
