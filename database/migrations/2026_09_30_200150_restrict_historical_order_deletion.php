<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_detalle', function (Blueprint $table): void {
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['id_orden'] : 'fk_detalle_orden');
            $table->foreign('id_orden', 'fk_detalle_orden')->references('id_orden')->on('ordenes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_detalle', function (Blueprint $table): void {
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['id_orden'] : 'fk_detalle_orden');
            $table->foreign('id_orden', 'fk_detalle_orden')->references('id_orden')->on('ordenes')->cascadeOnDelete();
        });
    }
};
