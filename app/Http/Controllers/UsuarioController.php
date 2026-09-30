<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UsuarioController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(UserResource::collection(User::orderBy('id_usuario')->paginate(30))->response()->getData(true));
    }

    public function show(User $usuario): UserResource
    {
        Gate::authorize('view', $usuario);

        return new UserResource($usuario);
    }

    public function profile(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(ProfileRequest $request, User $usuario, UserService $users): UserResource
    {
        return new UserResource($users->update($request->user(), $usuario, $request->validated()));
    }

    public function destroy(Request $request, User $usuario, UserService $users, AuditService $audit): JsonResponse
    {
        DB::transaction(function () use ($request, $usuario, $users, $audit): void {
            $usuario = User::whereKey($usuario->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('deactivate', $usuario);
            $usuario->activo = false;
            $users->revokeCredentials($usuario);
            $usuario->save();
            $audit->record($request->user(), 'DEACTIVATE', 'usuarios', $usuario->getKey(), ['activo' => true], ['activo' => false]);
        }, 3);

        return response()->json(['message' => 'Usuario desactivado.']);
    }
}
