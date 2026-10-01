<?php

return [
    'secret' => env('JWT_SECRET'),
    'keys' => ['public' => env('JWT_PUBLIC_KEY'), 'private' => env('JWT_PRIVATE_KEY'), 'passphrase' => env('JWT_PASSPHRASE')],
    'ttl' => (int) env('JWT_TTL', 60 * 24 * 7),           // 7 days (minutes)
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 60 * 24 * 60),
    'algo' => env('JWT_ALGO', 'HS256'),                    // Node realtime verifies with the same secret
    'required_claims' => ['iss', 'iat', 'exp', 'nbf', 'sub', 'jti'],
    'persistent_claims' => [],
    'lock_subject' => true,
    'leeway' => 5,
    'blacklist_enabled' => true,
    'blacklist_grace_period' => 30,
    'show_black_list_exception' => true,
    'decrypt_cookies' => false,
    'cookie_key_name' => 'token',
    'providers' => [
        'jwt' => PHPOpenSourceSaver\JWTAuth\Providers\JWT\Lcobucci::class,
        'auth' => PHPOpenSourceSaver\JWTAuth\Providers\Auth\Illuminate::class,
        'storage' => PHPOpenSourceSaver\JWTAuth\Providers\Storage\Illuminate::class,
    ],
];
