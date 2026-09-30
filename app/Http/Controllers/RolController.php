<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolController extends Controller
{
	public function index(): JsonResponse
	{
		return response()->json(Rol::with('permisos')->orderBy('id_rol')->paginate(30));
	}

	public function store(Request $request, User $usuario, Rol $rol, RoleAssignmentService $roles): JsonResponse
	{
		$data = $request->validate(['expira_en' => ['nullable', 'date', 'after:now']]);
		$roles->assign($request->user(), $usuario, $rol, $data['expira_en'] ?? null);

		return response()->json(['message' => 'Rol asignado.']);
	}

	public function destroy(Request $request, User $usuario, Rol $rol, RoleAssignmentService $roles): JsonResponse
	{
		$roles->assign($request->user(), $usuario, $rol, revoke: true);

		return response()->json(['message' => 'Rol revocado.']);
	}
}