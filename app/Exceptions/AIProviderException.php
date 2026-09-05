<?php

namespace App\Exceptions;

use RuntimeException;

class AIProviderException extends RuntimeException
{
    public function __construct(string $message, private readonly int $statusCode = 503, ?\Throwable $previous = null,) {
        parent::__construct($message, 0, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
