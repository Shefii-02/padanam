<?php

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),
    'connections' => [
        'sync' => ['driver' => 'sync'],
        'database' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 120],
        'redis' => ['driver' => 'redis', 'connection' => 'default', 'queue' => 'default', 'retry_after' => 120, 'block_for' => null],
    ],
    'batching' => ['database' => env('DB_CONNECTION', 'mysql'), 'table' => 'job_batches'],
    'failed' => ['driver' => 'database-uuids', 'database' => env('DB_CONNECTION', 'mysql'), 'table' => 'failed_jobs'],
];
