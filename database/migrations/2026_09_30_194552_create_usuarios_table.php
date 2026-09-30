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
        Schema::create('usuarios', function (Blueprint $table) {
            $table->integer('id_usuario')->autoIncrement()->primary();
            $table->integer('id_rol');
            $table->string('nombre', 100);
            $table->string('email', 150)->unique('uq_usuarios_email');
            $table->string('password_hash');
            $table->boolean('activo')->default(true);
            $table->integer('intentos_fallidos')->default(0);
            $table->timestamp('bloqueado_hasta')->nullable();
            $table->timestamp('ultimo_acceso')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();

            $table->index('id_rol', 'idx_usuarios_rol');
            $table->foreign('id_rol', 'fk_usuarios_roles')
                ->references('id_rol')->on('roles')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
