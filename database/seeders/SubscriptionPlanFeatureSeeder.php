<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanFeatureSeeder extends Seeder
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
        | Basic
        |--------------------------------------------------------------------------
        */

        $basic->features()->syncWithoutDetaching([
            $fileUpload->id => [
                'status' => true,
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | Pro
        |--------------------------------------------------------------------------
        */

        $pro->features()->syncWithoutDetaching([
            $fileUpload->id => [
                'status' => true,
            ],

            $imageUpload->id => [
                'status' => true,
            ],

            $webSearch->id => [
                'status' => true,
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | Premium
        |--------------------------------------------------------------------------
        */

        $premium->features()->syncWithoutDetaching([
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
        ]);
    }
}
