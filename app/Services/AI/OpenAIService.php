<?php
namespace App\Services\AI;

use App\Exceptions\AIProviderException;
use GuzzleHttp\Client as GuzzleClient;
use RuntimeException;
use Throwable;

class OpenAIService implements AIProviderInterface
{
    public function chat(
        string $model,
        array $messages,
        array $attachments = []
    ): array {
        try {
            $factory = new \OpenAI\Factory();

            $client = $factory
                ->withApiKey(
                    config('openai.api_key')
                )
                ->withHttpClient(
                    new GuzzleClient([
                        'timeout' => config(
                            'openai.request_timeout',
                            60
                        ),

                        'connect_timeout' => 10,

                        'verify' =>
                            config('openai.ca_bundle'),
                    ])
                )
                ->make();

            /*
            |--------------------------------------------------------------------------
            | 1. Convert conversation history
            |--------------------------------------------------------------------------
            */

            $input = collect($messages)
                ->map(function (array $message) {
                    return [
                        'role' =>
                            $message['role'],

                        'content' => [
                            [
                                'type' =>
                                    'input_text',

                                'text' =>
                                    $message['content'],
                            ],
                        ],
                    ];
                })
                ->values()
                ->toArray();

            /*
            |--------------------------------------------------------------------------
            | 2. Add attachments to CURRENT user message
            |--------------------------------------------------------------------------
            */

            if (! empty($attachments)) {

                $lastIndex = array_key_last(
                    $input
                );

                if ($lastIndex === null) {
                    throw new RuntimeException(
                        'Unable to attach files without a user message.'
                    );
                }

                /*
                 * Attachments should belong to
                 * the latest user message.
                 */
                if (
                    ($input[$lastIndex]['role'] ?? null)
                    !== 'user'
                ) {
                    throw new RuntimeException(
                        'Attachments must be associated with a user message.'
                    );
                }

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

                        $input[
                            $lastIndex
                        ]['content'][] =
                            $this->buildImageInput(
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

                        $input[
                            $lastIndex
                        ]['content'][] =
                            $this->buildFileInput(
                                $attachment
                            );

                        continue;
                    }

                    throw new RuntimeException(
                        'Unsupported attachment type.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Send Responses API request
            |--------------------------------------------------------------------------
            */

            $response = $client
                ->responses()
                ->create([
                    'model' =>
                        $model,

                    'input' =>
                        $input,
                ]);

            /*
            |--------------------------------------------------------------------------
            | 4. Return unified response
            |--------------------------------------------------------------------------
            */

            return [
                'content' =>
                    $response->outputText,

                'input_tokens' =>
                    (int) (
                        $response
                            ->usage
                            ->inputTokens
                        ?? 0
                    ),

                'output_tokens' =>
                    (int) (
                        $response
                            ->usage
                            ->outputTokens
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
     * Build OpenAI image input.
     */
    private function buildImageInput(
        array $attachment
    ): array {
        $absolutePath =
            $attachment['absolute_path'];

        $mimeType =
            $attachment['mime_type']
            ?? null;

        /*
         * Only image MIME types are accepted here.
         */
        if (
            ! $mimeType ||
            ! str_starts_with(
                $mimeType,
                'image/'
            )
        ) {
            throw new RuntimeException(
                'Invalid image attachment.'
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
         * Responses API accepts image_url
         * using a data URL.
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
                'input_image',

            'image_url' =>
                $dataUrl,

            /*
             * Can later be changed to:
             * low / high / auto
             */
            'detail' =>
                'auto',
        ];
    }

    /**
     * Build OpenAI document/file input.
     */
    private function buildFileInput(
        array $attachment
    ): array {
        $absolutePath =
            $attachment['absolute_path'];

        $fileData =
            file_get_contents(
                $absolutePath
            );

        if ($fileData === false) {
            throw new RuntimeException(
                'Unable to read document attachment.'
            );
        }

        return [
            'type' =>
                'input_file',

            'filename' =>
                $attachment['name']
                ?? basename(
                    $absolutePath
                ),

            /*
             * File input accepts inline file data.
             */
            'file_data' =>
                base64_encode(
                    $fileData
                ),
        ];
    }
}
