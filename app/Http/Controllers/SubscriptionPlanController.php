<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use App\Services\Stripe\StripeService;
use Illuminate\Support\Facades\DB;
use Throwable;

class SubscriptionPlanController extends Controller
{
    public function store(StoreSubscriptionPlanRequest $request, StripeService $service)
    {
        DB::beginTransaction();
        try {
            $plan = SubscriptionPlan::create($request->validated());
            $service->storeProduct($plan);
            DB::commit();
            return response()->json([
                'message' => 'Subscription plan created successfully.',
                'Subscription plan' => $plan->fresh(),
                ],201);

        } catch (Throwable $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Unable to create subscription plan.',
        ], 500);
    }
    }
}
