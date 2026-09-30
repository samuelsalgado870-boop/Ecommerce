<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $all = DB::table('permisos')->pluck('id_permiso', 'codigo');
        $sets = [
            'cliente' => ['pedidos.create', 'pedidos.cancel'],
            'superadministrador' => $all->keys()->all(),
            'administrador' => $all->keys()->reject(fn (string $code) => $code === 'usuarios.assign_superadmin')->all(),
            'gestor_catalogo' => ['productos.read', 'productos.create', 'productos.update', 'productos.delete', 'categorias.read', 'categorias.manage'],
            'gestor_inventario' => ['inventario.read', 'inventario.update', 'productos.read'],
            'gestor_pedidos' => ['pedidos.read', 'pedidos.update', 'pedidos.cancel'],
            'soporte' => ['usuarios.read', 'pedidos.read'],
            'marketing' => ['promociones.read', 'promociones.manage'],
            'finanzas' => ['pagos.read', 'pagos.manage', 'pagos.refund'],
            'logistica' => ['envios.read', 'envios.manage'],
            'auditor' => ['auditoria.read', 'auditoria.export', 'reportes.read'],
            'analista' => ['reportes.read'],
        ];
        foreach ($sets as $code => $permissions) {
            $roleId = DB::table('roles')->where('codigo', $code)->value('id_rol');
            if (DB::table('rol_permisos')->where('id_rol', $roleId)->exists()) {
                continue;
            }
            foreach ($permissions as $permission) {
                DB::table('rol_permisos')->insertOrIgnore([
                    'id_rol' => $roleId, 'id_permiso' => $all[$permission], 'asignado_en' => now(),
                ]);
            }
        }
    }
}
