<?php

namespace App\Http\Controllers;

use App\Models\AiMessage;
use App\Models\AiPendingAction;
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

        if ($action->status === AiPendingAction::STATUS_EXECUTED) {
            return response()->json(['ok' => true, 'deduped' => true, 'message' => 'Already executed.']);
        }

        if (! $action->isActionable()) {
            if ($action->isPending() && $action->isExpired()) {
                $action->markExpired();
            }

            return response()->json(['ok' => false, 'error' => 'This confirmation has expired. Ask Lina again.'], 422);
        }

        // Re-validate at execution time (ownership may have changed).
        $check = $this->tools->validateCall($action->tool, (array) $action->args, Auth::user());
        if (! ($check['ok'] ?? false)) {
            return response()->json(['ok' => false, 'error' => $check['error'] ?? 'No longer valid.'], 422);
        }

        $action->status = AiPendingAction::STATUS_CONFIRMED;
        $action->save();

        $result = $this->tools->execute($action->tool, $check['resolved'], Auth::user());

        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'error' => $result['message'] ?? 'Execution failed.'], 422);
        }

        $action->status = AiPendingAction::STATUS_EXECUTED;
        $action->executed_at = now();
        $action->save();

        Log::info('ai.tool.executed', [
            'user_id' => Auth::id(), 'tool' => $action->tool, 'action_id' => $action->id,
        ]);

        if ($action->conversation_id) {
            AiMessage::create([
                'conversation_id' => $action->conversation_id,
                'role' => 'assistant',
                'content' => '✅ ' . $result['message'],
            ]);
        }

        return response()->json(['ok' => true, 'message' => $result['message']]);
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

        return response()->json(['ok' => true, 'message' => 'Cancelled — nothing changed.']);
    }
}
