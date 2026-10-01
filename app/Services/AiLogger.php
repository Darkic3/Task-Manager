<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AiLogger — single entry point for ALL AI logging.
 *
 * Why: AI failures were previously scattered across laravel.log with no
 * correlation ID, so diagnosing "agent said X but created nothing" required
 * guesswork. Every AI request now gets a short request ID (e.g. ai_a1b2c3d4)
 * logged to a dedicated storage/logs/ai.log channel with the same fields:
 * user, mode, provider, model, message preview, tools, errors.
 *
 * Usage: AiLogger::log('request.received', [...]);
 */
class AiLogger
{
    public const CHANNEL = 'ai';

    public const MAX_MESSAGE_PREVIEW = 300;

    public static function newRequestId(): string
    {
        return 'ai_'.Str::random(8);
    }

    /**
     * Log one AI event to the dedicated ai channel.
     * Never throws — logging must not break chat.
     */
    public static function log(string $event, array $context = []): void
    {
        try {
            Log::channel(self::CHANNEL)->info($event, self::sanitize($context));
        } catch (\Throwable $e) {
            // Last resort: default channel so the event is not fully lost.
            try {
                Log::info('[ai-logger-fallback] '.$event, ['error' => $e->getMessage()]);
            } catch (\Throwable $ignored) {
            }
        }
    }

    public static function error(string $event, array $context = []): void
    {
        try {
            Log::channel(self::CHANNEL)->error($event, self::sanitize($context));
        } catch (\Throwable $e) {
            try {
                Log::error('[ai-logger-fallback] '.$event, ['error' => $e->getMessage()]);
            } catch (\Throwable $ignored) {
            }
        }
    }

    /**
     * Remove secrets + truncate long text so ai.log stays small and safe.
     */
    public static function sanitize(array $context): array
    {
        foreach (['key', 'api_key', 'authorization', 'Authorization'] as $secret) {
            if (array_key_exists($secret, $context)) {
                $context[$secret] = '[redacted]';
            }
        }

        foreach (['message', 'message_preview', 'reply', 'reply_preview', 'text', 'content'] as $field) {
            if (isset($context[$field]) && is_string($context[$field])) {
                $context[$field] = mb_substr($context[$field], 0, self::MAX_MESSAGE_PREVIEW);
            }
        }

        // Truncate nested args/preview blobs to keep one line readable.
        foreach (['args', 'resolved', 'preview', 'structure'] as $field) {
            if (isset($context[$field])) {
                $encoded = is_string($context[$field])
                    ? $context[$field]
                    : json_encode($context[$field], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (is_string($encoded) && mb_strlen($encoded) > 800) {
                    $context[$field] = mb_substr($encoded, 0, 800).'…[truncated]';
                }
            }
        }

        return $context;
    }

    /**
     * Last N lines of the ai log file for the /ai/debug endpoint.
     * Returns newest-first strings, never throws.
     */
    public static function tail(int $lines = 80): array
    {
        try {
            $path = storage_path('logs/ai.log');
            if (! is_file($path)) {
                return [];
            }
            $all = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

            return array_values(array_slice($all, -$lines));
        } catch (\Throwable $e) {
            return [];
        }
    }
}
