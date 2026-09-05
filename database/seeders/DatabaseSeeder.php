<?php

namespace Database\Seeders;

use App\Models\AIModel;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AIModelSeeder::class,
            SubscriptionPlanSeeder::class,
            AIModelCostSeeder::class,
            SubscriptionPlanModelSeeder::class,
            UserSeeder::class,
            FeatureSeeder::class,
            SubscriptionPlanFeatureSeeder::class,
            SubscriptionPlanLimitSeeder::class,
            ModelCapabilitySeeder::class,
            ]);
    }
}
