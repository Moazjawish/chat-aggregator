<?php

namespace Database\Seeders;

use App\Models\AIModel;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanModelSeeder extends Seeder
{
    public function run(): void
    {
        $basic = SubscriptionPlan::where(
            'name',
            'Basic'
        )->firstOrFail();

        $pro = SubscriptionPlan::where(
            'name',
            'Pro'
        )->firstOrFail();

        $premium = SubscriptionPlan::where(
            'name',
            'Premium'
        )->firstOrFail();

        $gpt = AIModel::where(
            'model_key',
            'gpt-5.6'
        )->firstOrFail();

        $gemini = AIModel::where(
            'model_key',
            'gemini-2.5-pro'
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
        | Basic
        |--------------------------------------------------------------------------
        */

        $basic->models()->syncWithoutDetaching([
            $gemini->id => [
                'input_price' => 0.00000300,
                'output_price' => 0.00001000,
                'status' => true,
            ],

            $deepseek->id => [
                'input_price' => 0.00000100,
                'output_price' => 0.00000300,
                'status' => true,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Pro
        |--------------------------------------------------------------------------
        */

        $pro->models()->syncWithoutDetaching([
            $gpt->id => [
                'input_price' => 0.00000500,
                'output_price' => 0.00001500,
                'status' => true,
            ],

            $gemini->id => [
                'input_price' => 0.00000300,
                'output_price' => 0.00001000,
                'status' => true,
            ],

            $claude->id => [
                'input_price' => 0.00000600,
                'output_price' => 0.00002000,
                'status' => true,
            ],

            $deepseek->id => [
                'input_price' => 0.00000100,
                'output_price' => 0.00000300,
                'status' => true,
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | Premium
        |--------------------------------------------------------------------------
        */

        $premium->models()->syncWithoutDetaching([
            $gpt->id => [
                'input_price' => 0.00000400,
                'output_price' => 0.00001200,
                'status' => true,
            ],

            $gemini->id => [
                'input_price' => 0.00000250,
                'output_price' => 0.00000800,
                'status' => true,
            ],

            $claude->id => [
                'input_price' => 0.00000500,
                'output_price' => 0.00001800,
                'status' => true,
            ],

            $deepseek->id => [
                'input_price' => 0.00000080,
                'output_price' => 0.00000250,
                'status' => true,
            ],
        ]);
    }
}
