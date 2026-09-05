<?php
namespace App\Services\AI;

use App\Exceptions\AIProviderException;
use App\Exceptions\AIRequestBusyException;
use App\Exceptions\UsageLimitExceededException;
use App\Models\AIModel;
use App\Models\Conversation;
use App\Models\File;
use App\Models\ModelCost;
use App\Models\ModelUsage;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Throwable;

class AIService
{
    public function __construct(
        private OpenAIService $openAIService,
        private GoogleAIService $googleAIService,
        private ClaudeService $claudeService,
        private DeepSeekService $deepSeekService,
        private UsageService $usageService,
    ) {
    }

    /**
     * Send a message to an AI model.
     */
    public function chat(
        User $user,
        Subscription $subscription,
        Conversation $conversation,
        AIModel $model,
        string $message,
        array $fileIds = []
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Validate conversation
        |--------------------------------------------------------------------------
        */

        if ((int) $conversation->user_id !== (int) $user->id) {
            throw new RuntimeException(
                'You are not allowed to access this conversation.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate subscription
        |--------------------------------------------------------------------------
        */

        if ((int) $subscription->user_id !== (int) $user->id) {
            throw new RuntimeException(
                'Invalid subscription.'
            );
        }

        if (! $subscription->valid()) {
            throw new RuntimeException(
                'Your subscription is not active.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Current plan
        |--------------------------------------------------------------------------
        */

        $plan = $subscription->subscriptionPlan;

        if (! $plan) {
            throw new RuntimeException(
                'Subscription plan not found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Check if model belongs to current plan
        |--------------------------------------------------------------------------
        */

        $planModel = $plan
            ->activeModels()
            ->where('models.id', $model->id)
            ->first();

        if (! $planModel) {
            throw new RuntimeException(
                'This model is not available in your current subscription plan.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate CURRENT attachments
        |--------------------------------------------------------------------------
        |
        | These are files explicitly sent in this request using:
        |
        | file_ids: [...]
        |
        */

        $files = $this->validateAttachments(
            user: $user,
            plan: $plan,
            model: $model,
            conversation: $conversation,
            fileIds: $fileIds,
        );

        /*
        |--------------------------------------------------------------------------
        | Get current provider cost
        |--------------------------------------------------------------------------
        */

        $modelCost = ModelCost::query()
            ->where('model_id', $model->id)
            ->where(function ($query) {
                $query
                    ->whereNull('effective_from')
                    ->orWhere(
                        'effective_from',
                        '<=',
                        now()
                    );
            })
            ->where(function ($query) {
                $query
                    ->whereNull('effective_to')
                    ->orWhere(
                        'effective_to',
                        '>',
                        now()
                    );
            })
            ->latest('effective_from')
            ->first();

        if (! $modelCost) {
            throw new LogicException(
                "No active cost configuration exists for model {$model->name}."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Atomic usage lock
        |--------------------------------------------------------------------------
        |
        | Prevent simultaneous requests from checking the same usage
        | at the same time and both passing the token limit.
        |
        */

        $lockKey =
            "ai-usage:subscription:{$subscription->id}:model:{$model->id}";

        $lock = Cache::lock(
            $lockKey,
            120
        );

        try {

            return $lock->block(
                5,
                function () use (
                    $user,
                    $subscription,
                    $conversation,
                    $model,
                    $message,
                    $files,
                    $plan,
                    $planModel,
                    $modelCost
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | Check usage limit inside lock
                    |--------------------------------------------------------------------------
                    */

                if (
    ! $this->usageService->canUse(
        subscription: $subscription,
        model: $model
    )
) {
    throw new UsageLimitExceededException(
        'You have reached the usage limit for this model.'
    );
}

                    /*
                    |--------------------------------------------------------------------------
                    | Load complete previous history
                    |--------------------------------------------------------------------------
                    |
                    | We now load attachments belonging to each previous message.
                    |
                    */

                    $historyMessages = $conversation
                        ->messages()
                        ->with([
                            'files:id,user_id,conversation_id,original_name,path,disk,mime_type,extension,size,status',
                        ])
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->get();

                    /*
                    |--------------------------------------------------------------------------
                    | Build previous text history
                    |--------------------------------------------------------------------------
                    */

                    $history = $historyMessages
                        ->map(function ($historyMessage) {
                            return [
                                'role' =>
                                    $historyMessage->role,

                                'content' =>
                                    $historyMessage->content,
                            ];
                        })
                        ->values()
                        ->all();

                    /*
                    |--------------------------------------------------------------------------
                    | Add current user message
                    |--------------------------------------------------------------------------
                    */

                    $history[] = [
                        'role' => 'user',
                        'content' => $message,
                    ];

                    /*
                    |--------------------------------------------------------------------------
                    | Get attachments used in previous USER messages
                    |--------------------------------------------------------------------------
                    |
                    | Example:
                    |
                    | Message 1:
                    |   "Explain this CV"
                    |    └── cv.pdf
                    |
                    | Message 3:
                    |   "What do you see here?"
                    |    └── image.png
                    |
                    | Both files remain part of the conversation context.
                    |
                    */

                    $historicalFiles = $historyMessages
                        ->where(
                            'role',
                            'user'
                        )
                        ->flatMap(
                            function ($historyMessage) {
                                return $historyMessage->files;
                            }
                        )
                        ->unique('id')
                        ->values();

                    /*
                    |--------------------------------------------------------------------------
                    | Historical + current files
                    |--------------------------------------------------------------------------
                    |
                    | IMPORTANT:
                    |
                    | $files
                    | = explicitly attached to current message
                    |
                    | $contextFiles
                    | = all files necessary for AI context
                    |
                    */

                    $contextFiles = $historicalFiles
                        ->concat($files)
                        ->unique('id')
                        ->values();

                    /*
                    |--------------------------------------------------------------------------
                    | Validate ALL context files against CURRENT model
                    |--------------------------------------------------------------------------
                    |
                    | Important when switching models.
                    |
                    | A PDF may have originally been sent to Gemini,
                    | but the newly selected model may not support documents.
                    |
                    */

                    $this->validateAttachmentCollection(
                        user: $user,
                        plan: $plan,
                        model: $model,
                        conversation: $conversation,
                        files: $contextFiles
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Build provider-neutral attachments
                    |--------------------------------------------------------------------------
                    */

                    $attachments = $this->buildAttachments(
                        $contextFiles
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Send request to provider
                    |--------------------------------------------------------------------------
                    */

                    $providerResponse =
                        $this->sendToProvider(
                            model: $model,
                            messages: $history,
                            attachments: $attachments
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Provider result
                    |--------------------------------------------------------------------------
                    */

                    $assistantContent =
                        $providerResponse['content']
                        ?? null;

                    $inputTokens =
                        (int) (
                            $providerResponse['input_tokens']
                            ?? 0
                        );

                    $outputTokens =
                        (int) (
                            $providerResponse['output_tokens']
                            ?? 0
                        );

                    if (
                        ! is_string($assistantContent) ||
                        trim($assistantContent) === ''
                    ) {
                        throw new RuntimeException(
                            'The AI provider returned an empty response.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Calculate provider cost
                    |--------------------------------------------------------------------------
                    |
                    | Prices are per 1,000,000 tokens.
                    |
                    */

                    $providerInputCost =
                        ($inputTokens / 1_000_000)
                        * (float) $modelCost->input_cost;

                    $providerOutputCost =
                        ($outputTokens / 1_000_000)
                        * (float) $modelCost->output_cost;

                    $totalProviderCost =
                        $providerInputCost
                        +
                        $providerOutputCost;

                    /*
                    |--------------------------------------------------------------------------
                    | Calculate user cost
                    |--------------------------------------------------------------------------
                    |
                    | Plan-model pivot contains customer-facing prices.
                    |
                    */

                    $inputPrice =
                        (float) (
                            $planModel->pivot->input_price
                            ?? 0
                        );

                    $outputPrice =
                        (float) (
                            $planModel->pivot->output_price
                            ?? 0
                        );

                    $userInputCost =
                        ($inputTokens / 1_000_000)
                        * $inputPrice;

                    $userOutputCost =
                        ($outputTokens / 1_000_000)
                        * $outputPrice;

                    $totalUserCost =
                        $userInputCost
                        +
                        $userOutputCost;

                    /*
                    |--------------------------------------------------------------------------
                    | Save everything atomically
                    |--------------------------------------------------------------------------
                    */

                    $result = DB::transaction(
                        function () use (
                            $user,
                            $subscription,
                            $conversation,
                            $model,
                            $message,
                            $assistantContent,
                            $inputTokens,
                            $outputTokens,
                            $totalProviderCost,
                            $totalUserCost,
                            $files
                        ) {

                            /*
                            |--------------------------------------------------------------------------
                            | Save USER message
                            |--------------------------------------------------------------------------
                            */

                            $userMessage = $conversation
                                ->messages()
                                ->create([
                                    'model_id' =>
                                        $model->id,

                                    'role' =>
                                        'user',

                                    'content' =>
                                        $message,

                                    'input_tokens' =>
                                        0,

                                    'output_tokens' =>
                                        0,

                                    'provider_cost' =>
                                        0,

                                    'user_cost' =>
                                        0,
                                ]);

                            /*
                            |--------------------------------------------------------------------------
                            | Link CURRENT files to CURRENT message
                            |--------------------------------------------------------------------------
                            |
                            | Very important:
                            |
                            | Use $files here.
                            |
                            | DO NOT use $contextFiles.
                            |
                            | Historical files are context only and must not
                            | be attached again to every new message.
                            |
                            */

                            if ($files->isNotEmpty()) {

                                $userMessage
                                    ->files()
                                    ->attach(
                                        $files
                                            ->pluck('id')
                                            ->all()
                                    );
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | Assign newly uploaded files to conversation
                            |--------------------------------------------------------------------------
                            */

                            if ($files->isNotEmpty()) {

                                File::query()
                                    ->whereIn(
                                        'id',
                                        $files->pluck('id')
                                    )
                                    ->whereNull(
                                        'conversation_id'
                                    )
                                    ->update([
                                        'conversation_id' =>
                                            $conversation->id,
                                    ]);
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | Generate conversation title
                            |--------------------------------------------------------------------------
                            |
                            | Only when no title exists.
                            |
                            */

                            if (
                                blank(
                                    $conversation->title
                                )
                            ) {
                                $conversation->update([
                                    'title' =>
                                        mb_substr(
                                            $message,
                                            0,
                                            100
                                        ),
                                ]);
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | Save ASSISTANT message
                            |--------------------------------------------------------------------------
                            */

                            $assistantMessage =
                                $conversation
                                    ->messages()
                                    ->create([
                                        'model_id' =>
                                            $model->id,

                                        'role' =>
                                            'assistant',

                                        'content' =>
                                            $assistantContent,

                                        'input_tokens' =>
                                            $inputTokens,

                                        'output_tokens' =>
                                            $outputTokens,

                                        'provider_cost' =>
                                            $totalProviderCost,

                                        'user_cost' =>
                                            $totalUserCost,
                                    ]);

                            /*
                            |--------------------------------------------------------------------------
                            | Save model usage
                            |--------------------------------------------------------------------------
                            */

                            $usage = ModelUsage::create([
                                'user_id' =>
                                    $user->id,

                                'subscription_id' =>
                                    $subscription->id,

                                'model_id' =>
                                    $model->id,

                                'input_tokens' =>
                                    $inputTokens,

                                'output_tokens' =>
                                    $outputTokens,

                                'total_provider_cost' =>
                                    $totalProviderCost,

                                'total_user_cost' =>
                                    $totalUserCost,
                            ]);

                            /*
                            |--------------------------------------------------------------------------
                            | Touch conversation
                            |--------------------------------------------------------------------------
                            */

                            $conversation->touch();

                            return [
                                'user_message' =>
                                    $userMessage,

                                'assistant_message' =>
                                    $assistantMessage,

                                'usage' =>
                                    $usage,
                            ];
                        }
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | API response
                    |--------------------------------------------------------------------------
                    */

                    return [
                        'conversation_id' =>
                            $conversation->id,

                        'user_message_id' =>
                            $result['user_message']->id,

                        'assistant_message_id' =>
                            $result['assistant_message']->id,

                        'model' => [
                            'id' =>
                                $model->id,

                            'name' =>
                                $model->name,

                            'provider' =>
                                $model->provider,
                        ],

                        'message' =>
                            $assistantContent,

                        /*
                         * Return only CURRENT attachments.
                         *
                         * Historical context files do not need to be
                         * returned as if the user attached them again.
                         */
                        'files' => $files
                            ->map(function ($file) {
                                return [
                                    'id' =>
                                        $file->id,

                                    'type' =>
                                        $this->attachmentType(
                                            $file
                                        ),

                                    'name' =>
                                        $file->original_name,

                                    'mime_type' =>
                                        $file->mime_type,

                                    'size' =>
                                        $file->size,
                                ];
                            })
                            ->values()
                            ->all(),

                        'usage' => [
                            'input_tokens' =>
                                $inputTokens,

                            'output_tokens' =>
                                $outputTokens,

                            'provider_cost' =>
                                $totalProviderCost,

                            'user_cost' =>
                                $totalUserCost,
                        ],
                    ];
                }
            );

        } catch (LockTimeoutException $e) {

            throw new AIRequestBusyException(
                'Another request for this model is currently being processed. Please try again.'
            );
        }
    }

    /**
     * Validate attachments explicitly sent with the current request.
     */
    private function validateAttachments(
        User $user,
        SubscriptionPlan $plan,
        AIModel $model,
        Conversation $conversation,
        array $fileIds
    ): Collection {

        if (empty($fileIds)) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize IDs
        |--------------------------------------------------------------------------
        */

        $fileIds = collect($fileIds)
            ->map(
                fn ($id) => (int) $id
            )
            ->filter(
                fn ($id) => $id > 0
            )
            ->unique()
            ->values();

        if ($fileIds->isEmpty()) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Get files belonging to authenticated user only
        |--------------------------------------------------------------------------
        */

        $files = File::query()
            ->where(
                'user_id',
                $user->id
            )
            ->whereIn(
                'id',
                $fileIds->all()
            )
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Prevent requesting files belonging to another user
        |--------------------------------------------------------------------------
        */

        if (
            $files->count()
            !==
            $fileIds->count()
        ) {
            throw new RuntimeException(
                'One or more attachments were not found or do not belong to you.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Shared attachment validation
        |--------------------------------------------------------------------------
        */

        $this->validateAttachmentCollection(
            user: $user,
            plan: $plan,
            model: $model,
            conversation: $conversation,
            files: $files
        );

        return $files;
    }

    /**
     * Validate files against:
     *
     * - ownership
     * - conversation
     * - physical storage
     * - subscription plan feature
     * - model capability
     */
    private function validateAttachmentCollection(
        User $user,
        SubscriptionPlan $plan,
        AIModel $model,
        Conversation $conversation,
        Collection $files
    ): void {

        foreach ($files as $file) {

            /*
            |--------------------------------------------------------------------------
            | File owner
            |--------------------------------------------------------------------------
            */

            if (
                (int) $file->user_id
                !==
                (int) $user->id
            ) {
                throw new RuntimeException(
                    'You are not allowed to use one of these attachments.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Conversation
            |--------------------------------------------------------------------------
            |
            | conversation_id NULL:
            | newly uploaded file.
            |
            | Otherwise it must belong to the current conversation.
            |
            */

            if (
                $file->conversation_id !== null
                &&
                (int) $file->conversation_id
                !==
                (int) $conversation->id
            ) {
                throw new RuntimeException(
                    'One of the attachments belongs to another conversation.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Storage existence
            |--------------------------------------------------------------------------
            */

            if (
                ! Storage::disk(
                    $file->disk
                )->exists(
                    $file->path
                )
            ) {
                throw new RuntimeException(
                    "Attachment not found: {$file->original_name}"
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Attachment type
            |--------------------------------------------------------------------------
            */

            $type =
                $this->attachmentType(
                    $file
                );

            /*
            |--------------------------------------------------------------------------
            | Image
            |--------------------------------------------------------------------------
            */

            if ($type === 'image') {

                if (
                    ! $plan->hasFeature(
                        'image_upload'
                    )
                ) {
                    throw new RuntimeException(
                        'Your current plan does not support image uploads.'
                    );
                }

                if (
                    ! $model->hasCapability(
                        'image_input'
                    )
                ) {
                    throw new RuntimeException(
                        'The selected model does not support image input.'
                    );
                }

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Document
            |--------------------------------------------------------------------------
            */

            if (
                ! $plan->hasFeature(
                    'file_upload'
                )
            ) {
                throw new RuntimeException(
                    'Your current plan does not support document uploads.'
                );
            }

            if (
                ! $model->hasCapability(
                    'document_input'
                )
            ) {
                throw new RuntimeException(
                    'The selected model does not support document input.'
                );
            }
        }
    }

    /**
     * Convert DB files into provider-neutral attachment structure.
     */
    private function buildAttachments(
        Collection $files
    ): array {

        return $files
            ->map(function ($file) {

                return [
                    'id' =>
                        $file->id,

                    'type' =>
                        $this->attachmentType(
                            $file
                        ),

                    'name' =>
                        $file->original_name,

                    'mime_type' =>
                        $file->mime_type,

                    'extension' =>
                        $file->extension,

                    'size' =>
                        $file->size,

                    'disk' =>
                        $file->disk,

                    'path' =>
                        $file->path,

                    'absolute_path' =>
                        Storage::disk(
                            $file->disk
                        )->path(
                            $file->path
                        ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Determine whether attachment is image or document.
     */
    private function attachmentType(
        File $file
    ): string {

        return str_starts_with(
            $file->mime_type ?? '',
            'image/'
        )
            ? 'image'
            : 'document';
    }

    /**
     * Send request to correct AI provider.
     */
    private function sendToProvider(
        AIModel $model,
        array $messages,
        array $attachments = []
    ): array {

        return match (
            strtolower(
                $model->provider
            )
        ) {

            'openai' =>
                $this->openAIService->chat(
                    model: $model->model_key,
                    messages: $messages,
                    attachments: $attachments
                ),

            'google',
            'gemini' =>
                $this->googleAIService->chat(
                    model: $model->model_key,
                    messages: $messages,
                    attachments: $attachments
                ),

            'anthropic',
            'claude' =>
                $this->claudeService->chat(
                    model: $model->model_key,
                    messages: $messages,
                    attachments: $attachments
                ),

            'deepseek' =>
                $this->deepSeekService->chat(
                    model: $model->model_key,
                    messages: $messages,
                    attachments: $attachments
                ),

            default =>
                throw new RuntimeException(
                    "Unsupported AI provider: {$model->provider}"
                ),
        };
    }
}
