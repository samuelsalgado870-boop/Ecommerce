<?php

namespace Tests\Feature;

use App\Exceptions\BusinessConflict;
use App\Models\Producto;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\BackendTestCase;

class InventoryTest extends BackendTestCase
{
    public function test_entry_exit_and_signed_adjustment_record_exact_stock_history(): void
    {
        $actor = $this->userWithRole('gestor_inventario');
        $product = Producto::factory()->create();
        $inventory = app(InventoryService::class);

        $inventory->change($product->getKey(), 'ENTRADA', 5, $actor, 'entry');
        $inventory->change($product->getKey(), 'SALIDA', 2, $actor, 'exit');
        $inventory->change($product->getKey(), 'AJUSTE', -1, $actor, 'adjustment');

        $this->assertSame(2, $product->refresh()->stock);
        $this->assertDatabaseHas('movimientos_inventario', ['clave_operacion' => 'entry', 'stock_anterior' => 0, 'stock_nuevo' => 5]);
        $this->assertDatabaseHas('movimientos_inventario', ['clave_operacion' => 'exit', 'stock_anterior' => 5, 'stock_nuevo' => 3]);
        $this->assertDatabaseHas('movimientos_inventario', ['clave_operacion' => 'adjustment', 'delta_stock' => -1, 'stock_anterior' => 3, 'stock_nuevo' => 2]);
    }

    public function test_insufficient_stock_returns_409_without_partial_movement(): void
    {
        $actor = $this->userWithRole('gestor_inventario');
        $product = $this->productWithStock(1, $actor);
        Sanctum::actingAs($actor, ['api']);

        $this->postJson('/api/productos/'.$product->getKey().'/movimientos', [
            'tipo' => 'SALIDA', 'cantidad' => 2, 'clave_operacion' => (string) Str::uuid(),
        ])->assertConflict();

        $this->assertSame(1, $product->refresh()->stock);
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_repeated_inventory_key_does_not_apply_twice(): void
    {
        $actor = $this->userWithRole('gestor_inventario');
        $product = Producto::factory()->create();
        $inventory = app(InventoryService::class);

        $first = $inventory->change($product->getKey(), 'ENTRADA', 3, $actor, 'same');
        $second = $inventory->change($product->getKey(), 'ENTRADA', 3, $actor, 'same');

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(3, $product->refresh()->stock);
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_outer_transaction_failure_rolls_back_stock_movement_and_audit(): void
    {
        $actor = $this->userWithRole('gestor_inventario');
        $product = Producto::factory()->create();
        try {
            DB::transaction(function () use ($actor, $product): void {
                app(InventoryService::class)->change($product->getKey(), 'ENTRADA', 3, $actor, 'rollback');
                throw new BusinessConflict('Injected failure');
            });
            $this->fail('Expected failure.');
        } catch (BusinessConflict) {
            $this->assertSame(0, $product->refresh()->stock);
            $this->assertDatabaseCount('movimientos_inventario', 0);
            $this->assertDatabaseCount('auditoria', 0);
        }
    }

    public function test_product_update_cannot_change_stock(): void
    {
        $actor = $this->userWithRole('gestor_catalogo');
        $product = Producto::factory()->create();
        Sanctum::actingAs($actor, ['api']);

        $this->patchJson('/api/productos/'.$product->getKey(), ['stock' => 200])
            ->assertUnprocessable()->assertJsonValidationErrors('stock');

        $this->assertSame(0, $product->refresh()->stock);
    }

    public function test_direct_eloquent_stock_write_is_rejected(): void
    {
        $product = Producto::factory()->create();
        $product->stock = 5;

        $this->expectException(\LogicException::class);
        $product->save();
    }

    public function test_audit_insert_failure_rolls_back_inventory_inside_service(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Failure injection uses a SQLite trigger.');
        }
        $actor = $this->userWithRole('gestor_inventario');
        $product = Producto::factory()->create();
        DB::unprepared("CREATE TRIGGER reject_audit BEFORE INSERT ON auditoria BEGIN SELECT RAISE(ABORT, 'injected'); END");
        try {
            app(InventoryService::class)->change($product->getKey(), 'ENTRADA', 3, $actor, 'failed-audit');
            $this->fail('Expected database failure.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame(0, $product->refresh()->stock);
            $this->assertDatabaseCount('movimientos_inventario', 0);
            $this->assertDatabaseCount('auditoria', 0);
        } finally {
            DB::unprepared('DROP TRIGGER reject_audit');
        }
    }

    public function test_product_update_permission_alone_cannot_retire_a_product(): void
    {
        $actor = $this->userWithRole('gestor_catalogo');
        $role = \App\Models\Rol::where('codigo', 'gestor_catalogo')->firstOrFail();
        $permission = DB::table('permisos')->where('codigo', 'productos.delete')->value('id_permiso');
        $role->permisos()->detach($permission);
        $product = Producto::factory()->create();
        Sanctum::actingAs($actor, ['api']);

        $this->patchJson('/api/productos/'.$product->getKey(), ['activo' => false])->assertForbidden();

        $this->assertTrue($product->refresh()->activo);
    }
}
