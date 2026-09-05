<?php
namespace App\Services\AI;

use App\Exceptions\AIProviderException;
use Gemini;
use Gemini\Data\Blob;
use Gemini\Data\Content;
use Gemini\Data\UploadedFile;
use Gemini\Enums\FileState;
use Gemini\Enums\MimeType;
use Gemini\Enums\Role;
use GuzzleHttp\Client;
use RuntimeException;
use Throwable;

class GoogleAIService implements AIProviderInterface
{
    public function chat(
        string $model,
        array $messages,
        array $attachments = []
    ): array {
        try {

            /*
            |--------------------------------------------------------------------------
            | 1. Build Gemini client
            |--------------------------------------------------------------------------
            */

            $httpClient = new Client([
                'timeout' => config(
                    'gemini.request_timeout',
                    60
                ),

                'connect_timeout' => 10,

                'verify' =>
                    config('gemini.ca_bundle'),
            ]);

            $client = Gemini::factory()
                ->withApiKey(
                    config('gemini.api_key')
                )
                ->withHttpClient(
                    $httpClient
                )
                ->make();

            /*
            |--------------------------------------------------------------------------
            | 2. Validate messages
            |--------------------------------------------------------------------------
            */

            if (empty($messages)) {
                throw new RuntimeException(
                    'Gemini conversation cannot be empty.'
                );
            }

            /*
             * Current user message is always
             * the last item added by AIService.
             */
            $currentMessage = array_pop(
                $messages
            );

            if (
                ($currentMessage['role'] ?? null)
                !== 'user'
            ) {
                throw new RuntimeException(
                    'The current Gemini message must belong to the user.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Convert PREVIOUS history to Gemini Content objects
            |--------------------------------------------------------------------------
            |
            | Important:
            |
            | We do NOT place UploadedFile inside Content->parts.
            |
            */

            $history = array_map(
                function (array $message): Content {

                    $role =
                        $message['role'] === 'assistant'
                            ? Role::MODEL
                            : Role::USER;

                    return Content::parse(
                        part: $message['content'],
                        role: $role
                    );
                },
                $messages
            );

            /*
            |--------------------------------------------------------------------------
            | 4. Start Gemini chat with previous history
            |--------------------------------------------------------------------------
            */

            $chat = $client
                ->generativeModel(
                    model: $model
                )
                ->startChat(
                    history: $history
                );

            /*
            |--------------------------------------------------------------------------
            | 5. Build CURRENT multimodal message
            |--------------------------------------------------------------------------
            |
            | sendMessage() / generateContent() accepts:
            |
            | string
            | Blob
            | UploadedFile
            |
            | in the input array.
            |
            */

            $currentParts = [
                $currentMessage['content'],
            ];

            foreach ($attachments as $attachment) {

                $type =
                    $attachment['type']
                    ?? null;

                if ($type === 'image') {

                    $currentParts[] =
                        $this->buildImageInput(
                            $attachment
                        );

                    continue;
                }

                if ($type === 'document') {

                    $currentParts[] =
                        $this->buildDocumentInput(
                            $client,
                            $attachment
                        );

                    continue;
                }

                throw new RuntimeException(
                    'Unsupported Gemini attachment type.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 6. Send current user message
            |--------------------------------------------------------------------------
            */

            if (empty($attachments)) {

                /*
                 * Simple text message.
                 */
                $response = $chat->sendMessage(
                    $currentMessage['content']
                );

            } else {

                /*
                 * Multimodal message.
                 *
                 * Important:
                 *
                 * UploadedFile belongs here,
                 * NOT inside Content(parts: ...).
                 */
                $response = $chat->sendMessage(
                    $currentParts
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 7. Unified response
            |--------------------------------------------------------------------------
            */

            return [
                'content' =>
                    $response->text(),

                'input_tokens' =>
                    (int) (
                        $response
                            ->usageMetadata
                            ->promptTokenCount
                        ?? 0
                    ),

                'output_tokens' =>
                    (int) (
                        $response
                            ->usageMetadata
                            ->candidatesTokenCount
                        ?? 0
                    ),
            ];

        } catch (Throwable $e) {

            report($e);

            throw new AIProviderException(
                config('app.debug')
                    ? $e->getMessage()
                    : 'The AI provider is temporarily unavailable. Please try again later.',
                503,
                $e
            );
        }
    }

    /**
     * Build inline Gemini image input.
     */
    private function buildImageInput(
        array $attachment
    ): Blob {
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
                'Gemini image attachment was not found.'
            );
        }

        if (
            ! str_starts_with(
                $mimeType ?? '',
                'image/'
            )
        ) {
            throw new RuntimeException(
                'Invalid Gemini image attachment.'
            );
        }

        $data = file_get_contents(
            $absolutePath
        );

        if ($data === false) {
            throw new RuntimeException(
                'Unable to read Gemini image attachment.'
            );
        }

        return new Blob(
            mimeType:
                $this->resolveGeminiMimeType(
                    $mimeType
                ),

            data:
                base64_encode(
                    $data
                )
        );
    }

    /**
     * Upload document to Gemini Files API
     * and return an UploadedFile reference.
     */
    private function buildDocumentInput(
        $client,
        array $attachment
    ): UploadedFile {
        $mimeType =
            $attachment['mime_type']
            ?? null;

        $absolutePath =
            $attachment['absolute_path']
            ?? null;

        if (
            $mimeType !==
            'application/pdf'
        ) {
            throw new RuntimeException(
                'Gemini document attachments currently support PDF only.'
            );
        }

        if (
            ! $absolutePath ||
            ! is_file($absolutePath)
        ) {
            throw new RuntimeException(
                'Gemini PDF attachment was not found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Upload PDF
        |--------------------------------------------------------------------------
        */

        $files = $client->files();

        $meta = $files->upload(
            filename:
                $absolutePath,

            mimeType:
                MimeType::APPLICATION_PDF,

            displayName:
                $attachment['name']
                ?? basename(
                    $absolutePath
                )
        );

        /*
        |--------------------------------------------------------------------------
        | Wait for processing
        |--------------------------------------------------------------------------
        */

        $attempts = 0;
        $maxAttempts = 30;

        while (! $meta->state->complete()) {

            if (
                $meta->state ===
                FileState::Failed
            ) {
                throw new RuntimeException(
                    'Gemini failed to process the uploaded PDF.'
                );
            }

            if (
                $attempts >=
                $maxAttempts
            ) {
                throw new RuntimeException(
                    'Gemini file processing timed out.'
                );
            }

            sleep(1);

            $meta = $files->metadataGet(
                $meta->uri
            );

            $attempts++;
        }

        return new UploadedFile(
            fileUri:
                $meta->uri,

            mimeType:
                MimeType::APPLICATION_PDF
        );
    }

    /**
     * Resolve MIME type supported by
     * the installed Gemini PHP client.
     */
    private function resolveGeminiMimeType(
        ?string $mimeType
    ): MimeType {
        return match ($mimeType) {

            'image/jpeg' =>
                MimeType::IMAGE_JPEG,

            'image/png' =>
                MimeType::IMAGE_PNG,

            'image/webp' =>
                MimeType::IMAGE_WEBP,

            'application/pdf' =>
                MimeType::APPLICATION_PDF,

            default =>
                throw new RuntimeException(
                    "Unsupported Gemini MIME type: {$mimeType}"
                ),
        };
    }
}
