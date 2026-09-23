<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\File;
use App\Models\Note;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\Routine;
use App\Models\Task;
use App\Services\AiProviderService;
use App\Services\LinaFallbackBrain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        return response()->json([
            'resolved' => $this->ai->resolve($user),
            'enabled'  => $this->ai->enabledMap($user),
            'providers'=> collect($this->ai->providersForUser($user))->map(fn($c) => [
                'label' => $c['label'],
                'models'=> $c['models'],
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
            'label'   => $request->input('label', 'New Chat'),
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
        $conversation->update(['label' => 'New Chat']);
        return response()->json(['ok' => true]);
    }

    /* ── Non-streaming chat ── */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:8000',
            'history' => 'nullable|array|max:40',
            'history.*.role'    => 'required|in:user,assistant',
            'history.*.content' => 'required|string|max:8000',
            'mode' => 'nullable|in:chat,agent',
        ]);

        $user = Auth::user();
        $resolved = $this->ai->resolve($user);
        // Server is the only authority: tools only in agent mode.
        $agentMode = $request->input('mode', 'chat') === 'agent';

        if (!$resolved) {
            return response()->json(['reply' => 'No AI provider is configured. Go to AI Settings and add an API key for OpenAI, Gemini, Claude, DeepSeek or Meta.'], 200);
        }

        try {
            $context = $this->buildContext($user);
        } catch (\Exception $e) {
            \Log::error('AI buildContext error', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $context = '(Could not load user data)';
        }

        $messages = $this->buildMessages($user, $context, $request->input('history', []), $request->message, $agentMode ? 'agent' : 'chat');

        try {
            $result = $this->callProviderSyncWithTools($resolved, $messages, $user, $agentMode);
            \Log::info('AI chat response', ['user_id' => $user->id, 'provider' => $resolved['provider'], 'model' => $resolved['model'], 'mode' => $agentMode ? 'agent' : 'chat']);
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
        } catch (\Exception $e) {
            \Log::error('AI chat failed', ['provider' => $resolved['provider'], 'model' => $resolved['model'], 'error' => $e->getMessage()]);
            return response()->json(['reply' => 'AI error: ' . $e->getMessage()], 200);
        }
    }

    /* ── Streaming ── */
    public function stream(Request $request)
    {
        $request->validate([
            'message'         => 'required|string|max:8000',
            'conversation_id' => 'nullable|integer',
            'history'         => 'nullable|array|max:40',
            'history.*.role'    => 'required|in:user,assistant',
            'history.*.content' => 'required|string|max:8000',
            'mode' => 'nullable|in:chat,agent',
        ]);

        $user = Auth::user();
        $resolved = $this->ai->resolve($user);
        $agentMode = $request->input('mode', 'chat') === 'agent';

        // Resolve or create conversation
        $convId = $request->input('conversation_id');
        if ($convId) {
            $conversation = AiConversation::where('id', $convId)->where('user_id', $user->id)->first();
        }
        if (empty($conversation)) {
            $conversation = AiConversation::create(['user_id' => $user->id, 'label' => 'New Chat']);
        }

        // Save user message
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $request->message,
        ]);
        if ($conversation->messages()->count() === 1) {
            $conversation->update(['label' => mb_substr($request->message, 0, 60)]);
        }

        // No provider -> offline fallback
        if (!$resolved) {
            $fallbackText = (new LinaFallbackBrain($user, $request->message))->respond();
            $offlineConvId = $conversation->id;
            $sseFlush = $this->sseFlushClosure();
            return response()->stream(function () use ($sseFlush, $offlineConvId, $fallbackText) {
                echo "data: " . json_encode(['model' => 'lina-offline', 'provider' => 'offline', 'conversation_id' => $offlineConvId]) . "\n\n";
                $sseFlush();
                foreach (str_split($fallbackText, 4) as $chunk) {
                    echo "data: " . json_encode(['choices' => [['delta' => ['content' => $chunk]]]]) . "\n\n";
                    $sseFlush();
                    usleep(14000);
                }
                try {
                    AiMessage::create(['conversation_id' => $offlineConvId, 'role' => 'assistant', 'content' => $fallbackText, 'model' => 'lina-offline']);
                    AiConversation::where('id', $offlineConvId)->touch();
                } catch (\Exception $e) {}
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

        $messages = $this->buildMessages($user, $context, $request->input('history', []), $request->message, $agentMode ? 'agent' : 'chat');

        // Agent mode needs function-calling: only OpenAI-compatible providers.
        if ($agentMode && ($resolved['type'] ?? 'openai') !== 'openai') {
            $msg = 'Agent mode needs an OpenAI-compatible provider (e.g. OpenRouter custom provider). Switch to chat mode, or pick an OpenAI-compatible model in AI Settings — nothing was changed.';
            $conversationId = $conversation->id;
            $model = $resolved['model'];
            $provider = $resolved['provider'];
            $sseFlush = $this->sseFlushClosure();
            return response()->stream(function () use ($sseFlush, $conversationId, $msg, $model, $provider) {
                echo "data: " . json_encode(['model' => $model, 'provider' => $provider, 'conversation_id' => $conversationId]) . "\n\n";
                $sseFlush();
                echo "data: " . json_encode(['choices' => [['delta' => ['content' => $msg]]]]) . "\n\n";
                $sseFlush();
                try {
                    AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => $msg, 'model' => $model]);
                    AiConversation::where('id', $conversationId)->touch();
                } catch (\Exception $e) {}
                echo "data: [DONE]\n\n";
                $sseFlush();
            }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no', 'Connection' => 'keep-alive']);
        }

        // For OpenAI-compatible providers, do true streaming. For Gemini/Anthropic, do sync then chunk.
        if (($resolved['type'] ?? 'openai') === 'openai') {
            return $this->streamOpenAi($resolved, $messages, $conversation, $agentMode);
        }

        // Non-OpenAI: sync call then simulate streaming
        try {
            $fullText = $this->callProviderSync($resolved, $messages);
        } catch (\Exception $e) {
            \Log::error('AI stream sync failed', ['provider' => $resolved['provider'], 'error' => $e->getMessage()]);
            $fullText = "AI error: " . $e->getMessage();
        }

        $conversationId = $conversation->id;
        $model = $resolved['model'];
        $provider = $resolved['provider'];
        $sseFlush = $this->sseFlushClosure();

        return response()->stream(function () use ($sseFlush, $conversationId, $fullText, $model, $provider) {
            echo "data: " . json_encode(['model' => $model, 'provider' => $provider, 'conversation_id' => $conversationId]) . "\n\n";
            $sseFlush();
            foreach (str_split($fullText, 5) as $chunk) {
                echo "data: " . json_encode(['choices' => [['delta' => ['content' => $chunk]]]]) . "\n\n";
                $sseFlush();
                usleep(12000);
            }
            try {
                AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => $fullText, 'model' => $model]);
                AiConversation::where('id', $conversationId)->touch();
            } catch (\Exception $e) {}
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
            throw new \Exception('Empty response from provider');
        }
        return $result['text'];
    }

    /**
     * Raw OpenAI-compatible sync call with optional tools.
     * Returns ['text'=>string,'tool_calls'=>array].
     */
    private function callOpenAiSyncRaw(string $key, string $baseUrl, array $messages, string $model, ?array $tools): array
    {
        $payload = $this->ai->openAiPayload($messages, $model, false, $baseUrl, $tools);
        $response = $this->ai->postJson($baseUrl, $payload, [
            'Authorization' => 'Bearer ' . $key,
        ], 60);

        if ($response->failed()) {
            throw new \Exception($this->ai->formatErrorResponse($response));
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
    private function callProviderSyncWithTools(array $resolved, array $messages, $user, bool $withTools = true): array
    {
        $type = $resolved['type'] ?? 'openai';
        if ($type !== 'openai' || ! $withTools) {
            return ['reply' => $this->callProviderSync($resolved, $messages)];
        }

        $tools = (new \App\Services\AiToolService)->definitions();
        $raw = $this->callOpenAiSyncRaw($resolved['key'], $resolved['config']['base_url'], $messages, $resolved['model'], $tools);

        if (empty($raw['tool_calls'])) {
            if ($raw['text'] === '') {
                throw new \Exception('Empty response from provider');
            }
            return ['reply' => $raw['text']];
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
            if (count($proposals) >= 5) {
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
        $tool = \App\Services\AiToolService::normalizeToolName($tool);
        if ($tool === 'plan_propose') {
            return $this->createPlanFromToolCall($user, $conversationId, $argsJson);
        }

        $service = new \App\Services\AiToolService;
        $args = is_string($argsJson) ? (json_decode($argsJson, true) ?? []) : (array) $argsJson;

        $open = \App\Models\AiPendingAction::where('user_id', $user->id)
            ->where('status', \App\Models\AiPendingAction::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->count();
        if ($open >= 5) {
            return ['error' => 'Too many pending confirmations. Confirm or cancel one first.'];
        }

        $check = $service->validateCall($tool, $args, $user);
        if (! ($check['ok'] ?? false)) {
            return ['error' => $check['error'] ?? 'Invalid action.'];
        }

        $action = \App\Models\AiPendingAction::create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'tool' => $tool,
            'args' => $check['resolved'],
            'preview' => $service->preview($tool, $check['resolved'], $user),
            'status' => \App\Models\AiPendingAction::STATUS_PENDING,
            'expires_at' => now()->addMinutes(\App\Models\AiPendingAction::EXPIRY_MINUTES),
            'idempotency_key' => bin2hex(random_bytes(32)),
        ]);

        \Log::info('ai.tool.proposed', ['user_id' => $user->id, 'tool' => $tool, 'action_id' => $action->id]);

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

        return (new \App\Services\AiToolService)->definitions();
    }

    /**
     * Validate + store a plan_propose call as an AiPlan (no execution).
     * Returns a plan_proposal packet for the structure card.
     */
    private function createPlanFromToolCall($user, $conversationId, $argsJson): array
    {
        $service = new \App\Services\AiToolService;
        $args = is_string($argsJson) ? (json_decode($argsJson, true) ?? []) : (array) $argsJson;

        $open = \App\Models\AiPlan::where('user_id', $user->id)
            ->whereIn('status', [
                \App\Models\AiPlan::STATUS_PROPOSED,
                \App\Models\AiPlan::STATUS_CONFIRMED,
                \App\Models\AiPlan::STATUS_EXECUTING,
            ])
            ->where('expires_at', '>', now())
            ->count();
        if ($open >= 3) {
            return ['error' => 'Too many open plans. Finish or cancel one first.'];
        }

        $check = $service->validateCall('plan_propose', $args, $user);
        if (! ($check['ok'] ?? false)) {
            return ['error' => $check['error'] ?? 'Invalid plan.'];
        }

        $plan = \App\Models\AiPlan::create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'title' => $check['resolved']['title'],
            'structure' => $check['resolved']['structure'],
            'phases' => $service->buildPlanPhases($check['resolved']['structure']),
            'status' => \App\Models\AiPlan::STATUS_PROPOSED,
            'current_phase' => 0,
            'expires_at' => now()->addMinutes(\App\Models\AiPlan::EXPIRY_MINUTES),
            'idempotency_key' => bin2hex(random_bytes(32)),
        ]);

        \Log::info('ai.plan.proposed', ['user_id' => $user->id, 'plan_id' => $plan->id]);

        $controller = app(\App\Http\Controllers\AiPlanController::class);

        return ['plan' => $controller->serialize($plan)] + ['expires_at' => $plan->expires_at->toIso8601String()];
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

        $url = rtrim($baseUrl, '/') . '/' . $model . ':generateContent?key=' . $key;

        $response = $this->ai->postJson($url, $body, [], 60);

        if ($response->failed()) {
            throw new \Exception($this->ai->formatErrorResponse($response));
        }
        $text = $response->json('candidates.0.content.parts.0.text');
        if (!$text) throw new \Exception('Empty response from Gemini');
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
        if ($system) $payload['system'] = $system;

        $response = $this->ai->postJson($baseUrl, $payload, [
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
        ], 60);

        if ($response->failed()) {
            throw new \Exception($this->ai->formatErrorResponse($response));
        }
        $blocks = $response->json('content');
        $text = '';
        if (is_array($blocks)) {
            foreach ($blocks as $b) {
                if (($b['type'] ?? '') === 'text') $text .= $b['text'] ?? '';
            }
        }
        if (!$text) throw new \Exception('Empty response from Claude');
        return trim($text);
    }

    /* ── OpenAI streaming ── */
    private function streamOpenAi(array $resolved, array $messages, AiConversation $conversation, bool $agentMode = true)
    {
        $key = $resolved['key'];
        $cfg = $resolved['config'];
        $model = $resolved['model'];
        $provider = $resolved['provider'];
        $tools = $agentMode ? $this->toolsForResolved($resolved) : null;

        $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 60]);
        $response = null;

        try {
            $response = $client->post($cfg['base_url'], [
                'http_errors' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => (string) config('app.url'),
                    'X-Title'       => (string) config('app.name', 'Task Manager'),
                ],
                'json' => $this->ai->openAiPayload($messages, $model, true, $cfg['base_url'], $tools),
                'stream' => true,
            ]);
            $status = $response->getStatusCode();
            if ($status !== 200) {
                $body = (string) $response->getBody();
                $json = json_decode($body, true);
                $detail = (is_array($json) && isset($json['error']))
                    ? $this->ai->formatErrorArray($json['error'])
                    : trim($body);
                throw new \Exception("HTTP {$status}: {$detail}");
            }
        } catch (\Exception $e) {
            // Fall back to sync + chunk (keeps tool calls via the Raw variant).
            \Log::warning('AI stream openai failed, falling back to sync', ['provider' => $provider, 'error' => $e->getMessage()]);
            $conversationId = $conversation->id;
            $userId = Auth::id();
            $sseFlush = $this->sseFlushClosure();
            $controller = $this;
            try {
                $raw = $this->callOpenAiSyncRaw($key, $cfg['base_url'], $messages, $model, $tools);
                $fullText = $raw['text'] !== '' ? $raw['text'] : 'AI error: Empty response from provider';
                $fallbackTools = $raw['tool_calls'];
            } catch (\Exception $e2) {
                $fullText = "AI error: " . $e2->getMessage();
                $fallbackTools = [];
            }
            return response()->stream(function () use ($sseFlush, $conversationId, $fullText, $fallbackTools, $model, $provider, $userId, $controller, $agentMode) {
                echo "data: " . json_encode(['model' => $model, 'provider' => $provider, 'conversation_id' => $conversationId]) . "\n\n";
                $sseFlush();
                foreach (str_split($fullText, 5) as $chunk) {
                    echo "data: " . json_encode(['choices' => [['delta' => ['content' => $chunk]]]]) . "\n\n";
                    $sseFlush();
                    usleep(12000);
                }
                try {
                    AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => $fullText, 'model' => $model]);
                    AiConversation::where('id', $conversationId)->touch();
                } catch (\Exception $e) {}
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

        return response()->stream(function () use ($body, $model, $provider, $userId, $conversationId, $sseFlush, $agentMode) {
            echo "data: " . json_encode(['model' => $model, 'provider' => $provider, 'conversation_id' => $conversationId]) . "\n\n";
            $sseFlush();
            $buffer = '';
            $accumulatedText = '';
            $toolAccum = [];
            $controller = $this;
            try {
                while (!$body->eof()) {
                    $chunk = $body->read(256);
                    $buffer .= $chunk;
                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 1);
                        $line = trim($line);
                        if ($line === '') continue;
                        if (!str_starts_with($line, 'data: ')) continue;
                        $data = substr($line, 6);
                        if ($data === '[DONE]') {
                            if ($accumulatedText) {
                                AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => $accumulatedText, 'model' => $model]);
                                AiConversation::where('id', $conversationId)->touch();
                            }
                            if ($agentMode) {
                                $controller->emitToolProposals($toolAccum, $userId, $conversationId, $sseFlush);
                                $controller->emitToolMissedNotice($accumulatedText, $toolAccum, $sseFlush);
                            }
                            echo "data: [DONE]\n\n";
                            $sseFlush();
                            return;
                        }
                        $decoded = json_decode($data, true);
                        if (is_array($decoded) && isset($decoded['error'])) {
                            if ($accumulatedText) {
                                AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => $accumulatedText, 'model' => $model]);
                                AiConversation::where('id', $conversationId)->touch();
                            }
                            echo "data: " . json_encode(['error' => $this->ai->formatErrorArray($decoded['error'])]) . "\n\n";
                            $sseFlush();
                            echo "data: [DONE]\n\n";
                            $sseFlush();
                            return;
                        }
                        $delta = $decoded['choices'][0]['delta'] ?? [];
                        $token = $delta['content'] ?? '';
                        if ($token) $accumulatedText .= $token;
                        foreach (($delta['tool_calls'] ?? []) as $tc) {
                            $idx = (int) ($tc['index'] ?? 0);
                            $toolAccum[$idx]['id'] = $tc['id'] ?? ($toolAccum[$idx]['id'] ?? null);
                            $toolAccum[$idx]['name'] = $tc['function']['name'] ?? ($toolAccum[$idx]['name'] ?? null);
                            $toolAccum[$idx]['arguments'] = ($toolAccum[$idx]['arguments'] ?? '') . ($tc['function']['arguments'] ?? '');
                        }
                        echo "data: {$data}\n\n";
                        $sseFlush();
                    }
                }
            } catch (\Exception $e) {
                \Log::error('AI stream read error', ['model' => $model, 'provider' => $provider, 'user_id' => $userId, 'error' => $e->getMessage()]);
                echo "data: " . json_encode(['error' => 'Stream interrupted.']) . "\n\n";
                $sseFlush();
            }
            // Persist if we exited without [DONE]
            if ($accumulatedText) {
                try {
                    $exists = AiMessage::where('conversation_id', $conversationId)->where('role', 'assistant')->where('content', $accumulatedText)->exists();
                    if (!$exists) {
                        AiMessage::create(['conversation_id' => $conversationId, 'role' => 'assistant', 'content' => $accumulatedText, 'model' => $model]);
                        AiConversation::where('id', $conversationId)->touch();
                    }
                } catch (\Exception $e) {}
            }
            $controller->emitToolProposals($agentMode ? $toolAccum : [], $userId, $conversationId, $sseFlush);
            if ($agentMode) {
                $controller->emitToolMissedNotice($accumulatedText, $toolAccum, $sseFlush);
            }
            echo "data: [DONE]\n\n";
            $sseFlush();
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no', 'Connection' => 'keep-alive']);
    }

    /**
     * Turn accumulated tool_calls into pending actions and emit SSE proposals.
     */
    public function emitToolProposals(array $toolAccum, int $userId, int $conversationId, callable $sseFlush): void
    {
        if (empty($toolAccum)) {
            return;
        }
        $user = \App\Models\User::find($userId);
        if (! $user) {
            return;
        }
        foreach (array_values($toolAccum) as $tc) {
            $name = $tc['name'] ?? null;
            if (! $name) {
                continue;
            }
            $proposal = $this->createPendingFromToolCall($user, $conversationId, $name, $tc['arguments'] ?? '{}');
            $packetType = \App\Services\AiToolService::normalizeToolName($name) === 'plan_propose'
                ? 'plan_proposal'
                : 'tool_proposal';
            echo 'data: ' . json_encode(['type' => $packetType] + $proposal) . "\n\n";
            $sseFlush();
        }
    }

    /**
     * Agent mode but the model answered with plain text that looks like
     * tool JSON (no real function call): tell the user nothing was created
     * instead of leaving raw JSON on screen. No pending action is stored.
     */
    public function emitToolMissedNotice(string $text, array $toolAccum, callable $sseFlush): void
    {
        if (! empty($toolAccum) || trim($text) === '') {
            return;
        }
        // At least two tool-shaped keys -> likely a pasted tool call, not a code example.
        $hits = preg_match_all('/"(title|description|due_date|project_id|projectId|dueDate)"\s*:/', $text);
        if ($hits < 2 || ! str_contains($text, '{')) {
            return;
        }
        $notice = '⚠️ I answered in text instead of creating anything — nothing was saved. Please send the request again (or pick a model with function-calling support).';
        echo 'data: ' . json_encode(['choices' => [['delta' => ['content' => "\n\n" . $notice]]]]) . "\n\n";
        $sseFlush();
    }

    /* ── Helpers ── */
    private function buildMessages($user, string $context, array $history, string $newMessage, string $mode = 'chat'): array
    {
        $today = now()->format('l, F j, Y');
        $creatorName = $user->name;
        $modeBlock = $mode === 'agent'
            ? <<<AGENT
            MODE: AGENT — you can act on the workspace via tools.
            - When the user asks to create, edit, complete or delete a SINGLE task, reminder, note, project, routine or checklist item, call the matching tool instead of just describing it. Destructive deletes need no extra warning text because the app shows a confirmation card.
            - For a BUILD request (a program, a project with parts, anything with more than 2 items): call plan_propose ONCE with the FULL tree (project + sub-projects + tasks with due_dates + subtasks). Never fire many single calls for one program. The user confirms the structure first, then each phase separately.
            - For a recurring weekly program with no sub-parts, prefer routine_create (frequency weekly + days) over tasks, unless the user explicitly asked for tasks/projects.
            - Keep every single title SHORT: task/subtask titles under 120 chars, descriptions under 500 chars. Never paste a whole program, list or long text into one argument — it gets cut off and garbled. Exercises go into subtasks, one per subtask.
            - Compute due_dates yourself from today's date (given above). Max per plan: 3 sub-projects, 30 tasks, 100 subtasks. If the request is bigger, propose the first chunk and tell the user to say "continue" for the rest.
            - Use exact snake_case argument names from the schema (project_id, due_date, task_id). Omit project_id when unsure — the server picks the user's first project.
            AGENT
            : <<<CHAT
            MODE: CHAT — read-only discussion. You cannot create, edit or delete anything; there are no tools in this mode.
            - Talk about the user's projects, tasks, notes, reminders and routines, explain, summarize and advise.
            - If the user asks you to create or change something, explain briefly what you would do and ask them to switch to Agent mode (🛠 اجرا) so you can do it with their confirmation.
            CHAT;
        $systemPrompt = <<<PROMPT
You are Lina, a smart personal AI assistant built into this Task Manager app by {$creatorName}.
If asked your name, say your name is Lina. If asked who created or built you, say you were created by {$creatorName}.
Today is {$today}.

You can help the user with:
- Their workspace data: tasks, projects, notes, reminders, routines, and files (full data provided below)
- Coding, programming, software development, and any technical / technology questions
- General knowledge

Guidelines:
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
        return function () { if (ob_get_level() > 0) ob_flush(); flush(); };
    }

    private function cleanUtf8(string $str): string
    {
        $clean = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean);
    }

    private function buildContext($user): string
    {
        $projects = Project::where('user_id', $user->id)->get(['name', 'status', 'end_date', 'budget']);
        $projectLines = $projects->map(fn($p) => "- {$p->name} (status: {$p->status}" . ($p->end_date ? ", due: {$p->end_date->format('Y-m-d')}" : '') . ")")->join("\n");

        $tasks = Task::where('user_id', $user->id)->with('project:id,name')->get(['title', 'status', 'priority', 'due_date', 'project_id']);
        $taskLines = $tasks->map(fn($t) => "- [{$t->status}] {$t->title} (priority: {$t->priority}" . ($t->due_date ? ", due: {$t->due_date}" : '') . ($t->project ? ", project: {$t->project->name}" : '') . ")")->join("\n");

        $notes = Note::where('user_id', $user->id)->get(['title', 'content', 'tags']);
        $noteLines = $notes->map(function ($n) {
            $tags = is_array($n->tags) ? implode(', ', $n->tags) : ($n->tags ?? '');
            return "- {$n->title}" . ($tags ? " [tags: {$tags}]" : '') . ": " . strip_tags(substr($n->content ?? '', 0, 120));
        })->join("\n");

        $reminders = Reminder::where('user_id', $user->id)->get(['title', 'date', 'time', 'priority', 'is_completed', 'tags']);
        $reminderLines = $reminders->map(function ($r) {
            $tags = is_array($r->tags) ? implode(', ', $r->tags) : ($r->tags ?? '');
            $status = $r->is_completed ? 'done' : 'pending';
            $when = $r->date ? $r->date->format('Y-m-d') . ($r->time ? " {$r->time}" : '') : '';
            return "- [{$status}] {$r->title}" . ($when ? " at {$when}" : '') . ($tags ? " [tags: {$tags}]" : '');
        })->join("\n");

        $routines = Routine::where('user_id', $user->id)->get(['title', 'frequency']);
        $routineLines = $routines->map(fn($r) => "- {$r->title} ({$r->frequency})")->join("\n");

        $files = File::where('user_id', $user->id)->get(['name', 'type']);
        $fileLines = $files->map(fn($f) => "- {$f->name} (type: {$f->type})")->join("\n");

        return implode("\n\n", array_filter([
            $projects->count()  ? "PROJECTS ({$projects->count()}):\n{$projectLines}"    : null,
            $tasks->count()     ? "TASKS ({$tasks->count()}):\n{$taskLines}"              : null,
            $notes->count()     ? "NOTES ({$notes->count()}):\n{$noteLines}"              : null,
            $reminders->count() ? "REMINDERS ({$reminders->count()}):\n{$reminderLines}"  : null,
            $routines->count()  ? "ROUTINES ({$routines->count()}):\n{$routineLines}"     : null,
            $files->count()     ? "FILES ({$files->count()}):\n{$fileLines}"              : null,
        ]));
    }
}
