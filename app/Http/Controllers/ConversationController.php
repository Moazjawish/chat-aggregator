<?php
namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ConversationController extends Controller
{
    /**
     * Get all conversations for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $conversations = $user
                ->conversations()
                ->withCount('messages')
                ->latest('updated_at')
                ->get();

            return response()->json([
                'conversations' => $conversations
                    ->map(function (Conversation $conversation) {
                        return [
                            'id' =>
                                $conversation->id,

                            'title' =>
                                $conversation->title,

                            'messages_count' =>
                                $conversation->messages_count,

                            'created_at' =>
                                $conversation->created_at,

                            'updated_at' =>
                                $conversation->updated_at,
                        ];
                    })
                    ->values(),
            ], 200);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' =>
                    'Unable to retrieve conversations.',
            ], 500);
        }
    }

    /**
     * Create a new conversation.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $request->validate([
                'title' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
            ]);

            $conversation = $user
                ->conversations()
                ->create([
                    'title' =>
                        $request->input('title'),
                ]);

            return response()->json([
                'message' =>
                    'Conversation created successfully.',

                'conversation' => [
                    'id' =>
                        $conversation->id,

                    'title' =>
                        $conversation->title,

                    'messages_count' =>
                        0,

                    'created_at' =>
                        $conversation->created_at,

                    'updated_at' =>
                        $conversation->updated_at,
                ],
            ], 201);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' =>
                    'Unable to create conversation.',
            ], 500);
        }
    }

    /**
     * Show one conversation with:
     *
     * - messages
     * - model used for every message
     * - attachments belonging to every message
     */
    public function show(
        Request $request,
        int $conversation
    ): JsonResponse {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | Load conversation
            |--------------------------------------------------------------------------
            |
            | Searching through $user->conversations()
            | guarantees that the conversation belongs
            | to the authenticated user.
            |
            */

            $conversationModel = $user
                ->conversations()
                ->with([
                    'messages' => function ($query) {
                        $query
                            ->with([
                                'model:id,name,provider',

                                /*
                                 * Load attachments belonging
                                 * to each exact message.
                                 */
                                'files:id,user_id,conversation_id,original_name,mime_type,extension,size,status',
                            ])
                            ->orderBy('created_at')
                            ->orderBy('id');
                    },
                ])
                ->find($conversation);

            if (! $conversationModel) {
                return response()->json([
                    'message' =>
                        'Conversation not found.',
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'conversation' => [
                    'id' =>
                        $conversationModel->id,

                    'title' =>
                        $conversationModel->title,

                    'created_at' =>
                        $conversationModel->created_at,

                    'updated_at' =>
                        $conversationModel->updated_at,
                ],

                'messages' => $conversationModel
                    ->messages
                    ->map(function ($message) {

                        return [
                            'id' =>
                                $message->id,

                            'role' =>
                                $message->role,

                            'content' =>
                                $message->content,

                            /*
                             * Model used for this message.
                             */
                            'model' =>
                                $message->model
                                    ? [
                                        'id' =>
                                            $message->model->id,

                                        'name' =>
                                            $message->model->name,

                                        'provider' =>
                                            $message->model->provider,
                                    ]
                                    : null,

                            /*
                             * Files/images attached
                             * specifically to this message.
                             */
                            'attachments' =>
                                $message->files
                                    ->map(function ($file) {

                                        $type = str_starts_with(
                                            $file->mime_type ?? '',
                                            'image/'
                                        )
                                            ? 'image'
                                            : 'document';

                                        return [
                                            'id' =>
                                                $file->id,

                                            'type' =>
                                                $type,

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
                                        ];
                                    })
                                    ->values(),

                            /*
                             * Usage/cost fields mainly exist
                             * on assistant messages.
                             */
                            'input_tokens' =>
                                $message->input_tokens,

                            'output_tokens' =>
                                $message->output_tokens,

                            'provider_cost' =>
                                $message->provider_cost,

                            'user_cost' =>
                                $message->user_cost,

                            'created_at' =>
                                $message->created_at,
                        ];
                    })
                    ->values(),
            ], 200);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' =>
                    'Unable to retrieve conversation.',
            ], 500);
        }
    }

    /**
     * Update conversation title.
     */
    public function update(
        Request $request,
        int $conversation
    ): JsonResponse {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $request->validate([
                'title' => [
                    'required',
                    'string',
                    'max:255',
                ],
            ]);

            /*
             * Make sure the conversation
             * belongs to this user.
             */
            $conversationModel = $user
                ->conversations()
                ->find($conversation);

            if (! $conversationModel) {
                return response()->json([
                    'message' =>
                        'Conversation not found.',
                ], 404);
            }

            $conversationModel->update([
                'title' =>
                    $request->input('title'),
            ]);

            return response()->json([
                'message' =>
                    'Conversation updated successfully.',

                'conversation' => [
                    'id' =>
                        $conversationModel->id,

                    'title' =>
                        $conversationModel->title,

                    'created_at' =>
                        $conversationModel->created_at,

                    'updated_at' =>
                        $conversationModel->updated_at,
                ],
            ], 200);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' =>
                    'Unable to update conversation.',
            ], 500);
        }
    }

    /**
     * Delete a conversation.
     */
    public function destroy(
        Request $request,
        int $conversation
    ): JsonResponse {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            /*
             * Make sure the conversation
             * belongs to this user.
             */
            $conversationModel = $user
                ->conversations()
                ->find($conversation);

            if (! $conversationModel) {
                return response()->json([
                    'message' =>
                        'Conversation not found.',
                ], 404);
            }

            $conversationModel->delete();

            return response()->json([
                'message' =>
                    'Conversation deleted successfully.',
            ], 200);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' =>
                    'Unable to delete conversation.',
            ], 500);
        }
    }
}
