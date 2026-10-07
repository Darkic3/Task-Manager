<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiPendingAction;
use App\Models\AiPlan;
use App\Models\File;
use App\Models\Note;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use App\Exceptions\AiProviderException;
use App\Services\AiLogger;
use App\Services\AiProviderService;
use App\Services\AiToolService;
use App\Services\LinaFallbackBrain;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AiChatController extends Controller
{
    protected AiProviderService $ai;

    public function __construct(AiProviderService $ai)
    {
        $this->ai = $ai;
    }

    public function index()
    {
        $user = Auth::user();
        $resolved = $this->ai->resolve($user);
        $enabledMap = $this->ai->enabledMap($user);
        $providers = $this->ai->providersForUser($user);

        return view('ai.index', compact('resolved', 'enabledMap', 'providers'));
    }

    /* ── Status endpoint for frontend ── */
    public function status()
    {
        $user = Auth::user();
        $resolved = $this->ai->resolve($user);
        // Never expose API keys to the browser — only provider/model/type.
        $safe = $resolved ? [
            'provider' => $resolved['provider'],
            'model' => $resolved['model'],
            'type' => $resolved['type'] ?? 'openai',
        ] : null;
        $agentReady = (bool) $safe && ($safe['type'] ?? 'openai') === 'openai';

        return response()->json([
            'resolved' => $safe,
            'enabled' => $this->ai->enabledMap($user),
            'agent_ready' => $agentReady,
            'agent_block_reason' => ! $safe ? 'no_provider' : ($agentReady ? null : 'non_openai_provider_needs_openrouter_custom'),
            'providers' => collect($this->ai->providersForUser($user))->map(fn ($c) => [
                'label' => $c['label'],
                'models' => $c['models'],
                'default_model' => $c['default_model'],
            ]),
        ]);
    }

    /* ── Conversation CRUD ── */

    public function conversations()
    {
        $convs = AiConversation::where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->get(['id', 'label', 'updated_at']);

        return response()->json($convs);
    }

    public function createConversation(Request $request)
    {
        $conv = AiConversation::create([
            'user_id' => Auth::id(),
            'label' => $request->input('label', __('New Chat')),
        ]);

        return response()->json($conv);
    }

    public function getConversation(AiConversation $conversation)
    {
        abort_if($conversation->user_id !== Auth::id(), 403);
        $conversation->load('messages');

        return response()->json($conversation);
    }

    public function renameConversation(Request $request, AiConversation $conversation)
    {
        abort_if($conversation->user_id !== Auth::id(), 403);
        $request->validate(['label' => 'required|string|max:120']);
        $conversation->update(['label' => $request->label]);

        return response()->json(['ok' => true]);
    }

    public function deleteConversation(AiConversation $conversation)
    {
        abort_if($conversation->user_id !== Auth::id(), 403);
        $conversation->delete();

        return response()->json(['ok' => true]);
    }

    public function clearConversation(AiConversation $conversation)
    {
        abort_if($conversation->user_id !== Auth::id(), 403);
        $conversation->messages()->delete();
        $conversation->update(['label' => __('New Chat')]);

        return response()->json(['ok' => true]);
    }

    /* ── Non-streaming chat ── */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:8000',
            'history' => 'nullable|array|max:40',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'nullable|string|max:8000',
            'mode' => 'nullable|in:chat,agent',
        ]);

        // Drop empty/whitespace-only turns (tool-only replies) before prompting.
        $history = array_values(array_filter(
            (array) $request->input('history', []),
            fn ($m) => is_array($m) && trim((string) ($m['content'] ?? '')) !== ''
        ));

        $user = Auth::user();
        $resolved = $this->ai->resolve($user);
        // Server is the only authority: tools only in agent mode.
        $agentMode = $request->input('mode', 'chat') === 'agent';
        $rid = AiLogger::newRequestId();
        AiLogger::log('request.received', ['rid' => $rid, 'endpoint' => 'chat', 'user_id' => $user->id, 'mode' => $agentMode ? 'agent' : 'chat', 'message_preview' => $request->message, 'message_len' => mb_strlen($request->message ?? '')]);

        if (! $resolved) {
            AiLogger::log('request.no_provider', ['rid' => $rid, 'user_id' => $user->id, 'mode' => $agentMode ? 'agent' : 'chat']);

            return response()->json(['reply' => __('No AI provider is configured. Go to AI Settings and add an API key for OpenAI, Gemini, Claude, DeepSeek or Meta.')], 200);
        }
        AiLogger::log('request.resolved', ['rid' => $rid, 'user_id' => $user->id, 'mode' => $agentMode ? 'agent' : 'chat', 'provider' => $resolved['provider'], 'model' => $resolved['model'], 'type' => $resolved['type'] ?? 'openai']);

        try {
            $context = $this->buildContext($user);
        } catch (\Exception $e) {
            \Log::error('AI buildContext error', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            AiLogger::error('context.failed', ['rid' => $rid, 'user_id' => $user->id, 'error' => $e->getMessage()]);
            $context = '(Could not load user data)';
        }

        $messages = $this->buildMessages($user, $context, $history, $request->message, $agentMode ? 'agent' : 'chat');

        try {
            $result = $this->callProviderSyncWithTools($resolved, $messages, $user, $agentMode, $rid);
            \Log::info('AI chat response', ['user_id' => $user->id, 'provider' => $resolved['provider'], 'model' => $resolved['model'], 'mode' => $agentMode ? 'agent' : 'chat']);
            $toolNames = collect($result['proposals'] ?? [])->map(fn ($p) => $p['tool'] ?? (isset($p['plan']) ? 'plan_propose' : (isset($p['import']) ? 'workout_plan_propose' : null)))->filter()->all();
            AiLogger::log('request.completed', ['rid' => $rid, 'user_id' => $user->id, 'provider' => $resolved['provider'], 'model' => $resolved['model'], 'mode' => $agentMode ? 'agent' : 'chat', 'has_reply' => isset($result['reply']), 'proposals' => count($result['proposals'] ?? []), 'tools' => $toolNames]);
            $payload = ['model' => $resolved['model'], 'provider' => $resolved['provider']];
            if (isset($result['reply'])) {
                $payload['reply'] = $result['reply'];
            }
            if (isset($result['proposal'])) {
                $payload['proposal'] = $result['proposal'];
            }
            if (isset($result['proposals'])) {
                $payload['proposals'] = $result['proposals'];
            }

            return response()->json($payload);
        } catch (AiProviderException $e) {
            \Log::warning('AI provider failed', ['provider' => $e->provider, 'endpoint' => $e->endpoint, 'status' => $e->httpStatus, 'error' => $e->getMessage()]);
            AiLogger::error('request.failed', ['rid' => $rid, 'user_id' => $user->id, 'provider' => $resolved['provider'], 'model' => $resolved['model'], 'error_type' => 'provider', 'endpoint' => $e->endpoint, 'http_status' => $e->httpStatus, 'error' => $e->getMessage()]);

            return response()->json(['reply' => $this->formatProviderError($e, $resolved), 'error_type' => 'provider', 'provider' => $e->provider, 'endpoint' => $e->endpoint, 'http_status' => $e->httpStatus], 200);
        } catch (\Exception $e) {
            \Log::error('AI chat failed', ['provider' => $resolved['provider'], 'model' => $resolved['model'], 'error' => $e->getMessage()]);
            AiLogger::error('request.failed', ['rid' => $rid, 'user_id' => $user->id, 'provider' => $resolved['provider'], 'model' => $resolved['model'], 'error_type' => 'system', 'error' => $e->getMessage()]);

            return response()->json(['reply' => $this->formatSystemError($e), 'error_type' => 'system'], 200);
        }
    }

    /* ── Streaming ── */
    public function stream(Request $request)
    {
        // This endpoint is consumed by fetch() as SSE. Always answer validation
        // problems as JSON (never a 302 redirect), otherwise fetch follows the
        // redirect to an HTML page and the UI can only say "No response received."
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:8000',
            'conversation_id' => 'nullable|integer',
            'history' => 'nullable|array|max:40',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'nullable|string|max:8000',
            'mode' => 'nullable|in:chat,agent',
        ]);
        if ($validator->fails()) {
            AiLogger::log('request.invalid', ['endpoint' => 'stream', 'user_id' => Auth::id(), 'errors' => $validator->errors()->toArray()]);

            return response()->json([
                'message' => __('Invalid request: :details', ['details' => $validator->errors()->first()]),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Drop empty/whitespace-only turns (e.g. a tool-only reply stored as
        // just newlines) — they carry no usable context and some clients send
        // them without content at all.
        $history = array_values(array_filter(
            (array) $request->input('history', []),
            fn ($m) => is_array($m) && trim((string) ($m['content'] ?? '')) !== ''
        ));

        $user = Auth::user();
        $resolved = $this->ai->resolve($user);
        $agentMode = $request->input('mode', 'chat') === 'agent';
        $rid = AiLogger::newRequestId();
        AiLogger::log('request.received', ['rid' => $rid, 'endpoint' => 'stream', 'user_id' => $user->id, 'mode' => $agentMode ? 'agent' : 'chat', 'message_preview' => $request->message, 'message_len' => mb_strlen($request->message ?? ''), 'has_provider' => (bool) $resolved, 'provider' => $resolved['provider'] ?? null, 'model' => $resolved['model'] ?? null, 'type' => $resolved['type'] ?? null]);

        // Resolve or create conversation
        $convId = $request->input('conversation_id');
        if ($convId) {
            $conversation = AiConversation::where('id', $convId)->where('user_id', $user->id)->first();
        }
        if (empty($conversation)) {
            $conversation = AiConversation::create(['user_id' => $user->id, 'label' => __('New Chat')]);
        }

        // Save user message
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $request->message,
        ]);
        if ($conversation->messages()->count() === 1) {
            $conversation->update(['label' => mb_substr($request->message, 0, 60)]);
        }

        // No provider -> offline fallback (read-only, never creates anything)
        if (! $resolved) {
            AiLogger::log('request.offline_fallback', ['rid' => $rid, 'user_id' => $user->id, 'conversation_id' => $conversation->id, 'reason' => 'no_provider_configured']);
            $fallbackText = (new LinaFallbackBrain($user, $request->message))->respond();
            $offlineConvId = $conversation->id;
            $sseFlush = $this->sseFlushClosure();

            return response()->stream(function () use ($sseFlush, $offlineConvId, $fallbackText) {
                echo 'data: '.json_encode(['model' => 'lina-offline', 'provider' => 'offline', 'conversation_id' => $offlineConvId])."\n\n";
                $sseFlush();
                foreach (mb_str_split($fallbackText, 4) as $chunk) {
                    echo 'data: '.json_encode(['choices' => [['delta' => ['content' => $chunk]]]])."\n\n";
                    $sseFlush();
                    usleep(14000);
                }
                try {
                    AiMessage::create(['conversation_id' => $offlineConvId, 'role' => 'assistant', 'content' => $fallbackText, 'model' => 'lina-offline']);
                    AiConversation::where('id', $offlineConvId)->touch();
                } catch (\Exception $e) {
                }
                echo "data: [DONE]\n\n";
                $sseFlush();
            }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no', 'Connection' => 'keep-alive']);
        }

        try {
            $context = $this->buildContext($user);
        } catch (\Exception $e) {
            \Log::error('AI stream buildContext error', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $context = '(Could not load user data)';
        }

        $messages = $this->buildMessages($user, $context, $history, $request->message, $agentMode ? 'agent' : 'chat');

        // Agent mode needs function-calling: only OpenAI-compatible providers.
        if ($agentMode && ($resolved['type'] ?? 'openai') !== 'openai') {
            AiLogger::log('agent.blocked_non_openai', ['rid' => $rid, 'user_id' => $user->id, 'conversation_id' => $conversation->id, 'provider' => $resolved['provider'], 'model' => $resolved['model'], 'type' => $resolved['type'] ?? null]);
            $msg = __('Agent mode needs an OpenAI-compatible provider (e.g. OpenRouter custom provider). Switch to chat mode, or pick an OpenAI-compatible model in AI Settings — nothing was changed.');
            $conversationId = $conversation->id;
            $model = $resolved['model'];
            $provider = $resolved['provider'];
            $sseFlush = $this->sseFlushClosure();

            return response()->stream(function () use ($sseFlush, $conversationId, $msg, $model, $provider) {
                echo 'data: '.json_encode(['model' => $model, 'provider' => $provider, 'conversation_id' => $conversationId])."\n\n";
                $sseFlush();
                echo 'data: '.json_encode(['choices' => [['delta' => ['content' => $msg]]]])."\n\n";
                $sseFlush();
                try {
                    AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => $msg, 'model' => $model]);
                    AiConversation::where('id', $conversationId)->touch();
                } catch (\Exception $e) {
                }
                echo "data: [DONE]\n\n";
                $sseFlush();
            }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no', 'Connection' => 'keep-alive']);
        }

        // For OpenAI-compatible providers, do true streaming. For Gemini/Anthropic, do sync then chunk.
        if (($resolved['type'] ?? 'openai') === 'openai') {
            AiLogger::log('stream.openai_start', ['rid' => $rid, 'user_id' => $user->id, 'conversation_id' => $conversation->id, 'provider' => $resolved['provider'], 'model' => $resolved['model'], 'agent_mode' => $agentMode]);

            return $this->streamOpenAi($resolved, $messages, $conversation, $agentMode, $rid);
        }
        AiLogger::log('stream.sync_fallback_provider', ['rid' => $rid, 'user_id' => $user->id, 'provider' => $resolved['provider'], 'model' => $resolved['model'], 'type' => $resolved['type'] ?? null]);

        // Non-OpenAI: sync call then simulate streaming
        try {
            $fullText = $this->callProviderSync($resolved, $messages);
        } catch (AiProviderException $e) {
            \Log::warning('AI stream provider failed', ['provider' => $e->provider, 'endpoint' => $e->endpoint, 'status' => $e->httpStatus, 'error' => $e->getMessage()]);
            AiLogger::error('stream.failed', ['rid' => $rid, 'provider' => $e->provider, 'error_type' => 'provider', 'error' => $e->getMessage()]);
            $fullText = $this->formatProviderError($e, $resolved);
        } catch (\Exception $e) {
            \Log::error('AI stream sync failed', ['provider' => $resolved['provider'], 'error' => $e->getMessage()]);
            AiLogger::error('stream.failed', ['rid' => $rid, 'provider' => $resolved['provider'], 'error_type' => 'system', 'error' => $e->getMessage()]);
            $fullText = $this->formatSystemError($e);
        }

        $conversationId = $conversation->id;
        $model = $resolved['model'];
        $provider = $resolved['provider'];
        $sseFlush = $this->sseFlushClosure();

        return response()->stream(function () use ($sseFlush, $conversationId, $fullText, $model, $provider) {
            echo 'data: '.json_encode(['model' => $model, 'provider' => $provider, 'conversation_id' => $conversationId])."\n\n";
            $sseFlush();
            foreach (mb_str_split($fullText, 5) as $chunk) {
                echo 'data: '.json_encode(['choices' => [['delta' => ['content' => $chunk]]]])."\n\n";
                $sseFlush();
                usleep(12000);
            }
            try {
                AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => $fullText, 'model' => $model]);
                AiConversation::where('id', $conversationId)->touch();
            } catch (\Exception $e) {
            }
            echo "data: [DONE]\n\n";
            $sseFlush();
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no', 'Connection' => 'keep-alive']);
    }

    /* ── Provider dispatch (sync) ── */
    private function callProviderSync(array $resolved, array $messages): string
    {
        $provider = $resolved['provider'];
        $type = $resolved['type'] ?? 'openai';
        $key = $resolved['key'];
        $model = $resolved['model'];
        $cfg = $resolved['config'];

        if ($type === 'openai') {
            return $this->callOpenAiSync($key, $cfg['base_url'], $messages, $model);
        }
        if ($type === 'gemini') {
            return $this->callGeminiSync($key, $cfg['base_url'], $messages, $model);
        }
        if ($type === 'anthropic') {
            return $this->callAnthropicSync($key, $cfg['base_url'], $messages, $model);
        }
        throw new \Exception("Unknown provider type: {$type}");
    }

    private function callOpenAiSync(string $key, string $baseUrl, array $messages, string $model): string
    {
        $result = $this->callOpenAiSyncRaw($key, $baseUrl, $messages, $model, null);
        if ($result['text'] === '' && empty($result['tool_calls'])) {
            throw new AiProviderException('Empty response from provider', parse_url($baseUrl, PHP_URL_HOST) ?: 'openai', $baseUrl, null);
        }

        return $result['text'];
    }

    /**
     * Raw OpenAI-compatible sync call with optional tools.
     * Returns ['text'=>string,'tool_calls'=>array].
     */
    private function callOpenAiSyncRaw(string $key, string $baseUrl, array $messages, string $model, ?array $tools): array
    {
        $endpoint = $this->ai->endpointFor($baseUrl, 'openai');
        $payload = $this->ai->openAiPayload($messages, $model, false, $endpoint, $tools);
        $response = $this->ai->postJson($endpoint, $payload, [
            'Authorization' => 'Bearer '.$key,
        ], 60);

        if ($response->failed()) {
            $detail = $this->ai->formatErrorResponse($response);
            throw new AiProviderException($detail, parse_url($endpoint, PHP_URL_HOST) ?: 'openai', $endpoint, $response->status());
        }
        $msg = $response->json('choices.0.message') ?? [];
        $text = trim($msg['content'] ?? '');
        $toolCalls = $msg['tool_calls'] ?? [];

        return ['text' => $text, 'tool_calls' => is_array($toolCalls) ? $toolCalls : []];
    }

    /**
     * Sync dispatch that also supports tool proposals for OpenAI-compatible providers.
     * Returns ['reply'=>string] or ['reply'=>string,'proposal'=>array].
     */
    private function callProviderSyncWithTools(array $resolved, array $messages, $user, bool $withTools = true, ?string $rid = null): array
    {
        $type = $resolved['type'] ?? 'openai';
        if ($type !== 'openai' || ! $withTools) {
            if ($withTools && $type !== 'openai') {
                AiLogger::log('agent.blocked_non_openai', ['rid' => $rid, 'user_id' => $user->id ?? null, 'provider' => $resolved['provider'], 'model' => $resolved['model'], 'type' => $type]);
            }

            return ['reply' => $this->callProviderSync($resolved, $messages)];
        }

        $tools = (new AiToolService)->definitions();
        $raw = $this->callOpenAiSyncRaw($resolved['key'], $resolved['config']['base_url'], $messages, $resolved['model'], $tools);
        AiLogger::log('provider.tool_calls', ['rid' => $rid, 'user_id' => $user->id ?? null, 'provider' => $resolved['provider'], 'model' => $resolved['model'], 'tool_call_count' => count($raw['tool_calls'] ?? []), 'tool_names' => collect($raw['tool_calls'] ?? [])->map(fn ($tc) => $tc['function']['name'] ?? '?')->all(), 'reply_len' => mb_strlen($raw['text'] ?? '')]);

        if (empty($raw['tool_calls'])) {
            if ($raw['text'] === '') {
                AiLogger::error('provider.empty_response', ['rid' => $rid, 'user_id' => $user->id ?? null, 'provider' => $resolved['provider'], 'model' => $resolved['model']]);
                throw new AiProviderException('Empty response from provider', $resolved['provider'], $resolved['config']['base_url'] ?? null, null);
            }

            $out = ['reply' => $raw['text']];
            // Custom proxies often drop tool_calls: if the user pasted a
            // training plan, build the import preview from the raw text.
            $userMessage = '';
            foreach (array_reverse($messages) as $message) {
                if (($message['role'] ?? null) === 'user' && trim((string) ($message['content'] ?? '')) !== '') {
                    $userMessage = (string) $message['content'];
                    break;
                }
            }
            if ($userMessage !== '' && $this->isWorkoutIntent($userMessage)) {
                $fallback = $this->buildWorkoutFallbackProposal($user, null, $userMessage, $raw['text']);
                if (isset($fallback['import'])) {
                    $out['proposals'] = [$fallback];
                    $out['proposal'] = $fallback;
                } elseif (isset($fallback['error'])) {
                    $out['reply'] .= "\n\n⚠️ ".$fallback['error'];
                }
            }

            return $out;
        }

        $first = $raw['tool_calls'][0];
        $proposal = $this->createPendingFromToolCall(
            $user, null,
            $first['function']['name'] ?? '',
            $first['function']['arguments'] ?? '{}'
        );

        $out = [];
        if ($raw['text'] !== '') {
            $out['reply'] = $raw['text'];
        }
        // Keep EVERY tool call: long plans (e.g. one task per day) arrive
        // as several calls, not one. 'proposal' stays for backward compat.
        $proposals = [$proposal];
        foreach (array_slice($raw['tool_calls'], 1) as $tc) {
            $proposals[] = $this->createPendingFromToolCall(
                $user, null,
                $tc['function']['name'] ?? '',
                $tc['function']['arguments'] ?? '{}'
            );
            if (count($proposals) >= AiPendingAction::MAX_OPEN) {
                break; // matches the per-user pending cap
            }
        }
        $out['proposals'] = $proposals;
        $out['proposal'] = $proposal;

        return $out;
    }

    /**
     * Validate + store a tool call as a pending action (no execution).
     * Returns proposal array for the frontend card, or ['error'=>...] on failure.
     */
    private function createPendingFromToolCall($user, $conversationId, string $tool, $argsJson): array
    {
        $tool = AiToolService::normalizeToolName($tool);
        if ($tool === 'plan_propose') {
            return $this->createPlanFromToolCall($user, $conversationId, $argsJson);
        }
        if ($tool === 'workout_plan_propose') {
            return $this->createWorkoutImportFromToolCall($user, $conversationId, $argsJson);
        }

        $service = new AiToolService;
        $args = is_string($argsJson) ? (json_decode($argsJson, true) ?? []) : (array) $argsJson;

        $openActions = AiPendingAction::where('user_id', $user->id)
            ->where('status', AiPendingAction::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->limit(AiPendingAction::MAX_OPEN)
            ->get(['id', 'tool', 'preview']);
        if ($openActions->count() >= AiPendingAction::MAX_OPEN) {
            AiLogger::log('tool.proposal_capped', ['user_id' => $user->id, 'tool' => $tool, 'reason' => 'too_many_pending']);

            return [
                'error' => __('Too many pending confirmations. Confirm or cancel one first.'),
                'code' => 'too_many_pending',
                'limit' => AiPendingAction::MAX_OPEN,
                'pending' => $openActions->map(fn ($a) => [
                    'id' => $a->id,
                    'tool' => $a->tool,
                    'label' => $a->preview['title'] ?? $a->tool,
                ])->all(),
            ];
        }

        $check = $service->validateCall($tool, $args, $user);
        if (! ($check['ok'] ?? false)) {
            AiLogger::log('tool.proposal_invalid', ['user_id' => $user->id, 'tool' => $tool, 'error' => $check['error'] ?? 'Invalid action.', 'args' => $args]);

            return ['error' => $check['error'] ?? __('Invalid action.')];
        }

        $action = AiPendingAction::create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'tool' => $tool,
            'args' => $check['resolved'],
            'preview' => $service->preview($tool, $check['resolved'], $user),
            'status' => AiPendingAction::STATUS_PENDING,
            'expires_at' => now()->addMinutes(AiPendingAction::EXPIRY_MINUTES),
            'idempotency_key' => bin2hex(random_bytes(32)),
        ]);

        \Log::info('ai.tool.proposed', ['user_id' => $user->id, 'tool' => $tool, 'action_id' => $action->id]);
        AiLogger::log('tool.proposed', ['user_id' => $user->id, 'tool' => $tool, 'action_id' => $action->id, 'conversation_id' => $conversationId, 'resolved' => $check['resolved']]);

        return [
            'action_id' => $action->id,
            'tool' => $tool,
            'preview' => $action->preview,
            'expires_at' => $action->expires_at->toIso8601String(),
        ];
    }

    private function toolsForResolved(array $resolved): ?array
    {
        if (($resolved['type'] ?? 'openai') !== 'openai') {
            return null;
        }

        return (new AiToolService)->definitions();
    }

    /**
     * Validate + store a plan_propose call as an AiPlan (no execution).
     * Returns a plan_proposal packet for the structure card.
     */
    private function createPlanFromToolCall($user, $conversationId, $argsJson): array
    {
        $service = new AiToolService;
        $args = is_string($argsJson) ? (json_decode($argsJson, true) ?? []) : (array) $argsJson;

        $open = AiPlan::where('user_id', $user->id)
            ->whereIn('status', [
                AiPlan::STATUS_PROPOSED,
                AiPlan::STATUS_CONFIRMED,
                AiPlan::STATUS_EXECUTING,
            ])
            ->where('expires_at', '>', now())
            ->count();
        if ($open >= 3) {
            AiLogger::log('plan.proposal_capped', ['user_id' => $user->id, 'reason' => 'too_many_open_plans']);

            return ['error' => __('Too many open plans. Finish or cancel one first.')];
        }

        $check = $service->validateCall('plan_propose', $args, $user);
        if (! ($check['ok'] ?? false)) {
            AiLogger::log('plan.proposal_invalid', ['user_id' => $user->id, 'error' => $check['error'] ?? 'Invalid plan.']);

            return ['error' => $check['error'] ?? __('Invalid plan.')];
        }

        $plan = AiPlan::create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'title' => $check['resolved']['title'],
            'structure' => $check['resolved']['structure'],
            'phases' => $service->buildPlanPhases($check['resolved']['structure']),
            'status' => AiPlan::STATUS_PROPOSED,
            'current_phase' => 0,
            'expires_at' => now()->addMinutes(AiPlan::EXPIRY_MINUTES),
            'idempotency_key' => bin2hex(random_bytes(32)),
        ]);

        \Log::info('ai.plan.proposed', ['user_id' => $user->id, 'plan_id' => $plan->id]);
        AiLogger::log('plan.proposed', ['user_id' => $user->id, 'plan_id' => $plan->id, 'conversation_id' => $conversationId, 'title' => $plan->title, 'totals' => $check['resolved']['totals'] ?? []]);

        $controller = app(AiPlanController::class);

        return ['plan' => $controller->serialize($plan)] + ['expires_at' => $plan->expires_at->toIso8601String()];
    }

    /**
     * Validate a workout_plan_propose call and store it as a WorkoutImport
     * preview (no WorkoutPlan is created yet). Returns a
     * workout_import_proposal packet for the import review card.
     */
    private function createWorkoutImportFromToolCall($user, $conversationId, string $argsJson): array
    {
        $service = new AiToolService;
        $args = is_string($argsJson) ? (json_decode($argsJson, true) ?? []) : (array) $argsJson;

        $open = \App\Models\WorkoutImport::where('user_id', $user->id)
            ->where('status', \App\Models\WorkoutImport::PREVIEW)
            ->where('expires_at', '>', now())
            ->count();
        if ($open >= 3) {
            return ['error' => __('Too many open workout imports. Confirm or discard one first.')];
        }

        $check = $service->validateCall('workout_plan_propose', $args, $user);
        if (! ($check['ok'] ?? false)) {
            return ['error' => $check['error'] ?? __('Invalid workout plan.')];
        }

        $structure = app(\App\Services\WorkoutImportService::class)
            ->normalizeStructure($check['resolved']['structure'], $check['resolved']['structure']['start_date'] ?? null);

        $import = \App\Models\WorkoutImport::create([
            'user_id' => $user->id,
            'source_text' => 'AI chat workout plan proposed on '.now()->toDateTimeString(),
            'structure' => $structure,
            'status' => \App\Models\WorkoutImport::PREVIEW,
            'expires_at' => now()->addMinutes(30),
        ]);

        \Log::info('ai.workout.proposed', ['user_id' => $user->id, 'import_id' => $import->id]);

        return [
            'import' => [
                'id' => $import->id,
                'title' => $structure['title'] ?? 'Workout plan',
                'week_number' => $structure['week_number'] ?? null,
                'start_date' => $structure['start_date'] ?? null,
                'preview' => $service->previewWorkoutPlan($check['resolved']),
                'preview_url' => route('workouts.imports.show', $import),
            ],
            'expires_at' => $import->expires_at->toIso8601String(),
        ];
    }

    /**
     * Server-side workout intent detection, independent of the model.
     * Catches pasted training plans even when the provider never emits a
     * tool_call (common with custom proxies like Nara/OpenRouter).
     */
    private function isWorkoutIntent(string $text): bool
    {
        $hits = 0;
        foreach (['week', 'day 1', 'day1', 'pull', 'push', 'legs', 'rir', 'amrap', 'circuit', '×', 'warm-up', 'warmup', 'recovery', 'ست', 'تکرار', 'حرکت', 'برنامه', 'تمرین', 'عضله', 'ورزش', 'dead hang', 'push-up', 'pull-up', 'squat', 'mobility'] as $needle) {
            if (mb_stripos($text, $needle) !== false) {
                $hits++;
            }
        }

        return $hits >= 2 && mb_strlen($text) > 100;
    }

    private function startsTodayCue(string $text): bool
    {
        foreach (['starting today', 'start from today', 'starts today', 'شروع از امروز', 'شروعش از امروز', 'از امروز'] as $needle) {
            if (mb_stripos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function extractWorkoutJson(string $text): ?array
    {
        if (preg_match_all('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $matches)) {
            foreach ($matches[1] as $candidate) {
                $decoded = json_decode($candidate, true);
                if (is_array($decoded) && isset($decoded['days']) && is_array($decoded['days'])) {
                    return $decoded;
                }
            }
        }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded) && isset($decoded['days']) && is_array($decoded['days'])) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Fallback: build a WorkoutImport preview from the raw pasted plan when
     * the model replied with text only (no tool_call). Idempotent per message
     * hash so streaming retries never create duplicates.
     * Returns a workout_import_proposal packet (import or error key).
     */
    private function buildWorkoutFallbackProposal($user, $conversationId, string $userMessage, string $assistantText): array
    {
        $hash = md5($userMessage);
        $recent = \App\Models\WorkoutImport::where('user_id', $user->id)
            ->where('status', \App\Models\WorkoutImport::PREVIEW)
            ->where('expires_at', '>', now())
            ->where('source_text', 'like', '%[msg:'.$hash.']%')
            ->latest()
            ->first();
        if ($recent) {
            $structure = $recent->structure;

            return [
                'import' => [
                    'id' => $recent->id,
                    'title' => $structure['title'] ?? 'Workout plan',
                    'week_number' => $structure['week_number'] ?? null,
                    'start_date' => $structure['start_date'] ?? null,
                    'preview' => (new AiToolService)->previewWorkoutPlan(['structure' => $structure]),
                    'preview_url' => route('workouts.imports.show', $recent),
                ],
                'expires_at' => $recent->expires_at->toIso8601String(),
            ];
        }

        $imports = app(\App\Services\WorkoutImportService::class);
        $anchor = $this->startsTodayCue($userMessage) ? now()->toDateString() : null;

        $structure = null;
        $extracted = $this->extractWorkoutJson($assistantText);
        if ($extracted) {
            try {
                $structure = $imports->normalizeStructure($extracted, $anchor);
            } catch (\Throwable $e) {
                $structure = null;
            }
        }
        if (! $structure) {
            try {
                $structure = $imports->parseWithAi($user, mb_substr($userMessage, 0, 30000), $anchor);
            } catch (\Throwable $e) {
                \Log::warning('ai.workout.fallback_failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

                return ['error' => 'نتونستم ساختار برنامه را استخراج کنم. از مسیر Workouts → Import with AI متن را بده تا همان‌جا parse شود.'];
            }
        }
        if ($anchor && empty($structure['start_date'])) {
            $structure['start_date'] = $anchor;
            $structure = $imports->normalizeStructure($structure, $anchor);
        }

        $import = \App\Models\WorkoutImport::create([
            'user_id' => $user->id,
            'source_text' => 'AI chat workout plan [msg:'.$hash.'] proposed on '.now()->toDateTimeString()."\n\n".mb_substr($userMessage, 0, 8000),
            'structure' => $structure,
            'status' => \App\Models\WorkoutImport::PREVIEW,
            'expires_at' => now()->addMinutes(30),
        ]);

        \Log::info('ai.workout.fallback_proposed', ['user_id' => $user->id, 'import_id' => $import->id]);

        return [
            'import' => [
                'id' => $import->id,
                'title' => $structure['title'] ?? 'Workout plan',
                'week_number' => $structure['week_number'] ?? null,
                'start_date' => $structure['start_date'] ?? null,
                'preview' => (new AiToolService)->previewWorkoutPlan(['structure' => $structure]),
                'preview_url' => route('workouts.imports.show', $import),
            ],
            'expires_at' => $import->expires_at->toIso8601String(),
        ];
    }

    private function callGeminiSync(string $key, string $baseUrl, array $messages, string $model): string
    {
        [$contents, $systemText] = $this->ai->toGeminiContents($messages);

        $body = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => 2048,
                'temperature' => 0.7,
            ],
        ];
        if ($systemText) {
            $body['systemInstruction'] = ['parts' => [['text' => $systemText]]];
        }

        $url = rtrim($baseUrl, '/').'/'.$model.':generateContent?key='.$key;

        $response = $this->ai->postJson($url, $body, [], 60);

        if ($response->failed()) {
            throw new AiProviderException($this->ai->formatErrorResponse($response), parse_url($url, PHP_URL_HOST) ?: 'gemini', $url, $response->status());
        }
        $text = $response->json('candidates.0.content.parts.0.text');
        if (! $text) {
            throw new AiProviderException('Empty response from Gemini', 'gemini', $url, $response->status());
        }

        return trim($text);
    }

    private function callAnthropicSync(string $key, string $baseUrl, array $messages, string $model): string
    {
        [$system, $anthropicMessages] = $this->ai->toAnthropicMessages($messages);

        $payload = [
            'model' => $model,
            'max_tokens' => 2048,
            'temperature' => 0.7,
            'messages' => $anthropicMessages,
        ];
        if ($system) {
            $payload['system'] = $system;
        }

        $response = $this->ai->postJson($baseUrl, $payload, [
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
        ], 60);

        if ($response->failed()) {
            throw new AiProviderException($this->ai->formatErrorResponse($response), parse_url($baseUrl, PHP_URL_HOST) ?: 'anthropic', $baseUrl, $response->status());
        }
        $blocks = $response->json('content');
        $text = '';
        if (is_array($blocks)) {
            foreach ($blocks as $b) {
                if (($b['type'] ?? '') === 'text') {
                    $text .= $b['text'] ?? '';
                }
            }
        }
        if (! $text) {
            throw new AiProviderException('Empty response from Claude', 'anthropic', $baseUrl, $response->status());
        }

        return trim($text);
    }

    /* ── helpers: distinguish provider vs system errors for the UI ── */
    private function formatProviderError(AiProviderException $e, array $resolved): string
    {
        $host = $e->endpoint ? parse_url($e->endpoint, PHP_URL_HOST) : ($resolved['config']['base_url'] ?? $e->provider);
        $status = $e->httpStatus ? " (HTTP {$e->httpStatus})" : '';
        $raw = trim($e->getMessage());

        // Short technical detail (one line)
        $detail = $raw;
        // Keep cURL detail concise — already contains URL
        if (mb_strlen($detail) > 300) {
            $detail = mb_substr($detail, 0, 300) . '…';
        }

        return "🔌 **خطای پرووایدر هوش مصنوعی**{$status}\n\n"
            . "سرور `{$host}` پاسخ نداد (Empty reply).\n\n"
            . "این مشکل **از Task Manager نیست** — از سمت سرویس هوش مصنوعی (پرووایدر مدل) است.\n\n"
            . "**چکار کنید:**\n"
            . "- چند لحظه بعد دوباره تلاش کنید\n"
            . "- در **تنظیمات AI** اتصال و کلید API پرووایدر را بررسی کنید\n\n"
            . "<details><summary>جزئیات فنی</summary>\n\n```\n{$detail}\n```\n</details>";
    }

    private function formatSystemError(\Exception $e): string
    {
        $msg = trim($e->getMessage());
        if (mb_strlen($msg) > 400) $msg = mb_substr($msg, 0, 400) . '…';
        return "⚠️ **خطای داخلی Task Manager**\n\n"
            . "این مشکل از سیستم خود Task Manager است (نه پرووایدر).\n\n"
            . "```\n{$msg}\n```\n\n"
            . "لاگ‌ها را بررسی کنید یا با پشتیبانی تماس بگیرید.";
    }

    private function isProviderConnectionError(\Exception $e): bool
    {
        $msg = $e->getMessage() . ' ' . ($e->getPrevious()?->getMessage() ?? '');
        foreach (['cURL error', 'Empty reply', 'Connection timed out', 'Connection reset', 'Failed to connect', 'Could not resolve host', 'timed out', 'ConnectException'] as $needle) {
            if (str_contains($msg, $needle)) return true;
        }
        // Guzzle ConnectException / CurlException class names
        if ($e instanceof \GuzzleHttp\Exception\ConnectException) return true;
        if ($e->getPrevious() instanceof \GuzzleHttp\Exception\ConnectException) return true;
        return false;
    }

    /* ── OpenAI streaming ── */
    private function streamOpenAi(array $resolved, array $messages, AiConversation $conversation, bool $agentMode = true, ?string $rid = null)
    {
        $key = $resolved['key'];
        $cfg = $resolved['config'];
        $model = $resolved['model'];
        $provider = $resolved['provider'];
        $tools = $agentMode ? $this->toolsForResolved($resolved) : null;

        $client = new Client(['verify' => false, 'timeout' => 60]);
        $response = null;
        $endpoint = $this->ai->endpointFor($cfg['base_url'], 'openai');

        try {
            $response = $client->post($endpoint, [
                'http_errors' => false,
                'headers' => [
                    'Authorization' => 'Bearer '.$key,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => (string) config('app.url'),
                    'X-Title' => (string) config('app.name', 'Task Manager'),
                ],
                'json' => $this->ai->openAiPayload($messages, $model, true, $endpoint, $tools),
                'stream' => true,
            ]);
            $status = $response->getStatusCode();
            if ($status !== 200) {
                $body = (string) $response->getBody();
                $json = json_decode($body, true);
                $detail = (is_array($json) && isset($json['error']))
                    ? $this->ai->formatErrorArray($json['error'])
                    : trim($body);
                throw new AiProviderException("HTTP {$status}: {$detail}", parse_url($endpoint, PHP_URL_HOST) ?: $provider, $endpoint, $status);
            }
        } catch (AiProviderException $e) {
            \Log::warning('AI stream provider failed, falling back to sync', ['provider' => $e->provider, 'endpoint' => $e->endpoint, 'status' => $e->httpStatus, 'error' => $e->getMessage()]);
            AiLogger::error('stream.fallback_provider', ['rid' => $rid, 'provider' => $e->provider, 'error_type' => 'provider', 'error' => $e->getMessage()]);
            $conversationId = $conversation->id;
            $userId = Auth::id();
            $sseFlush = $this->sseFlushClosure();
            $controller = $this;
            $resolvedForError = $resolved;
            try {
                $raw = $this->callOpenAiSyncRaw($key, $cfg['base_url'], $messages, $model, $tools);
                $fullText = $raw['text'] !== '' ? $raw['text'] : $this->formatProviderError(new AiProviderException('Empty response from provider', $provider, $endpoint), $resolvedForError);
                $fallbackTools = $raw['tool_calls'];
            } catch (AiProviderException $e2) {
                $fullText = $this->formatProviderError($e2, $resolvedForError);
                $fallbackTools = [];
            } catch (\Exception $e2) {
                $fullText = $this->isProviderConnectionError($e2) ? $this->formatProviderError(new AiProviderException($e2->getMessage(), parse_url($endpoint, PHP_URL_HOST) ?: $provider, $endpoint, null, 0, $e2), $resolvedForError) : $this->formatSystemError($e2);
                $fallbackTools = [];
            }

            return response()->stream(function () use ($sseFlush, $conversationId, $fullText, $fallbackTools, $model, $provider, $userId, $controller, $agentMode, $messages) {
                echo 'data: '.json_encode(['model' => $model, 'provider' => $provider, 'conversation_id' => $conversationId])."\n\n";
                $sseFlush();
                foreach (mb_str_split($fullText, 5) as $chunk) {
                    echo 'data: '.json_encode(['choices' => [['delta' => ['content' => $chunk]]]])."\n\n";
                    $sseFlush();
                    usleep(12000);
                }
                try {
                    AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => $fullText, 'model' => $model]);
                    AiConversation::where('id', $conversationId)->touch();
                } catch (\Exception $e) {
                }
                if ($agentMode) {
                    $accum = [];
                    foreach (array_values($fallbackTools) as $i => $tc) {
                        $accum[$i] = [
                            'id' => $tc['id'] ?? null,
                            'name' => $tc['function']['name'] ?? null,
                            'arguments' => is_string($tc['function']['arguments'] ?? null)
                                ? $tc['function']['arguments']
                                : json_encode($tc['function']['arguments'] ?? []),
                        ];
                    }
                    $controller->emitToolProposals($accum, $userId, $conversationId, $sseFlush);
                    $controller->emitWorkoutFallbackIfNeeded($accum, $userId, $conversationId, $messages, $fullText, $sseFlush);
                    $controller->emitToolMissedNotice($fullText, $accum, $sseFlush);
                }
                echo "data: [DONE]\n\n";
                $sseFlush();
            }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no', 'Connection' => 'keep-alive']);
        } catch (\Exception $e) {
            if ($this->isProviderConnectionError($e)) {
                $pe = new AiProviderException($e->getMessage(), parse_url($endpoint, PHP_URL_HOST) ?: $provider, $endpoint, null, 0, $e);
                \Log::warning('AI stream provider connection failed, falling back to sync', ['provider' => $pe->provider, 'endpoint' => $pe->endpoint, 'error' => $pe->getMessage()]);
                AiLogger::error('stream.fallback_provider', ['rid' => $rid, 'provider' => $pe->provider, 'error_type' => 'provider', 'error' => $pe->getMessage()]);
                $conversationId = $conversation->id;
                $userId = Auth::id();
                $sseFlush = $this->sseFlushClosure();
                $controller = $this;
                $resolvedForError = $resolved;
                try {
                    $raw = $this->callOpenAiSyncRaw($key, $cfg['base_url'], $messages, $model, $tools);
                    $fullText = $raw['text'] !== '' ? $raw['text'] : $this->formatProviderError(new AiProviderException('Empty response from provider', $provider, $endpoint), $resolvedForError);
                    $fallbackTools = $raw['tool_calls'];
                } catch (AiProviderException $e2) {
                    $fullText = $this->formatProviderError($e2, $resolvedForError);
                    $fallbackTools = [];
                } catch (\Exception $e2) {
                    $fullText = $this->isProviderConnectionError($e2) ? $this->formatProviderError(new AiProviderException($e2->getMessage(), parse_url($endpoint, PHP_URL_HOST) ?: $provider, $endpoint, null, 0, $e2), $resolvedForError) : $this->formatSystemError($e2);
                    $fallbackTools = [];
                }
                return response()->stream(function () use ($sseFlush, $conversationId, $fullText, $fallbackTools, $model, $provider, $userId, $controller, $agentMode, $messages) {
                    echo 'data: '.json_encode(['model' => $model, 'provider' => $provider, 'conversation_id' => $conversationId])."\n\n";
                    $sseFlush();
                    foreach (mb_str_split($fullText, 5) as $chunk) {
                        echo 'data: '.json_encode(['choices' => [['delta' => ['content' => $chunk]]]])."\n\n";
                        $sseFlush();
                        usleep(12000);
                    }
                    try {
                        AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => $fullText, 'model' => $model]);
                        AiConversation::where('id', $conversationId)->touch();
                    } catch (\Exception $e) {
                    }
                    if ($agentMode) {
                        $accum = [];
                        foreach (array_values($fallbackTools) as $i => $tc) {
                            $accum[$i] = [
                                'id' => $tc['id'] ?? null,
                                'name' => $tc['function']['name'] ?? null,
                                'arguments' => is_string($tc['function']['arguments'] ?? null)
                                    ? $tc['function']['arguments']
                                    : json_encode($tc['function']['arguments'] ?? []),
                            ];
                        }
                        $controller->emitToolProposals($accum, $userId, $conversationId, $sseFlush);
                        $controller->emitWorkoutFallbackIfNeeded($accum, $userId, $conversationId, $messages, $fullText, $sseFlush);
                        $controller->emitToolMissedNotice($fullText, $accum, $sseFlush);
                    }
                    echo "data: [DONE]\n\n";
                    $sseFlush();
                }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no', 'Connection' => 'keep-alive']);
            }
            \Log::error('AI stream system failed, falling back to sync', ['provider' => $provider, 'error' => $e->getMessage()]);
            AiLogger::error('stream.fallback_system', ['rid' => $rid, 'provider' => $provider, 'error_type' => 'system', 'error' => $e->getMessage()]);
            $conversationId = $conversation->id;
            $userId = Auth::id();
            $sseFlush = $this->sseFlushClosure();
            $controller = $this;
            $resolvedForError = $resolved;
            try {
                $raw = $this->callOpenAiSyncRaw($key, $cfg['base_url'], $messages, $model, $tools);
                $fullText = $raw['text'] !== '' ? $raw['text'] : $this->formatSystemError(new \Exception('Empty response from provider'));
                $fallbackTools = $raw['tool_calls'];
            } catch (AiProviderException $e2) {
                $fullText = $this->formatProviderError($e2, $resolvedForError);
                $fallbackTools = [];
            } catch (\Exception $e2) {
                $fullText = $this->formatSystemError($e2);
                $fallbackTools = [];
            }

            return response()->stream(function () use ($sseFlush, $conversationId, $fullText, $fallbackTools, $model, $provider, $userId, $controller, $agentMode, $messages) {
                echo 'data: '.json_encode(['model' => $model, 'provider' => $provider, 'conversation_id' => $conversationId])."\n\n";
                $sseFlush();
                foreach (mb_str_split($fullText, 5) as $chunk) {
                    echo 'data: '.json_encode(['choices' => [['delta' => ['content' => $chunk]]]])."\n\n";
                    $sseFlush();
                    usleep(12000);
                }
                try {
                    AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => $fullText, 'model' => $model]);
                    AiConversation::where('id', $conversationId)->touch();
                } catch (\Exception $e) {
                }
                if ($agentMode) {
                    $accum = [];
                    foreach (array_values($fallbackTools) as $i => $tc) {
                        $accum[$i] = [
                            'id' => $tc['id'] ?? null,
                            'name' => $tc['function']['name'] ?? null,
                            'arguments' => is_string($tc['function']['arguments'] ?? null)
                                ? $tc['function']['arguments']
                                : json_encode($tc['function']['arguments'] ?? []),
                        ];
                    }
                    $controller->emitToolProposals($accum, $userId, $conversationId, $sseFlush);
                    $controller->emitWorkoutFallbackIfNeeded($accum, $userId, $conversationId, $messages, $fullText, $sseFlush);
                    $controller->emitToolMissedNotice($fullText, $accum, $sseFlush);
                }
                echo "data: [DONE]\n\n";
                $sseFlush();
            }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no', 'Connection' => 'keep-alive']);
        }

        $body = $response->getBody();
        $conversationId = $conversation->id;
        $userId = Auth::id();
        $sseFlush = $this->sseFlushClosure();

        return response()->stream(function () use ($body, $model, $provider, $userId, $conversationId, $sseFlush, $agentMode, $key, $endpoint, $messages, $tools) {
            echo 'data: '.json_encode(['model' => $model, 'provider' => $provider, 'conversation_id' => $conversationId])."\n\n";
            $sseFlush();
            $buffer = '';
            $accumulatedText = '';
            $toolAccum = [];
            $controller = $this;
            try {
                while (! $body->eof()) {
                    $chunk = $body->read(256);
                    $buffer .= $chunk;
                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 1);
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }
                        if (! str_starts_with($line, 'data: ')) {
                            continue;
                        }
                        $data = substr($line, 6);
                        if ($data === '[DONE]') {
                            if (trim($accumulatedText) !== '') {
                                AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => trim($accumulatedText), 'model' => $model]);
                                AiConversation::where('id', $conversationId)->touch();
                            }
                            if ($agentMode) {
                                $controller->emitToolProposals($toolAccum, $userId, $conversationId, $sseFlush);
                                $controller->emitWorkoutFallbackIfNeeded($toolAccum, $userId, $conversationId, $messages, $accumulatedText, $sseFlush);
                                $controller->emitToolMissedNotice($accumulatedText, $toolAccum, $sseFlush);
                            }
                            echo "data: [DONE]\n\n";
                            $sseFlush();

                            return;
                        }
                        $decoded = json_decode($data, true);
                        if (is_array($decoded) && isset($decoded['error'])) {
                            $providerError = $this->ai->formatErrorArray($decoded['error']);
                            // Mid-stream provider error (e.g. Nara returns HTTP 200 with
                            // {"error":{"type":"upstream_error",...}} on streaming only).
                            // If nothing was streamed yet, fall back to a sync call which
                            // often still works, instead of showing the raw error.
                            if (trim($accumulatedText) === '' && empty($toolAccum)) {
                                \Log::warning('AI stream mid-stream provider error, falling back to sync', ['provider' => $provider, 'model' => $model, 'error' => $providerError, 'error_type' => 'provider']);
                                try {
                                    $raw = $this->callOpenAiSyncRaw($key, $endpoint, $messages, $model, $tools);
                                    $fallbackText = trim($raw['text'] ?? '') !== '' ? $raw['text'] : null;
                                    $fallbackTools = $raw['tool_calls'] ?? [];
                                } catch (AiProviderException $e2) {
                                    $fallbackText = null;
                                    $fallbackTools = [];
                                    \Log::warning('AI stream sync fallback also failed (provider)', ['provider' => $e2->provider, 'error' => $e2->getMessage(), 'error_type' => 'provider']);
                                } catch (\Exception $e2) {
                                    $fallbackText = null;
                                    $fallbackTools = [];
                                    \Log::warning('AI stream sync fallback also failed (system)', ['provider' => $provider, 'error' => $e2->getMessage(), 'error_type' => 'system']);
                                }
                                if ($fallbackText !== null && trim($fallbackText) !== '') {
                                    $accumulatedText = $fallbackText;
                                    foreach (mb_str_split($fallbackText, 5) as $chunk) {
                                        echo 'data: '.json_encode(['choices' => [['delta' => ['content' => $chunk]]]])."\n\n";
                                        $sseFlush();
                                        usleep(12000);
                                    }
                                    try {
                                        AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => $fallbackText, 'model' => $model]);
                                        AiConversation::where('id', $conversationId)->touch();
                                    } catch (\Exception $e) {
                                    }
                                        if ($agentMode) {
                                            $accum = [];
                                            foreach (array_values($fallbackTools) as $i => $tc) {
                                                $accum[$i] = [
                                                    'id' => $tc['id'] ?? null,
                                                    'name' => $tc['function']['name'] ?? null,
                                                    'arguments' => is_string($tc['function']['arguments'] ?? null)
                                                        ? $tc['function']['arguments']
                                                        : json_encode($tc['function']['arguments'] ?? []),
                                                ];
                                            }
                                            $controller->emitToolProposals($accum, $userId, $conversationId, $sseFlush);
                                            $controller->emitWorkoutFallbackIfNeeded($accum, $userId, $conversationId, $messages, $fallbackText, $sseFlush);
                                            $controller->emitToolMissedNotice($fallbackText, $accum, $sseFlush);
                                        }
                                    echo "data: [DONE]\n\n";
                                    $sseFlush();

                                    return;
                                }
                            }
                            if (trim($accumulatedText) !== '') {
                                AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => trim($accumulatedText), 'model' => $model]);
                                AiConversation::where('id', $conversationId)->touch();
                            }
                            echo 'data: '.json_encode(['error' => $providerError, 'error_type' => 'provider', 'provider' => $provider, 'endpoint' => $endpoint])."\n\n";
                            $sseFlush();
                            echo "data: [DONE]\n\n";
                            $sseFlush();

                            return;
                        }
                        $delta = $decoded['choices'][0]['delta'] ?? [];
                        $token = $delta['content'] ?? '';
                        if ($token) {
                            $accumulatedText .= $token;
                        }
                        foreach (($delta['tool_calls'] ?? []) as $tc) {
                            $idx = (int) ($tc['index'] ?? 0);
                            $toolAccum[$idx]['id'] = $tc['id'] ?? ($toolAccum[$idx]['id'] ?? null);
                            $toolAccum[$idx]['name'] = $tc['function']['name'] ?? ($toolAccum[$idx]['name'] ?? null);
                            $toolAccum[$idx]['arguments'] = ($toolAccum[$idx]['arguments'] ?? '').($tc['function']['arguments'] ?? '');
                        }
                        echo "data: {$data}\n\n";
                        $sseFlush();
                    }
                }
            } catch (\Exception $e) {
                \Log::error('AI stream read error', ['model' => $model, 'provider' => $provider, 'user_id' => $userId, 'error' => $e->getMessage()]);
                echo 'data: '.json_encode(['error' => __('Stream interrupted.')])."\n\n";
                $sseFlush();
            }
            // Persist if we exited without [DONE]
            if (trim($accumulatedText) !== '') {
                $accumulatedText = trim($accumulatedText);
                try {
                    $exists = AiMessage::where('conversation_id', $conversationId)->where('role', 'assistant')->where('content', $accumulatedText)->exists();
                    if (! $exists) {
                        AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => $accumulatedText, 'model' => $model]);
                        AiConversation::where('id', $conversationId)->touch();
                    }
                } catch (\Exception $e) {
                }
            }
            $controller->emitToolProposals($agentMode ? $toolAccum : [], $userId, $conversationId, $sseFlush);
            if ($agentMode) {
                $controller->emitWorkoutFallbackIfNeeded($toolAccum, $userId, $conversationId, $messages, $accumulatedText, $sseFlush);
                $controller->emitToolMissedNotice($accumulatedText, $toolAccum, $sseFlush);
            }
            echo "data: [DONE]\n\n";
            $sseFlush();
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no', 'Connection' => 'keep-alive']);
    }

    /**
     * Streaming fallback: when the provider returned text only (no tool_call)
     * but the user pasted a training plan, build the import preview from the
     * raw text and emit it as a workout_import_proposal packet.
     */
    public function emitWorkoutFallbackIfNeeded(array $toolAccum, int $userId, int $conversationId, array $messages, string $assistantText, callable $sseFlush): void
    {
        if (! empty($toolAccum)) {
            return;
        }
        $userMessage = '';
        foreach (array_reverse($messages) as $message) {
            if (($message['role'] ?? null) === 'user' && trim((string) ($message['content'] ?? '')) !== '') {
                $userMessage = (string) $message['content'];
                break;
            }
        }
        if ($userMessage === '' || ! $this->isWorkoutIntent($userMessage)) {
            return;
        }
        $user = User::find($userId);
        if (! $user) {
            return;
        }
        $packet = $this->buildWorkoutFallbackProposal($user, $conversationId, $userMessage, $assistantText);
        echo 'data: '.json_encode(['type' => 'workout_import_proposal'] + $packet)."\n\n";
        $sseFlush();
    }

    /**
     * Turn accumulated tool_calls into pending actions and emit SSE proposals.
     */
    public function emitToolProposals(array $toolAccum, int $userId, int $conversationId, callable $sseFlush, ?string $rid = null): void
    {
        if (empty($toolAccum)) {
            AiLogger::log('stream.no_tool_calls', ['user_id' => $userId, 'conversation_id' => $conversationId, 'rid' => $rid]);

            return;
        }
        $user = User::find($userId);
        if (! $user) {
            return;
        }
        AiLogger::log('stream.tool_calls_received', ['rid' => $rid, 'user_id' => $userId, 'conversation_id' => $conversationId, 'count' => count($toolAccum), 'names' => collect(array_values($toolAccum))->map(fn ($tc) => $tc['name'] ?? '?')->all()]);
        foreach (array_values($toolAccum) as $tc) {
            $name = $tc['name'] ?? null;
            if (! $name) {
                continue;
            }
            $proposal = $this->createPendingFromToolCall($user, $conversationId, $name, $tc['arguments'] ?? '{}');
            $normalizedName = AiToolService::normalizeToolName($name);
            $packetType = $normalizedName === 'plan_propose'
                ? 'plan_proposal'
                : ($normalizedName === 'workout_plan_propose' ? 'workout_import_proposal' : 'tool_proposal');
            AiLogger::log('stream.proposal_emitted', ['rid' => $rid, 'user_id' => $userId, 'conversation_id' => $conversationId, 'tool' => $normalizedName, 'packet' => $packetType, 'has_error' => isset($proposal['error']), 'action_id' => $proposal['action_id'] ?? null, 'plan_id' => $proposal['plan']['id'] ?? null]);
            echo 'data: '.json_encode(['type' => $packetType] + $proposal)."\n\n";
            $sseFlush();
        }
    }

    /**
     * Agent mode but the model answered with plain text that looks like
     * tool JSON (no real function call): tell the user nothing was created
     * instead of leaving raw JSON on screen. No pending action is stored.
     */
    public function emitToolMissedNotice(string $text, array $toolAccum, callable $sseFlush, ?string $rid = null): void
    {
        if (! empty($toolAccum) || trim($text) === '') {
            return;
        }
        // At least two tool-shaped keys -> likely a pasted tool call, not a code example.
        $hits = preg_match_all('/"(title|description|due_date|project_id|projectId|dueDate)"\s*:/', $text);
        if ($hits < 2 || ! str_contains($text, '{')) {
            AiLogger::log('stream.text_only_no_tools', ['rid' => $rid, 'reply_len' => mb_strlen($text)]);

            return;
        }
        AiLogger::log('stream.tool_missed_text_json', ['rid' => $rid, 'reply_preview' => $text]);
        $notice = '⚠️ I answered in text instead of creating anything — nothing was saved. Please send the request again (or pick a model with function-calling support).';
        echo 'data: '.json_encode(['choices' => [['delta' => ['content' => "\n\n".$notice]]]])."\n\n";
        $sseFlush();
    }

    /* ── Helpers ── */
    private function buildMessages($user, string $context, array $history, string $newMessage, string $mode = 'chat'): array
    {
        $today = now()->format('l, F j, Y');
        $creatorName = $user->name;
        $modeBlock = $mode === 'agent'
            ? <<<'AGENT'
            MODE: AGENT — you can act on the workspace via tools.
            - SINGLE items: task_create/update/complete/delete, reminder_*, note_*, project_create, project_add_member, note_link, report_generate, checklist_*, routine_create/complete/delete/log. Destructive deletes need no extra warning text because the app shows a confirmation card.
            - TASKS BY NAME: task_update/complete/delete and checklist_add accept a task title (+ optional project scope) and resolve it to the right record automatically — pass titles straight from the user's message or the workspace context below, NEVER ask the user for numeric IDs. For bulk edits (e.g. updating 20 tasks at once), emit one call per task in the same turn; the app confirms them together.
            - COLLABORATORS: project_add_member finds the person by email, name, or user ID — prefer email when the user gives one. In plans, put collaborator emails/names in each project's members[] so they join when the project is built.
            - NOTE LINKS: note_link connects a note to a project/task/note so it appears in backlinks — use it when the user says a note "belongs to" or "is about" something.
            - REPORTS: report_generate builds a read-only workspace summary for today/week/month (nothing changes). Use it when the user asks "how am I doing / گزارش بده". After it runs, explain the numbers in 2-4 bullets.
            - PROJECT BUILDS & STRATEGY: When asked to plan, break down, or architect projects/goals, first provide a concise 2-4 bullet strategic overview in your text reply, and then call plan_propose ONCE with EVERYTHING.
              * MULTIPLE independent projects (e.g. "make projects A, B, C"): put them ALL in the projects[] array in ONE call — never one call per project, never project+subprojects for this.
              * ONE project with sub-divisions: use project + subprojects (max 3).
              * Reminders/events (e.g. "today at 18:00 at Laleh bazaar") go in reminders[] with date YYYY-MM-DD, time HH:MM and location — SAME call, not a separate tool.
              * Notes go in notes[] — SAME call.
              * Caps per plan: 5 projects, 30 tasks, 100 subtasks, 10 reminders, 10 notes, 5 members per project (emails or names). Persian dates: امروز=today, فردا=tomorrow, پس‌فردا=+2 days; "ساعت 6 بعد از ظهر/عصر"=18:00, "ساعت 9 صبح"=09:00. Compute YYYY-MM-DD from today's date above.
            - PLAN REVISIONS / EDITS: When the user asks for changes, edits, additions, or removals in a proposed plan (e.g. "تسک فلان رو تغییر بده", "X رو اضافه کن"), acknowledge the refinement and immediately emit an updated plan_propose with the revised structure.
            - WORKOUT PLANS: For ANY pasted training plan (with DAYs, sets×reps, RIR, circuits, warm-ups, tempo): call workout_plan_propose ONCE with the FULL 7-day structure — never plan_propose, never project_create, never routine_create, never many single calls. Preserve every movement and detail; do not summarize exercises into one task.
            - DAY MAPPING: days[] must arrive in execution order (index 0 = DAY 1). When the user says "starting today / شروع از امروز", set start_date to today's date (given above) so DAY 1 maps to today — even if today is Sunday. Never force DAY 1 back to Saturday in that case.
            - WEEK NUMBER: if the pasted text says one week (e.g. WEEK 13) but the user explicitly says another (e.g. week 14), ASK in text which week number to use before calling the tool. If the user already confirmed, use the confirmed number.
            - PARSING: "4×6–8" → target_sets 4, rep_min 6, rep_max 8. "30–45s / 2min / 3min" → duration_seconds. "7kg / 2kg / 9kg" → target_weight. "RIR 1–2" → target_rir. "3s پایین رفتن" → tempo/notes. "/ پا / سمت" → side_mode per_side. "1×MAX" → is_amrap true. "Circuit ×3 + استراحت بین دورها 2min" → is_circuit true + circuit_rounds + circuit_rest_seconds. "❌ movement" → rules (excluded), not exercises. "جایگزین: X" → notes. Safety/STOP warnings → notes + rules. Keep Persian and English names exactly as written.
            - routine_create with frequency weekly REQUIRES days (lowercase, e.g. ["thursday"]); never omit it. Different workouts on different days = separate calls, one weekday each. Compute the weekday from today's date.
            - TRACKING: routines support tracking_mode none|value|sets. If the user wants numbers logged (weight, wake time, reps) but the unit kind is unknown, ASK in text first (e.g. "in what unit?"), then call with the right value_kind/value_unit. Steps of sets-mode routines carry target_sets; exercises go to subtasks/steps, one per item.
            - Keep every single title SHORT: task/subtask/routine titles under 120 chars, descriptions under 500 chars. For workouts, keep every exercise as its own item — never paste a whole program into one argument.
            - Compute due_dates/start_date/reminder date+time yourself from today's date (given above). Max per plan: 5 projects, 3 sub-projects each, 30 tasks, 100 subtasks, 10 reminders, 10 notes, 7 routines. Max per workout: 7 days, 40 exercises per day.
            - Use exact snake_case argument names from the schema (project_id, due_date, task_id). Omit project_id when unsure — the task will then simply have no project (allowed).
            AGENT
            : <<<'CHAT'
            MODE: CHAT — read-only discussion. You cannot create, edit or delete anything; there are no tools in this mode.
            - Talk about the user's projects, tasks, notes, reminders, routines, workouts, explain, summarize and advise.
            - For workout plans: explain the structure, differences, and what you would build, but do not create anything here. Ask them to switch to Agent mode (🛠 اجرا) or use Workouts → Import with AI so it can be built with confirmation.
            - If the user asks you to create or change something, explain briefly what you would do and ask them to switch to Agent mode (🛠 اجرا) so you can do it with their confirmation.
            CHAT;
        $langDirective = app()->getLocale() === 'fa'
            ? "- The user's interface language is Persian (Farsi). Respond in fluent, polite Persian (فارسی روان و دقیق) unless the user asks in English or another language."
            : "- The user's interface language is English. Respond in clear, natural English unless the user asks in another language.";

        $systemPrompt = <<<PROMPT
You are Lina, a smart personal AI assistant built into this Task Manager app by {$creatorName}.
If asked your name, say your name is Lina. If asked who created or built you, say you were created by {$creatorName}.
Today is {$today}.

You can help the user with:
- Their workspace data: tasks, projects, notes, reminders, routines, and files (full data provided below)
- Coding, programming, software development, and any technical / technology questions
- General knowledge

Guidelines:
{$langDirective}
- Use markdown formatting — bullet points, code blocks, bold headings where helpful
- For code, always use fenced code blocks with the language specified
- For workspace data, only refer to what is in the context below — do not invent data
- Be concise and practical
{$modeBlock}

--- USER WORKSPACE DATA ---
{$context}
--- END WORKSPACE DATA ---
PROMPT;
        $messages = [['role' => 'system', 'content' => $this->cleanUtf8($systemPrompt)]];
        foreach ($history as $turn) {
            $messages[] = ['role' => $turn['role'], 'content' => $this->cleanUtf8($turn['content'])];
        }
        $messages[] = ['role' => 'user', 'content' => $this->cleanUtf8($newMessage)];

        return $messages;
    }

    private function sseFlushClosure(): callable
    {
        return function () {
            if (ob_get_level() > 0) {
                ob_flush();
            } flush();
        };
    }

    private function cleanUtf8(string $str): string
    {
        $clean = mb_convert_encoding($str, 'UTF-8', 'UTF-8');

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean);
    }

    private function buildContext($user): string
    {
        $projects = Project::where('user_id', $user->id)->get(['name', 'status', 'end_date', 'budget']);
        $projectLines = $projects->map(fn ($p) => "- {$p->name} (status: {$p->status}".($p->end_date ? ", due: {$p->end_date->format('Y-m-d')}" : '').')')->join("\n");

        $tasks = Task::where('user_id', $user->id)->with('project:id,name')->get(['title', 'status', 'priority', 'due_date', 'project_id']);
        $taskLines = $tasks->map(fn ($t) => "- [{$t->status}] {$t->title} (priority: {$t->priority}".($t->due_date ? ", due: {$t->due_date}" : '').($t->project ? ", project: {$t->project->name}" : '').')')->join("\n");

        $notes = Note::where('user_id', $user->id)->get(['title', 'content', 'tags']);
        $noteLines = $notes->map(function ($n) {
            $tags = is_array($n->tags) ? implode(', ', $n->tags) : ($n->tags ?? '');

            return "- {$n->title}".($tags ? " [tags: {$tags}]" : '').': '.strip_tags(substr($n->content ?? '', 0, 120));
        })->join("\n");

        $reminders = Reminder::where('user_id', $user->id)->get(['title', 'date', 'time', 'priority', 'is_completed', 'tags']);
        $reminderLines = $reminders->map(function ($r) {
            $tags = is_array($r->tags) ? implode(', ', $r->tags) : ($r->tags ?? '');
            $status = $r->is_completed ? 'done' : 'pending';
            $when = $r->date ? $r->date->format('Y-m-d').($r->time ? " {$r->time}" : '') : '';

            return "- [{$status}] {$r->title}".($when ? " at {$when}" : '').($tags ? " [tags: {$tags}]" : '');
        })->join("\n");

        $routines = Routine::where('user_id', $user->id)->get(['title', 'frequency']);
        $routineLines = $routines->map(fn ($r) => "- {$r->title} ({$r->frequency})")->join("\n");

        $files = File::where('user_id', $user->id)->get(['name', 'type']);
        $fileLines = $files->map(fn ($f) => "- {$f->name} (type: {$f->type})")->join("\n");

        return implode("\n\n", array_filter([
            $projects->count() ? "PROJECTS ({$projects->count()}):\n{$projectLines}" : null,
            $tasks->count() ? "TASKS ({$tasks->count()}):\n{$taskLines}" : null,
            $notes->count() ? "NOTES ({$notes->count()}):\n{$noteLines}" : null,
            $reminders->count() ? "REMINDERS ({$reminders->count()}):\n{$reminderLines}" : null,
            $routines->count() ? "ROUTINES ({$routines->count()}):\n{$routineLines}" : null,
            $files->count() ? "FILES ({$files->count()}):\n{$fileLines}" : null,
        ]));
    }

    /* ── Debug endpoint: one place to diagnose "agent said X but created nothing" ── */
    public function debug()
    {
        $user = Auth::user();
        $resolved = $this->ai->resolve($user);
        $enabled = $this->ai->enabledMap($user);

        // Never expose keys — only whether each provider has one.
        $safeResolved = $resolved ? [
            'provider' => $resolved['provider'],
            'model' => $resolved['model'],
            'type' => $resolved['type'] ?? 'openai',
        ] : null;

        $pending = AiPendingAction::where('user_id', $user->id)
            ->latest()->limit(AiPendingAction::MAX_OPEN)
            ->get(['id', 'tool', 'status', 'expires_at', 'created_at']);
        $plans = AiPlan::where('user_id', $user->id)
            ->latest()->limit(3)
            ->get(['id', 'title', 'status', 'current_phase', 'expires_at', 'created_at']);

        return response()->json([
            'now' => now()->toIso8601String(),
            'resolved' => $safeResolved,
            'enabled' => $enabled,
            'note' => $safeResolved ? null : __('No provider configured — Lina runs in offline read-only mode and can never create anything.'),
            'agent_ready' => (bool) $safeResolved && ($safeResolved['type'] ?? 'openai') === 'openai',
            'agent_block_reason' => ! $safeResolved ? 'no_provider' : ((($safeResolved['type'] ?? 'openai') !== 'openai') ? 'non_openai_provider_needs_openrouter_custom' : null),
            'recent_pending_actions' => $pending,
            'recent_plans' => $plans,
            'ai_log_tail' => AiLogger::tail(80),
        ]);
    }
}
