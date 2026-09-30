<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auditoria_cambios', function (Blueprint $table) {
            $table->bigInteger('id_auditoria')->autoIncrement()->primary();
            $table->string('nombre_tabla', 50);
            $table->string('operacion', 10);
            $table->string('usuario_bd', 288)->nullable();
            $table->integer('id_usuario_aplicacion')->nullable();
            $table->string('ip_origen', 45)->nullable();
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->timestamp('fecha')->useCurrent();

            $table->index(['fecha', 'id_auditoria'], 'idx_auditoria_fecha_id');
            $table->index(['id_usuario_aplicacion', 'fecha', 'id_auditoria'], 'idx_auditoria_usuario_fecha_id');
            $table->index(['nombre_tabla', 'fecha', 'id_auditoria'], 'idx_auditoria_tabla_fecha_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditoria_cambios');
    }
};
