<?php

namespace App\Modules\Chat\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Node realtime → Laravel calls. */
class InternalKey
{
    public function handle(Request $request, Closure $next)
    {
        $key = (string) config('services.realtime.internal_key');
        abort_unless($key !== '' && hash_equals($key, (string) $request->header('X-Internal-Key')), 401);

        return $next($request);
    }
}
