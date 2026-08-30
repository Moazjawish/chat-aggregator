<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'description' => 'Basic AI access.',
                'price' => 10.00,
                'billing_interval' => 'month',
                'stripe_product_id' => null,
                'stripe_price_id' => null,
                'status' => true,
            ],

            [
                'name' => 'Pro',
                'description' => 'Professional AI access.',
                'price' => 20.00,
                'billing_interval' => 'month',
                'stripe_product_id' => null,
                'stripe_price_id' => null,
                'status' => true,
            ],

            [
                'name' => 'Premium',
                'description' => 'Full access to advanced AI models.',
                'price' => 50.00,
                'billing_interval' => 'month',
                'stripe_product_id' => null,
                'stripe_price_id' => null,
                'status' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                [
                    'name' => $plan['name'],
                ],
                $plan
            );
        }
    }
}
