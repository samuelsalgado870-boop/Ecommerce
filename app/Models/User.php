<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $fillable = [
        'id_rol',
        'nombre',
        'email',
        'password_hash',
        'activo',
        'intentos_fallidos',
        'bloqueado_hasta',
        'ultimo_acceso',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'bloqueado_hasta' => 'datetime',
        'ultimo_acceso' => 'datetime',
        'fecha_creacion' => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}
