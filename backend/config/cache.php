<?php

return [
    'default' => env('CACHE_STORE', 'redis'),
    'stores' => [
        'array' => ['driver' => 'array', 'serialize' => false],
        'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data')],
        'database' => ['driver' => 'database', 'table' => 'cache'],
        'redis' => ['driver' => 'redis', 'connection' => 'cache'],
    ],
    'prefix' => 'padanam_cache_',
];
