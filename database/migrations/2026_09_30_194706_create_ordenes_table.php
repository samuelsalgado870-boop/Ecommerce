<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ordenes', function (Blueprint $table) {
            $table->integer('id_orden')->autoIncrement()->primary();
            $table->integer('id_usuario');
            $table->timestamp('fecha_orden')->useCurrent();
            $table->string('estado', 20)->default('PENDIENTE');
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('total_calculado', 12, 2)->default(0);

            $table->index('id_usuario', 'idx_ordenes_usuario');
            $table->index('fecha_orden', 'idx_ordenes_fecha');
            $table->index(['id_usuario', 'fecha_orden', 'id_orden'], 'idx_ordenes_usuario_fecha_id');
            $table->index(['fecha_orden', 'id_orden'], 'idx_ordenes_fecha_id');
            $table->foreign('id_usuario', 'fk_ordenes_usuarios')
                ->references('id_usuario')->on('usuarios')->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ordenes ADD CONSTRAINT chk_estado_valido CHECK (estado IN ('PENDIENTE', 'PAGADO', 'ENVIADO', 'CANCELADO'))");
            DB::statement('ALTER TABLE ordenes ADD CONSTRAINT chk_total_no_menor_calculado CHECK (total = total_calculado)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordenes');
    }
};
