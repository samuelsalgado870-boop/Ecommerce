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
        Schema::create('permisos', function (Blueprint $table) {
            $table->bigInteger('id_permiso')->unsigned()->autoIncrement()->primary();
            $table->string('codigo', 100)->unique('uq_permisos_codigo');
            $table->string('modulo', 40);
            $table->string('accion', 30);
            $table->string('nombre', 120);
            $table->string('descripcion', 500);
            $table->boolean('activo')->default(true);

            $table->unique(['modulo', 'accion'], 'uq_permisos_modulo_accion');
            $table->index(['modulo', 'activo'], 'idx_permisos_modulo_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permisos');
    }
};
