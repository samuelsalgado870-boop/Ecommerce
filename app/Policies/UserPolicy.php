<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function view(User $actor, User $usuario): bool
    {
        return $actor->is($usuario) || $actor->tienePermiso('usuarios.read');
    }

    public function update(User $actor, User $usuario): bool
    {
        return $actor->is($usuario) || ($actor->tienePermiso('usuarios.update') && $this->canManage($actor, $usuario));
    }

    public function deactivate(User $actor, User $usuario): bool
    {
        return ! $actor->is($usuario) && $actor->tienePermiso('usuarios.update')
            && $this->canManage($actor, $usuario);
    }

    private function canManage(User $actor, User $usuario): bool
    {
        if ($usuario->roles()->where('codigo', 'superadministrador')->exists() && ! $actor->tieneRol('superadministrador')) {
            return false;
        }

        return $usuario->codigosPermisos()->diff($actor->codigosPermisos())->isEmpty();
    }
}
