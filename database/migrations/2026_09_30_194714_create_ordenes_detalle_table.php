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
        Schema::create('ordenes_detalle', function (Blueprint $table) {
            $table->integer('id_detalle')->autoIncrement()->primary();
            $table->integer('id_orden');
            $table->integer('id_producto');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 12, 2);

            $table->unique(['id_orden', 'id_producto'], 'uq_detalle_orden_producto');
            $table->index('id_producto', 'idx_detalle_producto');
            $table->foreign('id_orden', 'fk_detalle_orden')
                ->references('id_orden')->on('ordenes')->cascadeOnDelete();
            $table->foreign('id_producto', 'fk_detalle_producto')
                ->references('id_producto')->on('productos')->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ordenes_detalle ADD CONSTRAINT chk_cantidad_positiva CHECK (cantidad > 0)');
            DB::statement('ALTER TABLE ordenes_detalle ADD CONSTRAINT chk_precio_unitario_positivo CHECK (precio_unitario >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordenes_detalle');
    }
};
