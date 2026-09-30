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
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->bigInteger('id_movimiento')->autoIncrement()->primary();
            $table->integer('id_producto');
            $table->integer('id_orden')->nullable();
            $table->string('tipo', 20);
            $table->integer('cantidad');
            $table->integer('delta_stock');
            $table->integer('stock_anterior');
            $table->integer('stock_nuevo');
            $table->integer('id_usuario_aplicacion')->nullable();
            $table->timestamp('fecha')->useCurrent();

            $table->index('id_orden', 'idx_movimientos_orden');
            $table->index(['id_producto', 'fecha', 'id_movimiento'], 'idx_movimientos_producto_fecha_id');
            $table->foreign('id_orden', 'fk_movimientos_orden')
                ->references('id_orden')->on('ordenes')->restrictOnDelete();
            $table->foreign('id_producto', 'fk_movimientos_producto')
                ->references('id_producto')->on('productos')->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE movimientos_inventario ADD CONSTRAINT chk_movimiento_cantidad CHECK (cantidad > 0)');
            DB::statement('ALTER TABLE movimientos_inventario ADD CONSTRAINT chk_movimiento_delta_no_cero CHECK (delta_stock <> 0)');
            DB::statement("ALTER TABLE movimientos_inventario ADD CONSTRAINT chk_movimiento_tipo CHECK (tipo IN ('ENTRADA', 'SALIDA', 'AJUSTE'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
