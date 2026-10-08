<?php

namespace App\Services\AiTooling;

/**
 * Structured error codes for the AI tool pipeline.
 *
 * Every validation / authorization / execution failure carries a machine
 * readable `code` so the agent (and the UI) never has to guess success or
 * failure from a free-text message.
 */
final class ToolError
{
    public const OK = 'ok';

    /** Schema / argument shape problems. */
    public const VALIDATION_ERROR = 'validation_error';

    /** Explicitly forbidden: foreign owner, policy keys, tampered pending. */
    public const AUTHORIZATION_ERROR = 'authorization_error';

    /** Owned resource does not exist (also used to hide foreign existence). */
    public const NOT_FOUND = 'not_found';

    /** Resource exists but its current state rejects the mutation. */
    public const CONFLICT = 'conflict';

    /** Pending action expired (or already resolved to a terminal state). */
    public const EXPIRED_ACTION = 'expired_action';

    /** Args payload exceeds the size budget. */
    public const PAYLOAD_TOO_LARGE = 'payload_too_large';

    /** Tool name is not in the registry. */
    public const UNKNOWN_TOOL = 'unknown_tool';

    /** Identical call repeated in one turn (model loop). */
    public const LOOP_DETECTED = 'loop_detected';

    /** Per-user pending cap reached. */
    public const RATE_LIMITED = 'rate_limited';

    /** Execution itself failed after a valid claim. */
    public const EXECUTION_ERROR = 'execution_error';

    public static function fail(string $code, string $error, array $extra = []): array
    {
        return ['ok' => false, 'code' => $code, 'error' => $error] + $extra;
    }

    public static function ok(array $extra = []): array
    {
        return ['ok' => true, 'code' => self::OK] + $extra;
    }
}
