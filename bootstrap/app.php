<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'active' => \App\Http\Middleware\EnsureActiveUser::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'token', 'password_hash']);
        $exceptions->dontReport([\Illuminate\Database\UniqueConstraintViolationException::class]);
        $exceptions->report(function (\Illuminate\Database\QueryException $exception): bool {
            \Illuminate\Support\Facades\Log::error('Database operation failed.', ['sqlstate' => $exception->getCode()]);

            return false;
        });
        $exceptions->render(function (\Illuminate\Database\QueryException $exception, Request $request): ?\Illuminate\Http\JsonResponse {
            if ($request->is('api/*')) {
                $duplicate = $exception instanceof \Illuminate\Database\UniqueConstraintViolationException;

                return response()->json(['message' => $duplicate ? 'Conflicto con un registro existente.' : 'No se pudo completar la operación.'], $duplicate ? 409 : 500);
            }

            return null;
        });
    })->create();
