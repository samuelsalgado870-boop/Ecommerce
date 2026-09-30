<?php

namespace Tests\Feature;

use App\Exceptions\BusinessConflict;
use App\Models\Orden;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\BackendTestCase;

class OrderTest extends BackendTestCase
{
    public function test_order_uses_historical_price_exact_total_and_inventory(): void
    {
        $actor = $this->userWithRole();
        $product = $this->productWithStock(3, $actor, '12.35');

        $order = app(OrderService::class)->create($actor, [['id_producto' => $product->getKey(), 'cantidad' => 2]], (string) Str::uuid());
        $product->update(['precio' => '99.00']);

        $this->assertSame('24.70', $order->total);
        $this->assertSame('12.35', $order->detalles->first()->precio_unitario);
        $this->assertSame(1, $product->refresh()->stock);
        $this->assertDatabaseHas('movimientos_inventario', ['id_orden' => $order->getKey(), 'delta_stock' => -2]);
    }

    public function test_repeated_order_key_returns_same_order_without_double_sale(): void
    {
        $actor = $this->userWithRole();
        $product = $this->productWithStock(2, $actor);
        $items = [['id_producto' => $product->getKey(), 'cantidad' => 1]];
        $key = (string) Str::uuid();
        $service = app(OrderService::class);

        $first = $service->create($actor, $items, $key);
        $second = $service->create($actor, $items, $key);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertDatabaseCount('ordenes', 1);
        $this->assertSame(1, $product->refresh()->stock);
    }

    public function test_order_http_conflict_leaves_no_order_when_stock_is_insufficient(): void
    {
        $actor = $this->userWithRole();
        $product = $this->productWithStock(1, $actor);
        Sanctum::actingAs($actor, ['api']);

        $this->postJson('/api/ordenes', [
            'items' => [['id_producto' => $product->getKey(), 'cantidad' => 2]],
            'clave_idempotencia' => (string) Str::uuid(),
        ])->assertConflict();

        $this->assertDatabaseCount('ordenes', 0);
        $this->assertSame(1, $product->refresh()->stock);
    }

    public function test_failure_after_order_creation_rolls_back_details_stock_and_audit(): void
    {
        $actor = $this->userWithRole();
        $product = $this->productWithStock(2, $actor);
        $beforeAudit = DB::table('auditoria')->count();
        try {
            DB::transaction(function () use ($actor, $product): void {
                app(OrderService::class)->create($actor, [['id_producto' => $product->getKey(), 'cantidad' => 1]], (string) Str::uuid());
                throw new BusinessConflict('Injected failure');
            });
            $this->fail('Expected failure.');
        } catch (BusinessConflict) {
            $this->assertDatabaseCount('ordenes', 0);
            $this->assertDatabaseCount('ordenes_detalle', 0);
            $this->assertSame(2, $product->refresh()->stock);
            $this->assertDatabaseCount('movimientos_inventario', 1);
            $this->assertSame($beforeAudit, DB::table('auditoria')->count());
        }
    }

    public function test_cancellation_restores_inventory_only_once(): void
    {
        $actor = $this->userWithRole();
        $product = $this->productWithStock(2, $actor);
        $service = app(OrderService::class);
        $order = $service->create($actor, [['id_producto' => $product->getKey(), 'cantidad' => 1]], (string) Str::uuid());

        $service->cancel($actor, $order->getKey());
        $service->cancel($actor, $order->getKey());

        $this->assertSame(2, $product->refresh()->stock);
        $this->assertDatabaseHas('ordenes', ['id_orden' => $order->getKey(), 'estado_orden' => 'cancelada']);
        $this->assertSame(1, DB::table('movimientos_inventario')->where('clave_operacion', 'cancel:'.$order->getKey().':'.$product->getKey())->count());
    }

    public function test_order_owned_by_another_user_returns_404(): void
    {
        $owner = $this->userWithRole();
        $product = $this->productWithStock(1, $owner);
        $order = app(OrderService::class)->create($owner, [['id_producto' => $product->getKey(), 'cantidad' => 1]], (string) Str::uuid());
        Sanctum::actingAs($this->userWithRole(), ['api']);

        $this->getJson('/api/ordenes/'.$order->getKey())->assertNotFound();
    }

    public function test_idempotency_key_cannot_be_reused_for_a_different_quantity(): void
    {
        $actor = $this->userWithRole();
        $product = $this->productWithStock(3, $actor);
        $key = (string) Str::uuid();
        app(OrderService::class)->create($actor, [['id_producto' => $product->getKey(), 'cantidad' => 1]], $key);
        Sanctum::actingAs($actor, ['api']);

        $this->postJson('/api/ordenes', ['items' => [['id_producto' => $product->getKey(), 'cantidad' => 2]],
            'clave_idempotencia' => $key])->assertConflict();

        $this->assertSame(2, $product->refresh()->stock);
        $this->assertDatabaseCount('ordenes', 1);
    }

    public function test_second_product_failure_rolls_back_first_sale_and_all_order_rows(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Failure injection uses a SQLite trigger.');
        }
        $actor = $this->userWithRole();
        $first = $this->productWithStock(1, $actor);
        $second = $this->productWithStock(1, $actor);
        $secondId = (int) $second->getKey();
        DB::unprepared("CREATE TRIGGER reject_second_sale BEFORE INSERT ON movimientos_inventario
            WHEN NEW.id_producto = $secondId AND NEW.tipo = 'SALIDA' BEGIN SELECT RAISE(ABORT, 'injected'); END");
        try {
            app(OrderService::class)->create($actor, [
                ['id_producto' => $first->getKey(), 'cantidad' => 1],
                ['id_producto' => $secondId, 'cantidad' => 1],
            ], (string) Str::uuid());
            $this->fail('Expected database failure.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame(1, $first->refresh()->stock);
            $this->assertSame(1, $second->refresh()->stock);
            $this->assertDatabaseCount('ordenes', 0);
            $this->assertDatabaseCount('ordenes_detalle', 0);
            $this->assertDatabaseCount('movimientos_inventario', 2);
        } finally {
            DB::unprepared('DROP TRIGGER reject_second_sale');
        }
    }

    public function test_owner_without_cancel_permission_cannot_restore_inventory(): void
    {
        $actor = $this->userWithRole();
        $product = $this->productWithStock(1, $actor);
        $order = app(OrderService::class)->create($actor, [['id_producto' => $product->getKey(), 'cantidad' => 1]], (string) Str::uuid());
        $role = \App\Models\Rol::where('codigo', 'cliente')->firstOrFail();
        $permission = DB::table('permisos')->where('codigo', 'pedidos.cancel')->value('id_permiso');
        $role->permisos()->detach($permission);
        Sanctum::actingAs($actor, ['api']);

        $this->postJson('/api/ordenes/'.$order->getKey().'/cancelar')->assertForbidden();

        $this->assertSame(0, $product->refresh()->stock);
        $this->assertDatabaseHas('ordenes', ['id_orden' => $order->getKey(), 'estado_orden' => 'pendiente']);
    }
}
