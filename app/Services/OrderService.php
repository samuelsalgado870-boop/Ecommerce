<?php

namespace App\Services;

use App\Exceptions\BusinessConflict;
use App\Models\Orden;
use App\Models\OrdenDetalle;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private InventoryService $inventory, private AuditService $audit) {}

    /** @param array<int, array{id_producto: int, cantidad: int}> $items */
    public function create(User $actor, array $items, string $key): Orden
    {
        if ($items === [] || count($items) > 100) {
            throw ValidationException::withMessages(['items' => 'Se requieren entre 1 y 100 productos.']);
        }
        $canonical = [];
        foreach ($items as $item) {
            $productId = filter_var($item['id_producto'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT);
            if ($productId === false || $productId < 1 || $quantity === false || $quantity < 1) {
                throw ValidationException::withMessages(['items' => 'Productos o cantidades inválidos.']);
            }
            $canonical[] = ['id_producto' => $productId, 'cantidad' => $quantity];
        }
        $items = $canonical;
        usort($items, fn (array $a, array $b) => $a['id_producto'] <=> $b['id_producto']);
        $ids = [];
        foreach ($items as $item) {
            if ($item['cantidad'] < 1 || $item['cantidad'] > 1000000 || in_array($item['id_producto'], $ids, true)) {
                throw ValidationException::withMessages(['items' => 'Cantidades inválidas o productos duplicados.']);
            }
            $ids[] = $item['id_producto'];
        }
        $fingerprint = hash('sha256', json_encode($items, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $items, $key, $fingerprint): Orden {
            $user = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($user->disponible() && $user->tienePermiso('pedidos.create'), 403);
            $existing = Orden::where('id_usuario', $user->getKey())->where('clave_idempotencia', $key)->first();
            if ($existing) {
                if ($existing->huella_solicitud !== $fingerprint) {
                    throw new BusinessConflict('La clave de idempotencia ya tiene otra solicitud.');
                }

                return $existing->load('detalles');
            }
            $products = [];
            $total = 0;
            foreach ($items as $item) {
                $product = Producto::whereKey($item['id_producto'])->lockForUpdate()->firstOrFail();
                if (! $product->activo || $product->stock < $item['cantidad']) {
                    throw new BusinessConflict('Producto no disponible o stock insuficiente.');
                }
                $total += Money::cents($product->precio) * $item['cantidad'];
                Money::decimal($total);
                $products[$product->getKey()] = $product;
            }
            $order = new Orden;
            $order->forceFill([
                'id_usuario' => $user->getKey(), 'estado_orden' => 'pendiente', 'estado_pago' => 'pendiente',
                'estado_envio' => 'pendiente', 'total' => Money::decimal($total), 'moneda' => config('commerce.currency'),
                'fecha_orden' => now(), 'clave_idempotencia' => $key, 'huella_solicitud' => $fingerprint,
            ])->save();
            foreach ($items as $item) {
                $product = $products[$item['id_producto']];
                $detail = new OrdenDetalle;
                $detail->forceFill([
                    'id_orden' => $order->getKey(), 'id_producto' => $product->getKey(),
                    'cantidad' => $item['cantidad'], 'precio_unitario' => $product->precio,
                ])->save();
                $this->inventory->change($product->getKey(), 'SALIDA', $item['cantidad'], $user,
                    'order:'.$order->getKey().':'.$product->getKey(), $order->getKey());
            }
            $this->audit->record($user, 'CREATE', 'ordenes', $order->getKey(), [], ['total' => $order->total]);

            return $order->load('detalles');
        }, 3);
    }

    public function cancel(User $actor, int $orderId): Orden
    {
        return DB::transaction(function () use ($actor, $orderId): Orden {
            $order = Orden::whereKey($orderId)->lockForUpdate()->firstOrFail();
            $own = (int) $order->id_usuario === (int) $actor->getKey();
            abort_unless($actor->tienePermiso('pedidos.cancel') && ($own || $actor->tienePermiso('pedidos.update')), 403);
            if ($order->estado_orden === 'cancelada') {
                return $order->load('detalles');
            }
            if ($order->estado_orden !== 'pendiente' || $order->estado_pago !== 'pendiente'
                || $order->estado_envio !== 'pendiente') {
                throw new BusinessConflict('La orden requiere conciliación de pago o envío antes de cancelarse.');
            }
            $details = $order->detalles()->orderBy('id_producto')->get();
            foreach ($details as $detail) {
                $sale = DB::table('movimientos_inventario')->where('id_orden', $order->getKey())
                    ->where('id_producto', $detail->id_producto)->where('tipo', 'SALIDA')
                    ->sum('cantidad');
                if ((int) $sale !== $detail->cantidad) {
                    throw new BusinessConflict('No se puede reponer una orden sin salida de inventario conciliada.');
                }
                $this->inventory->change($detail->id_producto, 'ENTRADA', $detail->cantidad, $actor,
                    'cancel:'.$order->getKey().':'.$detail->id_producto, $order->getKey());
            }
            $order->estado_orden = 'cancelada';
            $order->save();
            $this->audit->record($actor, 'CANCEL', 'ordenes', $order->getKey(),
                ['estado_orden' => 'pendiente'], ['estado_orden' => 'cancelada']);

            return $order->load('detalles');
        }, 3);
    }
}
