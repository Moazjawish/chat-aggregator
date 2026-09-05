<?php
namespace Database\Seeders;

use App\Models\Feature;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanFeatureSeeder extends Seeder
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
        | Features
        |--------------------------------------------------------------------------
        */

        $fileUpload = Feature::where(
            'key',
            'file_upload'
        )->firstOrFail();

        $imageUpload = Feature::where(
            'key',
            'image_upload'
        )->firstOrFail();

        $webSearch = Feature::where(
            'key',
            'web_search'
        )->firstOrFail();

        $advancedModels = Feature::where(
            'key',
            'advanced_models'
        )->firstOrFail();


        /*
        |--------------------------------------------------------------------------
        | Basic Features
        |--------------------------------------------------------------------------
        */

        $basicFeatures = [
            $fileUpload->id => [
                'status' => true,
            ],
        ];


        /*
        |--------------------------------------------------------------------------
        | Pro Features
        |--------------------------------------------------------------------------
        */

        $proFeatures = [
            $fileUpload->id => [
                'status' => true,
            ],

            $imageUpload->id => [
                'status' => true,
            ],

            $webSearch->id => [
                'status' => true,
            ],

            $advancedModels->id => [
                'status' => true,
            ],
        ];


        /*
        |--------------------------------------------------------------------------
        | Basic Monthly
        |--------------------------------------------------------------------------
        */

        $basicMonthly->features()->sync(
            $basicFeatures
        );


        /*
        |--------------------------------------------------------------------------
        | Basic Yearly
        |--------------------------------------------------------------------------
        */

        $basicYearly->features()->sync(
            $basicFeatures
        );


        /*
        |--------------------------------------------------------------------------
        | Pro Monthly
        |--------------------------------------------------------------------------
        */

        $proMonthly->features()->sync(
            $proFeatures
        );


        /*
        |--------------------------------------------------------------------------
        | Pro Yearly
        |--------------------------------------------------------------------------
        */

        $proYearly->features()->sync(
            $proFeatures
        );
    }
}
