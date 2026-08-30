<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscriptionCheckoutRequest;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Throwable;

class SubscriptionCheckoutController extends Controller
{
    public function store(SubscriptionCheckoutRequest $request): JsonResponse {

        try {
            $user = $request->user();
            $plan = SubscriptionPlan::query()
                ->where('id', $request->validated('subscription_plan_id'))
                ->where('status', true)
                ->first();

            if (!$plan) {
                return response()->json([
                    'message' => 'Subscription plan not found or inactive.',
                ], 404);
            }

            if (!$plan->stripe_price_id) {
                return response()->json([
                    'message' => 'This subscription plan is not configured with Stripe.',
                ], 422);
            }

            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            $checkout = $user
                ->newSubscription(
                    'default',
                    $plan->stripe_price_id
                )
                ->checkout([
                    'success_url' =>
                        config('app.frontend_url')
                        . '/subscription/success'
                        . '?session_id={CHECKOUT_SESSION_ID}',

                    'cancel_url' =>
                        config('app.frontend_url')
                        . '/subscription/cancel',

                    'subscription_data' => [
                        'metadata' => [
                            'subscription_plan_id' =>
                                (string) $plan->id,
                        ],
                    ],
                ]);

            return response()->json([
                'message' => 'Checkout session created successfully.',
                'checkout_url' => $checkout->url,
            ], 200);

        } catch (IncompletePayment $e) {

            return response()->json([
                'message' => 'Payment requires additional action.',
                'payment' => $e->payment->id,
            ], 402);

        } catch (Throwable $e) {

            return response()->json([
                'message' => 'Unable to create checkout session.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
