<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\BackendTestCase;

class AuthorizationTest extends BackendTestCase
{
    public function test_unauthenticated_profile_returns_401(): void
    {
        $this->getJson('/api/perfil')->assertUnauthorized();
    }

    public function test_customer_cannot_list_users_or_create_products(): void
    {
        Sanctum::actingAs($this->userWithRole(), ['api']);

        $this->getJson('/api/usuarios')->assertForbidden();
        $this->postJson('/api/productos', ['sku' => 'NEW', 'nombre' => 'Producto', 'precio' => '2.00'])->assertForbidden();
        $this->assertDatabaseCount('productos', 0);
    }

    public function test_catalog_permission_allows_product_creation_with_zero_stock(): void
    {
        Sanctum::actingAs($this->userWithRole('gestor_catalogo'), ['api']);

        $this->postJson('/api/productos', ['sku' => 'NEW', 'nombre' => 'Producto', 'precio' => '2.00'])
            ->assertCreated()->assertJsonPath('stock', 0);

        $this->assertDatabaseHas('productos', ['sku' => 'NEW', 'stock' => 0]);
    }

    #[TestWith(['expired'])]
    #[TestWith(['inactive_role'])]
    #[TestWith(['inactive_permission'])]
    public function test_invalid_role_or_permission_does_not_grant_access(string $state): void
    {
        $this->freezeTime();
        $user = $this->userWithRole('gestor_catalogo');
        $role = Rol::where('codigo', 'gestor_catalogo')->firstOrFail();
        if ($state === 'expired') {
            $user->roles()->updateExistingPivot($role->getKey(), ['expira_en' => now()->subSecond()]);
        } elseif ($state === 'inactive_role') {
            DB::table('roles')->where('id_rol', $role->getKey())->update(['activo' => false]);
        } else {
            DB::table('permisos')->where('codigo', 'productos.create')->update(['activo' => false]);
        }
        Sanctum::actingAs($user, ['api']);

        $this->postJson('/api/productos', ['sku' => 'NEW', 'nombre' => 'Producto', 'precio' => '2.00'])->assertForbidden();
        $this->assertDatabaseCount('productos', 0);
    }

    public function test_user_can_update_own_profile(): void
    {
        $user = $this->userWithRole();
        Sanctum::actingAs($user, ['api']);

        $this->patchJson('/api/usuarios/'.$user->getKey(), ['nombre' => 'Nuevo nombre'])
            ->assertOk()->assertJsonPath('data.nombre', 'Nuevo nombre');

        $this->assertDatabaseHas('usuarios', ['id_usuario' => $user->getKey(), 'nombre' => 'Nuevo nombre']);
    }

    public function test_user_cannot_update_another_profile(): void
    {
        $user = $this->userWithRole();
        $other = User::factory()->create(['nombre' => 'Original']);
        Sanctum::actingAs($user, ['api']);

        $this->patchJson('/api/usuarios/'.$other->getKey(), ['nombre' => 'Ataque'])->assertForbidden();

        $this->assertDatabaseHas('usuarios', ['id_usuario' => $other->getKey(), 'nombre' => 'Original']);
    }

    public function test_profile_rejects_role_and_activation_mass_assignment(): void
    {
        $user = $this->userWithRole();
        Sanctum::actingAs($user, ['api']);

        $this->patchJson('/api/usuarios/'.$user->getKey(), ['id_rol' => 3, 'activo' => false])
            ->assertUnprocessable()->assertJsonValidationErrors(['id_rol', 'activo']);

        $this->assertTrue($user->refresh()->activo);
        $this->assertSame(['cliente'], $user->roles()->pluck('codigo')->all());
    }

    public function test_role_assigner_cannot_assign_to_self_or_delegate_superadmin(): void
    {
        $admin = $this->userWithRole('administrador');
        $target = $this->userWithRole();
        $super = Rol::where('codigo', 'superadministrador')->firstOrFail();
        Sanctum::actingAs($admin, ['api']);

        $this->putJson('/api/usuarios/'.$admin->getKey().'/roles/'.$super->getKey())->assertForbidden();
        $this->putJson('/api/usuarios/'.$target->getKey().'/roles/'.$super->getKey())->assertForbidden();

        $this->assertFalse($target->roles()->whereKey($super->getKey())->exists());
        $this->assertFalse($admin->roles()->whereKey($super->getKey())->exists());
    }

    public function test_customer_cannot_assign_roles(): void
    {
        $user = $this->userWithRole();
        $role = Rol::where('codigo', 'administrador')->firstOrFail();
        Sanctum::actingAs($user, ['api']);

        $this->putJson('/api/usuarios/'.$user->getKey().'/roles/'.$role->getKey())->assertForbidden();
    }

    public function test_superadmin_can_delegate_and_revoke_role_with_expiration_and_actor(): void
    {
        $this->freezeTime();
        $actor = $this->userWithRole('superadministrador');
        $target = $this->userWithRole();
        $role = Rol::where('codigo', 'gestor_catalogo')->firstOrFail();
        Sanctum::actingAs($actor, ['api']);
        $url = '/api/usuarios/'.$target->getKey().'/roles/'.$role->getKey();

        $this->putJson($url, ['expira_en' => now()->addDay()->toDateTimeString()])->assertOk();
        $this->assertDatabaseHas('usuario_roles', [
            'id_usuario' => $target->getKey(), 'id_rol' => $role->getKey(), 'asignado_por' => $actor->getKey(),
        ]);
        $this->deleteJson($url)->assertOk();
        $this->assertDatabaseMissing('usuario_roles', ['id_usuario' => $target->getKey(), 'id_rol' => $role->getKey()]);
    }

    public function test_admin_cannot_take_over_superadmin_profile(): void
    {
        $admin = $this->userWithRole('administrador');
        $super = $this->userWithRole('superadministrador');
        Sanctum::actingAs($admin, ['api']);

        $this->patchJson('/api/usuarios/'.$super->getKey(), ['email' => 'takeover@example.test'])->assertForbidden();

        $this->assertDatabaseHas('usuarios', ['id_usuario' => $super->getKey(), 'email' => $super->email]);
    }

    public function test_deactivation_preserves_user_and_revokes_all_api_tokens(): void
    {
        $admin = $this->userWithRole('administrador');
        $target = $this->userWithRole();
        $target->createToken('old', ['api']);
        Sanctum::actingAs($admin, ['api']);

        $this->deleteJson('/api/usuarios/'.$target->getKey())->assertOk();

        $this->assertDatabaseHas('usuarios', ['id_usuario' => $target->getKey(), 'activo' => false]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $target->getKey()]);
    }

    public function test_limited_role_assigner_cannot_delegate_permissions_it_does_not_have(): void
    {
        $actor = $this->userWithRole();
        $target = $this->userWithRole();
        $customer = Rol::where('codigo', 'cliente')->firstOrFail();
        $permission = DB::table('permisos')->where('codigo', 'usuarios.assign_roles')->value('id_permiso');
        $customer->permisos()->attach($permission);
        $catalog = Rol::where('codigo', 'gestor_catalogo')->firstOrFail();
        Sanctum::actingAs($actor, ['api']);

        $this->putJson('/api/usuarios/'.$target->getKey().'/roles/'.$catalog->getKey())->assertForbidden();

        $this->assertDatabaseMissing('usuario_roles', ['id_usuario' => $target->getKey(), 'id_rol' => $catalog->getKey()]);
    }
}
