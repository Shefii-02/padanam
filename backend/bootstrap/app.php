<?php

use App\Core\Http\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'active' => \App\Core\Middleware\EnsureUserIsActive::class,
            'staff' => \App\Core\Middleware\EnsureStaff::class,
            'locale' => \App\Core\Middleware\SetLocale::class,
        ]);
        $middleware->api(prepend: [\App\Core\Middleware\ForceJsonResponse::class]);
        $middleware->api(append: [\App\Core\Middleware\SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $api = fn (Request $r) => $r->is('api/*') || $r->expectsJson();

        $exceptions->render(fn (ValidationException $e, Request $r) => $api($r)
            ? ApiResponse::fail($e->validator->errors()->first(), 422, ['errors' => $e->errors()]) : null);
        $exceptions->render(fn (AuthenticationException $e, Request $r) => $api($r)
            ? ApiResponse::fail('Please log in again.', 401) : null);
        $exceptions->render(fn (AuthorizationException|UnauthorizedException $e, Request $r) => $api($r)
            ? ApiResponse::fail("You don't have permission to do this.", 403) : null);
        $exceptions->render(fn (ModelNotFoundException|NotFoundHttpException $e, Request $r) => $api($r)
            ? ApiResponse::fail('Not found.', 404) : null);
        $exceptions->render(fn (\App\Core\Support\DomainException $e, Request $r) => $api($r)
            ? ApiResponse::fail($e->getMessage(), $e->status, $e->extra) : null);
        $exceptions->render(fn (HttpExceptionInterface $e, Request $r) => $api($r)
            ? ApiResponse::fail($e->getMessage() ?: 'Request failed.', $e->getStatusCode()) : null);
    })->create();
