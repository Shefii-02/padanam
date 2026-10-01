<?php

return [
    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],
    'phonepe' => [
        // PhonePe PG (standard checkout). Sandbox: https://api-preprod.phonepe.com/apis/pg-sandbox
        'base_url' => env('PHONEPE_BASE_URL', 'https://api-preprod.phonepe.com/apis/pg-sandbox'),
        'merchant_id' => env('PHONEPE_MERCHANT_ID'),
        'salt_key' => env('PHONEPE_SALT_KEY'),
        'salt_index' => env('PHONEPE_SALT_INDEX', 1),
    ],
    'fcm' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),          // log | msg91 | twofactor
        'key' => env('SMS_KEY'),
        'template' => env('SMS_OTP_TEMPLATE'),
    ],
    'whatsapp' => [
        // optional WhatsApp Business API for invoices / links; otherwise wa.me share links are used
        'token' => env('WHATSAPP_TOKEN'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
    ],
    'realtime' => [
        'url' => env('REALTIME_URL', 'http://localhost:4000'),
        'internal_key' => env('REALTIME_INTERNAL_KEY'),   // Laravel → Node internal events
    ],
    'aws_video' => [
        'bucket' => env('AWS_VIDEO_BUCKET'),
        'cloudfront_domain' => env('CLOUDFRONT_DOMAIN'),
        'cloudfront_key_pair_id' => env('CLOUDFRONT_KEY_PAIR_ID'),
        'cloudfront_private_key' => env('CLOUDFRONT_PRIVATE_KEY_PATH'),
    ],
];
