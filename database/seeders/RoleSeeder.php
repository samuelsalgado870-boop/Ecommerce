<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->insertOrIgnore([
            ['nombre' => 'CLIENTE', 'descripcion' => 'Compra y consulta sus propios pedidos.', 'codigo' => 'cliente', 'es_sistema' => true, 'activo' => true],
            ['nombre' => 'Superadministrador', 'descripcion' => 'Control total operativo y de seguridad.', 'codigo' => 'superadministrador', 'es_sistema' => true, 'activo' => true],
            ['nombre' => 'Administrador', 'descripcion' => 'Administra la operacion general.', 'codigo' => 'administrador', 'es_sistema' => true, 'activo' => true],
            ['nombre' => 'Gestor de catalogo', 'descripcion' => 'Administra productos y categorias.', 'codigo' => 'gestor_catalogo', 'es_sistema' => true, 'activo' => true],
            ['nombre' => 'Gestor de inventario', 'descripcion' => 'Administra existencias y movimientos.', 'codigo' => 'gestor_inventario', 'es_sistema' => true, 'activo' => true],
            ['nombre' => 'Gestor de pedidos', 'descripcion' => 'Gestiona el ciclo de vida de pedidos.', 'codigo' => 'gestor_pedidos', 'es_sistema' => true, 'activo' => true],
            ['nombre' => 'Soporte', 'descripcion' => 'Consulta clientes y pedidos.', 'codigo' => 'soporte', 'es_sistema' => true, 'activo' => true],
            ['nombre' => 'Marketing', 'descripcion' => 'Administra promociones.', 'codigo' => 'marketing', 'es_sistema' => true, 'activo' => true],
            ['nombre' => 'Finanzas', 'descripcion' => 'Gestiona pagos y reembolsos.', 'codigo' => 'finanzas', 'es_sistema' => true, 'activo' => true],
            ['nombre' => 'Logistica', 'descripcion' => 'Gestiona envios.', 'codigo' => 'logistica', 'es_sistema' => true, 'activo' => true],
            ['nombre' => 'Auditor', 'descripcion' => 'Consulta informacion y auditoria.', 'codigo' => 'auditor', 'es_sistema' => true, 'activo' => true],
            ['nombre' => 'Analista', 'descripcion' => 'Consulta reportes operativos.', 'codigo' => 'analista', 'es_sistema' => true, 'activo' => true],
        ]);
    }
}
