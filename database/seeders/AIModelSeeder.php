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
        // OpenAI - GPT-5.5
        $gpt = AIModel::create([
            'name' => 'GPT-5.5',
            'provider' => 'OpenAI',
            'model_key' => 'gpt-5.5',

        ]);

        $gpt->subscriptions()->createMany([
            ['name' => 'Free'],
            ['name' => 'Plus'],
            ['name' => 'Pro'],
            ['name' => 'Enterprise'],
        ]);


        // Google - Gemini
        $gemini = AIModel::create([
            'name' => 'Gemini 2.5',
            'provider' => 'Google',
            'model_key' => 'gemini-2.5-pro',

        ]);

        $gemini->subscriptions()->createMany([
            ['name' => 'Free'],
            ['name' => 'Advanced'],
            ['name' => 'Business'],
            ['name' => 'Enterprise'],
        ]);


        // Anthropic - Claude
        $claude = AIModel::create([
            'name' => 'Claude Sonnet',
            'provider' => 'Anthropic',
            'model_key' => 'claude-sonnet-4-20250514',

        ]);

        $claude->subscriptions()->createMany([
            ['name' => 'Free'],
            ['name' => 'Pro'],
            ['name' => 'Team'],
            ['name' => 'Enterprise'],
        ]);


        // DeepSeek
        $deepseek = AIModel::create([
            'name' => 'DeepSeek',
            'provider' => 'DeepSeek',
            'model_key' => 'deepseek-chat',

        ]);

        $deepseek->subscriptions()->createMany([
            ['name' => 'Free'],
            ['name' => 'Pro'],
        ]);


        // Meta - Llama
        $llama = AIModel::create([
            'name' => 'Llama',
            'provider' => 'Meta',
            'model_key' => 'Llama-3.3-70B-Instruct',
        ]);

        $llama->subscriptions()->createMany([
            ['name' => 'Free'],
            ['name' => 'Pro'],
            ['name' => 'Enterprise'],
        ]);


        // Mistral
        $mistral = AIModel::create([
            'name' => 'Mistral Large',
            'provider' => 'Mistral AI',
            'model_key' => 'mistral-large-latest',
        ]);

        $mistral->subscriptions()->createMany([
            ['name' => 'Free'],
            ['name' => 'Pro'],
            ['name' => 'Business'],
        ]);


        // xAI - Grok
        $grok = AIModel::create([
            'name' => 'Grok',
            'provider' => 'xAI',
            'model_key' => 'grok-4',

        ]);

        $grok->subscriptions()->createMany([
            ['name' => 'Free'],
            ['name' => 'Premium'],
            ['name' => 'SuperGrok'],
        ]);
    }
}
