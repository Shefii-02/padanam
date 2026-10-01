<?php

return [
    'name' => env('APP_NAME', 'Padanam'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    'deep_link_host' => env('DEEP_LINK_HOST', 'https://padanam.app'),
    'timezone' => 'Asia/Kolkata',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_IN',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [],
    'maintenance' => ['driver' => 'file'],
    // demo OTP for non-production (never works in production)
    'demo_otp' => env('DEMO_OTP', '123456'),
    'support_whatsapp' => env('SUPPORT_WHATSAPP'),   // e.g. 919876543210 – shown as "Help on WhatsApp" in the app
    // App Links / Universal Links for padanam.app/j/{code}, /c/{slug}, /a/{slug}, /paid/{order}
    'android_package' => env('ANDROID_PACKAGE', 'com.padanam.app'),
    'android_sha256' => array_filter(explode(',', env('ANDROID_SHA256', ''))),
    'ios_app_id' => env('IOS_APP_ID', 'TEAMID.com.padanam.app'),
    'play_store_url' => env('PLAY_STORE_URL', 'https://play.google.com/store/apps/details?id=com.padanam.app'),
    'app_store_url' => env('APP_STORE_URL', 'https://apps.apple.com/app/id0000000000'),
    'supported_languages' => ['en', 'ml', 'hi', 'ta', 'kn', 'te'],
];
