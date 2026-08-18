<?php
namespace App\Services\AI;

use App\Services\AI\AIProviderInterface;
use Gemini;

class GoogleAIService implements AIProviderInterface
{
    public function chat(string $model, string $message): string
    {
        $httpClient = new \GuzzleHttp\Client([
            'timeout' => 60,
            'connect_timeout' => 10,
            'verify' => config('gemini.ca_bundle'),
        ]);

        $client = Gemini::factory()
            ->withApiKey(config('gemini.api_key'))
            ->withHttpClient($httpClient)
            ->make();

        $response = $client
            ->generativeModel(model: $model)
            ->generateContent($message);

        return $response->text();
    }
}

