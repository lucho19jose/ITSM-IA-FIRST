<?php

return [
    'provider' => env('AI_PROVIDER', 'openrouter'),
    'api_key' => env('OPENROUTER_API_KEY'),
    'model' => env('AI_MODEL', 'google/gemma-3-27b-it:free'),
    'fast_model' => env('AI_FAST_MODEL', 'google/gemma-3-12b-it:free'),
    'base_url' => env('AI_BASE_URL', 'https://openrouter.ai/api/v1'),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),
    'auto_classify_threshold' => 0.70,
    'rate_limit' => [
        'requests_per_minute' => 30,
        'tokens_per_minute' => 50000,
    ],
];
