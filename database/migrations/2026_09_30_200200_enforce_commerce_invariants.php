<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function invariants(): array
    {
        $checks = [
            'productos' => ['precio >= 0 AND stock >= 0 AND version > 0'],
            'ordenes_detalle' => ['cantidad > 0 AND precio_unitario >= 0'],
            'ordenes' => [
                'total >= 0',
                "estado_orden IN ('pendiente', 'confirmada', 'procesando', 'cancelada', 'completada')",
                "estado_pago IN ('pendiente', 'aprobado', 'rechazado', 'reembolsado', 'parcialmente_reembolsado')",
                "estado_envio IN ('pendiente', 'preparando', 'despachado', 'en_transito', 'entregado', 'devuelto')",
                '(clave_idempotencia IS NULL AND huella_solicitud IS NULL) OR (clave_idempotencia IS NOT NULL AND huella_solicitud IS NOT NULL)',
            ],
            'movimientos_inventario' => [
                'cantidad > 0 AND delta_stock <> 0 AND stock_anterior >= 0 AND stock_nuevo >= 0',
                'stock_nuevo = stock_anterior + delta_stock AND cantidad = ABS(delta_stock)',
                "(tipo = 'ENTRADA' AND delta_stock > 0) OR (tipo = 'SALIDA' AND delta_stock < 0) OR tipo = 'AJUSTE'",
            ],
        ];
        if (DB::getDriverName() === 'sqlite') {
            return $checks;
        }
        // Existing MySQL migrations already protect price, quantity and non-negative stock.
        $checks['productos'] = ['version > 0'];
        unset($checks['ordenes_detalle']);

        return $checks;
    }

    public function up(): void
    {
        foreach ($this->invariants() as $table => $conditions) {
            foreach ($conditions as $index => $condition) {
                $name = 'foundation_'.$table.'_'.$index;
                if (DB::getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE $table ADD CONSTRAINT $name CHECK ($condition)");
                } elseif (DB::getDriverName() === 'sqlite') {
                    $qualified = preg_replace_callback(
                        '/\b(precio|stock|version|cantidad|precio_unitario|total|estado_orden|estado_pago|estado_envio|clave_idempotencia|huella_solicitud|delta_stock|stock_anterior|stock_nuevo|tipo)\b/',
                        fn (array $match) => 'NEW.'.$match[0], $condition,
                    );
                    foreach (['INSERT', 'UPDATE'] as $event) {
                        DB::unprepared("CREATE TRIGGER {$name}_{$event} BEFORE $event ON $table
                            FOR EACH ROW WHEN NOT ($qualified)
                            BEGIN SELECT RAISE(ABORT, 'commerce invariant'); END");
                    }
                } else {
                    throw new RuntimeException('Backend compatible con MySQL 8.0.16+ o SQLite para pruebas.');
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->invariants() as $table => $conditions) {
            foreach ($conditions as $index => $condition) {
                $name = 'foundation_'.$table.'_'.$index;
                if (DB::getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE $table DROP CHECK $name");
                } else {
                    foreach (['INSERT', 'UPDATE'] as $event) {
                        DB::unprepared("DROP TRIGGER IF EXISTS {$name}_{$event}");
                    }
                }
            }
        }
    }
};
