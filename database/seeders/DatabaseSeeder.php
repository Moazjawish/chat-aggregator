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
            UserSeeder::class,
            AIModelSeeder::class,
            AIModelCostSeeder::class,
            FeatureSeeder::class,
            SubscriptionPlanSeeder::class,
            SubscriptionPlanModelSeeder::class,
            SubscriptionPlanFeatureSeeder::class,
        ]);
    }
}
