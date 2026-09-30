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
        Schema::create('productos', function (Blueprint $table) {
            $table->integer('id_producto')->autoIncrement()->primary();
            $table->string('sku', 50)->unique('uq_productos_sku');
            $table->string('nombre', 150);
            $table->decimal('precio', 12, 2);
            $table->integer('stock')->default(0);
            $table->integer('version')->default(1);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE productos ADD CONSTRAINT chk_precio_positivo CHECK (precio >= 0)');
            DB::statement('ALTER TABLE productos ADD CONSTRAINT chk_stock_positivo CHECK (stock >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
