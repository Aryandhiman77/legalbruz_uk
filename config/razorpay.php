<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Razorpay Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Razorpay Payment Gateway Integration
    | Get keys from: https://dashboard.razorpay.com/app/keys
    |
    */

    'key_id' => env('RAZORPAY_KEY_ID'),
    'key_secret' => env('RAZORPAY_KEY_SECRET'),
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Default Payment Settings
    |--------------------------------------------------------------------------
    */

    'currency' => env('PAYMENT_CURRENCY', 'GBP'),
    'timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Application Settings for Payment
    |--------------------------------------------------------------------------
    */

    'app_name' => 'Legal Bruz UK',
    'app_description' => 'UK Trade Mark Application Services',

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    */

    'webhook_url' => env('APP_URL') . '/webhooks/razorpay',
    'webhook_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Custom Payment Options
    |--------------------------------------------------------------------------
    |
    | Allow users to specify custom payment amounts
    | Set 'enabled' to true to allow custom amounts
    |
    */

    'custom_payments' => [
        'enabled' => true,
        'min_amount' => 1,
        'max_amount' => 100000,
    ],
];
