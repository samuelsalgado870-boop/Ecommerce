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
        Schema::create('auditoria', function (Blueprint $table) {
            $table->bigInteger('id_auditoria')->unsigned()->autoIncrement()->primary();
            $table->integer('id_usuario')->nullable();
            $table->string('accion', 40);
            $table->string('modulo', 40);
            $table->string('entidad', 80);
            $table->string('id_entidad', 100)->nullable();
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();
            $table->string('direccion_ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->char('request_id', 36)->nullable();
            $table->enum('resultado', ['OK', 'ERROR', 'DENEGADO'])->default('OK');
            $table->timestamp('fecha_hora', precision: 6)->useCurrent();

            $table->index(['id_usuario', 'fecha_hora'], 'idx_auditoria_usuario_fecha');
            $table->index(['modulo', 'fecha_hora'], 'idx_auditoria_modulo_fecha');
            $table->index(['entidad', 'id_entidad'], 'idx_auditoria_entidad');
            $table->foreign('id_usuario', 'fk_auditoria_usuario')
                ->references('id_usuario')->on('usuarios')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
