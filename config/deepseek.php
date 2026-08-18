<?php

return [

    'api_key' => env('DEEPSEEK_API_KEY'),

    'base_url' => env(
        'DEEPSEEK_BASE_URL',
        'https://api.deepseek.com'
    ),

    'ca_bundle' => env('DEEPSEEK_CA_BUNDLE'),

];
