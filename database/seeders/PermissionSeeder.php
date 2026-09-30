<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('permisos')->upsert([
            ['codigo' => 'usuarios.read', 'modulo' => 'usuarios', 'accion' => 'read', 'nombre' => 'Consultar usuarios', 'descripcion' => 'Consultar perfiles no sensibles.', 'activo' => true],
            ['codigo' => 'usuarios.create', 'modulo' => 'usuarios', 'accion' => 'create', 'nombre' => 'Crear usuarios', 'descripcion' => 'Crear cuentas.', 'activo' => true],
            ['codigo' => 'usuarios.update', 'modulo' => 'usuarios', 'accion' => 'update', 'nombre' => 'Actualizar usuarios', 'descripcion' => 'Actualizar datos permitidos.', 'activo' => true],
            ['codigo' => 'usuarios.assign_roles', 'modulo' => 'usuarios', 'accion' => 'assign_roles', 'nombre' => 'Asignar roles', 'descripcion' => 'Asignar y revocar roles.', 'activo' => true],
            ['codigo' => 'productos.read', 'modulo' => 'productos', 'accion' => 'read', 'nombre' => 'Consultar productos', 'descripcion' => 'Consultar catalogo.', 'activo' => true],
            ['codigo' => 'productos.create', 'modulo' => 'productos', 'accion' => 'create', 'nombre' => 'Crear productos', 'descripcion' => 'Crear productos.', 'activo' => true],
            ['codigo' => 'productos.update', 'modulo' => 'productos', 'accion' => 'update', 'nombre' => 'Actualizar productos', 'descripcion' => 'Actualizar ficha comercial.', 'activo' => true],
            ['codigo' => 'productos.delete', 'modulo' => 'productos', 'accion' => 'delete', 'nombre' => 'Eliminar productos', 'descripcion' => 'Retirar productos.', 'activo' => true],
            ['codigo' => 'categorias.read', 'modulo' => 'categorias', 'accion' => 'read', 'nombre' => 'Consultar categorias', 'descripcion' => 'Consultar categorias.', 'activo' => true],
            ['codigo' => 'categorias.manage', 'modulo' => 'categorias', 'accion' => 'manage', 'nombre' => 'Gestionar categorias', 'descripcion' => 'Administrar categorias.', 'activo' => true],
            ['codigo' => 'inventario.read', 'modulo' => 'inventario', 'accion' => 'read', 'nombre' => 'Consultar inventario', 'descripcion' => 'Consultar existencias.', 'activo' => true],
            ['codigo' => 'inventario.update', 'modulo' => 'inventario', 'accion' => 'update', 'nombre' => 'Actualizar inventario', 'descripcion' => 'Registrar entradas y ajustes.', 'activo' => true],
            ['codigo' => 'pedidos.read', 'modulo' => 'pedidos', 'accion' => 'read', 'nombre' => 'Consultar pedidos', 'descripcion' => 'Consultar pedidos autorizados.', 'activo' => true],
            ['codigo' => 'pedidos.create', 'modulo' => 'pedidos', 'accion' => 'create', 'nombre' => 'Crear pedidos', 'descripcion' => 'Crear pedidos.', 'activo' => true],
            ['codigo' => 'pedidos.update', 'modulo' => 'pedidos', 'accion' => 'update', 'nombre' => 'Actualizar pedidos', 'descripcion' => 'Cambiar estados.', 'activo' => true],
            ['codigo' => 'pedidos.cancel', 'modulo' => 'pedidos', 'accion' => 'cancel', 'nombre' => 'Cancelar pedidos', 'descripcion' => 'Cancelar pedidos.', 'activo' => true],
            ['codigo' => 'pagos.read', 'modulo' => 'pagos', 'accion' => 'read', 'nombre' => 'Consultar pagos', 'descripcion' => 'Consultar estados de pago.', 'activo' => true],
            ['codigo' => 'pagos.manage', 'modulo' => 'pagos', 'accion' => 'manage', 'nombre' => 'Gestionar pagos', 'descripcion' => 'Conciliar pagos.', 'activo' => true],
            ['codigo' => 'pagos.refund', 'modulo' => 'pagos', 'accion' => 'refund', 'nombre' => 'Gestionar reembolsos', 'descripcion' => 'Ejecutar reembolsos.', 'activo' => true],
            ['codigo' => 'envios.read', 'modulo' => 'envios', 'accion' => 'read', 'nombre' => 'Consultar envios', 'descripcion' => 'Consultar seguimiento.', 'activo' => true],
            ['codigo' => 'envios.manage', 'modulo' => 'envios', 'accion' => 'manage', 'nombre' => 'Gestionar envios', 'descripcion' => 'Gestionar despachos.', 'activo' => true],
            ['codigo' => 'promociones.read', 'modulo' => 'promociones', 'accion' => 'read', 'nombre' => 'Consultar promociones', 'descripcion' => 'Consultar promociones.', 'activo' => true],
            ['codigo' => 'promociones.manage', 'modulo' => 'promociones', 'accion' => 'manage', 'nombre' => 'Gestionar promociones', 'descripcion' => 'Administrar promociones.', 'activo' => true],
            ['codigo' => 'reportes.read', 'modulo' => 'reportes', 'accion' => 'read', 'nombre' => 'Consultar reportes', 'descripcion' => 'Consultar reportes.', 'activo' => true],
            ['codigo' => 'auditoria.read', 'modulo' => 'auditoria', 'accion' => 'read', 'nombre' => 'Consultar auditoria', 'descripcion' => 'Consultar eventos.', 'activo' => true],
            ['codigo' => 'auditoria.export', 'modulo' => 'auditoria', 'accion' => 'export', 'nombre' => 'Exportar auditoria', 'descripcion' => 'Exportar evidencia.', 'activo' => true],
        ], ['codigo'], ['modulo', 'accion', 'nombre', 'descripcion', 'activo']);
    }
}
