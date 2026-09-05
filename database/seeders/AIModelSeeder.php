<?php
namespace Database\Seeders;

use App\Models\AIModel;
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
                'name' => 'Gemini 3.6 Flash',
                'provider' => 'gemini',
                'model_key' => 'gemini-3.6-flash',
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

        /*
        |--------------------------------------------------------------------------
        | Disable old Gemini model
        |--------------------------------------------------------------------------
        */

        AIModel::where('model_key', 'gemini-2.5-flash')
        ->update([
            'status' => false,
        ]);
        /*
        |--------------------------------------------------------------------------
        | Create or update current models
        |--------------------------------------------------------------------------
        */

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
