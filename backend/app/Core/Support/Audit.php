<?php

namespace App\Core\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class Audit
{
    public static function log(string $action, ?Model $subject = null, array $meta = []): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ? json_encode($meta) : null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
