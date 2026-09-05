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

            $subscription = $user->subscriptions()
                ->where('stripe_status', 'active')
                ->latest()
                ->first();

            if (! $subscription) {
                return response()->json([
                    'message' =>
                        'You do not have an active subscription.',
                ], 404);
            }

            if ($subscription->onGracePeriod()) {
                return response()->json([
                    'message' =>
                        'Subscription is already scheduled for cancellation.',

                    'ends_at' =>
                        $subscription->ends_at,
                ], 422);
            }

            /*
            * Cashier sends the cancellation request to Stripe.
            */
            $subscription->cancel();

            /*
            * Refresh the local subscription after Stripe operation.
            */
            $subscription->refresh();

            return response()->json([
                'message' =>
                    'Subscription cancellation scheduled successfully.',

                'subscription' => [
                    'id' => $subscription->id,

                    'status' =>
                        $subscription->stripe_status,

                    'ends_at' =>
                        $subscription->ends_at,

                    'on_grace_period' =>
                        $subscription->onGracePeriod(),
                ],
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' =>
                    'Unable to cancel subscription.',
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

            $subscription = $user->subscriptions()
                ->where('stripe_status', 'active')
                ->latest()
                ->first();

            if (! $subscription) {
                return response()->json([
                    'message' =>
                        'You do not have an active subscription.',
                ], 404);
            }

            if (! $subscription->onGracePeriod()) {
                return response()->json([
                    'message' =>
                        'Subscription is not scheduled for cancellation.',
                ], 422);
            }

            /*
            * Remove the cancellation from Stripe.
            */
            $subscription->resume();

            $subscription->refresh();

            return response()->json([
                'message' =>
                    'Subscription resumed successfully.',

                'subscription' => [
                    'id' => $subscription->id,

                    'status' =>
                        $subscription->stripe_status,

                    'ends_at' =>
                        $subscription->ends_at,

                    'on_grace_period' =>
                        $subscription->onGracePeriod(),
                ],
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' =>
                    'Unable to resume subscription.',
            ], 500);
        }
    }


    public function current(Request $request)
    {
        $user = $request->user();

        $subscription = $user->subscriptions()
            ->where('stripe_status', 'active')
            ->latest()
            ->first();

        if (! $subscription) {
            return response()->json([
                'message' => 'You do not have an active subscription.',
            ], 404);
        }

        /*
        * تحميل الخطة الحالية والخطة المعلقة.
        */
        $subscription->load([
            'subscriptionPlan',
            'pendingSubscriptionPlan',
        ]);

        return response()->json([
            'subscription' => [
                'id' => $subscription->id,

                'stripe_subscription_id' =>
                    $subscription->stripe_id,

                'stripe_status' =>
                    $subscription->stripe_status,

                'stripe_price' =>
                    $subscription->stripe_price,

                /*
                * الخطة الفعالة حالياً.
                */
                'current_plan' => $subscription->subscriptionPlan
                    ? [
                        'id' =>
                            $subscription->subscriptionPlan->id,

                        'name' =>
                            $subscription->subscriptionPlan->name,

                        'description' =>
                            $subscription->subscriptionPlan->description,

                        'price' =>
                            $subscription->subscriptionPlan->price,

                        'billing_interval' =>
                            $subscription->subscriptionPlan->billing_interval
                            ?? null,

                        'status' =>
                            $subscription->subscriptionPlan->status,
                    ]
                    : null,

                /*
                * الخطة التي يحاول المستخدم
                * الانتقال إليها.
                *
                * ستكون null في الوضع الطبيعي.
                */
                'pending_plan' =>
                    $subscription->pendingSubscriptionPlan
                        ? [
                            'id' =>
                                $subscription
                                    ->pendingSubscriptionPlan
                                    ->id,

                            'name' =>
                                $subscription
                                    ->pendingSubscriptionPlan
                                    ->name,

                            'description' =>
                                $subscription
                                    ->pendingSubscriptionPlan
                                    ->description,

                            'price' =>
                                $subscription
                                    ->pendingSubscriptionPlan
                                    ->price,

                            'billing_interval' =>
                                $subscription
                                    ->pendingSubscriptionPlan
                                    ->billing_interval
                                ?? null,

                            'status' =>
                                $subscription
                                    ->pendingSubscriptionPlan
                                    ->status,
                        ]
                        : null,

                /*
                * هل يوجد تغيير خطة قيد المعالجة؟
                */
                'plan_change_pending' =>
                    $subscription
                        ->pending_subscription_plan_id !== null,

                /*
                * معلومات Cashier.
                */
                'trial_ends_at' =>
                    $subscription->trial_ends_at,

                'ends_at' =>
                    $subscription->ends_at,

                'on_grace_period' =>
                    $subscription->onGracePeriod(),

                'active' =>
                    $subscription->active(),

                'cancelled' =>
                    $subscription->canceled(),
            ],
        ]);
    }


    public function payments(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $payments = $user->payments()
                ->with('subscription')
                ->latest()
                ->get();

            return response()->json([
                'payments' => $payments->map(
                    function ($payment) {
                        return [
                            'id' => $payment->id,

                            'subscription_id' =>
                                $payment->subscription_id,

                            'amount' =>
                                $payment->amount,

                            'currency' =>
                                $payment->currency,

                            'status' =>
                                $payment->status,

                            'stripe_payment_intent_id' =>
                                $payment->stripe_payment_intent_id,

                            'stripe_checkout_session_id' =>
                                $payment->stripe_checkout_session_id,

                            'stripe_invoice_id' =>
                                $payment->stripe_invoice_id,

                            'created_at' =>
                                $payment->created_at,
                        ];
                    }
                ),
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' =>
                    'Unable to retrieve payment history.',
            ], 500);
        }
    }

    public function changePlan(ChangeSubscriptionPlanRequest $request)
    {
        $user = $request->user();

        $newPlan = SubscriptionPlan::findOrFail(
            $request->subscription_plan_id
        );

        if (! $newPlan->status) {
            return response()->json([
                'message' => 'This subscription plan is not active.',
            ], 422);
        }

        if (! $newPlan->stripe_price_id) {
            return response()->json([
                'message' => 'This subscription plan has no Stripe price.',
            ], 422);
        }

        $subscription = $user->subscription('default');

        if (! $subscription) {
            return response()->json([
                'message' => 'Subscription not found.',
            ], 404);
        }

        if (! $subscription->active()) {
            return response()->json([
                'message' => 'Subscription is not active.',
            ], 422);
        }

        /*
        * المستخدم موجود بالفعل على نفس الخطة.
        */
        if (
            (int) $subscription->subscription_plan_id ===
            (int) $newPlan->id
        ) {
            return response()->json([
                'message' => 'You are already subscribed to this plan.',
            ], 422);
        }

        /*
        * لا نسمح ببدء تغيير آخر
        * قبل انتهاء التغيير السابق.
        */
        if ($subscription->pending_subscription_plan_id) {
            return response()->json([
                'message' => 'There is already a pending plan change.',
            ], 409);
        }

        $oldPlan = $subscription->subscriptionPlan;

        try {
            /*
            * ------------------------------------------------
            * Step 1
            * ------------------------------------------------
            *
            * نحفظ الخطة المطلوبة كـ pending.
            *
            * لا نغير subscription_plan_id.
            */
            $subscription->update([
                'pending_subscription_plan_id' =>
                    $newPlan->id,
            ]);

            /*
            * ------------------------------------------------
            * Step 2
            * ------------------------------------------------
            *
            * نطلب من Stripe:
            *
            * 1. تغيير الـ Price
            * 2. حساب الـ proration
            * 3. إنشاء Invoice
            * 4. محاولة الدفع فوراً
            */
            $subscription->swapAndInvoice(
                $newPlan->stripe_price_id
            );

            /*
            * مهم:
            *
            * حتى لو عاد swapAndInvoice بنجاح،
            * لا نفعّل الخطة هنا.
            *
            * تفعيل الخطة مسؤولية:
            *
            * invoice.payment_succeeded
            *
            * وذلك حتى تكون Stripe Webhooks
            * هي مصدر الحقيقة للدفع.
            */

            Log::info(
                'Subscription plan change initiated.',
                [
                    'subscription_id' =>
                        $subscription->id,

                    'user_id' =>
                        $user->id,

                    'current_plan_id' =>
                        $oldPlan?->id,

                    'pending_plan_id' =>
                        $newPlan->id,

                    'new_stripe_price_id' =>
                        $newPlan->stripe_price_id,
                ]
            );

            /*
            * Refresh حتى نحصل على آخر قيم
            * subscription الموجودة محلياً.
            */
            $subscription->refresh();

            return response()->json([
                'message' =>
                    'Plan change is being processed.',

                'subscription' => [
                    'id' =>
                        $subscription->id,

                    'current_plan' => [
                        'id' =>
                            $oldPlan?->id,

                        'name' =>
                            $oldPlan?->name,
                    ],

                    'pending_plan' => [
                        'id' =>
                            $newPlan->id,

                        'name' =>
                            $newPlan->name,
                    ],

                    'plan_change_pending' =>
                        $subscription
                            ->pending_subscription_plan_id !== null,
                ],
            ], 200);

        } catch (IncompletePayment $e) {

            /*
            * ------------------------------------------------
            * Payment requires user action
            * ------------------------------------------------
            *
            * مثال:
            *
            * 3D Secure
            * أو authentication إضافية.
            *
            * مهم جداً:
            *
            * لا نمسح pending_subscription_plan_id.
            *
            * لأن عملية تغيير الخطة ما زالت
            * قيد التنفيذ.
            */

            Log::warning(
                'Subscription plan change requires payment action.',
                [
                    'subscription_id' =>
                        $subscription->id,

                    'user_id' =>
                        $user->id,

                    'current_plan_id' =>
                        $subscription
                            ->subscription_plan_id,

                    'pending_plan_id' =>
                        $newPlan->id,

                    'payment_id' =>
                        $e->payment->id ?? null,
                ]
            );

            return response()->json([
                'message' =>
                    'Payment action is required.',

                'payment_required' =>
                    true,

                'subscription_id' =>
                    $subscription->id,

                'current_subscription_plan_id' =>
                    $subscription
                        ->subscription_plan_id,

                'pending_subscription_plan_id' =>
                    $newPlan->id,

                /*
                * Cashier IncompletePayment
                * يحتوي على Payment المرتبط بالمشكلة.
                */
                'payment_id' =>
                    $e->payment->id ?? null,
            ], 402);

        } catch (Throwable $e) {

            /*
            * ------------------------------------------------
            * Unexpected error
            * ------------------------------------------------
            *
            * في حال فشل بدء العملية نفسها،
            * نمسح الـ pending لأننا لا نريد
            * إبقاء المستخدم عالقاً.
            */

            $subscription->update([
                'pending_subscription_plan_id' =>
                    null,
            ]);

            Log::error(
                'Failed to initiate subscription plan change.',
                [
                    'subscription_id' =>
                        $subscription->id,

                    'user_id' =>
                        $user->id,

                    'current_plan_id' =>
                        $subscription
                            ->subscription_plan_id,

                    'requested_plan_id' =>
                        $newPlan->id,

                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );

            return response()->json([
                'message' =>
                    'Failed to change subscription plan.',
            ], 500);
        }
    }



}

