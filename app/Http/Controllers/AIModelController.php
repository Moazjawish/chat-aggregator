<?php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AIModelController extends Controller
{
    /**
     * Get models available for the user's current subscription plan.
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

            /*
             * Get the user's current subscription.
             *
             * We use the subscription named "default"
             * so the same subscription selection strategy
             * is used throughout the application.
             */
            $subscription = $user->subscription('default');

            if (! $subscription) {
                return response()->json([
                    'message' =>
                        'You do not have an active subscription.',
                    'models' => [],
                ], 403);
            }

            /*
             * Do not allow access if the subscription
             * is no longer valid.
             */
            if (! $subscription->valid()) {
                return response()->json([
                    'message' =>
                        'Your subscription is not active.',
                    'models' => [],
                ], 403);
            }

            /*
             * Important:
             *
             * subscriptionPlan refers to:
             *
             * subscription_plan_id
             *
             * NOT pending_subscription_plan_id.
             *
             * Therefore a pending plan does not grant
             * access to its models before payment succeeds.
             */
            $plan = $subscription->subscriptionPlan;

            if (! $plan) {
                return response()->json([
                    'message' =>
                        'Subscription plan not found.',
                    'models' => [],
                ], 403);
            }

            /*
             * activeModels() checks:
             *
             * models.status = true
             * subscription_plan_model.status = true
             */
            $models = $plan
                ->activeModels()
                ->get()
                ->map(function ($model) {
                    return [
                        'id' =>
                            $model->id,

                        'name' =>
                            $model->name,

                        'provider' =>
                            $model->provider,

                        /*
                         * Prices configured for this model
                         * inside the current plan.
                         */
                        'input_price' =>
                            $model->pivot->input_price,

                        'output_price' =>
                            $model->pivot->output_price,

                        /*
                         * Limits for the entire
                         * subscription period.
                         */
                        'input_token_limit' =>
                            $model->pivot->input_token_limit,

                        'output_token_limit' =>
                            $model->pivot->output_token_limit,
                        'capabilities' => [
                            'document_input' =>
                                $model->hasCapability(
                                    'document_input'
                                ),

                            'image_input' =>
                                $model->hasCapability(
                                    'image_input'
                                ),
                        ],
                    ];
                })
                ->values();

            return response()->json([
                'subscription' => [
                    'id' =>
                        $subscription->id,

                    'status' =>
                        $subscription->stripe_status,

                    'plan' => [
                        'id' =>
                            $plan->id,

                        'name' =>
                            $plan->name,
                    ],

                    'plan_change_pending' =>
                        $subscription
                            ->pending_subscription_plan_id !== null,
                ],

                'models' => $models,
            ], 200);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' =>
                    'Unable to retrieve available models.',
            ], 500);
        }
    }
}
