<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Producto $producto): JsonResponse
    {
        return response()->json($producto->movimientos()->orderByDesc('id_movimiento')->paginate(30));
    }

    public function store(Request $request, Producto $producto, InventoryService $inventory): JsonResponse
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:ENTRADA,SALIDA,AJUSTE'],
            'cantidad' => ['required', 'integer', 'between:-1000000,1000000', 'not_in:0'],
            'clave_operacion' => ['required', 'uuid'], 'id_orden' => ['prohibited'],
        ]);

        return response()->json($inventory->change($producto->getKey(), $data['tipo'], $data['cantidad'],
            $request->user(), $data['clave_operacion']), 201);
    }
}
