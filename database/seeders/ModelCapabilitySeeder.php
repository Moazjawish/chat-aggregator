<?php
namespace Database\Seeders;

use App\Models\AIModel;
use App\Models\ModelCapability;
use Illuminate\Database\Seeder;

class ModelCapabilitySeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Model capabilities configuration
        |--------------------------------------------------------------------------
        |
        | لا نعتمد على model_id لأنه قد يختلف
        | بين قواعد البيانات.
        |
        | نعتمد على model_key لأنه ثابت.
        |
        | capabilities:
        |
        | image_input
        | document_input
        |
        */

        $models = [

            /*
            |--------------------------------------------------------------------------
            | OpenAI
            |--------------------------------------------------------------------------
            */

            'gpt-5.6' => [
                'image_input',
                'document_input',
            ],

            'gpt-5.5' => [
                'image_input',
                'document_input',
            ],

            /*
            |--------------------------------------------------------------------------
            | Gemini
            |--------------------------------------------------------------------------
            */

            'gemini-3.6-flash' => [
                'image_input',
                'document_input',
            ],

            /*
            |--------------------------------------------------------------------------
            | Claude
            |--------------------------------------------------------------------------
            */

            'claude-sonnet' => [
                'image_input',
                'document_input',
            ],

            /*
            |--------------------------------------------------------------------------
            | DeepSeek
            |--------------------------------------------------------------------------
            |
            | أضف image_input فقط للموديل الذي
            | يدعم Vision فعليًا.
            |
            */

            'deepseek-chat' => [
                // no attachments
            ],
        ];

        foreach ($models as $modelKey => $capabilities) {

            $model = AIModel::query()
                ->where(
                    'model_key',
                    $modelKey
                )
                ->first();

            /*
             * إذا لم يكن Model موجودًا في DB
             * نتجاوزه بدل أن يفشل Seeder بالكامل.
             */
            if (! $model) {
                continue;
            }

            /*
             * نحذف capabilities القديمة لهذا الموديل
             * ثم نعيد بنائها من config أعلاه.
             */
            ModelCapability::query()
                ->where(
                    'model_id',
                    $model->id
                )
                ->delete();

            foreach ($capabilities as $capability) {

                ModelCapability::create([
                    'model_id' =>
                        $model->id,

                    'key' =>
                        $capability,

                    'status' =>
                        true,
                ]);
            }
        }
    }
}
