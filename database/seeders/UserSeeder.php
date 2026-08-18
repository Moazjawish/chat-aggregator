<?php

namespace Database\Seeders;

use App\Models\AIModel;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::factory(10)->create();
        $models = AIModel::with('subscriptions')->get();
        foreach($users as $user)
        {
            $numOfModels = rand(1, $models->count());
            $ramdomModels= $models->random($numOfModels);

            foreach($ramdomModels as $model){
                $subscription = $model->subscriptions->random();
                $user->models()->attach($model->id, [
                    'subscription_id' =>$subscription->id,
                    'started_at' =>now(),
                    'expires_at' =>now()->addMonth(),
                    'status' => 'active'
                ]);
            }
        }

    }
}
