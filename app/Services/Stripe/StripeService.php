<?php

namespace App\Services\Stripe;

use App\Models\SubscriptionPlan;
use Laravel\Cashier\Cashier;
use Throwable;

class StripeService
{
    public function storeProduct(SubscriptionPlan $plan)
    {
        if($plan->stripe_product_id)
        {
            return $plan;
        }
        if($plan->stripe_price_id)
        {
            return $plan;
        }
        try{
        $stripe = Cashier::stripe();
        $product = $stripe->products->create([
            'name' => $plan->name,
            'description' => $plan->description,
            [
                'metadata'=>[
                    'subscription_plan_id' => $plan->id
                ]
            ]
        ]);

        $price = $stripe->prices->create([
            'product' => $product->id,
            'unit_amount' => (int) round($plan->price * 100),
            'currency' => 'usd',
            'recurring' => [
                'interval' => $plan->billing_interval,
            ],
                'metadata'=>[
                    'subscription_plan_id' => $plan->id
                ]
        ]);

        $plan->update([
            'stripe_product_id' => $product->id,
            'stripe_price_id' => $price->id,
        ]);

        return $plan->fresh();

        } catch(Throwable $e){
            if($product)
                try {
                        $stripe->products->delete(
                            $product->id
                        );
                    } catch (Throwable $cleanupException) {
                        // Log cleanup failure
                    }
                    throw $e;
        }
    }
}
