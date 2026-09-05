<?php

namespace App\Exceptions;

use RuntimeException;

class UsageLimitExceededException extends RuntimeException
{
    public function __construct(string $message = 'You have reached the usage limit for this model.') {
        parent::__construct($message);
    }
}
