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
                'input_cost' => 1.25,
                'output_cost' => 10.00,
            ],

            'gemini-3.6-flash' => [
                'input_cost' => 1.25,
                'output_cost' => 5.00,
            ],

            'claude-sonnet' => [
                'input_cost' => 3.00,
                'output_cost' => 15.00,
            ],

            'deepseek-chat' => [
                'input_cost' => 0.27,
                'output_cost' => 1.10,
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
