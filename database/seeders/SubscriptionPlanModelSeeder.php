<?php
namespace Database\Seeders;

use App\Models\AIModel;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanModelSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Subscription Plans
        |--------------------------------------------------------------------------
        */

        $basicMonthly = SubscriptionPlan::where(
            'name',
            'Basic Monthly'
        )->firstOrFail();

        $basicYearly = SubscriptionPlan::where(
            'name',
            'Basic Yearly'
        )->firstOrFail();

        $proMonthly = SubscriptionPlan::where(
            'name',
            'Pro Monthly'
        )->firstOrFail();

        $proYearly = SubscriptionPlan::where(
            'name',
            'Pro Yearly'
        )->firstOrFail();


        /*
        |--------------------------------------------------------------------------
        | AI Models
        |--------------------------------------------------------------------------
        */

        $gpt = AIModel::where(
            'model_key',
            'gpt-5.6'
        )->firstOrFail();

        $gemini = AIModel::where(
            'model_key',
            'gemini-3.6-flash'
        )->firstOrFail();

        $claude = AIModel::where(
            'model_key',
            'claude-sonnet'
        )->firstOrFail();

        $deepseek = AIModel::where(
            'model_key',
            'deepseek-chat'
        )->firstOrFail();


        /*
        |--------------------------------------------------------------------------
        | Basic Models
        |--------------------------------------------------------------------------
        */

        $basicModels = [
            $gemini->id => [
                'input_price' => 3.00,
                'output_price' => 10.00,
                'status' => true,
                'input_token_limit' => 10000,
                'output_token_limit' => 5000,
            ],

            $deepseek->id => [
                'input_price' => 1.00,
                'output_price' => 3.00,
                'status' => true,
                'input_token_limit' => 10000,
                'output_token_limit' => 5000,
                ],
        ];


        /*
        |--------------------------------------------------------------------------
        | Pro Models
        |--------------------------------------------------------------------------
        */

        $proModels = [
            $gpt->id => [
                'input_price' => 5.00,
                'output_price' => 15.00,
                'status' => true,
                'input_token_limit' => 100000,
                'output_token_limit' => 50000,
            ],

            $gemini->id => [
                'input_price' => 3.00,
                'output_price' => 10.00,
                'status' => true,
                'input_token_limit' => 100000,
                'output_token_limit' => 50000,
            ],

            $claude->id => [
                'input_price' => 6.00,
                'output_price' => 20.00,
                'status' => true,
                'input_token_limit' => 100000,
                'output_token_limit' => 50000,
            ],

            $deepseek->id => [
                'input_price' => 1.00,
                'output_price' => 3.00,
                'status' => true,
                'input_token_limit' => 100000,
                'output_token_limit' => 50000,
            ],
        ];


        /*
        |--------------------------------------------------------------------------
        | Basic Monthly
        |--------------------------------------------------------------------------
        */

        $basicMonthly->models()->sync(
            $basicModels
        );


        /*
        |--------------------------------------------------------------------------
        | Basic Yearly
        |--------------------------------------------------------------------------
        */

        $basicYearly->models()->sync(
            $basicModels
        );


        /*
        |--------------------------------------------------------------------------
        | Pro Monthly
        |--------------------------------------------------------------------------
        */

        $proMonthly->models()->sync(
            $proModels
        );


        /*
        |--------------------------------------------------------------------------
        | Pro Yearly
        |--------------------------------------------------------------------------
        */

        $proYearly->models()->sync(
            $proModels
        );
    }
}

