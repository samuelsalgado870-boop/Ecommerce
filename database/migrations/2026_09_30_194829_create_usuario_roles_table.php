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
        Schema::create('usuario_roles', function (Blueprint $table) {
            $table->integer('id_usuario');
            $table->integer('id_rol');
            $table->integer('asignado_por')->nullable();
            $table->timestamp('asignado_en')->useCurrent();
            $table->timestamp('expira_en')->nullable();

            $table->primary(['id_usuario', 'id_rol']);
            $table->index(['id_rol', 'id_usuario'], 'idx_usuario_roles_rol');
            $table->index('expira_en', 'idx_usuario_roles_expira');
            $table->index('asignado_por', 'fk_usuario_roles_asignador');
            $table->foreign('asignado_por', 'fk_usuario_roles_asignador')
                ->references('id_usuario')->on('usuarios')->nullOnDelete();
            $table->foreign('id_rol', 'fk_usuario_roles_rol')
                ->references('id_rol')->on('roles')->restrictOnDelete();
            $table->foreign('id_usuario', 'fk_usuario_roles_usuario')
                ->references('id_usuario')->on('usuarios')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuario_roles');
    }
};
