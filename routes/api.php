<?
use App\Http\Controllers\ChatController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\UserAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [UserAuthController::class, 'register']);
Route::post('/login', [UserAuthController::class, 'login']);
Route::post('/stripe/webhook', [StripeWebhookController::class,'handleWebhook']);

Route::middleware('auth:sanctum')->group(function(){
    Route::post('/subscriptions/checkout', [SubscriptionCheckoutController::class, 'store']);
    Route::post('/plan/store', [SubscriptionPlanController::class, 'store']);
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'] );
    Route::post('/subscription/resume', [SubscriptionController::class, 'resume'] );

    Route::get('/billing', function (Request $request){
        return $request->user()->redirectToBillingPortal( route('billing.return') );
        })->name('billing');

    Route::get('/billing/return', function () {
        return response()->json([ 'message' => 'Returned from Stripe Billing Portal.', ]); })
        ->name('billing.return');

    Route::get('/subscription', [SubscriptionController::class, 'current'] );
    Route::get( '/payments', [SubscriptionController::class, 'payments'] );


    Route::post('/subscription/change-plan', [SubscriptionController::class,'changePlan']);

    Route::post('/chat', [ChatController::class, 'chat']);
    Route::post('/logout', [UserAuthController::class, 'logout']);

});
