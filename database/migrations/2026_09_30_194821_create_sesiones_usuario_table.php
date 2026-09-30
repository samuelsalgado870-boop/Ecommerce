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
        Schema::create('sesiones_usuario', function (Blueprint $table) {
            $table->char('id_sesion', 36)->primary();
            $table->integer('id_usuario');
            $table->binary('token_hash', length: 32)->unique('uq_sesiones_token_hash');
            $table->string('ip_origen', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('expira_en');
            $table->timestamp('revocada_en')->nullable();

            $table->index(['id_usuario', 'revocada_en', 'expira_en'], 'idx_sesiones_usuario_estado');
            $table->foreign('id_usuario', 'fk_sesiones_usuario')
                ->references('id_usuario')->on('usuarios')->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sesiones_usuario MODIFY token_hash BINARY(32) NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sesiones_usuario');
    }
};
