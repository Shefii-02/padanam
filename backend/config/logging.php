<?php

return [
    'default' => env('LOG_CHANNEL', 'daily'),
    'channels' => [
        'daily' => ['driver' => 'daily', 'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'debug'), 'days' => 14],
        'payments' => ['driver' => 'daily', 'path' => storage_path('logs/payments.log'), 'level' => 'info', 'days' => 90],
        'stderr' => ['driver' => 'monolog', 'handler' => Monolog\Handler\StreamHandler::class, 'with' => ['stream' => 'php://stderr']],
    ],
];
