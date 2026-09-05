<?php
namespace App\Http\Controllers;

use App\Services\AI\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class UsageController extends Controller
{
    public function __construct(
        private UsageService $usageService
    ) {
    }

    /**
     * Get usage for all models available
     * in the current subscription plan.
     */
    public function index(
        Request $request
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
            | Current subscription
            |--------------------------------------------------------------------------
            */

            $subscription =
                $user->subscription('default');

            if (! $subscription) {
                return response()->json([
                    'message' =>
                        'No subscription found.',
                ], 404);
            }

            if (! $subscription->valid()) {
                return response()->json([
                    'message' =>
                        'Your subscription is not active.',
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | Current plan
            |--------------------------------------------------------------------------
            */

            $plan =
                $subscription
                    ->subscriptionPlan;

            if (! $plan) {
                return response()->json([
                    'message' =>
                        'Subscription plan not found.',
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | Models available in current plan
            |--------------------------------------------------------------------------
            */

            $models =
                $plan
                    ->activeModels()
                    ->get();

            /*
            |--------------------------------------------------------------------------
            | Usage
            |--------------------------------------------------------------------------
            */

            $usage =
                $models
                    ->map(
                        function ($model) use (
                            $subscription
                        ) {

                            $modelUsage =
                                $this
                                    ->usageService
                                    ->getUsage(
                                        subscription:
                                            $subscription,

                                        model:
                                            $model
                                    );

                            $canUse =
                                $this
                                    ->usageService
                                    ->canUse(
                                        subscription:
                                            $subscription,

                                        model:
                                            $model
                                    );

                            return [
                                'model' => [
                                    'id' =>
                                        $model->id,

                                    'name' =>
                                        $model->name,

                                    'provider' =>
                                        $model->provider,
                                ],

                                'can_use' =>
                                    $canUse,

                                'input' =>
                                    $modelUsage[
                                        'input'
                                    ],

                                'output' =>
                                    $modelUsage[
                                        'output'
                                    ],
                            ];
                        }
                    )
                    ->values();

            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'subscription' => [
                    'id' =>
                        $subscription->id,

                    'plan' => [
                        'id' =>
                            $plan->id,

                        'name' =>
                            $plan->name,
                    ],

                    'current_period_start' =>
                        $subscription
                            ->current_period_start,

                    'current_period_end' =>
                        $subscription
                            ->current_period_end,
                ],

                'models' =>
                    $usage,

            ], 200);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' =>
                    'Unable to retrieve usage.',
            ], 500);
        }
    }
}
