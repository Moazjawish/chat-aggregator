<?php
namespace App\Http\Controllers;

use App\Exceptions\AIProviderException;
use App\Exceptions\AIRequestBusyException;
use App\Exceptions\UsageLimitExceededException;
use App\Models\AIModel;
use App\Services\AI\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ChatController extends Controller
{
    public function chat(
        Request $request,
        AIService $aiService
    ): JsonResponse {
        $request->validate([
            'conversation_id' => [
                'nullable',
                'integer',
            ],

            'model_id' => [
                'required',
                'integer',
                'exists:models,id',
            ],

            'message' => [
                'required',
                'string',
            ],

            /*
             * Optional files attached
             * to this user message.
             */
            'file_ids' => [
                'nullable',
                'array',
            ],

            'file_ids.*' => [
                'integer',
                'distinct',
                'exists:files,id',
            ],
        ]);

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $model = AIModel::findOrFail(
            $request->model_id
        );

        $subscription = $user->subscription(
            'default'
        );

        if (! $subscription) {
            return response()->json([
                'message' =>
                    'You do not have an active subscription.',
            ], 403);
        }

        if (! $subscription->valid()) {
            return response()->json([
                'message' =>
                    'Your subscription is not active.',
            ], 403);
        }

        $newConversationCreated = false;

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
                    'message' =>
                        'Conversation not found.',
                ], 404);
            }

        } else {

            $conversation = $user
                ->conversations()
                ->create([
                    'title' => null,
                ]);

            $newConversationCreated = true;
        }

        try {

            $response = $aiService->chat(
                user: $user,
                subscription: $subscription,
                conversation: $conversation,
                model: $model,
                message: $request->message,
                fileIds: $request->input(
                    'file_ids',
                    []
                ),
            );

            return response()->json([
                'conversation_id' =>
                    $response['conversation_id'],

                'model' => [
                    'id' =>
                        $model->id,

                    'name' =>
                        $model->name,
                ],

                'provider' =>
                    $model->provider,

                'response' =>
                    $response['content'],

                'input_tokens' =>
                    $response['input_tokens'],

                'output_tokens' =>
                    $response['output_tokens'],

                'total_provider_cost' =>
                    $response['provider_cost'],

                'total_user_cost' =>
                    $response['user_cost'],

                'usage_id' =>
                    $response['usage_id'],

                'user_message_id' =>
                    $response['user_message_id'],

                'assistant_message_id' =>
                    $response['assistant_message_id'],

                'files' =>
                    $response['files'],
            ], 200);

        } catch (AIRequestBusyException $e) {

            $this->deleteEmptyConversation(
                $conversation,
                $newConversationCreated
            );

            return response()->json([
                'message' => $e->getMessage(),
            ], 429);

        } catch (UsageLimitExceededException $e) {

            $this->deleteEmptyConversation(
                $conversation,
                $newConversationCreated
            );

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);

        } catch (AIProviderException $e) {

            $this->deleteEmptyConversation(
                $conversation,
                $newConversationCreated
            );

            report($e);

            return response()->json([
                'message' => $e->getMessage(),
            ], $e->statusCode());

        } catch (RuntimeException $e) {

            $this->deleteEmptyConversation(
                $conversation,
                $newConversationCreated
            );

            return response()->json([
                'message' => $e->getMessage(),
            ], 403);

        } catch (Throwable $e) {

            $this->deleteEmptyConversation(
                $conversation,
                $newConversationCreated
            );

            report($e);

            return response()->json([
                'message' =>
                    'Unable to process the AI request.',
            ], 500);
        }
    }

    private function deleteEmptyConversation(
        $conversation,
        bool $newConversationCreated
    ): void {
        if (
            $newConversationCreated &&
            ! $conversation->messages()->exists()
        ) {
            $conversation->delete();
        }
    }
}
