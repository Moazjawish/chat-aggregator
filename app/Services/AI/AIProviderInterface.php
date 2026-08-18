<?php

namespace App\Services\AI;

interface AIProviderInterface
{
    public function chat(string $model, string $message): string;
}
