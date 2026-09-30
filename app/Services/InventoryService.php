<?php

namespace App\Services;

use App\Exceptions\BusinessConflict;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private AuditService $audit) {}

    /**
     * AJUSTE receives a signed delta; ENTRADA/SALIDA receive a positive quantity.
     * A stable operation key makes retries safe and rejects reuse for different input.
     */
    public function change(int $productId, string $type, int $quantity, User $actor, string $key, ?int $orderId = null): MovimientoInventario
    {
        if (! in_array($type, ['ENTRADA', 'SALIDA', 'AJUSTE'], true)
            || $quantity === 0 || abs($quantity) > 1000000
            || ($type !== 'AJUSTE' && $quantity < 0) || trim($key) === '' || strlen($key) > 100) {
            throw ValidationException::withMessages(['cantidad' => 'Movimiento inválido.']);
        }
        $delta = $type === 'SALIDA' ? -$quantity : $quantity;

        return DB::transaction(function () use ($productId, $type, $quantity, $actor, $key, $orderId, $delta): MovimientoInventario {
            $product = Producto::whereKey($productId)->lockForUpdate()->firstOrFail();
            $existing = MovimientoInventario::where('clave_operacion', $key)->first();
            if ($existing) {
                if ((int) $existing->id_producto !== $productId || (int) $existing->delta_stock !== $delta
                    || $existing->tipo !== $type || (int) $existing->id_usuario_aplicacion !== (int) $actor->getKey()
                    || ($existing->id_orden === null ? null : (int) $existing->id_orden) !== $orderId) {
                    throw new BusinessConflict('La clave de operación ya tiene otro movimiento.');
                }

                return $existing;
            }
            $newStock = $product->stock + $delta;
            if ($newStock < 0 || $newStock > 2147483647) {
                throw new BusinessConflict('Stock insuficiente o fuera de rango.');
            }
            $updated = DB::table('productos')->where('id_producto', $productId)
                ->where('version', $product->version)->where('stock', $product->stock)
                ->update(['stock' => $newStock, 'version' => $product->version + 1]);
            if ($updated !== 1) {
                throw new BusinessConflict('El inventario cambió; reintenta la operación.');
            }
            $movement = new MovimientoInventario;
            $movement->forceFill([
                'id_producto' => $productId, 'id_orden' => $orderId, 'tipo' => $type,
                'cantidad' => abs($quantity), 'delta_stock' => $delta,
                'stock_anterior' => $product->stock, 'stock_nuevo' => $newStock,
                'id_usuario_aplicacion' => $actor->getKey(), 'fecha' => now(), 'clave_operacion' => $key,
            ])->save();
            $this->audit->record($actor, 'INVENTORY', 'productos', $productId,
                ['stock' => $product->stock], ['stock' => $newStock, 'delta_stock' => $delta]);

            return $movement;
        }, 3);
    }
}
