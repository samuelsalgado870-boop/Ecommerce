<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Services\AuditService;
use App\Services\OrderService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\BackendTestCase;

class DatabaseTest extends BackendTestCase
{
    public function test_seeded_installation_uses_one_identity_and_one_role_source(): void
    {
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasColumn('usuarios', 'id_rol'));
        $this->assertFalse(Schema::hasTable('sesiones_usuario'));
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
        $this->assertDatabaseHas('roles', ['codigo' => 'superadministrador']);
        $this->assertDatabaseHas('permisos', ['codigo' => 'productos.create']);
        $this->assertDatabaseCount('usuarios', 0);
        $before = DB::table('rol_permisos')->count();

        $this->seed();

        $this->assertSame($before, DB::table('rol_permisos')->count());
    }

    #[TestWith(['precio', '-1.00'])]
    #[TestWith(['stock', -1])]
    public function test_database_rejects_negative_product_values(string $field, string|int $value): void
    {
        $product = Producto::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('productos')->where('id_producto', $product->getKey())->update([$field => $value]);
    }

    public function test_foreign_key_prevents_removing_a_product_with_order_history(): void
    {
        $actor = $this->userWithRole();
        $product = $this->productWithStock(1, $actor);
        app(OrderService::class)->create($actor, [['id_producto' => $product->getKey(), 'cantidad' => 1]], (string) Str::uuid());

        $this->expectException(QueryException::class);
        DB::table('productos')->where('id_producto', $product->getKey())->delete();
    }

    public function test_inventory_constraint_rejects_inconsistent_stock_equation(): void
    {
        $actor = $this->userWithRole();
        $product = $this->productWithStock(1, $actor);

        $this->expectException(QueryException::class);
        DB::table('movimientos_inventario')->where('id_producto', $product->getKey())->update(['stock_nuevo' => 100]);
    }

    public function test_audit_drops_secrets_and_nested_sensitive_fields(): void
    {
        $actor = $this->userWithRole();
        app(AuditService::class)->record($actor, 'UPDATE', 'usuarios', $actor->getKey(), [], [
            'activo' => true, 'password_hash' => 'secret', 'token' => 'secret',
            'authorization' => 'Bearer secret', 'nested' => ['password' => 'secret'],
            'roles' => ['codigo' => 'cliente', 'token' => 'secret'],
        ]);

        $record = DB::table('auditoria')->first();
        $this->assertSame(['activo' => true, 'roles' => ['codigo' => 'cliente']], json_decode($record->valores_nuevos, true));
        $this->assertStringNotContainsString('secret', $record->valores_nuevos);
        $this->assertDatabaseCount('auditoria_cambios', 1);
    }

    public function test_reseeding_does_not_reactivate_disabled_roles_or_permissions(): void
    {
        DB::table('roles')->where('codigo', 'gestor_catalogo')->update(['activo' => false]);
        DB::table('permisos')->where('codigo', 'productos.create')->update(['activo' => false]);

        $this->seed();

        $this->assertDatabaseHas('roles', ['codigo' => 'gestor_catalogo', 'activo' => false]);
        $this->assertDatabaseHas('permisos', ['codigo' => 'productos.create', 'activo' => false]);
    }

    public function test_inactive_products_are_not_visible_publicly(): void
    {
        $product = Producto::factory()->create(['activo' => false]);

        $this->getJson('/api/productos/'.$product->getKey())->assertNotFound();
    }
}
