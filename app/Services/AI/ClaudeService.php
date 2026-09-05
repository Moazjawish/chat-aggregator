<?php

namespace App\Services\AI;

use App\Exceptions\AIProviderException;
use GuzzleHttp\Client;
use RuntimeException;
use Throwable;

class ClaudeService implements AIProviderInterface
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
                'verify' => config('claude.ca_bundle'),
            ]);

            /*
            |--------------------------------------------------------------------------
            | 1. Convert unified conversation history
            |--------------------------------------------------------------------------
            */

            $claudeMessages = array_map(
                function (array $message) {
                    return [
                        'role' => $message['role'],

                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $message['content'],
                            ],
                        ],
                    ];
                },
                $messages
            );

            /*
            |--------------------------------------------------------------------------
            | 2. Attach files/images to CURRENT user message
            |--------------------------------------------------------------------------
            */

            if (! empty($attachments)) {

                $lastIndex = array_key_last(
                    $claudeMessages
                );

                if ($lastIndex === null) {
                    throw new RuntimeException(
                        'Unable to attach files without a user message.'
                    );
                }

                if (
                    ($claudeMessages[$lastIndex]['role'] ?? null)
                    !== 'user'
                ) {
                    throw new RuntimeException(
                        'Attachments must be associated with a user message.'
                    );
                }

                /*
                 * We place attachments before the text prompt.
                 *
                 * For long documents this is also a good prompting pattern:
                 * document first, question afterwards.
                 */
                $attachmentBlocks = [];

                foreach ($attachments as $attachment) {

                    $type =
                        $attachment['type']
                        ?? null;

                    $absolutePath =
                        $attachment['absolute_path']
                        ?? null;

                    if (
                        ! $absolutePath ||
                        ! is_file($absolutePath)
                    ) {
                        throw new RuntimeException(
                            'Attachment file not found: '
                            . (
                                $attachment['name']
                                ?? 'unknown'
                            )
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Image
                    |--------------------------------------------------------------------------
                    */

                    if ($type === 'image') {

                        $attachmentBlocks[] =
                            $this->buildImageBlock(
                                $attachment
                            );

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Document
                    |--------------------------------------------------------------------------
                    */

                    if ($type === 'document') {

                        $attachmentBlocks[] =
                            $this->buildDocumentBlock(
                                $attachment
                            );

                        continue;
                    }

                    throw new RuntimeException(
                        'Unsupported attachment type.'
                    );
                }

                /*
                 * Put attachments before the user's text.
                 */
                $claudeMessages[
                    $lastIndex
                ]['content'] = array_merge(
                    $attachmentBlocks,
                    $claudeMessages[
                        $lastIndex
                    ]['content']
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Send request
            |--------------------------------------------------------------------------
            */

            $response = $client->post(
                'https://api.anthropic.com/v1/messages',
                [
                    'headers' => [
                        'x-api-key' =>
                            config('claude.api_key'),

                        'anthropic-version' =>
                            '2023-06-01',

                        'Content-Type' =>
                            'application/json',
                    ],

                    'json' => [
                        'model' =>
                            $model,

                        'max_tokens' =>
                            1024,

                        'messages' =>
                            $claudeMessages,
                    ],
                ]
            );

            $data = json_decode(
                $response
                    ->getBody()
                    ->getContents(),
                true
            );

            /*
            |--------------------------------------------------------------------------
            | 4. Collect text response
            |--------------------------------------------------------------------------
            |
            | Claude's content is an array of content blocks.
            | We collect all text blocks instead of assuming index 0.
            |
            */

            $content = collect(
                $data['content'] ?? []
            )
                ->where(
                    'type',
                    'text'
                )
                ->pluck(
                    'text'
                )
                ->implode('');

            return [
                'content' =>
                    $content,

                'input_tokens' =>
                    (int) (
                        $data['usage']['input_tokens']
                        ?? 0
                    ),

                'output_tokens' =>
                    (int) (
                        $data['usage']['output_tokens']
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
     * Build Claude image content block.
     */
    private function buildImageBlock(
        array $attachment
    ): array {
        $mimeType =
            $attachment['mime_type']
            ?? null;

        $absolutePath =
            $attachment['absolute_path'];

        /*
         * Keep the accepted set explicit.
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
                "Unsupported Claude image type: {$mimeType}"
            );
        }

        $fileData = file_get_contents(
            $absolutePath
        );

        if ($fileData === false) {
            throw new RuntimeException(
                'Unable to read image attachment.'
            );
        }

        return [
            'type' =>
                'image',

            'source' => [
                'type' =>
                    'base64',

                'media_type' =>
                    $mimeType,

                'data' =>
                    base64_encode(
                        $fileData
                    ),
            ],
        ];
    }

    /**
     * Build Claude document content block.
     *
     * For now we support PDF documents directly.
     */
    private function buildDocumentBlock(
        array $attachment
    ): array {
        $mimeType =
            $attachment['mime_type']
            ?? null;

        $absolutePath =
            $attachment['absolute_path'];

        if (
            $mimeType !==
            'application/pdf'
        ) {
            throw new RuntimeException(
                'Claude document attachments currently support PDF only.'
            );
        }

        $fileData = file_get_contents(
            $absolutePath
        );

        if ($fileData === false) {
            throw new RuntimeException(
                'Unable to read PDF attachment.'
            );
        }

        return [
            'type' =>
                'document',

            'source' => [
                'type' =>
                    'base64',

                'media_type' =>
                    'application/pdf',

                'data' =>
                    base64_encode(
                        $fileData
                    ),
            ],
        ];
    }
}
