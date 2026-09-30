<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Services\AuditService;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Producto::where('activo', true)->orderBy('id_producto')
            ->paginate(30, ['id_producto', 'sku', 'nombre', 'precio']));
    }

    public function show(Producto $producto): JsonResponse
    {
        abort_unless($producto->activo, 404);

        return response()->json($producto->only(['id_producto', 'sku', 'nombre', 'precio']));
    }

    public function store(Request $request, InventoryService $inventory, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:50', 'unique:productos,sku'],
            'nombre' => ['required', 'string', 'max:150'],
            'precio' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'stock' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ]);
        abort_if(($data['stock'] ?? 0) > 0 && ! $request->user()->tienePermiso('inventario.update'), 403);
        $product = DB::transaction(function () use ($data, $request, $inventory, $audit): Producto {
            $product = Producto::create(array_intersect_key($data, array_flip(['sku', 'nombre', 'precio'])));
            if (($data['stock'] ?? 0) > 0) {
                $inventory->change($product->getKey(), 'ENTRADA', $data['stock'], $request->user(), 'initial:'.$product->getKey());
            }
            $audit->record($request->user(), 'CREATE', 'productos', $product->getKey(), [], $product->toArray());

            return $product->refresh();
        }, 3);

        return response()->json($product, 201);
    }

    public function update(Request $request, Producto $producto, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('productos', 'sku')->ignore($producto->getKey(), 'id_producto')],
            'nombre' => ['sometimes', 'required', 'string', 'max:150'],
            'precio' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'activo' => ['sometimes', 'boolean'], 'stock' => ['prohibited'], 'version' => ['prohibited'],
        ]);
        if (array_key_exists('activo', $data) && ! $data['activo']) {
            abort_unless($request->user()->tienePermiso('productos.delete'), 403);
        }
        $product = DB::transaction(function () use ($data, $producto, $request, $audit): Producto {
            $product = Producto::whereKey($producto->getKey())->lockForUpdate()->firstOrFail();
            $before = $product->toArray();
            $product->update($data);
            $audit->record($request->user(), 'UPDATE', 'productos', $product->getKey(), $before, $product->toArray());

            return $product;
        }, 3);

        return response()->json($product);
    }

    public function destroy(Request $request, Producto $producto, AuditService $audit): JsonResponse
    {
        DB::transaction(function () use ($request, $producto, $audit): void {
            $product = Producto::whereKey($producto->getKey())->lockForUpdate()->firstOrFail();
            $product->update(['activo' => false]);
            $audit->record($request->user(), 'DEACTIVATE', 'productos', $product->getKey(), ['activo' => true], ['activo' => false]);
        }, 3);

        return response()->json(['message' => 'Producto desactivado.']);
    }
}
