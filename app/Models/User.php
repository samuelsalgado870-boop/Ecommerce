<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $fillable = ['nombre', 'email'];
    protected $hidden = ['password_hash', 'remember_token', 'intentos_fallidos', 'bloqueado_hasta'];
    protected $casts = [
        'activo' => 'boolean', 'password_hash' => 'hashed',
        'bloqueado_hasta' => 'datetime', 'ultimo_acceso' => 'datetime',
        'fecha_creacion' => 'datetime', 'intentos_fallidos' => 'integer',
    ];

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function disponible(): bool
    {
        return $this->activo && (! $this->bloqueado_hasta || $this->bloqueado_hasta->isPast());
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'usuario_roles', 'id_usuario', 'id_rol')
            ->withPivot(['asignado_por', 'asignado_en', 'expira_en']);
    }

    public function rolesVigentes(): BelongsToMany
    {
        return $this->roles()->where('roles.activo', true)
            ->where(function ($query): void {
                $query->whereNull('usuario_roles.expira_en')->orWhere('usuario_roles.expira_en', '>', now());
            });
    }

    public function tieneRol(string $codigo): bool
    {
        return $this->disponible() && $this->rolesVigentes()->where('codigo', $codigo)->exists();
    }

    public function tienePermiso(string $codigo): bool
    {
        return $this->disponible() && $this->rolesVigentes()
            ->whereHas('permisos', fn ($query) => $query->where('codigo', $codigo)->where('activo', true))->exists();
    }

    public function codigosPermisos(): \Illuminate\Support\Collection
    {
        return $this->rolesVigentes()
            ->join('rol_permisos', 'rol_permisos.id_rol', '=', 'roles.id_rol')
            ->join('permisos', 'permisos.id_permiso', '=', 'rol_permisos.id_permiso')
            ->where('permisos.activo', true)->distinct()->pluck('permisos.codigo');
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(Orden::class, 'id_usuario');
    }
}
