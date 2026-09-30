<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'productos';

    protected $primaryKey = 'id_producto';

    public $timestamps = false;

    protected $fillable = ['sku', 'nombre', 'precio', 'activo'];
    protected $hidden = ['version'];
    protected $casts = ['precio' => 'decimal:2', 'stock' => 'integer', 'version' => 'integer', 'activo' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (Producto $product): void {
            if ($product->isDirty('stock') || $product->isDirty('version')) {
                throw new \LogicException('El stock y la versión se modifican exclusivamente con InventoryService.');
            }
        });
        static::deleting(function (): void {
            throw new \LogicException('Los productos se desactivan para conservar el historial.');
        });
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'id_producto');
    }
}