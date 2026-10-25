<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class AiProviderException extends Exception
{
    public string $provider;
    public ?string $endpoint;
    public ?int $httpStatus;

    public function __construct(
        string $message,
        string $provider = 'unknown',
        ?string $endpoint = null,
        ?int $httpStatus = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        // Scrub once at the source: this message flows into user responses
        // AND server logs, so URLs/keys must never survive here.
        parent::__construct(self::scrub($message), $code, $previous);
        $this->provider = $provider;
        $this->endpoint = $endpoint;
        $this->httpStatus = $httpStatus;
    }

    /**
     * Strip secrets and full URLs from any provider-related text before it
     * reaches users or logs. Host names are kept ([provider host]) so
     * failures stay diagnosable without leaking paths, query keys or tokens.
     */
    public static function scrub(string $text): string
    {
        // Credential-bearing query params (?key=..., &token=...).
        $text = (string) preg_replace(
            '/([?&](?:api[_-]?key|key|token|secret|password|auth)=)[^&\s\'"`<>]*/i',
            '$1[redacted]',
            $text
        );
        // Bearer-style tokens.
        $text = (string) preg_replace(
            '/\bBearer\s+[A-Za-z0-9\-._~+\/=]+/i',
            'Bearer [redacted]',
            $text
        );
        // Full URLs → host only.
        $text = (string) preg_replace_callback(
            '/https?:\/\/[^\s\'"`<>]+/i',
            static fn ($m) => '[provider '.(parse_url($m[0], PHP_URL_HOST) ?: 'host').']',
            $text
        );

        return $text;
    }

    /**
     * P3 error taxonomy: only transport-level failures are retryable
     * (timeouts, connection errors, 429, 5xx). SSRF blocks, 4xx client
     * errors, validation, auth and business errors must surface
     * immediately — retrying them can never succeed.
     */
    public function isRetryable(): bool
    {
        if (str_starts_with($this->getMessage(), 'Blocked provider URL')) {
            return false;
        }
        if ($this->httpStatus === null) {
            return true;
        }

        return $this->httpStatus === 429 || $this->httpStatus >= 500;
    }
}
