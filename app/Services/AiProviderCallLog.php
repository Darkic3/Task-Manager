<?php

namespace App\Services;

use App\Models\AiProviderCall;

/**
 * P3 provider-call metrics recorder.
 *
 * One row per provider round-trip the server initiates. Recorded fields
 * are deliberately narrow: operation, provider/model names, latency,
 * attempt count, token COUNTS (never content), outcome + error code.
 * There is no column for prompts, responses, keys or URLs, so this sink
 * cannot leak secrets by construction.
 *
 * Token counts come verbatim from the provider response when present;
 * when the provider reports nothing, both stay NULL — we never estimate
 * or fabricate. Never throws: metrics must not break chat.
 */
final class AiProviderCallLog
{
    public static function record(array $fields): void
    {
        try {
            AiProviderCall::create([
                'user_id' => isset($fields['user_id']) ? (int) $fields['user_id'] : null,
                'conversation_id' => isset($fields['conversation_id']) ? (int) $fields['conversation_id'] : null,
                'operation' => mb_substr((string) ($fields['operation'] ?? 'unknown'), 0, 40),
                'provider' => isset($fields['provider']) ? mb_substr((string) $fields['provider'], 0, 40) : null,
                'model' => isset($fields['model']) ? mb_substr((string) $fields['model'], 0, 120) : null,
                'latency_ms' => isset($fields['latency_ms']) ? max(0, (int) $fields['latency_ms']) : null,
                'attempts' => max(1, min(99, (int) ($fields['attempts'] ?? 1))),
                'prompt_tokens' => self::countOrNull($fields['prompt_tokens'] ?? null),
                'completion_tokens' => self::countOrNull($fields['completion_tokens'] ?? null),
                'outcome' => mb_substr((string) ($fields['outcome'] ?? AiProviderCall::OUTCOME_CONNECTION_ERROR), 0, 30),
                'error_code' => isset($fields['error_code']) ? mb_substr((string) $fields['error_code'], 0, 60) : null,
            ]);
        } catch (\Throwable $ignored) {
        }
    }

    private static function countOrNull($v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v) || (int) $v < 0) {
            return null;
        }

        return (int) $v;
    }

    /**
     * Best-effort token counts from a decoded provider response, per
     * provider type. Returns [prompt, completion] with NULL for anything
     * the provider did not report — never estimated.
     */
    public static function parseUsage($json, string $type): array
    {
        if (! is_array($json)) {
            return [null, null];
        }
        if ($type === 'gemini') {
            $u = $json['usageMetadata'] ?? null;
            if (! is_array($u)) {
                return [null, null];
            }

            return [self::countOrNull($u['promptTokenCount'] ?? null), self::countOrNull($u['candidatesTokenCount'] ?? null)];
        }
        if ($type === 'anthropic') {
            $u = $json['usage'] ?? null;
            if (! is_array($u)) {
                return [null, null];
            }

            return [self::countOrNull($u['input_tokens'] ?? null), self::countOrNull($u['output_tokens'] ?? null)];
        }
        $u = $json['usage'] ?? null;
        if (! is_array($u)) {
            return [null, null];
        }

        return [self::countOrNull($u['prompt_tokens'] ?? null), self::countOrNull($u['completion_tokens'] ?? null)];
    }
}
