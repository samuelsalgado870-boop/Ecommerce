<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * auditoria records application events; auditoria_cambios records explicit data changes.
 * Both participate in the caller's transaction. Never persist request bodies or headers.
 */
class AuditService
{
    private const SAFE_KEYS = [
        'id_usuario', 'id_producto', 'id_orden', 'id_rol', 'codigo', 'activo',
        'sku', 'precio', 'stock', 'tipo', 'cantidad', 'delta_stock', 'stock_anterior',
        'stock_nuevo', 'estado_orden', 'estado_pago', 'estado_envio', 'total',
        'expira_en', 'roles',
    ];

    public function sanitize(array $values): array
    {
        $safe = [];
        foreach ($values as $key => $value) {
            if (in_array($key, self::SAFE_KEYS, true)) {
                $safe[$key] = is_array($value) ? $this->sanitize($value) : $value;
            }
        }

        return $safe;
    }

    public function record(?User $actor, string $action, string $table, int $id, array $before = [], array $after = []): void
    {
        $before = $this->sanitize($before);
        $after = $this->sanitize($after);
        $request = app()->bound('request') ? request() : null;
        $requestId = $request?->attributes->get('backend_request_id') ?? (string) Str::uuid();
        $request?->attributes->set('backend_request_id', $requestId);
        DB::table('auditoria')->insert([
            'id_usuario' => $actor?->getKey(), 'accion' => $action,
            'modulo' => $table, 'entidad' => $table, 'id_entidad' => (string) $id,
            'valores_anteriores' => json_encode($before, JSON_THROW_ON_ERROR),
            'valores_nuevos' => json_encode($after, JSON_THROW_ON_ERROR),
            'request_id' => $requestId, 'resultado' => 'OK', 'fecha_hora' => now(),
        ]);
        if (in_array($action, ['CREATE', 'UPDATE', 'DEACTIVATE', 'ASSIGN_ROLE', 'REVOKE_ROLE', 'CANCEL', 'INVENTORY'], true)) {
            DB::table('auditoria_cambios')->insert([
                'nombre_tabla' => $table, 'operacion' => $before === [] ? 'INSERT' : 'UPDATE',
                'id_usuario_aplicacion' => $actor?->getKey(),
                'datos_anteriores' => json_encode($before, JSON_THROW_ON_ERROR),
                'datos_nuevos' => json_encode($after, JSON_THROW_ON_ERROR), 'fecha' => now(),
            ]);
        }
    }
}
