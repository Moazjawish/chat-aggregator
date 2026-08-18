<?php

return [

    'api_key' => env('CLAUDE_API_KEY'),

    'base_url' => env(
        'CLAUDE_BASE_URL',
        'https://api.anthropic.com'
    ),

    'ca_bundle' => env('CLAUDE_CA_BUNDLE'),

];
