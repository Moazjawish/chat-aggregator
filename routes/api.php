<?php

use App\Http\Controllers\AIModelController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\UsageController;
use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\FileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::post('/register', [
    UserAuthController::class,
    'register',
]);

Route::post('/login', [
    UserAuthController::class,
    'login',
]);

/*
|--------------------------------------------------------------------------
| Stripe Webhook
|--------------------------------------------------------------------------
|
| يجب أن يبقى خارج auth:sanctum لأن Stripe هو الذي يرسل الطلب.
|
*/

Route::post('/stripe/webhook', [
    StripeWebhookController::class,
    'handleWebhook',
]);

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [
        UserAuthController::class,
        'logout',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    */

    Route::post('/plans', [
        SubscriptionPlanController::class,
        'store',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Subscription
    |--------------------------------------------------------------------------
    */

    Route::get('/subscription/current', [
        SubscriptionController::class,
        'current',
    ]);

    Route::post('/subscription/checkout', [
        SubscriptionCheckoutController::class,
        'store',
    ]);

    Route::post('/subscription/change-plan', [
        SubscriptionController::class,
        'changePlan',
    ]);

    Route::post('/subscription/cancel', [
        SubscriptionController::class,
        'cancel',
    ]);

    Route::post('/subscription/resume', [
        SubscriptionController::class,
        'resume',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    Route::get('/payments', [
        SubscriptionController::class,
        'payments',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Usage
    |--------------------------------------------------------------------------
    |
    | نستخدم Route واحدة فقط.
    |
    */

    Route::get('/usage', [
        UsageController::class,
        'index',
    ]);

    /*
    |--------------------------------------------------------------------------
    | AI Models
    |--------------------------------------------------------------------------
    */

    Route::get('/models', [
        AIModelController::class,
        'index',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Conversations
    |--------------------------------------------------------------------------
    */

    Route::get('/conversations', [
        ConversationController::class,
        'index',
    ]);

    Route::post('/conversations', [
        ConversationController::class,
        'store',
    ]);

    Route::get('/conversations/{conversation}', [
        ConversationController::class,
        'show',
    ]);

    Route::put('/conversations/{conversation}', [
        ConversationController::class,
        'update',
    ]);

    Route::delete('/conversations/{conversation}', [
        ConversationController::class,
        'destroy',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Chat
    |--------------------------------------------------------------------------
    */

    Route::post('/chat', [
        ChatController::class,
        'chat',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Stripe Billing Portal
    |--------------------------------------------------------------------------
    */

    Route::get('/billing', function (Request $request) {
        return $request
            ->user()
            ->redirectToBillingPortal(
                route('billing.return')
            );
    })->name('billing');

    Route::get('/billing/return', function () {
        return response()->json([
            'message' =>
                'Returned from Stripe Billing Portal.',
        ]);
    })->name('billing.return');

    Route::post('/files', [FileController::class,'store']);
    });
