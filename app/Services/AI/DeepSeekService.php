<?php
namespace App\Services\AI;

use App\Exceptions\AIProviderException;
use GuzzleHttp\Client;
use RuntimeException;
use Throwable;

class DeepSeekService implements AIProviderInterface
{
    public function chat(
        string $model,
        array $messages,
        array $attachments = []
    ): array {
        try {

            $client = new Client([
                'timeout' => 60,
                'connect_timeout' => 10,
                'verify' => config('deepseek.ca_bundle'),
            ]);

            /*
            |--------------------------------------------------------------------------
            | 1. Prepare conversation messages
            |--------------------------------------------------------------------------
            |
            | Normal text-only messages can stay as:
            |
            | [
            |     'role' => 'user',
            |     'content' => 'Hello'
            | ]
            |
            | If the current user message has images,
            | its content becomes an array of content parts.
            |
            */

            $deepSeekMessages = $messages;

            /*
            |--------------------------------------------------------------------------
            | 2. Attach images to CURRENT user message
            |--------------------------------------------------------------------------
            */

            if (! empty($attachments)) {

                $lastIndex = array_key_last(
                    $deepSeekMessages
                );

                if ($lastIndex === null) {
                    throw new RuntimeException(
                        'Unable to attach images without a user message.'
                    );
                }

                if (
                    ($deepSeekMessages[$lastIndex]['role'] ?? null)
                    !== 'user'
                ) {
                    throw new RuntimeException(
                        'Attachments must be associated with a user message.'
                    );
                }

                /*
                 * Start current user content with text.
                 */
                $content = [
                    [
                        'type' => 'text',
                        'text' =>
                            $deepSeekMessages[
                                $lastIndex
                            ]['content'],
                    ],
                ];

                foreach ($attachments as $attachment) {

                    $type =
                        $attachment['type']
                        ?? null;

                    /*
                    |--------------------------------------------------------------------------
                    | Documents are not supported here
                    |--------------------------------------------------------------------------
                    */

                    if ($type === 'document') {
                        throw new RuntimeException(
                            'The selected DeepSeek model does not support document attachments.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Images
                    |--------------------------------------------------------------------------
                    */

                    if ($type !== 'image') {
                        throw new RuntimeException(
                            'Unsupported attachment type.'
                        );
                    }

                    $content[] =
                        $this->buildImageContent(
                            $attachment
                        );
                }

                /*
                 * Replace only the latest user message.
                 */
                $deepSeekMessages[
                    $lastIndex
                ]['content'] = $content;
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Send Chat Completions request
            |--------------------------------------------------------------------------
            */

            $response = $client->post(
                config('deepseek.base_url')
                    . '/chat/completions',
                [
                    'headers' => [
                        'Authorization' =>
                            'Bearer '
                            . config(
                                'deepseek.api_key'
                            ),

                        'Content-Type' =>
                            'application/json',
                    ],

                    'json' => [
                        'model' =>
                            $model,

                        'messages' =>
                            $deepSeekMessages,
                    ],
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | 4. Decode provider response
            |--------------------------------------------------------------------------
            */

            $data = json_decode(
                $response
                    ->getBody()
                    ->getContents(),
                true
            );

            return [
                'content' =>
                    $data[
                        'choices'
                    ][0][
                        'message'
                    ][
                        'content'
                    ]
                    ?? '',

                'input_tokens' =>
                    (int) (
                        $data[
                            'usage'
                        ][
                            'prompt_tokens'
                        ]
                        ?? 0
                    ),

                'output_tokens' =>
                    (int) (
                        $data[
                            'usage'
                        ][
                            'completion_tokens'
                        ]
                        ?? 0
                    ),
            ];

        } catch (Throwable $e) {

            report($e);

            throw new AIProviderException(
                'The AI provider is temporarily unavailable. Please try again later.',
                503,
                $e
            );
        }
    }

    /**
     * Build DeepSeek image content part.
     */
    private function buildImageContent(
        array $attachment
    ): array {
        $absolutePath =
            $attachment['absolute_path']
            ?? null;

        $mimeType =
            $attachment['mime_type']
            ?? null;

        if (
            ! $absolutePath ||
            ! is_file($absolutePath)
        ) {
            throw new RuntimeException(
                'Image attachment file not found.'
            );
        }

        /*
         * DeepSeek Vision currently supports:
         *
         * JPEG
         * PNG
         * GIF
         * WebP
         */
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];

        if (! in_array(
            $mimeType,
            $allowedMimeTypes,
            true
        )) {
            throw new RuntimeException(
                "Unsupported DeepSeek image type: {$mimeType}"
            );
        }

        $fileData =
            file_get_contents(
                $absolutePath
            );

        if ($fileData === false) {
            throw new RuntimeException(
                'Unable to read image attachment.'
            );
        }

        /*
         * DeepSeek uses OpenAI-compatible
         * image_url content parts.
         */
        $dataUrl = sprintf(
            'data:%s;base64,%s',
            $mimeType,
            base64_encode(
                $fileData
            )
        );

        return [
            'type' =>
                'image_url',

            'image_url' => [
                'url' =>
                    $dataUrl,

                'detail' =>
                    'auto',
            ],
        ];
    }
}
