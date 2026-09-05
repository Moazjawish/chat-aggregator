<?php
namespace App\Http\Controllers;

use App\Models\File;
use App\Services\FeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FileController extends Controller
{
    /**
     * Upload a document or image for the authenticated user.
     */
    public function store(
        Request $request,
        FeatureService $featureService
    ): JsonResponse {
        $request->validate([
            'conversation_id' => [
                'nullable',
                'integer',
            ],

            'file' => [
                'required',
                'file',

                /*
                 * Maximum size: 10 MB
                 */
                'max:10240',

                /*
                 * Documents + images.
                 */
                'mimes:pdf,txt,doc,docx,jpg,jpeg,png,webp',
            ],
        ]);

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | 1. Validate conversation ownership
        |--------------------------------------------------------------------------
        */

        $conversation = null;

        if ($request->filled('conversation_id')) {

            $conversation = $user
                ->conversations()
                ->where(
                    'id',
                    $request->conversation_id
                )
                ->first();

            if (! $conversation) {
                return response()->json([
                    'message' => 'Conversation not found.',
                ], 404);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Read uploaded file metadata
        |--------------------------------------------------------------------------
        */

        $uploadedFile = $request->file('file');

        $originalName =
            $uploadedFile->getClientOriginalName();

        $mimeType =
            $uploadedFile->getMimeType();

        $extension = strtolower(
            $uploadedFile->getClientOriginalExtension()
        );

        $size =
            $uploadedFile->getSize();

        /*
        |--------------------------------------------------------------------------
        | 3. Detect attachment type
        |--------------------------------------------------------------------------
        |
        | image/*  => image
        | anything else from allowed list => document
        |
        */

        $attachmentType = str_starts_with(
            $mimeType,
            'image/'
        )
            ? 'image'
            : 'document';

        /*
        |--------------------------------------------------------------------------
        | 4. Check subscription feature
        |--------------------------------------------------------------------------
        |
        | Documents require:
        | file_upload
        |
        | Images require:
        | image_upload
        |
        */

        $requiredFeature = $attachmentType === 'image'
            ? 'image_upload'
            : 'file_upload';

        if (! $featureService->has(
            $user,
            $requiredFeature
        )) {
            return response()->json([
                'message' =>
                    $attachmentType === 'image'
                        ? 'Image upload is not available in your subscription plan.'
                        : 'File upload is not available in your subscription plan.',

                'feature' =>
                    $requiredFeature,
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Store physical file
        |--------------------------------------------------------------------------
        */

        $path = null;

        try {

            /*
             * Separate images and documents
             * into different storage folders.
             */
            $folder = $attachmentType === 'image'
                ? "users/{$user->id}/images"
                : "users/{$user->id}/files";

            $path = $uploadedFile->store(
                $folder,
                'local'
            );

            if (! $path) {
                return response()->json([
                    'message' =>
                        'Unable to store uploaded file.',
                ], 500);
            }

            /*
            |--------------------------------------------------------------------------
            | 6. Create database record
            |--------------------------------------------------------------------------
            */

            $file = DB::transaction(function () use (
                $user,
                $conversation,
                $originalName,
                $path,
                $mimeType,
                $extension,
                $size
            ) {
                return File::create([
                    'user_id' =>
                        $user->id,

                    'conversation_id' =>
                        $conversation?->id,

                    'original_name' =>
                        $originalName,

                    'path' =>
                        $path,

                    'disk' =>
                        'local',

                    'mime_type' =>
                        $mimeType,

                    'extension' =>
                        $extension,

                    'size' =>
                        $size,

                    'status' =>
                        'uploaded',

                    /*
                     * These fields are no longer used
                     * for manual text extraction,
                     * but they can remain nullable
                     * until the schema is cleaned later.
                     */
                    'extracted_text' =>
                        null,

                    'processing_error' =>
                        null,
                ]);
            });

            /*
            |--------------------------------------------------------------------------
            | 7. Return response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'message' =>
                    $attachmentType === 'image'
                        ? 'Image uploaded successfully.'
                        : 'File uploaded successfully.',

                'attachment' => [
                    'id' =>
                        $file->id,

                    'type' =>
                        $attachmentType,

                    'conversation_id' =>
                        $file->conversation_id,

                    'original_name' =>
                        $file->original_name,

                    'mime_type' =>
                        $file->mime_type,

                    'extension' =>
                        $file->extension,

                    'size' =>
                        $file->size,

                    'status' =>
                        $file->status,

                    'created_at' =>
                        $file->created_at,
                ],
            ], 201);

        } catch (Throwable $e) {

            /*
             * Prevent orphan physical files.
             */
            if (
                $path &&
                Storage::disk('local')->exists($path)
            ) {
                Storage::disk('local')->delete($path);
            }

            report($e);

            return response()->json([
                'message' =>
                    'Unable to upload attachment.',
            ], 500);
        }
    }
}
