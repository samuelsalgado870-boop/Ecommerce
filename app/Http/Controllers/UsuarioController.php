<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    /**
     * Listar todos los usuarios.
     */
    public function index(): JsonResponse
    {
        $usuarios = User::select(
            'id_usuario',
            'id_rol',
            'nombre',
            'email',
            'activo',
            'intentos_fallidos',
            'bloqueado_hasta',
            'ultimo_acceso',
            'fecha_creacion'
        )->get();

        return response()->json([
            'usuarios' => $usuarios
        ], 200);
    }

    /**
     * Mostrar un usuario específico.
     */
    public function show(int $id): JsonResponse
    {
        $usuario = User::select(
            'id_usuario',
            'id_rol',
            'nombre',
            'email',
            'activo',
            'intentos_fallidos',
            'bloqueado_hasta',
            'ultimo_acceso',
            'fecha_creacion'
        )->find($id);

        if (!$usuario) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'usuario' => $usuario
        ], 200);
    }

    /**
     * Crear un usuario.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'id_rol' => ['required', 'integer'],
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:usuarios,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $usuario = User::create([
            'id_rol' => $datos['id_rol'],
            'nombre' => $datos['nombre'],
            'email' => $datos['email'],
            'password_hash' => Hash::make($datos['password']),
            'activo' => true,
            'intentos_fallidos' => 0,
        ]);

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'usuario' => [
                'id_usuario' => $usuario->id_usuario,
                'id_rol' => $usuario->id_rol,
                'nombre' => $usuario->nombre,
                'email' => $usuario->email,
                'activo' => $usuario->activo,
            ]
        ], 201);
    }


/**
 * Actualizar un usuario.
 */
public function update(Request $request, int $id): JsonResponse
{
    $usuario = User::find($id);

    if (!$usuario) {
        return response()->json([
            'message' => 'Usuario no encontrado'
        ], 404);
    }

    $datos = $request->validate([
        'id_rol' => ['sometimes', 'integer'],
        'nombre' => ['sometimes', 'string', 'max:255'],
        'email' => ['sometimes', 'email', 'unique:usuarios,email,' . $id . ',id_usuario'],
        'password' => ['sometimes', 'string', 'min:8'],
        'activo' => ['sometimes', 'boolean'],
    ]);

   if (isset($datos['id_rol'])) {

    $rolUsuario = (int) $request->user()->id_rol;

    if (!in_array($rolUsuario, [3, 4], true)) {
        return response()->json([
            'message' => 'No tienes permisos para cambiar roles.'
        ], 403);
    }

    $usuario->id_rol = $datos['id_rol'];
}

    if (isset($datos['nombre'])) {
        $usuario->nombre = $datos['nombre'];
    }

    if (isset($datos['email'])) {
        $usuario->email = $datos['email'];
    }

    if (isset($datos['password'])) {
        $usuario->password_hash = Hash::make($datos['password']);
    }

    if (isset($datos['activo'])) {
        $usuario->activo = $datos['activo'];
    }

    $usuario->save();

    return response()->json([
        'message' => 'Usuario actualizado correctamente',
        'usuario' => [
            'id_usuario' => $usuario->id_usuario,
            'id_rol' => $usuario->id_rol,
            'nombre' => $usuario->nombre,
            'email' => $usuario->email,
            'activo' => $usuario->activo,
        ]
    ], 200);
}
public function destroy(int $id): JsonResponse
{
    $usuario = User::find($id);
 
    if (!$usuario) {
        return response()->json([
            'message' => 'Usuario no encontrado'
        ], 404);

    if (
    $request->user()->id_usuario !== $usuario->id_usuario &&
    !in_array((int) $request->user()->id_rol, [3, 4], true)
) {
    return response()->json([   
        'message' => 'No tienes permisos para modificar este usuario.'
    ], 403);
}
    }

    $usuario->delete();

    return response()->json([
        'message' => 'Usuario eliminado correctamente'
    ], 200);
}

}
