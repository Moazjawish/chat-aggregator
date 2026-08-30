<?php

namespace Database\Seeders;

use App\Models\AIModel;
use App\Models\ModelCost;
use Illuminate\Database\Seeder;

class AIModelCostSeeder extends Seeder
{
    public function run(): void
    {
        $costs = [
            'gpt-5.6' => [
                'input_cost' => 0.00000200,
                'output_cost' => 0.00000800,
            ],

            'gemini-2.5-pro' => [
                'input_cost' => 0.00000125,
                'output_cost' => 0.00000500,
            ],

            'claude-sonnet' => [
                'input_cost' => 0.00000300,
                'output_cost' => 0.00001500,
            ],

            'deepseek-chat' => [
                'input_cost' => 0.00000027,
                'output_cost' => 0.00000110,
            ],
        ];

        foreach ($costs as $modelKey => $cost) {

            $model = AIModel::where(
                'model_key',
                $modelKey
            )->firstOrFail();

            ModelCost::updateOrCreate(
                [
                    'model_id' => $model->id,
                    'effective_to' => null,
                ],
                [
                    'input_cost' => $cost['input_cost'],
                    'output_cost' => $cost['output_cost'],
                    'effective_from' => now(),
                    'effective_to' => null,
                ]
            );
        }
    }
}
