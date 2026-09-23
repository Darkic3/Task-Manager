<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\AiSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class AiProviderService
{
    public function allProviders(): array
    {
        return config('ai.providers', []);
    }

    public function providerIds(): array
    {
        return array_keys($this->allProviders());
    }

    public function providerConfig(string $provider, $user = null): ?array
    {
        if ($this->isCustom($provider)) {
            return $user ? ($this->customProviders($user)[$provider] ?? null) : null;
        }
        return config("ai.providers.{$provider}");
    }

    /* ── Custom (user-defined) providers ─────────────────────────── */

    public function isCustom(string $provider): bool
    {
        return str_starts_with($provider, 'custom:');
    }

    public function customProvider($user, string $provider): ?AiProvider
    {
        if (!$user || !$this->isCustom($provider)) return null;
        $id = (int) substr($provider, strlen('custom:'));
        return AiProvider::where('id', $id)->where('user_id', $user->id)->first();
    }

    /**
     * Custom providers shaped like config entries, keyed by "custom:{id}".
     */
    public function customProviders($user): array
    {
        if (!$user) return [];
        $out = [];
        foreach (AiProvider::where('user_id', $user->id)->orderBy('sort_order')->orderBy('id')->get() as $p) {
            $out[$p->providerKey()] = [
                'label'        => $p->label,
                'key_env'      => null,
                'key_column'   => null,
                'model_column' => null,
                'base_url'     => $p->base_url,
                'type'         => $p->type,
                'models'       => $p->model ? [$p->model => $p->model] : [],
                'default_model'=> $p->model,
                'is_custom'    => true,
                'custom_id'    => $p->id,
            ];
        }
        return $out;
    }

    /**
     * Built-in providers plus the user's custom providers.
     */
    public function providersForUser($user): array
    {
        return $this->allProviders() + $this->customProviders($user);
    }

    /**
     * Get the stored AiSetting for user (or null).
     */
    public function setting($user): ?AiSetting
    {
        if (!$user) return null;
        return AiSetting::where('user_id', $user->id)->first();
    }

    /**
     * Does this provider have an API key? Checks encrypted DB column then env fallback.
     */
    public function hasKey($user, string $provider): bool
    {
        return (bool) $this->getKey($user, $provider);
    }

    public function getKey($user, string $provider): ?string
    {
        if ($this->isCustom($provider)) {
            $p = $this->customProvider($user, $provider);
            return ($p && $p->enabled && !empty($p->api_key)) ? $p->api_key : null;
        }

        $cfg = $this->providerConfig($provider, $user);
        if (!$cfg) return null;

        // DB first (encrypted)
        $setting = $this->setting($user);
        $col = $cfg['key_column'] ?? null;
        if ($setting && $col && !empty($setting->{$col})) {
            return $setting->{$col};
        }

        // Env fallback (global)
        $envKey = $cfg['key_env'] ?? null;
        if ($envKey) {
            $val = config("services.{$provider}.key") ?: env($envKey);
            if (!empty($val)) return $val;
        }

        return null;
    }

    public function getModel($user, string $provider): string
    {
        if ($this->isCustom($provider)) {
            $p = $this->customProvider($user, $provider);
            return $p?->model ?: '';
        }

        $cfg = $this->providerConfig($provider, $user);
        $default = $cfg['default_model'] ?? 'gpt-4o-mini';

        $setting = $this->setting($user);
        $col = $cfg['model_column'] ?? null;
        if ($setting && $col && !empty($setting->{$col})) {
            // validate still exists in config
            if (isset($cfg['models'][$setting->{$col}])) {
                return $setting->{$col};
            }
        }
        // per-user default_model override if matches this provider?
        // otherwise config default
        return $default;
    }

    /**
     * All providers with whether they are enabled (have key).
     * Returns [provider => bool]
     */
    public function enabledMap($user): array
    {
        $map = [];
        foreach ($this->providersForUser($user) as $id => $cfg) {
            $map[$id] = $this->hasKey($user, $id);
        }
        return $map;
    }

    /**
     * List of provider ids that currently have a key.
     */
    public function enabledProviders($user): array
    {
        return array_keys(array_filter($this->enabledMap($user)));
    }

    public function isConfigured($user): bool
    {
        return !empty($this->enabledProviders($user));
    }

    /**
     * Resolve which provider/model/key to actually use for chat.
     * Returns ['provider'=>..., 'key'=>..., 'model'=>..., 'type'=>..., 'config'=>...] or null if none.
     */
    public function resolve($user): ?array
    {
        $setting = $this->setting($user);
        $providers = $this->providersForUser($user);

        // 1) Explicit default_provider if it has a key
        $defaultProvider = $setting?->default_provider ?: config('ai.default_provider');
        if ($defaultProvider && isset($providers[$defaultProvider])) {
            $key = $this->getKey($user, $defaultProvider);
            if ($key) {
                return $this->buildResolved($user, $defaultProvider, $key);
            }
        }

        // 2) Auto-detect: first provider in order that has a key
        foreach ($providers as $id => $cfg) {
            $key = $this->getKey($user, $id);
            if ($key) {
                return $this->buildResolved($user, $id, $key);
            }
        }

        return null;
    }

    private function buildResolved($user, string $provider, string $key): array
    {
        $cfg = $this->providerConfig($provider, $user);
        $model = $this->getModel($user, $provider);
        // If user set a global default_model that belongs to this provider, prefer it
        $setting = $this->setting($user);
        if ($setting && !empty($setting->default_model) && $setting->default_provider === $provider) {
            if (isset($cfg['models'][$setting->default_model])) {
                $model = $setting->default_model;
            }
        }

        return [
            'provider' => $provider,
            'key'      => $key,
            'model'    => $model,
            'type'     => $cfg['type'] ?? 'openai',
            'config'   => $cfg,
        ];
    }

    /**
     * Resolve per-provider model — validates that model exists, else default.
     */
    public function validateModel(string $provider, string $model, $user = null): bool
    {
        if ($this->isCustom($provider)) {
            return is_string($model) && trim($model) !== '';
        }
        $cfg = $this->providerConfig($provider, $user);
        if (!$cfg) return false;
        return isset($cfg['models'][$model]);
    }

    /**
     * Lightweight connectivity test for a custom provider.
     * Returns ['ok' => bool, 'message' => string].
     */
    public function testConnection(AiProvider $provider): array
    {
        $key = $provider->api_key;
        $model = $provider->model;

        if (!$key)   return ['ok' => false, 'message' => 'No API key set for this provider.'];
        if (!$model) return ['ok' => false, 'message' => 'Set a model before testing.'];

        try {
            if ($provider->type === 'gemini') {
                $url = rtrim($provider->base_url, '/') . '/' . $model . ':generateContent?key=' . $key;
                $res = $this->postJson($url, [
                    'contents' => [['role' => 'user', 'parts' => [['text' => 'ping']]]],
                    'generationConfig' => ['maxOutputTokens' => 5],
                ], [], 30);
                if ($res->failed()) {
                    return ['ok' => false, 'message' => $this->formatErrorResponse($res)];
                }
                return ['ok' => true, 'message' => 'Connection OK (' . $model . ').'];
            }

            if ($provider->type === 'anthropic') {
                $res = $this->postJson($provider->base_url, [
                    'model' => $model,
                    'max_tokens' => 5,
                    'messages' => [['role' => 'user', 'content' => 'ping']],
                ], [
                    'x-api-key'         => $key,
                    'anthropic-version' => '2023-06-01',
                ], 30);
                if ($res->failed()) {
                    return ['ok' => false, 'message' => $this->formatErrorResponse($res)];
                }
                return ['ok' => true, 'message' => 'Connection OK (' . $model . ').'];
            }

            // Default: OpenAI-compatible
            $payload = $this->openAiPayload(
                [['role' => 'user', 'content' => 'ping']],
                $model,
                false,
                $provider->base_url
            );
            $payload['max_tokens'] = 5;
            $res = $this->postJson($provider->base_url, $payload, ['Authorization' => 'Bearer ' . $key], 30);
            if ($res->failed()) {
                return ['ok' => false, 'message' => $this->formatErrorResponse($res)];
            }
            return ['ok' => true, 'message' => 'Connection OK (' . $model . ').'];
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    // ── HTTP helpers ────────────────────────────────────────────────

    /**
     * POST JSON with automatic retry/backoff on 429 and 5xx.
     * Adds OpenRouter-recommended attribution headers (harmless elsewhere).
     */
    public function postJson(string $url, array $payload, array $headers = [], int $timeout = 60): \Illuminate\Http\Client\Response
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'HTTP-Referer' => (string) config('app.url'),
            'X-Title'      => (string) config('app.name', 'Task Manager'),
        ], $headers);

        $maxAttempts = 3;
        for ($attempt = 1; ; $attempt++) {
            $response = Http::withHeaders($headers)
                ->withOptions(['verify' => false])
                ->timeout($timeout)
                ->post($url, $payload);

            $status = $response->status();
            if (($status === 429 || $status >= 500) && $attempt < $maxAttempts) {
                usleep(1500 * 1000 * $attempt); // 1.5s, 3s
                continue;
            }
            return $response;
        }
    }

    /**
     * Turn an error value (string or provider error array) into a readable message.
     * Surfaces OpenRouter's error.metadata.raw / remedy_hint which otherwise get lost.
     */
    public function formatErrorArray($err): string
    {
        if (is_string($err)) return $err;
        if (!is_array($err)) return (string) json_encode($err);

        $parts = [];
        foreach ([
            $err['message'] ?? null,
            $err['metadata']['raw'] ?? null,
            $err['metadata']['remedy_hint'] ?? null,
        ] as $part) {
            if (is_string($part) && trim($part) !== '' && !in_array($part, $parts, true)) {
                $parts[] = trim($part);
            }
        }
        return $parts ? implode(' — ', $parts) : (string) json_encode($err);
    }

    /**
     * Build a detailed error string from an HTTP response (includes status code).
     */
    public function formatErrorResponse($response): string
    {
        $json = $response->json();
        $err  = is_array($json) ? ($json['error'] ?? null) : null;

        $detail = $err !== null
            ? $this->formatErrorArray($err)
            : (trim((string) $response->body()) ?: 'Unknown error');

        return 'HTTP ' . $response->status() . ': ' . $detail;
    }

    public function openAiPayload(array $messages, string $model, bool $stream = false, ?string $baseUrl = null, ?array $tools = null): array
    {
        $payload = [
            'messages'    => $messages,
            // Tool calls carry their arguments in the output, so they need headroom.
            'max_tokens'  => ! empty($tools) ? 4096 : 2048,
            'temperature' => 0.7,
        ];
        if ($stream) $payload['stream'] = true;
        if (! empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        // OpenRouter supports a fallback list via the `models` array.
        // A comma-separated model field (e.g. "free-model:free, openai/gpt-4o-mini")
        // lets OpenRouter auto-route when a free model is rate-limited/unavailable.
        if ($baseUrl && str_contains($baseUrl, 'openrouter.ai') && str_contains($model, ',')) {
            $models = array_values(array_filter(array_map('trim', explode(',', $model))));
            if ($models) {
                $payload['models'] = $models;
                return $payload;
            }
        }

        $payload['model'] = $model;
        return $payload;
    }

    /**
     * Convert OpenAI-style messages to Gemini contents.
     */
    public function toGeminiContents(array $messages): array
    {
        $contents = [];
        $systemText = null;

        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $systemText = $m['content'];
                continue;
            }
            $role = $m['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role'  => $role,
                'parts' => [['text' => $m['content']]],
            ];
        }

        // Gemini may also accept systemInstruction; we prepend as first user hint if needed via config
        return [$contents, $systemText];
    }

    /**
     * Convert OpenAI-style messages to Anthropic format.
     * Returns [system, messages]
     */
    public function toAnthropicMessages(array $messages): array
    {
        $system = null;
        $out = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $system = $m['content'];
                continue;
            }
            $out[] = ['role' => $m['role'], 'content' => $m['content']];
        }
        return [$system, $out];
    }
}
