<?php

namespace App\Core\Middleware;

use App\Core\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->status === 'blocked') {
            return ApiResponse::fail('Your account is blocked. Please contact support.', 403);
        }
        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(5)))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
