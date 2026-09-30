<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $names = array_column(Schema::getIndexes('ordenes'), 'name');
        foreach (['idx_ordenes_usuario', 'idx_ordenes_fecha'] as $name) {
            if (in_array($name, $names, true)) {
                Schema::table('ordenes', fn (Blueprint $table) => $table->dropIndex($name));
            }
        }
    }

    public function down(): void
    {
        $names = array_column(Schema::getIndexes('ordenes'), 'name');
        foreach (['idx_ordenes_usuario' => 'id_usuario', 'idx_ordenes_fecha' => 'fecha_orden'] as $name => $column) {
            if (! in_array($name, $names, true)) {
                Schema::table('ordenes', fn (Blueprint $table) => $table->index($column, $name));
            }
        }
    }
};
