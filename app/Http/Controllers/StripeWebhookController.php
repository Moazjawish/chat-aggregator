<?php
namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Throwable;

class StripeWebhookController extends CashierWebhookController
{
    /**
     * Handle subscription creation.
     */
    public function handleCustomerSubscriptionCreated(array $payload)
    {
        /*
        |--------------------------------------------------------------------------
        | Let Cashier process subscription first
        |--------------------------------------------------------------------------
        */

        $response =
            parent::handleCustomerSubscriptionCreated(
                $payload
            );

        try {

            $stripeSubscription =
                $payload['data']['object'];

            $stripeSubscriptionId =
                $stripeSubscription['id']
                ?? null;

            if (! $stripeSubscriptionId) {

                Log::warning(
                    'customer.subscription.created has no subscription ID.',
                    [
                        'event_id' =>
                            $payload['id']
                            ?? null,
                    ]
                );

                return $response;
            }

            /*
            |--------------------------------------------------------------------------
            | Find local subscription created by Cashier
            |--------------------------------------------------------------------------
            */

            $subscription =
                Subscription::query()
                    ->where(
                        'stripe_id',
                        $stripeSubscriptionId
                    )
                    ->first();

            if (! $subscription) {

                Log::warning(
                    'Subscription not found after Cashier processing.',
                    [
                        'stripe_subscription_id' =>
                            $stripeSubscriptionId,
                    ]
                );

                return $response;
            }

            /*
            |--------------------------------------------------------------------------
            | Link local subscription plan
            |--------------------------------------------------------------------------
            */

            $subscriptionPlanId =
                $stripeSubscription['metadata']
                    ['subscription_plan_id']
                ?? null;

            if ($subscriptionPlanId) {

                $subscription->update([
                    'subscription_plan_id' =>
                        (int) $subscriptionPlanId,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Synchronize billing period
            |--------------------------------------------------------------------------
            */

            $this->updateSubscriptionBillingPeriod(
                subscription: $subscription,
                stripeSubscription: $stripeSubscription
            );

            $subscription->refresh();

            Log::info(
                'Subscription created and linked to plan.',
                [
                    'subscription_id' =>
                        $subscription->id,

                    'stripe_subscription_id' =>
                        $subscription->stripe_id,

                    'subscription_plan_id' =>
                        $subscription
                            ->subscription_plan_id,

                    'current_period_start' =>
                        $subscription
                            ->current_period_start
                            ?->toDateTimeString(),

                    'current_period_end' =>
                        $subscription
                            ->current_period_end
                            ?->toDateTimeString(),
                ]
            );

        } catch (Throwable $e) {

            Log::error(
                'Failed to process subscription.created.',
                [
                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),

                    'stripe_subscription_id' =>
                        $payload['data']['object']['id']
                        ?? null,
                ]
            );
        }

        return $response;
    }

    /**
     * Handle subscription updates.
     *
     * Important:
     * We allow Cashier to sync Stripe fields,
     * but we DO NOT activate pending local plan here.
     */
    protected function handleCustomerSubscriptionUpdated(
        array $payload
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Let Cashier sync its own fields first
        |--------------------------------------------------------------------------
        */

        parent::handleCustomerSubscriptionUpdated(
            $payload
        );

        try {

            $stripeSubscription =
                $payload['data']['object'];

            $stripeSubscriptionId =
                $stripeSubscription['id']
                ?? null;

            if (! $stripeSubscriptionId) {

                Log::warning(
                    'customer.subscription.updated has no subscription ID.',
                    [
                        'event_id' =>
                            $payload['id']
                            ?? null,
                    ]
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Stripe price
            |--------------------------------------------------------------------------
            |
            | Used here for logging only.
            |
            */

            $stripePriceId =
                $stripeSubscription['items']
                    ['data'][0]
                    ['price']['id']
                ?? null;

            /*
            |--------------------------------------------------------------------------
            | Find local subscription
            |--------------------------------------------------------------------------
            */

            $subscription =
                Subscription::query()
                    ->where(
                        'stripe_id',
                        $stripeSubscriptionId
                    )
                    ->first();

            if (! $subscription) {

                Log::warning(
                    'Local subscription not found after subscription update.',
                    [
                        'stripe_subscription_id' =>
                            $stripeSubscriptionId,

                        'stripe_price_id' =>
                            $stripePriceId,
                    ]
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Synchronize billing period
            |--------------------------------------------------------------------------
            |
            | This is important for renewals.
            |
            | When Stripe moves the subscription into a new billing cycle,
            | these values change.
            |
            */

            $this->updateSubscriptionBillingPeriod(
                subscription: $subscription,
                stripeSubscription: $stripeSubscription
            );

            $subscription->refresh();

            /*
            |--------------------------------------------------------------------------
            | DO NOT activate plan here
            |--------------------------------------------------------------------------
            |
            | customer.subscription.updated does not guarantee
            | successful payment.
            |
            | pending_subscription_plan_id stays pending until:
            |
            | invoice.payment_succeeded
            |
            */

            Log::info(
                'Stripe subscription updated without activating local plan.',
                [
                    'subscription_id' =>
                        $subscription->id,

                    'stripe_subscription_id' =>
                        $stripeSubscriptionId,

                    'stripe_status' =>
                        $stripeSubscription['status']
                        ?? null,

                    'stripe_price_id' =>
                        $stripePriceId,

                    'current_subscription_plan_id' =>
                        $subscription
                            ->subscription_plan_id,

                    'pending_subscription_plan_id' =>
                        $subscription
                            ->pending_subscription_plan_id,

                    'current_period_start' =>
                        $subscription
                            ->current_period_start
                            ?->toDateTimeString(),

                    'current_period_end' =>
                        $subscription
                            ->current_period_end
                            ?->toDateTimeString(),
                ]
            );

        } catch (Throwable $e) {

            Log::error(
                'Failed to process customer.subscription.updated.',
                [
                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),

                    'stripe_subscription_id' =>
                        $payload['data']['object']['id']
                        ?? null,
                ]
            );
        }
    }

    /**
     * Handle deleted/canceled subscription.
     */
    public function handleCustomerSubscriptionDeleted(
        array $payload
    ) {

        $response =
            parent::handleCustomerSubscriptionDeleted(
                $payload
            );

        try {

            $stripeSubscription =
                $payload['data']['object'];

            $stripeSubscriptionId =
                $stripeSubscription['id']
                ?? null;

            if (! $stripeSubscriptionId) {
                return $response;
            }

            $subscription =
                Subscription::query()
                    ->where(
                        'stripe_id',
                        $stripeSubscriptionId
                    )
                    ->first();

            if (! $subscription) {

                Log::warning(
                    'Subscription not found after subscription.deleted.',
                    [
                        'stripe_subscription_id' =>
                            $stripeSubscriptionId,
                    ]
                );

                return $response;
            }

            Log::info(
                'Subscription deleted/canceled.',
                [
                    'subscription_id' =>
                        $subscription->id,

                    'stripe_subscription_id' =>
                        $subscription->stripe_id,

                    'stripe_status' =>
                        $subscription->stripe_status,

                    'ends_at' =>
                        $subscription->ends_at,
                ]
            );

        } catch (Throwable $e) {

            Log::error(
                'Failed to process subscription.deleted.',
                [
                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),

                    'stripe_subscription_id' =>
                        $payload['data']['object']['id']
                        ?? null,
                ]
            );
        }

        return $response;
    }

    /**
     * Handle successful invoice payment.
     */
    public function handleInvoicePaymentSucceeded(
        array $payload
    ) {

        Log::info(
            'invoice.payment_succeeded received.',
            [
                'event_id' =>
                    $payload['id']
                    ?? null,

                'invoice_id' =>
                    $payload['data']['object']['id']
                    ?? null,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Let Cashier handle invoice first
        |--------------------------------------------------------------------------
        */

        $response =
            parent::handleInvoicePaymentSucceeded(
                $payload
            );

        try {

            $invoice =
                $payload['data']['object'];

            /*
            |--------------------------------------------------------------------------
            | Resolve Stripe subscription ID
            |--------------------------------------------------------------------------
            */

            $stripeSubscriptionId =
                $invoice['parent']
                    ['subscription_details']
                    ['subscription']
                ?? $invoice['subscription']
                ?? null;

            if (! $stripeSubscriptionId) {

                Log::warning(
                    'Successful invoice has no subscription.',
                    [
                        'invoice_id' =>
                            $invoice['id']
                            ?? null,
                    ]
                );

                return $response;
            }

            /*
            |--------------------------------------------------------------------------
            | Find local subscription
            |--------------------------------------------------------------------------
            */

            $subscription =
                Subscription::query()
                    ->where(
                        'stripe_id',
                        $stripeSubscriptionId
                    )
                    ->first();

            if (! $subscription) {

                Log::warning(
                    'Subscription not found for successful payment.',
                    [
                        'stripe_subscription_id' =>
                            $stripeSubscriptionId,

                        'stripe_invoice_id' =>
                            $invoice['id']
                            ?? null,
                    ]
                );

                return $response;
            }

            /*
            |--------------------------------------------------------------------------
            | Get latest Stripe subscription
            |--------------------------------------------------------------------------
            |
            | We retrieve it directly from Stripe instead of relying only
            | on invoice payload.
            |
            */

            $stripeSubscriptionObject =
                $subscription
                    ->asStripeSubscription();

            $stripeSubscription =
                $stripeSubscriptionObject
                    ->toArray();

            /*
            |--------------------------------------------------------------------------
            | Synchronize billing period
            |--------------------------------------------------------------------------
            */

            $this->updateSubscriptionBillingPeriod(
                subscription: $subscription,
                stripeSubscription: $stripeSubscription
            );

            /*
            |--------------------------------------------------------------------------
            | Get current Stripe Price
            |--------------------------------------------------------------------------
            */

            $currentStripePriceId =
                $stripeSubscription['items']
                    ['data'][0]
                    ['price']['id']
                ?? null;

            /*
            |--------------------------------------------------------------------------
            | Extract Payment Intent ID
            |--------------------------------------------------------------------------
            */

            $paymentIntentId = null;

            if (
                is_string(
                    $invoice['payment_intent']
                    ?? null
                )
            ) {

                $paymentIntentId =
                    $invoice['payment_intent'];

            } elseif (
                is_array(
                    $invoice['payment_intent']
                    ?? null
                )
            ) {

                $paymentIntentId =
                    $invoice['payment_intent']['id']
                    ?? null;
            }

            /*
            |--------------------------------------------------------------------------
            | Record payment
            |--------------------------------------------------------------------------
            */

            $amountPaid =
                ((int) (
                    $invoice['amount_paid']
                    ?? 0
                )) / 100;

            $currency =
                strtolower(
                    $invoice['currency']
                    ?? 'usd'
                );

            $payment =
                Payment::query()
                    ->updateOrCreate(
                        [
                            'stripe_invoice_id' =>
                                $invoice['id'],
                        ],
                        [
                            'user_id' =>
                                $subscription->user_id,

                            'subscription_id' =>
                                $subscription->id,

                            'stripe_payment_intent_id' =>
                                $paymentIntentId,

                            'amount' =>
                                $amountPaid,

                            'currency' =>
                                $currency,

                            'status' =>
                                'paid',

                            'paid_at' =>
                                now(),
                        ]
                    );

            Log::info(
                'Payment recorded successfully.',
                [
                    'payment_id' =>
                        $payment->id,

                    'subscription_id' =>
                        $subscription->id,

                    'stripe_invoice_id' =>
                        $invoice['id']
                        ?? null,

                    'stripe_payment_intent_id' =>
                        $paymentIntentId,

                    'amount' =>
                        $amountPaid,

                    'currency' =>
                        $currency,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Activate pending plan
            |--------------------------------------------------------------------------
            */

            if (
                $subscription
                    ->pending_subscription_plan_id
            ) {

                $oldPlanId =
                    $subscription
                        ->subscription_plan_id;

                $newPlanId =
                    $subscription
                        ->pending_subscription_plan_id;

                /*
                |--------------------------------------------------------------------------
                | Find pending plan
                |--------------------------------------------------------------------------
                */

                $newPlan =
                    SubscriptionPlan::query()
                        ->where(
                            'id',
                            $newPlanId
                        )
                        ->where(
                            'status',
                            true
                        )
                        ->first();

                if (! $newPlan) {

                    Log::error(
                        'Pending subscription plan not found or inactive.',
                        [
                            'subscription_id' =>
                                $subscription->id,

                            'pending_subscription_plan_id' =>
                                $newPlanId,

                            'stripe_invoice_id' =>
                                $invoice['id']
                                ?? null,
                        ]
                    );

                    return $response;
                }

                /*
                |--------------------------------------------------------------------------
                | Verify Stripe price
                |--------------------------------------------------------------------------
                |
                | Payment succeeded alone is not enough.
                |
                | We also verify that Stripe subscription now points
                | to the expected price of the pending plan.
                |
                */

                if (
                    ! $currentStripePriceId
                    ||
                    $currentStripePriceId
                    !==
                    $newPlan->stripe_price_id
                ) {

                    Log::warning(
                        'Stripe price does not match pending plan.',
                        [
                            'subscription_id' =>
                                $subscription->id,

                            'pending_subscription_plan_id' =>
                                $newPlan->id,

                            'expected_stripe_price_id' =>
                                $newPlan
                                    ->stripe_price_id,

                            'current_stripe_price_id' =>
                                $currentStripePriceId,

                            'stripe_invoice_id' =>
                                $invoice['id']
                                ?? null,
                        ]
                    );

                    return $response;
                }

                /*
                |--------------------------------------------------------------------------
                | Activate new local plan
                |--------------------------------------------------------------------------
                */

                $subscription->update([
                    'subscription_plan_id' =>
                        $newPlan->id,

                    'pending_subscription_plan_id' =>
                        null,
                ]);

                Log::info(
                    'Pending subscription plan activated successfully.',
                    [
                        'subscription_id' =>
                            $subscription->id,

                        'old_subscription_plan_id' =>
                            $oldPlanId,

                        'new_subscription_plan_id' =>
                            $newPlan->id,

                        'stripe_price_id' =>
                            $currentStripePriceId,

                        'stripe_invoice_id' =>
                            $invoice['id']
                            ?? null,
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Final log
            |--------------------------------------------------------------------------
            */

            $subscription->refresh();

            Log::info(
                'Successful invoice fully processed.',
                [
                    'subscription_id' =>
                        $subscription->id,

                    'subscription_plan_id' =>
                        $subscription
                            ->subscription_plan_id,

                    'pending_subscription_plan_id' =>
                        $subscription
                            ->pending_subscription_plan_id,

                    'current_period_start' =>
                        $subscription
                            ->current_period_start
                            ?->toDateTimeString(),

                    'current_period_end' =>
                        $subscription
                            ->current_period_end
                            ?->toDateTimeString(),
                ]
            );

        } catch (Throwable $e) {

            Log::error(
                'Failed to process successful invoice payment.',
                [
                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),

                    'invoice_id' =>
                        $payload['data']['object']['id']
                        ?? null,
                ]
            );
        }

        return $response;
    }

    /**
     * Handle failed invoice payment.
     */
    public function handleInvoicePaymentFailed(
        array $payload
    ) {

        try {

            $invoice =
                $payload['data']['object'];

            /*
            |--------------------------------------------------------------------------
            | Resolve Stripe subscription ID
            |--------------------------------------------------------------------------
            */

            $stripeSubscriptionId =
                $invoice['parent']
                    ['subscription_details']
                    ['subscription']
                ?? $invoice['subscription']
                ?? null;

            Log::warning(
                'invoice.payment_failed received.',
                [
                    'event_id' =>
                        $payload['id']
                        ?? null,

                    'invoice_id' =>
                        $invoice['id']
                        ?? null,

                    'subscription' =>
                        $stripeSubscriptionId,

                    'amount_due' =>
                        $invoice['amount_due']
                        ?? null,

                    'currency' =>
                        $invoice['currency']
                        ?? null,
                ]
            );

            if (! $stripeSubscriptionId) {

                Log::warning(
                    'Failed-payment invoice has no subscription.',
                    [
                        'invoice_id' =>
                            $invoice['id']
                            ?? null,
                    ]
                );

                return new Response(
                    'Webhook Handled',
                    200
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Find local subscription
            |--------------------------------------------------------------------------
            */

            $subscription =
                Subscription::query()
                    ->where(
                        'stripe_id',
                        $stripeSubscriptionId
                    )
                    ->first();

            if (! $subscription) {

                Log::warning(
                    'Subscription not found for failed payment.',
                    [
                        'stripe_subscription_id' =>
                            $stripeSubscriptionId,

                        'stripe_invoice_id' =>
                            $invoice['id']
                            ?? null,
                    ]
                );

                return new Response(
                    'Webhook Handled',
                    200
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Extract Payment Intent
            |--------------------------------------------------------------------------
            */

            $paymentIntentId = null;

            if (
                is_string(
                    $invoice['payment_intent']
                    ?? null
                )
            ) {

                $paymentIntentId =
                    $invoice['payment_intent'];

            } elseif (
                is_array(
                    $invoice['payment_intent']
                    ?? null
                )
            ) {

                $paymentIntentId =
                    $invoice['payment_intent']['id']
                    ?? null;
            }

            /*
            |--------------------------------------------------------------------------
            | Record failed payment
            |--------------------------------------------------------------------------
            */

            $payment =
                Payment::query()
                    ->updateOrCreate(
                        [
                            'stripe_invoice_id' =>
                                $invoice['id'],
                        ],
                        [
                            'user_id' =>
                                $subscription->user_id,

                            'subscription_id' =>
                                $subscription->id,

                            'stripe_payment_intent_id' =>
                                $paymentIntentId,

                            'amount' =>
                                ((int) (
                                    $invoice['amount_due']
                                    ?? 0
                                )) / 100,

                            'currency' =>
                                strtolower(
                                    $invoice['currency']
                                    ?? 'usd'
                                ),

                            'status' =>
                                'failed',

                            'paid_at' =>
                                null,
                        ]
                    );

            Log::warning(
                'Failed payment recorded.',
                [
                    'payment_id' =>
                        $payment->id,

                    'subscription_id' =>
                        $subscription->id,

                    'stripe_invoice_id' =>
                        $invoice['id']
                        ?? null,

                    'stripe_payment_intent_id' =>
                        $paymentIntentId,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Cancel pending local plan
            |--------------------------------------------------------------------------
            |
            | Current plan remains untouched.
            |
            */

            if (
                $subscription
                    ->pending_subscription_plan_id
            ) {

                $currentPlanId =
                    $subscription
                        ->subscription_plan_id;

                $pendingPlanId =
                    $subscription
                        ->pending_subscription_plan_id;

                $subscription->update([
                    'pending_subscription_plan_id' =>
                        null,
                ]);

                Log::warning(
                    'Pending subscription plan cancelled because payment failed.',
                    [
                        'subscription_id' =>
                            $subscription->id,

                        'current_subscription_plan_id' =>
                            $currentPlanId,

                        'cancelled_pending_plan_id' =>
                            $pendingPlanId,

                        'stripe_invoice_id' =>
                            $invoice['id']
                            ?? null,
                    ]
                );
            }

        } catch (Throwable $e) {

            Log::error(
                'Failed to process failed invoice payment.',
                [
                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),

                    'invoice_id' =>
                        $payload['data']['object']['id']
                        ?? null,
                ]
            );
        }

        return new Response(
            'Webhook Handled',
            200
        );
    }

    /**
     * Handle refunds.
     */
    public function handleChargeRefunded(
        array $payload
    ) {

        try {

            $charge =
                $payload['data']['object'];

            $paymentIntentId =
                $charge['payment_intent']
                ?? null;

            /*
            |--------------------------------------------------------------------------
            | Expanded PaymentIntent support
            |--------------------------------------------------------------------------
            */

            if (
                is_array(
                    $paymentIntentId
                )
            ) {
                $paymentIntentId =
                    $paymentIntentId['id']
                    ?? null;
            }

            Log::info(
                'charge.refunded received.',
                [
                    'charge_id' =>
                        $charge['id']
                        ?? null,

                    'payment_intent_id' =>
                        $paymentIntentId,

                    'amount' =>
                        $charge['amount']
                        ?? null,

                    'amount_refunded' =>
                        $charge['amount_refunded']
                        ?? null,

                    'status' =>
                        $charge['status']
                        ?? null,
                ]
            );

            if (! $paymentIntentId) {

                Log::warning(
                    'Refunded charge has no payment intent.',
                    [
                        'charge_id' =>
                            $charge['id']
                            ?? null,
                    ]
                );

                return response()->json([
                    'message' =>
                        'Payment intent not found.',
                ], 200);
            }

            /*
            |--------------------------------------------------------------------------
            | Find local payment
            |--------------------------------------------------------------------------
            */

            $payment =
                Payment::query()
                    ->where(
                        'stripe_payment_intent_id',
                        $paymentIntentId
                    )
                    ->first();

            Log::info(
                'Refund payment lookup.',
                [
                    'payment_intent_id' =>
                        $paymentIntentId,

                    'payment_found' =>
                        (bool) $payment,

                    'payment_id' =>
                        $payment?->id,
                ]
            );

            if (! $payment) {

                Log::warning(
                    'Local payment not found for refund.',
                    [
                        'payment_intent_id' =>
                            $paymentIntentId,

                        'charge_id' =>
                            $charge['id']
                            ?? null,
                    ]
                );

                return response()->json([
                    'message' =>
                        'Payment not found.',
                ], 200);
            }

            /*
            |--------------------------------------------------------------------------
            | Determine refund status
            |--------------------------------------------------------------------------
            */

            $originalAmount =
                (float) $payment->amount;

            $refundedAmount =
                ((int) (
                    $charge['amount_refunded']
                    ?? 0
                )) / 100;

            if (
                $refundedAmount
                >=
                $originalAmount
            ) {

                $payment->update([
                    'status' =>
                        'refunded',
                ]);

            } else {

                $payment->update([
                    'status' =>
                        'partially_refunded',
                ]);
            }

            Log::info(
                'Payment refund processed successfully.',
                [
                    'payment_id' =>
                        $payment->id,

                    'original_amount' =>
                        $originalAmount,

                    'refunded_amount' =>
                        $refundedAmount,

                    'status' =>
                        $payment
                            ->fresh()
                            ->status,
                ]
            );

        } catch (Throwable $e) {

            Log::error(
                'Failed to process refund.',
                [
                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );
        }

        return response()->json([
            'message' =>
                'Refund processed.',
        ]);
    }

    /**
     * Synchronize local billing period
     * with Stripe Subscription Item.
     *
     * Stripe Basil:
     *
     * subscription.items.data[0].current_period_start
     * subscription.items.data[0].current_period_end
     */
    private function updateSubscriptionBillingPeriod(
        Subscription $subscription,
        array $stripeSubscription
    ): void {

        /*
        |--------------------------------------------------------------------------
        | First subscription item
        |--------------------------------------------------------------------------
        |
        | Your current design uses one Stripe Price per subscription.
        |
        */

        $item =
            $stripeSubscription['items']['data'][0]
            ?? null;

        if (! $item) {

            Log::warning(
                'Stripe subscription has no subscription items.',
                [
                    'subscription_id' =>
                        $subscription->id,

                    'stripe_subscription_id' =>
                        $subscription->stripe_id,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Billing period
        |--------------------------------------------------------------------------
        */

        $periodStart =
            $item['current_period_start']
            ?? null;

        $periodEnd =
            $item['current_period_end']
            ?? null;

        if (
            ! $periodStart
            ||
            ! $periodEnd
        ) {

            Log::warning(
                'Stripe subscription item has no billing period.',
                [
                    'subscription_id' =>
                        $subscription->id,

                    'stripe_subscription_id' =>
                        $subscription->stripe_id,

                    'stripe_item_id' =>
                        $item['id']
                        ?? null,

                    'current_period_start' =>
                        $periodStart,

                    'current_period_end' =>
                        $periodEnd,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Save billing period locally
        |--------------------------------------------------------------------------
        */

        $subscription->update([
            'current_period_start' =>
                Carbon::createFromTimestamp(
                    (int) $periodStart
                ),

            'current_period_end' =>
                Carbon::createFromTimestamp(
                    (int) $periodEnd
                ),
        ]);

        Log::info(
            'Subscription billing period synchronized.',
            [
                'subscription_id' =>
                    $subscription->id,

                'stripe_subscription_id' =>
                    $subscription->stripe_id,

                'current_period_start' =>
                    $subscription
                        ->fresh()
                        ->current_period_start
                        ?->toDateTimeString(),

                'current_period_end' =>
                    $subscription
                        ->fresh()
                        ->current_period_end
                        ?->toDateTimeString(),
            ]
        );
    }

    /**
     * Keep Cashier default customer update behavior.
     */
    public function handleCustomerUpdated(
        array $payload
    ) {
        return parent::handleCustomerUpdated(
            $payload
        );
    }

    /**
     * Keep Cashier default customer deletion behavior.
     */
    public function handleCustomerDeleted(
        array $payload
    ) {
        return parent::handleCustomerDeleted(
            $payload
        );
    }
}
