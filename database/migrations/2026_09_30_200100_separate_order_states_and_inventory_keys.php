<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $triggers = DB::select("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE IN ('productos', 'ordenes', 'ordenes_detalle')");
            if ($triggers !== []) {
                throw new RuntimeException('Hay triggers externos. Revisarlos y retirar la lógica duplicada antes de esta migración.');
            }
        }
        $badTotals = DB::table('ordenes as o')->whereRaw(
            'o.total <> (SELECT COALESCE(SUM(d.cantidad * d.precio_unitario), 0) FROM ordenes_detalle d WHERE d.id_orden = o.id_orden)'
        )->exists();
        if ($badTotals || DB::table('ordenes')->whereColumn('total', '<>', 'total_calculado')->exists()) {
            throw new RuntimeException('Totales históricos inconsistentes: conciliar antes de migrar; no se reescribieron importes.');
        }
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ordenes DROP CHECK chk_estado_valido');
            DB::statement('ALTER TABLE ordenes DROP CHECK chk_total_no_menor_calculado');
        }
        Schema::table('productos', fn (Blueprint $table) => $table->boolean('activo')->default(true));
        Schema::table('ordenes', function (Blueprint $table): void {
            $table->string('estado_orden', 20)->default('pendiente');
            $table->string('estado_pago', 30)->default('pendiente');
            $table->string('estado_envio', 20)->default('pendiente');
            $table->char('moneda', 3)->nullable();
            $table->uuid('clave_idempotencia')->nullable();
            $table->char('huella_solicitud', 64)->nullable();
            $table->unique(['id_usuario', 'clave_idempotencia'], 'uq_orden_usuario_idempotencia');
        });
        DB::table('ordenes')->where('estado', 'PAGADO')->update(['estado_orden' => 'confirmada', 'estado_pago' => 'aprobado']);
        DB::table('ordenes')->where('estado', 'ENVIADO')->update(['estado_orden' => 'procesando', 'estado_envio' => 'despachado']);
        DB::table('ordenes')->where('estado', 'CANCELADO')->update(['estado_orden' => 'cancelada']);
        // Currency stays NULL for legacy orders: no evidence establishes their currency.
        Schema::table('ordenes', function (Blueprint $table): void {
            $table->dropColumn(['estado', 'total_calculado']);
        });
        Schema::table('movimientos_inventario', function (Blueprint $table): void {
            $table->string('clave_operacion', 100)->nullable()->unique('uq_movimiento_operacion');
            $table->foreign('id_usuario_aplicacion', 'fk_movimientos_usuario')
                ->references('id_usuario')->on('usuarios')->restrictOnDelete();
        });
        Schema::table('auditoria_cambios', function (Blueprint $table): void {
            $table->foreign('id_usuario_aplicacion', 'fk_cambios_usuario')
                ->references('id_usuario')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('ordenes')->exists()) {
            throw new RuntimeException('Estados separados no se pueden recombinar sin perder información. Usar una corrección hacia adelante.');
        }
        Schema::table('auditoria_cambios', fn (Blueprint $table) => $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['id_usuario_aplicacion'] : 'fk_cambios_usuario'));
        Schema::table('movimientos_inventario', function (Blueprint $table): void {
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['id_usuario_aplicacion'] : 'fk_movimientos_usuario');
            $table->dropUnique('uq_movimiento_operacion');
            $table->dropColumn('clave_operacion');
        });
        Schema::table('ordenes', function (Blueprint $table): void {
            $table->dropUnique('uq_orden_usuario_idempotencia');
            $table->dropColumn(['estado_orden', 'estado_pago', 'estado_envio', 'moneda', 'clave_idempotencia', 'huella_solicitud']);
            $table->string('estado', 20)->default('PENDIENTE');
            $table->decimal('total_calculado', 12, 2)->default(0);
        });
        Schema::table('productos', fn (Blueprint $table) => $table->dropColumn('activo'));
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ordenes ADD CONSTRAINT chk_estado_valido CHECK (estado IN ('PENDIENTE', 'PAGADO', 'ENVIADO', 'CANCELADO'))");
            DB::statement('ALTER TABLE ordenes ADD CONSTRAINT chk_total_no_menor_calculado CHECK (total = total_calculado)');
        }
    }
};
