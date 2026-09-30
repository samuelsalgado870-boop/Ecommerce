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
        Schema::create('roles', function (Blueprint $table) {
            $table->integer('id_rol')->autoIncrement()->primary();
            $table->string('nombre', 50)->unique('uq_roles_nombre');
            $table->string('descripcion', 500);
            $table->string('codigo', 50)->unique('uq_roles_codigo');
            $table->boolean('es_sistema')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent()->useCurrentOnUpdate();

            $table->index('activo', 'idx_roles_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
