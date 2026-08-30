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
     * Handle customer.subscription.created
     *
     * Cashier creates the local subscription.
     * We additionally attach our local SubscriptionPlan.
     */
    public function handleCustomerSubscriptionCreated(array $payload)
    {
        $response = parent::handleCustomerSubscriptionCreated($payload);

        try {
            $stripeSubscription = $payload['data']['object'];

            $subscription = Subscription::where(
                'stripe_id',
                $stripeSubscription['id']
            )->first();

            if (! $subscription) {
                Log::warning('Subscription not found after Cashier processing.', [
                    'stripe_subscription_id' => $stripeSubscription['id'],
                ]);

                return $response;
            }

            $subscriptionPlanId =
                $stripeSubscription['metadata']['subscription_plan_id'] ?? null;

            if ($subscriptionPlanId) {
                $subscription->update([
                    'subscription_plan_id' => (int) $subscriptionPlanId,
                ]);
            }

            Log::info('Subscription created and linked to plan.', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->stripe_id,
                'subscription_plan_id' => $subscription->subscription_plan_id,
            ]);

        } catch (Throwable $e) {
            Log::error('Failed to process subscription.created.', [
                'error' => $e->getMessage(),
                'stripe_subscription_id' =>
                    $payload['data']['object']['id'] ?? null,
            ]);
        }

        return $response;
    }

    public function handleCustomerSubscriptionUpdated(array $payload)
    {
        /*
        |--------------------------------------------------------------------------
        | Let Cashier update the standard subscription fields first
        |--------------------------------------------------------------------------
        */

        $response = parent::handleCustomerSubscriptionUpdated($payload);

        try {

            $stripeSubscription = $payload['data']['object'];

            /*
            |--------------------------------------------------------------------------
            | Get Stripe subscription ID
            |--------------------------------------------------------------------------
            */

            $stripeSubscriptionId = $stripeSubscription['id'] ?? null;

            if (! $stripeSubscriptionId) {

                Log::warning(
                    'Subscription updated webhook has no Stripe subscription ID.'
                );

                return $response;
            }

            /*
            |--------------------------------------------------------------------------
            | Find local subscription
            |--------------------------------------------------------------------------
            */

            $subscription = Subscription::where(
                'stripe_id',
                $stripeSubscriptionId
            )->first();

            if (! $subscription) {

                Log::warning(
                    'Local subscription not found for subscription.updated.',
                    [
                        'stripe_subscription_id' => $stripeSubscriptionId,
                    ]
                );

                return $response;
            }

            $stripePriceId =
                $stripeSubscription['items']['data'][0]['price']['id']
                ?? null;

            if (! $stripePriceId) {

                Log::warning(
                    'Subscription updated webhook has no Stripe Price ID.',
                    [
                        'stripe_subscription_id' => $stripeSubscriptionId,
                    ]
                );

                return $response;
            }


            $plan = SubscriptionPlan::where(
                'stripe_price_id',
                $stripePriceId
            )->first();

            if (! $plan) {

                Log::warning(
                    'Subscription plan not found for Stripe Price.',
                    [
                        'stripe_subscription_id' => $stripeSubscriptionId,

                        'stripe_price_id' => $stripePriceId,
                    ]
                );

                return $response;
            }

            $oldPlanId = $subscription->subscription_plan_id;
            $subscription->update([
                'subscription_plan_id' => $plan->id,

                'stripe_price' => $stripePriceId,
            ]);


            Log::info(
                'Subscription plan synchronized successfully.',
                [
                    'subscription_id' => $subscription->id,
                    'stripe_subscription_id' => $stripeSubscriptionId,
                    'old_plan_id' => $oldPlanId,
                    'new_plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'stripe_price_id' => $stripePriceId,
                ]
            );

            Log::info('customer.subscription.updated received', [
            'payload' => $payload,
            ]);
        } catch (Throwable $e) {

            Log::error(
                'Failed to synchronize subscription plan.',
                [
                    'error' => $e->getMessage(),

                    'file' => $e->getFile(),

                    'line' => $e->getLine(),
                ]
            );
        }

        return $response;
    }

    /**
     * Handle customer.subscription.deleted
     *
     * Cashier marks the subscription as canceled.
     */
    public function handleCustomerSubscriptionDeleted(array $payload)
    {
        $response = parent::handleCustomerSubscriptionDeleted($payload);

        try {
            $stripeSubscription = $payload['data']['object'];

            $subscription = Subscription::where(
                'stripe_id',
                $stripeSubscription['id']
            )->first();

            if (! $subscription) {
                Log::warning('Subscription not found after subscription.deleted.', [
                    'stripe_subscription_id' => $stripeSubscription['id'],
                ]);

                return $response;
            }

            Log::info('Subscription deleted/canceled.', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->stripe_id,
                'stripe_status' => $subscription->stripe_status,
                'ends_at' => $subscription->ends_at,
            ]);

        } catch (Throwable $e) {
            Log::error('Failed to process subscription.deleted.', [
                'error' => $e->getMessage(),
                'stripe_subscription_id' =>
                    $payload['data']['object']['id'] ?? null,
            ]);
        }

        return $response;
    }


    public function handleInvoicePaymentSucceeded(array $payload)
    {
        Log::info('invoice.payment_succeeded received.', [
            'event_id' => $payload['id'] ?? null,
            'invoice_id' => $payload['data']['object']['id'] ?? null,
        ]);

        $response = parent::handleInvoicePaymentSucceeded($payload);

        try {
            $invoice = $payload['data']['object'];

            /*
             * Cashier 16 / current Stripe API structure:
             *
             * invoice.parent.subscription_details.subscription
             *
             * We keep a fallback to the older "subscription"
             * property for compatibility.
             */
            $stripeSubscriptionId =
                $invoice['parent']['subscription_details']['subscription']
                ?? $invoice['subscription']
                ?? null;

            Log::info('Invoice data.', [
                'invoice_id' => $invoice['id'] ?? null,
                'subscription' => $stripeSubscriptionId,
                'customer' => $invoice['customer'] ?? null,
                'amount_paid' => $invoice['amount_paid'] ?? null,
                'currency' => $invoice['currency'] ?? null,
                'payment_intent' => $invoice['payment_intent'] ?? null,
            ]);

            if (! $stripeSubscriptionId) {
                Log::warning('Invoice has no subscription.', [
                    'invoice_id' => $invoice['id'] ?? null,
                ]);

                return $response;
            }

            $subscription = Subscription::where(
                'stripe_id',
                $stripeSubscriptionId
            )->first();

            if (! $subscription) {
                Log::warning(
                    'Subscription not found for successful payment.',
                    [
                        'stripe_subscription_id' => $stripeSubscriptionId,
                        'stripe_invoice_id' => $invoice['id'] ?? null,
                    ]
                );

                return $response;
            }

            /*
             * payment_intent can be either:
             *
             * "pi_xxx"
             *
             * or, depending on Stripe response expansion,
             * an object containing an "id".
             */
            $paymentIntentId = null;

            if (is_string($invoice['payment_intent'] ?? null)) {
                $paymentIntentId = $invoice['payment_intent'];
            } elseif (is_array($invoice['payment_intent'] ?? null)) {
                $paymentIntentId =
                    $invoice['payment_intent']['id'] ?? null;
            }

            /*
             * Checkout Session is not normally stored directly
             * inside the invoice object.
             *
             * Therefore we leave it null here unless another part
             * of the application has already associated it.
             */

            $amountPaid = ($invoice['amount_paid'] ?? 0) / 100;

            $currency = $invoice['currency'] ?? 'usd';

            $payment = Payment::updateOrCreate(
                [
                    'stripe_invoice_id' => $invoice['id'],
                ],
                [
                    'user_id' => $subscription->user_id,
                    'subscription_id' => $subscription->id,
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'amount' => $amountPaid,
                    'currency' => $currency,
                    'status' => 'paid',
                    'paid_at' => now(),
                ]
            );

            Log::info('Payment recorded successfully.', [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'stripe_invoice_id' => $invoice['id'],
                'stripe_payment_intent_id' => $paymentIntentId,
                'amount' => $amountPaid,
                'currency' => $currency,
            ]);

        } catch (Throwable $e) {
            Log::error('Failed to record successful payment.', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'invoice_id' =>
                    $payload['data']['object']['id'] ?? null,
            ]);
        }

        return $response;
    }


    public function handleInvoicePaymentFailed(array $payload)
    {
        try {
            $invoice = $payload['data']['object'];

            /*
             * Current Stripe structure.
             */
            $stripeSubscriptionId =
                $invoice['parent']['subscription_details']['subscription']
                ?? $invoice['subscription']
                ?? null;

            Log::warning('invoice.payment_failed received.', [
                'event_id' => $payload['id'] ?? null,
                'invoice_id' => $invoice['id'] ?? null,
                'subscription' => $stripeSubscriptionId,
                'amount_due' => $invoice['amount_due'] ?? null,
                'currency' => $invoice['currency'] ?? null,
            ]);

            if (! $stripeSubscriptionId) {
                Log::warning('Failed-payment invoice has no subscription.', [
                    'invoice_id' => $invoice['id'] ?? null,
                ]);

                return new Response('Webhook Handled', 200);
            }

            $subscription = Subscription::where(
                'stripe_id',
                $stripeSubscriptionId
            )->first();

            if (! $subscription) {
                Log::warning(
                    'Subscription not found for failed payment.',
                    [
                        'stripe_subscription_id' => $stripeSubscriptionId,
                        'stripe_invoice_id' => $invoice['id'] ?? null,
                    ]
                );

                return new Response('Webhook Handled', 200);
            }

            $payment = Payment::updateOrCreate(
                [
                    'stripe_invoice_id' => $invoice['id'],
                ],
                [
                    'user_id' => $subscription->user_id,
                    'subscription_id' => $subscription->id,
                    'amount' => ($invoice['amount_due'] ?? 0) / 100,
                    'currency' => $invoice['currency'] ?? 'usd',
                    'status' => 'failed',
                    'paid_at' => null,
                ]
            );

            Log::warning('Failed payment recorded.', [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'stripe_invoice_id' => $invoice['id'],
            ]);

        } catch (Throwable $e) {
            Log::error('Failed to record failed payment.', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'invoice_id' =>
                    $payload['data']['object']['id'] ?? null,
            ]);
        }

        return new Response('Webhook Handled', 200);
    }


    public function handleChargeRefunded(array $payload)
    {
        try {

            $charge = $payload['data']['object'];
            $paymentIntentId = $charge['payment_intent'] ?? null;
            Log::info('charge.refunded received', [
                'charge_id' => $charge['id'] ?? null,
                'payment_intent_id' => $paymentIntentId,
                'amount' => $charge['amount'] ?? null,
                'amount_refunded' => $charge['amount_refunded'] ?? null,
                'status' => $charge['status'] ?? null,
            ]);

            if (! $paymentIntentId) {
                Log::warning('Refunded charge has no payment intent.', [
                    'charge_id' => $charge['id'] ?? null,
                ]);

                return response()->json([
                    'message' => 'Payment intent not found.',
                ], 200);
            }

            $payment = Payment::where(
                'stripe_payment_intent_id',
                $paymentIntentId
            )->first();

            Log::info('Refund payment lookup', [
                'payment_intent_id' => $paymentIntentId,
                'payment_found' => (bool) $payment,
                'payment_id' => $payment?->id,
            ]);

            if (! $payment) {

                Log::warning('Local payment not found for refund.', [
                    'payment_intent_id' => $paymentIntentId,
                    'charge_id' => $charge['id'] ?? null,
                ]);

                return response()->json([
                    'message' => 'Payment not found.',
                ], 200);
            }

            $originalAmount = (float) $payment->amount;

            $refundedAmount =
                ($charge['amount_refunded'] ?? 0) / 100;

            if ($refundedAmount >= $originalAmount) {

                $payment->update([
                    'status' => 'refunded',
                ]);

            } else {

                $payment->update([
                    'status' => 'partially_refunded',
                ]);
            }

            Log::info('Payment refund processed successfully.', [
                'payment_id' => $payment->id,
                'original_amount' => $originalAmount,
                'refunded_amount' => $refundedAmount,
                'status' => $payment->fresh()->status,
            ]);

        } catch (Throwable $e) {

            Log::error('Failed to process refund.', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return response()->json([
            'message' => 'Refund processed.',
        ]);
    }


    public function handleCustomerUpdated(array $payload)
    {
        return parent::handleCustomerUpdated($payload);
    }


    /**
     * Handle customer.deleted
     *
     * Cashier cancels the customer's subscriptions
     * and clears Stripe billing fields.
     */
    public function handleCustomerDeleted(array $payload)
    {
        return parent::handleCustomerDeleted($payload);
    }
}
