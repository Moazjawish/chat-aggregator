<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            [
                'name' => 'File Upload',
                'key' => 'file_upload',
                'description' => 'Allow users to upload files.',
                'status' => true,
            ],
            [
                'name' => 'Image Upload',
                'key' => 'image_upload',
                'description' => 'Allow users to upload images.',
                'status' => true,
            ],
            [
                'name' => 'Web Search',
                'key' => 'web_search',
                'description' => 'Allow AI models to perform web searches.',
                'status' => true,
            ],
            [
                'name' => 'Advanced Models',
                'key' => 'advanced_models',
                'description' => 'Allow access to advanced AI models.',
                'status' => true,
            ],
        ];

        foreach ($features as $feature) {
            Feature::updateOrCreate(
                [
                    'key' => $feature['key'],
                ],
                $feature
            );
        }
    }
}
