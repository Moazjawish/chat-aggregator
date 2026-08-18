<?php

namespace App\Services\AI;

use GuzzleHttp\Client;

class DeepSeekService implements AIProviderInterface
{
    public function chat(string $model, string $message): string
    {
        $client = new Client([
            'timeout' => 60,
            'connect_timeout' => 10,
            'verify' => config('deepseek.ca_bundle'),
        ]);

        $response = $client->post(
            config('deepseek.base_url') . '/chat/completions',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('deepseek.api_key'),
                    'Content-Type' => 'application/json',
                ],

                'json' => [
                    'model' => $model,

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

        return $data['choices'][0]['message']['content'];
    }
}
