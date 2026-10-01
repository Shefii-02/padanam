<?php

namespace App\Core\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $lang = substr((string) $request->header('Accept-Language', 'en'), 0, 2);
        app()->setLocale(in_array($lang, config('app.supported_languages'), true) ? $lang : 'en');

        return $next($request);
    }
}
