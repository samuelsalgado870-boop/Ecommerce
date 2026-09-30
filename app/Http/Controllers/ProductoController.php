<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'sku' => ['required', 'string', 'max:50', 'unique:productos,sku'],
            'nombre' => ['required', 'string', 'max:150'],
            'precio' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
        ]);

        $producto = Producto::create([
            'sku' => $datos['sku'],
            'nombre' => $datos['nombre'],
            'precio' => $datos['precio'],
            'stock' => $datos['stock'],
            'version' => 1,
        ]);

        return response()->json($producto, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $datos = $request->validate([
            'sku' => ['sometimes', 'string', 'max:50', 'unique:productos,sku,' . $id . ',id_producto'],
            'nombre' => ['sometimes', 'string', 'max:150'],
            'precio' => ['sometimes', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
        ]);

        $producto->update($datos);

        return response()->json($producto);
    }
    public function destroy(int $id): JsonResponse
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $producto->delete();

        return response()->json([
            'message' => 'Producto eliminado correctamente'
        ]);
    }
}
