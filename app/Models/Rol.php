<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rol extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id_rol';
    public $timestamps = false;
    protected $casts = ['activo' => 'boolean', 'es_sistema' => 'boolean'];

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permisos', 'id_rol', 'id_permiso')->withPivot('asignado_en');
    }
}