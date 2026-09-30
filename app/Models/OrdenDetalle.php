<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenDetalle extends Model
{
    protected $table = 'ordenes_detalle';
    protected $primaryKey = 'id_detalle';
    public $timestamps = false;
    protected $casts = ['cantidad' => 'integer', 'precio_unitario' => 'decimal:2'];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class, 'id_orden');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }
}