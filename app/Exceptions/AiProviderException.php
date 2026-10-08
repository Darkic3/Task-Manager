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
}
