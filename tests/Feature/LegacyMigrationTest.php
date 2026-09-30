<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyMigrationTest extends TestCase
{
    private function onLegacySchema(callable $test): void
    {
        $previous = config('database.default');
        config(['database.connections.legacy_fixture' => config('database.connections.sqlite')]);
        config(['database.connections.legacy_fixture.database' => ':memory:', 'database.default' => 'legacy_fixture']);
        DB::purge('legacy_fixture');
        try {
            foreach (glob(database_path('migrations/*.php')) as $path) {
                if (basename($path) < '2026_09_30_195000') {
                    (require $path)->up();
                }
            }
            $this->seed(RoleSeeder::class);
            $test();
        } finally {
            DB::purge('legacy_fixture');
            config(['database.default' => $previous]);
        }
    }

    public function test_existing_identity_and_role_are_preserved_while_legacy_users_are_archived(): void
    {
        $this->onLegacySchema(function (): void {
            $role = DB::table('roles')->where('codigo', 'cliente')->value('id_rol');
            $user = User::factory()->create(['id_rol' => $role]);
            DB::table('users')->insert(['name' => 'Legacy', 'email' => 'legacy@example.test', 'password' => 'historical-hash']);

            (require database_path('migrations/2026_09_30_200000_unify_identity_and_role_assignments.php'))->up();

            $this->assertFalse(Schema::hasColumn('usuarios', 'id_rol'));
            $this->assertDatabaseHas('usuario_roles', ['id_usuario' => $user->getKey(), 'id_rol' => $role]);
            $this->assertDatabaseHas('users_legacy', ['email' => 'legacy@example.test']);
            $this->assertDatabaseHas('usuarios', ['id_usuario' => $user->getKey(), 'email' => $user->email]);
        });
    }

    public function test_expired_assignment_and_additional_valid_role_are_not_overwritten(): void
    {
        $this->freezeTime();
        $this->onLegacySchema(function (): void {
            $customer = DB::table('roles')->where('codigo', 'cliente')->value('id_rol');
            $catalog = DB::table('roles')->where('codigo', 'gestor_catalogo')->value('id_rol');
            $user = User::factory()->create(['id_rol' => $customer]);
            DB::table('usuario_roles')->insert([
                ['id_usuario' => $user->getKey(), 'id_rol' => $customer, 'expira_en' => now()->subDay(), 'asignado_en' => now()],
                ['id_usuario' => $user->getKey(), 'id_rol' => $catalog, 'expira_en' => null, 'asignado_en' => now()],
            ]);

            (require database_path('migrations/2026_09_30_200000_unify_identity_and_role_assignments.php'))->up();

            $this->assertDatabaseHas('usuario_roles', ['id_usuario' => $user->getKey(), 'id_rol' => $customer, 'expira_en' => now()->subDay()->toDateTimeString()]);
            $this->assertSame(2, DB::table('usuario_roles')->where('id_usuario', $user->getKey())->count());
            $this->assertFalse($user->tieneRol('cliente'));
            $this->assertTrue($user->tieneRol('gestor_catalogo'));
        });
    }

    public function test_conflicting_roles_stop_migration_without_granting_extra_privileges(): void
    {
        $this->onLegacySchema(function (): void {
            $customer = DB::table('roles')->where('codigo', 'cliente')->value('id_rol');
            $admin = DB::table('roles')->where('codigo', 'administrador')->value('id_rol');
            $user = User::factory()->create(['id_rol' => $customer]);
            DB::table('usuario_roles')->insert(['id_usuario' => $user->getKey(), 'id_rol' => $admin, 'asignado_en' => now()]);
            try {
                (require database_path('migrations/2026_09_30_200000_unify_identity_and_role_assignments.php'))->up();
                $this->fail('Expected role conflict.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('Roles contradictorios', $exception->getMessage());
                $this->assertTrue(Schema::hasColumn('usuarios', 'id_rol'));
                $this->assertDatabaseMissing('usuario_roles', ['id_usuario' => $user->getKey(), 'id_rol' => $customer]);
            }
        });
    }
}