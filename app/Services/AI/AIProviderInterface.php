<?php
namespace App\Services\AI;

interface AIProviderInterface
{
    public function chat(
        string $model,
        array $messages,
        array $attachments = []
    ): array;
}
