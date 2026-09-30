<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $usuario = User::where('email', $datos['email'])->first();

        if (!$usuario) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        if (!$usuario->activo) {
            return response()->json([
                'message' => 'El usuario está inactivo'
            ], 403);
        }

        if (!Hash::check($datos['password'], $usuario->password_hash)) {
            $usuario->increment('intentos_fallidos');

            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        $usuario->update([
            'ultimo_acceso' => now(),
            'intentos_fallidos' => 0,
        ]);

        $token = $usuario->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'token' => $token,
            'usuario' => [
                'id_usuario' => $usuario->id_usuario,
                'nombre' => $usuario->nombre,
                'email' => $usuario->email,
                'id_rol' => $usuario->id_rol,
            ],
        ], 200);
    }
    public function logout(Request $request): JsonResponse
{
    $request->user()->currentAccessToken()->delete();

    return response()->json([
        'message' => 'Sesión cerrada correctamente'
    ], 200);
}
}
