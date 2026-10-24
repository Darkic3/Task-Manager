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
        parent::__construct($message, $code, $previous);
        $this->provider = $provider;
        $this->endpoint = $endpoint;
        $this->httpStatus = $httpStatus;
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
