<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Orden extends Model
{
    protected $table = 'ordenes';
    protected $primaryKey = 'id_orden';
    public $timestamps = false;
    protected $casts = ['total' => 'decimal:2', 'fecha_orden' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new \LogicException('Las órdenes se cancelan; no se eliminan físicamente.');
        });
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(OrdenDetalle::class, 'id_orden');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}