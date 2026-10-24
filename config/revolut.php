<?php

return [
    'secret_key' => env('REVOLUT_SECRET_KEY'),
    'webhook_secret' => env('REVOLUT_WEBHOOK_SECRET'),
    'api_version' => env('REVOLUT_API_VERSION', '2026-08-17'),
    'environment' => env('REVOLUT_ENVIRONMENT', 'sandbox'),
    'currency' => env('REVOLUT_CURRENCY', 'GBP'),
    'timeout' => (int) env('REVOLUT_TIMEOUT', 30),
    'webhook_tolerance_seconds' => (int) env('REVOLUT_WEBHOOK_TOLERANCE_SECONDS', 300),
    'custom_payments' => [
        'min_amount' => (float) env('REVOLUT_MIN_AMOUNT', 1),
        'max_amount' => (float) env('REVOLUT_MAX_AMOUNT', 100000),
    ],
];
