<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Orden;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrdenController extends Controller
{
	public function index(Request $request): JsonResponse
	{
		return response()->json(Orden::where('id_usuario', $request->user()->getKey())
			->with('detalles')->orderByDesc('fecha_orden')->orderByDesc('id_orden')->paginate(30));
	}

	public function show(Request $request, Orden $orden): JsonResponse
	{
		abort_unless((int) $orden->id_usuario === (int) $request->user()->getKey()
			|| $request->user()->tienePermiso('pedidos.read'), 404);

		return response()->json($orden->load('detalles'));
	}

	public function store(StoreOrderRequest $request, OrderService $orders): JsonResponse
	{
		return response()->json($orders->create($request->user(), $request->validated('items'),
			$request->validated('clave_idempotencia')), 201);
	}

	public function cancel(Request $request, Orden $orden, OrderService $orders): JsonResponse
	{
		abort_unless((int) $orden->id_usuario === (int) $request->user()->getKey()
			|| $request->user()->tienePermiso('pedidos.update'), 404);

		return response()->json($orders->cancel($request->user(), $orden->getKey()));
	}
}