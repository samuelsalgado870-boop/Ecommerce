<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';

    protected $primaryKey = 'id_producto';

    public $timestamps = false;

    protected $fillable = [
        'sku',
        'nombre',
        'precio',
        'stock',
        'version',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'stock' => 'integer',
        'version' => 'integer',
    ];
}