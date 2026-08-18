<?php

namespace App\Services\AI;

use App\Models\AIModel;
use InvalidArgumentException;

class AIService
{
    public function chat(AIModel $model, string $message): string
    {
        return match ($model->provider) {
            'OpenAI' => app(OpenAIService::class)->chat($model->model_key, $message),
            'Gemini' => app(GoogleAIService::class)->chat($model->model_key, $message),
            'Anthropic' => app(ClaudeService::class)->chat($model->model_key, $message),
            'DeepSeek' => app(DeepSeekService::class)->chat($model->model_key, $message),

            default => throw new InvalidArgumentException(
                "Unsupported AI provider: {$model->provider}"
            ),
        };
    }

}
