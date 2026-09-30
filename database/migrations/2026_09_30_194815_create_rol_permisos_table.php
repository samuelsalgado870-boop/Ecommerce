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
        Schema::create('rol_permisos', function (Blueprint $table) {
            $table->integer('id_rol');
            $table->bigInteger('id_permiso')->unsigned();
            $table->timestamp('asignado_en')->useCurrent();

            $table->primary(['id_rol', 'id_permiso']);
            $table->index(['id_permiso', 'id_rol'], 'idx_rol_permisos_permiso');
            $table->foreign('id_rol', 'fk_rol_permisos_rol')
                ->references('id_rol')->on('roles')->cascadeOnDelete();
            $table->foreign('id_permiso', 'fk_rol_permisos_permiso')
                ->references('id_permiso')->on('permisos')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rol_permisos');
    }
};
