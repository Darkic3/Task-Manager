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
     * Never log API keys, tokens, or Authorization headers in any spelling.
     */
    public static function sanitize(array $context): array
    {
        foreach ($context as $k => $v) {
            $lk = strtolower((string) $k);
            if (str_contains($lk, 'api_key')
                || str_contains($lk, 'apikey')
                || $lk === 'key'
                || str_contains($lk, 'secret')
                || str_contains($lk, 'token')
                || str_contains($lk, 'password')
                || str_contains($lk, 'authorization')
                || str_contains($lk, 'x-api-key')
                || str_contains($lk, 'cookie')
                || str_contains($lk, 'set-cookie')) {
                $context[$k] = '[redacted]';
            }
        }

        // Scrub secrets embedded inside free-text fields (Bearer tokens,
        // sk- keys, api key query params, logged URLs/headers/payloads).
        $scrubText = function (?string $text): ?string {
            if (! is_string($text)) {
                return $text;
            }
            $text = preg_replace('/(Bearer\s+)[A-Za-z0-9\-._~+\/=]{8,}/i', '$1[redacted]', $text);
            $text = preg_replace('/\b(sk-[A-Za-z0-9\-_]{8,})\b/', '[redacted-sk]', $text);
            $text = preg_replace('/\b(xox[bap]-[A-Za-z0-9\-]+)\b/i', '[redacted-token]', $text);
            $text = preg_replace('/([?&](?:key|api_key|apikey|token|auth)[^=]*=)[^&\s"\']+/i', '$1[redacted]', $text);
            $text = preg_replace('/("?(?:api_key|apikey|authorization|x-api-key|secret|token|password)"?\s*[:=]\s*"?)[^",\s\}]+/i', '$1[redacted]', $text);

            return $text;
        };

        foreach (['message', 'message_preview', 'reply', 'reply_preview', 'text', 'content', 'error', 'detail', 'body', 'url', 'endpoint', 'base_url'] as $field) {
            if (isset($context[$field]) && is_string($context[$field])) {
                $context[$field] = $scrubText(mb_substr($context[$field], 0, self::MAX_MESSAGE_PREVIEW));
            }
        }

        // Truncate nested args/preview blobs to keep one line readable.
        foreach (['args', 'resolved', 'preview', 'structure'] as $field) {
            if (isset($context[$field])) {
                $encoded = is_string($context[$field])
                    ? $scrubText($context[$field])
                    : json_encode($context[$field], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $encoded = $scrubText(is_string($encoded) ? $encoded : (string) json_encode($context[$field]));
                if (is_string($encoded) && mb_strlen($encoded) > 800) {
                    $context[$field] = mb_substr($encoded, 0, 800).'…[truncated]';
                } elseif (is_string($encoded)) {
                    $context[$field] = $encoded;
                }
            }
        }

        return $context;
    }

    /**
     * Last N lines of the ai log file for the /ai/debug endpoint.
     * Returns newest-first strings, never throws.
     *
     * WARNING: the file is GLOBAL. Never return it unfiltered —
     * use tailForUser() so a user only sees their own entries.
     */
    public static function tail(int $lines = 80): array
    {
        try {
            // `daily` driver layout (ai-YYYY-MM-DD.log) is primary;
            // legacy `single` file (ai.log) is a fallback for old installs.
            $dated = storage_path('logs/ai-' . now()->format('Y-m-d') . '.log');
            $legacy = storage_path('logs/ai.log');
            $paths = [];
            // Chronological order: legacy (stale) first, dated (fresh) last,
            // so slicing the tail keeps the newest entries.
            if (is_file($legacy)) {
                $paths[] = $legacy;
            }
            if (is_file($dated)) {
                $paths[] = $dated;
            }
            if (empty($paths)) {
                return [];
            }
            $all = [];
            foreach ($paths as $path) {
                $all = array_merge($all, @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
            }

            return array_values(array_slice($all, -$lines));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Per-user log tail: only lines belonging to $userId.
     * Entries are matched by the structured `user_id` field in the
     * JSON context; lines without a matching user_id are EXCLUDED
     * (fail closed — never leak another user's previews).
     */
    public static function tailForUser(int $userId, int $lines = 80): array
    {
        $all = self::tail(max($lines * 5, 200));
        $out = [];
        foreach (array_reverse($all) as $line) {
            if (self::lineBelongsToUser($line, $userId)) {
                $out[] = $line;
                if (count($out) >= $lines) {
                    break;
                }
            }
        }

        return array_values(array_reverse($out));
    }

    private static function lineBelongsToUser(string $line, int $userId): bool
    {
        // Structured context is JSON-encoded after the message: {...}.
        $pos = strpos($line, '{');
        if ($pos === false) {
            return false;
        }
        $json = substr($line, $pos);
        $data = json_decode($json, true);
        if (! is_array($data)) {
            // Fallback: exact `"user_id":<id>` token match only.
            return str_contains($line, '"user_id":' . $userId)
                || str_contains($line, '"user_id":"' . $userId . '"');
        }
        if (! array_key_exists('user_id', $data)) {
            return false;
        }

        return (int) $data['user_id'] === $userId;
    }
}
