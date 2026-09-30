<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $usuario = $request->user();

        if (!$usuario) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $rolesPermitidos = array_map('intval', $roles);

        if (!in_array((int) $usuario->id_rol, $rolesPermitidos, true)) {
            return response()->json([
                'message' => 'No tienes permisos para realizar esta acción.'
            ], 403);
        }

        return $next($request);
    }
}