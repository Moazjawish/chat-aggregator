<?php

namespace Database\Seeders;

use App\Models\AIModel;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;

class AIModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $models = [
            [
                'name' => 'GPT-5.6',
                'provider' => 'openai',
                'model_key' => 'gpt-5.6',
                'status' => true,
            ],
            [
                'name' => 'Gemini 2.5 Pro',
                'provider' => 'google',
                'model_key' => 'gemini-2.5-pro',
                'status' => true,
            ],
            [
                'name' => 'Claude Sonnet',
                'provider' => 'anthropic',
                'model_key' => 'claude-sonnet',
                'status' => true,
            ],
            [
                'name' => 'DeepSeek Chat',
                'provider' => 'deepseek',
                'model_key' => 'deepseek-chat',
                'status' => true,
            ],
        ];

        foreach ($models as $model) {
            AIModel::updateOrCreate(
                [
                    'model_key' => $model['model_key'],
                ],
                $model
            );
        }
    }
}
