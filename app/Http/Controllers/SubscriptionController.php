<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeSubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Throwable;

class SubscriptionController extends Controller
{
    public function cancel(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
            $subscription = $user->subscription('default');
            if (! $subscription) {
                return response()->json([
                    'message' => 'You do not have an active subscription.',
                ], 404);
            }

            if ($subscription->onGracePeriod()) {
                return response()->json([
                    'message' => 'Subscription is already scheduled for cancellation.',
                    'ends_at' => $subscription->ends_at,
                ], 422);
            }
            $subscription->cancel();
            $subscription->refresh();

            return response()->json([
                'message' => 'Subscription cancellation scheduled successfully.',
                'subscription' => [
                    'id' => $subscription->id,
                    'stripe_id' => $subscription->stripe_id,
                    'status' => $subscription->stripe_status,
                    'ends_at' => $subscription->ends_at,
                ],
            ], 200);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' => 'Unable to cancel subscription.',
            ], 500);
        }
    }

    public function resume(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $subscription = $user->subscription('default');

            if (! $subscription) {
                return response()->json([
                    'message' => 'You do not have a subscription.',
                ], 404);
            }


            if (! $subscription->onGracePeriod()) {
                return response()->json([
                    'message' => 'Subscription is not scheduled for cancellation.',
                ], 422);
            }
            $subscription->resume();
            $subscription->refresh();
            return response()->json([
                'message' => 'Subscription resumed successfully.',
                'subscription' => [
                    'id' => $subscription->id,
                    'stripe_id' => $subscription->stripe_id,
                    'status' => $subscription->stripe_status,
                    'ends_at' => $subscription->ends_at,
                ],
            ], 200);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' => 'Unable to resume subscription.',
            ], 500);
        }
    }

    public function current(Request $request)
    {
        $user = $request->user();

        $subscription = $user->subscription('default');

        if (! $subscription) {
            return response()->json([
                'has_subscription' => false,
                'subscription' => null,
            ]);
        }

        return response()->json([
            'has_subscription' => true,
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->stripe_status,
                'stripe_id' => $subscription->stripe_id,
                'plan' => $subscription->subscriptionPlan
                    ? [
                        'id' => $subscription->subscriptionPlan->id,
                        'name' => $subscription->subscriptionPlan->name,
                    ]
                    : null,
                'stripe_price' => $subscription->stripe_price,
                'quantity' => $subscription->quantity,
                'trial_ends_at' => $subscription->trial_ends_at,
                'ends_at' => $subscription->ends_at,
                'on_grace_period' => $subscription->onGracePeriod(),
                'active' => $subscription->active(),
            ],
        ]);
    }


    public function payments(Request $request)
    {
        $user = $request->user();
        $payments = $user->payments()->latest('paid_at')->get();

        return response()->json([
            'payments' => $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'paid_at' => $payment->paid_at,
                    'stripe_invoice_id' => $payment->stripe_invoice_id,
                    'stripe_payment_intent_id' =>
                        $payment->stripe_payment_intent_id,
                ];
            }),
        ]);
    }

    public function changePlan(ChangeSubscriptionPlanRequest $request): JsonResponse
    {
        $user = $request->user();
        $newPlan = SubscriptionPlan::findOrFail(
            $request->subscription_plan_id
        );

        if (! $newPlan->status) {
            return response()->json([
                'message' => 'The selected subscription plan is not available.',
            ], 422);
        }

        if (! $newPlan->stripe_price_id) {
            return response()->json([
                'message' => 'The selected subscription plan is not configured with Stripe.',
            ], 422);
        }

        $subscription = $user->subscription('default');
        if (! $subscription) {
            return response()->json([
                'message' => 'You do not have an active subscription.',
            ], 404);
        }

        if (! $subscription->active()) {
            return response()->json([
                'message' => 'Your subscription is not active.',
                'status' => $subscription->stripe_status,
            ], 422);
        }

        if (
            (int) $subscription->subscription_plan_id === (int) $newPlan->id) {
            return response()->json([
                'message' => 'You are already subscribed to this plan.',
            ], 422);
        }

        $oldPlanId = $subscription->subscription_plan_id;
        $oldPlan = $oldPlanId? SubscriptionPlan::find($oldPlanId): null;

        try {
            $subscription->swapAndInvoice($newPlan->stripe_price_id);

            Log::info('Subscription plan change initiated successfully.', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->stripe_id,
                'old_plan_id' => $oldPlanId,
                'old_plan_name' => $oldPlan?->name,
                'new_plan_id' => $newPlan->id,
                'new_plan_name' => $newPlan->name,
                'new_stripe_price_id' => $newPlan->stripe_price_id,
            ]);

            return response()->json([
                'message' => 'Subscription plan changed successfully.',
                'subscription' => [
                    'id' => $subscription->id,
                    'stripe_id' => $subscription->stripe_id,
                    'old_plan' => [
                        'id' => $oldPlan?->id,
                        'name' => $oldPlan?->name,
                    ],
                    'new_plan' => [
                        'id' => $newPlan->id,
                        'name' => $newPlan->name,
                    ],
                    'stripe_price_id' => $newPlan->stripe_price_id,
                    'status' => $subscription->stripe_status,
                ],
            ], 200);

        } catch (IncompletePayment $e) {

            Log::warning(
                'Subscription plan change requires payment confirmation.',
                [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'stripe_subscription_id' => $subscription->stripe_id,
                    'new_plan_id' => $newPlan->id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'message' => 'Additional payment confirmation is required.',
                'payment_required' => true,
                'subscription_id' => $subscription->id,
                'payment_intent' => $subscription->latestPayment()?->id,
            ], 402);

        } catch (Throwable $e) {

            Log::error(
                'Failed to change subscription plan.',
                [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'stripe_subscription_id' => $subscription->stripe_id,
                    'old_plan_id' => $oldPlanId,
                    'new_plan_id' => $newPlan->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
            return response()->json([
                'message' => 'Unable to change subscription plan.',
            ], 500);
        }
    }
}
