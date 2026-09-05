<?php
namespace App\Exceptions;

use RuntimeException;

class AIRequestBusyException extends RuntimeException
{
    public function __construct(
        string $message = 'Another request for this model is currently being processed. Please try again.'
    ) {
        parent::__construct($message);
    }
}
