<?php
namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanLimit;
use Illuminate\Database\Seeder;

class SubscriptionPlanLimitSeeder extends Seeder
{
    public function run(): void
    {
        $limits = [
            'Basic Monthly' => [
                'input_token_limit' => 100_000,
                'output_token_limit' => 50_000,
            ],

            'Basic Yearly' => [
                'input_token_limit' => 1_200_000,
                'output_token_limit' => 600_000,
            ],

            'Pro Monthly' => [
                'input_token_limit' => 1_000_000,
                'output_token_limit' => 500_000,
            ],

            'Pro Yearly' => [
                'input_token_limit' => 12_000_000,
                'output_token_limit' => 6_000_000,
            ],
        ];

        foreach ($limits as $planName => $tokenLimits) {

            $plan = SubscriptionPlan::where('name', $planName)->first();

            if (! $plan) {
                $this->command->warn(
                    "Subscription plan '{$planName}' not found."
                );

                continue;
            }

            SubscriptionPlanLimit::updateOrCreate(
                [
                    'subscription_plan_id' => $plan->id,
                ],
                [
                    'input_token_limit' => $tokenLimits['input_token_limit'],
                    'output_token_limit' => $tokenLimits['output_token_limit'],
                ]
            );
        }
    }
}
