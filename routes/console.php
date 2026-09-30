<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Inspiring;

Artisan::command('backend:preflight', function (): int {
    $problems = [];
    if (DB::getDriverName() === 'mysql') {
        $version = DB::selectOne('SELECT VERSION() AS version')->version;
        if (str_contains($version, 'MariaDB') || version_compare($version, '8.0.16', '<')) {
            $problems[] = 'Se requiere MySQL >= 8.0.16 para CHECK constraints aplicados.';
        }
        $triggers = DB::select("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE IN ('productos','ordenes','ordenes_detalle')");
        if ($triggers !== []) {
            $problems[] = 'Triggers externos deben revisarse antes de migrar: '.implode(', ', array_column($triggers, 'TRIGGER_NAME'));
        }
    }
    foreach (['users', 'users_legacy', 'sesiones_usuario', 'sesiones_usuario_legacy'] as $table) {
        if (Schema::hasTable($table) && DB::table($table)->exists()) {
            $this->warn($table.': contiene datos históricos; conservar backup y conciliar identidad fuera de la aplicación.');
        }
    }
    if (Schema::hasTable('usuarios') && Schema::hasColumn('usuarios', 'id_rol') && Schema::hasTable('usuario_roles')) {
        $conflicts = DB::table('usuarios as u')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('usuario_roles as r')->whereColumn('r.id_usuario', 'u.id_usuario'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('usuario_roles as r')
                ->whereColumn('r.id_usuario', 'u.id_usuario')->whereColumn('r.id_rol', 'u.id_rol'))->count();
        if ($conflicts > 0) {
            $problems[] = $conflicts.' identidades con roles contradictorios.';
        }
    }
    if (Schema::hasTable('ordenes') && Schema::hasTable('ordenes_detalle')) {
        $bad = DB::table('ordenes as o')->whereRaw('o.total <> (SELECT COALESCE(SUM(d.cantidad*d.precio_unitario),0) FROM ordenes_detalle d WHERE d.id_orden=o.id_orden)')->count();
        if ($bad > 0) {
            $problems[] = $bad.' órdenes con importes sin conciliar.';
        }
    }
    if (Schema::hasTable('movimientos_inventario') && Schema::hasTable('productos')) {
        $bad = DB::table('productos as p')->whereRaw('p.stock <> (SELECT COALESCE(SUM(m.delta_stock),0) FROM movimientos_inventario m WHERE m.id_producto=p.id_producto)')->count();
        if ($bad > 0) {
            $problems[] = $bad.' productos con historial de stock incompleto o inconsistente.';
        }
    }
    foreach ($problems as $problem) {
        $this->error($problem);
    }
    if ($problems === []) {
        $this->info('No se detectaron conflictos en las comprobaciones disponibles.');
    }

    return $problems === [] ? 0 : 1;
})->purpose('Revisión de solo lectura antes de migrar una base existente.');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
