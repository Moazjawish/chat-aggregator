<?php

namespace App\Services\AI;

use GuzzleHttp\Client;

class ClaudeService implements AIProviderInterface
{
    public function chat(string $model, string $message): string
    {
        $client = new Client([
            'timeout' => 60,
            'connect_timeout' => 10,
            'verify' => config('claude.ca_bundle'),
        ]);

        $response = $client->post(
            'https://api.anthropic.com/v1/messages',
            [
                'headers' => [
                    'x-api-key' => config('claude.api_key'),
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ],

                'json' => [
                    'model' => $model,
                    'max_tokens' => 1024,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $message,
                        ],
                    ],
                ],
            ]
        );

        $data = json_decode(
            $response->getBody()->getContents(),
            true
        );

        return $data['content'][0]['text'];
    }
}
