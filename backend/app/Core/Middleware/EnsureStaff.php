<?php

namespace App\Core\Middleware;

use App\Core\Enums\Role;
use App\Core\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;

/** Admin panel routes: super_admin, admin, staff, teacher (and custom roles flagged as panel roles). */
class EnsureStaff
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || ! $user->isPanelUser()) {
            return ApiResponse::fail('This area is for staff only.', 403);
        }

        return $next($request);
    }
}
