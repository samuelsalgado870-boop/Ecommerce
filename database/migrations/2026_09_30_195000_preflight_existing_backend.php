<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Migración compatible con MySQL o SQLite; no transformar otro motor automáticamente.');
        }
        if (DB::getDriverName() === 'mysql') {
            $version = DB::selectOne('SELECT VERSION() AS version')->version;
            if (str_contains($version, 'MariaDB') || version_compare($version, '8.0.16', '<')) {
                throw new RuntimeException('Se requiere MySQL >= 8.0.16 con CHECK constraints aplicados.');
            }
            $triggers = DB::select("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE IN ('usuarios','usuario_roles','productos','ordenes','ordenes_detalle')");
            if ($triggers !== []) {
                throw new RuntimeException('Triggers externos requieren revisión; migración detenida antes de transformar datos.');
            }
            $engines = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('usuarios','usuario_roles','roles','rol_permisos','permisos','productos','ordenes','ordenes_detalle','movimientos_inventario','auditoria','auditoria_cambios') AND ENGINE <> 'InnoDB'");
            if ($engines !== []) {
                throw new RuntimeException('Las tablas transaccionales deben utilizar InnoDB.');
            }
        }
        if (DB::table('usuarios')->selectRaw('LOWER(TRIM(email)) AS normalized')->groupByRaw('LOWER(TRIM(email))')
            ->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Hay emails duplicados tras normalización; conciliar antes de migrar.');
        }
        foreach (['movimientos_inventario', 'auditoria_cambios'] as $table) {
            $orphans = DB::table($table.' as h')->whereNotNull('h.id_usuario_aplicacion')
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('usuarios as u')->whereColumn('u.id_usuario', 'h.id_usuario_aplicacion'))->exists();
            if ($orphans) {
                throw new RuntimeException('Hay referencias de usuario huérfanas en '.$table.'; no se descartaron datos.');
            }
        }
        if (DB::table('productos')->where('stock', '<', 0)->orWhere('precio', '<', 0)->orWhere('version', '<=', 0)->exists()
            || DB::table('ordenes_detalle')->where('cantidad', '<=', 0)->orWhere('precio_unitario', '<', 0)->exists()) {
            throw new RuntimeException('Hay productos o detalles inválidos; conciliar antes de migrar.');
        }
        if (DB::table('movimientos_inventario')->whereRaw('cantidad <= 0 OR delta_stock = 0 OR stock_anterior < 0 OR stock_nuevo < 0 OR stock_nuevo <> stock_anterior + delta_stock OR cantidad <> ABS(delta_stock)')
            ->orWhereNotIn('tipo', ['ENTRADA', 'SALIDA', 'AJUSTE'])
            ->orWhereRaw("(tipo = 'ENTRADA' AND delta_stock < 0) OR (tipo = 'SALIDA' AND delta_stock > 0)")->exists()) {
            throw new RuntimeException('Movimientos históricos inválidos; conciliar antes de migrar.');
        }
        if (DB::table('productos as p')->whereRaw(
            'p.stock <> (SELECT COALESCE(SUM(m.delta_stock), 0) FROM movimientos_inventario m WHERE m.id_producto = p.id_producto)'
        )->exists()) {
            throw new RuntimeException('Stock histórico sin saldo conciliado. Registrar apertura documentada antes de migrar.');
        }
        if (Schema::hasColumn('ordenes', 'estado')
            && DB::table('ordenes')->whereNotIn('estado', ['PENDIENTE', 'PAGADO', 'ENVIADO', 'CANCELADO'])->exists()) {
            throw new RuntimeException('Estado histórico desconocido; no se adivinó su significado.');
        }
        if (DB::table('ordenes as o')->whereRaw(
            'o.total < 0 OR o.total <> (SELECT COALESCE(SUM(d.cantidad * d.precio_unitario), 0) FROM ordenes_detalle d WHERE d.id_orden = o.id_orden)'
        )->exists() || (Schema::hasColumn('ordenes', 'total_calculado')
            && DB::table('ordenes')->whereColumn('total', '<>', 'total_calculado')->exists())) {
            throw new RuntimeException('Totales históricos sin conciliar; no se reescribieron importes.');
        }
    }

    public function down(): void
    {
        // Read-only preflight has no schema or data changes to reverse.
    }
};
