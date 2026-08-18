<?php

namespace App\Services\AI;

use OpenAI\Laravel\Facades\OpenAI;
use GuzzleHttp\Client as GuzzleClient;
class OpenAIService implements AIProviderInterface
{
    public function chat(string $model, string $message): string
    {
        $factory = new \OpenAI\Factory();

        $client = $factory
            ->withApiKey(config('openai.api_key'))
            ->withHttpClient(new GuzzleClient([
                'timeout' => config('openai.request_timeout', 30),
                'verify' => config('openai.ca_bundle'),
            ]))
            ->make();

        $response = $client->responses()->create([
            'model' => $model,
            'input' => $message,
        ]);

        return $response->outputText;
    }
}
