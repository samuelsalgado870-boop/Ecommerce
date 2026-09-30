<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->disponible(), 403, 'Cuenta no disponible.');
        if ($request->user()->currentAccessToken() instanceof \Laravel\Sanctum\PersonalAccessToken) {
            abort_unless($request->user()->tokenCan('api'), 403);
        }

        return $next($request);
    }
}
